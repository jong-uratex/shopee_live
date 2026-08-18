<?php
require_once __DIR__ . '/../app/config.php';

function persistShopeeTokenConfig(string $newAccessToken, string $newRefreshToken, int $shopId): void
{
    $configPath = __DIR__ . '/../app/config.php';
    if (!is_file($configPath)) {
        return;
    }

    $contents = file_get_contents($configPath);
    if ($contents === false) {
        return;
    }

    $contents = preg_replace(
        "/\$access_token\s*=\s*'([^']*)';/",
        "\$access_token = '" . addslashes($newAccessToken) . "';",
        $contents,
        1
    );

    $contents = preg_replace(
        "/\$refresh_token\s*=\s*'([^']*)';/",
        "\$refresh_token = '" . addslashes($newRefreshToken) . "';",
        $contents,
        1
    );

    $contents = preg_replace(
        "/\$shop_id\s*=\s*\d+;/",
        "\$shop_id = " . (int) $shopId . ";",
        $contents,
        1
    );

    file_put_contents($configPath, $contents);
}

function refreshShopeeAccessToken(string $refreshToken, int $shopId): array
{
    global $partnerId, $partnerKey, $host;

    $path = '/api/v2/auth/token/get';
    $timestamp = (int) round(microtime(true) * 1000);
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
    $endpoint = 'https://partner.shopeemobile.com/api/v2/product/get_item_list';
    $payload = [
        'access_token' => $accessToken,
        'shop_id' => $shopId,
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

$shopId = isset($shop_id) ? (int) $shop_id : 0;
$accessToken = isset($access_token) ? (string) $access_token : '';
$refreshToken = isset($refresh_token) ? (string) $refresh_token : '';
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
            persistShopeeTokenConfig($accessToken, $refreshToken, $shopId);
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
