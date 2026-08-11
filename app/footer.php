<footer class="footer-bar">
  <div>
    <small>© 2026 by Jenor Ricafort</small>
  </div>
  <div class="footer-status">
    <span class="status-pill">DB: <?php echo htmlspecialchars(pdo_connection_status()); ?></span>
    <span class="status-pill">Network: <?php echo htmlspecialchars(get_network_status()); ?></span>
    <span class="status-pill">Location: <?php echo htmlspecialchars(get_current_location()); ?></span>
  </div>
</footer>
