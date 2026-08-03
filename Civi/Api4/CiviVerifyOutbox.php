<?php

declare(strict_types=1);

namespace Civi\Api4;

/** Internal transactional delivery records for CiviVerify lifecycle events. */
final class CiviVerifyOutbox extends Generic\DAOEntity {

  public static function permissions(): array {
    return [
      'meta' => ['administer verification tokens'],
      'default' => ['administer verification tokens'],
    ];
  }

}
