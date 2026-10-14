# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-10-14

### Added

- `Wthaiq\Client` for API version `2026-07-01` with secret, test and publishable keys, test/live mode helpers,
  configurable base URL, timeouts, retries, API version and application info.
- Services for every public endpoint: account, usage, balance, templates, signature requests (create, bulk,
  send, remind, cancel, audit trail, certificate, download, PDF download with fingerprint check, evidence),
  sessions, signers and embedded signing sessions, documents (multipart upload), identity verifications,
  verification references, webhook endpoints, events (with redelivery) and console flows.
- Immutable typed response objects with property and array access, `toArray()`, `toJson()` and the HTTP
  response metadata of each call.
- One-page `list()` collections and lazy auto-paginating `all()` iterators.
- Typed exception hierarchy covering every documented API error code.
- Automatic retries with exponential backoff and jitter, `Retry-After` support and automatic idempotency keys.
- `Wthaiq\Webhook` helper to verify `Wthaiq-Signature` headers and decode typed events, plus `EventType` constants.
- Built-in cURL transport and a PSR-18 adapter.
- PHPStan extension so that undocumented and nested response fields can be read without analyser errors.
- `EventType::SIGNER_IDENTITY_MATCH_HOLD` (`signer.identity_match_hold`) and
  `EventType::SIGNER_IDENTITY_MATCH_DECIDED` (`signer.identity_match_decided`): the name on the signer's identity
  document differs from the name in the request, and the decision on that difference. `EventType::all()` now lists 21 types.
- `$client->signers->decideIdentityMatch($id, ['decision' => 'approve'|'reject', 'note' => ?])` for
  `POST /signers/{id}/identity_match`: decides a name difference held for review and returns the signer.
- `Wthaiq\Resource\NameMatch` with `STATE_*` / `VERDICT_*` constants and `isAwaitingDecision()`, returned by
  `Signer::getNameMatch()` (`identity.name_match`), `Signer::getIdentityMatch()` (`identity_match` in the two
  name-match events) and `IdentityVerification::getNameMatch()` (`name_match`).
- `TooManySignersException` (a `ValidationException`) for `400 too_many_signers`, with `getMaxSigners()` read from
  the error's `max_signers` (falls back to `TooManySignersException::MAX_SIGNERS`, 5).
- `PermissionException::getVerifyUrl()` reads `verify_url` from `kyb_required` errors (falls back to
  `PermissionException::VERIFY_URL`). `getUpgradeUrl()` reads the `upgrade_url` the API now sends on
  `plan_required`, `plan_expired` and `plan_feature_unavailable`.
- Client option `language` (`en` or `ar`), sent as `Accept-Language`, for English error messages. The default is
  unchanged: no header, Arabic messages. Error codes do not change with the language.
- `webhookEndpoints->list()` accepts `starting_after`, and `all()` follows `next_cursor` across pages, now that the
  API paginates webhook endpoints like other lists.
- `verifications->retrieve()` also returns `found === false` when the API answers an unknown reference with a
  `404` error whose code is `not_found`, so its result does not depend on the server version.
- A per-call header now replaces a client header of the same name whatever its letter case (for example
  `accept-language` overrides the `language` option) instead of being sent twice.

[1.0.0]: https://github.com/wthaiq/wthaiq-php/releases/tag/v1.0.0
