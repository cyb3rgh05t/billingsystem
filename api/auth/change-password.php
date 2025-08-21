<?php

/**
 * KFZ Fac Pro - Change Password API
 */

// Headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Bei OPTIONS-Request direkt beenden
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Nur POST erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Methode nicht erlaubt']);
    exit;
}

// Auth-Klasse laden
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';

// Auth-Check
Auth::requireAuth();

// Input-Daten
$input = json_decode(file_get_contents('php://input'), true);

// Validierung
if (empty($input['oldPassword']) || empty($input['newPassword'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Altes und neues Passwort erforderlich']);
    exit;
}

// Passwort-Länge prüfen
if (strlen($input['newPassword']) < 6) {
    http_response_code(400);
    echo json_encode(['error' => 'Neues Passwort muss mindestens 6 Zeichen lang sein']);
    exit;
}

// Passwort ändern
$userId = $_SESSION['user_id'];
$result = Auth::changePassword($userId, $input['oldPassword'], $input['newPassword']);

if ($result['success']) {
    echo json_encode([
        'success' => true,
        'message' => 'Passwort erfolgreich geändert'
    ]);
} else {
    http_response_code(400);
    echo json_encode($result);
}
