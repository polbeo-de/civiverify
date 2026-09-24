<?php

declare(strict_types=1);

/** Upgrade steps for installations that predate the transactional outbox. */
final class CRM_CiviVerify_Upgrader extends CRM_Extension_Upgrader_Base {

  public function upgrade_1000(): bool {
    $this->ensureOutboxTable();
    return TRUE;
  }

  /** Run on installations already at revision 1000 before the outbox existed. */
  public function upgrade_1001(): bool {
    $this->ensureOutboxTable();
    return TRUE;
  }

  /** Point existing managed delivery jobs at the API3 bridge. */
  public function upgrade_1002(): bool {
    CRM_Core_DAO::executeQuery(
      'UPDATE civicrm_job
       SET api_action = %1
       WHERE api_entity = %2 AND LOWER(api_action) = %3',
      [
        1 => ['dispatchoutbox', 'String'],
        2 => ['CiviVerifyToken', 'String'],
        3 => ['dispatchoutbox', 'String'],
      ]
    );
    return TRUE;
  }

  /** Add the non-reversible digest column for alternate verification codes. */
  public function upgrade_1004(): bool {
    $column = CRM_Core_DAO::singleValueQuery(
      "SHOW COLUMNS FROM civicrm_civiverify_token LIKE 'code_hash'"
    );
    if ($column === NULL) {
      CRM_Core_DAO::executeQuery(
        'ALTER TABLE civicrm_civiverify_token ADD COLUMN code_hash CHAR(64) NULL AFTER token_hash'
      );
    }
    return TRUE;
  }

  public function upgrade_1005(): bool {
    $column = CRM_Core_DAO::singleValueQuery("SHOW COLUMNS FROM civicrm_civiverify_token LIKE 'code_attempt_count'");
    if ($column === NULL) {
      CRM_Core_DAO::executeQuery('ALTER TABLE civicrm_civiverify_token ADD COLUMN code_attempt_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER use_count');
    }
    return TRUE;
  }

  private function ensureOutboxTable(): void {
    $helper = $GLOBALS['CiviMixSchema']->getHelper(CRM_CiviVerify_ExtensionUtil::LONG_NAME);
    if (!$helper->tableExists('civicrm_civiverify_outbox')) {
      $helper->createEntityTable('schema/CiviVerifyOutbox.entityType.php');
    }
  }

}
