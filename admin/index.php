<?php
session_start();
require_once '../config/conf_db.php';
require_once '../includes/auth_check.php';

// Check if user is admin
if ($current_user_role !== 'admin' && $current_user_role !== 'agent') {
    header('Location: ../tickets/index.php');
    exit;
}

$conn = getDBConnection();

// Get filter
$filter = $_GET['filter'] ?? 'all';
$assigned_filter = $_GET['assigned'] ?? 'all';

// Build WHERE clause
$where_conditions = [];
$params = [];
$param_types = '';

if ($filter === 'open') {
    $where_conditions[] = "s.name != 'Closed'";
} elseif ($filter === 'closed') {
    $where_conditions[] = "s.name = 'Closed'";
}

if ($assigned_filter === 'unassigned') {
    $where_conditions[] = "t.assigned_to IS NULL";
} elseif ($assigned_filter === 'assigned') {
    $where_conditions[] = "t.assigned_to IS NOT NULL";
} elseif ($assigned_filter === 'mine' && $current_user_role === 'agent') {
    $where_conditions[] = "t.assigned_to = ?";
    $params[] = $current_user_id;
    $param_types .= 'i';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get all tickets
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
        creator.name as creator_name,
        assigned.name as assigned_name
    FROM tickets t
    LEFT JOIN priorities p ON t.priority_id = p.id
    LEFT JOIN statuses s ON t.status_id = s.id
    LEFT JOIN users creator ON t.user_id = creator.id
    LEFT JOIN users assigned ON t.assigned_to = assigned.id
    $where_clause
    ORDER BY 
        CASE 
            WHEN s.name = 'New' THEN 1
            WHEN s.name = 'In Progress' THEN 2
            WHEN s.name = 'Resolved' THEN 3
            ELSE 4
        END,
        p.level DESC,
        t.created_at DESC
";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $tickets = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $result = $conn->query($query);
    $tickets = $result->fetch_all(MYSQLI_ASSOC);
}

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN s.name != 'Closed' THEN 1 ELSE 0 END) as open,
        SUM(CASE WHEN s.name = 'Closed' THEN 1 ELSE 0 END) as closed,
        SUM(CASE WHEN t.assigned_to IS NULL THEN 1 ELSE 0 END) as unassigned,
        SUM(CASE WHEN s.name = 'New' THEN 1 ELSE 0 END) as new_tickets,
        SUM(CASE WHEN p.name = 'Urgent' AND s.name != 'Closed' THEN 1 ELSE 0 END) as urgent
    FROM tickets t
    LEFT JOIN statuses s ON t.status_id = s.id
    LEFT JOIN priorities p ON t.priority_id = p.id
";

$stats = $conn->query($stats_query)->fetch_assoc();

// Get agents for assignment
$agents_query = "SELECT id, name FROM users WHERE role IN ('agent', 'admin') ORDER BY name";
$agents = $conn->query($agents_query)->fetch_all(MYSQLI_ASSOC);

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - TicketFlow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .stats-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .ticket-row {
            transition: background-color 0.2s;
            cursor: pointer;
        }
        .ticket-row:hover {
            background-color: #f8f9fa;
        }
        .priority-badge, .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-weight: 600;
        }
        .urgent-indicator {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="../index.php">🎫 TicketFlow</a>
            <span class="navbar-text text-warning me-3">👑 ADMIN PANEL</span>
            <div class="ms-auto">
                <span class="text-white me-3">👤 <?= htmlspecialchars($current_user_name) ?></span>
                <a href="../tickets/index.php" class="btn btn-outline-light btn-sm me-2">My Tickets</a>
                <a href="users.php" class="btn btn-outline-warning btn-sm me-2">Users</a>
                <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col">
                <h2 class="fw-bold">Admin Dashboard</h2>
                <p class="text-muted">Manage all support tickets across the system</p>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="card stats-card" style="border-left-color: #667eea;">
                    <div class="card-body text-center">
                        <h3 class="display-5 text-primary"><?= $stats['total'] ?></h3>
                        <p class="text-muted mb-0 small">Total Tickets</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stats-card" style="border-left-color: #ffc107;">
                    <div class="card-body text-center">
                        <h3 class="display-5 text-warning"><?= $stats['open'] ?></h3>
                        <p class="text-muted mb-0 small">Open</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stats-card" style="border-left-color: #28a745;">
                    <div class="card-body text-center">
                        <h3 class="display-5 text-success"><?= $stats['closed'] ?></h3>
                        <p class="text-muted mb-0 small">Closed</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stats-card" style="border-left-color: #6c757d;">
                    <div class="card-body text-center">
                        <h3 class="display-5 text-secondary"><?= $stats['unassigned'] ?></h3>
                        <p class="text-muted mb-0 small">Unassigned</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stats-card" style="border-left-color: #17a2b8;">
                    <div class="card-body text-center">
                        <h3 class="display-5 text-info"><?= $stats['new_tickets'] ?></h3>
                        <p class="text-muted mb-0 small">New</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stats-card" style="border-left-color: #dc3545;">
                    <div class="card-body text-center">
                        <h3 class="display-5 text-danger urgent-indicator"><?= $stats['urgent'] ?></h3>
                        <p class="text-muted mb-0 small">Urgent Open</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-3">
            <div class="col-md-8">
                <div class="btn-group me-2" role="group">
                    <a href="?filter=all&assigned=<?= $assigned_filter ?>" 
                       class="btn btn-sm <?= $filter === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">
                        All (<?= $stats['total'] ?>)
                    </a>
                    <a href="?filter=open&assigned=<?= $assigned_filter ?>" 
                       class="btn btn-sm <?= $filter === 'open' ? 'btn-primary' : 'btn-outline-primary' ?>">
                        Open (<?= $stats['open'] ?>)
                    </a>
                    <a href="?filter=closed&assigned=<?= $assigned_filter ?>" 
                       class="btn btn-sm <?= $filter === 'closed' ? 'btn-primary' : 'btn-outline-primary' ?>">
                        Closed (<?= $stats['closed'] ?>)
                    </a>
                </div>

                <div class="btn-group" role="group">
                    <a href="?filter=<?= $filter ?>&assigned=all" 
                       class="btn btn-sm <?= $assigned_filter === 'all' ? 'btn-success' : 'btn-outline-success' ?>">
                        All Tickets
                    </a>
                    <a href="?filter=<?= $filter ?>&assigned=unassigned" 
                       class="btn btn-sm <?= $assigned_filter === 'unassigned' ? 'btn-success' : 'btn-outline-success' ?>">
                        Unassigned (<?= $stats['unassigned'] ?>)
                    </a>
                    <a href="?filter=<?= $filter ?>&assigned=assigned" 
                       class="btn btn-sm <?= $assigned_filter === 'assigned' ? 'btn-success' : 'btn-outline-success' ?>">
                        Assigned
                    </a>
                    <?php if ($current_user_role === 'agent'): ?>
                        <a href="?filter=<?= $filter ?>&assigned=mine" 
                           class="btn btn-sm <?= $assigned_filter === 'mine' ? 'btn-success' : 'btn-outline-success' ?>">
                            My Tickets
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tickets Table -->
        <div class="card">
            <div class="card-body p-0">
                <?php if (empty($tickets)): ?>
                    <div class="text-center py-5">
                        <h4 class="text-muted">No tickets found</h4>
                        <p>No tickets match the current filters.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="80">ID</th>
                                    <th>Title</th>
                                    <th width="120">Creator</th>
                                    <th width="120">Priority</th>
                                    <th width="120">Status</th>
                                    <th width="150">Assigned To</th>
                                    <th width="120">Created</th>
                                    <th width="100">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tickets as $ticket): ?>
                                    <tr class="ticket-row" onclick="window.location.href='ticket_view.php?id=<?= $ticket['id'] ?>'">
                                        <td class="fw-bold">#<?= $ticket['id'] ?></td>
                                        <td>
                                            <?= htmlspecialchars($ticket['title']) ?>
                                            <br>
                                            <small class="text-muted">
                                                <?= htmlspecialchars(substr($ticket['description'], 0, 60)) ?>...
                                            </small>
                                        </td>
                                        <td><?= htmlspecialchars($ticket['creator_name']) ?></td>
                                        <td>
                                            <span class="priority-badge" 
                                                  style="background-color: <?= $ticket['priority_color'] ?>20; color: <?= $ticket['priority_color'] ?>">
                                                <?= htmlspecialchars($ticket['priority_name']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge" 
                                                  style="background-color: <?= $ticket['status_color'] ?>20; color: <?= $ticket['status_color'] ?>">
                                                <?= htmlspecialchars($ticket['status_name']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($ticket['assigned_name']): ?>
                                                <span class="badge bg-info">
                                                    <?= htmlspecialchars($ticket['assigned_name']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Unassigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?= date('M j, Y', strtotime($ticket['created_at'])) ?></small>
                                        </td>
                                        <td>
                                            <a href="ticket_view.php?id=<?= $ticket['id'] ?>" 
                                               class="btn btn-sm btn-primary" onclick="event.stopPropagation()">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="py-4"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>