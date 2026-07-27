<?php

declare(strict_types=1);

namespace Civi\CiviVerify\CiviRules;

use Civi\CiviVerify\Event\TokenEvent;
use PHPUnit\Framework\TestCase;

final class EventSubscriberTest extends TestCase {

  public function testSubscribesToEveryTokenLifecycleEvent(): void {
    self::assertSame([
      TokenEvent::ISSUED => 'onIssued',
      TokenEvent::VERIFIED => 'onVerified',
      TokenEvent::REVOKED => 'onRevoked',
      TokenEvent::EXPIRED => 'onExpired',
    ], EventSubscriber::getSubscribedEvents());
  }

}
