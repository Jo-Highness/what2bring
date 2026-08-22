-- fragmichnicht — SQLite schema
-- E-mail plaintext lives ONLY in contribution_contacts; public repositories never join it.
PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS polls (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  token       TEXT NOT NULL UNIQUE,          -- URL-safe secret, >=128 bit
  title       TEXT NOT NULL,                 -- Überschrift
  description TEXT,                          -- kurzer Text
  event_date  TEXT,                          -- ISO-8601 date (YYYY-MM-DD)
  visibility  TEXT NOT NULL DEFAULT 'names_only'
              CHECK (visibility IN ('who_and_what','names_only','none')),
  email_required INTEGER NOT NULL DEFAULT 1,   -- 1 = E-Mail Pflicht, 0 = optional
  created_at  TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at  TEXT
);

CREATE TABLE IF NOT EXISTS items (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  poll_id    INTEGER NOT NULL REFERENCES polls(id) ON DELETE CASCADE,
  label      TEXT NOT NULL,
  sort_order INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_items_poll ON items(poll_id);

-- public-safe: NO e-mail plaintext here
CREATE TABLE IF NOT EXISTS contributions (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  poll_id    INTEGER NOT NULL REFERENCES polls(id) ON DELETE CASCADE,
  name       TEXT NOT NULL,
  email_hash TEXT NOT NULL,                  -- HMAC(app_pepper, lower(trim(email)))
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT,
  UNIQUE (poll_id, email_hash)               -- upsert key
);
CREATE INDEX IF NOT EXISTS idx_contrib_poll ON contributions(poll_id);

-- e-mail plaintext isolated here; read only by admin/mailer, never by public views
CREATE TABLE IF NOT EXISTS contribution_contacts (
  contribution_id INTEGER PRIMARY KEY REFERENCES contributions(id) ON DELETE CASCADE,
  email           TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS contribution_items (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  contribution_id INTEGER NOT NULL REFERENCES contributions(id) ON DELETE CASCADE,
  item_id         INTEGER NOT NULL REFERENCES items(id) ON DELETE CASCADE,
  detail          TEXT,
  UNIQUE (contribution_id, item_id)
);
CREATE INDEX IF NOT EXISTS idx_ci_item ON contribution_items(item_id);
CREATE INDEX IF NOT EXISTS idx_ci_contrib ON contribution_items(contribution_id);

-- Free-form site settings (e.g. legal texts: impressum, datenschutz)
CREATE TABLE IF NOT EXISTS settings (
  key   TEXT PRIMARY KEY,
  value TEXT NOT NULL DEFAULT ''
);
