<?php

declare(strict_types=1);

namespace Civi\Api4\Action\CiviVerifyToken;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;

/**
 * @method string getPurpose()
 * @method $this setPurpose(string $purpose)
 * @method int|null getContactId()
 * @method $this setContactId(?int $contactId)
 * @method string|null getEntityName()
 * @method $this setEntityName(?string $entityName)
 * @method int|null getEntityId()
 * @method $this setEntityId(?int $entityId)
 * @method int|null getTtl()
 * @method $this setTtl(?int $ttl)
 * @method array|null getMetadata()
 * @method $this setMetadata(?array $metadata)
 * @method bool getAllowUnbound()
 * @method $this setAllowUnbound(bool $allowUnbound)
 */
final class Issue extends AbstractAction {

  /** @required */
  protected string $purpose = '';
  protected ?int $contactId = NULL;
  protected ?string $entityName = NULL;
  protected ?int $entityId = NULL;
  protected ?int $ttl = NULL;
  protected ?array $metadata = NULL;
  protected bool $allowUnbound = FALSE;

  public function _run(Result $result): void {
    $issued = \Civi::service('civiverify.issuer')->issue([
      'purpose' => $this->purpose,
      'contact_id' => $this->contactId,
      'entity_name' => $this->entityName,
      'entity_id' => $this->entityId,
      'ttl' => $this->ttl,
      'metadata' => $this->metadata,
      'allow_unbound' => $this->allowUnbound,
    ]);
    $issued['confirmation_url'] = \Civi::service('civiverify.confirmation_url_builder')->build($issued['token']);
    $result[] = $issued;
  }

}
