<?php

declare(strict_types=1);

namespace Civi\Api4\Action\CiviVerifyToken;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;

/** Verify a six-digit code issued for a public verification UUID. */
final class VerifyCode extends AbstractAction {

  /** @required */
  protected string $uuid = '';

  /** @required */
  protected string $code = '';

  public function _run(Result $result): void {
    $result[] = \Civi::service('civiverify.verifier')->verifyCode($this->uuid, $this->code)->toArray();
  }

}
