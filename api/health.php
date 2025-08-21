<?php

/**
 * KFZ Fac Pro - Health Check API
 * Öffentlicher Health-Check Endpunkt
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

// Keine Auth erforderlich für Health-Check
require_once dirname(__DIR__) . '/config/database.php';

try {
    $db = Database::getInstance()->getConnection();

    $health = [
        'status' => 'healthy',
        'timestamp' => date('Y-m-d H:i:s'),
        'version' => '2.0.0',
        'authenticated' => false,
        'database' => false
    ];

    // Session prüfen
    session_start();
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
        $health['authenticated'] = true;
        $health['user'] = $_SESSION['username'] ?? 'unknown';
    }

    // Datenbank-Check
    try {
        $stmt = $db->query("SELECT 1");
        if ($stmt) {
            $health['database'] = true;

            // Tabellen-Count
            $stmt = $db->query("SELECT COUNT(*) as count FROM sqlite_master WHERE type='table'");
            $result = $stmt->fetch();
            $health['tables'] = $result['count'];
        }
    } catch (Exception $e) {
        $health['status'] = 'degraded';
        $health['database_error'] = $e->getMessage();
    }

    echo json_encode($health);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'timestamp' => date('Y-m-d H:i:s'),
        'error' => $e->getMessage()
    ]);
}
