# CiviVerify

CiviVerify is a generic CiviCRM extension for issuing and redeeming single-use, time-limited verification links. A verification may refer to a contact, any APIv4 entity, or—when explicitly allowed—no entity at all. The extension never performs domain-specific follow-up work; consumers use its optional native CiviRules triggers or subscribe to its Symfony events.

Maintained by [polbeo](https://polbeo.de). Extension key: `de.polbeo.civicrm.civiverify`. License: AGPL-3.0-or-later.

Release history is maintained in [CHANGELOG.md](CHANGELOG.md).

## Status and support

CiviVerify is currently an alpha extension. For support, maintenance, or
coordination, contact [mcc+civiverify@polbeo.de](mailto:mcc+civiverify@polbeo.de).
For security-sensitive reports, follow [SECURITY.md](SECURITY.md).

## Localisation

English is the source and fallback language for all CiviVerify interface
strings. The included German catalogue lives in
[`l10n/de_DE/LC_MESSAGES`](l10n/de_DE/LC_MESSAGES); adding another CiviCRM
locale only requires its own Gettext catalogue. See [l10n/README.md](l10n/README.md)
for the refresh and compilation workflow.

## Scope and privacy

Verifying an email address or request is not newsletter consent, does not create mailing-list membership, and must not be interpreted as marketing consent. Tokens contain no email address, contact ID, entity ID, or other personal data. Application metadata is never included in public responses.

The raw token is returned exactly once by `issue`. Only a keyed SHA-256 digest is stored. IP storage is disabled by default; if enabled, only an installation-keyed digest is persisted. Rate limiting uses the same kind of digest transiently, never the clear IP. Deleted contacts set `contact_id` and `created_by_contact_id` to `NULL`; generic entity references are intentionally not foreign keys and may become orphaned.

## Requirements and installation

- CiviCRM 6.15 or later
- PHP 8.1–8.4
- A strong `CIVICRM_SITE_KEY`
- MariaDB/MySQL supported by the installed CiviCRM version

Place this directory in CiviCRM's extension directory, refresh extensions, and enable **CiviVerify**. The `.entityType.php` mixin creates and upgrades `civicrm_civiverify_token`. Do not configure a web-accessible extension directory.

## Data model

The APIv4 entity is `CiviVerifyToken`; the table is `civicrm_civiverify_token`. It stores UUID and binding data, purpose, keyed token digest, lifecycle dates, status, JSON metadata, optional keyed IP digests, and a use counter. Database constraints enforce unique UUIDs and token digests. Status values are `pending`, `used`, `expired`, and `revoked`; expiration is persisted by cleanup for idempotent lifecycle handling.

`token_hash`, IP hashes, `metadata`, and `result_metadata` require `administer verification tokens` even when a caller may otherwise view records.

## Permissions

- `administer verification tokens`
- `issue verification tokens`
- `view verification tokens`
- `revoke verification tokens`

The public route requires no CiviCRM account. Knowledge of a valid raw token plus the explicit POST confirmation is its narrowly scoped authorization.

## APIv4

Issue a contact/entity-bound token:

```bash
cv api4 CiviVerifyToken.issue \
  purpose=provision_order \
  contact_id=123 \
  entity_name=Case \
  entity_id=4711 \
  ttl=86400
```

```php
$issued = \Civi\Api4\CiviVerifyToken::issue(FALSE)
  ->setPurpose('provision_order')
  ->setContactId(123)
  ->setEntityName('Case')
  ->setEntityId(4711)
  ->setTtl(86400)
  ->setMetadata(['product' => 'civicrm_instance'])
  ->execute()
  ->single();

// $issued['token'] and $issued['confirmation_url'] exist only in this response.
```

Issue a token and send it through CiviCRM's transactional message system:

```php
$sent = \Civi\Api4\CiviVerifyToken::issueAndSend(FALSE)
  ->setPurpose('provision_order')
  ->setContactId(123)
  ->setEntityName('Case')
  ->setEntityId(4711)
  ->setTtl(86400)
  ->setWorkflowName('civiverify_confirmation')
  ->setTemplateParams(['productLabel' => 'Managed CiviCRM'])
  ->execute()
  ->single();

// $sent contains delivery and verification metadata, but no raw token or URL.
```

`issueAndSend` uses the contact's primary email address unless `emailId` selects another address belonging to the same contact. It deliberately has no arbitrary recipient parameter. Select either a stable `workflowName` or a concrete `messageTemplateId`, never both. If mail delivery fails, the newly issued verification is immediately revoked. Use `issue` instead when an external system owns delivery.

Administrative actions:

```bash
cv api4 CiviVerifyToken.inspect id=17
cv api4 CiviVerifyToken.revoke id=17 reason='Request cancelled'
cv api4 CiviVerifyToken.cleanup batch_size=200 retention_days=90
cv api4 CiviVerifyToken.verify token='RAW_TOKEN'
```

Standard create/update/delete require the administrative permission. Token creation should always use `issue`; only it returns a raw token. `inspect` never returns a raw token or digest.

## Public confirmation flow

`confirmation_url` targets `/civicrm/verify?token=…`. A GET only previews the verification and writes the raw token to CiviCRM's server-side cache for at most ten minutes under a random nonce. The HTML contains the nonce, not the token. Only the subsequent explicit POST consumes it. This prevents mail scanners and link prefetchers from confirming requests.

Responses set `Cache-Control: no-store`, `Referrer-Policy: no-referrer`, `X-Content-Type-Options: nosniff`, and `X-Frame-Options: DENY`. Invalid, expired, and revoked links share the same public message. There are no caller-controlled redirects or template paths.

The initial URL necessarily contains the bearer token and may reach access logs. Operators should configure their reverse proxy/web server to redact query strings for `/civicrm/verify`. CiviVerify itself never logs the token.

## Events

- `civiverify.token.issued`
- `civiverify.token.verified`
- `civiverify.token.expired`
- `civiverify.token.revoked`

Each event is a `Civi\CiviVerify\Event\TokenEvent`. `getVerification()` returns lifecycle and binding fields plus metadata, but never the raw token, token digest, secret, or IP digest. See [`examples/VerificationSubscriber.php`](examples/VerificationSubscriber.php).

The successful state change commits before the event is dispatched. Listener failure therefore does not make a used token reusable. A crash between commit and dispatch can lose an event; version 1 intentionally has no transactional outbox. Follow-up handlers should be idempotent and operational monitoring should detect failures.

## Configuration

Domain settings define default/minimum/maximum TTL (24 hours/60 seconds/30 days), retention (90 days), optional messages, optional persistent IP hashing, rate-limit attempts/window (20/15 minutes), and direct GET confirmation (disabled). Under **Administration → CiviVerify**, administrators maintain trusted confirmation targets and verification drafts separately: every draft selects one target and can therefore send distinct workflows to distinct external frontends. A target may be `civicrm/verify` or an administrator-controlled absolute HTTPS URL. A trusted external frontend must keep GET side-effect free and consume the token only after an explicit, CSRF-protected POST. The scanner-safe two-step flow is the default. Direct GET consumption exists only as an explicit compatibility opt-in and should remain disabled for emailed links.

No secret is stored in this repository. Token and IP HMAC keys are independently derived from `CIVICRM_SITE_KEY` using HKDF domain separation. Rotating the site key invalidates all outstanding links and changes IP pseudonyms; schedule rotation accordingly.

## Cleanup and scheduled jobs

CiviVerify installs the active scheduled job **CiviVerify: Verifizierungstoken bereinigen**. Its first run is scheduled for the next 02:42 local server time and it then runs daily through CiviCRM's standard job runner. It calls `CiviVerifyToken.cleanup` with a batch size of 200 and uses the configured retention period. Administrators may change the schedule or deactivate the job under **Administration → System → Scheduled Jobs**.

The cleanup claims expired rows in ordered, locked batches, persists `expired`, emits the expired event once for claimed rows, and deletes terminal rows older than retention. Repeated runs are idempotent. The post-commit event limitation described above also applies to expired/revoked events. Domain extensions should subscribe to the expiry event to perform their own state transitions; CiviVerify never deletes domain entities itself.

## CiviRules

CiviRules is optional and not a dependency. When CiviRules is enabled, CiviVerify automatically provides four native triggers:

- **CiviVerify: Verification is issued**
- **CiviVerify: Verification is confirmed**
- **CiviVerify: Verification is revoked**
- **CiviVerify: Verification expires**

Create a CiviRules rule, select one of these triggers, and optionally restrict it to an exact CiviVerify `purpose` and one bound APIv4 entity. Every rule receives a sanitized `CiviVerifyToken` entity and the related Contact when present. If an entity filter is configured, for example `Case`, that bound entity is loaded and provided to CiviRules actions as well. Raw tokens, token and IP digests, and metadata fields are deliberately not exposed to CiviRules.

CiviVerify also registers the action **CiviVerify: Issue and send verification**. Attach it to any CiviRules trigger which provides a Contact, choose purpose, validity, message template, and an optional bound entity, and add optional template parameters as a JSON object. The action is intentionally unavailable on CiviVerify's own lifecycle triggers to prevent mail loops.

Rules run after the token state change has committed. A failing rule is logged and cannot make a consumed token reusable; rule actions should therefore be idempotent. CiviVerify trigger definitions are automatically registered or reactivated when CiviRules becomes available. They are deactivated, but retained to preserve rule references, when CiviVerify is disabled or uninstalled.

The integration is tested with CiviRules 3.38.0. CiviVerify continues to operate when CiviRules is absent or disabled, including if CiviRules is installed later.

## Message templates and tokens

CiviVerify installs the workflow `civiverify_confirmation` with an editable default template and a reserved reference copy. The workflow name is the stable identifier maintained by this extension; CiviCRM assigns numeric template IDs per installation. Administrators may edit the default template or select any active user template by its ID.

The selected template must contain either `{civiverify.confirmation_url}` or `{$civiverifyConfirmationUrl}`. This is checked before a token is issued. The following CiviVerify tokens are available during this workflow:

- `{civiverify.confirmation_url}`
- `{civiverify.expires_date}`
- `{civiverify.purpose}`
- `{civiverify.uuid}`
- `{civiverify.entity_name}`
- `{civiverify.entity_id}`

Their Smarty equivalents use camel case, for example `{$civiverifyPurpose}`. Normal CiviCRM contact, domain, and site tokens remain available. Callers may provide additional JSON-compatible Smarty parameters through `templateParams`; names beginning with `civiverify` are reserved.

## Example case integration (not built in)

1. FormProcessor creates a Case in “email confirmation pending”.
2. Integration code calls `CiviVerifyToken.issue` with `purpose=provision_order`, Case ID, and contact ID.
3. The transactional message includes `confirmation_url`.
4. The customer opens the link and explicitly confirms.
5. `civiverify.token.verified` fires.
6. A purpose- and entity-filtered CiviRules rule changes the bound Case to “provisioning requested”. A Symfony subscriber remains available when custom code is preferable.
7. The existing queue runner provisions it.

The Case status change and provisioning are deliberately outside this generic extension.

## Development and tests

```bash
composer install
composer test
composer lint
```

On a disposable installation with the extension enabled, run:

```bash
cv php:script "$PWD/tests/integration/CiviVerifyLifecycle.php"
cv php:script "$PWD/tests/integration/IssueAndSend.php"
CV="$(command -v cv)" tests/integration/run-public-endpoint.sh
CV="$(command -v cv)" tests/integration/run-concurrency.sh
# With CiviRules 3.38+ enabled:
cv php:script "$PWD/tests/integration/CiviRules.php"
cv php:script "$PWD/tests/integration/CiviRulesIssueAndSend.php"
```

The scripts create only temporary contacts/tokens and clean them up, including after failed assertions. The HTTPS test temporarily raises its own rate-limit ceiling and restores the previous value. Unit tests run independently. Installation/uninstallation and permission-matrix tests remain environment-level operations; never run destructive installation tests against production. Current architectural decisions are recorded in [`docs/adr/0001-verification-architecture.md`](docs/adr/0001-verification-architecture.md).

## Known limitations

- No transactional outbox; a post-commit crash can lose an event.
- Rate limiting uses CiviCRM's cache and is intentionally lightweight; high-volume deployments may replace it with an atomic shared limiter.
- Generic entity deletion cannot be enforced by a database foreign key.
- The confirmation token can appear in upstream request logs unless those logs redact the route's query string.
