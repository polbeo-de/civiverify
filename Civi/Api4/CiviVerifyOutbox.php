<?php

declare(strict_types=1);

namespace Civi\Api4;

/** Internal transactional delivery records for CiviVerify lifecycle events. */
final class CiviVerifyOutbox extends Generic\DAOEntity {

  public static function permissions(): array {
    return [
      // SearchKit loads API action metadata for every entity visible in its
      // administration UI. Metadata must therefore be available to ordinary
      // CiviCRM users, while all actual outbox record operations stay admin-only.
      'meta' => ['access CiviCRM'],
      'default' => ['administer verification tokens'],
    ];
  }

}
