# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-10-07

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

[Unreleased]: https://github.com/wthaiq/wthaiq-php/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/wthaiq/wthaiq-php/releases/tag/v1.0.0
