<?php

declare(strict_types=1);

/**
 * End-to-end test for template-driven verification mail.
 *
 * Mail is written to a temporary log and never delivered.
 */

$assertions = 0;
$contactId = NULL;
$emailId = NULL;
$tokenIds = [];
$templateIds = [];
$mailLog = sys_get_temp_dir() . '/civiverify-mail-' . getmypid() . '.log';

if (!defined('CIVICRM_MAIL_LOG')) {
  define('CIVICRM_MAIL_LOG', $mailLog);
}

$assert = static function (bool $condition, string $message) use (&$assertions): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
  $assertions++;
};

$tokenCount = static function (): int {
  return (int) CRM_Core_DAO::singleValueQuery('SELECT COUNT(*) FROM civicrm_civiverify_token');
};

try {
  @unlink($mailLog);
  $contact = \Civi\Api4\Contact::create(FALSE)
    ->addValue('contact_type', 'Individual')
    ->addValue('first_name', 'CiviVerify')
    ->addValue('last_name', 'Mail Test')
    ->execute()
    ->single();
  $contactId = (int) $contact['id'];
  $email = \Civi\Api4\Email::create(FALSE)
    ->addValue('contact_id', $contactId)
    ->addValue('email', 'civiverify-test@example.invalid')
    ->addValue('is_primary', TRUE)
    ->execute()
    ->single();
  $emailId = (int) $email['id'];

  $before = $tokenCount();
  $sent = civicrm_api4('CiviVerifyToken', 'issueAndSend', [
    'checkPermissions' => FALSE,
    'purpose' => 'integration.issue_and_send',
    'contactId' => $contactId,
    'entityName' => 'Contact',
    'entityId' => $contactId,
    'ttl' => 600,
    'templateParams' => ['integrationMarker' => 'safe custom value'],
  ])->single();
  $tokenIds[] = (int) $sent['id'];

  $assert($tokenCount() === $before + 1, 'The verification was not persisted exactly once.');
  $assert($sent['mail_status'] === 'sent', 'The mail action did not report success.');
  $assert($sent['workflow_name'] === 'civiverify_confirmation', 'The default workflow was not selected.');
  $assert((int) $sent['email_id'] === $emailId, 'The primary email address was not selected.');
  $assert(!array_key_exists('token', $sent), 'issueAndSend exposed the raw token.');
  $assert(!array_key_exists('confirmation_url', $sent), 'issueAndSend exposed the confirmation URL.');
  $assert(is_file($mailLog), 'CiviCRM did not write the message to the mail log.');

  $mail = file_get_contents($mailLog);
  $assert(is_string($mail) && str_contains($mail, 'civiverify-test@example.invalid'), 'Recipient is absent from mail.');
  $assert(str_contains($mail, 'CiviVerify Mail Test'), 'Contact token was not rendered.');
  $assert(
    preg_match('~/civicrm/verify\?token=([A-Za-z0-9_-]{43})~', $mail, $matches) === 1,
    'Rendered mail does not contain a valid confirmation link.'
  );
  $verified = civicrm_api4('CiviVerifyToken', 'verify', [
    'checkPermissions' => FALSE,
    'token' => $matches[1],
  ])->single();
  $assert($verified['result'] === 'verified', 'The token extracted from mail could not be verified.');

  $customTemplate = \Civi\Api4\MessageTemplate::create(FALSE)
    ->addValue('msg_title', 'CiviVerify integration custom template')
    ->addValue('msg_subject', 'Custom verification {$integrationMarker}')
    ->addValue(
      'msg_html',
      '<p>{$integrationMarker}</p><p><a href="{$civiverifyConfirmationUrl}">Verify</a></p>'
    )
    ->addValue('is_default', TRUE)
    ->addValue('is_reserved', FALSE)
    ->addValue('is_active', TRUE)
    ->execute()
    ->single();
  $templateIds[] = (int) $customTemplate['id'];
  $customSent = civicrm_api4('CiviVerifyToken', 'issueAndSend', [
    'checkPermissions' => FALSE,
    'purpose' => 'integration.custom_template',
    'contactId' => $contactId,
    'messageTemplateId' => (int) $customTemplate['id'],
    'templateParams' => ['integrationMarker' => 'custom-template-marker'],
  ])->single();
  $tokenIds[] = (int) $customSent['id'];
  $assert(
    (int) $customSent['message_template_id'] === (int) $customTemplate['id'],
    'The concrete message template was not selected.'
  );
  $customMail = file_get_contents($mailLog);
  $assert(
    is_string($customMail) && str_contains($customMail, 'custom-template-marker'),
    'Additional template parameters were not rendered.'
  );

  $invalidTemplate = \Civi\Api4\MessageTemplate::create(FALSE)
    ->addValue('msg_title', 'CiviVerify integration invalid template')
    ->addValue('msg_subject', 'Missing confirmation URL')
    ->addValue('msg_html', '<p>No verification link here.</p>')
    ->addValue('is_default', TRUE)
    ->addValue('is_reserved', FALSE)
    ->addValue('is_active', TRUE)
    ->execute()
    ->single();
  $templateIds[] = (int) $invalidTemplate['id'];
  $beforeRejected = $tokenCount();
  try {
    civicrm_api4('CiviVerifyToken', 'issueAndSend', [
      'checkPermissions' => FALSE,
      'purpose' => 'integration.invalid_template',
      'contactId' => $contactId,
      'messageTemplateId' => (int) $invalidTemplate['id'],
    ]);
    throw new RuntimeException('A template without the confirmation URL unexpectedly succeeded.');
  }
  catch (CRM_Core_Exception $e) {
    $assert(
      str_contains($e->getMessage(), 'must contain the CiviVerify confirmation URL token'),
      'The invalid template failed for an unexpected reason.'
    );
  }
  $assert($tokenCount() === $beforeRejected, 'Template validation issued an unusable token.');

  printf("PASS: CiviVerify issueAndSend integration test (%d assertions)\n", $assertions);
}
finally {
  if ($templateIds !== []) {
    \Civi\Api4\MessageTemplate::delete(FALSE)->addWhere('id', 'IN', $templateIds)->execute();
  }
  if ($tokenIds !== []) {
    $ids = implode(',', array_map('intval', $tokenIds));
    CRM_Core_DAO::executeQuery('DELETE FROM civicrm_civiverify_token WHERE id IN (' . $ids . ')');
  }
  if ($emailId !== NULL) {
    \Civi\Api4\Email::delete(FALSE)->addWhere('id', '=', $emailId)->execute();
  }
  if ($contactId !== NULL) {
    \Civi\Api4\Contact::delete(FALSE)->addWhere('id', '=', $contactId)->execute();
  }
  @unlink($mailLog);
}
