<?php
require_once __DIR__ . '/app/security.php';
// Redirect to dashboard if logged in, otherwise to login
if (!empty($_SESSION['user_id'])) {
    header('Location: /jong/shopee_live/dashboard.php');
    exit;
}
header('Location: /jong/shopee_live/login.php');
exit;
