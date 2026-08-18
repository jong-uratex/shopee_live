<?php
require_once __DIR__ . '/../app/config.php';

function loadShopeeOauthFromDb(): ?array
{
    try {
        $pdo = pdo_connect(false);
        $stmt = $pdo->query(
            'SELECT shop_id, access_token, refresh_token, expire_in FROM shopee_oauth_tokens ORDER BY updated_at DESC LIMIT 1'
        );
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        if (!$row) {
            return null;
        }

        return [
            'shop_id' => (int) ($row['shop_id'] ?? 0),
            'access_token' => (string) ($row['access_token'] ?? ''),
            'refresh_token' => (string) ($row['refresh_token'] ?? ''),
            'expire_in' => (int) ($row['expire_in'] ?? 0),
        ];
    } catch (Throwable $e) {
        error_log('Failed to load Shopee OAuth record: ' . $e->getMessage());
        return null;
    }
}

function saveShopeeOauthToDb(string $newAccessToken, string $newRefreshToken, int $shopId, int $expireIn): void
{
    try {
        $pdo = pdo_connect(false);
        $isSqlite = stripos((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'sqlite') !== false;

        if ($isSqlite) {
            $stmt = $pdo->prepare(
                'INSERT INTO shopee_oauth_tokens (shop_id, access_token, refresh_token, expire_in, updated_at) '
                . 'VALUES (:shop_id, :access_token, :refresh_token, :expire_in, CURRENT_TIMESTAMP) '
                . 'ON CONFLICT(shop_id) DO UPDATE SET access_token = excluded.access_token, refresh_token = excluded.refresh_token, expire_in = excluded.expire_in, updated_at = CURRENT_TIMESTAMP'
            );
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO shopee_oauth_tokens (shop_id, access_token, refresh_token, expire_in) VALUES (:shop_id, :access_token, :refresh_token, :expire_in) '
                . 'ON DUPLICATE KEY UPDATE access_token = VALUES(access_token), refresh_token = VALUES(refresh_token), expire_in = VALUES(expire_in), updated_at = NOW()'
            );
        }

        $stmt->execute([
            ':shop_id' => $shopId,
            ':access_token' => $newAccessToken,
            ':refresh_token' => $newRefreshToken,
            ':expire_in' => $expireIn,
        ]);
    } catch (Throwable $e) {
        error_log('Failed to save Shopee OAuth token: ' . $e->getMessage());
    }
}

function refreshShopeeAccessToken(string $refreshToken, int $shopId): array
{
    global $partnerId, $partnerKey, $host;

    $path = '/api/v2/auth/token/get';
    $timestamp = (int) time();
    $sign = hash_hmac('sha256', (string) $partnerId . $path . (string) $timestamp, $partnerKey);

    $url = sprintf(
        '%s%s?partner_id=%s&timestamp=%s&sign=%s',
        $host,
        $path,
        $partnerId,
        $timestamp,
        $sign
    );

    $payload = [
        'partner_id' => $partnerId,
        'refresh_token' => $refreshToken,
        'shop_id' => $shopId,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $error !== '') {
        return [
            'success' => false,
            'message' => $error ?: 'Failed to refresh Shopee token.',
            'http_code' => $httpCode,
            'access_token' => '',
            'refresh_token' => '',
            'shop_id' => $shopId,
        ];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return [
            'success' => false,
            'message' => 'Invalid JSON response from Shopee refresh endpoint.',
            'http_code' => $httpCode,
            'access_token' => '',
            'refresh_token' => '',
            'shop_id' => $shopId,
        ];
    }

    $accessToken = (string) ($decoded['access_token'] ?? '');
    $newRefreshToken = (string) ($decoded['refresh_token'] ?? $refreshToken);
    $newShopId = isset($decoded['shop_id']) ? (int) $decoded['shop_id'] : $shopId;

    if ($accessToken === '') {
        return [
            'success' => false,
            'message' => $decoded['message'] ?? $decoded['error'] ?? 'Shopee token refresh returned no access token.',
            'http_code' => $httpCode,
            'access_token' => '',
            'refresh_token' => '',
            'shop_id' => $shopId,
        ];
    }

    return [
        'success' => true,
        'message' => 'Shopee access token refreshed successfully.',
        'http_code' => $httpCode,
        'access_token' => $accessToken,
        'refresh_token' => $newRefreshToken,
        'shop_id' => $newShopId,
    ];
}

function fetchShopeeProducts(string $accessToken, int $shopId): array
{
    global $partnerId, $partnerKey, $host;

    $path = '/api/v2/product/get_item_list';
    $timestamp = (int) time();
    $sign = hash_hmac('sha256', (string) $partnerId . $path . (string) $timestamp, $partnerKey);
    $endpoint = sprintf(
        '%s%s?partner_id=%s&shop_id=%s&access_token=%s&timestamp=%s&sign=%s',
        $host,
        $path,
        $partnerId,
        $shopId,
        rawurlencode($accessToken),
        $timestamp,
        $sign
    );

    $payload = [
        'page_no' => 1,
        'page_size' => 20,
    ];

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $error !== '') {
        return [
            'success' => false,
            'message' => $error ?: 'cURL request failed',
            'http_code' => $httpCode,
            'items' => [],
        ];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return [
            'success' => false,
            'message' => 'Invalid JSON response from Shopee.',
            'http_code' => $httpCode,
            'items' => [],
        ];
    }

    $items = [];
    if (isset($decoded['data']['item_list']) && is_array($decoded['data']['item_list'])) {
        $items = $decoded['data']['item_list'];
    } elseif (isset($decoded['item_list']) && is_array($decoded['item_list'])) {
        $items = $decoded['item_list'];
    }

    $errorMessage = $decoded['message'] ?? $decoded['error'] ?? null;
    if ($errorMessage !== null || ($httpCode >= 400 && $httpCode < 600)) {
        return [
            'success' => false,
            'message' => (string) $errorMessage ?: 'Shopee service error.',
            'http_code' => $httpCode,
            'items' => $items,
        ];
    }

    return [
        'success' => true,
        'message' => 'Products loaded.',
        'http_code' => $httpCode,
        'items' => $items,
    ];
}

$dbOauth = loadShopeeOauthFromDb();
$shopId = $dbOauth['shop_id'] ?? (isset($shop_id) ? (int) $shop_id : 0);
$accessToken = $dbOauth['access_token'] ?? (isset($access_token) ? (string) $access_token : '');
$refreshToken = $dbOauth['refresh_token'] ?? (isset($refresh_token) ? (string) $refresh_token : '');
$productsResult = [
    'success' => false,
    'message' => 'Missing Shopee access token or shop_id.',
    'http_code' => 0,
    'items' => [],
];

if ($shopId > 0 && $accessToken !== '') {
    $productsResult = fetchShopeeProducts($accessToken, $shopId);

    $tokenExpired = false;
    $messageLower = strtolower((string) ($productsResult['message'] ?? ''));
    if (
        $productsResult['http_code'] === 401 ||
        $productsResult['http_code'] === 403 ||
        str_contains($messageLower, 'expired') ||
        str_contains($messageLower, 'invalid access token') ||
        str_contains($messageLower, 'token is invalid') ||
        str_contains($messageLower, 'access token')
    ) {
        $tokenExpired = true;
    }

    if ($tokenExpired && $refreshToken !== '') {
        $refreshResult = refreshShopeeAccessToken($refreshToken, $shopId);
        if ($refreshResult['success']) {
            $accessToken = $refreshResult['access_token'];
            $refreshToken = $refreshResult['refresh_token'];
            $shopId = $refreshResult['shop_id'];
            saveShopeeOauthToDb($accessToken, $refreshToken, $shopId, 14399);
            $productsResult = fetchShopeeProducts($accessToken, $shopId);
        } else {
            $productsResult['message'] = $refreshResult['message'];
            $productsResult['http_code'] = $refreshResult['http_code'];
        }
    }
}
?>

<section class="panel">
  <div class="panel-header">
    <div>
      <h2 class="panel-title">Products</h2>
      <p class="panel-subtitle">Shopee product list for shop ID <?php echo htmlspecialchars((string) $shopId); ?></p>
    </div>
  </div>

  <?php if (!$productsResult['success']): ?>
    <div class="panel-warning">
      <strong>Unable to load products.</strong>
      <p><?php echo htmlspecialchars($productsResult['message']); ?></p>
      <?php if ($productsResult['http_code']): ?>
        <small>HTTP status: <?php echo (int) $productsResult['http_code']; ?></small>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="table-wrapper">
      <table class="table-basic">
        <thead>
          <tr>
            <th>Product</th>
            <th>SKU</th>
            <th>Category</th>
            <th>Status</th>
            <th>Price</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($productsResult['items'])): ?>
            <tr>
              <td colspan="5">No products returned for this shop.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($productsResult['items'] as $item): ?>
              <?php
                $name = $item['name'] ?? $item['item_name'] ?? 'Unnamed Product';
                $sku = $item['item_sku'] ?? $item['sku'] ?? '-';
                $category = $item['category_name'] ?? $item['category'] ?? '-';
                $status = $item['status'] ?? 'Unknown';
                $price = isset($item['price']) ? number_format((float) $item['price'] / 100000, 2, '.', ',') : '-';
              ?>
              <tr>
                <td><?php echo htmlspecialchars((string) $name); ?></td>
                <td><?php echo htmlspecialchars((string) $sku); ?></td>
                <td><?php echo htmlspecialchars((string) $category); ?></td>
                <td><span class="badge success"><?php echo htmlspecialchars((string) $status); ?></span></td>
                <td>$<?php echo htmlspecialchars((string) $price); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
