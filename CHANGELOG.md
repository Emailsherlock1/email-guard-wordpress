# Changelog

## 0.1.0

First release.

- Decision engine ported from email-guard-core-php, conformant to
  email-guard-spec 1.3.0 (full vector set in the test suite).
- Local checks: syntax profile, reserved TLDs, bundled disposable snapshot.
- Remote check against `POST /v1/verify/single` with degrade-to-local
  (fail-open by default), relay signal support.
- Adapters: comments, registration, WooCommerce, Contact Form 7, WPForms,
  Gravity Forms, Elementor Pro forms, Fluent Forms, Ninja Forms, Formidable
  Forms; adapter pattern open via the
  `hostsherlock_email_guard_adapters` filter.
- Per-verdict block / flag / allow policy, per-form toggles, custom blocked
  message.
- Domain-only decision log (option-backed ring buffer) and domain-only
  telemetry to `POST /v1/guard/events` with optional `property_key`.
- Settings screen with ajax connection test (nonce + capability checked).
- Uninstall hook removes all options, multisite-aware.
