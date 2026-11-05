<?php

declare(strict_types=1);

use Civi\CiviVerify\Event\TokenEvent;

$createdTokenIds = [];
$contactId = NULL;

$assert = static function (bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
};

try {
  $contact = civicrm_api4('Contact', 'create', [
    'checkPermissions' => FALSE,
    'values' => ['first_name' => 'Atomic', 'last_name' => 'Redeemer'],
  ])->first();
  $contactId = (int) $contact['id'];
  $issue = static function (string $purpose = 'integration.atomic-redeem') use (&$createdTokenIds, $contactId): array {
    $issued = Civi::service('civiverify.issuer')->issue([
      'purpose' => $purpose,
      'contact_id' => $contactId,
      'ttl' => 600,
    ]);
    $createdTokenIds[] = (int) $issued['id'];
    return $issued;
  };
  $outboxCount = static function (int $tokenId, string $eventName): int {
    return (int) CRM_Core_DAO::singleValueQuery(
      'SELECT COUNT(*) FROM civicrm_civiverify_outbox WHERE token_id = %1 AND event_name = %2',
      [1 => [$tokenId, 'Integer'], 2 => [$eventName, 'String']]
    );
  };

  $issued = $issue();
  $callbackRecord = NULL;
  $result = Civi::service('civiverify.redeemer')->redeem(
    $issued['token'],
    ['purpose' => 'integration.atomic-redeem', 'contact_id' => $contactId],
    static function (array $record) use (&$callbackRecord, $contactId): void {
      $callbackRecord = $record;
      CRM_Core_DAO::executeQuery(
        'UPDATE civicrm_contact SET nick_name = %1 WHERE id = %2',
        [1 => ['redeemed', 'String'], 2 => [$contactId, 'Integer']]
      );
    }
  );
  $assert($result->result === 'verified', 'Atomic redemption did not succeed.');
  $assert($callbackRecord !== NULL && !array_intersect_key($callbackRecord, array_flip([
    'token', 'token_hash', 'code_hash', 'created_ip_hash', 'used_ip_hash',
  ])), 'The redemption callback received a secret value.');
  $assert(
    CRM_Core_DAO::singleValueQuery(
      'SELECT nick_name FROM civicrm_contact WHERE id = %1', [1 => [$contactId, 'Integer']]
    ) === 'redeemed',
    'The callback database change was not committed.'
  );
  $assert($outboxCount((int) $issued['id'], TokenEvent::VERIFIED) === 1, 'Verified event was not written once.');
  $assert(
    Civi::service('civiverify.redeemer')->redeem(
      $issued['token'], [], static function (): void {}
    )->result === 'already_used',
    'A repeated redemption did not return already_used.'
  );
  $assert(
    $outboxCount((int) $issued['id'], TokenEvent::VERIFIED) === 1,
    'A repeated redemption enqueued another verified event.'
  );

  $failed = $issue();
  try {
    Civi::service('civiverify.redeemer')->redeem(
      $failed['token'], [],
      static function () use ($contactId): void {
        CRM_Core_DAO::executeQuery('UPDATE civicrm_contact SET nick_name = %1 WHERE id = %2', [
          1 => ['rolled-back', 'String'], 2 => [$contactId, 'Integer'],
        ]);
        throw new RuntimeException('Callback failure');
      }
    );
    throw new RuntimeException('Callback failure was not propagated.');
  }
  catch (RuntimeException $e) {
    $assert($e->getMessage() === 'Callback failure', 'Unexpected redemption failure.');
  }
  $assert(
    CRM_Core_DAO::singleValueQuery(
      'SELECT status FROM civicrm_civiverify_token WHERE id = %1', [1 => [(int) $failed['id'], 'Integer']]
    ) === 'pending',
    'A failed callback consumed its token.'
  );
  $assert($outboxCount((int) $failed['id'], TokenEvent::VERIFIED) === 0, 'A failed callback enqueued an event.');
  $assert(
    CRM_Core_DAO::singleValueQuery(
      'SELECT nick_name FROM civicrm_contact WHERE id = %1', [1 => [$contactId, 'Integer']]
    ) === 'redeemed',
    'A failed callback committed its database change.'
  );

  $mismatch = $issue();
  $assert(
    Civi::service('civiverify.redeemer')->redeem(
      $mismatch['token'], ['purpose' => 'other'], static function (): void {
        throw new RuntimeException('Must not run.');
      }
    )->result === 'invalid',
    'A mismatching purpose was accepted.'
  );
  $assert(
    Civi::service('civiverify.redeemer')->redeem(
      $mismatch['token'], ['contact_id' => $contactId + 1], static function (): void {
        throw new RuntimeException('Must not run.');
      }
    )->result === 'invalid',
    'A mismatching binding was accepted.'
  );
  $assert(
    CRM_Core_DAO::singleValueQuery(
      'SELECT status FROM civicrm_civiverify_token WHERE id = %1', [1 => [(int) $mismatch['id'], 'Integer']]
    ) === 'pending',
    'A mismatching binding consumed its token.'
  );

  $expired = $issue();
  CRM_Core_DAO::executeQuery(
    'UPDATE civicrm_civiverify_token SET expires_date = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE) WHERE id = %1',
    [1 => [(int) $expired['id'], 'Integer']]
  );
  $assert(
    Civi::service('civiverify.redeemer')->redeem(
      $expired['token'], [], static function (): void {}
    )->result === 'expired',
    'An expired token was accepted.'
  );

  $revoked = $issue();
  CRM_Core_DAO::executeQuery(
    'UPDATE civicrm_civiverify_token SET status = %1, revoked_date = UTC_TIMESTAMP() WHERE id = %2',
    [1 => ['revoked', 'String'], 2 => [(int) $revoked['id'], 'Integer']]
  );
  $assert(
    Civi::service('civiverify.redeemer')->redeem(
      $revoked['token'], [], static function (): void {}
    )->result === 'revoked',
    'A revoked token was accepted.'
  );

  print "PASS: atomic redemption lifecycle tests\n";
}
finally {
  if ($createdTokenIds !== []) {
    $ids = implode(',', array_map('intval', $createdTokenIds));
    CRM_Core_DAO::executeQuery('DELETE FROM civicrm_civiverify_outbox WHERE token_id IN (' . $ids . ')');
    CRM_Core_DAO::executeQuery('DELETE FROM civicrm_civiverify_token WHERE id IN (' . $ids . ')');
  }
  if ($contactId !== NULL) {
    civicrm_api4('Contact', 'delete', ['checkPermissions' => FALSE, 'where' => [['id', '=', $contactId]]]);
  }
}
