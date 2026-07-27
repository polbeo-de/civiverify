<?php

declare(strict_types=1);

use CRM_CiviVerify_ExtensionUtil as E;

if (!class_exists('CRM_CivirulesActions_Form_Form')) {
  return;
}

final class CRM_CiviVerify_Form_CiviRulesIssueAndSend extends CRM_CivirulesActions_Form_Form {

  public function buildQuickForm(): void {
    $this->add('hidden', 'rule_action_id');
    $this->add('text', 'purpose', E::ts('Purpose'), ['maxlength' => 128], TRUE);
    $this->add('number', 'ttl', E::ts('Validity period in seconds'), ['min' => 60, 'max' => 2592000], TRUE);
    $this->add(
      'select',
      'message_template',
      E::ts('Message template'),
      $this->getTemplateOptions(),
      TRUE,
      ['class' => 'crm-select2 huge']
    );
    $this->add(
      'select',
      'entity_name',
      E::ts('Link verification to'),
      $this->getEntityOptions(),
      FALSE,
      ['class' => 'crm-select2 huge']
    );
    $this->add(
      'textarea',
      'template_params_json',
      E::ts('Additional template parameters (JSON object)'),
      ['rows' => 6, 'class' => 'huge']
    );
    $this->addRule(
      'purpose',
      E::ts('The purpose must be a valid machine-readable key.'),
      'regex',
      '/^[a-z0-9][a-z0-9._:-]{0,127}$/'
    );
    $this->addFormRule([self::class, 'validateValues']);
    $defaults = parent::setDefaultValues();
    $params = $this->ruleAction->unserializeParams();
    $defaults += [
      'purpose' => $params['purpose'] ?? '',
      'ttl' => $params['ttl'] ?? (int) Civi::settings()->get('civiverify_default_ttl'),
      'entity_name' => $params['entity_name'] ?? '',
      'message_template' => !empty($params['workflow_name'])
        ? 'workflow:' . $params['workflow_name']
        : 'id:' . (int) ($params['message_template_id'] ?? 0),
      'template_params_json' => empty($params['template_params'])
        ? ''
        : json_encode($params['template_params'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
    ];
    $this->setDefaults($defaults);
    $this->addButtons([
      ['type' => 'next', 'name' => E::ts('Save'), 'isDefault' => TRUE],
      ['type' => 'cancel', 'name' => E::ts('Cancel')],
    ]);
  }

  public static function validateValues(array $values): array {
    $errors = [];
    $ttl = (int) ($values['ttl'] ?? 0);
    $minimum = (int) Civi::settings()->get('civiverify_minimum_ttl');
    $maximum = (int) Civi::settings()->get('civiverify_maximum_ttl');
    if ($ttl < $minimum || $ttl > $maximum) {
      $errors['ttl'] = E::ts('The validity period must be between %1 and %2 seconds.', [1 => $minimum, 2 => $maximum]);
    }
    $json = trim((string) ($values['template_params_json'] ?? ''));
    if ($json !== '') {
      try {
        $decoded = json_decode($json, TRUE, 32, JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || array_is_list($decoded)) {
          $errors['template_params_json'] = E::ts('The template parameters must be a JSON object.');
        }
      }
      catch (JsonException $e) {
        $errors['template_params_json'] = E::ts('The template parameters contain invalid JSON.');
      }
    }
    return $errors;
  }

  public function postProcess(): void {
    $values = $this->exportValues();
    $template = (string) $values['message_template'];
    $params = [
      'purpose' => trim((string) $values['purpose']),
      'ttl' => (int) $values['ttl'],
      'entity_name' => trim((string) ($values['entity_name'] ?? '')),
      'template_params' => trim((string) ($values['template_params_json'] ?? '')) === ''
        ? []
        : json_decode((string) $values['template_params_json'], TRUE, 32, JSON_THROW_ON_ERROR),
    ];
    if (str_starts_with($template, 'workflow:')) {
      $params['workflow_name'] = substr($template, strlen('workflow:'));
    }
    else {
      $params['message_template_id'] = (int) substr($template, strlen('id:'));
    }
    $this->ruleAction->action_params = serialize($params);
    $this->ruleAction->save();
    parent::postProcess();
  }

  private function getTemplateOptions(): array {
    $options = [];
    $templates = Civi\Api4\MessageTemplate::get(FALSE)
      ->addSelect('id', 'workflow_name', 'msg_title')
      ->addWhere('is_default', '=', TRUE)
      ->addWhere('is_reserved', '=', FALSE)
      ->addWhere('is_active', '=', TRUE)
      ->addOrderBy('msg_title', 'ASC')
      ->execute();
    foreach ($templates as $template) {
      if (!empty($template['workflow_name'])) {
        $key = 'workflow:' . $template['workflow_name'];
        $suffix = ' [' . $template['workflow_name'] . ']';
      }
      else {
        $key = 'id:' . (int) $template['id'];
        $suffix = ' [#' . (int) $template['id'] . ']';
      }
      $options[$key] = (string) $template['msg_title'] . $suffix;
    }
    return $options;
  }

  private function getEntityOptions(): array {
    $options = ['' => E::ts('Contact only')];
    foreach ($this->triggerClass->getProvidedEntities() as $definition) {
      if (!in_array($definition->entity, ['Contact', 'CiviVerifyToken'], TRUE)
        && !empty($definition->daoClass)
        && class_exists($definition->daoClass)) {
        $options[$definition->entity] = $definition->label;
      }
    }
    return $options;
  }

}
