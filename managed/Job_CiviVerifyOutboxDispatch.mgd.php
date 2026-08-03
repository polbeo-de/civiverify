<?php

declare(strict_types=1);

use CRM_CiviVerify_ExtensionUtil as E;

return [[
  'name' => 'Job_CiviVerifyOutboxDispatch',
  'entity' => 'Job',
  'cleanup' => 'unused',
  // Always runs whenever the CiviCRM job runner is invoked. Production cron
  // should invoke that runner once a minute because verification is time-critical.
  'update' => 'never',
  'params' => [
    'version' => 3,
    'name' => E::ts('CiviVerify: Deliver verification events'),
    'description' => E::ts('Delivers queued verification lifecycle events after their token changes have committed.'),
    'run_frequency' => 'Always',
    'scheduled_run_date' => gmdate('Y-m-d H:i:s'),
    'api_entity' => 'CiviVerifyToken',
    'api_action' => 'dispatchOutbox',
    'parameters' => 'batch_size=50',
    'is_active' => TRUE,
  ],
]];
