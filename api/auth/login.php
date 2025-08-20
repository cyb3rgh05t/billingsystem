<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Bei OPTIONS-Request direkt beenden
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Session starten
session_start();

// POST-Daten lesen
$input = json_decode(file_get_contents('php://input'), true);

// Standard-Login (später durch echte Authentifizierung ersetzen)
if ($input['username'] === 'admin' && $input['password'] === 'admin123') {
    $_SESSION['logged_in'] = true;
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'admin';
    $_SESSION['role'] = 'admin';
    $_SESSION['login_time'] = time();

    echo json_encode([
        'success' => true,
        'message' => 'Erfolgreich angemeldet',
        'user' => [
            'id' => 1,
            'username' => 'admin',
            'role' => 'admin'
        ],
        'session_id' => session_id()
    ]);
} else {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Ungültige Anmeldedaten'
    ]);
}
