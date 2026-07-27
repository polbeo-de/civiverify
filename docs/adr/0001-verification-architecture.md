# ADR 0001: Verification architecture

Status: Accepted, 2026-07-16

## Context

CiviVerify needs searchable bearer-token digests, single-success concurrency semantics, CMS-independent public confirmation, generic follow-up integration, and minimal personal-data collection on CiviCRM 6.15.

## Decision

- Keep the polbeo-owned key `de.polbeo.civicrm.civiverify`; use generic names and behavior elsewhere.
- Model `CiviVerifyToken` with `.entityType.php` and database uniqueness for UUID and token digest.
- Generate 32 random bytes and Base64url-encode them. Store HMAC-SHA-256 using an HKDF-derived `CIVICRM_SITE_KEY` subkey. Password hashes are inappropriate because exact indexed lookup is required and tokens already have 256 bits of entropy.
- Persist `expired` and lifecycle timestamps. Use a conditional SQL update for the only successful `pending → used` transition.
- Dispatch Symfony events after commit. Consumers must be idempotent. Defer an outbox until reliability requirements justify its operational cost.
- Use public CiviCRM menu routing. GET previews and moves the raw token into a short-lived server cache; POST with an unguessable nonce consumes it.
- Keep CiviRules and mail delivery optional. Provide APIv4 and Symfony events as integration boundaries, plus native CiviRules lifecycle triggers when CiviRules is available. Filter rules by exact purpose and bound entity, and expose only sanitized verification data.

## Consequences

Concurrent verification yields one success. A site-key rotation invalidates pending links. Link-scanner consumption is avoided, but the original query string must be redacted by infrastructure logs. Post-commit process failure may lose an event; it cannot duplicate successful verification.
