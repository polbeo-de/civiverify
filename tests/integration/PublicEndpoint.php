<?php

declare(strict_types=1);

/**
 * Fixture helper for run-public-endpoint.sh.
 *
 * Two PHP processes are intentional: the issuing transaction must finish
 * before a separate HTTPS request can observe the new token.
 */

$mode = $argv[1] ?? '';
$stateFile = $argv[2] ?? '';
if (!in_array($mode, ['setup', 'test', 'cleanup'], TRUE) || $stateFile === '') {
  throw new InvalidArgumentException('Usage: PublicEndpoint.php setup|test|cleanup STATE_FILE');
}

$readState = static function () use ($stateFile): array {
  $json = is_file($stateFile) ? file_get_contents($stateFile) : FALSE;
  $state = is_string($json) ? json_decode($json, TRUE) : NULL;
  if (!is_array($state)) {
    throw new RuntimeException('Public endpoint fixture state is unavailable.');
  }
  return $state;
};

$cleanup = static function () use ($readState, $stateFile): void {
  if (!is_file($stateFile)) {
    return;
  }
  $state = $readState();
  if (!empty($state['id'])) {
    CRM_Core_DAO::executeQuery(
      'DELETE FROM civicrm_civiverify_token WHERE id = %1',
      [1 => [(int) $state['id'], 'Integer']]
    );
  }
  if (array_key_exists('original_rate_limit', $state)) {
    Civi::settings()->set('civiverify_rate_limit_attempts', $state['original_rate_limit']);
  }
  unlink($stateFile);
};

if ($mode === 'cleanup') {
  $cleanup();
  return;
}

if ($mode === 'setup') {
  $originalRateLimit = Civi::settings()->get('civiverify_rate_limit_attempts');
  Civi::settings()->set('civiverify_rate_limit_attempts', 1000);
  try {
    $issued = civicrm_api4('CiviVerifyToken', 'issue', [
      'checkPermissions' => FALSE,
      'purpose' => 'integration.public',
      'ttl' => 600,
      'allowUnbound' => TRUE,
    ])->first();
    $state = [
      'id' => (int) $issued['id'],
      'token' => $issued['token'],
      'url' => $issued['confirmation_url'],
      'original_rate_limit' => $originalRateLimit,
    ];
    if (file_put_contents($stateFile, json_encode($state, JSON_THROW_ON_ERROR), LOCK_EX) === FALSE) {
      throw new RuntimeException('Could not write public endpoint fixture state.');
    }
    chmod($stateFile, 0600);
    print "Public endpoint fixture ready.\n";
  }
  catch (Throwable $e) {
    Civi::settings()->set('civiverify_rate_limit_attempts', $originalRateLimit);
    throw $e;
  }
  return;
}

$state = $readState();
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
  $assertions++;
};

$request = static function (string $url, ?string $postData = NULL): array {
  $options = [
    'http' => [
      'method' => $postData === NULL ? 'GET' : 'POST',
      'header' => 'Accept: text/html',
      'content' => $postData ?? '',
      'ignore_errors' => TRUE,
      'timeout' => 20,
    ],
  ];
  if ($postData !== NULL) {
    $options['http']['header'] .= "\r\nContent-Type: application/x-www-form-urlencoded";
  }
  $body = file_get_contents($url, FALSE, stream_context_create($options));
  if ($body === FALSE) {
    throw new RuntimeException('HTTP request failed.');
  }
  return [$body, $http_response_header ?? []];
};

$hasHeader = static function (array $headers, string $expected): bool {
  foreach ($headers as $header) {
    if (str_starts_with(strtolower($header), strtolower($expected))) {
      return TRUE;
    }
  }
  return FALSE;
};

[$preview, $getHeaders] = $request($state['url']);
$assert($hasHeader($getHeaders, 'HTTP/1.1 200'), 'Preview did not return HTTP 200.');
$assert($hasHeader($getHeaders, 'Cache-Control: no-store'), 'Preview lacks no-store.');
$assert($hasHeader($getHeaders, 'Referrer-Policy: no-referrer'), 'Preview lacks no-referrer.');
$assert($hasHeader($getHeaders, 'X-Content-Type-Options: nosniff'), 'Preview lacks nosniff.');
$assert($hasHeader($getHeaders, 'X-Frame-Options: DENY'), 'Preview lacks frame denial.');
$assert(!str_contains($preview, $state['token']), 'Preview HTML exposed the raw token.');
preg_match('/<h1>([^<]+)<\/h1>/', $preview, $heading);
$assert(
  str_contains($preview, '<form'),
  'Preview did not render a confirmation form; result heading: ' . ($heading[1] ?? 'unknown')
);
$assert(
  preg_match('/name="state" value="([a-f0-9]{48})"/', $preview, $matches) === 1,
  'Preview did not contain a valid server-side state nonce.'
);
$statusAfterGet = CRM_Core_DAO::singleValueQuery(
  'SELECT status FROM civicrm_civiverify_token WHERE id = %1',
  [1 => [(int) $state['id'], 'Integer']]
);
$assert($statusAfterGet === 'pending', 'GET consumed the token.');

$postUrl = strtok($state['url'], '?');
[$confirmed, $postHeaders] = $request($postUrl, http_build_query(['state' => $matches[1]]));
$assert($hasHeader($postHeaders, 'HTTP/1.1 200'), 'Confirmation did not return HTTP 200.');
$assert(!str_contains($confirmed, $state['token']), 'Confirmation HTML exposed the raw token.');
preg_match('/<h1>([^<]+)<\/h1>/', $confirmed, $confirmedHeading);
$assert(
  str_contains($confirmed, 'Confirmation successful'),
  'POST did not confirm the token; result heading: ' . ($confirmedHeading[1] ?? 'unknown')
);
$used = CRM_Core_DAO::executeQuery(
  'SELECT status, use_count FROM civicrm_civiverify_token WHERE id = %1',
  [1 => [(int) $state['id'], 'Integer']]
);
$assert($used->fetch() && $used->status === 'used', 'POST did not persist used status.');
$assert((int) $used->use_count === 1, 'POST did not increment use_count exactly once.');

[$replay] = $request($postUrl, http_build_query(['state' => $matches[1]]));
$assert(str_contains($replay, 'Link unavailable'), 'A reused state nonce was not rejected.');
$useCount = (int) CRM_Core_DAO::singleValueQuery(
  'SELECT use_count FROM civicrm_civiverify_token WHERE id = %1',
  [1 => [(int) $state['id'], 'Integer']]
);
$assert($useCount === 1, 'A reused state nonce consumed the token again.');

printf("PASS: CiviVerify public endpoint test (%d assertions)\n", $assertions);
