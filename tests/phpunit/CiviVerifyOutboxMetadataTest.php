<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CiviVerifyOutboxMetadataTest extends TestCase {

  public function testMetadataIsAvailableWithoutGrantingOutboxRecordAccess(): void {
    $source = file_get_contents(dirname(__DIR__, 2) . '/Civi/Api4/CiviVerifyOutbox.php');

    self::assertIsString($source);
    self::assertMatchesRegularExpression(
      "/'meta'\\s*=>\\s*\\['access CiviCRM'\\]/",
      $source
    );
    self::assertMatchesRegularExpression(
      "/'default'\\s*=>\\s*\\['administer verification tokens'\\]/",
      $source
    );
  }

}
