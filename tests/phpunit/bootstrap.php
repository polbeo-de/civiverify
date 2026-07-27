<?php

declare(strict_types=1);

$autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (is_file($autoload)) {
  require_once $autoload;
}

require_once dirname(__DIR__, 2) . '/CRM/CiviVerify/ExtensionUtil.php';

if (!function_exists('ts')) {
  function ts(string $text, array $params = []): string {
    return $text;
  }
}
