<section class="panel">
  <div class="panel-header">
    <div>
      <h2 class="panel-title">Profile</h2>
      <p class="panel-subtitle">Manage your account details and change your profile information.</p>
    </div>
  </div>

  <form method="post" action="dashboard?page=profile">
    <div class="form-group">
      <label>Username</label>
      <input class="form-control" value="<?php echo htmlspecialchars($_SESSION['username'] ?? 'admin'); ?>" readonly>
    </div>
    <div class="form-group">
      <label>Email</label>
      <input class="form-control" value="admin@example.com" readonly>
    </div>
    <div class="form-group">
      <label>Display Name</label>
      <input class="form-control" name="display_name" value="Admin User" />
    </div>
    <div class="form-group">
      <label>About</label>
      <textarea class="form-textarea" rows="4">I am the administrator for Shopee Live.</textarea>
    </div>
    <button type="submit" class="btn-primary">Save Changes</button>
  </form>
</section>
