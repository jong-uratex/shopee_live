<section class="panel">
  <div class="panel-header">
    <div>
      <h1 class="panel-title">Welcome back, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>!</h1>
      <p class="panel-subtitle">Your dashboard gives you a quick view of site activity and admin tools.</p>
    </div>
  </div>

  <div class="dashboard-grid">
    <article class="stat-card">
      <h3>1,248</h3>
      <p>Active Users</p>
    </article>
    <article class="stat-card">
      <h3>78</h3>
      <p>New Orders</p>
    </article>
    <article class="stat-card">
      <h3>24</h3>
      <p>Pending Reviews</p>
    </article>
    <article class="stat-card">
      <h3>4</h3>
      <p>System Alerts</p>
    </article>
  </div>
</section>
