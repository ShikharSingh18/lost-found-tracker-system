-- Proposed addition to lost_found_schema.sql (needs the database team's OK).
-- POPIA / GDPR (Phase 3 NFR3 and group note 8): record WHEN the claimant agreed
-- to their proof of ownership being stored and checked by an administrator.
-- The claim form refuses to submit without this consent, so every new claim
-- has a value. NULL is allowed only so the column can be added to a table
-- that may already hold test claims.

ALTER TABLE claims
  ADD COLUMN consent_given_at DATETIME(6) NULL
  COMMENT 'When the claimant agreed to the privacy notice (POPIA/GDPR)'
  AFTER proof_description;
