<?php

declare(strict_types=1);

namespace Civi\CiviVerify\Event;

use Symfony\Contracts\EventDispatcher\Event;

final class TokenEvent extends Event {

  public const ISSUED = 'civiverify.token.issued';
  public const VERIFIED = 'civiverify.token.verified';
  public const EXPIRED = 'civiverify.token.expired';
  public const REVOKED = 'civiverify.token.revoked';

  public function __construct(private readonly array $verification) {}

  public function getVerification(): array {
    return $this->verification;
  }

}
