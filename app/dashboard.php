<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$username = htmlspecialchars($_SESSION['username'] ?? '');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Dashboard - AdminLTE Demo</title>
  <link rel="stylesheet" href="../vendor/adminlte/dist/css/adminlte.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" />
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
  <nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <a class="navbar-brand" href="#">Dashboard</a>
    <ul class="navbar-nav ml-auto">
      <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
    </ul>
  </nav>

  <div class="content-wrapper">
    <section class="content">
      <div class="container-fluid mt-3">
        <div class="card">
          <div class="card-header">Welcome</div>
          <div class="card-body">Hello, <?php echo $username; ?> — you are logged in.</div>
        </div>
      </div>
    </section>
  </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../vendor/adminlte/dist/js/adminlte.min.js"></script>
</body>
</html>
