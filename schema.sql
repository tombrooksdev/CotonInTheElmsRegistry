CREATE TABLE signups (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  business VARCHAR(160) NOT NULL,
  description VARCHAR(500) NOT NULL,
  marketing_consent TINYINT(1) NOT NULL DEFAULT 0,
  consent_text VARCHAR(255) NOT NULL,
  ip_hash CHAR(64) NOT NULL,
  token CHAR(64) NOT NULL,
  confirmed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_email (email),
  UNIQUE KEY uq_token (token),
  KEY idx_ip (ip_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
