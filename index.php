<?php
require_once __DIR__ . '/app/security.php';
// Redirect to success if logged in, otherwise to login
if (!empty($_SESSION['user_id'])) {
    header('Location: success.php');
    exit;
}
header('Location: login.php');
exit;
