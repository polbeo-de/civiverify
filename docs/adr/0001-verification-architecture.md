# ADR 0001: Verification architecture

Status: Accepted, 2026-07-16

## Context

CiviVerify needs searchable bearer-token digests, single-success concurrency semantics, CMS-independent public confirmation, generic follow-up integration, and minimal personal-data collection on CiviCRM 6.15.

## Decision

- Keep the polbeo-owned key `de.polbeo.civicrm.civiverify`; use generic names and behavior elsewhere.
- Model `CiviVerifyToken` with `.entityType.php` and database uniqueness for UUID and token digest.
- Generate 32 random bytes and Base64url-encode them. Store HMAC-SHA-256 using an HKDF-derived `CIVICRM_SITE_KEY` subkey. Password hashes are inappropriate because exact indexed lookup is required and tokens already have 256 bits of entropy.
- Persist `expired` and lifecycle timestamps. Use a conditional SQL update for the only successful `pending → used` transition.
- Persist lifecycle events in a transactional outbox. Delivery is at least once, so consumers must be idempotent.
- Provide a trusted PHP-only atomic redemption service for extensions whose local database mutation must commit with token consumption. It locks the token row, validates explicitly expected generic bindings, invokes a callback with sanitized data, and only then persists `pending → used` and the verified outbox entry. The callback may perform local transactional writes only; external effects belong after commit or in the consumer's outbox.
- Use public CiviCRM menu routing. GET previews and moves the raw token into a short-lived server cache; POST with an unguessable nonce consumes it.
- Keep CiviRules and mail delivery optional. Provide APIv4 and Symfony events as integration boundaries, plus native CiviRules lifecycle triggers when CiviRules is available. Filter rules by exact purpose and bound entity, and expose only sanitized verification data.

## Consequences

Concurrent verification or redemption yields one success. A callback exception rolls back both its local changes and the token lifecycle changes, leaving the token pending. A site-key rotation invalidates pending links. Link-scanner consumption is avoided, but the original query string must be redacted by infrastructure logs. Outbox delivery may be retried and cannot be treated as exactly once.
