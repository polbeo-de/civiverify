<?php

declare(strict_types=1);

namespace Civi\Api4\Action\CiviVerifyToken;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;

/**
 * @method int getId()
 * @method $this setId(int $id)
 * @method string|null getReason()
 * @method $this setReason(?string $reason)
 */
final class Revoke extends AbstractAction {

  /** @required */
  protected int $id;
  protected ?string $reason = NULL;

  public function _run(Result $result): void {
    if ($this->reason !== NULL && strlen($this->reason) > 255) {
      throw new \CRM_Core_Exception('Revoke reason must not exceed 255 characters.');
    }
    $result[] = \Civi::service('civiverify.manager')->revoke($this->id, $this->reason);
  }

}
