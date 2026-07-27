<?php

declare(strict_types=1);

namespace Civi\Api4\Action\CiviVerifyToken;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;

/**
 * @method int|null getId()
 * @method $this setId(?int $id)
 * @method string|null getUuid()
 * @method $this setUuid(?string $uuid)
 */
final class Inspect extends AbstractAction {

  protected ?int $id = NULL;
  protected ?string $uuid = NULL;

  public function _run(Result $result): void {
    $record = \Civi::service('civiverify.manager')->inspect($this->id, $this->uuid);
    if ($record !== NULL) {
      if (!\CRM_Core_Permission::check('administer verification tokens')) {
        unset($record['metadata'], $record['result_metadata']);
      }
      $result[] = $record;
    }
  }

}
