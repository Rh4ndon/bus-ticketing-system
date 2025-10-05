<?php
require_once "../admin/config.php";
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();
// ✅ Redirect to login page with logout message
header("Location: driver_login.php?logout=1");
exit();
?>