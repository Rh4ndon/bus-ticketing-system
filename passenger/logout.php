<?php
session_start();

// Destroy all session data
$_SESSION = [];
session_unset();
session_destroy();

// Redirect to passenger login
header("Location: passenger_login.php");
exit;
?>
