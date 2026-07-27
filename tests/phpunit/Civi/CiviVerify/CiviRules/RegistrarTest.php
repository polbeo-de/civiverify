<?php

declare(strict_types=1);

namespace Civi\CiviVerify\CiviRules;

use PHPUnit\Framework\TestCase;

final class RegistrarTest extends TestCase {

  public function testRecordsDefineEveryLifecycleTriggerExactlyOnce(): void {
    $records = Registrar::records();

    self::assertCount(4, $records);
    self::assertSame([
      'civiverify_token_issued',
      'civiverify_token_verified',
      'civiverify_token_revoked',
      'civiverify_token_expired',
    ], array_column($records, 'name'));
    self::assertSame([
      'CRM_CiviVerify_CiviRules_Trigger_Issued',
      'CRM_CiviVerify_CiviRules_Trigger_Verified',
      'CRM_CiviVerify_CiviRules_Trigger_Revoked',
      'CRM_CiviVerify_CiviRules_Trigger_Expired',
    ], array_column($records, 'class_name'));
    self::assertCount(4, array_unique(array_column($records, 'label')));
  }

  public function testActionRecordsDefineIssueAndSendAction(): void {
    self::assertSame([
      [
        'name' => 'civiverify_issue_and_send',
        'label' => 'CiviVerify: Issue and send verification',
        'class_name' => 'CRM_CiviVerify_CiviRules_Action_IssueAndSend',
      ],
    ], Registrar::actionRecords());
  }

}
