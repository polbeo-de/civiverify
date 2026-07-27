<?php

declare(strict_types=1);

namespace Civi\CiviVerify;

use PHPUnit\Framework\TestCase;

final class TokenHasherTest extends TestCase {

  public function testGeneratedTokenIsUrlSafeAndHas256BitsOfEntropy(): void {
    $hasher = new TokenHasher(str_repeat('s', 32));
    $token = $hasher->generate();
    self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $token);
    self::assertSame(32, strlen(base64_decode(strtr($token, '-_', '+/') . '=', TRUE)));
  }

  public function testHashIsStableKeyedAndDoesNotContainToken(): void {
    $token = 'v_8dYzNLVo2kQliP9L6a99GLAwf6Uw2cUtU_J6Yso2k';
    $first = new TokenHasher(str_repeat('a', 32));
    $second = new TokenHasher(str_repeat('b', 32));
    self::assertSame($first->hash($token), $first->hash($token));
    self::assertNotSame($first->hash($token), $second->hash($token));
    self::assertSame(64, strlen($first->hash($token)));
    self::assertStringNotContainsString($token, $first->hash($token));
  }

}
