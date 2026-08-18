<?php
require_once __DIR__ . '/app/config.php';

/**
 * Shopee Partner OAuth v2 – Callback / Token Exchange
 *
 * Shopee redirects here after the seller authorizes the app.
 * This file exchanges the authorization code for access_token + refresh_token.
 */

$partnerId  = isset($partnerId)  ? (int) $partnerId  : 0;
$partnerKey = isset($partnerKey) ? (string) $partnerKey : '';
$host       = isset($host)       ? (string) $host       : 'https://partner.shopeemobile.com';

/**
 * Unix timestamp in **seconds** (Shopee requirement)
 */
function currentTimestamp(): int
{
    return time();
}

/**
 * HMAC-SHA256 signature
 * Base string = partner_id + path + timestamp
 */
function generateSignature(int $partnerId, string $path, int $timestamp, string $partnerKey): string
{
    // Safety net: convert milliseconds → seconds if needed
    if ($timestamp > 9999999999) {
        $timestamp = (int) floor($timestamp / 1000);
    }

    $baseString = (string) $partnerId . $path . (string) $timestamp;
    return hash_hmac('sha256', $baseString, $partnerKey);
}

/**
 * JSON POST helper
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
// Must have authorization code
// --------------------------------------------------------------------------
if (!isset($_GET['code']) || trim((string) $_GET['code']) === '') {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing authorization code.']);
    exit;
}

$code          = trim((string) $_GET['code']);
$shopId        = (isset($_GET['shop_id']) && $_GET['shop_id'] !== '') ? (int) $_GET['shop_id'] : null;
$mainAccountId = (isset($_GET['main_account_id']) && $_GET['main_account_id'] !== '') ? (int) $_GET['main_account_id'] : null;

// --------------------------------------------------------------------------
// Build signed request for /api/v2/auth/token/get
// --------------------------------------------------------------------------
$path      = '/api/v2/auth/token/get';
$timestamp = currentTimestamp();          // ← seconds, not milliseconds
$sign      = generateSignature($partnerId, $path, $timestamp, $partnerKey);

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

// Optional debug
if (isset($_GET['debug'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'debug' => true,
        'url'   => $url,
        'payload' => $payload,
        'timestamp' => $timestamp,
        'sign' => $sign,
        'base_string' => $partnerId . $path . $timestamp,
        'server_time' => date('Y-m-d H:i:s T'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// --------------------------------------------------------------------------
// Call Shopee
// --------------------------------------------------------------------------
$result = postJson($url, $payload);

header('Content-Type: application/json');

if ($result['success']) {
    // Success – return the raw response from Shopee
    echo $result['response'];
} else {
    http_response_code($result['http_code'] ?: 500);
    echo json_encode([
        'success'   => false,
        'error'     => $result['error'],
        'http_code' => $result['http_code'],
    ]);
}