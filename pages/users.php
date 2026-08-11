<?php
require_admin_or_superadmin();

$errors = [];
$success = '';
$pdo = pdo_connect();

// Load available roles
$roles = [];
try {
    $stmt = $pdo->query('SELECT id, name, slug FROM roles ORDER BY name ASC');
    $roles = $stmt->fetchAll();
} catch (PDOException $e) {
    $errors[] = 'Failed to load roles: ' . $e->getMessage();
}

function build_user_params(array $input): array
{
    return [
        'username' => trim($input['username'] ?? ''),
        'email' => trim($input['email'] ?? ''),
        'role_id' => isset($input['role_id']) && $input['role_id'] !== '' ? (int) $input['role_id'] : null,
        'status' => in_array($input['status'] ?? 'inactive', ['active', 'inactive'], true) ? $input['status'] : 'inactive',
        'is_superadmin' => !empty($input['is_superadmin']) ? 1 : 0,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please refresh the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'create' || $action === 'update') {
            $fields = build_user_params($_POST);

            if ($fields['username'] === '' || $fields['email'] === '') {
                $errors[] = 'Username and email are required.';
            }
            if (!filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'A valid email address is required.';
            }

            if ($action === 'create' && trim($_POST['password'] ?? '') === '') {
                $errors[] = 'Password is required when creating a new user.';
            }

            if (empty($errors)) {
                try {
                    if ($action === 'create') {
                        $stmt = $pdo->prepare(
                            'INSERT INTO users (uuid, username, email, password, role_id, is_superadmin, status, created_at, updated_at)
                             VALUES (:uuid, :username, :email, :password, :role_id, :is_superadmin, :status, NOW(), NOW())'
                        );
                        $stmt->execute([
                            ':uuid' => bin2hex(random_bytes(16)),
                            ':username' => $fields['username'],
                            ':email' => $fields['email'],
                            ':password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
                            ':role_id' => $fields['role_id'],
                            ':is_superadmin' => $fields['is_superadmin'],
                            ':status' => $fields['status'],
                        ]);
                        $success = 'User created successfully.';
                    } else {
                        $userId = (int) ($_POST['user_id'] ?? 0);
                        if ($userId <= 0) {
                            throw new RuntimeException('Invalid user ID.');
                        }

                        $sql = 'UPDATE users SET username = :username, email = :email, role_id = :role_id, is_superadmin = :is_superadmin, status = :status, updated_at = NOW()';
                        $params = [
                            ':username' => $fields['username'],
                            ':email' => $fields['email'],
                            ':role_id' => $fields['role_id'],
                            ':is_superadmin' => $fields['is_superadmin'],
                            ':status' => $fields['status'],
                            ':id' => $userId,
                        ];

                        if (trim($_POST['password'] ?? '') !== '') {
                            $sql .= ', password = :password';
                            $params[':password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        }

                        $sql .= ' WHERE id = :id';
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        $success = 'User updated successfully.';
                    }
                } catch (Throwable $e) {
                    $errors[] = 'Database error: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'delete') {
            $deleteUserId = (int) ($_POST['user_id'] ?? 0);
            if ($deleteUserId <= 0) {
                $errors[] = 'Invalid user selected for deletion.';
            }
            if (empty($errors)) {
                try {
                    $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
                    $stmt->execute([':id' => $deleteUserId]);
                    $success = 'User deleted successfully.';
                } catch (Throwable $e) {
                    $errors[] = 'Database error: ' . $e->getMessage();
                }
            }
        }
    }
}

$userToEdit = null;
if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    if ($editId > 0) {
        $stmt = $pdo->prepare(
            'SELECT id, username, email, role_id, is_superadmin, status
             FROM users
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $editId]);
        $userToEdit = $stmt->fetch();
        if (!$userToEdit) {
            $errors[] = 'Selected user could not be found.';
        }
    }
}

$users = [];
try {
    $stmt = $pdo->query(
        'SELECT u.id, u.username, u.email, u.status, u.is_superadmin,
                u.created_at, u.updated_at, r.name AS role_name, r.slug AS role_slug
         FROM users u
         LEFT JOIN roles r ON r.id = u.role_id
         ORDER BY u.id DESC'
    );
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $errors[] = 'Failed to load users: ' . $e->getMessage();
}
?>
<div class="panel">
  <div class="panel-header">
    <div>
      <h2 class="panel-title">User Management</h2>
      <p class="panel-subtitle">Create, edit, or remove users. Only admin and super admin have access.</p>
    </div>
  </div>

  <?php if ($success !== ''): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>
  <?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
  <?php endforeach; ?>

  <div class="dashboard-grid">
    <div class="panel" style="min-width:300px;">
      <h3><?php echo $userToEdit ? 'Edit User' : 'Create User'; ?></h3>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
        <input type="hidden" name="action" value="<?php echo $userToEdit ? 'update' : 'create'; ?>">
        <?php if ($userToEdit): ?>
          <input type="hidden" name="user_id" value="<?php echo (int) $userToEdit['id']; ?>">
        <?php endif; ?>

        <div class="form-group">
          <label for="user-username">Username</label>
          <input id="user-username" class="form-control" name="username" required value="<?php echo htmlspecialchars($userToEdit['username'] ?? ''); ?>">
        </div>

        <div class="form-group">
          <label for="user-email">Email</label>
          <input id="user-email" type="email" class="form-control" name="email" required value="<?php echo htmlspecialchars($userToEdit['email'] ?? ''); ?>">
        </div>

        <div class="form-group">
          <label for="user-role">Role</label>
          <select id="user-role" class="form-select" name="role_id">
            <option value="">-- Select role --</option>
            <?php foreach ($roles as $role): ?>
              <option value="<?php echo (int) $role['id']; ?>" <?php echo isset($userToEdit['role_id']) && $userToEdit['role_id'] === $role['id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($role['name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="user-status">Status</label>
          <select id="user-status" class="form-select" name="status">
            <option value="active" <?php echo (isset($userToEdit['status']) && $userToEdit['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
            <option value="inactive" <?php echo (isset($userToEdit['status']) && $userToEdit['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
          </select>
        </div>

        <div class="form-group">
          <label for="user-password"><?php echo $userToEdit ? 'New password (optional)' : 'Password'; ?></label>
          <input id="user-password" type="password" class="form-control" name="password" <?php echo $userToEdit ? '' : 'required'; ?> autocomplete="new-password">
        </div>

        <div class="form-group">
          <label>
            <input type="checkbox" name="is_superadmin" value="1" <?php echo !empty($userToEdit['is_superadmin']) ? 'checked' : ''; ?>>
            Super admin
          </label>
        </div>

        <button type="submit" class="btn-primary"><?php echo $userToEdit ? 'Save changes' : 'Create user'; ?></button>
        <?php if ($userToEdit): ?>
          <a href="/jong/shopee_live/dashboard/?page=users" class="btn btn-secondary" style="margin-left:0.75rem;">Cancel</a>
        <?php endif; ?>
      </form>
    </div>

    <div class="panel table-wrapper" style="min-width:400px;">
      <h3>Users</h3>
      <table class="table-basic">
        <thead>
          <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($users) === 0): ?>
            <tr><td colspan="6">No users found.</td></tr>
          <?php else: ?>
            <?php foreach ($users as $user): ?>
              <tr>
                <td><?php echo (int) $user['id']; ?></td>
                <td><?php echo htmlspecialchars($user['username']); ?></td>
                <td><?php echo htmlspecialchars($user['email']); ?></td>
                <td><?php echo htmlspecialchars($user['role_name'] ?: ($user['is_superadmin'] ? 'Super Admin' : 'User')); ?></td>
                <td>
                  <span class="badge <?php echo $user['status'] === 'active' ? 'success' : 'warning'; ?>">
                    <?php echo htmlspecialchars(ucfirst($user['status'])); ?>
                  </span>
                </td>
                <td>
                  <a class="btn btn-sm" href="/jong/shopee_live/dashboard/?page=users&edit=<?php echo (int) $user['id']; ?>">Edit</a>
                  <form method="post" style="display:inline-block; margin:0;" onsubmit="return confirm('Delete this user?');">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                    <button type="submit" class="btn btn-sm" style="background:#ef4444; color:#fff; border:none; border-radius:0.75rem; padding:0.4rem 0.8rem;">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
