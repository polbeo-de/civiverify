<?php

declare(strict_types=1);

use CRM_CiviVerify_ExtensionUtil as E;

if (!class_exists('CRM_CivirulesTrigger_Form_Form')) {
  return;
}

final class CRM_CiviVerify_Form_CiviRulesTrigger extends CRM_CivirulesTrigger_Form_Form {

  public function buildQuickForm(): void {
    $this->add('hidden', 'rule_id');
    $this->add(
      'text',
      'purpose',
      E::ts('Purpose (optional exact match)'),
      ['maxlength' => 128]
    );
    $this->add(
      'select',
      'entity_name',
      E::ts('Linked entity (optional)'),
      $this->getEntityOptions(),
      FALSE,
      ['class' => 'crm-select2 huge']
    );
    $this->addRule(
      'purpose',
      E::ts('The purpose must be a valid machine-readable key.'),
      'regex',
      '/^$|^[a-z0-9][a-z0-9._:-]{0,127}$/'
    );
    $this->addButtons([
      ['type' => 'next', 'name' => E::ts('Save'), 'isDefault' => TRUE],
      ['type' => 'cancel', 'name' => E::ts('Cancel')],
    ]);
  }

  public function setDefaultValues(): array {
    $defaults = parent::setDefaultValues();
    $params = $this->triggerClass->getTriggerParams();
    $defaults['purpose'] = $params['purpose'] ?? '';
    $defaults['entity_name'] = $params['entity_name'] ?? '';
    return $defaults;
  }

  public function postProcess(): void {
    $this->triggerParams['purpose'] = trim((string) $this->getSubmittedValue('purpose'));
    $this->triggerParams['entity_name'] = trim((string) $this->getSubmittedValue('entity_name'));
    parent::postProcess();
  }

  private function getEntityOptions(): array {
    $options = ['' => E::ts('- Any or no linked entity -')];
    $entities = \Civi\Api4\Entity::get(FALSE)
      ->addOrderBy('title', 'ASC')
      ->execute();
    foreach ($entities as $entity) {
      if (!empty($entity['dao']) && $entity['name'] !== 'CiviVerifyToken') {
        $options[$entity['name']] = $entity['title'] ?? $entity['name'];
      }
    }
    return $options;
  }

}
