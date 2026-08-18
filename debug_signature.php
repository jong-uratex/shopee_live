<?php
require_once __DIR__ . '/app/config.php';

/**
 * Debug helper to test Shopee signature generation
 * Run this to verify your signature matches Shopee's expectations
 */

function generateSignature($partnerId, $path, $timestamp, $partnerKey)
{
    // Convert to milliseconds if needed (if timestamp looks like seconds)
    if ($timestamp < 10000000000) {
        $timestamp = $timestamp * 1000;
    }
    $baseString = (string) $partnerId . $path . (string) $timestamp;
    return hash_hmac('sha256', $baseString, $partnerKey);
}

$path = '/api/v2/shop/auth_partner';
$timestamp = time();
$sign = generateSignature($partnerId, $path, $timestamp, $partnerKey);

echo "<h2>Shopee Signature Debug</h2>";
echo "<pre>";
echo "Partner ID: " . htmlspecialchars($partnerId) . "\n";
echo "Partner Key: " . htmlspecialchars(substr($partnerKey, 0, 10)) . "...\n";
echo "Path: " . htmlspecialchars($path) . "\n";
echo "Timestamp: " . $timestamp . "\n";
echo "Base String: " . htmlspecialchars((string)$partnerId . $path . (string)$timestamp) . "\n";
echo "Generated Signature: " . htmlspecialchars($sign) . "\n";
echo "</pre>";

$authUrl = sprintf(
    '%s%s?partner_id=%s&timestamp=%s&sign=%s&redirect=%s',
    $host,
    $path,
    $partnerId,
    $timestamp,
    $sign,
    rawurlencode($redirectUrl)
);

echo "<p><a href='" . htmlspecialchars($authUrl) . "' target='_blank'>Test Auth URL</a></p>";
echo "<pre style='word-wrap: break-word;'>" . htmlspecialchars($authUrl) . "</pre>";
