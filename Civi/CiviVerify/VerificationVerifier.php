<?php

declare(strict_types=1);

namespace Civi\CiviVerify;

use Civi\CiviVerify\Event\TokenEvent;

final class VerificationVerifier {

  public function __construct(
    private readonly VerificationRepository $repository,
    private readonly TokenHasher $hasher,
  ) {}

  public function verify(string $rawToken, ?string $ipHash = NULL): VerificationResult {
    $hash = $this->hasher->hash($rawToken);
    if (!preg_match('/^[A-Za-z0-9_-]{43}$/', $rawToken)) {
      return new VerificationResult('invalid');
    }
    $now = gmdate('Y-m-d H:i:s');
    if ($this->repository->consume($hash, $now, $ipHash)) {
      $record = $this->repository->findByHash($hash);
      \Civi::dispatcher()->dispatch(TokenEvent::VERIFIED, new TokenEvent($record ?? []));
      return new VerificationResult('verified', $record);
    }
    $record = $this->repository->findByHash($hash);
    if ($record === NULL) {
      return new VerificationResult('invalid');
    }
    $result = match ($record['status']) {
      'used' => 'already_used',
      'revoked' => 'revoked',
      'expired' => 'expired',
      default => $this->isExpired($record) ? 'expired' : 'invalid',
    };
    return new VerificationResult($result, $record);
  }

  public function inspect(string $rawToken): VerificationResult {
    $hash = $this->hasher->hash($rawToken);
    if (!preg_match('/^[A-Za-z0-9_-]{43}$/', $rawToken)) {
      return new VerificationResult('invalid');
    }
    $record = $this->repository->findByHash($hash);
    if ($record === NULL) {
      return new VerificationResult('invalid');
    }
    $result = match ($record['status']) {
      'used' => 'already_used',
      'revoked' => 'revoked',
      'expired' => 'expired',
      default => $this->isExpired($record) ? 'expired' : 'pending',
    };
    return new VerificationResult($result, $record);
  }

  private function isExpired(array $record): bool {
    $expires = \DateTimeImmutable::createFromFormat(
      '!Y-m-d H:i:s',
      (string) $record['expires_date'],
      new \DateTimeZone('UTC')
    );
    return $expires === FALSE || $expires->getTimestamp() <= time();
  }

}
