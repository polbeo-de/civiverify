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

  private function ensureOutboxTable(): void {
    $helper = $GLOBALS['CiviMixSchema']->getHelper(CRM_CiviVerify_ExtensionUtil::LONG_NAME);
    if (!$helper->tableExists('civicrm_civiverify_outbox')) {
      $helper->createEntityTable('schema/CiviVerifyOutbox.entityType.php');
    }
  }

}
