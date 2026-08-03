<?php

declare(strict_types=1);

namespace Civi\CiviVerify;

final class OutboxRepository {

  private const TABLE = 'civicrm_civiverify_outbox';

  /** Persist a sanitized lifecycle snapshot in the caller's transaction. */
  public function enqueue(int $tokenId, string $eventName, array $verification, string $now): void {
    $payload = $this->sanitize($verification);
    \CRM_Core_DAO::executeQuery(
      'INSERT INTO ' . self::TABLE . ' (token_id, event_name, payload, created_date, available_date, attempt_count)
       VALUES (%1, %2, %3, %4, %4, 0)',
      [
        1 => [$tokenId, 'Integer'],
        2 => [$eventName, 'String'],
        3 => [json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), 'String'],
        4 => [$now, 'String'],
      ]
    );
  }

  /**
   * Claim a bounded batch. A lease makes a worker crash recoverable without
   * relying on database-specific SKIP LOCKED support.
   */
  public function claimBatch(int $limit, string $now, string $lockedUntil, string $lockToken): array {
    $tx = new \CRM_Core_Transaction();
    try {
      $dao = \CRM_Core_DAO::executeQuery(
        'SELECT id, token_id, event_name, payload, attempt_count FROM ' . self::TABLE . '
         WHERE delivered_date IS NULL AND failed_date IS NULL AND available_date <= %1
           AND (locked_until IS NULL OR locked_until <= %1)
         ORDER BY id LIMIT ' . $limit . ' FOR UPDATE',
        [1 => [$now, 'String']]
      );
      $records = [];
      $ids = [];
      while ($dao->fetch()) {
        $records[] = [
          'id' => (int) $dao->id,
          'token_id' => (int) $dao->token_id,
          'event_name' => (string) $dao->event_name,
          'payload' => json_decode((string) $dao->payload, TRUE, 32, JSON_THROW_ON_ERROR),
          'attempt_count' => (int) $dao->attempt_count + 1,
        ];
        $ids[] = (int) $dao->id;
      }
      if ($ids !== []) {
        \CRM_Core_DAO::executeQuery(
          'UPDATE ' . self::TABLE . ' SET locked_until = %1, lock_token = %2,
             attempt_count = attempt_count + 1 WHERE id IN (' . implode(',', $ids) . ')',
          [1 => [$lockedUntil, 'String'], 2 => [$lockToken, 'String']]
        );
      }
      $tx->commit();
      return $records;
    }
    catch (\Throwable $e) {
      $tx->rollback();
      throw $e;
    }
  }

  public function markDelivered(int $id, string $lockToken, string $now): bool {
    $dao = \CRM_Core_DAO::executeQuery(
      'UPDATE ' . self::TABLE . ' SET delivered_date = %1, locked_until = NULL, lock_token = NULL,
         last_error = NULL WHERE id = %2 AND lock_token = %3 AND delivered_date IS NULL',
      [1 => [$now, 'String'], 2 => [$id, 'Integer'], 3 => [$lockToken, 'String']]
    );
    return $dao->affectedRows() === 1;
  }

  public function reschedule(int $id, string $lockToken, string $availableDate, string $error): bool {
    $dao = \CRM_Core_DAO::executeQuery(
      'UPDATE ' . self::TABLE . ' SET available_date = %1, locked_until = NULL, lock_token = NULL,
         last_error = %2 WHERE id = %3 AND lock_token = %4 AND delivered_date IS NULL',
      [1 => [$availableDate, 'String'], 2 => [$error, 'String'], 3 => [$id, 'Integer'], 4 => [$lockToken, 'String']]
    );
    return $dao->affectedRows() === 1;
  }

  public function markFailed(int $id, string $lockToken, string $now, string $error): bool {
    $dao = \CRM_Core_DAO::executeQuery(
      'UPDATE ' . self::TABLE . ' SET failed_date = %1, locked_until = NULL, lock_token = NULL,
         last_error = %2 WHERE id = %3 AND lock_token = %4 AND delivered_date IS NULL',
      [1 => [$now, 'String'], 2 => [$error, 'String'], 3 => [$id, 'Integer'], 4 => [$lockToken, 'String']]
    );
    return $dao->affectedRows() === 1;
  }

  public function deleteTerminalBatch(int $limit, string $cutoff): int {
    $dao = \CRM_Core_DAO::executeQuery(
      'DELETE FROM ' . self::TABLE . ' WHERE COALESCE(delivered_date, failed_date) IS NOT NULL
       AND COALESCE(delivered_date, failed_date) < %1
       ORDER BY id LIMIT ' . $limit,
      [1 => [$cutoff, 'String']]
    );
    return $dao->affectedRows();
  }

  private function sanitize(array $verification): array {
    unset(
      $verification['token'],
      $verification['token_hash'],
      $verification['created_ip_hash'],
      $verification['used_ip_hash']
    );
    return $verification;
  }

}
