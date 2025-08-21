<?php

/**
 * KFZ Fac Pro - Auth Status API
 * Gibt den aktuellen Authentifizierungsstatus zurück
 */

// Headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Bei OPTIONS-Request direkt beenden
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Nur GET erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Methode nicht erlaubt']);
    exit;
}

// Auth-Klasse laden
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';

// Status prüfen
$user = Auth::getCurrentUser();

if ($user) {
    echo json_encode([
        'authenticated' => true,
        'user' => $user,
        'session' => [
            'login_time' => $_SESSION['login_time'] ?? null,
            'last_activity' => $_SESSION['last_activity'] ?? null
        ]
    ]);
} else {
    echo json_encode([
        'authenticated' => false,
        'user' => null
    ]);
}
