-- Schema for Top Debt Options leads.
-- Mirrors the record written to storage/leads.jsonl (the durable fallback).
-- Apply with: php db-migrate.php   (or paste into phpMyAdmin / the Cloudways DB tool).
--
-- Core lead data lives in `leads`; the three integrations each get their own
-- child table joined on lead_id (one row per lead, FK-cascaded on delete).

CREATE TABLE IF NOT EXISTS leads (
  id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lead_id               CHAR(16)        NOT NULL,                 -- bin2hex(random_bytes(8)) from submit.php
  first_name            VARCHAR(100)    NOT NULL DEFAULT '',
  last_name             VARCHAR(100)    NOT NULL DEFAULT '',
  email                 VARCHAR(255)    NOT NULL DEFAULT '',
  phone                 VARCHAR(32)     NOT NULL DEFAULT '',
  verified_phone        VARCHAR(32)     NULL,
  debt_amount           VARCHAR(20)     NOT NULL DEFAULT '',      -- range bucket, e.g. "10000-14999"
  payment_status        VARCHAR(20)     NOT NULL DEFAULT '',
  address               VARCHAR(255)    NOT NULL DEFAULT '',
  city                  VARCHAR(120)    NOT NULL DEFAULT '',
  state                 CHAR(2)         NOT NULL DEFAULT '',
  zip                   VARCHAR(10)     NOT NULL DEFAULT '',
  dob                   DATE            NULL,
  credit_consent        TINYINT(1)      NOT NULL DEFAULT 0,
  contact_consent       TINYINT(1)      NOT NULL DEFAULT 0,
  phone_verified        TINYINT(1)      NOT NULL DEFAULT 0,
  verified_total_debt   DECIMAL(12,2)   NULL,                     -- Equifax soft-pull total used for qualification
  outcome               VARCHAR(20)     NOT NULL DEFAULT '',      -- 'qualified' | 'offerwall'
  debt_qualified        TINYINT(1)      NOT NULL DEFAULT 0,       -- routing flag: 1 = thank-you (>=$10k), 0 = offerwall
  ip                    VARCHAR(45)     NOT NULL DEFAULT '',
  user_agent            VARCHAR(500)    NOT NULL DEFAULT '',
  trustedform_cert_url  VARCHAR(255)    NOT NULL DEFAULT '',
  jornaya_leadid        VARCHAR(255)    NOT NULL DEFAULT '',
  tcpa_text             TEXT            NULL,
  tracking              JSON            NULL,                     -- attribution params
  submitted_at          DATETIME        NOT NULL,
  created_at            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_lead_id (lead_id),
  KEY idx_email (email),
  KEY idx_outcome (outcome),
  KEY idx_submitted_at (submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Equifax soft-pull result (one per lead).
CREATE TABLE IF NOT EXISTS lead_equifax (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lead_id        CHAR(16)        NOT NULL,
  ok             TINYINT(1)      NOT NULL DEFAULT 0,
  skipped        TINYINT(1)      NOT NULL DEFAULT 0,
  status         INT             NOT NULL DEFAULT 0,   -- HTTP status
  total_debt     DECIMAL(12,2)   NULL,
  transaction_id VARCHAR(255)    NULL,
  error          TEXT            NULL,
  sent           JSON            NULL,                 -- request payload, account IDs redacted
  response       LONGTEXT        NULL,                 -- raw response body (diagnostics only)
  created_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_lead_id (lead_id),
  CONSTRAINT fk_equifax_lead FOREIGN KEY (lead_id) REFERENCES leads (lead_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- LeadProsper direct-post result (one per lead).
CREATE TABLE IF NOT EXISTS lead_leadprosper (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lead_id     CHAR(16)        NOT NULL,
  ok          TINYINT(1)      NOT NULL DEFAULT 0,
  skipped     TINYINT(1)      NOT NULL DEFAULT 0,
  status      INT             NOT NULL DEFAULT 0,      -- HTTP status
  error       TEXT            NULL,
  sent        JSON            NULL,                    -- request payload, lp_key redacted
  response    LONGTEXT        NULL,                    -- raw response body
  created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_lead_id (lead_id),
  CONSTRAINT fk_lp_lead FOREIGN KEY (lead_id) REFERENCES leads (lead_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Google Ads attribution (one per lead). utm_* + ValueTrack params captured
-- first-touch. gclid/gbraid/wbraid are the click IDs used for offline conversion
-- import back into Google Ads (Smart Bidding signal); utm_* are for reporting.
CREATE TABLE IF NOT EXISTS lead_google_ads (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lead_id       CHAR(16)        NOT NULL,
  utm_source    VARCHAR(255)    NOT NULL DEFAULT '',
  utm_medium    VARCHAR(255)    NOT NULL DEFAULT '',
  utm_campaign  VARCHAR(255)    NOT NULL DEFAULT '',
  utm_term      VARCHAR(255)    NOT NULL DEFAULT '',
  utm_content   VARCHAR(255)    NOT NULL DEFAULT '',
  keyword       VARCHAR(255)    NOT NULL DEFAULT '',
  matchtype     VARCHAR(20)     NOT NULL DEFAULT '',   -- e / p / b
  network       VARCHAR(20)     NOT NULL DEFAULT '',   -- g / s / d
  device        VARCHAR(20)     NOT NULL DEFAULT '',   -- m / t / c
  gclid         VARCHAR(255)    NOT NULL DEFAULT '',
  gbraid        VARCHAR(255)    NOT NULL DEFAULT '',
  wbraid        VARCHAR(255)    NOT NULL DEFAULT '',
  created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_lead_id (lead_id),
  KEY idx_gclid (gclid),
  KEY idx_utm_campaign (utm_campaign),
  CONSTRAINT fk_gads_lead FOREIGN KEY (lead_id) REFERENCES leads (lead_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CallGrid assigned tracking number (assigned client-side on the thank-you page).
CREATE TABLE IF NOT EXISTS lead_callgrid (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lead_id       CHAR(16)        NOT NULL,
  session_id    VARCHAR(64)     NOT NULL DEFAULT '',   -- analytics per-visit UUID; matches CallGrid call reports
  phone_number  VARCHAR(32)     NOT NULL,
  ip            VARCHAR(45)     NOT NULL DEFAULT '',
  assigned_at   DATETIME        NOT NULL,
  created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_lead_id (lead_id),
  KEY idx_session_id (session_id),
  CONSTRAINT fk_callgrid_lead FOREIGN KEY (lead_id) REFERENCES leads (lead_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
