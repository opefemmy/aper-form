<?php
require_once 'config.php';

startSession();

// Destroy session
session_destroy();

// Redirect to staff login
redirect(SITE_URL . '/staff-login.php');