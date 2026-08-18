<?php
require_once __DIR__ . '/app/config.php';

$defaultAdmin = [
    'username' => 'admin',
    'email' => 'admin@example.com',
    'password' => 'Admin@2026',
    'role_slug' => 'admin',
    'role_name' => 'Admin',
    'role_description' => 'Administrative user with elevated privileges',
];

function uuid_v4() {
    if (function_exists('random_bytes')) {
        $data = random_bytes(16);
    } else {
        $data = openssl_random_pseudo_bytes(16);
    }
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

try {
    $pdo = pdo_connect();

    $rolesTableSql = <<<SQL
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

    $usersTableSql = <<<SQL
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  uuid CHAR(36) NOT NULL UNIQUE,
  username VARCHAR(100) NOT NULL UNIQUE,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role_id INT DEFAULT NULL,
  status ENUM('active','inactive','suspended','pending') DEFAULT 'active',
  is_superadmin TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (role_id),
  INDEX (created_at),
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

    $pdo->exec($rolesTableSql);
    $pdo->exec($usersTableSql);

    $oauthTableSql = <<<SQL
CREATE TABLE IF NOT EXISTS shopee_oauth_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  shop_id BIGINT NOT NULL UNIQUE,
  access_token TEXT NOT NULL,
  refresh_token TEXT NOT NULL,
  expire_in INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

    $pdo->exec($oauthTableSql);

    $insertRole = $pdo->prepare(
        'INSERT INTO roles (name, slug, description, permissions) VALUES (:name, :slug, :description, JSON_OBJECT())'
        . ' ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description)'
    );
    $insertRole->execute([
        ':name' => $defaultAdmin['role_name'], 
        ':slug' => $defaultAdmin['role_slug'],
        ':description' => $defaultAdmin['role_description'],
    ]);

    $roleStmt = $pdo->prepare('SELECT id FROM roles WHERE slug = :slug LIMIT 1');
    $roleStmt->execute([':slug' => $defaultAdmin['role_slug']]);
    $roleRow = $roleStmt->fetch();
    $roleId = $roleRow ? $roleRow['id'] : null;

    $selectUser = $pdo->prepare('SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1');
    $selectUser->execute([
        ':username' => $defaultAdmin['username'],
        ':email' => $defaultAdmin['email'],
    ]);
    $existingUser = $selectUser->fetch();
    $hash = password_hash($defaultAdmin['password'], PASSWORD_DEFAULT);

    if ($existingUser) {
        $update = $pdo->prepare(
            'UPDATE users SET username = :username, email = :email, password = :password, role_id = :role_id, is_superadmin = 1, status = "active", updated_at = NOW() WHERE id = :id'
        );
        $update->execute([
            ':username' => $defaultAdmin['username'],
            ':email' => $defaultAdmin['email'],
            ':password' => $hash,
            ':role_id' => $roleId,
            ':id' => $existingUser['id'],
        ]);
        echo "Updated default admin user '{$defaultAdmin['username']}' with new password.\n";
    } else {
        $insertUser = $pdo->prepare(
            'INSERT INTO users (uuid, username, email, password, role_id, is_superadmin, status, created_at, updated_at) '
            . 'VALUES (:uuid, :username, :email, :password, :role_id, 1, "active", NOW(), NOW())'
        );
        $insertUser->execute([
            ':uuid' => uuid_v4(),
            ':username' => $defaultAdmin['username'],
            ':email' => $defaultAdmin['email'],
            ':password' => $hash,
            ':role_id' => $roleId,
        ]);
        echo "Created default admin user '{$defaultAdmin['username']}' with password Admin@2026.\n";
    }

    echo "defaultdb.php completed successfully.\n";
    exit(0);
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
    exit(1);
}
