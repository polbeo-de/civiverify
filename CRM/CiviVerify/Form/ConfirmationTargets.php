<?php

declare(strict_types=1);

use CRM_CiviVerify_ExtensionUtil as E;

final class CRM_CiviVerify_Form_ConfirmationTargets extends CRM_Core_Form {

  private ?string $editKey = NULL;

  public function preProcess(): void {
    $key = CRM_Utils_Request::retrieve('edit', 'String', $this, FALSE);
    $this->editKey = $key !== NULL && isset($this->targets()[$key]) ? $key : NULL;
    $this->assign('editKey', $this->editKey);
  }

  public function buildQuickForm(): void {
    CRM_Utils_System::setTitle(E::ts('CiviVerify – Confirmation targets'));
    $this->add('text', 'key', E::ts('Technical key'), ['size' => 30], TRUE);
    $this->add('text', 'label', E::ts('Label'), ['size' => 50], TRUE);
    $this->add('text', 'route', E::ts('Route or HTTPS URL'), ['size' => 80], TRUE);
    if ($this->editKey !== NULL) {
      $this->freeze('key');
    }
    $this->addButtons([['type' => 'submit', 'name' => E::ts('Save confirmation target'), 'isDefault' => TRUE]]);
    $targets = $this->targets();
    foreach ($targets as &$target) {
      $target['edit_url'] = CRM_Utils_System::url('civicrm/admin/civiverify/targets', [
        'reset' => 1,
        'edit' => $target['key'],
      ]);
    }
    unset($target);
    $this->assign('targets', $targets);
  }

  public function setDefaultValues(): array {
    return $this->editKey === NULL ? [] : $this->targets()[$this->editKey];
  }

  public function postProcess(): void {
    $values = $this->exportValues();
    $key = trim((string) $values['key']);
    $route = trim((string) $values['route']);
    if (!preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $key)) {
      $this->setElementError('key', E::ts('Use only lowercase letters, digits, underscores, or hyphens.'));
      return;
    }
    $scheme = parse_url($route, PHP_URL_SCHEME);
    $host = parse_url($route, PHP_URL_HOST);
    $local = $scheme === 'http' && in_array($host, ['localhost', '127.0.0.1'], TRUE);
    if ($route === '' || (filter_var($route, FILTER_VALIDATE_URL) && $scheme !== 'https' && !$local)) {
      $this->setElementError('route', E::ts('Use an internal route or an HTTPS URL.'));
      return;
    }
    $targets = $this->targets();
    $targets[$key] = ['key' => $key, 'label' => trim((string) $values['label']), 'route' => $route];
    Civi::settings()->set('civiverify_confirmation_targets', array_values($targets));
    CRM_Core_Session::setStatus(E::ts('Confirmation target saved.'), E::ts('Saved'), 'success');
    CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/admin/civiverify/targets', ['reset' => 1]));
  }

  private function targets(): array {
    $value = Civi::settings()->get('civiverify_confirmation_targets');
    if (is_string($value)) {
      $value = json_decode($value, TRUE);
    }
    $result = [];
    foreach (is_array($value) ? $value : [] as $target) {
      if (is_array($target) && !empty($target['key'])) {
        $result[(string) $target['key']] = $target;
      }
    }
    return $result ?: ['civicrm' => ['key' => 'civicrm', 'label' => 'CiviCRM', 'route' => 'civicrm/verify']];
  }
}
