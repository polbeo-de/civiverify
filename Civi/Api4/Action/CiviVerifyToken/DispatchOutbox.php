<?php

declare(strict_types=1);

namespace Civi\Api4\Action\CiviVerifyToken;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;

/**
 * @method int getBatchSize()
 * @method $this setBatchSize(int $batchSize)
 */
final class DispatchOutbox extends AbstractAction {

  protected int $batchSize = 50;

  public function _run(Result $result): void {
    $result[] = \Civi::service('civiverify.outbox_dispatcher')->dispatch($this->batchSize);
  }

}
