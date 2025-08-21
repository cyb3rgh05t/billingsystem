<?php

/**
 * Logout Handler
 * Logs out the user and redirects to login page
 */

require_once 'includes/auth.php';

// Perform logout
$auth->logout();

// Redirect to login page with success message
session_start();
$_SESSION['logout_message'] = 'Sie wurden erfolgreich abgemeldet.';

header('Location: login.php');
exit;
