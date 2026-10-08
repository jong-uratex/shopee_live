<?php
require_once __DIR__ . '/../app/config.php';

function ordersLoadOauth(): array
{
    global $shop_id, $access_token, $refresh_token;
    $oauth = [
        'shop_id' => isset($shop_id) ? (int) $shop_id : 0,
        'access_token' => isset($access_token) ? (string) $access_token : '',
        'refresh_token' => isset($refresh_token) ? (string) $refresh_token : '',
    ];
    try {
        $pdo = pdo_connect(false);
        $stmt = $pdo->query('SELECT shop_id, access_token, refresh_token FROM shopee_oauth_tokens ORDER BY updated_at DESC LIMIT 1');
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        if ($row) {
            $oauth = [
                'shop_id' => (int) $row['shop_id'],
                'access_token' => (string) $row['access_token'],
                'refresh_token' => (string) $row['refresh_token'],
            ];
        }
    } catch (Throwable $e) {
        error_log('Failed to load Shopee OAuth record: ' . $e->getMessage());
    }
    return $oauth;
}

function ordersSaveOauth(string $accessToken, string $refreshToken, int $shopId): void
{
    try {
        $pdo = pdo_connect(false);
        $isSqlite = stripos((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'sqlite') !== false;
        if ($isSqlite) {
            $sql = 'INSERT INTO shopee_oauth_tokens (shop_id, access_token, refresh_token, expire_in, updated_at) '
                . 'VALUES (:shop_id, :access_token, :refresh_token, :expire_in, CURRENT_TIMESTAMP) '
                . 'ON CONFLICT(shop_id) DO UPDATE SET access_token = excluded.access_token, refresh_token = excluded.refresh_token, expire_in = excluded.expire_in, updated_at = CURRENT_TIMESTAMP';
        } else {
            $sql = 'INSERT INTO shopee_oauth_tokens (shop_id, access_token, refresh_token, expire_in) VALUES (:shop_id, :access_token, :refresh_token, :expire_in) '
                . 'ON DUPLICATE KEY UPDATE access_token = VALUES(access_token), refresh_token = VALUES(refresh_token), expire_in = VALUES(expire_in), updated_at = NOW()';
        }
        $pdo->prepare($sql)->execute([
            ':shop_id' => $shopId,
            ':access_token' => $accessToken,
            ':refresh_token' => $refreshToken,
            ':expire_in' => 14399,
        ]);
    } catch (Throwable $e) {
        error_log('Failed to save Shopee OAuth token: ' . $e->getMessage());
    }
}

function ordersCurl(string $url, ?array $body = null): array
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ];
    if ($body !== null) {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    }
    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $error !== '') {
        return ['success' => false, 'message' => $error ?: 'cURL request failed', 'http_code' => $httpCode, 'data' => []];
    }
    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return ['success' => false, 'message' => 'Invalid JSON response from Shopee.', 'http_code' => $httpCode, 'data' => []];
    }
    $message = trim((string) ($decoded['message'] ?? ''));
    $apiError = trim((string) ($decoded['error'] ?? ''));
    if ($message !== '' || $apiError !== '' || $httpCode >= 400) {
        return [
            'success' => false,
            'message' => $message !== '' ? $message : ($apiError !== '' ? $apiError : 'Shopee service error.'),
            'http_code' => $httpCode,
            'data' => $decoded,
        ];
    }
    return ['success' => true, 'message' => '', 'http_code' => $httpCode, 'data' => $decoded];
}

function ordersShopApiGet(string $path, string $accessToken, int $shopId, array $params): array
{
    global $partnerId, $partnerKey, $host;

    $timestamp = time();
    $sign = hash_hmac('sha256', $partnerId . $path . $timestamp . $accessToken . $shopId, $partnerKey);
    $query = array_merge([
        'partner_id' => $partnerId,
        'shop_id' => $shopId,
        'access_token' => $accessToken,
        'timestamp' => $timestamp,
        'sign' => $sign,
    ], $params);

    return ordersCurl($host . $path . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));
}

function ordersRefreshToken(string $refreshToken, int $shopId): array
{
    global $partnerId, $partnerKey, $host;

    $path = '/api/v2/auth/token/get';
    $timestamp = time();
    $sign = hash_hmac('sha256', $partnerId . $path . $timestamp, $partnerKey);
    $url = sprintf('%s%s?partner_id=%s&timestamp=%s&sign=%s', $host, $path, $partnerId, $timestamp, $sign);

    $result = ordersCurl($url, [
        'partner_id' => $partnerId,
        'refresh_token' => $refreshToken,
        'shop_id' => $shopId,
    ]);
    $data = $result['data'];
    if (empty($data['access_token'])) {
        $result['success'] = false;
        $result['message'] = $result['message'] ?: 'Token refresh returned no access token.';
        return $result;
    }
    return [
        'success' => true,
        'access_token' => (string) $data['access_token'],
        'refresh_token' => (string) ($data['refresh_token'] ?? $refreshToken),
        'shop_id' => isset($data['shop_id']) ? (int) $data['shop_id'] : $shopId,
    ];
}

// Shopee limits get_order_list to a 15-day window, so longer ranges are chunked.
function ordersFetchSnList(string $accessToken, int $shopId, int $days, string $status): array
{
    $now = time();
    $from = $now - $days * 86400;
    $sns = [];

    for ($start = $from; $start < $now; $start += 15 * 86400) {
        $end = min($start + 15 * 86400 - 1, $now);
        $cursor = '';
        do {
            $params = [
                'time_range_field' => 'create_time',
                'time_from' => $start,
                'time_to' => $end,
                'page_size' => 100,
                'cursor' => $cursor,
            ];
            if ($status !== '') {
                $params['order_status'] = $status;
            }
            $result = ordersShopApiGet('/api/v2/order/get_order_list', $accessToken, $shopId, $params);
            if (!$result['success']) {
                return $result + ['sns' => []];
            }
            $response = $result['data']['response'] ?? [];
            foreach (($response['order_list'] ?? []) as $order) {
                if (!empty($order['order_sn'])) {
                    $sns[(string) $order['order_sn']] = true;
                }
            }
            $more = !empty($response['more']);
            $cursor = (string) ($response['next_cursor'] ?? '');
        } while ($more && $cursor !== '');
    }

    return ['success' => true, 'message' => '', 'http_code' => 200, 'sns' => array_keys($sns)];
}

function ordersFetchDetails(string $accessToken, int $shopId, array $sns): array
{
    if ($sns === []) {
        return ['success' => true, 'message' => '', 'http_code' => 200, 'orders' => []];
    }
    $result = ordersShopApiGet('/api/v2/order/get_order_detail', $accessToken, $shopId, [
        'order_sn_list' => implode(',', $sns),
        'response_optional_fields' => 'buyer_username,total_amount,payment_method,item_list',
    ]);
    if (!$result['success']) {
        return $result + ['orders' => []];
    }
    $byId = [];
    foreach (($result['data']['response']['order_list'] ?? []) as $order) {
        $byId[(string) $order['order_sn']] = $order;
    }
    // Keep the newest-first order from the list call
    $ordered = [];
    foreach ($sns as $sn) {
        if (isset($byId[$sn])) {
            $ordered[] = $byId[$sn];
        }
    }
    return ['success' => true, 'message' => '', 'http_code' => 200, 'orders' => $ordered];
}

function ordersIsAuthError(array $result): bool
{
    if ($result['success']) {
        return false;
    }
    $msg = strtolower((string) $result['message']);
    return in_array((int) $result['http_code'], [401, 403], true)
        || str_contains($msg, 'expired')
        || str_contains($msg, 'access token')
        || str_contains($msg, 'invalid_access_token');
}

function ordersPageUrl(array $overrides): string
{
    global $ordersQuery;
    $q = array_merge($ordersQuery, $overrides);
    return '/jong/shopee_live/dashboard.php?' . http_build_query(['page' => 'orders'] + $q);
}

// ---------------------------------------------------------------------------
// Request handling
// ---------------------------------------------------------------------------

$allowedDays = [7, 15, 30, 60, 90];
$allowedPer = [10, 20, 50];
$allowedStatus = ['', 'UNPAID', 'READY_TO_SHIP', 'PROCESSED', 'SHIPPED', 'COMPLETED', 'IN_CANCEL', 'CANCELLED'];

$days = (int) ($_GET['days'] ?? 15);
if (!in_array($days, $allowedDays, true)) $days = 15;
$perPage = (int) ($_GET['per'] ?? 20);
if (!in_array($perPage, $allowedPer, true)) $perPage = 20;
$statusFilter = (string) ($_GET['status'] ?? '');
if (!in_array($statusFilter, $allowedStatus, true)) $statusFilter = '';
$currentPage = max(1, (int) ($_GET['p'] ?? 1));

$ordersQuery = ['days' => $days, 'per' => $perPage, 'status' => $statusFilter];

$oauth = ordersLoadOauth();
$shopId = $oauth['shop_id'];
$accessToken = $oauth['access_token'];
$refreshToken = $oauth['refresh_token'];

$result = ['success' => false, 'message' => 'Shopee shop is not connected.', 'http_code' => 0];
$orders = [];
$totalOrders = 0;
$totalPages = 1;

if ($shopId > 0 && $accessToken !== '') {
    $cacheKey = $shopId . '|' . $days . '|' . $statusFilter;
    $cache = $_SESSION['orders_sn_cache'] ?? null;
    $useCache = is_array($cache) && ($cache['key'] ?? '') === $cacheKey && (time() - (int) ($cache['at'] ?? 0)) < 60;

    $load = static function () use (&$accessToken, &$refreshToken, &$shopId, $days, $statusFilter, &$useCache, $cache, &$currentPage, &$perPage, &$totalOrders, &$totalPages) {
        if ($useCache) {
            $list = ['success' => true, 'sns' => $cache['sns']];
        } else {
            $list = ordersFetchSnList($accessToken, $shopId, $days, $statusFilter);
        }
        if (!$list['success']) {
            return $list + ['orders' => []];
        }
        $sns = $list['sns'];
        $totalOrders = count($sns);
        $totalPages = max(1, (int) ceil($totalOrders / $perPage));
        $currentPage = min($currentPage, $totalPages);
        $pageSns = array_slice($sns, ($currentPage - 1) * $perPage, $perPage);
        $details = ordersFetchDetails($accessToken, $shopId, $pageSns);
        $details['sns'] = $sns;
        return $details;
    };

    $result = $load();
    if (ordersIsAuthError($result) && $refreshToken !== '') {
        $refreshed = ordersRefreshToken($refreshToken, $shopId);
        if ($refreshed['success']) {
            $accessToken = $refreshed['access_token'];
            $refreshToken = $refreshed['refresh_token'];
            $shopId = $refreshed['shop_id'];
            ordersSaveOauth($accessToken, $refreshToken, $shopId);
            $useCache = false;
            $result = $load();
        } else {
            $result['message'] = $refreshed['message'];
        }
    }

    if ($result['success']) {
        $orders = $result['orders'];
        $_SESSION['orders_sn_cache'] = ['key' => $cacheKey, 'at' => time(), 'sns' => $result['sns']];
    }
}

$statusBadge = static function (string $status): string {
    return match ($status) {
        'COMPLETED' => 'success',
        'CANCELLED', 'IN_CANCEL' => 'danger',
        default => 'warning',
    };
};

// Windowed page list: 1 … 4 5 [6] 7 8 … 20
$pageItems = [];
$window = 2;
for ($i = 1; $i <= $totalPages; $i++) {
    if ($i === 1 || $i === $totalPages || abs($i - $currentPage) <= $window) {
        $pageItems[] = $i;
    } elseif (end($pageItems) !== '…') {
        $pageItems[] = '…';
    }
}
$firstShown = $totalOrders === 0 ? 0 : ($currentPage - 1) * $perPage + 1;
$lastShown = min($currentPage * $perPage, $totalOrders);
?>

<style>
  .orders-toolbar { display: flex; flex-wrap: wrap; gap: 12px; align-items: end; margin: 0 0 16px; }
  .orders-toolbar label { display: flex; flex-direction: column; font-size: 12px; color: #64748b; gap: 4px; }
  .orders-toolbar select { padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; }
  .pager { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; justify-content: space-between; margin-top: 16px; }
  .pager-links { display: flex; flex-wrap: wrap; gap: 4px; }
  .pager-links a, .pager-links span { min-width: 36px; padding: 8px 10px; text-align: center; border: 1px solid #cbd5e1; border-radius: 8px; color: #1e293b; text-decoration: none; background: #fff; }
  .pager-links a:hover { background: #eff6ff; border-color: #93c5fd; }
  .pager-links .current { background: #2563eb; border-color: #2563eb; color: #fff; font-weight: 600; }
  .pager-links .disabled { color: #94a3b8; background: #f8fafc; }
  .pager-links .gap { border-color: transparent; background: transparent; }
  .pager-info { font-size: 13px; color: #64748b; }
</style>

<section class="panel">
  <div class="panel-header">
    <div>
      <h2 class="panel-title">Orders</h2>
      <p class="panel-subtitle">Shopee orders for shop ID <?php echo htmlspecialchars((string) $shopId); ?></p>
    </div>
  </div>

  <form method="get" class="orders-toolbar">
    <input type="hidden" name="page" value="orders">
    <label>Created in
      <select name="days" onchange="this.form.submit()">
        <?php foreach ($allowedDays as $d): ?>
          <option value="<?php echo $d; ?>" <?php echo $d === $days ? 'selected' : ''; ?>>Last <?php echo $d; ?> days</option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Status
      <select name="status" onchange="this.form.submit()">
        <?php foreach ($allowedStatus as $s): ?>
          <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $s === $statusFilter ? 'selected' : ''; ?>>
            <?php echo $s === '' ? 'All statuses' : htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', $s)))); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Rows per page
      <select name="per" onchange="this.form.submit()">
        <?php foreach ($allowedPer as $n): ?>
          <option value="<?php echo $n; ?>" <?php echo $n === $perPage ? 'selected' : ''; ?>><?php echo $n; ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <noscript><button type="submit">Apply</button></noscript>
  </form>

  <?php if (!$result['success']): ?>
    <div class="panel-warning">
      <strong>Unable to load orders.</strong>
      <p><?php echo htmlspecialchars((string) $result['message']); ?></p>
      <?php if (!empty($result['http_code'])): ?>
        <small>HTTP status: <?php echo (int) $result['http_code']; ?></small>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="table-wrapper">
      <table class="table-basic">
        <thead>
          <tr>
            <th>Order No.</th>
            <th>Date</th>
            <th>Buyer</th>
            <th>Items</th>
            <th>Payment</th>
            <th>Status</th>
            <th>Total</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($orders)): ?>
            <tr><td colspan="7">No orders found for the selected filters.</td></tr>
          <?php else: ?>
            <?php foreach ($orders as $order): ?>
              <?php
                $status = (string) ($order['order_status'] ?? 'UNKNOWN');
                $qty = 0;
                foreach (($order['item_list'] ?? []) as $it) {
                    $qty += (int) ($it['model_quantity_purchased'] ?? 0);
                }
                $total = is_numeric($order['total_amount'] ?? null) ? number_format((float) $order['total_amount'], 2) : '-';
              ?>
              <tr>
                <td><?php echo htmlspecialchars((string) ($order['order_sn'] ?? '-')); ?></td>
                <td><?php echo isset($order['create_time']) ? htmlspecialchars(date('Y-m-d H:i', (int) $order['create_time'])) : '-'; ?></td>
                <td><?php echo htmlspecialchars((string) ($order['buyer_username'] ?? '-')); ?></td>
                <td><?php echo $qty; ?></td>
                <td><?php echo htmlspecialchars((string) ($order['payment_method'] ?? '-')); ?></td>
                <td><span class="badge <?php echo $statusBadge($status); ?>"><?php echo htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', $status)))); ?></span></td>
                <td><?php echo htmlspecialchars((string) ($order['currency'] ?? 'PHP') . ' ' . $total); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <nav class="pager" aria-label="Orders pagination">
      <div class="pager-info">
        Showing <?php echo $firstShown; ?>–<?php echo $lastShown; ?> of <?php echo $totalOrders; ?> orders
      </div>
      <?php if ($totalPages > 1): ?>
        <div class="pager-links">
          <?php if ($currentPage > 1): ?>
            <a href="<?php echo htmlspecialchars(ordersPageUrl(['p' => $currentPage - 1])); ?>" rel="prev">‹ Prev</a>
          <?php else: ?>
            <span class="disabled">‹ Prev</span>
          <?php endif; ?>

          <?php foreach ($pageItems as $item): ?>
            <?php if ($item === '…'): ?>
              <span class="gap">…</span>
            <?php elseif ($item === $currentPage): ?>
              <span class="current" aria-current="page"><?php echo $item; ?></span>
            <?php else: ?>
              <a href="<?php echo htmlspecialchars(ordersPageUrl(['p' => $item])); ?>"><?php echo $item; ?></a>
            <?php endif; ?>
          <?php endforeach; ?>

          <?php if ($currentPage < $totalPages): ?>
            <a href="<?php echo htmlspecialchars(ordersPageUrl(['p' => $currentPage + 1])); ?>" rel="next">Next ›</a>
          <?php else: ?>
            <span class="disabled">Next ›</span>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
</section>
