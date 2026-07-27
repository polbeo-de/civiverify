<?php

declare(strict_types=1);

namespace Civi\CiviVerify;

final class TokenHasher {

  private string $key;

  public function __construct(?string $siteKey = NULL) {
    $siteKey ??= defined('CIVICRM_SITE_KEY') ? (string) CIVICRM_SITE_KEY : '';
    if (strlen($siteKey) < 16) {
      throw new \RuntimeException('CIVICRM_SITE_KEY must contain at least 16 bytes.');
    }
    // Keep this legacy context stable: existing pending links were hashed with
    // it before the extension key was corrected in 0.1.3.
    $this->key = hash_hkdf('sha256', $siteKey, 32, 'de.polbeo.civirm.civiverify/token-hmac');
  }

  public function generate(): string {
    return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
  }

  public function hash(string $token): string {
    return hash_hmac('sha256', $token, $this->key);
  }

}
