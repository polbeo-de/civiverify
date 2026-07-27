<?php

declare(strict_types=1);

namespace Civi\CiviVerify;

/** Resolves administrator-managed confirmation targets and verify workflows. */
final class VerifyDraftRegistry {

  public function target(string $key): array {
    foreach ($this->settingArray('civiverify_confirmation_targets', $this->defaultTargets()) as $target) {
      if (is_array($target) && ($target['key'] ?? NULL) === $key) {
        return $this->validateTarget($target);
      }
    }
    throw new \CRM_Core_Exception('Unknown CiviVerify confirmation target: ' . $key);
  }

  public function draft(string $workflow): array {
    foreach ($this->settingArray('civiverify_verify_drafts', []) as $draft) {
      if (is_array($draft) && ($draft['workflow_name'] ?? NULL) === $workflow) {
        $target = $this->target((string) ($draft['target_key'] ?? ''));
        $ttl = isset($draft['ttl']) ? (int) $draft['ttl'] : NULL;
        return ['workflow_name' => $workflow, 'target' => $target, 'ttl' => $ttl];
      }
    }
    // Backward-compatible safe fallback for existing workflows. New drafts
    // should always be registered explicitly by the administrative UI.
    $legacyRoute = trim((string) \Civi::settings()->get('civiverify_confirmation_route'));
    if ($legacyRoute !== '' && $legacyRoute !== 'civicrm/verify') {
      return ['workflow_name' => $workflow, 'target' => $this->validateTarget([
        'key' => 'legacy', 'label' => 'Migrated legacy target', 'route' => $legacyRoute,
      ]), 'ttl' => NULL];
    }
    return ['workflow_name' => $workflow, 'target' => $this->target('civicrm'), 'ttl' => NULL];
  }

  public function defaultTarget(): array {
    return $this->target('civicrm');
  }

  private function defaultTargets(): array {
    return [['key' => 'civicrm', 'label' => 'CiviCRM', 'route' => 'civicrm/verify']];
  }

  private function settingArray(string $name, array $default): array {
    $value = \Civi::settings()->get($name);
    if (is_string($value)) {
      $value = json_decode($value, TRUE);
    }
    return is_array($value) && $value !== [] ? $value : $default;
  }

  private function validateTarget(array $target): array {
    $key = (string) ($target['key'] ?? '');
    $route = trim((string) ($target['route'] ?? ''));
    if (!preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $key) || $route === '') {
      throw new \CRM_Core_Exception('Invalid CiviVerify confirmation target configuration.');
    }
    $scheme = parse_url($route, PHP_URL_SCHEME);
    $host = parse_url($route, PHP_URL_HOST);
    $isLocal = $scheme === 'http' && in_array($host, ['localhost', '127.0.0.1'], TRUE);
    if (filter_var($route, FILTER_VALIDATE_URL) && $scheme !== 'https' && !$isLocal) {
      throw new \CRM_Core_Exception('Confirmation targets must use HTTPS.');
    }
    return ['key' => $key, 'label' => (string) ($target['label'] ?? $key), 'route' => $route];
  }
}
