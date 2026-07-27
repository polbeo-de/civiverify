<?php

declare(strict_types=1);

namespace Civi\CiviVerify;

use Civi\CiviVerify\Event\TokenEvent;

final class VerificationIssuer {

  public function __construct(
    private readonly VerificationRepository $repository,
    private readonly TokenHasher $hasher,
    private readonly IpHasher $ipHasher,
  ) {}

  public function issue(array $input): array {
    $this->validate($input);
    $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    $ttl = (int) ($input['ttl'] ?? \Civi::settings()->get('civiverify_default_ttl'));
    $rawToken = $this->hasher->generate();
    $values = [
      'uuid' => Uuid::v4(),
      'contact_id' => $input['contact_id'] ?? NULL,
      'entity_name' => $input['entity_name'] ?? NULL,
      'entity_id' => $input['entity_id'] ?? NULL,
      'purpose' => $input['purpose'],
      'token_hash' => $this->hasher->hash($rawToken),
      'created_date' => $now->format('Y-m-d H:i:s'),
      'expires_date' => $now->modify('+' . $ttl . ' seconds')->format('Y-m-d H:i:s'),
      'created_by_contact_id' => \CRM_Core_Session::getLoggedInContactID() ?: NULL,
      'created_ip_hash' => (bool) \Civi::settings()->get('civiverify_ip_hashing_enabled')
        ? $this->ipHasher->persistentRequestHash()
        : NULL,
      'metadata' => empty($input['metadata'])
        ? NULL
        : json_encode($input['metadata'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
    ];
    $tx = new \CRM_Core_Transaction();
    try {
      $id = $this->repository->insert($values);
      $tx->commit();
    }
    catch (\Throwable $e) {
      $tx->rollback();
      throw $e;
    }
    $public = $values;
    unset($public['token_hash']);
    $public['id'] = $id;
    $public['status'] = 'pending';
    $public['metadata'] = $input['metadata'] ?? NULL;
    \Civi::dispatcher()->dispatch(TokenEvent::ISSUED, new TokenEvent($public));
    return $public + ['token' => $rawToken];
  }

  private function validate(array $input): void {
    $purpose = (string) ($input['purpose'] ?? '');
    if (!preg_match('/^[a-z0-9][a-z0-9._:-]{0,127}$/', $purpose)) {
      throw new \CRM_Core_Exception('Purpose must be a valid machine-readable key.');
    }
    $ttl = (int) ($input['ttl'] ?? \Civi::settings()->get('civiverify_default_ttl'));
    $minimumTtl = (int) \Civi::settings()->get('civiverify_minimum_ttl');
    $maximumTtl = (int) \Civi::settings()->get('civiverify_maximum_ttl');
    if ($ttl < $minimumTtl || $ttl > $maximumTtl) {
      throw new \CRM_Core_Exception(sprintf('TTL must be between %d and %d seconds.', $minimumTtl, $maximumTtl));
    }
    if (isset($input['entity_name']) xor isset($input['entity_id'])) {
      throw new \CRM_Core_Exception('Entity name and entity ID must be supplied together.');
    }
    if (empty($input['contact_id']) && empty($input['entity_name']) && empty($input['allow_unbound'])) {
      throw new \CRM_Core_Exception('A contact or entity binding is required unless allow_unbound is true.');
    }
    $metadata = empty($input['metadata']) ? '' : json_encode($input['metadata'], JSON_THROW_ON_ERROR);
    if (strlen($metadata) > 16384) {
      throw new \CRM_Core_Exception('Metadata exceeds the 16 KiB limit.');
    }
    if (!empty($input['contact_id'])) {
      $contacts = \Civi\Api4\Contact::get(FALSE)
        ->addWhere('id', '=', (int) $input['contact_id'])
        ->setLimit(1)
        ->execute();
      if (count($contacts) !== 1) {
        throw new \CRM_Core_Exception('The referenced contact does not exist.');
      }
    }
    if (!empty($input['entity_name'])) {
      $entityName = (string) $input['entity_name'];
      if (!preg_match('/^[A-Z][A-Za-z0-9_]{0,63}$/', $entityName)) {
        throw new \CRM_Core_Exception('Entity name is invalid.');
      }
      try {
        $entities = civicrm_api4($entityName, 'get', [
          'checkPermissions' => FALSE,
          'select' => ['id'],
          'where' => [['id', '=', (int) $input['entity_id']]],
          'limit' => 1,
        ]);
      }
      catch (\Throwable $e) {
        throw new \CRM_Core_Exception('The referenced entity is unavailable.', 0, [], $e);
      }
      if (count($entities) !== 1) {
        throw new \CRM_Core_Exception('The referenced entity does not exist.');
      }
    }
  }

}
