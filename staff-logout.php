<?php
require_once 'config.php';

startSession();

// Destroy all session data
$_SESSION = array();
session_destroy();

// Redirect to unified login
header("Location: unified-login.php");
exit;