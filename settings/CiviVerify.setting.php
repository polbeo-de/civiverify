<?php

declare(strict_types=1);

use CRM_CiviVerify_ExtensionUtil as E;

$settings = [
  'civiverify_default_ttl' => [
    'name' => 'civiverify_default_ttl', 'group' => 'civiverify', 'type' => 'Integer', 'default' => 86400,
    'html_type' => 'text', 'title' => E::ts('Default validity period (seconds)'), 'is_domain' => 1, 'is_contact' => 0,
    'description' => E::ts('Used when the selected verification draft does not define its own validity period. 86,400 seconds equal 24 hours.'),
  ],
  'civiverify_minimum_ttl' => [
    'name' => 'civiverify_minimum_ttl', 'group' => 'civiverify', 'type' => 'Integer', 'default' => 60,
    'html_type' => 'text', 'title' => E::ts('Minimum validity period (seconds)'), 'is_domain' => 1, 'is_contact' => 0,
    'description' => E::ts('Mandatory lower limit for every validity period. Prevents links from expiring immediately by mistake.'),
  ],
  'civiverify_maximum_ttl' => [
    'name' => 'civiverify_maximum_ttl', 'group' => 'civiverify', 'type' => 'Integer', 'default' => 2592000,
    'html_type' => 'text', 'title' => E::ts('Maximum validity period (seconds)'), 'is_domain' => 1, 'is_contact' => 0,
    'description' => E::ts('Mandatory upper limit for every validity period. The default value of 2,592,000 seconds equals 30 days.'),
  ],
  'civiverify_retention_days' => [
    'name' => 'civiverify_retention_days', 'group' => 'civiverify', 'type' => 'Integer', 'default' => 90,
    'html_type' => 'text', 'title' => E::ts('Retention period (days)'), 'is_domain' => 1, 'is_contact' => 0,
    'description' => E::ts('Non-secret verification metadata is retained for this long after a token expires, for support and audit purposes.'),
  ],
  'civiverify_confirmation_targets' => [
    'name' => 'civiverify_confirmation_targets', 'group' => 'civiverify', 'type' => 'Array',
    'serialize' => CRM_Core_DAO::SERIALIZE_JSON,
    'default' => [['key' => 'civicrm', 'label' => 'CiviCRM', 'route' => 'civicrm/verify']],
    'html_type' => 'textarea', 'title' => E::ts('Trusted confirmation targets'),
    'description' => E::ts('Administrator-managed targets for verification drafts.'),
    'is_domain' => 1, 'is_contact' => 0,
  ],
  'civiverify_verify_drafts' => [
    'name' => 'civiverify_verify_drafts', 'group' => 'civiverify', 'type' => 'Array',
    'serialize' => CRM_Core_DAO::SERIALIZE_JSON, 'default' => [],
    'html_type' => 'textarea', 'title' => E::ts('Verification drafts'),
    'description' => E::ts('Configuration of workflow, target, and default validity period.'),
    'is_domain' => 1, 'is_contact' => 0,
  ],
  'civiverify_success_message' => [
    'name' => 'civiverify_success_message', 'group' => 'civiverify', 'type' => 'String', 'default' => '',
    'html_type' => 'textarea', 'title' => E::ts('Custom success message'), 'is_domain' => 1, 'is_contact' => 0,
    'description' => E::ts('Optional text after a successful confirmation in CiviCRM. Leave empty to use the default message.'),
  ],
  'civiverify_failure_message' => [
    'name' => 'civiverify_failure_message', 'group' => 'civiverify', 'type' => 'String', 'default' => '',
    'html_type' => 'textarea', 'title' => E::ts('Custom error message'), 'is_domain' => 1, 'is_contact' => 0,
    'description' => E::ts('Optional text for invalid, expired, already-used, or revoked confirmation links in CiviCRM. Leave empty to use the default message.'),
  ],
  'civiverify_ip_hashing_enabled' => [
    'name' => 'civiverify_ip_hashing_enabled', 'group' => 'civiverify', 'type' => 'Boolean', 'default' => FALSE,
    'html_type' => 'checkbox', 'title' => E::ts('Enable IP hashing'), 'is_domain' => 1, 'is_contact' => 0,
    'description' => E::ts('Stores a one-way hash of the IP address with verification events for abuse analysis. No plain-text IP address is stored.'),
  ],
  'civiverify_rate_limit_attempts' => [
    'name' => 'civiverify_rate_limit_attempts', 'group' => 'civiverify', 'type' => 'Integer', 'default' => 20,
    'html_type' => 'text', 'title' => E::ts('Verification attempts per period'), 'is_domain' => 1, 'is_contact' => 0,
    'description' => E::ts('Maximum number of verification attempts by one client within the configured period.'),
  ],
  'civiverify_rate_limit_window' => [
    'name' => 'civiverify_rate_limit_window', 'group' => 'civiverify', 'type' => 'Integer', 'default' => 900,
    'html_type' => 'text', 'title' => E::ts('Rate limit period (seconds)'), 'is_domain' => 1, 'is_contact' => 0,
    'description' => E::ts('Period to which the attempt limit applies. The default value of 900 seconds equals 15 minutes.'),
  ],
  'civiverify_direct_get_confirmation' => [
    'name' => 'civiverify_direct_get_confirmation', 'group' => 'civiverify', 'type' => 'Boolean', 'default' => FALSE,
    'html_type' => 'checkbox', 'title' => E::ts('Allow direct confirmation by GET'), 'is_domain' => 1, 'is_contact' => 0,
    'description' => E::ts('When enabled, opening a CiviCRM confirmation link consumes the token. Leave disabled to require explicit confirmation and protect against mail scanners.'),
  ],
];

$weight = 1000;
foreach ($settings as &$setting) {
  // CRM_Admin_Form_Generic identifies its settings page by the final path
  // component ("civiverify"), not by the complete route.
  $setting['settings_pages'] = [
    'civiverify' => ['weight' => $weight],
  ];
  $weight += 10;
}
unset($setting);

// These structured settings have dedicated administration screens. Keeping
// them off the generic settings screen avoids exposing JSON configuration to
// administrators and keeps the responsibilities clearly separated.
foreach (['civiverify_confirmation_targets', 'civiverify_verify_drafts'] as $settingName) {
  $settings[$settingName]['settings_pages'] = [];
}

return $settings;
