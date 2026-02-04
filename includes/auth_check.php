<?php
/**
 * Authentication Check
 * Include this at the top of pages that require login
 * 
 * Usage:
 * require_once '../includes/auth_check.php';
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Not logged in - redirect to login page
    header('Location: ../auth/login.php');
    exit;
}

// Make user data easily accessible in all pages
$current_user_id = $_SESSION['user_id'];
$current_user_name = $_SESSION['user_name'];
$current_user_email = $_SESSION['user_email'];
$current_user_role = $_SESSION['user_role'];

// Optional: Check if user is admin
$is_admin = ($current_user_role === 'admin');
$is_agent = ($current_user_role === 'agent' || $current_user_role === 'admin');
?>