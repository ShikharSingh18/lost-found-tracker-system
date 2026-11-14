-- 005: force a password change after an admin sets a temporary password.
-- Run once on existing databases. Existing users stay FALSE (no forced change).
ALTER TABLE users
  ADD COLUMN must_change_password BOOLEAN NOT NULL DEFAULT FALSE;
