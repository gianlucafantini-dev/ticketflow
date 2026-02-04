<?php
session_start();
require_once 'config/conf_db.php'; 

$isLoggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TicketFlow - Simple Helpdesk System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 100px 0 80px;
        }
        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .status-badge {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 10px 15px;
            border-radius: 5px;
            font-size: 0.85rem;
            z-index: 1000;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">
            🎫 <strong>TicketFlow</strong>
        </a>
        <div class="ms-auto">
            <?php if ($isLoggedIn): ?>
                <?php 
                // Check if user is admin or agent
                $is_admin = ($_SESSION['user_role'] ?? 'user') === 'admin';
                $is_agent = in_array($_SESSION['user_role'] ?? 'user', ['agent', 'admin']);
                ?>
                
                <?php if ($is_admin || $is_agent): ?>
                    <a href="admin/index.php" class="btn btn-warning btn-sm me-2">
                        👑 Admin Panel
                    </a>
                <?php endif; ?>
                
                <a href="tickets/index.php" class="btn btn-outline-light btn-sm me-2">My Tickets</a>
                <a href="tickets/create.php" class="btn btn-success btn-sm me-2">New Ticket</a>
                <a href="auth/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
            <?php else: ?>
                <a href="auth/login.php" class="btn btn-outline-light btn-sm me-2">Login</a>
                <a href="auth/register.php" class="btn btn-light btn-sm">Sign Up</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

    <!-- Hero Section -->
    <div class="hero">
        <div class="container text-center">
            <h1 class="display-2 fw-bold mb-4">TicketFlow</h1>
            <p class="lead fs-3 mb-2">Simple & Efficient Helpdesk System</p>
            
            <?php if (!$isLoggedIn): ?>
                <div class="mt-5">
                    <a href="auth/register.php" class="btn btn-light btn-lg px-5 me-3">
                        Get Started Free
                    </a>
                    <a href="auth/login.php" class="btn btn-outline-light btn-lg px-5">
                        Login
                    </a>
                </div>
            <?php else: ?>
                <div class="mt-5">
                    <a href="tickets/create.php" class="btn btn-light btn-lg px-5 me-3">
                        Create Ticket
                    </a>
                    <a href="tickets/index.php" class="btn btn-outline-light btn-lg px-5">
                        View All Tickets
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Features Section -->
    <div class="container my-5 py-5">
        <div class="row text-center g-4">
            <div class="col-md-4">
                <div class="p-4">
                    <div class="feature-icon">🎯</div>
                    <h3 class="h4 fw-bold">Priority Management</h3>
                    <p class="text-muted">Organize tickets by urgency: Low, Medium, High, Urgent</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-4">
                    <div class="feature-icon">💬</div>
                    <h3 class="h4 fw-bold">Real-time Updates</h3>
                    <p class="text-muted">Comments, status tracking, and activity timeline</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-4">
                    <div class="feature-icon">📊</div>
                    <h3 class="h4 fw-bold">Simple Dashboard</h3>
                    <p class="text-muted">Clear overview with stats and quick actions</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tech Stack -->
    <div class="bg-light py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h2 class="fw-bold mb-3">Built with Modern Tech</h2>
                    <p class="text-muted mb-4">Simple, efficient, and reliable stack for fast development and easy maintenance.</p>
                    <ul class="list-unstyled">
                        <li class="mb-2">✅ <strong>PHP 8.3</strong> - Modern backend</li>
                        <li class="mb-2">✅ <strong>MySQL</strong> - Reliable database</li>
                        <li class="mb-2">✅ <strong>Bootstrap 5</strong> - Responsive UI</li>
                        <li class="mb-2">✅ <strong>No framework</strong> - Learn fundamentals</li>
                    </ul>
                </div>
                <div class="col-md-6 text-center">
                    <div class="p-5 bg-white rounded shadow-sm">
                        <h4 class="mb-4">Project Status</h4>
                        <div class="mb-3">
                            <span class="badge bg-primary fs-6">v0.1 Alpha</span>
                        </div>
                        <p class="text-muted mb-3">In active development</p>
                        <small class="text-muted">Started February 2026</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container text-center">
            <p class="mb-2">TicketFlow - Built with ❤️ by Gian Luca Fantini</p>
            <p class="mb-0 small text-muted">
                <a href="https://github.com/yourusername/ticketflow" class="text-muted text-decoration-none">
                    View on GitHub
                </a>
            </p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>