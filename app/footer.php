<footer class="footer-bar">
  <div>
    <small>© 2026 by Jenor Ricafort</small>
  </div>
  <div class="footer-status">
    <span class="status-pill tooltip-trigger" data-tooltip="Database Connection Status&#10;This shows whether the application can successfully connect to the MySQL or SQLite database. A 'Connected' status indicates the database is accessible and ready for operations.">
      DB: <?php echo htmlspecialchars(pdo_connection_status()); ?>
    </span>
    <span class="status-pill tooltip-trigger" data-tooltip="Network Connectivity Status&#10;Indicates whether your system has an active internet connection by attempting to connect to Google's DNS (8.8.8.8). 'Online' means your device can reach external networks.">
      Network: <?php echo htmlspecialchars(get_network_status()); ?>
    </span>
    <span class="status-pill tooltip-trigger" data-tooltip="Current User Location&#10;Displays your current IP address or 'Localhost' if you're accessing from the same machine where the application is running.">
      Location: <?php echo htmlspecialchars(get_current_location()); ?>
    </span>
    <?php 
      $shopee_stats = get_shopee_api_stats();
      echo '<span class="status-pill tooltip-trigger" data-tooltip="Shopee Partner API Connection&#10;Shows if the application can reach Shopee\'s partner API servers. \'Connected\' means the API endpoint is accessible and ready to process requests.">Shopee API: ' . htmlspecialchars($shopee_stats['status']) . '</span>';
      echo '<span class="status-pill tooltip-trigger" data-tooltip="OAuth Token Status&#10;\'Active\' indicates you have a valid Shopee OAuth access token stored. \'Inactive\' means you need to authenticate with Shopee first. Without an active token, API calls cannot be made.">Token: ' . htmlspecialchars($shopee_stats['token_status']) . '</span>';
      echo '<span class="status-pill tooltip-trigger" data-tooltip="Shopee Shop Identifier&#10;Displays the unique Shop ID associated with your Shopee account. This ID is required for all Shopee API operations. \'N/A\' means authentication is pending.">Shop: ' . htmlspecialchars($shopee_stats['shop_id']) . '</span>';
    ?>
  </div>
</footer>
