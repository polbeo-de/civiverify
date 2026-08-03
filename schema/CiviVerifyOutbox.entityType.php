<?php

declare(strict_types=1);

use CRM_CiviVerify_ExtensionUtil as E;

return [
  'name' => 'CiviVerifyOutbox',
  'table' => 'civicrm_civiverify_outbox',
  'class' => 'CRM_CiviVerify_DAO_CiviVerifyOutbox',
  'getInfo' => fn(): array => [
    'title' => E::ts('Verification event outbox'),
    'description' => E::ts('Transactional delivery queue for verification lifecycle events.'),
    'add' => '0.1.2',
  ],
  'getIndices' => fn(): array => [
    'index_pending' => [
      'fields' => ['delivered_date' => TRUE, 'failed_date' => TRUE, 'available_date' => TRUE, 'locked_until' => TRUE],
      'add' => '0.1.2',
    ],
    'index_token' => ['fields' => ['token_id' => TRUE], 'add' => '0.1.2'],
  ],
  'getFields' => fn(): array => [
    'id' => [
      'title' => E::ts('ID'), 'sql_type' => 'int unsigned', 'input_type' => 'Number',
      'required' => TRUE, 'primary_key' => TRUE, 'auto_increment' => TRUE, 'add' => '0.1.2',
    ],
    'token_id' => [
      'title' => E::ts('Verification token ID'), 'sql_type' => 'int unsigned', 'input_type' => 'Number',
      'required' => TRUE, 'add' => '0.1.2',
    ],
    'event_name' => [
      'title' => E::ts('Event name'), 'sql_type' => 'varchar(64)', 'input_type' => 'Text',
      'required' => TRUE, 'add' => '0.1.2',
    ],
    'payload' => [
      'title' => E::ts('Event payload'), 'sql_type' => 'text', 'input_type' => 'TextArea',
      'required' => TRUE, 'serialize' => constant('CRM_Core_DAO::SERIALIZE_JSON'),
      'permission' => [['administer verification tokens']], 'add' => '0.1.2',
    ],
    'created_date' => [
      'title' => E::ts('Created date'), 'sql_type' => 'datetime', 'input_type' => 'Select Date',
      'required' => TRUE, 'add' => '0.1.2',
    ],
    'available_date' => [
      'title' => E::ts('Available date'), 'sql_type' => 'datetime', 'input_type' => 'Select Date',
      'required' => TRUE, 'add' => '0.1.2',
    ],
    'attempt_count' => [
      'title' => E::ts('Delivery attempts'), 'sql_type' => 'int unsigned', 'input_type' => 'Number',
      'required' => TRUE, 'default' => 0, 'add' => '0.1.2',
    ],
    'locked_until' => [
      'title' => E::ts('Locked until'), 'sql_type' => 'datetime', 'input_type' => 'Select Date', 'add' => '0.1.2',
    ],
    'lock_token' => [
      'title' => E::ts('Lock token'), 'sql_type' => 'char(36)', 'input_type' => 'Text', 'add' => '0.1.2',
    ],
    'delivered_date' => [
      'title' => E::ts('Delivered date'), 'sql_type' => 'datetime', 'input_type' => 'Select Date', 'add' => '0.1.2',
    ],
    'failed_date' => [
      'title' => E::ts('Failed date'), 'sql_type' => 'datetime', 'input_type' => 'Select Date', 'add' => '0.1.2',
    ],
    'last_error' => [
      'title' => E::ts('Last delivery error'), 'sql_type' => 'text', 'input_type' => 'TextArea',
      'permission' => [['administer verification tokens']], 'add' => '0.1.2',
    ],
  ],
];
