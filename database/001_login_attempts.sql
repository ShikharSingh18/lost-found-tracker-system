-- Proposed addition to lost_found_schema.sql (needs the database team's OK).
-- Supports Phase 4, Section 8.2: short lockout after 5 failed logins.
-- Tracked by email address (including emails that don't exist), so the
-- lockout message never reveals which emails are registered.
-- No passwords or IP addresses are stored.

CREATE TABLE login_attempts (
  email VARCHAR(254) NOT NULL,
  failed_count INT UNSIGNED NOT NULL DEFAULT 0,
  last_failed_at DATETIME(6) NULL,
  locked_until DATETIME(6) NULL,
  PRIMARY KEY (email)
) ENGINE=InnoDB;
