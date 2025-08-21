<?php

/**
 * KFZ Fac Pro - Einstellungen API (VEREINFACHT)
 * Einfach und robust
 */

// Fehler als JSON ausgeben
header('Content-Type: application/json');
ini_set('display_errors', 0);

// CORS Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Bei OPTIONS-Request direkt beenden
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Includes
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/models/Einstellung.php';

// Auth-Check
Auth::requireAuth();

// Model initialisieren
$einstellungModel = new Einstellung();

// Request-Methode und Pfad ermitteln
$method = $_SERVER['REQUEST_METHOD'];
$pathInfo = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
$segments = array_filter(explode('/', $pathInfo));
$key = isset($segments[1]) ? $segments[1] : null;

// Input-Daten
$input = json_decode(file_get_contents('php://input'), true);

// Router (VEREINFACHT)
try {
    switch ($method) {
        case 'GET':
            if ($key) {
                // GET /api/einstellungen/{key} - Einzelne Einstellung
                $value = $einstellungModel->get($key);
                echo json_encode(['key' => $key, 'value' => $value]);
            } else {
                // GET /api/einstellungen - Alle als Array (für Frontend)
                $settings = $einstellungModel->getAll();
                $arrayFormat = [];
                foreach ($settings as $k => $v) {
                    $arrayFormat[] = [
                        'key' => $k,
                        'value' => $v
                    ];
                }
                echo json_encode($arrayFormat);
            }
            break;

        case 'PUT':
            if (!$input) {
                http_response_code(400);
                echo json_encode(['error' => 'Keine Daten erhalten']);
                break;
            }

            // Einfach speichern - egal welches Format
            $result = $einstellungModel->setBulk($input);
            echo json_encode($result);
            break;

        case 'POST':
            // POST ist wie PUT (für Kompatibilität)
            if (!$input) {
                http_response_code(400);
                echo json_encode(['error' => 'Keine Daten erhalten']);
                break;
            }

            $result = $einstellungModel->setBulk($input);
            echo json_encode($result);
            break;

        case 'DELETE':
            // DELETE /api/einstellungen/{key}
            if (!$key) {
                http_response_code(400);
                echo json_encode(['error' => 'Key fehlt']);
                break;
            }

            // Admin-Check für Löschen
            if (!Auth::isAdmin()) {
                http_response_code(403);
                echo json_encode(['error' => 'Keine Berechtigung']);
                break;
            }

            $result = $einstellungModel->remove($key);
            echo json_encode($result);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Methode nicht erlaubt']);
            break;
    }
} catch (Exception $e) {
    error_log("Einstellungen API Fehler: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Interner Serverfehler']);
}
