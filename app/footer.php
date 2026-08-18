<footer class="footer-bar">
  <div>
    <small>© 2026 by Jenor Ricafort</small>
  </div>
  <div class="footer-status">
    <span class="status-pill">DB: <?php echo htmlspecialchars(pdo_connection_status()); ?></span>
    <span class="status-pill">Network: <?php echo htmlspecialchars(get_network_status()); ?></span>
    <span class="status-pill">Location: <?php echo htmlspecialchars(get_current_location()); ?></span>
    <?php 
      $shopee_stats = get_shopee_api_stats();
      echo '<span class="status-pill">Shopee API: ' . htmlspecialchars($shopee_stats['status']) . '</span>';
      echo '<span class="status-pill">Token: ' . htmlspecialchars($shopee_stats['token_status']) . '</span>';
      echo '<span class="status-pill">Shop: ' . htmlspecialchars($shopee_stats['shop_id']) . '</span>';
    ?>
  </div>
</footer>
