<?php
session_start();
require_once 'config.php';

// Clean up admin session
if (isset($_SESSION['admin_session_token'])) {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Remove session from database
    $stmt = $conn->prepare("DELETE FROM admin_sessions WHERE session_id = ?");
    $stmt->execute([$_SESSION['admin_session_token']]);
    
    // Log logout activity
    if (isset($_SESSION['admin_username'])) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO admin_activity_log (admin_username, action, ip_address) 
                VALUES (?, 'Logout', ?)
            ");
            $stmt->execute([
                $_SESSION['admin_username'],
                $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
        } catch (Exception $e) {
            // Ignore logging errors during logout
        }
    }
}

// Destroy session
session_destroy();

// Redirect to login page
redirectTo('admin_login.php?logged_out=1');
?>