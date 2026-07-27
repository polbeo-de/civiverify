<?php

declare(strict_types=1);

if (!class_exists('CRM_Civirules_TriggerData_TriggerData')) {
  return;
}

final class CRM_CiviVerify_CiviRules_TriggerData extends CRM_Civirules_TriggerData_TriggerData {

  public function __construct(array $verification, CRM_Civirules_Trigger $trigger) {
    parent::__construct();
    $this->setTrigger($trigger);
    $this->setEntity('CiviVerifyToken');
    $this->setEntityId((int) $verification['id']);
    $this->setEntityData('CiviVerifyToken', $verification, TRUE);
    if (!empty($verification['contact_id'])) {
      $this->setContactId((int) $verification['contact_id']);
    }
  }

}
