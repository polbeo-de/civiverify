<?php

declare(strict_types=1);

namespace Civi\CiviVerify;

use Civi\CiviVerify\Event\TokenEvent;

final class OutboxDispatcher {

  private const MAX_ATTEMPTS = 10;

  public function __construct(private readonly OutboxRepository $outbox) {}

  public function dispatch(int $batchSize): array {
    if ($batchSize < 1 || $batchSize > 1000) {
      throw new \CRM_Core_Exception('Batch size must be between 1 and 1000.');
    }
    $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    $lockToken = Uuid::v4();
    $records = $this->outbox->claimBatch(
      $batchSize,
      $now->format('Y-m-d H:i:s'),
      $now->modify('+5 minutes')->format('Y-m-d H:i:s'),
      $lockToken
    );
    $delivered = 0;
    $failed = 0;
    $exhausted = 0;
    foreach ($records as $record) {
      try {
        \Civi::dispatcher()->dispatch($record['event_name'], new TokenEvent($record['payload']));
        if ($this->outbox->markDelivered($record['id'], $lockToken, gmdate('Y-m-d H:i:s'))) {
          $delivered++;
        }
      }
      catch (\Throwable $e) {
        $failed++;
        $error = substr($e->getMessage(), 0, 4096);
        if ($record['attempt_count'] >= self::MAX_ATTEMPTS) {
          $exhausted += (int) $this->outbox->markFailed($record['id'], $lockToken, gmdate('Y-m-d H:i:s'), $error);
        }
        else {
          $seconds = min(3600, 60 * (2 ** min(6, $record['attempt_count'] - 1)));
          $this->outbox->reschedule(
            $record['id'],
            $lockToken,
            gmdate('Y-m-d H:i:s', time() + $seconds),
            $error
          );
        }
        \Civi::log()->error('CiviVerify lifecycle event delivery failed.', [
          'outbox_id' => $record['id'],
          'event_name' => $record['event_name'],
          'exception_class' => $e::class,
        ]);
      }
    }
    return ['claimed' => count($records), 'delivered' => $delivered, 'failed' => $failed, 'exhausted' => $exhausted];
  }

}
