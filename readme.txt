=== HostSherlock Email Guard ===
Contributors: emailsherlock
Tags: email validation, disposable email, spam, comments, registration
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Blocks disposable and undeliverable email addresses at form submit: comments, registration, WooCommerce, Contact Form 7, WPForms, Gravity Forms.

== Description ==

`deleted+user274@deleted.invalid` passes the browser's email check. So does every throwaway Mailinator address. Email Guard adds the missing layer: it checks the address the visitor typed before the submission goes through, and rejects or flags the junk at the field where it was typed.

**What runs on your server, with no account and no API key:**

* a strict syntax profile made for form input (quoted local parts and address literals are legal in mail headers, but nobody types them into your signup form)
* reserved domains that can never receive mail (`.test`, `.invalid`, `example.com`, and friends, per RFC 2606 and RFC 6761)
* a bundled snapshot of 70,000+ disposable-mail domains

**What a HostSherlock API key adds:**

* live MX and mailbox verification through the HostSherlock Verify API
* the current disposable list instead of the bundled snapshot
* detection of role addresses, catch-all domains, and relay or forwarding services (Apple Hide My Email, SimpleLogin)
* a dashboard showing what the guard blocked on your site

**Protected forms**

* WordPress comments
* WordPress user registration
* WooCommerce checkout and account registration
* Contact Form 7 email fields
* WPForms email fields
* Gravity Forms email fields

Each form has its own on/off toggle. Adapters for WooCommerce, Contact Form 7, WPForms, and Gravity Forms only activate when that plugin is installed.

**You decide the policy**

For each verdict class (invalid, disposable, role, catch-all, unverifiable) and for the relay signal, you choose: block the submission, flag it, or allow it. Flagged comments go to the moderation queue; other flagged submissions pass and show up in the decision log. Defaults: invalid and disposable are blocked, relay addresses are flagged, everything else passes.

If the API is unreachable, the guard degrades to its local checks and, by default, lets unverifiable submissions through. Your form never breaks because of our API.

**Privacy**

The plugin never stores or transmits a full email address for analytics. The optional decision log and the optional telemetry events carry the domain only (like `mailinator.com`), never the part before the @.

== External services ==

With an API key configured, the plugin talks to the HostSherlock API in two ways. The API answers at `api.emailsherlock.com`, the host name it had before the product was renamed to HostSherlock; both names belong to the same operator.

1. **Verification.** On form submit, the typed email address is sent to `POST /v1/verify/single` to check whether the mailbox can receive mail. This is the one case where the full address leaves your server; it is required for verification to work. Without an API key, no address is ever sent anywhere.
2. **Decision telemetry.** After the response is sent to the visitor, the plugin reports the decision (domain, verdict, action, reasons) to `POST /v1/guard/events` so you can see blocked domains in your dashboard. The full address is never included. The connection test on the settings screen sends one such event with no domain.

This service is provided by HostSherlock: [terms of service](https://www.hostsherlock.com/terms), [privacy policy](https://www.hostsherlock.com/privacy).

== Installation ==

1. Install and activate the plugin.
2. Done for local checks: comments and registration are protected right away.
3. Optional: get an API key at [hostsherlock.com](https://www.hostsherlock.com/api) and enter it under Settings > Email Guard, then press "Test connection".
4. Optional: create a Guard property for this site in your HostSherlock dashboard and paste its site key, so decisions attribute to this site.

== Frequently Asked Questions ==

= Does it work without a HostSherlock account? =

Yes. Syntax, reserved domains, and the bundled disposable list run locally and block the bulk of junk signups. An API key adds live verification and the dashboard.

= Will an API outage block my visitors? =

No. A failed API call degrades the check to the local rules, and by default the submission passes. You can flip this under Policy if you prefer to fail closed.

= What data leaves my site? =

Without a key: nothing. With a key: the typed address goes to the verify endpoint (that is the product), and domain-only decision events go to the telemetry endpoint. See the External services section.

= Can I add my own form plugin? =

Yes. Implement the `AdapterInterface` and add your adapter through the `hostsherlock_email_guard_adapters` filter.

= Why did a legitimate address get blocked? =

Check the decision log under Settings > Email Guard. Each decision lists its reasons. If a domain is wrongly on the disposable list, report it to HostSherlock support.

== Changelog ==

= 0.1.0 =
* First release. Local checks (syntax profile, reserved TLDs, disposable snapshot), Verify API escalation with degrade-to-local, per-verdict block/flag/allow policy, adapters for comments, registration, WooCommerce, Contact Form 7, WPForms, Gravity Forms, domain-only decision log and telemetry.
