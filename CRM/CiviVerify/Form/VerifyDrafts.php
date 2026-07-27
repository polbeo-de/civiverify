<?php

declare(strict_types=1);

use CRM_CiviVerify_ExtensionUtil as E;

final class CRM_CiviVerify_Form_VerifyDrafts extends CRM_Core_Form {

  private ?string $editWorkflow = NULL;

  public function preProcess(): void {
    $workflow = CRM_Utils_Request::retrieve('edit', 'String', $this, FALSE);
    $this->editWorkflow = $workflow !== NULL && isset($this->drafts()[$workflow]) ? $workflow : NULL;
    $this->assign('editWorkflow', $this->editWorkflow);
  }

  public function buildQuickForm(): void {
    CRM_Utils_System::setTitle(E::ts('CiviVerify – Verification drafts'));
    $this->add('text', 'workflow_name', E::ts('Technical workflow name'), ['size' => 50], TRUE);
    $this->add('text', 'label', E::ts('Label'), ['size' => 50], TRUE);
    $this->add('select', 'target_key', E::ts('Confirmation target'), $this->targetOptions(), TRUE);
    $this->add('text', 'ttl', E::ts('Default validity period (seconds)'), ['size' => 12], TRUE);
    if ($this->editWorkflow !== NULL) {
      $this->freeze('workflow_name');
    }
    $this->addButtons([['type' => 'submit', 'name' => E::ts('Save verification draft'), 'isDefault' => TRUE]]);
    $drafts = $this->drafts();
    foreach ($drafts as &$draft) {
      $draft['edit_url'] = CRM_Utils_System::url('civicrm/admin/civiverify/verify-drafts', [
        'reset' => 1,
        'edit' => $draft['workflow_name'],
      ]);
    }
    unset($draft);
    $this->assign('drafts', $drafts);
  }

  public function setDefaultValues(): array {
    return $this->editWorkflow === NULL ? [] : $this->drafts()[$this->editWorkflow];
  }

  public function postProcess(): void {
    $values = $this->exportValues();
    $workflow = trim((string) $values['workflow_name']);
    $ttl = (int) $values['ttl'];
    if (!preg_match('/^[a-z][a-z0-9_]{0,127}$/', $workflow)) {
      $this->setElementError('workflow_name', E::ts('Use only lowercase letters, digits, and underscores.'));
      return;
    }
    $minimumTtl = (int) Civi::settings()->get('civiverify_minimum_ttl');
    $maximumTtl = (int) Civi::settings()->get('civiverify_maximum_ttl');
    if ($ttl < $minimumTtl || $ttl > $maximumTtl) {
      $this->setElementError('ttl', E::ts('The validity period is outside the range configured in CiviVerify.'));
      return;
    }
    if (!array_key_exists((string) $values['target_key'], $this->targetOptions())) {
      $this->setElementError('target_key', E::ts('Select a configured confirmation target.'));
      return;
    }
    $drafts = $this->drafts();
    $drafts[$workflow] = [
      'workflow_name' => $workflow,
      'label' => trim((string) $values['label']),
      'target_key' => (string) $values['target_key'],
      'ttl' => $ttl,
    ];
    Civi::settings()->set('civiverify_verify_drafts', array_values($drafts));
    CRM_Core_Session::setStatus(E::ts('Verification draft saved.'), E::ts('Saved'), 'success');
    CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/admin/civiverify/verify-drafts', ['reset' => 1]));
  }

  private function targetOptions(): array {
    $options = [];
    $targets = Civi::settings()->get('civiverify_confirmation_targets');
    if (is_string($targets)) {
      $targets = json_decode($targets, TRUE);
    }
    foreach ((array) $targets as $target) {
      if (is_array($target) && !empty($target['key'])) {
        $options[(string) $target['key']] = (string) ($target['label'] ?? $target['key']);
      }
    }
    return $options ?: ['civicrm' => 'CiviCRM'];
  }

  private function drafts(): array {
    $result = [];
    $drafts = Civi::settings()->get('civiverify_verify_drafts');
    if (is_string($drafts)) {
      $drafts = json_decode($drafts, TRUE);
    }
    foreach ((array) $drafts as $draft) {
      if (is_array($draft) && !empty($draft['workflow_name'])) {
        $result[(string) $draft['workflow_name']] = $draft;
      }
    }
    return $result;
  }
}
