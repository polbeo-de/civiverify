<?php

declare(strict_types=1);

use CRM_CiviVerify_ExtensionUtil as E;

/** Administrative delivery log for the verification lifecycle event outbox. */
final class CRM_CiviVerify_Form_Outbox extends CRM_Core_Form {

  public function buildQuickForm(): void {
    CRM_Utils_System::setTitle(E::ts('CiviVerify – Event delivery queue'));
    $rows = \Civi\Api4\CiviVerifyOutbox::get(FALSE)
      ->addSelect('id', 'event_name', 'created_date', 'available_date', 'attempt_count')
      ->addSelect('locked_until', 'delivered_date', 'failed_date', 'last_error')
      ->addOrderBy('id', 'DESC')
      ->setLimit(100)
      ->execute();

    $viewRows = [];
    foreach ($rows as $row) {
      $row['status'] = $this->status($row);
      $viewRows[] = $row;
    }
    $this->assign('outboxRows', $viewRows);
  }

  /** Classify a record without exposing its event payload. */
  private function status(array $row): string {
    if (!empty($row['delivered_date'])) {
      return E::ts('Delivered');
    }
    if (!empty($row['failed_date'])) {
      return E::ts('Failed');
    }
    if (!empty($row['locked_until'])) {
      return E::ts('Processing');
    }
    if ((int) ($row['attempt_count'] ?? 0) > 0) {
      return E::ts('Retry scheduled');
    }
    return E::ts('Queued');
  }

}
