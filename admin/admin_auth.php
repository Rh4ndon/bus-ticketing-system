<?php
// admin_auth.php - Include this file in all admin pages to check authentication

session_start();
require_once 'config.php';

function checkAdminAuth() {
    // Check if admin is logged in
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        redirectTo('admin_login.php');
    }
    
    // Check session timeout
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > SESSION_TIMEOUT) {
        // Session expired
        destroyAdminSession();
        redirectTo('admin_login.php?expired=1');
    }
    
    // Verify session token in database
    if (isset($_SESSION['admin_session_token'])) {
        $db = new Database();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("
            SELECT * FROM admin_sessions 
            WHERE session_id = ? AND admin_username = ? AND expires_at > NOW()
        ");
        $stmt->execute([$_SESSION['admin_session_token'], $_SESSION['admin_username']]);
        
        if (!$stmt->fetch()) {
            // Invalid or expired session token
            destroyAdminSession();
            redirectTo('admin_login.php?invalid=1');
        }
        
        // Update session expiry
        $stmt = $conn->prepare("
            UPDATE admin_sessions 
            SET expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND) 
            WHERE session_id = ?
        ");
        $stmt->execute([SESSION_TIMEOUT, $_SESSION['admin_session_token']]);
    }
    
    // Update last activity time
    $_SESSION['login_time'] = time();
}

function destroyAdminSession() {
    if (isset($_SESSION['admin_session_token'])) {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Remove session from database
        $stmt = $conn->prepare("DELETE FROM admin_sessions WHERE session_id = ?");
        $stmt->execute([$_SESSION['admin_session_token']]);
    }
    
    // Destroy PHP session
    session_destroy();
    session_start(); // Start new session for redirect messages
}

function getAdminUsername() {
    return $_SESSION['admin_username'] ?? '';
}

function logAdminActivity($action, $details = '') {
    // Optional: Log admin activities for audit trail
    $db = new Database();
    $conn = $db->getConnection();
    
    try {
        // Create admin_activity_log table if it doesn't exist
        $conn->exec("
            CREATE TABLE IF NOT EXISTS admin_activity_log (
                log_id INT PRIMARY KEY AUTO_INCREMENT,
                admin_username VARCHAR(50) NOT NULL,
                action VARCHAR(100) NOT NULL,
                details TEXT,
                ip_address VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $stmt = $conn->prepare("
            INSERT INTO admin_activity_log (admin_username, action, details, ip_address) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            getAdminUsername(),
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
    } catch (Exception $e) {
        // Log error but don't break the application
        error_log("Admin activity logging failed: " . $e->getMessage());
    }
}

// Auto-check authentication when this file is included
checkAdminAuth();
?>