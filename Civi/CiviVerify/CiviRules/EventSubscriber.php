<?php

declare(strict_types=1);

namespace Civi\CiviVerify\CiviRules;

use Civi\CiviVerify\Event\TokenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class EventSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    return [
      TokenEvent::ISSUED => 'onIssued',
      TokenEvent::VERIFIED => 'onVerified',
      TokenEvent::REVOKED => 'onRevoked',
      TokenEvent::EXPIRED => 'onExpired',
    ];
  }

  public function onIssued(TokenEvent $event): void {
    $this->dispatch('CRM_CiviVerify_CiviRules_Trigger_Issued', $event);
  }

  public function onVerified(TokenEvent $event): void {
    $this->dispatch('CRM_CiviVerify_CiviRules_Trigger_Verified', $event);
  }

  public function onRevoked(TokenEvent $event): void {
    $this->dispatch('CRM_CiviVerify_CiviRules_Trigger_Revoked', $event);
  }

  public function onExpired(TokenEvent $event): void {
    $this->dispatch('CRM_CiviVerify_CiviRules_Trigger_Expired', $event);
  }

  private function dispatch(string $triggerClass, TokenEvent $event): void {
    if (!class_exists('CRM_Civirules_BAO_Rule') || !class_exists($triggerClass)) {
      return;
    }
    try {
      $triggers = \CRM_Civirules_BAO_Rule::findRulesByClassname($triggerClass);
    }
    catch (\Throwable $e) {
      \Civi::log('civiverify')->error('CiviRules trigger lookup failed: ' . $e->getMessage());
      return;
    }
    foreach ($triggers as $trigger) {
      try {
        if (!$trigger instanceof \CRM_CiviVerify_CiviRules_Trigger_Base) {
          continue;
        }
        $verification = $event->getVerification();
        if (!$trigger->matches($verification)) {
          continue;
        }
        $triggerData = $trigger->createTriggerData($verification);
        \CRM_Civirules_Engine::triggerRule($trigger, $triggerData);
      }
      catch (\Throwable $e) {
        \Civi::log('civiverify')->error('CiviRules rule failed: ' . $e->getMessage());
      }
    }
  }

}
