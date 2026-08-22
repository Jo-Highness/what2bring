# What2Bring

PHP 8 + SQLite Web-App für Vereinsfeste: **Wer bringt was mit?** Eine Orga-Person
(Admin) legt Mitbring-Abfragen an (Kuchen, Salat, Obst …) und teilt je Abfrage
einen **geheimen Link**. Teilnehmende tragen **ohne Login** über diesen Link ein,
was sie mitbringen. Strato-tauglich: **kein Composer/Build**, reines PHP, eigener
abhängigkeitsfreier SMTP-Client. **E-Mail-Adressen bleiben strikt intern** (nie
auf öffentlichen Seiten).

> Kein MCP-Server, kein Reverse-Proxy nötig. Klassische PHP-App für Shared-Hosting.
> Hosting-Domain: **fragmichnicht.de**. Repo: gitea `jarvis/what2bring` (Branch `main`).
> QA: PASS 21/21 (Suite `ebc330e4`). Clean-Room-Anforderungen: infrastructure-docs, requirements-Doc `1dc5fb32`.

## Funktionen
- **Admin (passwortgeschützt):** Abfragen anlegen/bearbeiten/löschen (Überschrift,
  Beschreibung, „benötigt am"-Datum, dynamische Dinge-Liste, Sichtbarkeit); teilbarer
  Geheim-Link inkl. „neu erzeugen" (alter wird ungültig); Erinnerungsmails mit
  Platzhaltern `{name}`/`{ueberschrift}`/`{datum}`/`{was_ich_mitbringe}`, **Einzelversand**.
- **Teilnehmende (nur per Token):** Mehrfachauswahl der Dinge + optionales Detailfeld;
  Name + E-Mail Pflicht; erneutes Absenden mit gleicher E-Mail aktualisiert den eigenen
  Eintrag; optional Mitbring-Ansicht (nur Namen), je nach Admin-Sichtbarkeit.
- **Sichtbarkeit je Abfrage:** `who_and_what` | `names_only` | `none` — **E-Mail nie**.
- **Rechtliches:** Impressum + Datenschutzerklärung werden über die Admin-Seite
  „Rechtliches" gepflegt (in der DB, nicht im Code), mit Vorlagen zum Ausfüllen;
  öffentlich unter `?r=impressum` / `?r=datenschutz`, von jeder Seite im Footer verlinkt.

## Konfiguration (`config.php`, nicht eingecheckt)
`config.php.example` nach `config.php` kopieren (eine Ebene **über** dem Docroot
`public/`) und ausfüllen:
- `admin_password_hash` — `php -r "echo password_hash('DEIN-PASSWORT', PASSWORD_DEFAULT), PHP_EOL;"`
- `app_pepper` — `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"` (stabil halten!)
- `base_url` — öffentliche Basis-URL ohne Slash, z. B. `https://fragmichnicht.de`
- `db_file` — absoluter Pfad zur SQLite-Datei (außerhalb des Docroots)
- `mail_dry_run` — `true` = kein echter Versand, Mails landen in `data/mail.log`
- `smtp{}` — Host/Port/secure/User/Pass/from/verify_tls für den echten Versand

**Keine Secret-Werte ins Repo.** Die leere DB wird beim ersten Zugriff aus
`schema.sql` initialisiert (WAL, foreign_keys on).

## Lokal starten
    cp config.php.example config.php   # Werte ausfüllen (Hash + Pepper generieren)
    php -S 127.0.0.1:8479 -t public
    # -> http://127.0.0.1:8479/index.php?r=admin.login

## Docker-Test
    docker compose up -d               # baut Image, Container "what2bring"
    # -> http://<host>:8470/index.php?r=admin.login
- Port **8470** (Host) -> 80 (Container), **plain HTTP** (nicht hinter dem mTLS-Proxy).
- Mounts: `./data -> /var/www/html/data` (SQLite + `mail.log`),
  `./config.php -> /var/www/html/config.php:ro`.
- Apache-Docroot ist `public/`; `pdo_sqlite`, `rewrite`, `headers` sind im Image aktiv.
- Auf der Test-Site **`mail_dry_run = true`** setzen, damit keine echten Mails rausgehen.

## Strato-Deployment (Prod, Domain fragmichnicht.de)
- Bevorzugt: **Domain-Docroot auf `public/`** zeigen lassen. Dann liegen `config.php`,
  `data/`, `inc/`, `templates/`, `schema.sql` **oberhalb** des Docroots.
- Falls der Docroot **nicht** verschiebbar ist: die mitgelieferte **Fallback-`.htaccess`**
  im Projekt-Root sperrt `inc/`, `data/`, `templates/`, `config.php`, `schema.sql`, Dotfiles.
- `data/.htaccess` verbietet zusätzlich HTTP-Zugriff aufs Datenverzeichnis.
- Routing ist query-basiert (`index.php?r=…`), **mod_rewrite ist nicht erforderlich**.
- `mail_dry_run = false` erst setzen, wenn echte SMTP-Zugangsdaten hinterlegt sind.

## Sicherheit & Datenschutz (Kurz)
- E-Mail-Klartext liegt **isoliert** in einer eigenen Kontakt-Tabelle, die der
  öffentliche Datenpfad **nie** liest → strukturell keine E-Mail auf öffentlichen Seiten.
- Dedup/Upsert über **HMAC-Hash der E-Mail mit geheimem Pepper** (nicht umkehrbar).
- CSRF-Token auf allen POST-Formularen; Prepared Statements; Output-Escaping.
- `noindex`/`X-Robots-Tag`/`Referrer-Policy: no-referrer`/`nosniff`/`X-Frame-Options: DENY`
  (per PHP **und** `.htaccess`); `robots.txt` disallow all.
- Geheim-Token >=128 Bit, URL-safe; per „neu erzeugen" rotierbar.
- Admin-Login: Password-Hash (kein Klartext) + Login-Throttling.

## Backup & Betrieb
- **Kein App-eigenes Backup.** Sicherung = SQLite-Datei aus `data/` (bzw. Strato-Backup).
- SMTP-Zugangsdaten kommen vom Betreiber und stehen nur in `config.php`.
- Betriebsdetails: Runbook `what2bring` (infrastructure-docs).

## Troubleshooting
- **„Konfiguration fehlt"**: `config.php` liegt nicht eine Ebene über `public/` bzw. fehlt.
- **Teilnahme-Link 404**: Token falsch/rotiert (Regenerate macht alte Links ungültig).
- **Mails kommen nicht an**: `mail_dry_run` prüfen; bei `false` SMTP-Zugang/`verify_tls`
  prüfen; im Dry-Run `data/mail.log` ansehen.
