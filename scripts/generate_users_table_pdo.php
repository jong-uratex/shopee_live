<?php
// PDO-based script to create roles and users tables, and seed roles + admin user.
// Usage:
//   php scripts/generate_users_table_pdo.php         # print SQL only
//   php scripts/generate_users_table_pdo.php --exec  # execute
//   Or via browser: /scripts/generate_users_table_pdo.php?exec=1  (use with caution)

$db_host = 'localhost';
$db_user = 'root';
$db_pass = 'US@shopify!@#68';
$db_name = 'shopee_live';

function get_dsn($db_host, $db_name = null) {
    $dsn = "mysql:host={$db_host}";
    if ($db_name) $dsn .= ";dbname={$db_name}";
    $dsn .= ";charset=utf8mb4";
    return $dsn;
}

$roles_table_sql = <<<SQL
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

$users_table_sql = <<<SQL
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

$seed_roles = [
    ['Super Admin', 'superadmin', 'Full access to all system features'],
    ['Admin', 'admin', 'Administrative user with elevated privileges'],
    ['Editor', 'editor', 'Can edit content'],
    ['Moderator', 'moderator', 'Can moderate user content'],
    ['Viewer', 'viewer', 'Read-only access'],
];

$default_admin = [
    'username' => 'admin',
    'email' => 'admin@example.com',
    'password' => 'Admin@123',
    'is_superadmin' => 1,
];

// Print SQL for review
echo "-- PDO Generated SQL for roles + users (database: $db_name)\n\n";
echo "-- Roles table:\n" . $roles_table_sql . "\n";
echo "-- Users table:\n" . $users_table_sql . "\n";

$shouldExecute = false;
if (php_sapi_name() === 'cli') {
    global $argv;
    $shouldExecute = in_array('--exec', $argv, true) || in_array('-e', $argv, true);
} else {
    $shouldExecute = isset($_GET['exec']) && ($_GET['exec'] === '1' || strtolower($_GET['exec']) === 'true');
}

if (!$shouldExecute) {
    echo "-- Run with --exec to execute against the database.\n";
    exit(0);
}

// Execute with PDO
try {
    $pdo = new PDO(get_dsn($db_host), $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;");
    $pdo->exec("USE `{$db_name}`;");

    // Create tables
    $pdo->exec($roles_table_sql);
    echo "Created/verified roles table.\n";
    $pdo->exec($users_table_sql);
    echo "Created/verified users table.\n";

    // Seed roles
    $insertRole = $pdo->prepare("INSERT INTO roles (name, slug, description, permissions) VALUES (:name, :slug, :desc, :perms)
        ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description)");
    foreach ($seed_roles as $r) {
        $insertRole->execute([
            ':name' => $r[0],
            ':slug' => $r[1],
            ':desc' => $r[2],
            ':perms' => json_encode(new stdClass()),
        ]);
    }
    echo "Seeded roles.\n";

    // Ensure Admin role exists and get its id
    $stmt = $pdo->prepare("SELECT id FROM roles WHERE slug = :slug LIMIT 1");
    $stmt->execute([':slug' => 'admin']);
    $role = $stmt->fetch();
    $admin_role_id = $role ? $role['id'] : null;

    // Insert default admin user if not exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1");
    $stmt->execute([':username' => $default_admin['username'], ':email' => $default_admin['email']]);
    if (!$stmt->fetch()) {
        $uuid = com_create_guid();
        if (!$uuid) {
            // fallback UUID
            $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
        }
        $hash = password_hash($default_admin['password'], PASSWORD_DEFAULT);
        $ins = $pdo->prepare("INSERT INTO users (uuid, username, email, password, role_id, is_superadmin, created_at) VALUES
            (:uuid, :username, :email, :password, :role_id, :is_superadmin, NOW())");
        $ins->execute([
            ':uuid' => $uuid,
            ':username' => $default_admin['username'],
            ':email' => $default_admin['email'],
            ':password' => $hash,
            ':role_id' => $admin_role_id,
            ':is_superadmin' => $default_admin['is_superadmin'],
        ]);
        echo "Inserted default admin user ({$default_admin['username']}).\n";
    } else {
        echo "Default admin user already exists.\n";
    }

    echo "Done.\n";

} catch (PDOException $e) {
    echo 'PDO error: ' . $e->getMessage() . "\n";
    exit(1);
}

// Helper com_create_guid for non-Windows
function com_create_guid() {
    if (function_exists('com_create_guid')) return trim(com_create_guid(), '{}');
    // Not windows, attempt to use random_bytes
    try {
        $data = random_bytes(16);
    } catch (Exception $e) {
        return false;
    }
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
