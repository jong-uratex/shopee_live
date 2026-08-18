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
 * Shopee expects a signature built as:
 * HMAC-SHA256(partner_key, partner_id + path + timestamp)
 */
function generateSignature($partnerId, $path, $timestamp, $partnerKey)
{
    $baseString = (string) $partnerId . $path . (string) $timestamp;
    return hash_hmac('sha256', $baseString, $partnerKey);
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
    $timestamp = time();
    $sign = generateSignature($partnerId, $path, $timestamp, $partnerKey);

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
$timestamp = time();
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
