# email-guard-wordpress

WordPress integration for [Email-Guard](https://github.com/Emailsherlock1/email-guard-spec):
blocks junk email addresses at form submit, before they reach your database.

`deleted+user274@deleted.invalid` passes the browser's email check. So does
every disposable address. This plugin ports
[email-guard-core-php](https://github.com/Emailsherlock1/email-guard-core-php)
to the WordPress runtime (PHP 7.4+, WP HTTP API, no composer at runtime):
syntax profile, reserved TLDs, 70k+ disposable domains, all checked locally
with zero latency. A [HostSherlock API key](https://www.hostsherlock.com/api/docs)
adds live MX, SMTP probe, a fresh disposable list, relay detection, and the
guard dashboard.

Implements email-guard-spec **1.3.0** and passes the full conformance vector
set (vendored under `tests/vectors/`).

## Protected forms

| Surface | Hook | Active when |
|---|---|---|
| Comments | `pre_comment_approved` | always |
| Registration | `registration_errors` | always |
| WooCommerce checkout + registration | `woocommerce_after_checkout_validation`, `woocommerce_registration_errors` | WooCommerce installed |
| Contact Form 7 | `wpcf7_validate_email(*)` | CF7 installed |
| WPForms | `wpforms_process` | WPForms installed |
| Gravity Forms | `gform_field_validation` | Gravity Forms installed |

Each surface is one class implementing `AdapterInterface`
(`includes/Adapters/`). Another form plugin means one more adapter, wired in
via the `hostsherlock_email_guard_adapters` filter.

## Policy is yours

Per verdict class (`invalid`, `disposable`, `role`, `catch_all`, `unknown`)
and per signal (`relay`): **block** (reject with a message at the field),
**flag** (pass, but hold comments for moderation and record the decision),
or **allow**. Configured under Settings > Email Guard, mapped onto the
spec's `block_on` / `review_on`. Defaults follow the spec (block invalid +
disposable) plus relay flagged.

**Fail-open is the default on purpose.** If the API is unreachable, local
checks keep working and the rest passes. `fail_open` is a checkbox in the
policy section.

## Telemetry

With an API key, each decision is reported (batched, on `shutdown`,
non-blocking, fail-silent) to `POST /v1/guard/events`: domain, verdict,
action, reasons. Never the address. The optional site key from your
HostSherlock Guard property rides along as `property_key` so the dashboard
attributes decisions to this site. No key, no telemetry.

## Repo layout

```
hostsherlock-email-guard.php          plugin header + autoloader + boot
includes/Guard/          the ported decision engine (spec 1.3.0)
includes/Adapters/       one class per form surface
includes/Admin/          settings screen + ajax connection test
data/                    bundled disposable snapshot (json.gz)
tests/                   PHPUnit suite incl. vendored conformance vectors
```

## Development

```bash
composer install
composer test
```

The suite runs without a WordPress checkout: the engine is plain PHP and the
adapters touch only the narrow WP surface stubbed in
`tests/Support/wp-stubs.php`. The conformance vectors are a vendored copy
from the spec repo; refresh them when the spec tags a new version.

## License

GPLv2 or later (WordPress plugin). The spec and core library remain MIT in
their own repos.
