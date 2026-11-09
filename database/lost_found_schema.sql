-- Lost and Found Tracker: MySQL 8.0.16+; InnoDB, utf8mb4.
-- Run in a NEW development database only. No database is created or dropped.
-- For example: CREATE DATABASE lost_found CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
-- USE lost_found;
-- Existing object names will produce errors instead of being deleted.

CREATE TABLE users (
  user_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(254) NOT NULL,
  password_hash VARCHAR(255) NOT NULL COMMENT 'PHP password_hash output; never plaintext',
  role ENUM('Student','Staff','Admin') NOT NULL DEFAULT 'Student',
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

CREATE TABLE categories (
  category_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_name VARCHAR(80) NOT NULL,
  PRIMARY KEY (category_id),
  UNIQUE KEY uq_categories_name (category_name)
) ENGINE=InnoDB;

CREATE TABLE items (
  item_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id INT UNSIGNED NOT NULL,
  description TEXT NOT NULL,
  date_found DATE NOT NULL,
  location_found VARCHAR(200) NOT NULL,
  photo_storage_key VARCHAR(500) NULL COMMENT 'Private object key; no public access token',
  status ENUM('Found','Claimed','Disposed') NOT NULL DEFAULT 'Found',
  logged_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (item_id),
  KEY ix_items_search (status, category_id, date_found),
  KEY ix_items_location (location_found),
  KEY ix_items_logged_by (logged_by),
  CONSTRAINT fk_items_category FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE RESTRICT,
  CONSTRAINT fk_items_user FOREIGN KEY (logged_by) REFERENCES users(user_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE claims (
  claim_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  item_id BIGINT UNSIGNED NOT NULL,
  claimant_user_id BIGINT UNSIGNED NOT NULL,
  proof_description TEXT NOT NULL,
  verification_status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  date_submitted DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  verified_by BIGINT UNSIGNED NULL,
  date_verified DATETIME(6) NULL,
  -- NULL is permitted multiple times in MySQL UNIQUE indexes; only an approved
  -- claim contributes its item_id, protecting against concurrent approvals.
  approved_item_id BIGINT UNSIGNED GENERATED ALWAYS AS
    (CASE WHEN verification_status = 'Approved' THEN item_id ELSE NULL END) STORED,
  PRIMARY KEY (claim_id),
  UNIQUE KEY uq_claims_approved_item (approved_item_id),
  UNIQUE KEY uq_claims_id_item (claim_id, item_id),
  KEY ix_claims_item_status (item_id, verification_status),
  KEY ix_claims_claimant (claimant_user_id, date_submitted),
  KEY ix_claims_verifier (verified_by),
  CONSTRAINT fk_claims_item FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE RESTRICT,
  CONSTRAINT fk_claims_claimant FOREIGN KEY (claimant_user_id) REFERENCES users(user_id) ON DELETE RESTRICT,
  CONSTRAINT fk_claims_verifier FOREIGN KEY (verified_by) REFERENCES users(user_id) ON DELETE RESTRICT,
  CONSTRAINT ck_claim_review CHECK (
    (verification_status = 'Pending' AND verified_by IS NULL AND date_verified IS NULL)
    OR (verification_status IN ('Approved','Rejected') AND verified_by IS NOT NULL AND date_verified IS NOT NULL)
  )
) ENGINE=InnoDB;

CREATE TABLE notifications (
  notification_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  claim_id BIGINT UNSIGNED NULL,
  message VARCHAR(500) NOT NULL,
  is_read BOOLEAN NOT NULL DEFAULT FALSE,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (notification_id),
  KEY ix_notifications_inbox (user_id, is_read, created_at),
  KEY ix_notifications_claim (claim_id),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE RESTRICT,
  CONSTRAINT fk_notifications_claim FOREIGN KEY (claim_id) REFERENCES claims(claim_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE audit_log (
  log_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  item_id BIGINT UNSIGNED NULL,
  claim_id BIGINT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  performed_by BIGINT UNSIGNED NOT NULL,
  occurred_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (log_id),
  KEY ix_audit_item_time (item_id, occurred_at),
  KEY ix_audit_claim_time (claim_id, occurred_at),
  KEY ix_audit_actor_time (performed_by, occurred_at),
  CONSTRAINT fk_audit_item FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE RESTRICT,
  CONSTRAINT fk_audit_claim FOREIGN KEY (claim_id) REFERENCES claims(claim_id) ON DELETE RESTRICT,
  CONSTRAINT fk_audit_claim_item FOREIGN KEY (claim_id, item_id) REFERENCES claims(claim_id, item_id) ON DELETE RESTRICT,
  CONSTRAINT fk_audit_actor FOREIGN KEY (performed_by) REFERENCES users(user_id) ON DELETE RESTRICT,
  CONSTRAINT ck_audit_subject CHECK (item_id IS NOT NULL OR claim_id IS NOT NULL)
) ENGINE=InnoDB;

INSERT INTO categories (category_name) VALUES
('Keys'),('Wallet'),('Phone'),('ID Card'),('Clothing'),('Stationery'),('Other');

-- This file defines structure only. PHP must use prepared statements, authorize
-- roles, and atomically write item/claim changes, audit records, notifications.
-- See the design report for the concurrency-safe approval transaction.
