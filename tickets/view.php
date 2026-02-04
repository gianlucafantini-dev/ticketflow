<?php
session_start();
require_once '../config/conf_db.php';
require_once '../includes/auth_check.php';

// Get ticket ID from URL
$ticket_id = intval($_GET['id'] ?? 0);

if ($ticket_id <= 0) {
    header('Location: index.php');
    exit;
}

$conn = getDBConnection();

// Get ticket details
$query = "
    SELECT 
        t.id,
        t.title,
        t.description,
        t.created_at,
        t.updated_at,
        t.user_id,
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
    WHERE t.id = ? AND t.user_id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $ticket_id, $current_user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Ticket not found or not owned by user
    header('Location: index.php');
    exit;
}

$ticket = $result->fetch_assoc();
$stmt->close();

// Get comments
$comments_query = "
    SELECT 
        c.id,
        c.content,
        c.created_at,
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
$result = $stmt->get_result();
$comments = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get all statuses for status change
$statuses = $conn->query("SELECT * FROM statuses ORDER BY id")->fetch_all(MYSQLI_ASSOC);

// Handle comment submission
$comment_error = '';
$comment_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    $comment_content = trim($_POST['comment_content'] ?? '');
    
    if (empty($comment_content)) {
        $comment_error = 'Comment cannot be empty';
    } elseif (strlen($comment_content) < 5) {
        $comment_error = 'Comment must be at least 5 characters';
    } else {
        $stmt = $conn->prepare("INSERT INTO comments (ticket_id, user_id, content) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $ticket_id, $current_user_id, $comment_content);
        
        if ($stmt->execute()) {
            $comment_success = 'Comment added successfully!';
            // Refresh page to show new comment
            header("Location: view.php?id=$ticket_id");
            exit;
        } else {
            $comment_error = 'Failed to add comment';
        }
        $stmt->close();
    }
}

// Handle status change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
    $new_status_id = intval($_POST['status_id'] ?? 0);
    
    if ($new_status_id > 0) {
        $stmt = $conn->prepare("UPDATE tickets SET status_id = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
        $stmt->bind_param("iii", $new_status_id, $ticket_id, $current_user_id);
        
        if ($stmt->execute()) {
            header("Location: view.php?id=$ticket_id");
            exit;
        }
        $stmt->close();
    }
}

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?= $ticket['id'] ?> - TicketFlow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .ticket-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        .comment-author {
            font-weight: 600;
            color: #667eea;
        }
        .timeline-item {
            position: relative;
            padding-left: 30px;
            margin-bottom: 20px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 5px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #667eea;
        }
        .timeline-item::after {
            content: '';
            position: absolute;
            left: 14px;
            top: 15px;
            width: 2px;
            height: calc(100% + 5px);
            background: #e0e0e0;
        }
        .timeline-item:last-child::after {
            display: none;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="../index.php">🎫 TicketFlow</a>
            <div class="ms-auto">
                <span class="text-white me-3">👤 <?= htmlspecialchars($current_user_name) ?></span>
                <a href="index.php" class="btn btn-outline-light btn-sm me-2">← My Tickets</a>
                <a href="create.php" class="btn btn-success btn-sm me-2">New Ticket</a>
                <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Ticket Header -->
    <div class="ticket-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        Ticket #<?= $ticket['id'] ?> - <?= htmlspecialchars($ticket['title']) ?>
                    </h1>
                    <p class="mb-0 opacity-75">
                        Created by <?= htmlspecialchars($ticket['creator_name']) ?> 
                        on <?= date('F j, Y \a\t g:i A', strtotime($ticket['created_at'])) ?>
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <span class="priority-badge d-inline-block mb-2" 
                          style="background-color: <?= $ticket['priority_color'] ?>20; color: <?= $ticket['priority_color'] ?>">
                        Priority: <?= htmlspecialchars($ticket['priority_name']) ?>
                    </span>
                    <br>
                    <span class="status-badge d-inline-block" 
                          style="background-color: <?= $ticket['status_color'] ?>20; color: <?= $ticket['status_color'] ?>">
                        Status: <?= htmlspecialchars($ticket['status_name']) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row">
            <!-- Main Content -->
            <div class="col-md-8">
                <!-- Ticket Description -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">📝 Description</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-0" style="white-space: pre-line;"><?= htmlspecialchars($ticket['description']) ?></p>
                    </div>
                </div>

                <!-- Comments Section -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">💬 Comments (<?= count($comments) ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($comments)): ?>
                            <p class="text-muted text-center py-3">No comments yet. Be the first to comment!</p>
                        <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($comments as $comment): ?>
                                    <div class="timeline-item">
                                        <div class="comment-box">
                                            <div class="d-flex justify-content-between mb-2">
                                                <span class="comment-author">
                                                    <?= htmlspecialchars($comment['user_name']) ?>
                                                    <?php if ($comment['user_role'] === 'admin'): ?>
                                                        <span class="badge bg-danger">Admin</span>
                                                    <?php elseif ($comment['user_role'] === 'agent'): ?>
                                                        <span class="badge bg-primary">Agent</span>
                                                    <?php endif; ?>
                                                </span>
                                                <small class="text-muted">
                                                    <?= date('M j, Y \a\t g:i A', strtotime($comment['created_at'])) ?>
                                                </small>
                                            </div>
                                            <p class="mb-0" style="white-space: pre-line;"><?= htmlspecialchars($comment['content']) ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Add Comment Form -->
                        <hr>
                        <h6 class="mb-3">Add a comment:</h6>
                        
                        <?php if ($comment_error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($comment_error) ?></div>
                        <?php endif; ?>
                        
                        <?php if ($comment_success): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($comment_success) ?></div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <textarea class="form-control" name="comment_content" rows="3" 
                                          placeholder="Write your comment here..." required></textarea>
                                <small class="text-muted">Minimum 5 characters</small>
                            </div>
                            <button type="submit" name="add_comment" class="btn btn-primary">
                                💬 Add Comment
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-md-4">
                <!-- Ticket Info -->
                <div class="card mb-3">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">📊 Ticket Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-muted">Ticket ID</small>
                            <div class="fw-bold">#<?= $ticket['id'] ?></div>
                        </div>
                        
                        <div class="mb-3">
                            <small class="text-muted">Created</small>
                            <div><?= date('M j, Y', strtotime($ticket['created_at'])) ?></div>
                        </div>
                        
                        <div class="mb-3">
                            <small class="text-muted">Last Updated</small>
                            <div><?= date('M j, Y', strtotime($ticket['updated_at'])) ?></div>
                        </div>
                        
                        <div class="mb-3">
                            <small class="text-muted">Created By</small>
                            <div><?= htmlspecialchars($ticket['creator_name']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($ticket['creator_email']) ?></small>
                        </div>
                        
                        <?php if ($ticket['assigned_name']): ?>
                            <div class="mb-3">
                                <small class="text-muted">Assigned To</small>
                                <div><?= htmlspecialchars($ticket['assigned_name']) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Change Status -->
                <div class="card mb-3">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">🔄 Change Status</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label">Current: 
                                    <strong style="color: <?= $ticket['status_color'] ?>">
                                        <?= htmlspecialchars($ticket['status_name']) ?>
                                    </strong>
                                </label>
                                <select class="form-select" name="status_id" required>
                                    <?php foreach ($statuses as $status): ?>
                                        <option value="<?= $status['id'] ?>" 
                                                <?= $status['id'] == $ticket['status_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($status['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" name="change_status" class="btn btn-primary w-100">
                                Update Status
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Actions -->
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">⚡ Actions</h6>
                    </div>
                    <div class="card-body">
                        <a href="index.php" class="btn btn-outline-secondary w-100 mb-2">
                            ← Back to Tickets
                        </a>
                        <a href="create.php" class="btn btn-success w-100">
                            ➕ Create New Ticket
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="py-5"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>