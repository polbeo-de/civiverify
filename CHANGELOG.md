# Changelog

All notable changes to this extension are documented in this file. Versions
follow the extension version in `info.xml`; unreleased changes are recorded
first and moved into a dated release section when deployed.

## Unreleased

## 0.1.0-beta.5 - 2026-08-04

- Correct the release-archive installation examples so their archive name and
  GitHub download URL share one explicit current version variable.

## 0.1.0-beta.4 - 2026-08-04

- Add an API3 bridge for the verification-event dispatcher so the managed
  CiviCRM job delivers committed outbox events through the shared dispatcher.
- Migrate existing managed delivery jobs to the canonical API3 action while
  retaining the APIv4 `CiviVerifyToken.dispatchOutbox` action.

## 0.1.0-beta.3 - 2026-08-03

- Correct the public README status from alpha to beta.

## 0.1.0-beta.2 - 2026-08-03

- Correct the outbox migration for installations that had already reached
  schema revision 1000 before the transactional outbox was introduced.

## 0.1.0-beta.1 - 2026-08-03

- Add an administrative, payload-free view of the verification event delivery queue.

- Document the required minute-level CiviCRM job-runner cadence for timely
  verification-event delivery.

- Add a transactional outbox for all CiviVerify lifecycle events. Token state
  changes and their event records now commit together; a configurable CiviCRM
  job delivers events asynchronously with recoverable leases and retry backoff.
  Existing installations create the required outbox table through an upgrader.

- Load the CiviMix schema upgrader during activation on a fresh CiviCRM
  deployment, including the required Civix bootstrap and dynamic autoloader.

## 0.1.0-alpha - 2026-07-27

- Correct the public CiviCRM extension key to
  `de.polbeo.civicrm.civiverify` and provide a migration path for existing
  installations.
- Keep the legacy HMAC context for issued verification tokens and IP digests,
  so links issued under the former key remain verifiable until they expire.


- Make English the source and fallback language for all CiviVerify interface
  strings, including administrative forms, settings, permissions, public
  confirmation states, validation messages, and scheduled-job labels.
- Add the standard CiviCRM Gettext catalogue structure with a complete German
  `de_DE` translation and compilation guidance for additional locales.
- Ensure Smarty template strings use the extension translation domain.


- Add the CiviCRM administration pages for verification settings,
  confirmation targets, and reusable verify drafts.
- Let verify drafts define their target key, purpose, expiry, message
  template, and safe template parameters; consumers can select a draft rather
  than supplying a redirect target.
- Add administrator-managed confirmation targets without overwriting existing
  configuration.
- Add the nightly managed CiviCRM job that cleans up expired verification
  tokens and emits the corresponding lifecycle events.
- Provide German labels, descriptions, and help texts for the administrative
  interface.
- Make confirmation URL generation resolve a configured target by its stable
  key, while retaining scanner-safe confirmation handling.


- Add `CiviVerifyToken` entity and protected APIv4 access.
- Add secure issue, inspect, verify, revoke, and cleanup actions.
- Add scanner-safe two-step public confirmation route.
- Add generic lifecycle events, retention, settings, and lightweight rate limiting.
- Add optional native CiviRules triggers for issued, verified, revoked, and expired verifications, with purpose and bound-entity filters.
- Add template-driven `issueAndSend`, CiviVerify mail tokens, a managed default workflow, and a no-code CiviRules mail action.
- Add unit tests, architecture decision, security and integration documentation.
