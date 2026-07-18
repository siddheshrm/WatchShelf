<?php
session_start();

// Ensure only authenticated administrators can access protected admin pages
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../admin/login.php");
    exit();
}
?>