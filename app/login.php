<?php
session_start();
require_once __DIR__ . '/config.php';

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $err = 'Please provide username and password.';
    } else {
        $mysqli = db_connect();
        $stmt = $mysqli->prepare('SELECT id, password FROM users WHERE username = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 1) {
            $stmt->bind_result($id, $hash);
            $stmt->fetch();
            if (password_verify($password, $hash)) {
                // Success
                session_regenerate_id(true);
                $_SESSION['user_id'] = $id;
                $_SESSION['username'] = $username;
                header('Location: dashboard.php');
                exit;
            } else {
                $err = 'Invalid credentials.';
            }
        } else {
            $err = 'Invalid credentials.';
        }
        $stmt->close();
        $mysqli->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Login - AdminLTE Demo</title>
  <link rel="stylesheet" href="../vendor/adminlte/dist/css/adminlte.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" />
</head>
<body class="hold-transition login-page">
<div class="login-box">
  <div class="login-logo"><a href="#">AdminLTE <b>Auth</b></a></div>
  <div class="card">
    <div class="card-body login-card-body">
      <p class="login-box-msg">Sign in to start your session</p>

      <?php if ($err): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div>
      <?php endif; ?>

      <form action="" method="post">
        <div class="input-group mb-3">
          <input type="text" name="username" class="form-control" placeholder="Username" required />
          <div class="input-group-append"><div class="input-group-text"><span class="fas fa-user"></span></div></div>
        </div>
        <div class="input-group mb-3">
          <input type="password" name="password" class="form-control" placeholder="Password" required />
          <div class="input-group-append"><div class="input-group-text"><span class="fas fa-lock"></span></div></div>
        </div>
        <div class="row">
          <div class="col-8">
            <a href="setup_db.php">Setup DB / Create admin</a>
          </div>
          <div class="col-4">
            <button type="submit" class="btn btn-primary btn-block">Sign In</button>
          </div>
        </div>
      </form>

    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../vendor/adminlte/dist/js/adminlte.min.js"></script>
</body>
</html>
