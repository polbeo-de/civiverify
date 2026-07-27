<?php

declare(strict_types=1);

use CRM_CiviVerify_ExtensionUtil as E;

$subject = '{ts}Please confirm your request{/ts} – {domain.name}';
$html = <<<'HTML'
{site.message_header}
<p>{ts}Hello{/ts} {contact.display_name},</p>
<p>{ts}please confirm your request by selecting the following link:{/ts}</p>
<p><a href="{civiverify.confirmation_url}">{ts}Confirm request{/ts}</a></p>
<p>{ts}This link is valid until{/ts} {civiverify.expires_date}.</p>
<p>{ts}If you did not request this confirmation, you can ignore this message.{/ts}</p>
{site.message_footer}
HTML;

$values = [
  'workflow_name' => 'civiverify_confirmation',
  'msg_title' => E::ts('CiviVerify - Verification request'),
  'msg_subject' => $subject,
  'msg_text' => '',
  'msg_html' => $html,
  'is_active' => TRUE,
];

return [
  [
    'name' => 'MessageTemplate_CiviVerifyConfirmationReserved',
    'entity' => 'MessageTemplate',
    'cleanup' => 'unused',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'checkPermissions' => FALSE,
      'match' => ['workflow_name', 'is_reserved'],
      'values' => $values + ['is_default' => FALSE, 'is_reserved' => TRUE],
    ],
  ],
  [
    'name' => 'MessageTemplate_CiviVerifyConfirmationEditable',
    'entity' => 'MessageTemplate',
    'cleanup' => 'unused',
    'update' => 'never',
    'params' => [
      'version' => 4,
      'checkPermissions' => FALSE,
      'match' => ['workflow_name', 'is_reserved'],
      'values' => $values + ['is_default' => TRUE, 'is_reserved' => FALSE],
    ],
  ],
];
