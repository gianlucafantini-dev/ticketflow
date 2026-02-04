<?php
session_start();
require_once '../config/conf_db.php';
require_once '../includes/auth_check.php';

// Check if user is admin/agent
if ($current_user_role !== 'admin' && $current_user_role !== 'agent') {
    header('Location: ../tickets/index.php');
    exit;
}

$ticket_id = intval($_GET['id'] ?? 0);

if ($ticket_id <= 0) {
    header('Location: index.php');
    exit;
}

$conn = getDBConnection();

// Get ticket (admin can see ALL tickets)
$query = "
    SELECT 
        t.*,
        p.name as priority_name,
        p.color as priority_color,
        s.id as status_id,
        s.name as status_name,
        s.color as status_color,
        creator.name as creator_name,
        creator.email as creator_email,
        assigned.name as assigned_name
    FROM tickets t
    LEFT JOIN priorities p ON t.priority_id = p.id
    LEFT JOIN statuses s ON t.status_id = s.id
    LEFT JOIN users creator ON t.user_id = creator.id
    LEFT JOIN users assigned ON t.assigned_to = assigned.id
    WHERE t.id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $ticket_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: index.php');
    exit;
}

$ticket = $result->fetch_assoc();
$stmt->close();

// Get comments
$comments_query = "
    SELECT 
        c.*,
        u.name as user_name,
        u.role as user_role
    FROM comments c
    LEFT JOIN users u ON c.user_id = u.id
    WHERE c.ticket_id = ?
    ORDER BY c.created_at ASC
";

$stmt = $conn->prepare($comments_query);
$stmt->bind_param("i", $ticket_id);
$stmt->execute();
$comments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get agents for assignment
$agents = $conn->query("SELECT id, name FROM users WHERE role IN ('agent', 'admin') ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Get statuses and priorities
$statuses = $conn->query("SELECT * FROM statuses ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$priorities = $conn->query("SELECT * FROM priorities ORDER BY level")->fetch_all(MYSQLI_ASSOC);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Assign ticket
    if (isset($_POST['assign_ticket'])) {
        $assigned_to = intval($_POST['assigned_to'] ?? 0);
        $assigned_to = $assigned_to > 0 ? $assigned_to : null;
        
        $stmt = $conn->prepare("UPDATE tickets SET assigned_to = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("ii", $assigned_to, $ticket_id);
        $stmt->execute();
        $stmt->close();
        
        // Add comment
        $comment = $assigned_to ? "Ticket assigned to agent." : "Ticket unassigned.";
        $stmt = $conn->prepare("INSERT INTO comments (ticket_id, user_id, content) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $ticket_id, $current_user_id, $comment);
        $stmt->execute();
        $stmt->close();
        
        header("Location: ticket_view.php?id=$ticket_id");
        exit;
    }
    
    // Change status
    if (isset($_POST['change_status'])) {
        $new_status = intval($_POST['status_id']);
        $stmt = $conn->prepare("UPDATE tickets SET status_id = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("ii", $new_status, $ticket_id);
        $stmt->execute();
        $stmt->close();
        
        header("Location: ticket_view.php?id=$ticket_id");
        exit;
    }
    
    // Change priority
    if (isset($_POST['change_priority'])) {
        $new_priority = intval($_POST['priority_id']);
        $stmt = $conn->prepare("UPDATE tickets SET priority_id = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("ii", $new_priority, $ticket_id);
        $stmt->execute();
        $stmt->close();
        
        header("Location: ticket_view.php?id=$ticket_id");
        exit;
    }
    
    // Add comment
    if (isset($_POST['add_comment'])) {
        $content = trim($_POST['comment_content'] ?? '');
        if (!empty($content)) {
            $stmt = $conn->prepare("INSERT INTO comments (ticket_id, user_id, content) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $ticket_id, $current_user_id, $content);
            $stmt->execute();
            $stmt->close();
            
            header("Location: ticket_view.php?id=$ticket_id");
            exit;
        }
    }
}

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Ticket #<?= $ticket['id'] ?> - TicketFlow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .admin-header {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
        }
        .priority-badge, .status-badge {
            font-size: 0.85rem;
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-weight: 600;
        }
        .comment-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
        }
        .admin-badge {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="../index.php">🎫 TicketFlow</a>
            <span class="navbar-text text-warning me-3">👑 ADMIN VIEW</span>
            <div class="ms-auto">
                <span class="text-white me-3">👤 <?= htmlspecialchars($current_user_name) ?></span>
                <a href="index.php" class="btn btn-warning btn-sm me-2">← Admin Dashboard</a>
                <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Admin Header -->
    <div class="admin-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">Ticket #<?= $ticket['id'] ?> - <?= htmlspecialchars($ticket['title']) ?></h1>
                    <p class="mb-0 opacity-75">
                        Created by <?= htmlspecialchars($ticket['creator_name']) ?> 
                        on <?= date('F j, Y \a\t g:i A', strtotime($ticket['created_at'])) ?>
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <span class="priority-badge d-inline-block mb-2" 
                          style="background-color: <?= $ticket['priority_color'] ?>20; color: <?= $ticket['priority_color'] ?>">
                        <?= htmlspecialchars($ticket['priority_name']) ?>
                    </span>
                    <br>
                    <span class="status-badge d-inline-block" 
                          style="background-color: <?= $ticket['status_color'] ?>20; color: <?= $ticket['status_color'] ?>">
                        <?= htmlspecialchars($ticket['status_name']) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row">
            <!-- Main Content (same as user view) -->
            <div class="col-md-8">
                <!-- Description -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">📝 Description</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-0" style="white-space: pre-line;"><?= htmlspecialchars($ticket['description']) ?></p>
                    </div>
                </div>

                <!-- Comments (same structure) -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">💬 Comments (<?= count($comments) ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($comments)): ?>
                            <p class="text-muted text-center py-3">No comments yet.</p>
                        <?php else: ?>
                            <?php foreach ($comments as $comment): ?>
                                <div class="comment-box">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="fw-bold">
                                            <?= htmlspecialchars($comment['user_name']) ?>
                                            <?php if ($comment['user_role'] === 'admin'): ?>
                                                <span class="badge admin-badge">Admin</span>
                                            <?php elseif ($comment['user_role'] === 'agent'): ?>
                                                <span class="badge bg-primary">Agent</span>
                                            <?php endif; ?>
                                        </span>
                                        <small class="text-muted">
                                            <?= date('M j, Y \a\t g:i A', strtotime($comment['created_at'])) ?>
                                        </small>
                                    </div>
                                    <p class="mb-0"><?= htmlspecialchars($comment['content']) ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <!-- Add Comment -->
                        <hr>
                        <form method="POST">
                            <div class="mb-3">
                                <textarea class="form-control" name="comment_content" rows="3" 
                                          placeholder="Add admin comment..." required></textarea>
                            </div>
                            <button type="submit" name="add_comment" class="btn btn-primary">
                                💬 Add Comment
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Admin Sidebar -->
            <div class="col-md-4">
                <!-- Assign Ticket -->
                <div class="card mb-3 border-warning">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0">👤 Assign Ticket</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">
                                    Current: 
                                    <strong>
                                        <?= $ticket['assigned_name'] ? htmlspecialchars($ticket['assigned_name']) : 'Unassigned' ?>
                                    </strong>
                                </label>
                                <select class="form-select" name="assigned_to">
                                    <option value="0">Unassigned</option>
                                    <?php foreach ($agents as $agent): ?>
                                        <option value="<?= $agent['id'] ?>" 
                                                <?= $agent['id'] == $ticket['assigned_to'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($agent['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" name="assign_ticket" class="btn btn-warning w-100">
                                Assign
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Change Status -->
                <div class="card mb-3">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">🔄 Change Status</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <select class="form-select mb-2" name="status_id">
                                <?php foreach ($statuses as $status): ?>
                                    <option value="<?= $status['id'] ?>" 
                                            <?= $status['id'] == $ticket['status_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($status['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" name="change_status" class="btn btn-primary w-100">
                                Update Status
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Change Priority -->
                <div class="card mb-3">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">⚡ Change Priority</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <select class="form-select mb-2" name="priority_id">
                                <?php foreach ($priorities as $priority): ?>
                                    <option value="<?= $priority['id'] ?>" 
                                            <?= $priority['id'] == $ticket['priority_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($priority['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" name="change_priority" class="btn btn-primary w-100">
                                Update Priority
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Ticket Info -->
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">📊 Information</h6>
                    </div>
                    <div class="card-body">
                        <small class="text-muted">Creator</small>
                        <div class="mb-2"><?= htmlspecialchars($ticket['creator_name']) ?></div>
                        <div class="mb-3"><small class="text-muted"><?= htmlspecialchars($ticket['creator_email']) ?></small></div>
                        
                        <small class="text-muted">Created</small>
                        <div class="mb-3"><?= date('M j, Y g:i A', strtotime($ticket['created_at'])) ?></div>
                        
                        <small class="text-muted">Last Updated</small>
                        <div><?= date('M j, Y g:i A', strtotime($ticket['updated_at'])) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="py-5"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>