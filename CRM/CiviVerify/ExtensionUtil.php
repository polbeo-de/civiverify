<?php

declare(strict_types=1);

/**
 * Extension-local helpers generated in the style used by civix.
 */
final class CRM_CiviVerify_ExtensionUtil {

  public const LONG_NAME = 'de.polbeo.civicrm.civiverify';

  public const SHORT_NAME = 'civiverify';

  /**
   * Translate a string in this extension's text domain.
   */
  public static function ts(string $text, array $params = []): string {
    if (!array_key_exists('domain', $params)) {
      $params['domain'] = self::LONG_NAME;
    }
    return ts($text, $params);
  }

  /**
   * Resolve a file within the extension.
   */
  public static function path(string $file = ''): string {
    return rtrim(__DIR__ . '/../../', '/') . ($file === '' ? '' : '/' . ltrim($file, '/'));
  }

}
