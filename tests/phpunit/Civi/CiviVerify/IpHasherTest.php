<?php

declare(strict_types=1);

namespace Civi\CiviVerify;

use PHPUnit\Framework\TestCase;

final class IpHasherTest extends TestCase {

  protected function tearDown(): void {
    unset($_SERVER['REMOTE_ADDR']);
  }

  public function testHashIsStableAndDoesNotRevealAddress(): void {
    $_SERVER['REMOTE_ADDR'] = '192.0.2.15';
    $hasher = new IpHasher(str_repeat('k', 32));
    $first = $hasher->requestHash();
    self::assertSame($first, $hasher->requestHash());
    self::assertSame(64, strlen($first));
    self::assertStringNotContainsString('192.0.2.15', $first);
  }

  public function testDifferentInstallationKeyProducesDifferentHash(): void {
    $_SERVER['REMOTE_ADDR'] = '2001:db8::1';
    self::assertNotSame(
      (new IpHasher(str_repeat('a', 32)))->requestHash(),
      (new IpHasher(str_repeat('b', 32)))->requestHash()
    );
  }

  public function testPersistentHashSkipsUnknownAddress(): void {
    unset($_SERVER['REMOTE_ADDR']);
    self::assertNull((new IpHasher(str_repeat('a', 32)))->persistentRequestHash());
  }

}
