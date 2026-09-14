<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Destroy existing session and clear all sensitive data.
 */
$_SESSION = array();
session_destroy();

header("Location: login.php");
exit();
?>
