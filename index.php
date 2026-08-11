<?php
if (isset($_SERVER['HTTP_HOST']) && strcasecmp($_SERVER['HTTP_HOST'], 'dev.uratex.com.ph') !== 0) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    header('Location: ' . $protocol . '://dev.uratex.com.ph' . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
    exit;
}

require_once __DIR__ . '/app/security.php';
// Redirect to dashboard if logged in, otherwise to login
if (!empty($_SESSION['user_id'])) {
    header('Location: /jong/shopee_live/dashboard');
    exit;
}
header('Location: /jong/shopee_live/login');
exit;
