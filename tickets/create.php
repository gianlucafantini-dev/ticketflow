<?php
session_start();
require_once '../config/conf_db.php';
require_once '../includes/auth_check.php';

$errors = [];
$success = '';

// Get priorities and statuses for form
$conn = getDBConnection();

$priorities = $conn->query("SELECT * FROM priorities ORDER BY level ASC")->fetch_all(MYSQLI_ASSOC);
$statuses = $conn->query("SELECT * FROM statuses")->fetch_all(MYSQLI_ASSOC);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priority_id = intval($_POST['priority_id'] ?? 0);
    
    // Validation
    if (empty($title)) {
        $errors[] = 'Title is required';
    } elseif (strlen($title) < 5) {
        $errors[] = 'Title must be at least 5 characters';
    }
    
    if (empty($description)) {
        $errors[] = 'Description is required';
    } elseif (strlen($description) < 10) {
        $errors[] = 'Description must be at least 10 characters';
    }
    
    if ($priority_id <= 0) {
        $errors[] = 'Please select a priority';
    }
    
    // Create ticket
    if (empty($errors)) {
        $stmt = $conn->prepare("
            INSERT INTO tickets (title, description, priority_id, status_id, user_id) 
            VALUES (?, ?, ?, 1, ?)
        ");
        
        $stmt->bind_param("ssii", $title, $description, $priority_id, $current_user_id);
        
        if ($stmt->execute()) {
            $ticket_id = $stmt->insert_id;
            $stmt->close();
            closeDBConnection($conn);
            
            header("Location: view.php?id=$ticket_id");
            exit;
        } else {
            $errors[] = 'Failed to create ticket. Please try again.';
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
    <title>Create Ticket - TicketFlow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="../index.php">🎫 TicketFlow</a>
            <div class="ms-auto">
                <span class="text-white me-3">👤 <?= htmlspecialchars($current_user_name) ?></span>
                <a href="index.php" class="btn btn-outline-light btn-sm me-2">My Tickets</a>
                <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">➕ Create New Ticket</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= htmlspecialchars($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="title" class="form-label">Ticket Title *</label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" 
                                       placeholder="Brief description of the issue" required>
                                <small class="text-muted">Minimum 5 characters</small>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description *</label>
                                <textarea class="form-control" id="description" name="description" 
                                          rows="6" placeholder="Detailed description of the issue..." required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                                <small class="text-muted">Minimum 10 characters. Be as detailed as possible.</small>
                            </div>

                            <div class="mb-4">
                                <label for="priority_id" class="form-label">Priority *</label>
                                <select class="form-select" id="priority_id" name="priority_id" required>
                                    <option value="">Select priority...</option>
                                    <?php foreach ($priorities as $priority): ?>
                                        <option value="<?= $priority['id'] ?>" 
                                                <?= (isset($_POST['priority_id']) && $_POST['priority_id'] == $priority['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($priority['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    Create Ticket
                                </button>
                                <a href="index.php" class="btn btn-outline-secondary">
                                    Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>