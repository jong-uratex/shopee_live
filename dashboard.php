<?php
require_once __DIR__ . '/app/security.php';
require_once __DIR__ . '/app/config.php';

require_login();

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard</title>
  <link rel="stylesheet" href="/app/style.css">
</head>
<body class="app-shell">
  <?php include __DIR__ . '/app/header.php'; ?>
  <div class="app-body">
    <?php include __DIR__ . '/app/menu.php'; ?>
    <main class="content-area">
      <?php include __DIR__ . '/app/content_holder.php'; ?>
    </main>
  </div>
  <?php include __DIR__ . '/app/footer.php'; ?>
</body>
</html>
