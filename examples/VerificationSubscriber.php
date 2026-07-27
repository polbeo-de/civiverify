<?php

declare(strict_types=1);

namespace Example;

use Civi\CiviVerify\Event\TokenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class VerificationSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    return [TokenEvent::VERIFIED => 'onVerified'];
  }

  public function onVerified(TokenEvent $event): void {
    $verification = $event->getVerification();
    if ($verification['purpose'] !== 'provision_order' || $verification['entity_name'] !== 'Case') {
      return;
    }

    // Project-specific integration belongs here. Re-read and conditionally
    // update the Case so retries remain idempotent. This extension does not do it.
  }

}
