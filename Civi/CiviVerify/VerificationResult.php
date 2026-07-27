<?php

declare(strict_types=1);

namespace Civi\CiviVerify;

final class VerificationResult {

  public function __construct(
    public readonly string $result,
    public readonly ?array $record = NULL,
  ) {}

  public function toArray(): array {
    return ['result' => $this->result] + ($this->record ?? []);
  }

}
