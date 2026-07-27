<?php

declare(strict_types=1);

namespace Civi\Api4\Action\CiviVerifyToken;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;

/**
 * @method string getToken()
 * @method $this setToken(string $token)
 */
final class Verify extends AbstractAction {

  /** @required */
  protected string $token = '';

  public function _run(Result $result): void {
    $result[] = \Civi::service('civiverify.verifier')->verify($this->token)->toArray();
  }

}
