<?php

declare(strict_types=1);

/**
 * Fixture helper for run-concurrency.sh.
 */

$mode = $argv[1] ?? '';
$stateFile = $argv[2] ?? '';
if (!in_array($mode, ['setup', 'verify', 'check', 'cleanup'], TRUE) || $stateFile === '') {
  throw new InvalidArgumentException('Usage: Concurrency.php setup|verify|check|cleanup STATE_FILE [RESULTS]');
}

$readState = static function () use ($stateFile): array {
  $json = is_file($stateFile) ? file_get_contents($stateFile) : FALSE;
  $state = is_string($json) ? json_decode($json, TRUE) : NULL;
  if (!is_array($state)) {
    throw new RuntimeException('Concurrency fixture state is unavailable.');
  }
  return $state;
};

if ($mode === 'setup') {
  $issued = civicrm_api4('CiviVerifyToken', 'issue', [
    'checkPermissions' => FALSE,
    'purpose' => 'integration.concurrency',
    'ttl' => 600,
    'allowUnbound' => TRUE,
  ])->first();
  $state = ['id' => (int) $issued['id'], 'token' => $issued['token']];
  if (file_put_contents($stateFile, json_encode($state, JSON_THROW_ON_ERROR), LOCK_EX) === FALSE) {
    throw new RuntimeException('Could not write concurrency fixture state.');
  }
  chmod($stateFile, 0600);
  print "Concurrency fixture ready.\n";
  return;
}

if ($mode === 'cleanup') {
  if (is_file($stateFile)) {
    $state = $readState();
    CRM_Core_DAO::executeQuery(
      'DELETE FROM civicrm_civiverify_token WHERE id = %1',
      [1 => [(int) $state['id'], 'Integer']]
    );
    unlink($stateFile);
  }
  return;
}

$resultPath = $argv[3] ?? '';
if ($mode === 'verify') {
  if ($resultPath === '') {
    throw new InvalidArgumentException('Verify mode requires one result file.');
  }
  $state = $readState();
  $result = civicrm_api4('CiviVerifyToken', 'verify', [
    'checkPermissions' => FALSE,
    'token' => $state['token'],
  ])->first()['result'];
  file_put_contents($resultPath, $result . "\n", LOCK_EX);
  return;
}

$resultFiles = glob(rtrim($resultPath, '/') . '/*');
$results = array_map(static fn(string $file): string => trim((string) file_get_contents($file)), $resultFiles);
$counts = array_count_values($results);
if (count($results) !== 8 || ($counts['verified'] ?? 0) !== 1 || ($counts['already_used'] ?? 0) !== 7) {
  throw new RuntimeException('Concurrent verification results were not exactly one success and seven rejections.');
}
$state = $readState();
$useCount = (int) CRM_Core_DAO::singleValueQuery(
  'SELECT use_count FROM civicrm_civiverify_token WHERE id = %1',
  [1 => [(int) $state['id'], 'Integer']]
);
if ($useCount !== 1) {
  throw new RuntimeException('Concurrent verification changed use_count more than once.');
}
print "PASS: CiviVerify concurrency test (1 verified, 7 already_used, use_count 1)\n";
