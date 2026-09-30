-- DD Support Classes: registration table
-- Import with:  mysql -u your_user -p dd_support < schema.sql

CREATE DATABASE IF NOT EXISTS dd_support
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE dd_support;

CREATE TABLE IF NOT EXISTS registrations (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_id           VARCHAR(32)  NOT NULL UNIQUE,
  first_name          VARCHAR(100) NOT NULL,
  last_name           VARCHAR(100) NOT NULL,
  phone               VARCHAR(20)  NOT NULL,
  email               VARCHAR(190) NOT NULL,
  ofppt_establishment VARCHAR(150) NULL,
  level               ENUM('1','2') NULL,
  status              ENUM('PENDING','CONFIRMED','CONTACTED','CANCELLED','COMPLETED') NOT NULL DEFAULT 'PENDING',
  terms_accepted      TINYINT(1) NOT NULL DEFAULT 0,
  privacy_accepted    TINYINT(1) NOT NULL DEFAULT 0,
  accepted_at         DATETIME NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by          VARCHAR(100) NULL,

  INDEX idx_email (email),
  INDEX idx_phone (phone),
  INDEX idx_status (status)
) ENGINE=InnoDB;
