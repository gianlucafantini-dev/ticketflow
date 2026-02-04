<?php
session_start();
require_once '../config/conf_db.php';
require_once '../includes/auth_check.php';

// Only admin can access
if ($current_user_role !== 'admin') {
    header('Location: ../tickets/index.php');
    exit;
}

$conn = getDBConnection();
$success = '';
$error = '';

// Handle role change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_role'])) {
    $user_id = intval($_POST['user_id']);
    $new_role = $_POST['role'];
    
    // Validate role
    if (in_array($new_role, ['user', 'agent', 'admin'])) {
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->bind_param("si", $new_role, $user_id);
        
        if ($stmt->execute()) {
            $success = "User role updated successfully!";
        } else {
            $error = "Failed to update user role";
        }
        $stmt->close();
    }
}

// Handle delete user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $user_id = intval($_POST['user_id']);
    
    // Can't delete yourself
    if ($user_id !== $current_user_id) {
        // Delete user's comments first
        $conn->query("DELETE FROM comments WHERE user_id = $user_id");
        
        // Delete user's tickets
        $conn->query("DELETE FROM tickets WHERE user_id = $user_id");
        
        // Delete user
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            $success = "User deleted successfully!";
        } else {
            $error = "Failed to delete user";
        }
        $stmt->close();
    } else {
        $error = "You cannot delete yourself!";
    }
}

// Get all users with stats
$query = "
    SELECT 
        u.id,
        u.name,
        u.email,
        u.role,
        u.created_at,
        COUNT(DISTINCT t.id) as ticket_count,
        COUNT(DISTINCT c.id) as comment_count,
        COUNT(DISTINCT ta.id) as assigned_count
    FROM users u
    LEFT JOIN tickets t ON u.id = t.user_id
    LEFT JOIN comments c ON u.id = c.user_id
    LEFT JOIN tickets ta ON u.id = ta.assigned_to
    GROUP BY u.id
    ORDER BY u.created_at DESC
";

$users = $conn->query($query)->fetch_all(MYSQLI_ASSOC);

// Get counts by role
$role_counts = $conn->query("
    SELECT 
        role,
        COUNT(*) as count
    FROM users
    GROUP BY role
")->fetch_all(MYSQLI_ASSOC);

$counts = [
    'admin' => 0,
    'agent' => 0,
    'user' => 0
];

foreach ($role_counts as $rc) {
    $counts[$rc['role']] = $rc['count'];
}

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - TicketFlow Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .role-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-weight: 600;
        }
        .role-admin { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; }
        .role-agent { background: #007bff; color: white; }
        .role-user { background: #6c757d; color: white; }
        .stats-mini {
            font-size: 0.85rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="../index.php">🎫 TicketFlow</a>
            <span class="navbar-text text-warning me-3">👑 ADMIN - User Management</span>
            <div class="ms-auto">
                <span class="text-white me-3">👤 <?= htmlspecialchars($current_user_name) ?></span>
                <a href="index.php" class="btn btn-warning btn-sm me-2">Dashboard</a>
                <a href="../tickets/index.php" class="btn btn-outline-light btn-sm me-2">My Tickets</a>
                <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h2 class="fw-bold">👥 User Management</h2>
                <p class="text-muted">Manage user accounts and roles</p>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="display-5"><?= count($users) ?></h3>
                        <p class="text-muted mb-0">Total Users</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-danger">
                    <div class="card-body text-center">
                        <h3 class="display-5 text-danger"><?= $counts['admin'] ?></h3>
                        <p class="text-muted mb-0">Admins</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h3 class="display-5 text-primary"><?= $counts['agent'] ?></h3>
                        <p class="text-muted mb-0">Agents</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Users Table -->
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0">All Users</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Tickets Created</th>
                                <th>Comments</th>
                                <th>Tickets Assigned</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= $user['id'] ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($user['name']) ?></strong>
                                        <?php if ($user['id'] === $current_user_id): ?>
                                            <span class="badge bg-info">You</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <select name="role" class="form-select form-select-sm role-badge role-<?= $user['role'] ?>" 
                                                    onchange="if(confirm('Change role for <?= htmlspecialchars($user['name']) ?>?')) this.form.submit()">
                                                <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                                <option value="agent" <?= $user['role'] === 'agent' ? 'selected' : '' ?>>Agent</option>
                                                <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                            </select>
                                            <button type="submit" name="change_role" class="d-none"></button>
                                        </form>
                                    </td>
                                    <td class="stats-mini"><?= $user['ticket_count'] ?> tickets</td>
                                    <td class="stats-mini"><?= $user['comment_count'] ?> comments</td>
                                    <td class="stats-mini"><?= $user['assigned_count'] ?> assigned</td>
                                    <td class="stats-mini"><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
                                    <td>
                                        <?php if ($user['id'] !== $current_user_id): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <button type="submit" name="delete_user" 
                                                        class="btn btn-sm btn-outline-danger"
                                                        onclick="return confirm('Delete user <?= htmlspecialchars($user['name']) ?>? This will also delete their tickets and comments.')">
                                                    Delete
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted small">Current user</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="py-4"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>