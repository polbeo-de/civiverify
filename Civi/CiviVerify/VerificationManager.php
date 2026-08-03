<?php

declare(strict_types=1);

namespace Civi\CiviVerify;

use Civi\CiviVerify\Event\TokenEvent;

final class VerificationManager {

  public function __construct(
    private readonly VerificationRepository $repository,
    private readonly OutboxRepository $outbox,
  ) {}

  public function inspect(?int $id, ?string $uuid): ?array {
    if (($id === NULL) === ($uuid === NULL)) {
      throw new \CRM_Core_Exception('Supply exactly one of id or uuid.');
    }
    return $id !== NULL ? $this->repository->findById($id) : $this->repository->findByUuid((string) $uuid);
  }

  public function revoke(int $id, ?string $reason = NULL): array {
    $record = $this->repository->findById($id);
    if ($record === NULL) {
      throw new \CRM_Core_Exception('Verification record not found.');
    }
    if ($record['status'] === 'used') {
      throw new \CRM_Core_Exception('A used verification cannot be revoked.');
    }
    $metadata = $reason === NULL ? NULL : json_encode(['revoke_reason' => $reason], JSON_THROW_ON_ERROR);
    $now = gmdate('Y-m-d H:i:s');
    $tx = new \CRM_Core_Transaction();
    try {
      if ($this->repository->revoke($id, $now, $metadata)) {
        $record = $this->repository->findById($id) ?? $record;
        $this->outbox->enqueue($id, TokenEvent::REVOKED, $record, $now);
      }
      $tx->commit();
    }
    catch (\Throwable $e) {
      $tx->rollback();
      throw $e;
    }
    return $record;
  }

  public function cleanup(int $batchSize, int $retentionDays): array {
    if ($batchSize < 1 || $batchSize > 1000) {
      throw new \CRM_Core_Exception('Batch size must be between 1 and 1000.');
    }
    if ($retentionDays < 1 || $retentionDays > 3650) {
      throw new \CRM_Core_Exception('Retention must be between 1 and 3650 days.');
    }
    $now = gmdate('Y-m-d H:i:s');
    $expiredIds = $this->repository->expireBatch($batchSize, $now, function (array $record) use ($now): void {
      $this->outbox->enqueue((int) $record['id'], TokenEvent::EXPIRED, $record, $now);
    });
    $cutoff = gmdate('Y-m-d H:i:s', time() - ($retentionDays * 86400));
    return [
      'expired' => count($expiredIds),
      'deleted' => $this->repository->deleteRetainedBatch($batchSize, $cutoff),
      'outbox_deleted' => $this->outbox->deleteTerminalBatch($batchSize, $cutoff),
    ];
  }

}
