<?php
/**
 * Login Controller
 *
 * Authenticates a user by username or email + password.
 * On success:
 *   1. Regenerates the session ID (session-fixation protection)
 *   2. Stores minimal user data in $_SESSION
 *   3. Redirects to success.php
 *
 * @author  Jenor Ricafort
 */

declare(strict_types=1);

// Development only – turn off in production
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/app/security.php';
require_once __DIR__ . '/app/config.php';

// Extra safety (security.php should already have started the session)
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$err       = '';
$db_status = pdo_connection_status();

// ------------------------------------------------------------------
// Handle login form
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

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

            if ($user && password_verify($password, $user['password'])) {
                // Prevent session fixation
                session_regenerate_id(true);

                // Store only what you need
                $_SESSION['user_id']  = (int) $user['id'];
                $_SESSION['username'] = $user['username'];

                // Force session data to be written before redirect
                session_write_close();

                // Redirect to the clean dashboard URL
                header('Location: /jong/shopee_live/dashboard');
                exit;
            }

            $err = 'Invalid credentials.';

        } catch (Throwable $e) {
            // Log the real error in production
            // error_log($e->getMessage());
            $err = 'An error occurred. Please try again later.';
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