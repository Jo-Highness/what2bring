# Changelog

All notable changes to this project are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed
- Docker image now based on `php:8.5-apache` (was 8.3); CI also tests PHP 8.5.
- CI: `actions/checkout` v7, `hadolint/hadolint-action` v3.5.0.

## [1.6.0] - 2026-10-16

First release with a changelog; follows v1.5.0 (tags v1.0.0–v1.5.0 predate this file).

### Security
- **Installer takeover:** the web installer now requires a setup token (printed to the
  server/container log and stored in `data/.setup_token`, or set via `W2B_SETUP_TOKEN`).
  Loopback access without token only with `W2B_SETUP_ALLOW_LOCAL=1`.
- **Login throttling** is persisted in SQLite per IP (exponential lockout up to 15 min)
  and per account; it can no longer be bypassed by discarding the session cookie.
  The password is always verified for uniform response times.
- **Participant entries can no longer be overwritten by knowing someone's e-mail address.**
  Every entry gets a personal edit token (cookie + link on the thanks page, optionally by
  mail); without it, the same address is rejected with a clear message.
- Content-Security-Policy (`script-src 'self'`, all inline JavaScript moved to
  `public/assets/app.js`), HSTS on HTTPS, `Cross-Origin-Opener-Policy`, `Permissions-Policy`.
- `X-Forwarded-Proto` / `X-Forwarded-For` are only honoured from configured trusted proxies.
- Sessions: `use_strict_mode`, ID regeneration on login, admin idle timeout (30 min),
  hardened installer cookie (HttpOnly, SameSite=Strict, Secure on HTTPS).
- Server-side length limits, max. items per submission and a per-IP submission rate limit
  for public polls; `maxlength` in all forms.
- Docker: runs as `www-data` on port 8080 with read-only root filesystem, `cap_drop: ALL`,
  `no-new-privileges`, tmpfs `/tmp`, production `php.ini`, no server signature.

### Added
- Configuration via environment variables (`W2B_*`, incl. `*_FILE` for Docker secrets);
  config and data paths configurable via `W2B_CONFIG` / `W2B_DATA_DIR`.
- Participants can edit or withdraw their own entry; admins can delete single entries.
- Optional automatic deletion of polls N days after their date (`purge_after_days`).
- `bin/backup.php` (consistent, WAL-safe SQLite snapshot), `bin/purge.php`, `bin/setup-token.php`.
- `?r=health` endpoint for container health checks.
- Optional confirmation mail with the personal edit link (`confirmation_mail`, off by default).
- Version constant (`inc/version.php`), clear error on PHP < 8.1 or missing `pdo_sqlite`.
- CI (PHP 8.1–8.4 lint + tests, hadolint, shellcheck, Docker smoke test), Dependabot,
  issue/PR templates, `SECURITY.md`, example configs for nginx, logrotate and cron.

### Changed
- **Docker quick start reworked:** config is written to the `/data` volume
  (`/data/config.php`), named volume by default, entrypoint checks/fixes data directory
  permissions and prints the setup token. Host port stays 8470, container port is now 8080.
- Dry-run mail log masks recipient addresses by default (`mail_log`: `redacted`|`full`|`off`)
  and is stored next to the database.
- Minimum PHP version is now documented and enforced as **8.1**.
- Session cookie renamed from `fmn_sess` to `w2b_sess`.
- Minimum admin password length in the installer raised from 8 to 10 characters.
- Database schema version 2 (`PRAGMA user_version`); migrated automatically.
- Legacy project names and host-specific comments removed.

### Fixed
- Docker: a missing `./config.php` was turned into a directory by the bind mount and the
  installer could not write its config; `./data` owned by root prevented SQLite creation.
- Non-array `items[]` / `item_label[]` POST values caused HTTP 500.
- Hard-coded German 404 page title.
- Backup documentation ignored SQLite WAL files.

[Unreleased]: https://github.com/Jo-Highness/what2bring/compare/v1.6.0...HEAD
[1.6.0]: https://github.com/Jo-Highness/what2bring/compare/v1.5.0...v1.6.0
