# ADR 0002: Template-driven verification mail

Status: Accepted, 2026-07-16

## Context

Administrators need to issue and mail a verification from CiviRules without writing another extension. The message must remain editable, support normal CiviCRM tokens, and not turn the API into an open mail relay. A raw bearer token must not escape into CiviRules logs or later API processing.

## Decision

- Add `CiviVerifyToken.issueAndSend` while retaining `issue` for external delivery systems.
- Address only a valid email belonging to the required Contact, defaulting to its primary email. Do not accept an arbitrary recipient address.
- Install the stable workflow name `civiverify_confirmation` with an editable default template and a reserved reference copy. Also allow an explicit active default template ID; reject simultaneous workflow and ID selection.
- Require a CiviVerify confirmation URL token in the template before issuing the verification.
- Expose confirmation URL, expiry, purpose, UUID, and binding as scoped CiviCRM tokens and reserved Smarty parameters. Accept additional size-limited, JSON-compatible Smarty parameters.
- Send through CiviCRM's workflow-message API. Return delivery metadata but remove the raw token and URL from the action result.
- Revoke the newly issued verification when rendering or mail delivery fails.
- Register a CiviRules action for triggers that provide a Contact. Exclude CiviVerify lifecycle triggers to prevent recursive issue-and-send loops.

## Consequences

Template IDs remain installation-specific while the workflow name is portable configuration. Editors retain control over content and standard CiviCRM tokens. The recipient restriction prevents caller-controlled relay. Issuing and sending are not a distributed transaction: CiviCRM accepting a message is treated as success, while an immediate synchronous failure revokes the verification.
