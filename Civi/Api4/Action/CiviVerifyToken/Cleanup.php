<?php

declare(strict_types=1);

namespace Civi\Api4\Action\CiviVerifyToken;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;

/**
 * @method int getBatchSize()
 * @method $this setBatchSize(int $batchSize)
 * @method int getRetentionDays()
 * @method $this setRetentionDays(int $retentionDays)
 */
final class Cleanup extends AbstractAction {

  protected int $batchSize = 200;
  protected int $retentionDays = 0;

  public function _run(Result $result): void {
    $retentionDays = $this->retentionDays ?: (int) \Civi::settings()->get('civiverify_retention_days');
    $result[] = \Civi::service('civiverify.manager')->cleanup($this->batchSize, $retentionDays);
  }

}
