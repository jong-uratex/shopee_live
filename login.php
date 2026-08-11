<?php
/**
 * Login Controller
 *
 * Handles user authentication via username or email + password.
 * On success: regenerates the session ID (session fixation protection),
 * stores essential user data in $_SESSION, and redirects to success.php.
 *
 * Requirements:
 *   - app/security.php  → must start the session and contain no output
 *   - app/config.php    → provides pdo_connect() and pdo_connection_status()
 *
 * @author  Jenor Ricafort
 * @version 2026-08
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------

// Enable full error reporting during development.
// In production: set display_errors = 0 and log errors instead.
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/app/security.php';
require_once __DIR__ . '/app/config.php';

// Safety net: guarantee an active session even if security.php
// is modified or fails to call session_start().
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// ---------------------------------------------------------------------------
// Variables
// ---------------------------------------------------------------------------

$err       = '';
$db_status = pdo_connection_status();

// ---------------------------------------------------------------------------
// Form Handling (POST only)
// ---------------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Basic presence validation
    if ($username === '' || $password === '') {
        $err = 'Please enter username/email and password.';
    } else {
        try {
            $pdo  = pdo_connect();
            $stmt = $pdo->prepare(
                'SELECT id, username, password
                 FROM users
                 WHERE username = :u OR email = :e
                 LIMIT 1'
            );
            $stmt->execute([
                ':u' => $username,
                ':e' => $username,
            ]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verify password using the secure password_verify() function
            if ($user && password_verify($password, $user['password'])) {
                // -------------------------------------------------------
                // Successful authentication
                // -------------------------------------------------------

                // Regenerate session ID to prevent session fixation attacks.
                // Must be called while the session is still active.
                session_regenerate_id(true);

                // Store only the minimum data needed for the authenticated state
                $_SESSION['user_id']  = (int) $user['id'];
                $_SESSION['username'] = $user['username'];

                // Redirect after successful login.
                // Relative path is preferred when both files share the same directory.
                // Uncomment the absolute version only if your document root requires it.
                header('Location: success.php');
                // header('Location: /jong/shopee_live/success.php');
                exit;
            }

            // Authentication failed
            $err = 'Invalid credentials.';

        } catch (Throwable $e) {
            // In production you should log the full exception and show a generic message
            $err = 'An error occurred. Please try again later.';
            // Optional: error_log($e->getMessage());
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
</head>
<body class="d-flex align-items-center justify-content-center vh-100">

  <div class="card p-4" style="min-width:320px; max-width:420px; width:100%;">
    <h4 class="mb-3">Sign In</h4>

    <?php if ($err !== ''): ?>
      <div class="alert alert-danger">
        <?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <form method="post" action="" autocomplete="on">
      <div class="form-group">
        <label for="username">Username or Email</label>
        <input
          type="text"
          id="username"
          name="username"
          class="form-control"
          required
          autofocus
          autocomplete="username"
          value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
        >
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input
          type="password"
          id="password"
          name="password"
          class="form-control"
          required
          autocomplete="current-password"
        >
      </div>

      <div class="d-flex justify-content-between align-items-center">
        <button type="submit" class="btn btn-primary">Sign In</button>
        <a href="/">Home</a>
      </div>
    </form>

    <footer class="text-center mt-4 text-muted small">
      <div>
        © 2026 by Jenor Ricafort
        | <?= htmlspecialchars($db_status, ENT_QUOTES, 'UTF-8') ?>
      </div>
    </footer>
  </div>

</body>
</html>