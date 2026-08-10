-- PDO Generated SQL for roles + users (database: shopee_live)

-- Roles table:
CREATE TABLE IF NOT EXISTS roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE,
  description TEXT DEFAULT NULL,
  permissions JSON DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Users table:
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  uuid CHAR(36) NOT NULL UNIQUE,
  username VARCHAR(100) NOT NULL UNIQUE,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role_id INT DEFAULT NULL,
  status ENUM('active','inactive','suspended','pending') DEFAULT 'active',
  is_superadmin TINYINT(1) DEFAULT 0,
  two_factor_enabled TINYINT(1) DEFAULT 0,
  two_factor_secret VARCHAR(255) DEFAULT NULL,
  last_login_at DATETIME DEFAULT NULL,
  last_login_ip VARCHAR(45) DEFAULT NULL,
  password_reset_token VARCHAR(255) DEFAULT NULL,
  password_reset_expires DATETIME DEFAULT NULL,
  first_name VARCHAR(100) DEFAULT NULL,
  last_name VARCHAR(100) DEFAULT NULL,
  phone VARCHAR(50) DEFAULT NULL,
  avatar VARCHAR(255) DEFAULT NULL,
  bio TEXT DEFAULT NULL,
  locale VARCHAR(10) DEFAULT 'en',
  timezone VARCHAR(64) DEFAULT 'UTC',
  preferences JSON DEFAULT NULL,
  permissions JSON DEFAULT NULL,
  meta JSON DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME DEFAULT NULL,
  INDEX (role_id),
  INDEX (created_at),
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Run with --exec to execute against the database.
