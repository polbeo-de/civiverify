<?php

declare(strict_types=1);

namespace Civi\CiviVerify;

use Civi\Token\Event\TokenRegisterEvent;
use Civi\Token\Event\TokenValueEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class MailTokenSubscriber implements EventSubscriberInterface {

  private const CONTEXT = [
    'confirmation_url' => 'civiverifyConfirmationUrl',
    'expires_date' => 'civiverifyExpiresDate',
    'purpose' => 'civiverifyPurpose',
    'uuid' => 'civiverifyUuid',
    'entity_name' => 'civiverifyEntityName',
    'entity_id' => 'civiverifyEntityId',
  ];

  public static function getSubscribedEvents(): array {
    return [
      'civi.token.list' => 'registerTokens',
      'civi.token.eval' => 'evaluateTokens',
    ];
  }

  public function registerTokens(TokenRegisterEvent $event): void {
    $schema = $event->getTokenProcessor()->getContextValues('schema')[0] ?? [];
    if (!in_array('civiverifyConfirmationUrl', $schema, TRUE)) {
      return;
    }
    $entity = $event->entity('civiverify');
    $entity->register('confirmation_url', 'CiviVerify: Confirmation URL');
    $entity->register('expires_date', 'CiviVerify: Expiration date');
    $entity->register('purpose', 'CiviVerify: Purpose');
    $entity->register('uuid', 'CiviVerify: UUID');
    $entity->register('entity_name', 'CiviVerify: Bound entity name');
    $entity->register('entity_id', 'CiviVerify: Bound entity ID');
  }

  public function evaluateTokens(TokenValueEvent $event): void {
    if (empty($event->getTokenProcessor()->getMessageTokens()['civiverify'])) {
      return;
    }
    foreach ($event->getRows() as $row) {
      foreach (self::CONTEXT as $token => $contextKey) {
        $value = (string) ($row->context[$contextKey] ?? '');
        $row->format('text/plain')->tokens('civiverify', $token, $value);
        $row->format('text/html')->tokens(
          'civiverify',
          $token,
          htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        );
      }
    }
  }

}
