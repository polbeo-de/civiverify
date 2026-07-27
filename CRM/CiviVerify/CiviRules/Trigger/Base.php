<?php

declare(strict_types=1);

if (!class_exists('CRM_Civirules_Trigger')) {
  return;
}

abstract class CRM_CiviVerify_CiviRules_Trigger_Base extends CRM_Civirules_Trigger {

  public function getEntityName(): ?string {
    return 'CiviVerifyToken';
  }

  protected function reactOnEntity() {
    return new CRM_Civirules_TriggerData_EntityDefinition(
      'CiviVerify verification',
      'CiviVerifyToken',
      'CRM_CiviVerify_DAO_CiviVerifyToken',
      'CiviVerifyToken'
    );
  }

  /**
   * CiviRules caches provided entities globally in its base trigger. Trigger
   * parameters make the bound entity rule-specific, so calculate them here.
   */
  public function getProvidedEntities(): array {
    $entities = [];
    $primary = $this->reactOnEntity();
    $entities[$primary->key] = $primary;
    foreach ($this->getAdditionalEntities() as $entity) {
      $entities[$entity->key] = $entity;
    }
    return $entities;
  }

  protected function getAdditionalEntities(): array {
    $entities = [
      new CRM_Civirules_TriggerData_EntityDefinition(
        'Contact',
        'Contact',
        'CRM_Contact_DAO_Contact',
        'Contact'
      ),
    ];
    $entityName = $this->getConfiguredEntityName();
    if ($entityName !== NULL && !in_array($entityName, ['Contact', 'CiviVerifyToken'], TRUE)) {
      $definition = $this->getEntityDefinition($entityName);
      if ($definition !== NULL) {
        $entities[] = $definition;
      }
    }
    return $entities;
  }

  public function getExtraDataInputUrl($ruleId) {
    return CRM_Utils_System::url(
      'civicrm/civiverify/civirules/trigger',
      'rule_id=' . (int) $ruleId
    );
  }

  public function getTriggerDescription(): string {
    $purpose = trim((string) ($this->triggerParams['purpose'] ?? '')) ?: 'any purpose';
    $entity = $this->getConfiguredEntityName() ?? 'any bound entity';
    return sprintf('CiviVerify lifecycle event for %s and %s', $purpose, $entity);
  }

  public function getHelpText(string $context): string {
    return match ($context) {
      'triggerDescription' => 'Reacts to a CiviVerify lifecycle event.',
      default => 'Optionally restrict this rule to one exact purpose and one bound APIv4 entity.',
    };
  }

  public function matches(array $verification): bool {
    $purpose = trim((string) ($this->triggerParams['purpose'] ?? ''));
    if ($purpose !== '' && ($verification['purpose'] ?? NULL) !== $purpose) {
      return FALSE;
    }
    $entityName = $this->getConfiguredEntityName();
    return $entityName === NULL || ($verification['entity_name'] ?? NULL) === $entityName;
  }

  public function createTriggerData(array $verification): CRM_CiviVerify_CiviRules_TriggerData {
    foreach (['token', 'token_hash', 'created_ip_hash', 'used_ip_hash', 'metadata', 'result_metadata'] as $field) {
      unset($verification[$field]);
    }
    $triggerData = new CRM_CiviVerify_CiviRules_TriggerData($verification, $this);
    $entityName = (string) ($verification['entity_name'] ?? '');
    $entityId = (int) ($verification['entity_id'] ?? 0);
    if ($entityName !== '' && $entityId > 0) {
      try {
        $entity = civicrm_api4($entityName, 'get', [
          'checkPermissions' => FALSE,
          'where' => [['id', '=', $entityId]],
          'limit' => 1,
        ])->first();
        if (is_array($entity)) {
          $triggerData->setEntityData($entityName, $entity);
        }
      }
      catch (Throwable $e) {
        Civi::log('civiverify')->warning(sprintf(
          'Could not load bound %s #%d for CiviRules: %s',
          $entityName,
          $entityId,
          $e->getMessage()
        ));
      }
    }
    return $triggerData;
  }

  private function getConfiguredEntityName(): ?string {
    $entityName = trim((string) ($this->triggerParams['entity_name'] ?? ''));
    return $entityName === '' ? NULL : $entityName;
  }

  private function getEntityDefinition(string $entityName): ?CRM_Civirules_TriggerData_EntityDefinition {
    try {
      $entity = \Civi\Api4\Entity::get(FALSE)
        ->addWhere('name', '=', $entityName)
        ->setLimit(1)
        ->execute()
        ->first();
    }
    catch (Throwable $e) {
      return NULL;
    }
    if (empty($entity['dao'])) {
      return NULL;
    }
    return new CRM_Civirules_TriggerData_EntityDefinition(
      (string) ($entity['title'] ?? $entityName),
      $entityName,
      (string) $entity['dao'],
      $entityName
    );
  }

}
