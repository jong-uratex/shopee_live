<?php
require_once __DIR__ . '/app/config.php';

header('Content-Type: application/json; charset=utf-8');

function generateSignature($partnerId, $path, $timestamp, $partnerKey)
{
    // Convert to milliseconds if needed (if timestamp looks like seconds)
    if ($timestamp < 10000000000) {
        $timestamp = $timestamp * 1000;
    }
    $baseString = (string) $partnerId . $path . (string) $timestamp;
    return hash_hmac('sha256', $baseString, $partnerKey);
}

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

$code = isset($_GET['code']) ? trim((string) $_GET['code']) : '';
$shopId = isset($_GET['shop_id']) && $_GET['shop_id'] !== '' ? (int) $_GET['shop_id'] : null;
$mainAccountId = isset($_GET['main_account_id']) && $_GET['main_account_id'] !== '' ? (int) $_GET['main_account_id'] : null;

if ($code === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing authorization code.',
        'query' => $_GET,
    ]);
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

$payload = [
    'code' => $code,
    'partner_id' => $partnerId,
];

if ($shopId !== null) {
    $payload['shop_id'] = $shopId;
} elseif ($mainAccountId !== null) {
    $payload['main_account_id'] = $mainAccountId;
}

$result = postJson($url, $payload);

if ($result['success']) {
    echo $result['response'];
    exit;
}

http_response_code($result['http_code'] ?: 500);
echo json_encode([
    'success' => false,
    'error' => $result['error'],
    'http_code' => $result['http_code'],
]);
