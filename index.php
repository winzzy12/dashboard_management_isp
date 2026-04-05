<?php
session_start();

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: views/login.php");
    exit();
}

// Redirect to dashboard view
header("Location: views/dashboard/index.php");
exit();
?>