<?php

declare(strict_types=1);

namespace Civi\CiviVerify;

use PHPUnit\Framework\TestCase;

final class UuidTest extends TestCase {

  public function testV4FormatAndUniqueness(): void {
    $first = Uuid::v4();
    $second = Uuid::v4();
    self::assertMatchesRegularExpression(
      '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
      $first
    );
    self::assertNotSame($first, $second);
  }

}
