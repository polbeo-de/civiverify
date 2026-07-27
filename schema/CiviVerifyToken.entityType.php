<?php

declare(strict_types=1);

use CRM_CiviVerify_ExtensionUtil as E;

return [
  'name' => 'CiviVerifyToken',
  'table' => 'civicrm_civiverify_token',
  'class' => 'CRM_CiviVerify_DAO_CiviVerifyToken',
  'getInfo' => fn(): array => [
    'title' => E::ts('Verification Token'),
    'title_plural' => E::ts('Verification Tokens'),
    'description' => E::ts('Single-use, time-limited verification transactions.'),
    'add' => '0.1.0',
  ],
  'getIndices' => fn(): array => [
    'UI_uuid' => ['fields' => ['uuid' => TRUE], 'unique' => TRUE, 'add' => '0.1.0'],
    'UI_token_hash' => ['fields' => ['token_hash' => TRUE], 'unique' => TRUE, 'add' => '0.1.0'],
    'index_status_expires' => ['fields' => ['status' => TRUE, 'expires_date' => TRUE], 'add' => '0.1.0'],
    'index_contact' => ['fields' => ['contact_id' => TRUE], 'add' => '0.1.0'],
    'index_entity' => ['fields' => ['entity_name' => TRUE, 'entity_id' => TRUE], 'add' => '0.1.0'],
    'index_purpose' => ['fields' => ['purpose' => TRUE], 'add' => '0.1.0'],
    'index_retention' => [
      'fields' => ['status' => TRUE, 'used_date' => TRUE, 'revoked_date' => TRUE],
      'add' => '0.1.0',
    ],
  ],
  'getFields' => fn(): array => [
    'id' => [
      'title' => E::ts('ID'), 'sql_type' => 'int unsigned', 'input_type' => 'Number',
      'required' => TRUE, 'primary_key' => TRUE, 'auto_increment' => TRUE, 'add' => '0.1.0',
    ],
    'uuid' => [
      'title' => E::ts('Public UUID'), 'sql_type' => 'char(36)', 'input_type' => 'Text',
      'required' => TRUE, 'description' => E::ts('Non-secret public identifier.'), 'add' => '0.1.0',
    ],
    'contact_id' => [
      'title' => E::ts('Contact'), 'sql_type' => 'int unsigned', 'input_type' => 'EntityRef',
      'entity_reference' => ['entity' => 'Contact', 'key' => 'id', 'on_delete' => 'SET NULL'], 'add' => '0.1.0',
    ],
    'entity_name' => [
      'title' => E::ts('Entity Name'), 'sql_type' => 'varchar(64)', 'input_type' => 'Text', 'add' => '0.1.0',
    ],
    'entity_id' => [
      'title' => E::ts('Entity ID'), 'sql_type' => 'int unsigned', 'input_type' => 'Number', 'add' => '0.1.0',
    ],
    'purpose' => [
      'title' => E::ts('Purpose'), 'sql_type' => 'varchar(128)', 'input_type' => 'Text',
      'required' => TRUE, 'add' => '0.1.0',
    ],
    'token_hash' => [
      'title' => E::ts('Token Hash'), 'sql_type' => 'char(64)', 'input_type' => NULL,
      'required' => TRUE, 'readonly' => TRUE,
      'description' => E::ts('Keyed SHA-256 digest. Never expose through public APIs.'),
      'permission' => [['administer verification tokens']], 'add' => '0.1.0',
    ],
    'status' => [
      'title' => E::ts('Status'), 'sql_type' => 'varchar(16)', 'input_type' => 'Select',
      'required' => TRUE, 'default' => 'pending', 'add' => '0.1.0',
    ],
    'created_date' => [
      'title' => E::ts('Created Date'), 'sql_type' => 'timestamp', 'input_type' => NULL,
      'required' => TRUE, 'default' => 'CURRENT_TIMESTAMP', 'add' => '0.1.0',
    ],
    'expires_date' => [
      'title' => E::ts('Expires Date'), 'sql_type' => 'datetime', 'input_type' => 'Select Date',
      'required' => TRUE, 'add' => '0.1.0',
    ],
    'expired_date' => [
      'title' => E::ts('Expired Date'), 'sql_type' => 'datetime', 'input_type' => 'Select Date', 'add' => '0.1.0',
    ],
    'used_date' => [
      'title' => E::ts('Used Date'), 'sql_type' => 'datetime', 'input_type' => 'Select Date', 'add' => '0.1.0',
    ],
    'revoked_date' => [
      'title' => E::ts('Revoked Date'), 'sql_type' => 'datetime', 'input_type' => 'Select Date', 'add' => '0.1.0',
    ],
    'created_by_contact_id' => [
      'title' => E::ts('Created By'), 'sql_type' => 'int unsigned', 'input_type' => 'EntityRef',
      'entity_reference' => ['entity' => 'Contact', 'key' => 'id', 'on_delete' => 'SET NULL'], 'add' => '0.1.0',
    ],
    'created_ip_hash' => [
      'title' => E::ts('Created IP Hash'), 'sql_type' => 'char(64)', 'input_type' => NULL,
      'readonly' => TRUE, 'permission' => [['administer verification tokens']], 'add' => '0.1.0',
    ],
    'used_ip_hash' => [
      'title' => E::ts('Used IP Hash'), 'sql_type' => 'char(64)', 'input_type' => NULL,
      'readonly' => TRUE, 'permission' => [['administer verification tokens']], 'add' => '0.1.0',
    ],
    'metadata' => [
      'title' => E::ts('Metadata'), 'sql_type' => 'text', 'input_type' => 'TextArea',
      'serialize' => constant('CRM_Core_DAO::SERIALIZE_JSON'),
      'permission' => [['administer verification tokens']], 'add' => '0.1.0',
    ],
    'result_metadata' => [
      'title' => E::ts('Result Metadata'), 'sql_type' => 'text', 'input_type' => 'TextArea',
      'serialize' => constant('CRM_Core_DAO::SERIALIZE_JSON'),
      'permission' => [['administer verification tokens']], 'add' => '0.1.0',
    ],
    'use_count' => [
      'title' => E::ts('Use Count'), 'sql_type' => 'int unsigned', 'input_type' => 'Number',
      'required' => TRUE, 'default' => 0, 'add' => '0.1.0',
    ],
  ],
];
