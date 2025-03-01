<?php
session_start();

// Destroy the session
$_SESSION = array();
session_destroy();

// Redirect to login page
header('Location: login.php');
exit;