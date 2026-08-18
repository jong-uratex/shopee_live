<?php
require_once __DIR__ . '/app/config.php';

/**
 * Shopee Partner OAuth v2 live auth entrypoint
 *
 * Usage:
 * - Visit this file in the browser to start the Shopee authorization flow.
 * - Shopee redirects back to the configured callback URL with code, shop_id, or main_account_id.
 * - The callback exchange will request an access token from Shopee and return the raw JSON response.
 */

$partnerId = isset($partnerId) ? (int) $partnerId : 0;
$partnerKey = isset($partnerKey) ? (string) $partnerKey : '';
$host = isset($host) ? (string) $host : 'https://partner.shopeemobile.com';
$redirectUrl = isset($redirectUrl) ? (string) $redirectUrl : 'http://dev.uratex.com.ph/jong/shopee_live/callback.php';

/**
 * Generate HMAC-SHA256 signature for Shopee partner requests.
 *
 * Shopee expects the signature base string to be:
 * partner_id + path + timestamp
 * and the timestamp must be in milliseconds since Unix epoch.
 */
function currentTimestampMs()
{
    return (int) round(microtime(true) * 1000);
}

function generateSignature($partnerId, $path, $timestamp, $partnerKey)
{
    // Shopee uses 13-digit millisecond timestamps. Accept either seconds or ms.
    if ($timestamp < 100000000000) {
        $timestamp = (int) $timestamp * 1000;
    }
    $baseString = (string) $partnerId . $path . (string) $timestamp;
    return hash_hmac('sha256', $baseString, $partnerKey);
}

function renderDebugInfo(string $title, string $path, int $timestamp, string $sign, string $authUrl, array $extra = []): void
{
    $baseString = (string) $GLOBALS['partnerId'] . $path . (string) $timestamp;

    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . htmlspecialchars($title) . '</title>';
    echo '<style>body{font-family:Arial,sans-serif;padding:24px;background:#111;color:#eee;} pre{background:#1a1a1a;padding:16px;border-radius:8px;overflow:auto;} a{color:#66b3ff;} .label{font-weight:bold;color:#fff;} .value{word-break:break-all;}</style>';
    echo '</head><body>';
    echo '<h2>' . htmlspecialchars($title) . '</h2>';
    echo '<p><span class="label">Partner ID:</span> <span class="value">' . htmlspecialchars((string) $GLOBALS['partnerId']) . '</span></p>';
    echo '<p><span class="label">Path:</span> <span class="value">' . htmlspecialchars($path) . '</span></p>';
    echo '<p><span class="label">Timestamp (ms):</span> <span class="value">' . htmlspecialchars((string) $timestamp) . '</span></p>';
    echo '<p><span class="label">Base String:</span> <span class="value">' . htmlspecialchars($baseString) . '</span></p>';
    echo '<p><span class="label">Generated Signature:</span> <span class="value">' . htmlspecialchars($sign) . '</span></p>';
    echo '<p><span class="label">Auth URL:</span></p><pre>' . htmlspecialchars($authUrl) . '</pre>';

    if ($extra !== []) {
        echo '<h3>Additional Info</h3><pre>' . htmlspecialchars(json_encode($extra, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre>';
    }

    echo '<p><a href="' . htmlspecialchars($authUrl) . '" target="_blank">Open auth URL</a></p>';
    echo '</body></html>';
    exit;
}

/**
 * Make a JSON POST request to Shopee.
 */
function postJson($url, array $payload)
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 30,
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
            'http_code' => $httpCode,
            'error' => $error ?: 'cURL request failed',
            'response' => null,
        ];
    }

    return [
        'success' => true,
        'http_code' => $httpCode,
        'error' => null,
        'response' => $response,
    ];
}

// --------------------------------------------------------------------------
// Debug mode: show exact signature details before redirecting.
// --------------------------------------------------------------------------
if (isset($_GET['debug'])) {
    $path = '/api/v2/shop/auth_partner';
    $timestamp = currentTimestampMs();
    $sign = generateSignature($partnerId, $path, $timestamp, $partnerKey);
    $authUrl = sprintf(
        '%s%s?partner_id=%s&timestamp=%s&sign=%s&redirect=%s',
        $host,
        $path,
        $partnerId,
        $timestamp,
        $sign,
        rawurlencode($redirectUrl)
    );

    renderDebugInfo('Shopee Auth Debug', $path, $timestamp, $sign, $authUrl, [
        'redirect_url' => $redirectUrl,
        'host' => $host,
        'partner_key_prefix' => substr($partnerKey, 0, 12) . '...',
    ]);
}

// --------------------------------------------------------------------------
// STEP 1: Handle redirect from Shopee authorization callback.
// --------------------------------------------------------------------------
if (isset($_GET['code'])) {
    $code = trim((string) $_GET['code']);
    $shopId = isset($_GET['shop_id']) && $_GET['shop_id'] !== '' ? (int) $_GET['shop_id'] : null;
    $mainAccountId = isset($_GET['main_account_id']) && $_GET['main_account_id'] !== '' ? (int) $_GET['main_account_id'] : null;

    if ($code === '') {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Missing authorization code.']);
        exit;
    }

    $path = '/api/v2/auth/token/get';
    $timestamp = currentTimestampMs();
    $sign = generateSignature($partnerId, $path, $timestamp, $partnerKey);

    if (isset($_GET['debug_exchange'])) {
        $debugUrl = sprintf(
            '%s%s?partner_id=%s&timestamp=%s&sign=%s',
            $host,
            $path,
            $partnerId,
            $timestamp,
            $sign
        );
        renderDebugInfo('Shopee Token Exchange Debug', $path, $timestamp, $sign, $debugUrl, [
            'code' => $code,
            'shop_id' => $shopId,
            'main_account_id' => $mainAccountId,
            'payload' => [
                'code' => $code,
                'partner_id' => $partnerId,
                'shop_id' => $shopId,
                'main_account_id' => $mainAccountId,
            ],
        ]);
    }

    $url = sprintf(
        '%s%s?partner_id=%s&timestamp=%s&sign=%s',
        $host,
        $path,
        $partnerId,
        $timestamp,
        $sign
    );

    $payload = ['code' => $code, 'partner_id' => $partnerId];

    if ($shopId !== null) {
        $payload['shop_id'] = $shopId;
    } elseif ($mainAccountId !== null) {
        $payload['main_account_id'] = $mainAccountId;
    }

    $result = postJson($url, $payload);

    header('Content-Type: application/json');
    if ($result['success']) {
        echo $result['response'];
    } else {
        http_response_code($result['http_code'] ?: 500);
        echo json_encode([
            'success' => false,
            'error' => $result['error'],
            'http_code' => $result['http_code'],
        ]);
    }
    exit;
}

// --------------------------------------------------------------------------
// STEP 2: Generate authorization link if no code is present.
// --------------------------------------------------------------------------
$path = '/api/v2/shop/auth_partner';
$timestamp = currentTimestampMs();
$sign = generateSignature($partnerId, $path, $timestamp, $partnerKey);

$authUrl = sprintf(
    '%s%s?partner_id=%s&timestamp=%s&sign=%s&redirect=%s',
    $host,
    $path,
    $partnerId,
    $timestamp,
    $sign,
    rawurlencode($redirectUrl)
);

header('Location: ' . $authUrl);
exit;
