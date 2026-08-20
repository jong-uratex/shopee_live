<?php
$current_page = $_GET['page'] ?? 'main';
$is_admin = !empty($_SESSION['is_superadmin']) || (isset($_SESSION['role_slug']) && $_SESSION['role_slug'] === 'admin');
?>
<aside class="side-menu" aria-label="Main navigation">
  <div class="menu-header">
    <div class="menu-title">Navigation</div>
    <button class="collapse-btn" id="collapse-toggle" aria-label="Collapse menu">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="15 18 9 12 15 6"></polyline>
      </svg>
    </button>
  </div>
  <ul class="nav-list">
    <li>
      <a href="/jong/shopee_live/dashboard/" class="<?php echo $current_page === 'main' ? 'active' : ''; ?>" title="Overview">
        <span class="icon">🏠</span>
        <span class="label">Overview</span>
      </a>
    </li>
    <li>
      <a href="/jong/shopee_live/dashboard/?page=products" class="<?php echo $current_page === 'products' ? 'active' : ''; ?>" title="Products">
        <span class="icon">🛍️</span>
        <span class="label">Products</span>
      </a>
    </li>
    <?php if ($is_admin): ?>
      <li>
        <a href="/jong/shopee_live/dashboard/?page=users" class="<?php echo $current_page === 'users' ? 'active' : ''; ?>" title="Users">
          <span class="icon">👥</span>
          <span class="label">Users</span>
        </a>
      </li>
    <?php endif; ?>
    <li>
      <a href="/jong/shopee_live/dashboard/?page=profile" class="<?php echo $current_page === 'profile' ? 'active' : ''; ?>" title="Profile">
        <span class="icon">👤</span>
        <span class="label">Profile</span>
      </a>
    </li>
    <li>
      <a href="/jong/shopee_live/logout/" title="Sign Out">
        <span class="icon">🚪</span>
        <span class="label">Sign Out</span>
      </a>
    </li>
  </ul>
</aside>
