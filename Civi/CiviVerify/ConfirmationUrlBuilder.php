<?php

declare(strict_types=1);

namespace Civi\CiviVerify;

final class ConfirmationUrlBuilder {

  public function build(string $rawToken, ?array $target = NULL): string {
    $route = (string) ($target['route'] ?? 'civicrm/verify');
    // An absolute, administrator-configured URL may hand the presentation and
    // explicit POST confirmation to a trusted frontend. The raw token remains
    // a bearer secret, so this is deliberately a domain setting, never an API
    // parameter supplied by a caller.
    $scheme = parse_url($route, PHP_URL_SCHEME);
    $host = parse_url($route, PHP_URL_HOST);
    $isLocalTestRoute = $scheme === 'http' && in_array($host, ['localhost', '127.0.0.1'], TRUE);
    if (filter_var($route, FILTER_VALIDATE_URL) && ($scheme === 'https' || $isLocalTestRoute)) {
      $fragment = '';
      if (str_contains($route, '#')) {
        [$route, $fragment] = explode('#', $route, 2);
        $fragment = '#' . $fragment;
      }
      return $route . (str_contains($route, '?') ? '&' : '?')
        . 'token=' . rawurlencode($rawToken) . $fragment;
    }
    return \CRM_Utils_System::url(
      $route,
      'token=' . rawurlencode($rawToken),
      TRUE,
      NULL,
      FALSE,
      TRUE
    );
  }

}
