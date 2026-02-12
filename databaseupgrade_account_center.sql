-- Database upgrade for account center + redeem code management
ALTER TABLE users
  ADD COLUMN redeem_fail_count INT NOT NULL DEFAULT 0 AFTER disabled,
  ADD COLUMN redeem_locked_until DATETIME NULL DEFAULT NULL AFTER redeem_fail_count;

CREATE TABLE IF NOT EXISTS redeem_codes (
  id BIGINT NOT NULL AUTO_INCREMENT,
  code VARCHAR(64) NOT NULL,
  code_type ENUM('public','bound') NOT NULL DEFAULT 'public',
  bound_uid VARCHAR(50) DEFAULT NULL,
  target_group ENUM('new','auto','owner') NOT NULL DEFAULT 'auto',
  is_used TINYINT(1) NOT NULL DEFAULT 0,
  redeemed_by VARCHAR(50) DEFAULT NULL,
  redeemed_at DATETIME DEFAULT NULL,
  created_by VARCHAR(50) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_redeem_code (code),
  KEY idx_redeem_bound_uid (bound_uid),
  KEY idx_redeem_is_used (is_used),
  KEY idx_redeem_created_at (created_at),
  CONSTRAINT fk_redeem_bound_uid FOREIGN KEY (bound_uid) REFERENCES users (uid) ON DELETE SET NULL,
  CONSTRAINT fk_redeem_redeemed_by FOREIGN KEY (redeemed_by) REFERENCES users (uid) ON DELETE SET NULL,
  CONSTRAINT fk_redeem_created_by FOREIGN KEY (created_by) REFERENCES users (uid) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
