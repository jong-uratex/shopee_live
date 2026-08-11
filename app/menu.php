<?php
$current_page = $_GET['page'] ?? 'main';
$is_admin = !empty($_SESSION['is_superadmin']) || (isset($_SESSION['role_slug']) && $_SESSION['role_slug'] === 'admin');
?>
<aside class="side-menu" aria-label="Main navigation">
  <div class="menu-title">Navigation</div>
  <ul class="nav-list">
    <li><a href="/jong/shopee_live/dashboard/" class="<?php echo $current_page === 'main' ? 'active' : ''; ?>"><span class="icon">🏠</span><span>Overview</span></a></li>
    <li><a href="/jong/shopee_live/dashboard/?page=products" class="<?php echo $current_page === 'products' ? 'active' : ''; ?>"><span class="icon">🛍️</span><span>Products</span></a></li>
    <?php if ($is_admin): ?>
      <li><a href="/jong/shopee_live/dashboard/?page=users" class="<?php echo $current_page === 'users' ? 'active' : ''; ?>"><span class="icon">👥</span><span>Users</span></a></li>
    <?php endif; ?>
    <li><a href="/jong/shopee_live/dashboard/?page=profile" class="<?php echo $current_page === 'profile' ? 'active' : ''; ?>"><span class="icon">👤</span><span>Profile</span></a></li>
    <li><a href="/jong/shopee_live/logout/"><span class="icon">🚪</span><span>Sign Out</span></a></li>
  </ul>
</aside>
