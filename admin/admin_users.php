<?php
if (php_sapi_name() === 'cli-server') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}
include '../assets/db.php'; // Include database connection

// 🔒 Restrict Access to Admins Only
if (!isset($_SESSION['privilege']) || $_SESSION['privilege'] !== 'admin') {
    die("Access denied. You must be an admin.");
}

// Handle Delete Request
if (isset($_POST['delete_user'])) {
    $user_id = $_POST['user_id'];
    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    header("Location: admin_users.php?success=User deleted");
    exit();
}

// Handle Update Request
if (isset($_POST['update_user'])) {
    $user_id = $_POST['user_id'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $privilege = $_POST['privilege'];
    $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, privilege = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$username, $email, $privilege, $user_id]);

    header("Location: admin_users.php?success=User updated");
    exit();
}

// Fetch Users
$stmt = $db->query("SELECT id, username, email, privilege, created_at, updated_at FROM users");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Manage Users</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <h2 class="text-center text-primary">Admin Panel - Manage Users</h2>

    <!-- Success Message -->
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success text-center"><?= htmlspecialchars($_GET['success']) ?></div>
    <?php endif; ?>

    <table class="table table-bordered table-striped mt-4">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Admin</th>
                <th>Username</th>
                <th>Email</th>
                <th>Privilege</th>
                <th>Created At</th>
                <th>Updated At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <?php if ($_SESSION["username"] == "barroso" || $_SESSION["admin_impersonating"] == true) {?>
                    <td>
    <a href="https://qrsume.com/<?= htmlspecialchars($user['username']) ?>" target="_blank" class="btn btn-outline-primary btn-sm mb-1">View Site</a>

    <form method="POST" action="https://qrsume.com/admin/admin_login_as_user.php" class="d-inline">
        <input type="hidden" name="target_user_id" value="<?= $user['id'] ?>">
        <input type="hidden" name="target_username" value="<?= htmlspecialchars($user['username']) ?>">
        <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Sign in as <?= $user['username'] ?>?')">Sign In As</button>
    </form>
</td>
<?php }?>
                    <form method="POST">
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        <td><?= $user['id'] ?></td>

                        <td><input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" class="form-control"></td>
                        <td><input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" class="form-control"></td>
                        <td>
                            <select name="privilege" class="form-select">
                                
                                <option value="banned" <?= $user['privilege'] === 'banned' ? 'selected' : '' ?>>Banned</option>
                                <option value="user" <?= $user['privilege'] === 'user' ? 'selected' : '' ?>>User</option>
                                <option value="admin" <?= $user['privilege'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                        </td>
                        <td><?= $user['created_at'] ?></td>
                        <td><?= $user['updated_at'] ?></td>
                        <td>
                            <button type="submit" name="update_user" class="btn btn-success btn-sm">Update</button>
                            <button type="submit" name="delete_user" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?')">Delete</button>
                        </td>
                        
                    </form>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
