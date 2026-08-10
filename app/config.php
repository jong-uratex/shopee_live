<?php
// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = 'US@shopify!@#68';
$db_name = 'shopee_live';

// Friendly check for mysqli availability
if (!class_exists('mysqli')) {
    http_response_code(500);
    echo "Server configuration error: PHP MySQLi extension is not available.\n";
    echo "Install/enable the extension (e.g. on Debian/Ubuntu: `sudo apt install php-mysql`) and restart your web server/PHP-FPM.";
    exit;
}

function db_connect() {
    global $db_host, $db_user, $db_pass, $db_name;
    $mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($mysqli->connect_errno) {
        die('DB connect error: ' . $mysqli->connect_error);
    }
    $mysqli->set_charset('utf8mb4');
    return $mysqli;
}
