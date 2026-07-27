<?php

declare(strict_types=1);

namespace Civi\Api4\Action\CiviVerifyToken;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;

/**
 * @method string getPurpose()
 * @method $this setPurpose(string $purpose)
 * @method int getContactId()
 * @method $this setContactId(int $contactId)
 * @method string|null getEntityName()
 * @method $this setEntityName(?string $entityName)
 * @method int|null getEntityId()
 * @method $this setEntityId(?int $entityId)
 * @method int|null getTtl()
 * @method $this setTtl(?int $ttl)
 * @method array|null getMetadata()
 * @method $this setMetadata(?array $metadata)
 * @method string|null getWorkflowName()
 * @method $this setWorkflowName(?string $workflowName)
 * @method int|null getMessageTemplateId()
 * @method $this setMessageTemplateId(?int $messageTemplateId)
 * @method int|null getEmailId()
 * @method $this setEmailId(?int $emailId)
 * @method array|null getTemplateParams()
 * @method $this setTemplateParams(?array $templateParams)
 */
final class IssueAndSend extends AbstractAction {

  /** @required */
  protected string $purpose = '';

  /** @required */
  protected int $contactId;

  protected ?string $entityName = NULL;
  protected ?int $entityId = NULL;
  protected ?int $ttl = NULL;
  protected ?array $metadata = NULL;
  protected ?string $workflowName = NULL;
  protected ?int $messageTemplateId = NULL;
  protected ?int $emailId = NULL;
  protected ?array $templateParams = NULL;

  public function _run(Result $result): void {
    $result[] = \Civi::service('civiverify.mailer')->issueAndSend([
      'purpose' => $this->purpose,
      'contact_id' => $this->contactId,
      'entity_name' => $this->entityName,
      'entity_id' => $this->entityId,
      'ttl' => $this->ttl,
      'metadata' => $this->metadata,
      'workflow_name' => $this->workflowName,
      'message_template_id' => $this->messageTemplateId,
      'email_id' => $this->emailId,
      'template_params' => $this->templateParams,
    ]);
  }

}
