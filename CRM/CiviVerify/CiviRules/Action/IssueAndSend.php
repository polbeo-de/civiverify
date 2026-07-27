<?php

declare(strict_types=1);

if (!class_exists('CRM_Civirules_Action')) {
  return;
}

final class CRM_CiviVerify_CiviRules_Action_IssueAndSend extends CRM_Civirules_Action {

  public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData): void {
    $config = $this->getActionParameters();
    $contactId = (int) ($triggerData->getContactId() ?? 0);
    if ($contactId < 1) {
      throw new CRM_Core_Exception('CiviVerify issue and send requires a contact in the trigger data.');
    }
    $params = [
      'checkPermissions' => FALSE,
      'purpose' => (string) ($config['purpose'] ?? ''),
      'contactId' => $contactId,
      'ttl' => (int) ($config['ttl'] ?? Civi::settings()->get('civiverify_default_ttl')),
      'templateParams' => $config['template_params'] ?? [],
    ];
    if (!empty($config['workflow_name'])) {
      $params['workflowName'] = (string) $config['workflow_name'];
    }
    elseif (!empty($config['message_template_id'])) {
      $params['messageTemplateId'] = (int) $config['message_template_id'];
    }
    $entityName = trim((string) ($config['entity_name'] ?? ''));
    if ($entityName !== '') {
      $entityData = $triggerData->getEntityData($entityName);
      if (empty($entityData['id'])) {
        throw new CRM_Core_Exception('The configured bound entity is unavailable in the trigger data.');
      }
      $params['entityName'] = $entityName;
      $params['entityId'] = (int) $entityData['id'];
    }
    $result = civicrm_api4('CiviVerifyToken', 'issueAndSend', $params)->first();
    $this->logAction(
      sprintf('Issued and sent CiviVerify verification #%d.', (int) $result['id']),
      $triggerData
    );
  }

  public function getExtraDataInputUrl($ruleActionId): string {
    return $this->getFormattedExtraDataInputUrl(
      'civicrm/civiverify/civirules/action/issue-send',
      (int) $ruleActionId
    );
  }

  public function doesWorkWithTrigger(CRM_Civirules_Trigger $trigger, CRM_Civirules_BAO_Rule $rule): bool {
    return !$trigger instanceof CRM_CiviVerify_CiviRules_Trigger_Base;
  }

  public function getHelpText(string $context): string {
    return match ($context) {
      'actionDescription' => 'Issues a CiviVerify link and sends it with a CiviCRM message template.',
      'actionDescriptionWithParams' => $this->userFriendlyConditionParams(),
      default => 'The trigger must provide a contact. CiviVerify lifecycle triggers are excluded to prevent loops.',
    };
  }

  public function userFriendlyConditionParams(): string {
    $params = $this->getActionParameters();
    $template = !empty($params['workflow_name'])
      ? 'workflow ' . $params['workflow_name']
      : 'template #' . (int) ($params['message_template_id'] ?? 0);
    return sprintf(
      'Issue purpose %s for %d seconds and send with %s.',
      (string) ($params['purpose'] ?? ''),
      (int) ($params['ttl'] ?? 0),
      $template
    );
  }

}
