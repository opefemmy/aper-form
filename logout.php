<?php
require_once 'config.php';

startSession();

// Destroy session
session_destroy();

// Redirect to login
redirect(SITE_URL . '/login.php');