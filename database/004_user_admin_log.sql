-- Proposed addition to lost_found_schema.sql (needs the database team's OK).
-- Module 8 (user management): records who changed which account and when.
-- audit_log can't hold these, because its CHECK requires an item or a claim.
-- No passwords are ever stored here – only what kind of change was made.

CREATE TABLE user_admin_log (
  log_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  target_user_id BIGINT UNSIGNED NOT NULL,
  action VARCHAR(60) NOT NULL COMMENT 'USER_CREATED, ROLE_CHANGED, USER_DEACTIVATED, USER_REACTIVATED, PASSWORD_SET_BY_ADMIN, ACCOUNT_UNLOCKED, PASSWORD_CHANGED_BY_USER',
  detail VARCHAR(200) NULL COMMENT 'e.g. "Student -> Staff"; never a password',
  performed_by BIGINT UNSIGNED NOT NULL,
  occurred_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (log_id),
  KEY ix_user_admin_target (target_user_id, occurred_at),
  CONSTRAINT fk_user_admin_target FOREIGN KEY (target_user_id) REFERENCES users(user_id) ON DELETE RESTRICT,
  CONSTRAINT fk_user_admin_actor FOREIGN KEY (performed_by) REFERENCES users(user_id) ON DELETE RESTRICT
) ENGINE=InnoDB;
