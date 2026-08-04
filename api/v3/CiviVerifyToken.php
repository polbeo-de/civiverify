<?php

declare(strict_types=1);

use CRM_CiviVerify_ExtensionUtil as E;

/**
 * API3 bridge for the scheduled CiviVerify outbox delivery job.
 *
 * The actual dispatcher is shared with the API4 action so both invocation
 * paths have identical locking, retry, and delivery semantics.
 */
function _civicrm_api3_civi_verify_token_dispatchoutbox_spec(array &$spec): void {
  $spec['batch_size'] = [
    'title' => E::ts('Outbox batch size'),
    'type' => CRM_Utils_Type::T_INT,
    'api.default' => 50,
  ];
}

/**
 * Deliver a batch of committed CiviVerify lifecycle events.
 */
function civicrm_api3_civi_verify_token_dispatchoutbox(array $params): array {
  $batchSize = (int) ($params['batch_size'] ?? $params['batchSize'] ?? 50);

  try {
    return civicrm_api3_create_success(
      \Civi::service('civiverify.outbox_dispatcher')->dispatch($batchSize)
    );
  }
  catch (\Throwable $exception) {
    \Civi::log()->error(E::ts('CiviVerify outbox delivery failed.'), [
      'exception_class' => $exception::class,
    ]);
    return civicrm_api3_create_error(E::ts('CiviVerify outbox delivery failed.'));
  }
}

/** Define parameters for the scheduled token-cleanup job. */
function _civicrm_api3_civi_verify_token_cleanup_spec(array &$spec): void {
  $spec['batch_size'] = [
    'title' => E::ts('Cleanup batch size'),
    'type' => CRM_Utils_Type::T_INT,
    'api.default' => 200,
  ];
  $spec['retention_days'] = [
    'title' => E::ts('Retention period in days'),
    'type' => CRM_Utils_Type::T_INT,
    'api.default' => 0,
  ];
}

/** Expire tokens and remove retained terminal data through the shared manager. */
function civicrm_api3_civi_verify_token_cleanup(array $params): array {
  $batchSize = (int) ($params['batch_size'] ?? $params['batchSize'] ?? 200);
  $retentionDays = (int) ($params['retention_days'] ?? $params['retentionDays'] ?? 0);
  if ($retentionDays === 0) {
    $retentionDays = (int) \Civi::settings()->get('civiverify_retention_days');
  }

  try {
    return civicrm_api3_create_success(
      \Civi::service('civiverify.manager')->cleanup($batchSize, $retentionDays)
    );
  }
  catch (\Throwable $exception) {
    \Civi::log()->error(E::ts('CiviVerify token cleanup failed.'), [
      'exception_class' => $exception::class,
    ]);
    return civicrm_api3_create_error(E::ts('CiviVerify token cleanup failed.'));
  }
}
