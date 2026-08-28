# Requirements — What2Bring (Clean-Room)

> Code-unabhaengiges Anforderungsdokument (WAS/WARUM). Ziel: die App laesst sich allein
> hieraus als Greenfield neu bauen. Produktname What2Bring, Betriebsdomain fragmichnicht.de.
> Nutzer-Dokumentation (Bedienung mit Screenshots): [HANDBUCH.md](HANDBUCH.md) / [HANDBOOK.md](HANDBOOK.md).

## 1. Zweck
Kleine, selbst gehostete Web-App fuer Vereinsfeste, Potlucks und Team-Events: "Wer bringt was
mit?". Ein Organisator legt eine Abfrage mit den benoetigten Dingen (Kuchen, Salat, Getraenke ...)
an und verteilt EINEN geheimen Link. Gaeste oeffnen den Link, haken an, was sie mitbringen,
ergaenzen ein Detail und hinterlassen ihren Namen. Keine Konten fuer Gaeste, keine App, kein
Build-Schritt; muss auf guenstigem Shared Hosting (Strato) laufen. Datenschutz hat Vorrang:
E-Mail-Adressen von Gaesten werden niemals oeffentlich sichtbar.

## 2. Rollen
- **Organisator (Admin):** genau ein passwortgeschuetzter Admin-Bereich (kein Mehrbenutzer-
  System). Legt Abfragen an, verwaltet sie, sieht alle Rueckmeldungen inkl. E-Mail, versendet
  Erinnerungen, exportiert CSV, pflegt Impressum/Datenschutz.
- **Gast (Teilnehmer):** anonym, ohne Login; erreicht eine Abfrage ausschliesslich ueber den
  geheimen Link; sieht nur, was die Sichtbarkeitsstufe der Abfrage erlaubt.
- **Besucher ohne Link:** sieht nur Login-Seite, Impressum/Datenschutz und 404.

## 3. Datenmodell (fachlich)
- **Abfrage (Poll):** geheimes Token (URL-sicher, >=128 Bit Zufall, eindeutig), Ueberschrift
  (Pflicht), Beschreibung, "Benoetigt am"-Datum (ISO-Datum, optional), Sichtbarkeit
  (`who_and_what` | `names_only` | `none`, Default `names_only`), E-Mail-Pflicht (ja/nein,
  Default ja), Zeitstempel angelegt/geaendert.
- **Sache (Item):** gehoert zu genau einer Abfrage; Bezeichnung (Pflicht), Sortierreihenfolge.
  Eine Abfrage hat mindestens eine Sache.
- **Rueckmeldung (Contribution):** gehoert zu einer Abfrage; Name (Pflicht), nicht
  rueckrechenbarer E-Mail-Schluessel (HMAC ueber normalisierte Adresse mit geheimem Pepper),
  Zeitstempel. Pro Abfrage ist der E-Mail-Schluessel eindeutig (Upsert-Schluessel).
- **Kontakt (Contact):** E-Mail-Klartext, 1:1 zur Rueckmeldung, in einer GETRENNTEN Entitaet,
  die der oeffentliche Code-Pfad nie liest. Rueckmeldungen ohne E-Mail haben keinen Kontakt.
- **Rueckmeldungs-Sache:** n:m Rueckmeldung<->Sache mit optionalem Freitext-Detail; pro
  Rueckmeldung und Sache hoechstens ein Eintrag.
- **Einstellungen:** freie Key/Value-Ablage (u.a. Impressum, Datenschutzerklaerung).
- Loeschen einer Abfrage loescht kaskadierend Sachen, Rueckmeldungen, Kontakte, Zuordnungen.

## 4. Funktionale Anforderungen
### 4.1 Installation und Konfiguration
- **FR1** Web-Installer beim ersten Aufruf, solange keine Konfigurationsdatei existiert:
  Admin-Passwort (min. 8 Zeichen, mit Wiederholung), Basis-URL (http/https, vorbelegt aus
  Request), Standardsprache, optional SMTP (Host/Port/Verschluesselung/User/Passwort/
  Absender), Trockenlauf-Schalter (Default an). Der Installer erzeugt Passwort-Hash und
  geheimen Pepper selbst, schreibt die Konfiguration EINE Ebene oberhalb des Docroot und ist
  danach nicht mehr erreichbar (Selbstsperre). Alternativ manuelle Konfiguration aus Beispiel.
- **FR2** Datenbank (SQLite-Datei ausserhalb des Docroot) wird beim ersten Zugriff automatisch
  angelegt; Schema idempotent.
### 4.2 Organisator
- **FR3** Login nur per Passwort (Hash-Vergleich); Logout; wiederholte Fehlversuche werden
  gedrosselt (ab 5 Fehlern ansteigende Sperre, max. 5 Minuten); Session-ID wird beim Login
  erneuert.
- **FR4** Uebersicht aller Abfragen (neueste zuerst) mit Anzahl Sachen und Rueckmeldungen.
- **FR5** Abfrage anlegen/bearbeiten: Ueberschrift (Pflicht), Beschreibung, Datum, Sichtbarkeit,
  E-Mail-Pflicht, dynamische Liste von Sachen (hinzufuegen/umbenennen/entfernen, Reihenfolge
  bleibt erhalten; leere Zeilen werden ignoriert; mindestens eine Sache beim Anlegen).
  Beim Bearbeiten bleiben bestehende Sachen samt Zuordnungen erhalten; entfernte Sachen
  verschwinden auch aus bestehenden Rueckmeldungen. Fehleingaben bleiben sticky.
- **FR6** Abfrage-Detailansicht: Share-Link (kopierbar), alle Rueckmeldungen mit Name, E-Mail,
  gewaehlten Sachen + Details, Zeitpunkt; Zusammenfassung pro Sache.
- **FR7** Share-Link rotieren: neues Token erzeugen; der alte Link liefert sofort 404.
- **FR8** Abfrage loeschen (mit Bestaetigung, POST + CSRF).
- **FR9** CSV-Export der Rueckmeldungen (Semikolon-getrennt, UTF-8 mit BOM fuer Excel,
  Spalten Name/E-Mail/Bringt mit/Aktualisiert; Dateiname aus Titel + Datum).
- **FR10** Erinnerungen: Formular mit Betreff und Text, vorbelegt mit sprachabhaengiger
  Vorlage; Platzhalter `{name}`, `{ueberschrift}`, `{datum}`, `{was_ich_mitbringe}` werden
  pro Empfaenger ersetzt. Versand EINZELN je Empfaenger (ein To, kein CC/BCC). Nur Gaeste mit
  hinterlegter E-Mail. Ergebnis: Anzahl gesendet/fehlgeschlagen (mit bis zu 3 Fehlertexten);
  Hinweis, wenn keine Empfaenger.
- **FR11** Rechtliches: Impressum und Datenschutzerklaerung im Admin editierbar, in der DB
  gespeichert; solange leer, wird eine sprachabhaengige Vorlage als Entwurf vorgeschlagen.
### 4.3 Gast
- **FR12** Teilnahmeseite per Token: zeigt Ueberschrift, Beschreibung, Datum, Liste der Sachen
  als Mehrfachauswahl mit je einem Detail-Feld, Name (Pflicht), E-Mail (Pflicht oder optional
  je Abfrage). Unbekanntes/rotiertes Token -> 404-Seite ohne Informationspreisgabe.
- **FR13** Validierung: Name nicht leer; mindestens eine Sache; E-Mail gueltig, wenn Pflicht
  oder angegeben. Fehler als Meldungen, Eingaben bleiben erhalten.
- **FR14** Upsert-Logik: erneutes Absenden mit derselben E-Mail (Vergleich case-insensitiv,
  getrimmt) ersetzt die vorherige Auswahl derselben Person vollstaendig (kein Duplikat,
  Name wird aktualisiert). Ohne E-Mail entsteht immer ein neuer Eintrag. Nur Sachen der
  jeweiligen Abfrage werden akzeptiert. Speichern atomar.
- **FR15** "Wer ist dabei"-Bereich gemaess Sichtbarkeit: `who_and_what` = Namen je Sache inkl.
  Details; `names_only` = nur alphabetische Namensliste; `none` = nichts. E-Mail nie.
- **FR16** Dankeseite nach dem Absenden (Name aus Session, einmalig), mit Hinweis, dass die
  Angabe per erneutem Eintrag mit gleicher E-Mail anpassbar ist, und Link zurueck.
- **FR17** Impressum und Datenschutz frei erreichbar (ohne Login/Token).
### 4.4 Sprachen
- **FR18** UI dreisprachig DE/EN/ES inkl. Mail- und Rechtstext-Vorlagen. Sprachwahl: URL-
  Parameter > Session > Accept-Language > konfigurierte Standardsprache; Umschalter in der
  Fusszeile erhaelt Route/Query. Fehlende Uebersetzung faellt auf Deutsch zurueck.

## 5. Nicht-funktionale Anforderungen
- **NFR1 (Hosting)** PHP >= 8.0 mit pdo_sqlite, sonst nichts: kein Composer, keine externen
  Bibliotheken, kein Build, kein mod_rewrite (query-basiertes Routing `index.php?r=...`),
  kein Cron. Alles Sensible (Konfig, DB, Includes, Templates) liegt oberhalb des Docroot;
  Fallback-.htaccess sperrt diese Pfade, falls der Docroot nicht auf `public/` zeigen kann.
- **NFR2 (Datenschutz)** E-Mail-Klartext strukturell vom oeffentlichen Pfad getrennt; keine
  Tracker, keine externen Assets/CDNs; noindex/nofollow ueberall (Header, Meta, robots.txt);
  Referrer-Policy no-referrer. Rechtstexte sind Entwurf, keine Rechtsberatung.
- **NFR3 (Sicherheit)** CSRF-Token auf jedem Formular (Admin und Gast), Prepared Statements,
  HTML-Escaping aller Ausgaben, Security-Header (nosniff, X-Frame-Options DENY), Session-
  Cookie httponly/samesite/secure bei HTTPS, Mail-Header gegen CR/LF-Injection gehaertet,
  TLS-Zertifikat des SMTP-Servers wird geprueft. Secrets nur in der Konfigurationsdatei
  (nicht im Repo, nicht ueber HTTP erreichbar).
- **NFR4 (Bedienbarkeit)** Mobile-first, responsiv, grosse Touch-Ziele; Gastformular in unter
  einer Minute ausfuellbar; Flash-Meldungen fuer Erfolg/Fehler.
- **NFR5 (Betrieb)** Zustand ist eine einzige SQLite-Datei + optionales Mail-Log; Backup =
  Dateikopie; kein eingebautes Backup. Trockenlauf-Modus schreibt Mails in ein Log statt zu
  senden. Docker-Testcontainer (php:apache) mit Healthcheck als Entwicklungs-/Testumgebung.
- **NFR6 (Lizenz)** MIT.

## 6. Schnittstellen
- **HTTP/HTML:** einziger Einstiegspunkt mit Query-Router; Routen fuer Login/Logout, Uebersicht,
  Abfrage anlegen/ansehen/bearbeiten/loeschen/Link rotieren/CSV/Erinnerung, Gast-Seite/
  Absenden/Danke, Impressum/Datenschutz, Rechtstexte-Admin. Mutationen nur per POST.
- **Mail:** eigener minimaler SMTP-Client (EHLO, STARTTLS oder SSL oder unverschluesselt,
  AUTH LOGIN optional), Text/Plain UTF-8, ein Empfaenger pro Nachricht, `Auto-Submitted`.
- **Konfiguration:** eine PHP-Datei oberhalb des Docroot: Admin-Passwort-Hash, Pepper,
  Basis-URL, Standardsprache, DB-Pfad, Trockenlauf, SMTP-Block.
- **Export:** CSV-Download (siehe FR9).

## 7. Akzeptanzkriterien
- **AC1** Frischer Upload ohne Konfig zeigt den Installer; nach Abschluss ist der Installer
  nicht mehr aufrufbar, Login funktioniert, DB wird automatisch angelegt.
- **AC2** Organisator legt Abfrage mit 3 Sachen an; Share-Link oeffnet die Gastseite ohne
  Login; Entfernen einer Sache im Admin entfernt sie auch aus Gast-Ansicht und Rueckmeldungen.
- **AC3** Gast sendet zweimal mit derselben E-Mail (verschiedene Schreibweise/Gross-Klein):
  genau ein Eintrag mit der letzten Auswahl. Zwei Gaeste ohne E-Mail: zwei Eintraege.
- **AC4** Bei allen drei Sichtbarkeitsstufen erscheint auf keiner oeffentlichen Seite und in
  keinem oeffentlichen HTML-Quelltext eine E-Mail-Adresse.
- **AC5** Link-Rotation: alter Link -> 404, neuer Link -> Gastseite; Rueckmeldungen bleiben.
- **AC6** Erinnerung an Abfrage mit 2 Gaesten mit E-Mail und 1 ohne: genau 2 Mails, je ein
  Empfaenger, Platzhalter korrekt ersetzt; im Trockenlauf landen sie im Mail-Log.
- **AC7** POST ohne/mit falschem CSRF-Token wird abgewiesen; 5+ falsche Passwoerter fuehren
  zur zeitlichen Sperre; direkter HTTP-Zugriff auf Konfig, DB, Includes liefert 403/404.
- **AC8** CSV-Export oeffnet in Excel mit korrekten Umlauten und allen Rueckmeldungen.
- **AC9** Umschalten auf EN/ES uebersetzt UI, Mail-Vorlage und Rechtstext-Vorlage.

## 8. Rationale
- Ein geheimer Link statt Konten: Gaeste sollen ohne Registrierung in Sekunden antworten;
  die Hemmschwelle ist der entscheidende Erfolgsfaktor bei Vereinsfesten.
- E-Mail-Klartext in getrennter Entitaet + HMAC-Schluessel: Deduplizierung ohne dass die
  oeffentliche Ansicht jemals Adressen laden KANN (strukturelle statt disziplinarische
  Sicherheit).
- Kein Composer/Build/mod_rewrite/Cron: harte Randbedingung guenstiges Strato Shared
  Hosting; die App muss per FTP-Upload laufen.
- Eigener SMTP-Client statt `mail()`: Shared-Hosting-`mail()` ist unzuverlaessig und
  laesst sich schlecht mit Absender-Authentifizierung kombinieren; Einzelversand schuetzt
  Adressen der Gaeste voreinander.
- Sichtbarkeitsstufen pro Abfrage: sozialer Nutzen ("wer bringt schon Kuchen?") gegen
  Zurueckhaltung einzelner Gruppen abwaegbar, entscheidet der Organisator.
- Web-Installer: Zielgruppe sind Vereins-Ehrenamtliche ohne Shell-Zugang; Hash/Pepper duerfen
  nicht von Hand erzeugt werden muessen.
- Trockenlauf als Default: verhindert versehentlichen Massenversand bei der Einrichtung.
