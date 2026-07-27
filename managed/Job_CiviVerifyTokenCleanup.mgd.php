<?php

declare(strict_types=1);

use CRM_CiviVerify_ExtensionUtil as E;

// The general CiviCRM cron runner executes scheduled jobs. Seed the first
// execution for the next 02:42 local server time; subsequent executions use
// CiviCRM's daily job frequency.
$now = new DateTimeImmutable('now');
$firstRun = $now->setTime(2, 42, 0);
if ($firstRun <= $now) {
  $firstRun = $firstRun->modify('+1 day');
}

return [[
  'name' => 'Job_CiviVerifyTokenCleanup',
  'entity' => 'Job',
  'cleanup' => 'unused',
  // Administrators may change the schedule or deactivate the job. The
  // extension only supplies its safe initial configuration.
  'update' => 'never',
  'params' => [
    'version' => 3,
    'name' => E::ts('CiviVerify: Clean up verification tokens'),
    'description' => E::ts('Marks expired verification tokens and deletes unneeded data after the configured retention period.'),
    'run_frequency' => 'Daily',
    'scheduled_run_date' => $firstRun->format('Y-m-d H:i:s'),
    'api_entity' => 'CiviVerifyToken',
    'api_action' => 'cleanup',
    // retention_days=0 intentionally delegates to the CiviVerify setting.
    'parameters' => "batch_size=200\nretention_days=0",
    'is_active' => TRUE,
  ],
]];
