<?php

declare(strict_types=1);

namespace Civi\CiviVerify;

use Civi\CiviVerify\Event\TokenEvent;

final class VerificationVerifier {

  public function __construct(
    private readonly VerificationRepository $repository,
    private readonly OutboxRepository $outbox,
    private readonly TokenHasher $hasher,
  ) {}

  public function verify(string $rawToken, ?string $ipHash = NULL): VerificationResult {
    $hash = $this->hasher->hash($rawToken);
    if (!preg_match('/^[A-Za-z0-9_-]{43}$/', $rawToken)) {
      return new VerificationResult('invalid');
    }
    $now = gmdate('Y-m-d H:i:s');
    $tx = new \CRM_Core_Transaction();
    try {
      if ($this->repository->consume($hash, $now, $ipHash)) {
        $record = $this->repository->findByHash($hash);
        if ($record === NULL) {
          throw new \RuntimeException('Consumed verification record was not found.');
        }
        $this->outbox->enqueue((int) $record['id'], TokenEvent::VERIFIED, $record, $now);
        $tx->commit();
        return new VerificationResult('verified', $record);
      }
      $tx->commit();
    }
    catch (\Throwable $e) {
      $tx->rollback();
      throw $e;
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

  public function verifyCode(string $uuid, string $code, ?string $ipHash = NULL): VerificationResult {
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid)
      || !preg_match('/^[0-9]{6}$/', $code)) {
      return new VerificationResult('invalid');
    }
    $hash = $this->hasher->hashCode($code);
    $now = gmdate('Y-m-d H:i:s');
    $tx = new \CRM_Core_Transaction();
    try {
      if ($this->repository->consumeCode($uuid, $hash, $now, $ipHash)) {
        $record = $this->repository->findByUuid($uuid);
        if ($record === NULL) {
          throw new \RuntimeException('Consumed verification record was not found.');
        }
        $this->outbox->enqueue((int) $record['id'], TokenEvent::VERIFIED, $record, $now);
        $tx->commit();
        return new VerificationResult('verified', $record);
      }
      $tx->commit();
    }
    catch (\Throwable $e) {
      $tx->rollback();
      throw $e;
    }
    $record = $this->repository->findByUuidAndCodeHash($uuid, $hash);
    if ($record === NULL) {
      return new VerificationResult('invalid');
    }
    return new VerificationResult(match ($record['status']) {
      'used' => 'already_used',
      'revoked' => 'revoked',
      'expired' => 'expired',
      default => $this->isExpired($record) ? 'expired' : 'invalid',
    }, $record);
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
