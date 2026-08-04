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
    // Do not expose event payloads or other operational details through a
    // scheduled-job result. The queue view retains the diagnostic status.
    return civicrm_api3_create_error(E::ts('CiviVerify outbox delivery failed.'));
  }
}
