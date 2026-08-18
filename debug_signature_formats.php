<?php
require_once __DIR__ . '/app/config.php';

/**
 * Enhanced debug tool to test multiple signature formats
 * Shopee signature validation is strict - we need to find the exact format they expect
 */

$path = '/api/v2/shop/auth_partner';
$timestamp = time();
$timestampMs = $timestamp * 1000;

echo "<h2>Shopee Signature Format Testing</h2>";
echo "<p><strong>Partner ID:</strong> " . htmlspecialchars($partnerId) . "</p>";
echo "<p><strong>Partner Key (first 20 chars):</strong> " . htmlspecialchars(substr($partnerKey, 0, 20)) . "...</p>";
echo "<p><strong>Path:</strong> " . htmlspecialchars($path) . "</p>";
echo "<p><strong>Timestamp (seconds):</strong> " . $timestamp . "</p>";
echo "<p><strong>Timestamp (milliseconds):</strong> " . $timestampMs . "</p>";
echo "<hr>";

// Test different signature formats
$tests = [
    // Format 1: partner_id + path + timestamp (seconds)
    [
        'name' => 'Format 1: partnerId + path + timestamp (seconds)',
        'baseString' => (string)$partnerId . $path . (string)$timestamp,
        'timestamp' => $timestamp,
    ],
    // Format 2: partner_id + path + timestamp (milliseconds)
    [
        'name' => 'Format 2: partnerId + path + timestamp (milliseconds)',
        'baseString' => (string)$partnerId . $path . (string)$timestampMs,
        'timestamp' => $timestampMs,
    ],
    // Format 3: path + timestamp (seconds) + partner_id
    [
        'name' => 'Format 3: path + timestamp (seconds) + partnerId',
        'baseString' => $path . (string)$timestamp . (string)$partnerId,
        'timestamp' => $timestamp,
    ],
    // Format 4: path + timestamp (milliseconds) + partner_id
    [
        'name' => 'Format 4: path + timestamp (milliseconds) + partnerId',
        'baseString' => $path . (string)$timestampMs . (string)$partnerId,
        'timestamp' => $timestampMs,
    ],
    // Format 5: timestamp + path + partner_id (seconds)
    [
        'name' => 'Format 5: timestamp (seconds) + path + partnerId',
        'baseString' => (string)$timestamp . $path . (string)$partnerId,
        'timestamp' => $timestamp,
    ],
    // Format 6: timestamp + path + partner_id (milliseconds)
    [
        'name' => 'Format 6: timestamp (milliseconds) + path + partnerId',
        'baseString' => (string)$timestampMs . $path . (string)$partnerId,
        'timestamp' => $timestampMs,
    ],
];

echo "<table border='1' cellpadding='10' style='width:100%; border-collapse:collapse;'>";
echo "<tr style='background:#f0f0f0;'>";
echo "<th>Format</th>";
echo "<th>Base String</th>";
echo "<th>Signature</th>";
echo "<th>Test URL</th>";
echo "</tr>";

foreach ($tests as $test) {
    $baseString = $test['baseString'];
    $sig = hash_hmac('sha256', $baseString, $partnerKey);
    
    $testUrl = sprintf(
        '%s%s?partner_id=%s&timestamp=%s&sign=%s&redirect=%s',
        $host,
        $path,
        $partnerId,
        $test['timestamp'],
        $sig,
        rawurlencode($redirectUrl)
    );
    
    echo "<tr>";
    echo "<td><strong>" . htmlspecialchars($test['name']) . "</strong></td>";
    echo "<td><code style='font-size:11px; word-break:break-all;'>" . htmlspecialchars($baseString) . "</code></td>";
    echo "<td><code style='font-size:11px; word-break:break-all;'>" . htmlspecialchars($sig) . "</code></td>";
    echo "<td><a href='" . htmlspecialchars($testUrl) . "' target='_blank' style='color:blue; text-decoration:underline;'>Test</a></td>";
    echo "</tr>";
}

echo "</table>";

echo "<hr>";
echo "<h3>Instructions:</h3>";
echo "<ol>";
echo "<li>Click each 'Test' link and note which one succeeds (or gets furthest without 'Wrong sign' error)</li>";
echo "<li>Once you find the correct format, update the generateSignature() function in auth_live.php and callback.php</li>";
echo "<li>Verify the partner_key in app/config.php is exactly correct (no spaces, no typos)</li>";
echo "</ol>";
?>
