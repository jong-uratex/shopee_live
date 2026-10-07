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

/**
 * Generic Shopee Shop API caller.
 * Supports both GET and POST.
 * - GET  → parameters go into the query string
 * - POST → parameters go into the JSON body
 */
function requestShopeeShopApi(
    string $path,
    string $accessToken,
    int $shopId,
    array $payload = [],
    string $method = 'POST'
): array {
    global $partnerId, $partnerKey, $host;

    $timestamp = (int) time();
    $sign = hash_hmac(
        'sha256',
        (string) $partnerId . $path . (string) $timestamp . $accessToken . (string) $shopId,
        $partnerKey
    );

    $commonParams = [
        'partner_id'   => $partnerId,
        'shop_id'      => $shopId,
        'access_token' => $accessToken,
        'timestamp'    => $timestamp,
        'sign'         => $sign,
    ];

    $method = strtoupper($method);

    if ($method === 'GET') {
        // Build query string carefully so arrays become repeated keys
        // (required by Shopee for item_status, item_id_list, etc.)
        $queryParts = [];

        foreach ($commonParams as $key => $value) {
            $queryParts[] = rawurlencode($key) . '=' . rawurlencode((string) $value);
        }

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $item) {
                    $queryParts[] = rawurlencode($key) . '=' . rawurlencode((string) $item);
                }
            } else {
                $queryParts[] = rawurlencode($key) . '=' . rawurlencode((string) $value);
            }
        }

        $endpoint = $host . $path . '?' . implode('&', $queryParts);
    } else {
        // POST – common params in query, business payload in body
        $endpoint = $host . $path . '?' . http_build_query($commonParams, '', '&', PHP_QUERY_RFC3986);
    }

    $ch = curl_init($endpoint);

    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ];

    if ($method === 'POST') {
        $opts[CURLOPT_POST]       = true;
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload);
    } else {
        $opts[CURLOPT_HTTPGET] = true;
    }

    curl_setopt_array($ch, $opts);

    $response = curl_exec($ch);
    $error    = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $error !== '') {
        return [
            'success'   => false,
            'message'   => $error ?: 'cURL request failed',
            'http_code' => $httpCode,
            'data'      => [],
        ];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return [
            'success'   => false,
            'message'   => 'Invalid JSON response from Shopee.',
            'http_code' => $httpCode,
            'data'      => [],
        ];
    }

    $errorMessage = trim((string) ($decoded['message'] ?? ''));
    $apiError     = trim((string) ($decoded['error'] ?? ''));

    if ($errorMessage !== '' || $apiError !== '' || $httpCode >= 400) {
        return [
            'success'   => false,
            'message'   => $errorMessage !== '' ? $errorMessage : ($apiError !== '' ? $apiError : 'Shopee service error.'),
            'http_code' => $httpCode,
            'data'      => $decoded,
        ];
    }

    return [
        'success'   => true,
        'message'   => '',
        'http_code' => $httpCode,
        'data'      => $decoded,
    ];
}

function fetchShopeeProducts(string $accessToken, int $shopId): array
{
    $path       = '/api/v2/product/get_item_list';
    $pageSize   = 100;
    $offset     = 0;
    $itemIds    = [];
    $itemStatuses = ['NORMAL', 'UNLIST', 'BANNED', 'REVIEWING'];

    do {
        // IMPORTANT: this endpoint is GET
        $listResult = requestShopeeShopApi($path, $accessToken, $shopId, [
            'item_status' => $itemStatuses,
            'offset'      => $offset,
            'page_size'   => $pageSize,
        ], 'GET');

        if (!$listResult['success']) {
            return $listResult + ['items' => []];
        }

        $responseData = $listResult['data']['response'] ?? [];
        $pageItems    = $responseData['item'] ?? $responseData['item_list'] ?? [];
        if (!is_array($pageItems)) {
            $pageItems = [];
        }

        foreach ($pageItems as $item) {
            if (isset($item['item_id'])) {
                $itemIds[(string) $item['item_id']] = [
                    'item_id' => (int) $item['item_id'],
                    'status'  => (string) ($item['item_status'] ?? 'Unknown'),
                ];
            }
        }

        // Prefer official next_offset when available
        if (isset($responseData['next_offset'])) {
            $offset = (int) $responseData['next_offset'];
        } else {
            $offset += count($pageItems);
        }

        $hasNextPage = isset($responseData['has_next_page'])
            ? (bool) $responseData['has_next_page']
            : count($pageItems) === $pageSize;

    } while ($hasNextPage && count($pageItems) > 0);

    if ($itemIds === []) {
        return [
            'success'   => true,
            'message'   => 'No products returned for this shop.',
            'http_code' => 200,
            'items'     => [],
        ];
    }

    $items = [];
    foreach (array_chunk(array_values($itemIds), 50) as $itemBatch) {
        // IMPORTANT: this endpoint is also GET
        $detailResult = requestShopeeShopApi(
            '/api/v2/product/get_item_base_info',
            $accessToken,
            $shopId,
            ['item_id_list' => array_column($itemBatch, 'item_id')],
            'GET'
        );

        if (!$detailResult['success']) {
            return $detailResult + ['items' => []];
        }

        $detailItems = $detailResult['data']['response']['item_list']
                    ?? $detailResult['data']['response']['item']
                    ?? [];

        if (!is_array($detailItems)) {
            continue;
        }

        foreach ($detailItems as $item) {
            $itemId = (string) ($item['item_id'] ?? '');
            $categoryNames = array_filter(array_map(
                static fn(array $category): string => (string) ($category['display_category_name'] ?? $category['original_category_name'] ?? ''),
                is_array($item['category_list'] ?? null) ? $item['category_list'] : []
            ));
            $priceInfo = $item['price_info'][0] ?? [];

            $items[] = [
                'name'     => $item['item_name'] ?? 'Unnamed Product',
                'sku'      => $item['item_sku'] ?? '-',
                'category' => $categoryNames !== [] ? implode(' / ', $categoryNames) : ($item['category_id'] ?? '-'),
                'status'   => $item['item_status'] ?? ($itemIds[$itemId]['status'] ?? 'Unknown'),
                'price'    => $priceInfo['current_price'] ?? null,
                'currency' => $priceInfo['currency'] ?? 'PHP',
            ];
        }
    }

    return [
        'success'   => true,
        'message'   => 'Products loaded.',
        'http_code' => 200,
        'items'     => $items,
    ];
}

// ---------------------------------------------------------------------------
// Main execution
// ---------------------------------------------------------------------------

$dbOauth      = loadShopeeOauthFromDb();
$shopId       = $dbOauth['shop_id'] ?? (isset($shop_id) ? (int) $shop_id : 0);
$accessToken  = $dbOauth['access_token'] ?? (isset($access_token) ? (string) $access_token : '');
$refreshToken = $dbOauth['refresh_token'] ?? (isset($refresh_token) ? (string) $refresh_token : '');

$productsResult = [
    'success'   => false,
    'message'   => 'Missing Shopee access token or shop_id.',
    'http_code' => 0,
    'items'     => [],
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
            $accessToken  = $refreshResult['access_token'];
            $refreshToken = $refreshResult['refresh_token'];
            $shopId       = $refreshResult['shop_id'];

            saveShopeeOauthToDb($accessToken, $refreshToken, $shopId, 14399);
            $productsResult = fetchShopeeProducts($accessToken, $shopId);
        } else {
            $productsResult['message']   = $refreshResult['message'];
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
                $name     = $item['name'] ?? 'Unnamed Product';
                $sku      = $item['sku'] ?? '-';
                $category = $item['category'] ?? '-';
                $status   = $item['status'] ?? 'Unknown';
                $price    = is_numeric($item['price'] ?? null) ? number_format((float) $item['price'], 2, '.', ',') : '-';
                $currency = $item['currency'] ?? 'PHP';
              ?>
              <tr>
                <td><?php echo htmlspecialchars((string) $name); ?></td>
                <td><?php echo htmlspecialchars((string) $sku); ?></td>
                <td><?php echo htmlspecialchars((string) $category); ?></td>
                <td><span class="badge success"><?php echo htmlspecialchars((string) $status); ?></span></td>
                <td><?php echo htmlspecialchars((string) $currency . ' ' . $price); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>