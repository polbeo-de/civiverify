<?php

declare(strict_types=1);

$mode = $argv[1] ?? '';
$stateFile = $argv[2] ?? '';
if (!in_array($mode, ['setup', 'redeem', 'check', 'cleanup'], TRUE) || $stateFile === '') {
  throw new InvalidArgumentException('Usage: RedeemConcurrency.php setup|redeem|check|cleanup STATE_FILE [RESULTS]');
}
$readState = static function () use ($stateFile): array {
  $state = is_file($stateFile) ? json_decode((string) file_get_contents($stateFile), TRUE) : NULL;
  if (!is_array($state)) {
    throw new RuntimeException('Redemption concurrency fixture state is unavailable.');
  }
  return $state;
};
if ($mode === 'setup') {
  $issued = Civi::service('civiverify.issuer')->issue([
    'purpose' => 'integration.redeem-concurrency', 'ttl' => 600, 'allow_unbound' => TRUE,
  ]);
  file_put_contents(
    $stateFile, json_encode(['id' => $issued['id'], 'token' => $issued['token']], JSON_THROW_ON_ERROR), LOCK_EX
  );
  chmod($stateFile, 0600);
  return;
}
if ($mode === 'cleanup') {
  if (is_file($stateFile)) {
    $state = $readState();
    CRM_Core_DAO::executeQuery(
      'DELETE FROM civicrm_civiverify_outbox WHERE token_id = %1', [1 => [(int) $state['id'], 'Integer']]
    );
    CRM_Core_DAO::executeQuery(
      'DELETE FROM civicrm_civiverify_token WHERE id = %1', [1 => [(int) $state['id'], 'Integer']]
    );
    unlink($stateFile);
  }
  return;
}
$resultPath = $argv[3] ?? '';
if ($mode === 'redeem') {
  $state = $readState();
  $result = Civi::service('civiverify.redeemer')->redeem($state['token'], [], static function (): void {})->result;
  file_put_contents($resultPath, $result . "\n", LOCK_EX);
  return;
}
$counts = array_count_values(array_map(
  static fn(string $file): string => trim((string) file_get_contents($file)),
  glob(rtrim($resultPath, '/') . '/*')
));
if (($counts['verified'] ?? 0) !== 1 || ($counts['already_used'] ?? 0) !== 7) {
  throw new RuntimeException('Concurrent redemptions were not exactly one success and seven already_used results.');
}
$state = $readState();
if ((int) CRM_Core_DAO::singleValueQuery(
  'SELECT use_count FROM civicrm_civiverify_token WHERE id = %1', [1 => [(int) $state['id'], 'Integer']]
) !== 1) {
  throw new RuntimeException('Concurrent redemption changed use_count more than once.');
}
print "PASS: atomic redemption concurrency test\n";
