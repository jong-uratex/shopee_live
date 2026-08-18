<header class="main-header">
  <div class="main-header__brand">
    <button class="hamburger-menu" id="hamburger-toggle" aria-label="Toggle menu">
      <span></span>
      <span></span>
      <span></span>
    </button>
    <img src="https://uratex.com.ph/cdn/shop/files/Final_Logo.png" alt="Uratex Philippines Logo">
    <div>
      <span class="brand-title">Uratex Admin</span>
      <span class="brand-subtitle">Admin Dashboard</span>
    </div>
  </div>
  <div class="header-actions">
    <!-- Global Search Bar -->
    <div class="search-bar">
      <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="11" cy="11" r="8"></circle>
        <path d="m21 21-4.35-4.35"></path>
      </svg>
      <input type="text" class="search-input" placeholder="Search products, users...">
    </div>

    <!-- Notification Bell -->
    <div class="notification-bell">
      <button class="bell-btn" aria-label="Notifications">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
          <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
        </svg>
        <span class="notification-badge">3</span>
      </button>
      <div class="notification-dropdown">
        <div class="notification-header">Notifications</div>
        <div class="notification-item">
          <div class="notification-content">
            <p class="notification-title">API Rate Limit Warning</p>
            <p class="notification-time">5 minutes ago</p>
          </div>
        </div>
        <div class="notification-item">
          <div class="notification-content">
            <p class="notification-title">New product sync completed</p>
            <p class="notification-time">2 hours ago</p>
          </div>
        </div>
        <div class="notification-item">
          <div class="notification-content">
            <p class="notification-title">Token refresh successful</p>
            <p class="notification-time">1 day ago</p>
          </div>
        </div>
        <a href="#" class="notification-footer">View all notifications</a>
      </div>
    </div>

    <!-- Avatar Dropdown -->
    <div class="avatar-dropdown">
      <button class="avatar-btn" aria-label="User menu">
        <div class="avatar">
          <?php 
            $first_initial = isset($_SESSION['first_name']) ? strtoupper(substr($_SESSION['first_name'], 0, 1)) : 'U';
            $last_initial = isset($_SESSION['last_name']) ? strtoupper(substr($_SESSION['last_name'], 0, 1)) : '';
            echo $first_initial . $last_initial;
          ?>
        </div>
        <svg class="dropdown-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="6 9 12 15 18 9"></polyline>
        </svg>
      </button>
      <div class="avatar-menu">
        <div class="menu-header">
          <p class="menu-username"><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></p>
          <p class="menu-email"><?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></p>
        </div>
        <hr>
        <a href="/jong/shopee_live/dashboard/?page=profile" class="menu-item">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
            <circle cx="12" cy="7" r="4"></circle>
          </svg>
          Profile
        </a>
        <a href="/jong/shopee_live/dashboard/" class="menu-item">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
            <polyline points="9 22 9 12 15 12 15 22"></polyline>
          </svg>
          Dashboard
        </a>
        <?php if (!empty($_SESSION['is_superadmin']) || (isset($_SESSION['role_slug']) && $_SESSION['role_slug'] === 'admin')): ?>
        <a href="/jong/shopee_live/users.php" class="menu-item">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
          </svg>
          Users
        </a>
        <?php endif; ?>
        <hr>
        <a href="/jong/shopee_live/logout/" class="menu-item logout">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
            <polyline points="16 17 21 12 16 7"></polyline>
            <line x1="21" y1="12" x2="9" y2="12"></line>
          </svg>
          Logout
        </a>
      </div>
    </div>
  </div>
</header>
