<?php

declare(strict_types=1);

namespace Civi\CiviVerify;

use Civi\Api4\Contact;
use Civi\Api4\Email;
use Civi\Api4\MessageTemplate;
use Civi\WorkflowMessage\WorkflowMessage;

final class VerificationMailer {

  public const DEFAULT_WORKFLOW = 'civiverify_confirmation';

  public function __construct(
    private readonly VerificationIssuer $issuer,
    private readonly VerificationManager $manager,
    private readonly ConfirmationUrlBuilder $urlBuilder,
    private readonly VerifyDraftRegistry $draftRegistry,
  ) {}

  public function issueAndSend(array $input): array {
    $contactId = (int) ($input['contact_id'] ?? 0);
    if ($contactId < 1) {
      throw new \CRM_Core_Exception('A recipient contact is required.');
    }
    $recipient = $this->resolveRecipient($contactId, $input['email_id'] ?? NULL);
    $template = $this->resolveTemplate(
      $input['workflow_name'] ?? NULL,
      $input['message_template_id'] ?? NULL,
      $recipient['preferred_language']
    );
    $templateParams = $this->validateTemplateParams($input['template_params'] ?? []);
    $draft = $this->draftRegistry->draft((string) $template['workflow_name']);

    $issued = $this->issuer->issue([
      'purpose' => $input['purpose'] ?? '',
      'contact_id' => $contactId,
      'entity_name' => $input['entity_name'] ?? NULL,
      'entity_id' => $input['entity_id'] ?? NULL,
      'ttl' => $input['ttl'] ?? $draft['ttl'],
      'metadata' => $input['metadata'] ?? NULL,
      'allow_unbound' => FALSE,
    ]);
    $confirmationUrl = $this->urlBuilder->build($issued['token'], $draft['target']);
    $context = [
      'contactId' => $contactId,
      'civiverifyConfirmationUrl' => $confirmationUrl,
      'civiverifyExpiresDate' => (string) $issued['expires_date'],
      'civiverifyPurpose' => (string) $issued['purpose'],
      'civiverifyUuid' => (string) $issued['uuid'],
      'civiverifyEntityName' => (string) ($issued['entity_name'] ?? ''),
      'civiverifyEntityId' => (string) ($issued['entity_id'] ?? ''),
    ];
    $templateParams = array_merge($templateParams, [
      'civiverifyConfirmationUrl' => $confirmationUrl,
      'civiverifyExpiresDate' => (string) $issued['expires_date'],
      'civiverifyPurpose' => (string) $issued['purpose'],
      'civiverifyUuid' => (string) $issued['uuid'],
      'civiverifyEntityName' => (string) ($issued['entity_name'] ?? ''),
      'civiverifyEntityId' => (string) ($issued['entity_id'] ?? ''),
    ]);

    try {
      $model = WorkflowMessage::create($template['render_workflow'], [
        'tokenContext' => $context,
        'tplParams' => $templateParams,
        'envelope' => [
          'toEmail' => $recipient['email'],
          'toName' => $recipient['display_name'],
          'from' => \CRM_Core_BAO_Domain::getFromEmail(),
          'messageTemplateID' => (int) $template['id'],
        ],
      ]);
      [$sent, , , , $errorMessage] = $model->sendTemplate();
      if (!$sent) {
        throw new \RuntimeException((string) ($errorMessage ?: 'Mail transport rejected the message.'));
      }
    }
    catch (\Throwable $e) {
      try {
        $this->manager->revoke((int) $issued['id'], 'Verification email delivery failed');
      }
      catch (\Throwable $revokeError) {
        \Civi::log('civiverify')->critical(
          'Could not revoke an undelivered verification: ' . $revokeError->getMessage()
        );
      }
      throw new \CRM_Core_Exception('Verification email could not be sent.', 0, [], $e);
    }

    unset($issued['token']);
    return $issued + [
      'mail_status' => 'sent',
      'message_template_id' => (int) $template['id'],
      'workflow_name' => $template['workflow_name'],
      'email_id' => (int) $recipient['email_id'],
    ];
  }

  private function resolveRecipient(int $contactId, mixed $emailId): array {
    $contact = Contact::get(FALSE)
      ->addSelect('display_name', 'preferred_language')
      ->addWhere('id', '=', $contactId)
      ->addWhere('is_deleted', '=', FALSE)
      ->setLimit(1)
      ->execute()
      ->first();
    if (!$contact) {
      throw new \CRM_Core_Exception('The recipient contact does not exist.');
    }
    $emails = Email::get(FALSE)
      ->addSelect('id', 'email', 'on_hold', 'is_primary')
      ->addWhere('contact_id', '=', $contactId)
      ->addOrderBy('is_primary', 'DESC')
      ->addOrderBy('id', 'ASC')
      ->setLimit(1);
    if ($emailId !== NULL) {
      $emails->addWhere('id', '=', (int) $emailId);
    }
    $email = $emails->execute()->first();
    if (!$email || !filter_var($email['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
      throw new \CRM_Core_Exception('The recipient has no valid email address.');
    }
    if (!empty($email['on_hold'])) {
      throw new \CRM_Core_Exception('The selected recipient email address is on hold.');
    }
    return [
      'display_name' => (string) $contact['display_name'],
      'preferred_language' => $contact['preferred_language'] ?: NULL,
      'email_id' => (int) $email['id'],
      'email' => (string) $email['email'],
    ];
  }

  private function resolveTemplate(mixed $workflowName, mixed $messageTemplateId, ?string $language): array {
    $workflowName = trim((string) ($workflowName ?? '')) ?: NULL;
    $messageTemplateId = $messageTemplateId === NULL ? NULL : (int) $messageTemplateId;
    if ($workflowName !== NULL && $messageTemplateId !== NULL) {
      throw new \CRM_Core_Exception('Supply either workflowName or messageTemplateId, not both.');
    }
    if ($workflowName !== NULL && !preg_match('/^[a-z][a-z0-9_]{0,127}$/', $workflowName)) {
      throw new \CRM_Core_Exception('Workflow name is invalid.');
    }
    $workflowName ??= self::DEFAULT_WORKFLOW;
    $query = MessageTemplate::get(FALSE)
      ->setLanguage($language)
      ->setTranslationMode('fuzzy')
      ->addSelect('id', 'workflow_name', 'msg_subject', 'msg_text', 'msg_html')
      ->addWhere('is_default', '=', TRUE)
      ->addWhere('is_reserved', '=', FALSE)
      ->addWhere('is_active', '=', TRUE)
      ->setLimit(1);
    if ($messageTemplateId !== NULL) {
      $query->addWhere('id', '=', $messageTemplateId);
    }
    else {
      $query->addWhere('workflow_name', '=', $workflowName);
    }
    $template = $query->execute()->first();
    if (!$template) {
      throw new \CRM_Core_Exception('The selected active message template does not exist.');
    }
    $body = (string) ($template['msg_text'] ?? '') . (string) ($template['msg_html'] ?? '');
    if (!str_contains($body, '{civiverify.confirmation_url}')
      && !str_contains($body, '{$civiverifyConfirmationUrl}')) {
      throw new \CRM_Core_Exception('The message template must contain the CiviVerify confirmation URL token.');
    }
    $template['render_workflow'] = (string) ($template['workflow_name'] ?: self::DEFAULT_WORKFLOW);
    return $template;
  }

  private function validateTemplateParams(mixed $params): array {
    if (!is_array($params)) {
      throw new \CRM_Core_Exception('Template parameters must be an object.');
    }
    foreach (array_keys($params) as $key) {
      if (!is_string($key) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,127}$/', $key)) {
        throw new \CRM_Core_Exception('Template parameter names must be machine-readable keys.');
      }
      if (str_starts_with(strtolower($key), 'civiverify')) {
        throw new \CRM_Core_Exception('CiviVerify template parameters are reserved.');
      }
    }
    try {
      $json = json_encode($params, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $e) {
      throw new \CRM_Core_Exception('Template parameters must contain JSON-compatible values.', 0, [], $e);
    }
    if (strlen($json) > 16384) {
      throw new \CRM_Core_Exception('Template parameters exceed the 16 KiB limit.');
    }
    return $params;
  }

}
