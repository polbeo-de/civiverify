<?php

declare(strict_types=1);

/**
 * End-to-end test for the optional CiviRules integration.
 *
 * Requires CiviRules 3.38+ and an enabled CiviVerify extension.
 */

$assertions = 0;
$contactId = NULL;
$ruleIds = [];
$ruleActionIds = [];
$tokenIds = [];

$assert = static function (bool $condition, string $message) use (&$assertions): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
  $assertions++;
};

try {
  $triggerRecords = \Civi\Api4\CiviRulesTrigger::get(FALSE)
    ->addWhere('name', 'LIKE', 'civiverify_token_%')
    ->addWhere('is_active', '=', TRUE)
    ->execute()
    ->indexBy('name');
  $action = \Civi\Api4\CiviRulesAction::get(FALSE)
    ->addWhere('name', '=', 'set_field_generic')
    ->addWhere('is_active', '=', TRUE)
    ->execute()
    ->single();
  $expectedTriggers = [
    'civiverify_token_issued' => 'CRM_CiviVerify_CiviRules_Trigger_Issued',
    'civiverify_token_verified' => 'CRM_CiviVerify_CiviRules_Trigger_Verified',
    'civiverify_token_revoked' => 'CRM_CiviVerify_CiviRules_Trigger_Revoked',
    'civiverify_token_expired' => 'CRM_CiviVerify_CiviRules_Trigger_Expired',
  ];
  $assert(count($triggerRecords) === 4, 'The number of active CiviVerify triggers is invalid.');
  foreach ($expectedTriggers as $name => $className) {
    $assert(($triggerRecords[$name]['class_name'] ?? NULL) === $className, $name . ' is invalid.');
  }

  $triggerObject = new CRM_CiviVerify_CiviRules_Trigger_Verified();
  $triggerObject->setTriggerParams(serialize([
    'purpose' => 'integration.civirules',
    'entity_name' => 'Activity',
  ]));
  $providedEntities = array_keys($triggerObject->getProvidedEntities());
  $assert($providedEntities === ['CiviVerifyToken', 'Contact', 'Activity'], 'Provided entities are invalid.');

  $contact = \Civi\Api4\Contact::create(FALSE)
    ->addValue('contact_type', 'Individual')
    ->addValue('first_name', 'CiviRules')
    ->addValue('last_name', 'Integration Test')
    ->addValue('job_title', 'not fired')
    ->execute()
    ->single();
  $contactId = (int) $contact['id'];

  $addRule = static function (string $triggerName, string $purpose, string $value) use (
    $action,
    $triggerRecords,
    &$ruleIds,
    &$ruleActionIds
  ): void {
    $rule = \Civi\Api4\CiviRulesRule::create(FALSE)
      ->addValue('label', 'CiviVerify integration test: ' . $triggerName)
      ->addValue('trigger_id', (int) $triggerRecords[$triggerName]['id'])
      ->addValue('trigger_params', serialize([
        'purpose' => $purpose,
        'entity_name' => 'Contact',
      ]))
      ->addValue('is_active', TRUE)
      ->addValue('is_debug', FALSE)
      ->execute()
      ->single();
    $ruleIds[] = (int) $rule['id'];

    $ruleAction = \Civi\Api4\CiviRulesRuleAction::create(FALSE)
      ->addValue('rule_id', (int) $rule['id'])
      ->addValue('action_id', (int) $action['id'])
      ->addValue('action_params', [
        'entity' => 'Contact',
        'field' => 'job_title',
        'value' => $value,
      ])
      ->addValue('is_active', TRUE)
      ->addValue('weight', 1)
      ->execute()
      ->single();
    $ruleActionIds[] = (int) $ruleAction['id'];
  };

  $setJobTitle = static function (string $value) use ($contactId): void {
    \Civi\Api4\Contact::update(FALSE)
      ->addValue('job_title', $value)
      ->addWhere('id', '=', $contactId)
      ->execute();
  };

  $getJobTitle = static function () use ($contactId): ?string {
    return \Civi\Api4\Contact::get(FALSE)
      ->addSelect('job_title')
      ->addWhere('id', '=', $contactId)
      ->execute()
      ->single()['job_title'];
  };

  $issue = static function (string $purpose) use ($contactId, &$tokenIds): array {
    $issued = civicrm_api4('CiviVerifyToken', 'issue', [
      'checkPermissions' => FALSE,
      'purpose' => $purpose,
      'contactId' => $contactId,
      'entityName' => 'Contact',
      'entityId' => $contactId,
      'ttl' => 600,
    ])->first();
    $tokenIds[] = (int) $issued['id'];
    return $issued;
  };

  $addRule('civiverify_token_verified', 'integration.civirules.verified', 'verified fired');
  $mismatch = $issue('integration.different');
  civicrm_api4('CiviVerifyToken', 'verify', [
    'checkPermissions' => FALSE,
    'token' => $mismatch['token'],
  ]);
  $assert($getJobTitle() === 'not fired', 'Purpose filter did not suppress the rule.');

  $matching = $issue('integration.civirules.verified');
  $verified = civicrm_api4('CiviVerifyToken', 'verify', [
    'checkPermissions' => FALSE,
    'token' => $matching['token'],
  ])->first();
  $assert($verified['result'] === 'verified', 'Matching verification failed.');
  $assert($getJobTitle() === 'verified fired', 'Verified action did not update the bound entity.');

  $setJobTitle('not fired');
  $addRule('civiverify_token_issued', 'integration.civirules.issued', 'issued fired');
  $issue('integration.civirules.issued');
  $assert($getJobTitle() === 'issued fired', 'Issued action did not update the bound entity.');

  $setJobTitle('not fired');
  $addRule('civiverify_token_revoked', 'integration.civirules.revoked', 'revoked fired');
  $revocable = $issue('integration.civirules.revoked');
  civicrm_api4('CiviVerifyToken', 'revoke', [
    'checkPermissions' => FALSE,
    'id' => (int) $revocable['id'],
    'reason' => 'CiviRules integration test',
  ]);
  $assert($getJobTitle() === 'revoked fired', 'Revoked action did not update the bound entity.');

  $setJobTitle('not fired');
  $addRule('civiverify_token_expired', 'integration.civirules.expired', 'expired fired');
  $expirable = $issue('integration.civirules.expired');
  CRM_Core_DAO::executeQuery(
    'UPDATE civicrm_civiverify_token SET expires_date = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE) WHERE id = %1',
    [1 => [(int) $expirable['id'], 'Integer']]
  );
  civicrm_api4('CiviVerifyToken', 'cleanup', [
    'checkPermissions' => FALSE,
    'batchSize' => 50,
    'retentionDays' => 365,
  ]);
  $assert($getJobTitle() === 'expired fired', 'Expired action did not update the bound entity.');

  printf("PASS: CiviVerify CiviRules integration test (%d assertions)\n", $assertions);
}
finally {
  if ($ruleActionIds !== []) {
    \Civi\Api4\CiviRulesRuleAction::delete(FALSE)->addWhere('id', 'IN', $ruleActionIds)->execute();
  }
  if ($ruleIds !== []) {
    \Civi\Api4\CiviRulesRule::delete(FALSE)->addWhere('id', 'IN', $ruleIds)->execute();
  }
  if ($tokenIds !== []) {
    $ids = implode(',', array_map('intval', $tokenIds));
    CRM_Core_DAO::executeQuery('DELETE FROM civicrm_civiverify_token WHERE id IN (' . $ids . ')');
  }
  if ($contactId !== NULL) {
    \Civi\Api4\Contact::delete(FALSE)->addWhere('id', '=', $contactId)->execute();
  }
}
