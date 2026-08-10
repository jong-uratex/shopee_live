<?php
// DB credentials
$db_host = 'localhost';
$db_user = 'root';
$db_pass = 'US@shopify!@#68';
$db_name = 'shopee_live';

function pdo_connect() {
    global $db_host, $db_user, $db_pass, $db_name;
    $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
    try {
        $opts = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        return new PDO($dsn, $db_user, $db_pass, $opts);
    } catch (PDOException $e) {
        http_response_code(500);
        echo 'DB connection error: ' . htmlspecialchars($e->getMessage());
        exit;
    }
}
