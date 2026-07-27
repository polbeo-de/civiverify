<?php

declare(strict_types=1);

use Civi\WorkflowMessage\GenericWorkflowMessage;

final class CRM_CiviVerify_WorkflowMessage_Confirmation extends GenericWorkflowMessage {

  public const WORKFLOW = 'civiverify_confirmation';

  /** @var string @scope tokenContext as civiverifyConfirmationUrl, tplParams as civiverifyConfirmationUrl */
  public string $confirmationUrl = '';

  /** @var string @scope tokenContext as civiverifyExpiresDate, tplParams as civiverifyExpiresDate */
  public string $expiresDate = '';

  /** @var string @scope tokenContext as civiverifyPurpose, tplParams as civiverifyPurpose */
  public string $purpose = '';

  /** @var string @scope tokenContext as civiverifyUuid, tplParams as civiverifyUuid */
  public string $verificationUuid = '';

  /** @var string @scope tokenContext as civiverifyEntityName, tplParams as civiverifyEntityName */
  public string $entityName = '';

  /** @var string @scope tokenContext as civiverifyEntityId, tplParams as civiverifyEntityId */
  public string $entityId = '';

}
