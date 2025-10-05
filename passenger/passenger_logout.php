<?php
// Start the session first
session_start();

require_once "../admin/config.php";

session_start();

// Unset all session variables
$_SESSION = array();
// Destroy the session
session_destroy();
// Redirect to login page with logout success message
header("Location: passenger_login.php?logout=success");
exit();
?>