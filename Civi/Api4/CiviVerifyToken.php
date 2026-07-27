<?php

declare(strict_types=1);

namespace Civi\Api4;

/**
 * Persistent verification transaction.
 */
final class CiviVerifyToken extends Generic\DAOEntity {

  public static function issue(bool $checkPermissions = TRUE): Action\CiviVerifyToken\Issue {
    return (new Action\CiviVerifyToken\Issue(__CLASS__, __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function issueAndSend(bool $checkPermissions = TRUE): Action\CiviVerifyToken\IssueAndSend {
    return (new Action\CiviVerifyToken\IssueAndSend(__CLASS__, __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function verify(bool $checkPermissions = TRUE): Action\CiviVerifyToken\Verify {
    return (new Action\CiviVerifyToken\Verify(__CLASS__, __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function inspect(bool $checkPermissions = TRUE): Action\CiviVerifyToken\Inspect {
    return (new Action\CiviVerifyToken\Inspect(__CLASS__, __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function revoke(bool $checkPermissions = TRUE): Action\CiviVerifyToken\Revoke {
    return (new Action\CiviVerifyToken\Revoke(__CLASS__, __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function cleanup(bool $checkPermissions = TRUE): Action\CiviVerifyToken\Cleanup {
    return (new Action\CiviVerifyToken\Cleanup(__CLASS__, __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  /**
   * APIv4 permissions for standard entity operations.
   */
  public static function permissions(): array {
    return [
      'meta' => ['access CiviCRM'],
      'default' => ['administer verification tokens'],
      'get' => ['view verification tokens'],
      'getFields' => ['view verification tokens'],
      'issue' => ['issue verification tokens'],
      'issueAndSend' => ['issue verification tokens'],
      'verify' => ['administer verification tokens'],
      'inspect' => ['view verification tokens'],
      'revoke' => ['revoke verification tokens'],
      'cleanup' => ['administer verification tokens'],
    ];
  }

}
