<?php
session_start();
// Check if user is logged in; redirect to success or login accordingly
if (!empty($_SESSION['user_id'])) {
    header('Location: success.php');
    exit;
}
header('Location: login.php');
exit;
