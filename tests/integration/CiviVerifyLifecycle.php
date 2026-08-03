<?php

declare(strict_types=1);

/**
 * Integration smoke test for an installed CiviVerify extension.
 *
 * Run from a bootable CiviCRM directory:
 *   cv php:script /path/to/CiviVerifyLifecycle.php
 */

$assertions = 0;
$tokenIds = [];
$contactId = NULL;

$assert = static function (bool $condition, string $message) use (&$assertions): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
  $assertions++;
};

$api = static function (string $action, array $params = []): array {
  return civicrm_api4('CiviVerifyToken', $action, ['checkPermissions' => FALSE] + $params)->getArrayCopy();
};

try {
  $contact = \Civi\Api4\Contact::create(FALSE)
    ->addValue('contact_type', 'Individual')
    ->addValue('first_name', 'CiviVerify')
    ->addValue('last_name', 'Integration Test')
    ->execute()
    ->first();
  $contactId = (int) $contact['id'];

  $issued = $api('issue', [
    'purpose' => 'integration.lifecycle',
    'contactId' => $contactId,
    'ttl' => 600,
    'metadata' => ['marker' => 'integration-test', 'personal_data' => FALSE],
  ])[0];
  $tokenIds[] = (int) $issued['id'];
  $assert(preg_match('/^[A-Za-z0-9_-]{43}$/', $issued['token']) === 1, 'Raw token format is invalid.');
  $assert(str_contains($issued['confirmation_url'], '/civicrm/verify?token='), 'Confirmation URL is invalid.');
  $assert($issued['status'] === 'pending', 'Issued token is not pending.');

  $outboxCount = static function (int $tokenId): int {
    return (int) CRM_Core_DAO::singleValueQuery(
      'SELECT COUNT(*) FROM civicrm_civiverify_outbox WHERE token_id = %1',
      [1 => [$tokenId, 'Integer']]
    );
  };
  $assert($outboxCount((int) $issued['id']) === 1, 'Issuing a token did not enqueue an event.');
  $outbox = CRM_Core_DAO::executeQuery(
    'SELECT event_name, payload FROM civicrm_civiverify_outbox WHERE token_id = %1 ORDER BY id LIMIT 1',
    [1 => [(int) $issued['id'], 'Integer']]
  );
  $assert($outbox->fetch(), 'Issued outbox event was not persisted.');
  $payload = json_decode((string) $outbox->payload, TRUE, 32, JSON_THROW_ON_ERROR);
  $assert($outbox->event_name === 'civiverify.token.issued', 'Issued outbox event has the wrong name.');
  $assert(
    !isset($payload['token_hash'], $payload['created_ip_hash'], $payload['used_ip_hash']),
    'Outbox payload contains sensitive data.'
  );

  $dao = CRM_Core_DAO::executeQuery(
    'SELECT token_hash, status, use_count, metadata FROM civicrm_civiverify_token WHERE id = %1',
    [1 => [(int) $issued['id'], 'Integer']]
  );
  $assert($dao->fetch(), 'Issued token was not persisted.');
  $assert($dao->token_hash !== $issued['token'], 'Raw token was stored in the database.');
  $assert(preg_match('/^[a-f0-9]{64}$/', (string) $dao->token_hash) === 1, 'Stored token hash format is invalid.');
  $assert((int) $dao->use_count === 0, 'New token use_count is not zero.');
  $assert(json_decode((string) $dao->metadata, TRUE)['marker'] === 'integration-test', 'Metadata was not persisted.');

  $inspected = $api('inspect', ['id' => (int) $issued['id']])[0];
  $assert($inspected['status'] === 'pending', 'Inspection did not return pending.');
  $assert(!array_key_exists('token_hash', $inspected), 'Inspection exposed the token hash.');

  $verified = $api('verify', ['token' => $issued['token']])[0];
  $assert($verified['result'] === 'verified', 'First verification did not succeed.');
  $assert($outboxCount((int) $issued['id']) === 2, 'Verification did not enqueue an event.');
  $repeated = $api('verify', ['token' => $issued['token']])[0];
  $assert($repeated['result'] === 'already_used', 'Repeated verification was not rejected.');
  $useCount = (int) CRM_Core_DAO::singleValueQuery(
    'SELECT use_count FROM civicrm_civiverify_token WHERE id = %1',
    [1 => [(int) $issued['id'], 'Integer']]
  );
  $assert($useCount === 1, 'Repeated verification changed use_count.');
  $unknownResult = $api('verify', ['token' => str_repeat('x', 43)])[0]['result'];
  $assert($unknownResult === 'invalid', 'Unknown token was not invalid.');

  $revocable = $api('issue', [
    'purpose' => 'integration.revoke',
    'contactId' => $contactId,
    'ttl' => 600,
  ])[0];
  $tokenIds[] = (int) $revocable['id'];
  $revoked = $api('revoke', ['id' => (int) $revocable['id'], 'reason' => 'integration test'])[0];
  $assert($revoked['status'] === 'revoked', 'Revocation did not succeed.');
  $assert($outboxCount((int) $revocable['id']) === 2, 'Revocation did not enqueue an event.');
  $revokedResult = $api('verify', ['token' => $revocable['token']])[0]['result'];
  $assert($revokedResult === 'revoked', 'Revoked token was not rejected.');

  $expirable = $api('issue', [
    'purpose' => 'integration.expiry',
    'contactId' => $contactId,
    'ttl' => 600,
  ])[0];
  $tokenIds[] = (int) $expirable['id'];
  CRM_Core_DAO::executeQuery(
    'UPDATE civicrm_civiverify_token SET expires_date = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE) WHERE id = %1',
    [1 => [(int) $expirable['id'], 'Integer']]
  );
  $expiredResult = $api('verify', ['token' => $expirable['token']])[0]['result'];
  $assert($expiredResult === 'expired', 'Expired token was not rejected.');
  $cleanup = $api('cleanup', ['batchSize' => 50, 'retentionDays' => 365])[0];
  $assert($cleanup['expired'] >= 1, 'Cleanup did not persist expiration.');
  $expiredStatus = CRM_Core_DAO::singleValueQuery(
    'SELECT status FROM civicrm_civiverify_token WHERE id = %1',
    [1 => [(int) $expirable['id'], 'Integer']]
  );
  $assert($expiredStatus === 'expired', 'Cleanup did not set expired status.');
  $assert($outboxCount((int) $expirable['id']) === 2, 'Expiration did not enqueue an event.');

  $dispatch = $api('dispatchOutbox', ['batchSize' => 50])[0];
  $assert($dispatch['failed'] === 0, 'Outbox dispatch failed.');
  $outboxIds = implode(',', array_map('intval', $tokenIds));
  $undelivered = (int) CRM_Core_DAO::singleValueQuery(
    'SELECT COUNT(*) FROM civicrm_civiverify_outbox WHERE delivered_date IS NULL AND token_id IN (' . $outboxIds . ')'
  );
  $assert($undelivered === 0, 'Outbox events were not marked delivered.');

  try {
    $api('issue', ['purpose' => 'integration.unbound', 'ttl' => 600]);
    throw new RuntimeException('Unbound issue unexpectedly succeeded.');
  }
  catch (CRM_Core_Exception $e) {
    $assert(str_contains($e->getMessage(), 'binding is required'), 'Unbound issue failed for an unexpected reason.');
  }

  $unbound = $api('issue', [
    'purpose' => 'integration.unbound',
    'ttl' => 600,
    'allowUnbound' => TRUE,
  ])[0];
  $tokenIds[] = (int) $unbound['id'];
  $assert($unbound['contact_id'] === NULL && $unbound['entity_name'] === NULL, 'Explicit unbound token has a binding.');

  printf("PASS: CiviVerify lifecycle integration test (%d assertions)\n", $assertions);
}
finally {
  if ($tokenIds !== []) {
    $ids = implode(',', array_map('intval', $tokenIds));
    CRM_Core_DAO::executeQuery('DELETE FROM civicrm_civiverify_outbox WHERE token_id IN (' . $ids . ')');
    CRM_Core_DAO::executeQuery('DELETE FROM civicrm_civiverify_token WHERE id IN (' . $ids . ')');
  }
  if ($contactId !== NULL) {
    \Civi\Api4\Contact::delete(FALSE)->addWhere('id', '=', $contactId)->execute();
  }
}
