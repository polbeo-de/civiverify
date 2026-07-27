<?php

declare(strict_types=1);

/**
 * Legacy-compatible DAO stub backed by CiviVerifyToken.entityType.php.
 *
 * @property string $id
 * @property string $uuid
 * @property string|null $contact_id
 * @property string|null $entity_name
 * @property string|null $entity_id
 * @property string $purpose
 * @property string $token_hash
 * @property string $status
 * @property string $created_date
 * @property string $expires_date
 * @property string|null $expired_date
 * @property string|null $used_date
 * @property string|null $revoked_date
 * @property string|null $created_by_contact_id
 * @property string|null $created_ip_hash
 * @property string|null $used_ip_hash
 * @property string|null $metadata
 * @property string|null $result_metadata
 * @property string $use_count
 */
final class CRM_CiviVerify_DAO_CiviVerifyToken extends CRM_Core_DAO_Base {

  /**
   * Required for legacy DAO compatibility.
   */
  public static $_tableName = 'civicrm_civiverify_token';

}
