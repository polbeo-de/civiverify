<?php

declare(strict_types=1);

namespace Civi\CiviVerify;

use Civi\CiviVerify\Event\TokenEvent;

/**
 * Atomically consume a verification only after a domain callback succeeds.
 *
 * This service is for trusted extension code. The callback runs inside the
 * database transaction and receives a sanitized record only. It must avoid
 * external side effects and be idempotent if its application can retry work.
 */
final class VerificationRedeemer {

  private const BINDING_FIELDS = ['purpose', 'contact_id', 'entity_name', 'entity_id'];

  public function __construct(
    private readonly VerificationRepository $repository,
    private readonly OutboxRepository $outbox,
    private readonly TokenHasher $hasher,
  ) {}

  public function redeem(string $rawToken, array $expectedBinding, callable $apply): VerificationResult {
    if (!preg_match('/^[A-Za-z0-9_-]{43}$/', $rawToken)) {
      return new VerificationResult('invalid');
    }
    $expectedBinding = $this->validateBinding($expectedBinding);
    $hash = $this->hasher->hash($rawToken);
    $now = gmdate('Y-m-d H:i:s');
    $tx = new \CRM_Core_Transaction();
    try {
      $record = $this->repository->findByHashForUpdate($hash);
      if ($record === NULL) {
        $tx->commit();
        return new VerificationResult('invalid');
      }
      $result = $this->unavailableResult($record, $now);
      if ($result !== NULL) {
        $tx->commit();
        return new VerificationResult($result, $record);
      }
      if (!$this->matchesBinding($record, $expectedBinding)) {
        $tx->commit();
        return new VerificationResult('invalid', $record);
      }

      // $record has already been stripped of tokens, hashes, and IP hashes.
      $apply($record);
      if (!$this->repository->consumeLocked((int) $record['id'], $now)) {
        throw new \RuntimeException('Locked verification record could not be consumed.');
      }
      $verified = $this->repository->findById((int) $record['id']);
      if ($verified === NULL) {
        throw new \RuntimeException('Consumed verification record was not found.');
      }
      $this->outbox->enqueue((int) $verified['id'], TokenEvent::VERIFIED, $verified, $now);
      $tx->commit();
      return new VerificationResult('verified', $verified);
    }
    catch (\Throwable $e) {
      $tx->rollback();
      throw $e;
    }
  }

  private function validateBinding(array $expectedBinding): array {
    foreach ($expectedBinding as $field => $value) {
      if (!in_array($field, self::BINDING_FIELDS, TRUE)) {
        throw new \InvalidArgumentException('Unsupported verification binding field.');
      }
      if ($field === 'purpose' || $field === 'entity_name') {
        if (!is_string($value)) {
          throw new \InvalidArgumentException('Verification binding values must have the expected type.');
        }
      }
      elseif ($value !== NULL && (!is_int($value) || $value < 1)) {
        throw new \InvalidArgumentException('Verification binding values must have the expected type.');
      }
    }
    return $expectedBinding;
  }

  private function matchesBinding(array $record, array $expectedBinding): bool {
    foreach ($expectedBinding as $field => $value) {
      if (($field === 'contact_id' || $field === 'entity_id') && $value !== NULL) {
        if ((int) ($record[$field] ?? 0) !== $value) {
          return FALSE;
        }
      }
      elseif (($record[$field] ?? NULL) !== $value) {
        return FALSE;
      }
    }
    return TRUE;
  }

  private function unavailableResult(array $record, string $now): ?string {
    return match ($record['status']) {
      'used' => 'already_used',
      'revoked' => 'revoked',
      'expired' => 'expired',
      'pending' => (string) $record['expires_date'] <= $now ? 'expired' : NULL,
      default => 'invalid',
    };
  }

}
