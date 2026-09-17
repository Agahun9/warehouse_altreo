-- SalesCenter: tabele globalne (MySQL 5.7+/MariaDB 10.2+, utf8mb4).
-- Zwykle NIE trzeba importować ręcznie: wystarczy install.php?key=... albo php bin/install.php.
-- Tabele danych firm (t{ID}_om_*, t{ID}_print_*) tworzy aplikacja automatycznie przy rejestracji firmy.

CREATE TABLE IF NOT EXISTS sc_tenants (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  nip VARCHAR(30) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at VARCHAR(30) NOT NULL,
  updated_at VARCHAR(30) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sc_users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(150) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'member',
  access VARCHAR(10) NOT NULL DEFAULT 'edit',
  is_blocked INTEGER NOT NULL DEFAULT 0,
  failed_logins INTEGER NOT NULL DEFAULT 0,
  locked_until BIGINT NOT NULL DEFAULT 0,
  last_login_at VARCHAR(30) NULL,
  created_at VARCHAR(30) NOT NULL,
  updated_at VARCHAR(30) NULL,
  INDEX sc_user_tenant (tenant_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sc_password_resets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at BIGINT NOT NULL,
  used_at VARCHAR(30) NULL,
  created_at VARCHAR(30) NOT NULL,
  INDEX sc_reset_user (user_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sc_settings (
  setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
  value_json LONGTEXT NOT NULL,
  updated_at VARCHAR(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
