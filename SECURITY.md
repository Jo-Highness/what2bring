# Security Policy

## Supported versions

What2Bring follows a **latest-only** support policy: security fixes are released
for the most recent version. Please update before reporting an issue.

| Version | Supported |
| ------- | --------- |
| 1.x (latest) | ✅ |
| older / unversioned | ❌ |

## Reporting a vulnerability

Please report security issues **privately** through
[GitHub Security Advisories](https://github.com/Jo-Highness/what2bring/security/advisories/new).
Do **not** open a public issue for a security report.

Please include the version, how you run it (Docker, Apache, nginx …), steps to
reproduce and the impact you see. You will get an acknowledgement, and fixes and
disclosure will be coordinated with you.

## Scope and threat model

What2Bring is a small self-hosted PHP/SQLite application:

- **Admin area:** one password-protected organiser account. Login failures are
  throttled per IP and per account (persisted in SQLite); sessions use strict mode,
  are regenerated on login and expire after inactivity.
- **Participants:** no accounts. A poll is reachable only via its secret link
  (≥ 128-bit token). An entry can only be changed or withdrawn with its personal
  edit token (≥ 256 bit, stored as SHA-256 hash).
- **Installer:** only active while the app is unconfigured, and only with the
  setup token from the server/container log or `W2B_SETUP_TOKEN`.
- **Personal data:** names, optional e-mail addresses and what people bring.
  E-mail addresses are stored in a separate table that public pages never read;
  IP addresses used for throttling are stored only as HMAC and pruned after 24 h.
- **Hardening:** CSRF tokens on every form, prepared statements, output escaping,
  CSP without inline scripts, HSTS on HTTPS, `X-Forwarded-*` only from configured
  proxies, CSV formula-injection and mail header-injection guards.
- **Outbound connections:** only to the SMTP server you configure.

Out of scope: weaknesses that require control of the server, the config file or
the database; missing HTTPS when the operator does not configure it; denial of
service by sheer request volume (use your reverse proxy / hoster for that).
