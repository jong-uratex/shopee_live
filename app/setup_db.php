<?php
// Run this script once to create the `users` table and a default admin user.
require_once __DIR__ . '/config.php';
$mysqli = new mysqli($db_host, $db_user, $db_pass);
if ($mysqli->connect_errno) {
    die('DB connect error: ' . $mysqli->connect_error);
}

// Create database if not exists
if (!$mysqli->select_db($db_name)) {
    if (!$mysqli->query("CREATE DATABASE `" . $mysqli->real_escape_string($db_name) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci")) {
        die('Failed to create database: ' . $mysqli->error);
    }
    $mysqli->select_db($db_name);
}

// Create users table
$create = "CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
if (!$mysqli->query($create)) {
    die('Failed to create users table: ' . $mysqli->error);
}

// Insert default admin if not exists
$defaultUser = 'admin';
$defaultPass = 'Admin@123';
$stmt = $mysqli->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
$stmt->bind_param('s', $defaultUser);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    $hash = password_hash($defaultPass, PASSWORD_DEFAULT);
    $ins = $mysqli->prepare('INSERT INTO users (username, password) VALUES (?, ?)');
    $ins->bind_param('ss', $defaultUser, $hash);
    if ($ins->execute()) {
        echo "Created default user 'admin' with password: $defaultPass<br>";
    } else {
        echo "Failed to create default user: " . $ins->error . "<br>";
    }
    $ins->close();
} else {
    echo "Default user 'admin' already exists.<br>";
}
$stmt->close();
$mysqli->close();

echo "Setup complete. Remove or restrict access to setup_db.php after use.";
