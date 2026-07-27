<?php

declare(strict_types=1);

namespace Civi\CiviVerify;

final class IpHasher {

  private string $key;

  public function __construct(?string $siteKey = NULL) {
    $siteKey ??= defined('CIVICRM_SITE_KEY') ? (string) CIVICRM_SITE_KEY : '';
    if (strlen($siteKey) < 16) {
      throw new \RuntimeException('CIVICRM_SITE_KEY must contain at least 16 bytes.');
    }
    // Keep this legacy context stable: rate-limit and audit hashes must remain
    // comparable across the extension-key correction in 0.1.3.
    $this->key = hash_hkdf('sha256', $siteKey, 32, 'de.polbeo.civirm.civiverify/ip-hmac');
  }

  public function requestHash(): string {
    $ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: 'unknown';
    return hash_hmac('sha256', (string) $ip, $this->key);
  }

  public function persistentRequestHash(): ?string {
    $ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP);
    return $ip === FALSE ? NULL : hash_hmac('sha256', (string) $ip, $this->key);
  }

}
