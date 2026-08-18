<?php
require_once __DIR__ . '/app/config.php';

/**
 * Shopee Partner OAuth v2 – Live Auth Entrypoint
 *
 * Usage:
 * 1. Visit this file in the browser → redirects to Shopee authorization page.
 * 2. After seller authorizes, Shopee redirects back with ?code=...&shop_id=... (or main_account_id).
 * 3. This script exchanges the code for access_token + refresh_token and returns the raw JSON.
 *
 * Debug:
 *   ?debug=1              → show signature details for auth link
 *   ?debug_exchange=1     → show signature details during token exchange (when code is present)
 */

$partnerId   = isset($partnerId)   ? (int) $partnerId   : 0;
$partnerKey  = isset($partnerKey)  ? (string) $partnerKey  : '';
$host        = isset($host)        ? (string) $host        : 'https://partner.shopeemobile.com';
$redirectUrl = isset($redirectUrl) ? (string) $redirectUrl : 'http://dev.uratex.com.ph/jong/shopee_live/callback.php';

/**
 * Current Unix timestamp in **seconds** (required by Shopee).
 * Valid for 5 minutes only.
 */
function currentTimestamp(): int
{
    return time();
}

/**
 * Generate HMAC-SHA256 signature.
 * Base string = partner_id + path + timestamp (seconds)
 */
function generateSignature(int $partnerId, string $path, int $timestamp, string $partnerKey): string
{
    // Safety: if someone accidentally passes milliseconds, convert to seconds
    if ($timestamp > 9999999999) {
        $timestamp = (int) floor($timestamp / 1000);
    }

    $baseString = (string) $partnerId . $path . (string) $timestamp;
    return hash_hmac('sha256', $baseString, $partnerKey);
}

/**
 * Pretty debug page
 */
function renderDebugInfo(
    string $title,
    string $path,
    int $timestamp,
    string $sign,
    string $authUrl,
    array $extra = []
): void {
    $baseString = (string) $GLOBALS['partnerId'] . $path . (string) $timestamp;

    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . htmlspecialchars($title) . '</title>';
    echo '<style>
        body{font-family:Arial,sans-serif;padding:24px;background:#111;color:#eee;}
        pre{background:#1a1a1a;padding:16px;border-radius:8px;overflow:auto;}
        a{color:#66b3ff;}
        .label{font-weight:bold;color:#fff;}
        .value{word-break:break-all;}
    </style></head><body>';
    echo '<h2>' . htmlspecialchars($title) . '</h2>';
    echo '<p><span class="label">Partner ID:</span> <span class="value">' . htmlspecialchars((string) $GLOBALS['partnerId']) . '</span></p>';
    echo '<p><span class="label">Path:</span> <span class="value">' . htmlspecialchars($path) . '</span></p>';
    echo '<p><span class="label">Timestamp (seconds):</span> <span class="value">' . htmlspecialchars((string) $timestamp) . '</span></p>';
    echo '<p><span class="label">Base String:</span> <span class="value">' . htmlspecialchars($baseString) . '</span></p>';
    echo '<p><span class="label">Generated Signature:</span> <span class="value">' . htmlspecialchars($sign) . '</span></p>';
    echo '<p><span class="label">Auth / Request URL:</span></p><pre>' . htmlspecialchars($authUrl) . '</pre>';

    if ($extra !== []) {
        echo '<h3>Additional Info</h3><pre>' . htmlspecialchars(json_encode($extra, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre>';
    }

    echo '<p><a href="' . htmlspecialchars($authUrl) . '" target="_blank">Open URL</a></p>';
    echo '</body></html>';
    exit;
}

/**
 * Simple JSON POST helper
 */
function postJson(string $url, array $payload): array
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $response = curl_exec($ch);
    $error    = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $error !== '') {
        return [
            'success'   => false,
            'http_code' => $httpCode,
            'error'     => $error ?: 'cURL request failed',
            'response'  => null,
        ];
    }

    return [
        'success'   => true,
        'http_code' => $httpCode,
        'error'     => null,
        'response'  => $response,
    ];
}

// --------------------------------------------------------------------------
// DEBUG MODE: show signature details before redirecting
// --------------------------------------------------------------------------
if (isset($_GET['debug'])) {
    $path      = '/api/v2/shop/auth_partner';
    $timestamp = currentTimestamp();
    $sign      = generateSignature($partnerId, $path, $timestamp, $partnerKey);

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
        'redirect_url'        => $redirectUrl,
        'host'                => $host,
        'partner_key_prefix'  => substr($partnerKey, 0, 12) . '...',
        'server_time'         => date('Y-m-d H:i:s T'),
    ]);
}

// --------------------------------------------------------------------------
// STEP 1: Handle callback from Shopee (code present)
// --------------------------------------------------------------------------
if (isset($_GET['code'])) {
    $code          = trim((string) $_GET['code']);
    $shopId        = (isset($_GET['shop_id']) && $_GET['shop_id'] !== '') ? (int) $_GET['shop_id'] : null;
    $mainAccountId = (isset($_GET['main_account_id']) && $_GET['main_account_id'] !== '') ? (int) $_GET['main_account_id'] : null;

    if ($code === '') {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Missing authorization code.']);
        exit;
    }

    $path      = '/api/v2/auth/token/get';
    $timestamp = currentTimestamp();
    $sign      = generateSignature($partnerId, $path, $timestamp, $partnerKey);

    // Optional debug for the token exchange step
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
            'code'             => $code,
            'shop_id'          => $shopId,
            'main_account_id'  => $mainAccountId,
            'payload'          => [
                'code'             => $code,
                'partner_id'       => $partnerId,
                'shop_id'          => $shopId,
                'main_account_id'  => $mainAccountId,
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

    $payload = [
        'code'       => $code,
        'partner_id' => $partnerId,
    ];

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
            'success'   => false,
            'error'     => $result['error'],
            'http_code' => $result['http_code'],
        ]);
    }
    exit;
}

// --------------------------------------------------------------------------
// STEP 2: No code → generate auth link and redirect
// --------------------------------------------------------------------------
$path      = '/api/v2/shop/auth_partner';
$timestamp = currentTimestamp();
$sign      = generateSignature($partnerId, $path, $timestamp, $partnerKey);

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