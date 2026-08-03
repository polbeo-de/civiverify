<?php

declare(strict_types=1);

/** Upgrade steps for installations that predate the transactional outbox. */
final class CRM_CiviVerify_Upgrader extends CRM_Extension_Upgrader_Base {

  public function upgrade_1000(): bool {
    $helper = $GLOBALS['CiviMixSchema']->getHelper(CRM_CiviVerify_ExtensionUtil::LONG_NAME);
    if (!$helper->tableExists('civicrm_civiverify_outbox')) {
      $helper->createEntityTable('schema/CiviVerifyOutbox.entityType.php');
    }
    return TRUE;
  }

}
