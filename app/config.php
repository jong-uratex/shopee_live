<?php
// DB credentials
$db_host = 'localhost';
$db_user = 'root';
$db_pass = 'UX@shopify!@#68';
$db_name = 'shopee_live';

// Shopee Partner credentials
$partnerId = 2041083;
$partnerKey = 'shpk5a444c494c79715545596a714457424e684d784e796d4951645853454248';
$host = 'https://partner.shopeemobile.com';
$redirectUrl = 'http://dev.uratex.com.ph/jong/shopee_live/callback.php';

// Prevent direct web access to this file
if (php_sapi_name() !== 'cli' && realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

function pdo_connect($halt_on_error = true) {
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
        if ($halt_on_error) {
            http_response_code(500);
            echo 'DB connection error: ' . htmlspecialchars($e->getMessage());
            exit;
        }
        throw $e;
    }
}

function pdo_connection_status() {
    try {
        $pdo = pdo_connect(false);
        $pdo = null;
        return 'Database: Connected';
    } catch (PDOException $e) {
        return 'Database: Disconnected';
    }
}

function get_network_status(): string {
    $connected = @fsockopen('8.8.8.8', 53, $errno, $errstr, 2);
    if ($connected) {
        fclose($connected);
        return 'Online';
    }
    return 'Offline';
}

function get_current_location(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if ($ip === '127.0.0.1' || $ip === '::1') {
        return 'Localhost';
    }
    return $ip;
}
