<?php

/**
 * KFZ Fac Pro - Logout API
 */

// Headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Bei OPTIONS-Request direkt beenden
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Auth-Klasse laden
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';

// Logout durchführen
$result = Auth::logout();

// Bei GET-Request zur Login-Seite weiterleiten
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Location: /login');
    exit;
}

// Bei POST-Request JSON zurückgeben
echo json_encode($result);
