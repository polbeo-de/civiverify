<?php

declare(strict_types=1);

namespace Civi\CiviVerify;

final class VerificationRepository {

  private const TABLE = 'civicrm_civiverify_token';

  public function insert(array $values): int {
    $params = [];
    $placeholders = [];
    foreach ([
      [$values['uuid'], 'String'],
      [$values['contact_id'], 'Integer'],
      [$values['entity_name'], 'String'],
      [$values['entity_id'], 'Integer'],
      [$values['purpose'], 'String'],
      [$values['token_hash'], 'String'],
      ['pending', 'String'],
      [$values['created_date'], 'String'],
      [$values['expires_date'], 'String'],
      [$values['created_by_contact_id'], 'Integer'],
      [$values['created_ip_hash'], 'String'],
      [$values['metadata'], 'String'],
    ] as [$value, $type]) {
      $placeholders[] = $this->bindNullable($params, $value, $type);
    }
    \CRM_Core_DAO::executeQuery(
      'INSERT INTO ' . self::TABLE . '
       (uuid, contact_id, entity_name, entity_id, purpose, token_hash, status, created_date,
        expires_date, created_by_contact_id, created_ip_hash, metadata, use_count)
       VALUES (' . implode(', ', $placeholders) . ', 0)',
      $params
    );
    return (int) \CRM_Core_DAO::singleValueQuery('SELECT LAST_INSERT_ID()');
  }

  public function findById(int $id): ?array {
    return $this->find('id = %1', [1 => [$id, 'Integer']]);
  }

  public function findByUuid(string $uuid): ?array {
    return $this->find('uuid = %1', [1 => [$uuid, 'String']]);
  }

  public function findByHash(string $hash): ?array {
    return $this->find('token_hash = %1', [1 => [$hash, 'String']]);
  }

  public function consume(string $hash, string $usedDate, ?string $ipHash = NULL): bool {
    $params = [
      1 => ['used', 'String'],
      2 => [$usedDate, 'String'],
    ];
    $ipPlaceholder = $this->bindNullable($params, $ipHash, 'String');
    $hashPlaceholder = $this->bindNullable($params, $hash, 'String');
    $pendingPlaceholder = $this->bindNullable($params, 'pending', 'String');
    $dao = \CRM_Core_DAO::executeQuery(
      'UPDATE ' . self::TABLE . '
       SET status = %1, used_date = %2, used_ip_hash = ' . $ipPlaceholder . ', use_count = use_count + 1
       WHERE token_hash = ' . $hashPlaceholder . ' AND status = ' . $pendingPlaceholder . '
         AND used_date IS NULL AND revoked_date IS NULL AND expires_date > %2',
      $params
    );
    return $dao->affectedRows() === 1;
  }

  public function revoke(int $id, string $revokedDate, ?string $resultMetadata): bool {
    $params = [
      1 => ['revoked', 'String'],
      2 => [$revokedDate, 'String'],
    ];
    $metadataPlaceholder = $this->bindNullable($params, $resultMetadata, 'String');
    $idPlaceholder = $this->bindNullable($params, $id, 'Integer');
    $pendingPlaceholder = $this->bindNullable($params, 'pending', 'String');
    $dao = \CRM_Core_DAO::executeQuery(
      'UPDATE ' . self::TABLE . '
       SET status = %1, revoked_date = %2, result_metadata = ' . $metadataPlaceholder . '
       WHERE id = ' . $idPlaceholder . ' AND status = ' . $pendingPlaceholder . '
         AND used_date IS NULL AND revoked_date IS NULL',
      $params
    );
    return $dao->affectedRows() === 1;
  }

  public function expireBatch(int $limit, string $now, callable $onExpired): array {
    $tx = new \CRM_Core_Transaction();
    try {
      $dao = \CRM_Core_DAO::executeQuery(
        'SELECT id FROM ' . self::TABLE . '
         WHERE status = %1 AND expires_date <= %2 ORDER BY expires_date, id LIMIT ' . $limit . ' FOR UPDATE',
        [1 => ['pending', 'String'], 2 => [$now, 'String']]
      );
      $ids = [];
      while ($dao->fetch()) {
        $ids[] = (int) $dao->id;
      }
      if ($ids !== []) {
        \CRM_Core_DAO::executeQuery(
          'UPDATE ' . self::TABLE . ' SET status = %1, expired_date = %2 WHERE id IN (' . implode(',', $ids) . ')',
          [1 => ['expired', 'String'], 2 => [$now, 'String']]
        );
        foreach ($ids as $id) {
          $record = $this->findById($id);
          if ($record !== NULL) {
            $onExpired($record);
          }
        }
      }
      $tx->commit();
      return $ids;
    }
    catch (\Throwable $e) {
      $tx->rollback();
      throw $e;
    }
  }

  public function deleteRetainedBatch(int $limit, string $cutoff): int {
    $dao = \CRM_Core_DAO::executeQuery(
      'DELETE FROM ' . self::TABLE . '
       WHERE status <> %1
         AND COALESCE(used_date, revoked_date, expired_date, expires_date) < %2
       ORDER BY id LIMIT ' . $limit,
      [1 => ['pending', 'String'], 2 => [$cutoff, 'String']]
    );
    return $dao->affectedRows();
  }

  private function find(string $where, array $params): ?array {
    $dao = \CRM_Core_DAO::executeQuery('SELECT * FROM ' . self::TABLE . ' WHERE ' . $where . ' LIMIT 1', $params);
    if (!$dao->fetch()) {
      return NULL;
    }
    $row = $dao->toArray();
    foreach (['metadata', 'result_metadata'] as $field) {
      $row[$field] = empty($row[$field]) ? NULL : json_decode((string) $row[$field], TRUE, 32, JSON_THROW_ON_ERROR);
    }
    unset($row['token_hash'], $row['created_ip_hash'], $row['used_ip_hash']);
    return $row;
  }

  /**
   * CiviCRM's DAO query composer rejects NULL paired with a scalar type.
   */
  private function bindNullable(array &$params, mixed $value, string $type): string {
    if ($value === NULL) {
      return 'NULL';
    }
    $index = count($params) + 1;
    $params[$index] = [$value, $type];
    return '%' . $index;
  }

}
