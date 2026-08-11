<?php
require_once __DIR__ . '/app/security.php';
// Redirect to dashboard if logged in, otherwise to login
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard');
    exit;
}
header('Location: login');
exit;
