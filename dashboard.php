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
  <link rel="stylesheet" href="/jong/shopee_live/app/style.css">
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
  
  <script>
    // Hamburger menu toggle
    document.addEventListener('DOMContentLoaded', function() {
      const hamburger = document.getElementById('hamburger-toggle');
      const sideMenu = document.querySelector('.side-menu');
      const appBody = document.querySelector('.app-body');

      if (hamburger) {
        hamburger.addEventListener('click', function() {
          hamburger.classList.toggle('active');
          sideMenu.classList.toggle('mobile-open');
          appBody.classList.toggle('menu-open');
        });

        // Close menu when a menu item is clicked
        const menuItems = sideMenu.querySelectorAll('.nav-list a');
        menuItems.forEach(item => {
          item.addEventListener('click', function() {
            hamburger.classList.remove('active');
            sideMenu.classList.remove('mobile-open');
            appBody.classList.remove('menu-open');
          });
        });

        // Close menu when clicking outside
        document.addEventListener('click', function(event) {
          const isClickInsideMenu = sideMenu.contains(event.target);
          const isClickOnHamburger = hamburger.contains(event.target);
          
          if (!isClickInsideMenu && !isClickOnHamburger && sideMenu.classList.contains('mobile-open')) {
            hamburger.classList.remove('active');
            sideMenu.classList.remove('mobile-open');
            appBody.classList.remove('menu-open');
          }
        });
      }
    });
  </script>
</body>
</html>
