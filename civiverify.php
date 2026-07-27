<?php

declare(strict_types=1);

// CRM_Core_Controller still loads legacy CRM_* form classes through PHP's
// include_path. Composer knows this extension, but the controller does not
// consult that loader for forms. Register our root explicitly before any
// menu callback can create a form controller.
if (!in_array(__DIR__, explode(PATH_SEPARATOR, get_include_path()), TRUE)) {
  set_include_path(__DIR__ . PATH_SEPARATOR . get_include_path());
}

require_once __DIR__ . '/CRM/CiviVerify/ExtensionUtil.php';
require_once __DIR__ . '/CRM/CiviVerify/Form/ConfirmationTargets.php';
require_once __DIR__ . '/CRM/CiviVerify/Form/VerifyDrafts.php';

use Civi\CiviVerify\TokenHasher;
use Civi\CiviVerify\IpHasher;
use Civi\CiviVerify\RateLimiter;
use Civi\CiviVerify\ConfirmationUrlBuilder;
use Civi\CiviVerify\MailTokenSubscriber;
use Civi\CiviVerify\CiviRules\EventSubscriber as CiviRulesEventSubscriber;
use Civi\CiviVerify\CiviRules\Registrar as CiviRulesRegistrar;
use Civi\CiviVerify\VerificationIssuer;
use Civi\CiviVerify\VerificationManager;
use Civi\CiviVerify\VerificationMailer;
use Civi\CiviVerify\VerificationRepository;
use Civi\CiviVerify\VerificationVerifier;
use Civi\CiviVerify\VerifyDraftRegistry;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Implements hook_civicrm_container().
 */
function civiverify_civicrm_container(ContainerBuilder $container): void {
  $container->register('civiverify.token_hasher', TokenHasher::class);
  $container->register('civiverify.ip_hasher', IpHasher::class)
    ->setPublic(TRUE);
  $container->register('civiverify.rate_limiter', RateLimiter::class)
    ->setArguments([new Reference('civiverify.ip_hasher')])
    ->setPublic(TRUE);
  $container->register('civiverify.confirmation_url_builder', ConfirmationUrlBuilder::class)
    ->setPublic(TRUE);
  $container->register('civiverify.verify_draft_registry', VerifyDraftRegistry::class)
    ->setPublic(TRUE);
  $container->register('civiverify.repository', VerificationRepository::class);
  $container->register('civiverify.issuer', VerificationIssuer::class)
    ->setArguments([
      new Reference('civiverify.repository'),
      new Reference('civiverify.token_hasher'),
      new Reference('civiverify.ip_hasher'),
    ])
    ->setPublic(TRUE);
  $container->register('civiverify.verifier', VerificationVerifier::class)
    ->setArguments([new Reference('civiverify.repository'), new Reference('civiverify.token_hasher')])
    ->setPublic(TRUE);
  $container->register('civiverify.manager', VerificationManager::class)
    ->setArguments([new Reference('civiverify.repository')])
    ->setPublic(TRUE);
  $container->register('civiverify.mailer', VerificationMailer::class)
    ->setArguments([
      new Reference('civiverify.issuer'),
      new Reference('civiverify.manager'),
      new Reference('civiverify.confirmation_url_builder'),
      new Reference('civiverify.verify_draft_registry'),
    ])
    ->setPublic(TRUE);
  $container->register('civiverify.mail_token_subscriber', MailTokenSubscriber::class)
    ->addTag('kernel.event_subscriber')
    ->setPublic(TRUE);
  $container->register('civiverify.civirules.event_subscriber', CiviRulesEventSubscriber::class)
    ->addTag('kernel.event_subscriber')
    ->setPublic(TRUE);
}

/**
 * Implements hook_civicrm_config().
 */
function civiverify_civicrm_config(\CRM_Core_Config &$config): void {
  CiviRulesRegistrar::sync();
}

/**
 * Implements hook_civicrm_enable().
 */
function civiverify_civicrm_enable(): void {
  CiviRulesRegistrar::sync(TRUE);
}

/**
 * Implements hook_civicrm_disable().
 */
function civiverify_civicrm_disable(): void {
  CiviRulesRegistrar::deactivate();
}

/**
 * Implements hook_civicrm_uninstall().
 */
function civiverify_civicrm_uninstall(): void {
  CiviRulesRegistrar::deactivate();
}

/**
 * Implements hook_civicrm_permission().
 */
function civiverify_civicrm_permission(array &$permissions): void {
  $permissions['administer verification tokens'] = [
    'label' => CRM_CiviVerify_ExtensionUtil::ts('Administer verification tokens'),
    'description' => CRM_CiviVerify_ExtensionUtil::ts('Full access to verification data and its configuration.'),
  ];
  $permissions['issue verification tokens'] = [
    'label' => CRM_CiviVerify_ExtensionUtil::ts('Issue verification tokens'),
    'description' => CRM_CiviVerify_ExtensionUtil::ts('Issue new single-use verification tokens.'),
  ];
  $permissions['view verification tokens'] = [
    'label' => CRM_CiviVerify_ExtensionUtil::ts('View verification tokens'),
    'description' => CRM_CiviVerify_ExtensionUtil::ts('View non-secret verification data.'),
  ];
  $permissions['revoke verification tokens'] = [
    'label' => CRM_CiviVerify_ExtensionUtil::ts('Revoke verification tokens'),
    'description' => CRM_CiviVerify_ExtensionUtil::ts('Revoke pending verification tokens.'),
  ];
}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * CiviVerify is an administrative component. Keep it under Administration,
 * rather than exposing it in a functional top-level menu.
 */
function civiverify_civicrm_navigationMenu(array &$menu): void {
  $administerId = (int) \CRM_Core_DAO::getFieldValue(
    'CRM_Core_DAO_Navigation',
    'Administer',
    'id',
    'name'
  );
  if (!$administerId || empty($menu[$administerId])) {
    return;
  }
  foreach ($menu[$administerId]['child'] ?? [] as $item) {
    if (($item['attributes']['name'] ?? NULL) === 'CiviVerify') {
      return;
    }
  }
  $nextId = civiverify_nextNavigationId($menu);
  $componentId = $nextId;
  $settingsId = $nextId + 1;
  $menu[$administerId]['child'][$componentId] = [
    'attributes' => [
      'label' => CRM_CiviVerify_ExtensionUtil::ts('CiviVerify'),
      'name' => 'CiviVerify',
      'url' => NULL,
      'permission' => 'administer verification tokens',
      'operator' => 'OR',
      'separator' => 0,
      'parentID' => $administerId,
      'navID' => $componentId,
      'active' => 1,
    ],
    'child' => [
      $settingsId => [
        'attributes' => [
          'label' => CRM_CiviVerify_ExtensionUtil::ts('Settings'),
          'name' => 'CiviVerifySettings',
          'url' => 'civicrm/admin/setting/civiverify?reset=1',
          'permission' => 'administer verification tokens',
          'operator' => 'OR',
          'separator' => 0,
          'parentID' => $componentId,
          'navID' => $settingsId,
          'active' => 1,
        ],
      ],
      $settingsId + 1 => [
        'attributes' => [
          'label' => CRM_CiviVerify_ExtensionUtil::ts('Confirmation targets'),
          'name' => 'CiviVerifyConfirmationTargets',
          'url' => 'civicrm/admin/civiverify/targets?reset=1',
          'permission' => 'administer verification tokens',
          'operator' => 'OR', 'separator' => 0, 'parentID' => $componentId,
          'navID' => $settingsId + 1, 'active' => 1,
        ],
      ],
      $settingsId + 2 => [
        'attributes' => [
          'label' => CRM_CiviVerify_ExtensionUtil::ts('Verification drafts'),
          'name' => 'CiviVerifyVerifyDrafts',
          'url' => 'civicrm/admin/civiverify/verify-drafts?reset=1',
          'permission' => 'administer verification tokens',
          'operator' => 'OR', 'separator' => 0, 'parentID' => $componentId,
          'navID' => $settingsId + 2, 'active' => 1,
        ],
      ],
    ],
  ];
}

/** Allocate IDs from the in-memory tree so dynamic extensions cannot collide. */
function civiverify_nextNavigationId(array $menu): int {
  $ids = [];
  $collect = function (array $items) use (&$collect, &$ids): void {
    foreach ($items as $key => $item) {
      $ids[] = (int) ($item['attributes']['navID'] ?? $key);
      if (!empty($item['child']) && is_array($item['child'])) {
        $collect($item['child']);
      }
    }
  };
  $collect($menu);
  return max(array_merge([0], $ids)) + 1;
}
