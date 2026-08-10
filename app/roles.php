<?php
// Minimal roles management UI: list, create, delete roles (for demo purposes only)
require_once __DIR__ . '/config.php';
$pdo = pdo_connect();

// Handle create
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    if ($name === '' || $slug === '') {
        $errors[] = 'Name and slug are required.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO roles (name, slug, description, permissions) VALUES (:name, :slug, :desc, :perms)');
        try {
            $stmt->execute([
                ':name' => $name,
                ':slug' => $slug,
                ':desc' => $desc,
                ':perms' => json_encode(new stdClass()),
            ]);
            header('Location: ' . basename(__FILE__));
            exit;
        } catch (PDOException $e) {
            $errors[] = 'DB error: ' . $e->getMessage();
        }
    }
}

// Handle delete via ?delete=id
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare('DELETE FROM roles WHERE id = :id');
    try {
        $stmt->execute([':id' => $id]);
        header('Location: ' . basename(__FILE__));
        exit;
    } catch (PDOException $e) {
        $errors[] = 'DB error: ' . $e->getMessage();
    }
}

// Fetch roles
$roles = [];
try {
    $stmt = $pdo->query('SELECT id, name, slug, description, created_at FROM roles ORDER BY id DESC');
    $roles = $stmt->fetchAll();
} catch (PDOException $e) {
    $errors[] = 'DB error: ' . $e->getMessage();
}

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Roles Management</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
  <div class="container">
    <h1>Roles</h1>
    <?php foreach ($errors as $e): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($e); ?></div>
    <?php endforeach; ?>

    <div class="row">
      <div class="col-md-6">
        <h4>Create Role</h4>
        <form method="post">
          <input type="hidden" name="action" value="create">
          <div class="form-group">
            <label>Name</label>
            <input class="form-control" name="name" required>
          </div>
          <div class="form-group">
            <label>Slug</label>
            <input class="form-control" name="slug" required>
          </div>
          <div class="form-group">
            <label>Description</label>
            <textarea class="form-control" name="description"></textarea>
          </div>
          <button class="btn btn-primary">Create</button>
        </form>
      </div>

      <div class="col-md-6">
        <h4>Existing Roles</h4>
        <table class="table table-sm">
          <thead><tr><th>ID</th><th>Name</th><th>Slug</th><th>Created</th><th></th></tr></thead>
          <tbody>
            <?php if (count($roles) === 0): ?>
              <tr><td colspan="5">No roles found.</td></tr>
            <?php else: ?>
              <?php foreach ($roles as $r): ?>
                <tr>
                  <td><?php echo $r['id']; ?></td>
                  <td><?php echo htmlspecialchars($r['name']); ?></td>
                  <td><?php echo htmlspecialchars($r['slug']); ?></td>
                  <td><?php echo $r['created_at']; ?></td>
                  <td><a class="btn btn-sm btn-danger" href="?delete=<?php echo $r['id']; ?>" onclick="return confirm('Delete role?');">Delete</a></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</body>
</html>
