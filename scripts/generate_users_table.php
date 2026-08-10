<?php
// Generate (and optionally execute) SQL to create a comprehensive `users` table
// Usage (print only): php scripts/generate_users_table.php
// Execute the queries: php scripts/generate_users_table.php --exec
// Or in a browser: /scripts/generate_users_table.php?exec=1 (be careful)

$db_host = 'localhost';
$db_user = 'root';
$db_pass = 'US@shopify!@#68';
$db_name = 'shopee_live';

if (!class_exists('mysqli')) {
    echo "Error: PHP MySQLi extension is not available.\n";
    exit(1);
}

$roles_table = <<<SQL
CREATE TABLE IF NOT EXISTS roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE,
  description TEXT DEFAULT NULL,
  permissions JSON DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

$users_table = <<<SQL
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
SQL;

$seed_roles = <<<SQL
INSERT INTO roles (name, slug, description, permissions) VALUES
  ('Super Admin', 'superadmin', 'Full access to all system features', JSON_ARRAY()),
  ('Admin', 'admin', 'Administrative user with elevated privileges', JSON_ARRAY()),
  ('Editor', 'editor', 'Can edit content', JSON_ARRAY()),
  ('Moderator', 'moderator', 'Can moderate user content', JSON_ARRAY()),
  ('Viewer', 'viewer', 'Read-only access', JSON_ARRAY())
ON DUPLICATE KEY UPDATE name=VALUES(name);
SQL;

$queries = [
    "USE `$db_name`; -- Will be created if missing when executing below",
    "CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;",
    $roles_table,
    $users_table,
    $seed_roles
];

// Print the combined SQL
echo "-- Generated SQL for roles + users tables (database: $db_name)\n\n";
foreach ($queries as $q) {
    echo $q . "\n\n";
}

$shouldExecute = false;
if (php_sapi_name() === 'cli') {
    global $argv;
    $shouldExecute = in_array('--exec', $argv, true) || in_array('-e', $argv, true);
} else {
    // allow execution from browser if ?exec=1
    $shouldExecute = isset($_GET['exec']) && ($_GET['exec'] === '1' || strtolower($_GET['exec']) === 'true');
}

if ($shouldExecute) {
    echo "-- Executing queries...\n";
    $m = new mysqli($db_host, $db_user, $db_pass);
    if ($m->connect_errno) {
        echo 'DB connect error: ' . $m->connect_error . "\n";
        exit(1);
    }
    // Create database first
    if (!$m->query("CREATE DATABASE IF NOT EXISTS `" . $m->real_escape_string($db_name) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci")) {
        echo "Failed to create database: " . $m->error . "\n";
        exit(1);
    }
    if (!$m->select_db($db_name)) {
        echo "Failed to select database $db_name: " . $m->error . "\n";
        exit(1);
    }

    foreach ([$roles_table, $users_table, $seed_roles] as $sql) {
        if (!$m->query($sql)) {
            echo "Query failed: " . $m->error . "\nSQL: " . $sql . "\n";
        } else {
            echo "OK\n";
        }
    }
    $m->close();
    echo "-- Done.\n";
}

exit(0);
