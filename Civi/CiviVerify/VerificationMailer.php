<?php

declare(strict_types=1);

namespace Civi\CiviVerify;

use Civi\Api4\Contact;
use Civi\Api4\Email;
use Civi\Api4\MessageTemplate;
use Civi\WorkflowMessage\WorkflowMessage;
use CRM_CiviVerify_ExtensionUtil as E;

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
    $workflowName = $this->validateWorkflowName($input['workflow_name'] ?? NULL);
    $template = $this->resolveTemplate(
      $workflowName,
      $input['message_template_id'] ?? NULL,
      $recipient['preferred_language']
    );
    $templateParams = $this->validateTemplateParams($input['template_params'] ?? []);
    $draft = $this->draftRegistry->draft($workflowName);
    $targetKey = trim((string) ($input['target_key'] ?? ''));
    $target = $targetKey === '' ? $draft['target'] : $this->draftRegistry->target($targetKey);

    $issued = $this->issuer->issue([
      'purpose' => $input['purpose'] ?? '',
      'contact_id' => $contactId,
      'entity_name' => $input['entity_name'] ?? NULL,
      'entity_id' => $input['entity_id'] ?? NULL,
      'ttl' => $input['ttl'] ?? $draft['ttl'],
      'metadata' => $input['metadata'] ?? NULL,
      'allow_unbound' => FALSE,
    ]);
    $confirmationUrl = $this->urlBuilder->build($issued['token'], $target);
    $expiresDate = $this->formatExpiresDate(
      (string) $issued['expires_date'],
      $this->templateLanguage($template, $recipient['preferred_language'])
    );
    $context = [
      'contactId' => $contactId,
      'civiverifyConfirmationUrl' => $confirmationUrl,
      'civiverifyConfirmationCode' => (string) $issued['code'],
      'civiverifyExpiresDate' => $expiresDate,
      'civiverifyPurpose' => (string) $issued['purpose'],
      'civiverifyUuid' => (string) $issued['uuid'],
      'civiverifyEntityName' => (string) ($issued['entity_name'] ?? ''),
      'civiverifyEntityId' => (string) ($issued['entity_id'] ?? ''),
    ];
    $templateParams = array_merge($templateParams, [
      'civiverifyConfirmationUrl' => $confirmationUrl,
      'civiverifyConfirmationCode' => (string) $issued['code'],
      'civiverifyExpiresDate' => $expiresDate,
      'civiverifyPurpose' => (string) $issued['purpose'],
      'civiverifyUuid' => (string) $issued['uuid'],
      'civiverifyEntityName' => (string) ($issued['entity_name'] ?? ''),
      'civiverifyEntityId' => (string) ($issued['entity_id'] ?? ''),
    ]);

    try {
      $model = WorkflowMessage::create($workflowName, [
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

    unset($issued['token'], $issued['code']);
    return $issued + [
      'mail_status' => 'sent',
      'message_template_id' => (int) $template['id'],
      'workflow_name' => $workflowName,
      'email_id' => (int) $recipient['email_id'],
    ];
  }

  /** Format the expiry timestamp for the language of the rendered template. */
  private function formatExpiresDate(string $value, ?string $language): string {
    if (!class_exists(\IntlDateFormatter::class)) {
      return $value;
    }
    $date = \DateTimeImmutable::createFromFormat(
      '!Y-m-d H:i:s',
      $value,
      new \DateTimeZone('UTC')
    );
    if (!$date) {
      return $value;
    }
    $format = match (strtolower((string) $language)) {
      'de', 'de_de' => ['de_DE', "d. MMMM y, HH:mm 'Uhr'"],
      'en', 'en_us' => ['en_US', 'MMMM d, y, h:mm a'],
      'fr', 'fr_fr' => ['fr_FR', "d MMMM y 'à' HH:mm"],
      'sv', 'sv_se' => ['sv_SE', "d MMMM y 'kl.' HH:mm"],
      default => NULL,
    };
    if ($format === NULL) {
      return $value;
    }
    [$locale, $pattern] = $format;
    $formatter = new \IntlDateFormatter(
      $locale,
      \IntlDateFormatter::NONE,
      \IntlDateFormatter::NONE,
      'UTC',
      \IntlDateFormatter::GREGORIAN,
      $pattern
    );
    return $formatter->format($date) ?: $value;
  }

  /** Prefer the concrete template's language suffix over the contact profile. */
  private function templateLanguage(array $template, ?string $fallback): ?string {
    $workflow = (string) ($template['workflow_name'] ?? '');
    if (preg_match('/_([a-z]{2})(?:_[a-z]{2})?$/i', $workflow, $matches)) {
      return strtolower($matches[1]);
    }
    return $fallback;
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

  private function validateWorkflowName(mixed $workflowName): string {
    $workflowName = trim((string) ($workflowName ?? ''));
    if ($workflowName === '') {
      throw new \CRM_Core_Exception(E::ts('A workflow name is required.'));
    }
    if (!preg_match('/^[a-z][a-z0-9_]{0,127}$/', $workflowName)) {
      throw new \CRM_Core_Exception(E::ts('Workflow name is invalid.'));
    }
    return $workflowName;
  }

  private function resolveTemplate(string $workflowName, mixed $messageTemplateId, ?string $language): array {
    if ($messageTemplateId !== NULL) {
      $messageTemplateId = (int) $messageTemplateId;
      if ($messageTemplateId < 1) {
        throw new \CRM_Core_Exception(E::ts('Message template ID must be a positive integer.'));
      }
      return $this->loadExplicitTemplate($messageTemplateId);
    }

    $query = MessageTemplate::get(FALSE)
      ->setLanguage($language)
      ->setTranslationMode('fuzzy')
      ->addSelect('id', 'workflow_name', 'msg_subject', 'msg_text', 'msg_html')
      ->addWhere('is_default', '=', TRUE)
      ->addWhere('is_reserved', '=', FALSE)
      ->addWhere('is_active', '=', TRUE)
      ->setLimit(1);
    $query->addWhere('workflow_name', '=', $workflowName);
    $template = $query->execute()->first();
    if (!$template) {
      throw new \CRM_Core_Exception(E::ts('The active default message template for this workflow does not exist.'));
    }
    $this->validateTemplateTokens($template);
    return $template;
  }

  /** Load exactly the template selected by the calling extension, without language fallback. */
  private function loadExplicitTemplate(int $messageTemplateId): array {
    $template = MessageTemplate::get(FALSE)
      ->addSelect('id', 'workflow_name', 'msg_subject', 'msg_text', 'msg_html', 'is_active', 'is_reserved')
      ->addWhere('id', '=', $messageTemplateId)
      ->setLimit(1)
      ->execute()
      ->first();
    if (!$template) {
      throw new \CRM_Core_Exception(E::ts('The selected message template does not exist.'));
    }
    if (!empty($template['is_reserved'])) {
      throw new \CRM_Core_Exception(E::ts('The selected message template is reserved and cannot be used.'));
    }
    if (empty($template['is_active'])) {
      throw new \CRM_Core_Exception(E::ts('The selected message template is inactive.'));
    }
    $this->validateTemplateTokens($template);
    return $template;
  }

  private function validateTemplateTokens(array $template): void {
    $body = (string) ($template['msg_text'] ?? '') . (string) ($template['msg_html'] ?? '');
    if (!str_contains($body, '{civiverify.confirmation_url}')
      && !str_contains($body, '{$civiverifyConfirmationUrl}')) {
      throw new \CRM_Core_Exception(E::ts('The message template must contain the CiviVerify confirmation URL token.'));
    }
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
