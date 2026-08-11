<?php
session_start();
// Check if user is logged in; redirect to success or login accordingly
if (!empty($_SESSION['user_id'])) {
    header('Location: /jong/shopee_live/success/');
    exit;
}
header('Location: /jong/shopee_live/login/');
exit;
