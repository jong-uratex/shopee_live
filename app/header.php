<header class="main-header">
  <div class="main-header__brand">
    <img src="https://uratex.com.ph/cdn/shop/files/Final_Logo.png" alt="Uratex Philippines Logo">
    <div>
      <span class="brand-title">Uratex Admin</span>
      <span class="brand-subtitle">Admin Dashboard</span>
    </div>
  </div>
  <div class="header-actions">
    <a href="/jong/shopee_live/dashboard/">Dashboard</a>
    <?php if (!empty($_SESSION['is_superadmin']) || (isset($_SESSION['role_slug']) && $_SESSION['role_slug'] === 'admin')): ?>
      <a href="/jong/shopee_live/user.php">Users</a>
    <?php endif; ?>
    <a href="/jong/shopee_live/dashboard/?page=profile">Profile</a>
    <a href="/jong/shopee_live/logout/">Logout</a>
  </div>
</header>
