<?php

declare(strict_types=1);

/**
 * End-to-end test for the no-code CiviRules issue-and-send action.
 *
 * Mail is written to a temporary log and never delivered.
 */

$assertions = 0;
$contactId = NULL;
$emailId = NULL;
$ruleId = NULL;
$ruleActionId = NULL;
$tokenIds = [];
$mailLog = sys_get_temp_dir() . '/civiverify-civirules-mail-' . getmypid() . '.log';

if (!defined('CIVICRM_MAIL_LOG')) {
  define('CIVICRM_MAIL_LOG', $mailLog);
}

$assert = static function (bool $condition, string $message) use (&$assertions): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
  $assertions++;
};

try {
  @unlink($mailLog);
  $trigger = \Civi\Api4\CiviRulesTrigger::get(FALSE)
    ->addWhere('name', '=', 'changed_contact')
    ->addWhere('is_active', '=', TRUE)
    ->execute()
    ->single();
  $action = \Civi\Api4\CiviRulesAction::get(FALSE)
    ->addWhere('name', '=', 'civiverify_issue_and_send')
    ->addWhere('is_active', '=', TRUE)
    ->execute()
    ->single();
  $assert(
    $action['class_name'] === 'CRM_CiviVerify_CiviRules_Action_IssueAndSend',
    'The CiviRules action class is not registered.'
  );

  $contact = \Civi\Api4\Contact::create(FALSE)
    ->addValue('contact_type', 'Individual')
    ->addValue('first_name', 'CiviRules')
    ->addValue('last_name', 'Mail Action Test')
    ->execute()
    ->single();
  $contactId = (int) $contact['id'];
  $email = \Civi\Api4\Email::create(FALSE)
    ->addValue('contact_id', $contactId)
    ->addValue('email', 'civiverify-civirules@example.invalid')
    ->addValue('is_primary', TRUE)
    ->execute()
    ->single();
  $emailId = (int) $email['id'];

  $rule = \Civi\Api4\CiviRulesRule::create(FALSE)
    ->addValue('label', 'CiviVerify integration test: issue and send')
    ->addValue('trigger_id', (int) $trigger['id'])
    ->addValue('is_active', TRUE)
    ->addValue('is_debug', FALSE)
    ->execute()
    ->single();
  $ruleId = (int) $rule['id'];
  $ruleAction = \Civi\Api4\CiviRulesRuleAction::create(FALSE)
    ->addValue('rule_id', $ruleId)
    ->addValue('action_id', (int) $action['id'])
    ->addValue('action_params', [
      'purpose' => 'integration.civirules.issue_and_send',
      'ttl' => 600,
      'workflow_name' => 'civiverify_confirmation',
      'entity_name' => '',
      'template_params' => ['integrationMarker' => 'from CiviRules'],
    ])
    ->addValue('is_active', TRUE)
    ->addValue('weight', 1)
    ->execute()
    ->single();
  $ruleActionId = (int) $ruleAction['id'];

  \Civi\Api4\Contact::update(FALSE)
    ->addValue('job_title', 'Trigger the verification mail')
    ->addWhere('id', '=', $contactId)
    ->execute();

  $tokens = \Civi\Api4\CiviVerifyToken::get(FALSE)
    ->addSelect('id', 'purpose', 'status', 'contact_id')
    ->addWhere('contact_id', '=', $contactId)
    ->addWhere('purpose', '=', 'integration.civirules.issue_and_send')
    ->execute()
    ->getArrayCopy();
  $assert(count($tokens) === 1, 'The CiviRules action did not issue exactly one verification.');
  $tokenIds[] = (int) $tokens[0]['id'];
  $assert($tokens[0]['status'] === 'pending', 'The CiviRules verification is not pending.');
  $assert(is_file($mailLog), 'The CiviRules action did not send a message.');
  $mail = file_get_contents($mailLog);
  $assert(
    is_string($mail) && str_contains($mail, 'civiverify-civirules@example.invalid'),
    'The CiviRules message has the wrong recipient.'
  );
  $assert(
    preg_match('~/civicrm/verify\?token=([A-Za-z0-9_-]{43})~', $mail, $matches) === 1,
    'The CiviRules message has no valid confirmation link.'
  );
  $verified = civicrm_api4('CiviVerifyToken', 'verify', [
    'checkPermissions' => FALSE,
    'token' => $matches[1],
  ])->single();
  $assert($verified['result'] === 'verified', 'The CiviRules-issued link could not be verified.');

  printf("PASS: CiviVerify CiviRules issueAndSend test (%d assertions)\n", $assertions);
}
finally {
  if ($ruleActionId !== NULL) {
    \Civi\Api4\CiviRulesRuleAction::delete(FALSE)->addWhere('id', '=', $ruleActionId)->execute();
  }
  if ($ruleId !== NULL) {
    \Civi\Api4\CiviRulesRule::delete(FALSE)->addWhere('id', '=', $ruleId)->execute();
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
