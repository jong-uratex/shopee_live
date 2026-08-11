<?php
/**
 * Security bootstrap
 * - Security headers
 * - Secure session cookie settings
 * - Session start
 * - Auth & CSRF helpers
 *
 * This file must never produce output.
 */

// Block direct browser access
if (php_sapi_name() !== 'cli' && realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    http_response_code(403);
    exit;
}

// Normalize host: remove www prefix if present
if (isset($_SERVER['HTTP_HOST']) && str_starts_with($_SERVER['HTTP_HOST'], 'www.')) {
    $host = substr($_SERVER['HTTP_HOST'], 4);
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $url = $protocol . '://' . $host . ($_SERVER['REQUEST_URI'] ?? '/');
    header('Location: ' . $url, true, 301);
    exit;
}

// Security headers
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer-when-downgrade');
header('Permissions-Policy: interest-cohort=()');

// HSTS only on HTTPS
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

if ($isHttps) {
    header('Strict-Transport-Security: max-age=63072000; includeSubDomains; preload');
}

// ------------------------------------------------------------------
// Session cookie – keep it simple and reliable
// ------------------------------------------------------------------
session_set_cookie_params([
    'lifetime' => 0,          // session cookie
    'path'     => '/',        // available on whole domain
    'secure'   => $isHttps,  // only send over HTTPS when applicable
    'httponly' => true,
    'samesite' => 'Lax',
    // IMPORTANT: do NOT set 'domain' unless you really need it.
    // Leaving it unset lets PHP use the current host correctly.
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * Require an authenticated user.
 * Redirects to login if not logged in.
 */
function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: /jong/shopee_live/login');
        exit;
    }
}

/**
 * Generate or return the current CSRF token.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate a submitted CSRF token.
 */
function validate_csrf(?string $token): bool
{
    return isset($_SESSION['csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}