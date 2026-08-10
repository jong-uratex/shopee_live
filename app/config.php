<?php
// Database configuration
$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = 'US@shopify!@#68';
$db_name = 'shopee_live';

function db_connect() {
    global $db_host, $db_user, $db_pass, $db_name;
    $mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($mysqli->connect_errno) {
        die('DB connect error: ' . $mysqli->connect_error);
    }
    $mysqli->set_charset('utf8mb4');
    return $mysqli;
}
