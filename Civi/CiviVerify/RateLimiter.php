<?php

declare(strict_types=1);

namespace Civi\CiviVerify;

final class RateLimiter {

  public function __construct(private readonly IpHasher $ipHasher) {}

  public function consume(): bool {
    $limit = max(1, (int) \Civi::settings()->get('civiverify_rate_limit_attempts'));
    $window = max(60, (int) \Civi::settings()->get('civiverify_rate_limit_window'));
    $bucket = (int) floor(time() / $window);
    $key = 'civiverify_rate_' . $bucket . '_' . $this->ipHasher->requestHash();
    $cache = \Civi::cache('long');
    $attempts = (int) ($cache->get($key) ?? 0);
    if ($attempts >= $limit) {
      return FALSE;
    }
    $cache->set($key, $attempts + 1, $window + 60);
    return TRUE;
  }

}
