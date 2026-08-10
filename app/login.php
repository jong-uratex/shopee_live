<?php
session_start();
require_once __DIR__ . '/config.php';

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $err = 'Please provide username/email and password.';
    } else {
        try {
            $pdo = pdo_connect();
            $stmt = $pdo->prepare('SELECT id, username, password FROM users WHERE username = :u OR email = :u LIMIT 1');
            $stmt->execute([':u' => $username]);
            $user = $stmt->fetch();
            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                header('Location: success.php');
                exit;
            } else {
                $err = 'Invalid credentials.';
            }
        } catch (Exception $e) {
            $err = 'Error: ' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Login</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" />
</head>
<body class="d-flex align-items-center justify-content-center vh-100">
  <div class="card p-4" style="min-width:320px; max-width:420px; width:100%;">
    <h4 class="mb-3">Sign In</h4>
    <?php if ($err): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div>
    <?php endif; ?>
    <form method="post">
      <div class="form-group">
        <label>Username or Email</label>
        <input name="username" class="form-control" required autofocus />
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" class="form-control" required />
      </div>
      <div class="d-flex justify-content-between align-items-center">
        <button class="btn btn-primary">Sign In</button>
        <a href="/">Home</a>
      </div>
    </form>
  </div>
</body>
</html>
