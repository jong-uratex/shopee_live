<?php
/**
 * Success / Dashboard landing page after login
 */

require_once __DIR__ . '/app/security.php';

// Not logged in → send back to login
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$username = htmlspecialchars($_SESSION['username'] ?? 'User', ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Success</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
</head>
<body class="d-flex align-items-center justify-content-center vh-100">
  <div class="text-center">
    <h2>Successfully logged in</h2>
    <p class="lead">Welcome, <?= $username ?>.</p>
    <a href="logout.php" class="btn btn-danger">Logout</a>
  </div>
</body>
</html>