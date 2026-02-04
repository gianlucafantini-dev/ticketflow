<?php
session_start();
require_once '../config/conf_db.php';
require_once '../includes/auth_check.php';

// Get user's tickets
$conn = getDBConnection();

// Get filter from URL
$filter = $_GET['filter'] ?? 'all';

// Build query based on filter
$where = "WHERE t.user_id = ?";
if ($filter === 'open') {
    $where .= " AND s.name != 'Closed'";
} elseif ($filter === 'closed') {
    $where .= " AND s.name = 'Closed'";
}

$query = "
    SELECT 
        t.id,
        t.title,
        t.description,
        t.created_at,
        t.updated_at,
        p.name as priority_name,
        p.color as priority_color,
        s.name as status_name,
        s.color as status_color,
        assigned.name as assigned_name
    FROM tickets t
    LEFT JOIN priorities p ON t.priority_id = p.id
    LEFT JOIN statuses s ON t.status_id = s.id
    LEFT JOIN users assigned ON t.assigned_to = assigned.id
    $where
    ORDER BY t.created_at DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();
$tickets = $result->fetch_all(MYSQLI_ASSOC);

$stmt->close();

// Count tickets by status
$count_query = "
    SELECT 
        SUM(CASE WHEN s.name != 'Closed' THEN 1 ELSE 0 END) as open_count,
        SUM(CASE WHEN s.name = 'Closed' THEN 1 ELSE 0 END) as closed_count,
        COUNT(*) as total_count
    FROM tickets t
    LEFT JOIN statuses s ON t.status_id = s.id
    WHERE t.user_id = ?
";

$stmt = $conn->prepare($count_query);
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();
$counts = $result->fetch_assoc();
$stmt->close();

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tickets - TicketFlow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .ticket-card {
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
        }
        .ticket-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .priority-badge, .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-weight: 600;
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
                <a href="create.php" class="btn btn-success btn-sm me-2">➕ New Ticket</a>
                <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h2 class="fw-bold">My Tickets</h2>
                <p class="text-muted">View and manage your support tickets</p>
            </div>
            <div class="col-md-4 text-end">
                <a href="create.php" class="btn btn-primary">
                    ➕ Create New Ticket
                </a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="display-4 text-primary"><?= $counts['total_count'] ?></h3>
                        <p class="text-muted mb-0">Total Tickets</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="display-4 text-warning"><?= $counts['open_count'] ?></h3>
                        <p class="text-muted mb-0">Open Tickets</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="display-4 text-success"><?= $counts['closed_count'] ?></h3>
                        <p class="text-muted mb-0">Closed Tickets</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="mb-3">
            <div class="btn-group" role="group">
                <a href="?filter=all" class="btn btn-sm <?= $filter === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    All (<?= $counts['total_count'] ?>)
                </a>
                <a href="?filter=open" class="btn btn-sm <?= $filter === 'open' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    Open (<?= $counts['open_count'] ?>)
                </a>
                <a href="?filter=closed" class="btn btn-sm <?= $filter === 'closed' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    Closed (<?= $counts['closed_count'] ?>)
                </a>
            </div>
        </div>

        <!-- Tickets List -->
        <?php if (empty($tickets)): ?>
            <div class="alert alert-info text-center">
                <h4>No tickets found</h4>
                <p class="mb-3">You haven't created any tickets yet.</p>
                <a href="create.php" class="btn btn-primary">Create Your First Ticket</a>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($tickets as $ticket): ?>
                    <div class="col-md-6 mb-3">
                        <div class="card ticket-card" onclick="window.location.href='view.php?id=<?= $ticket['id'] ?>'">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="card-title mb-0">
                                        #<?= $ticket['id'] ?> - <?= htmlspecialchars($ticket['title']) ?>
                                    </h5>
                                    <span class="priority-badge" style="background-color: <?= $ticket['priority_color'] ?>20; color: <?= $ticket['priority_color'] ?>">
                                        <?= htmlspecialchars($ticket['priority_name']) ?>
                                    </span>
                                </div>
                                
                                <p class="card-text text-muted small mb-3">
                                    <?= htmlspecialchars(substr($ticket['description'], 0, 100)) ?>
                                    <?= strlen($ticket['description']) > 100 ? '...' : '' ?>
                                </p>

                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="status-badge" style="background-color: <?= $ticket['status_color'] ?>20; color: <?= $ticket['status_color'] ?>">
                                            <?= htmlspecialchars($ticket['status_name']) ?>
                                        </span>
                                        <?php if ($ticket['assigned_name']): ?>
                                            <small class="text-muted ms-2">
                                                👤 Assigned to: <?= htmlspecialchars($ticket['assigned_name']) ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted">
                                        <?= date('d M Y', strtotime($ticket['created_at'])) ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>