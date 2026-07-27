<?php

declare(strict_types=1);

namespace Civi\CiviVerify\CiviRules;

use Civi\Api4\CiviRulesAction;
use Civi\Api4\CiviRulesTrigger;
use CRM_CiviVerify_ExtensionUtil as E;

final class Registrar {

  private const CACHE_KEY = 'civiverify_civirules_components_v2';

  /**
   * @return array<int, array<string, mixed>>
   */
  public static function records(): array {
    return [
      [
        'name' => 'civiverify_token_issued',
        'label' => E::ts('CiviVerify: Verification issued'),
        'class_name' => 'CRM_CiviVerify_CiviRules_Trigger_Issued',
      ],
      [
        'name' => 'civiverify_token_verified',
        'label' => E::ts('CiviVerify: Verification confirmed'),
        'class_name' => 'CRM_CiviVerify_CiviRules_Trigger_Verified',
      ],
      [
        'name' => 'civiverify_token_revoked',
        'label' => E::ts('CiviVerify: Verification revoked'),
        'class_name' => 'CRM_CiviVerify_CiviRules_Trigger_Revoked',
      ],
      [
        'name' => 'civiverify_token_expired',
        'label' => E::ts('CiviVerify: Verification expired'),
        'class_name' => 'CRM_CiviVerify_CiviRules_Trigger_Expired',
      ],
    ];
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  public static function actionRecords(): array {
    return [[
      'name' => 'civiverify_issue_and_send',
      'label' => E::ts('CiviVerify: Issue and send verification'),
      'class_name' => 'CRM_CiviVerify_CiviRules_Action_IssueAndSend',
    ]];
  }

  public static function sync(bool $force = FALSE): void {
    if (!self::isAvailable()) {
      return;
    }
    $cache = \Civi::cache('long');
    if (!$force && $cache->get(self::CACHE_KEY) === 'registered') {
      return;
    }
    $records = array_map(static fn(array $record): array => $record + [
      'object_name' => NULL,
      'op' => NULL,
      'cron' => FALSE,
      'is_active' => TRUE,
    ], self::records());
    try {
      CiviRulesTrigger::save(FALSE)
        ->setRecords($records)
        ->setMatch(['name'])
        ->execute();
      CiviRulesAction::save(FALSE)
        ->setRecords(array_map(static fn(array $record): array => $record + [
          'is_active' => TRUE,
        ], self::actionRecords()))
        ->setMatch(['name'])
        ->execute();
      $cache->set(self::CACHE_KEY, 'registered');
    }
    catch (\Throwable $e) {
      // During CiviRules installation its classes and tables can be available
      // before its API entity provider. A later config hook retries the sync.
      \Civi::log('civiverify')->warning('CiviRules trigger registration deferred: ' . $e->getMessage());
    }
  }

  public static function deactivate(): void {
    if (!self::isAvailable()) {
      return;
    }
    try {
      CiviRulesTrigger::update(FALSE)
        ->addValue('is_active', FALSE)
        ->addWhere('name', 'IN', array_column(self::records(), 'name'))
        ->execute();
      CiviRulesAction::update(FALSE)
        ->addValue('is_active', FALSE)
        ->addWhere('name', 'IN', array_column(self::actionRecords(), 'name'))
        ->execute();
    }
    catch (\Throwable $e) {
      \Civi::log('civiverify')->warning('CiviRules trigger deactivation deferred: ' . $e->getMessage());
    }
    \Civi::cache('long')->delete(self::CACHE_KEY);
  }

  private static function isAvailable(): bool {
    return class_exists(CiviRulesTrigger::class)
      && class_exists(CiviRulesAction::class)
      && class_exists('CRM_Civirules_BAO_Rule')
      && \CRM_Core_DAO::checkTableExists('civirule_trigger')
      && \CRM_Core_DAO::checkTableExists('civirule_action');
  }

}
