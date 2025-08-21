<?php

/**
 * KFZ Fac Pro - Fahrzeuge API
 * REST-API für Fahrzeugverwaltung
 */

// Headers
header('Content-Type: application/json');
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
require_once dirname(__DIR__) . '/models/Fahrzeug.php';

// Auth-Check
Auth::requireAuth();

// Model initialisieren
$fahrzeugModel = new Fahrzeug();

// Request-Methode und Pfad ermitteln
$method = $_SERVER['REQUEST_METHOD'];
$pathInfo = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
$segments = array_filter(explode('/', $pathInfo));
$id = isset($segments[1]) ? intval($segments[1]) : null;
$action = isset($segments[2]) ? $segments[2] : null;

// Input-Daten
$input = json_decode(file_get_contents('php://input'), true);

// Router
try {
    switch ($method) {
        case 'GET':
            if ($id) {
                // Einzelnes Fahrzeug
                if ($action === 'service-history') {
                    // GET /api/fahrzeuge/{id}/service-history
                    $result = $fahrzeugModel->getWithServiceHistory($id);
                } elseif ($action === 'kunde') {
                    // GET /api/fahrzeuge/{id}/kunde
                    $result = $fahrzeugModel->getWithKunde($id);
                } else {
                    // GET /api/fahrzeuge/{id}
                    $result = $fahrzeugModel->findById($id);
                }

                if ($result) {
                    echo json_encode($result);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Fahrzeug nicht gefunden']);
                }
            } else {
                // Spezielle Abfragen
                if (isset($_GET['kennzeichen'])) {
                    // GET /api/fahrzeuge?kennzeichen=XX-YY-123
                    $result = $fahrzeugModel->findByKennzeichen($_GET['kennzeichen']);
                    echo json_encode($result ?: ['error' => 'Fahrzeug nicht gefunden']);
                } elseif (isset($_GET['kunde_id'])) {
                    // GET /api/fahrzeuge?kunde_id=123
                    $result = $fahrzeugModel->findByKundeId($_GET['kunde_id']);
                    echo json_encode($result);
                } elseif (isset($_GET['search'])) {
                    // GET /api/fahrzeuge?search=...
                    $result = $fahrzeugModel->search($_GET['search']);
                    echo json_encode($result);
                } elseif (isset($_GET['tuev_faellig'])) {
                    // GET /api/fahrzeuge?tuev_faellig=30
                    $tage = intval($_GET['tuev_faellig']);
                    $result = $fahrzeugModel->getTuevAuFaellig($tage);
                    echo json_encode($result);
                } elseif (isset($_GET['service_faellig'])) {
                    // GET /api/fahrzeuge?service_faellig=1
                    $result = $fahrzeugModel->getServiceFaellig();
                    echo json_encode($result);
                } elseif (isset($_GET['statistiken'])) {
                    // GET /api/fahrzeuge?statistiken=1
                    $result = $fahrzeugModel->getStatistiken();
                    echo json_encode($result);
                } else {
                    // GET /api/fahrzeuge
                    $orderBy = isset($_GET['sort']) ? $_GET['sort'] : 'kennzeichen';
                    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : null;

                    $result = $fahrzeugModel->findAll($orderBy, $limit);
                    echo json_encode($result);
                }
            }
            break;

        case 'POST':
            // POST /api/fahrzeuge
            if (!$input) {
                http_response_code(400);
                echo json_encode(['error' => 'Keine Daten erhalten']);
                break;
            }

            $result = $fahrzeugModel->create($input);

            if ($result['success']) {
                http_response_code(201);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode($result);
            }
            break;

        case 'PUT':
            // PUT /api/fahrzeuge/{id}
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'ID fehlt']);
                break;
            }

            if (!$input) {
                http_response_code(400);
                echo json_encode(['error' => 'Keine Daten erhalten']);
                break;
            }

            // Spezielle Updates
            if ($action === 'kilometerstand') {
                // PUT /api/fahrzeuge/{id}/kilometerstand
                $result = $fahrzeugModel->update($id, [
                    'kilometerstand' => $input['kilometerstand']
                ]);
            } else {
                // Standard Update
                $result = $fahrzeugModel->update($id, $input);
            }

            if ($result['success']) {
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode($result);
            }
            break;

        case 'DELETE':
            // DELETE /api/fahrzeuge/{id}
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'ID fehlt']);
                break;
            }

            $result = $fahrzeugModel->delete($id);

            if ($result['success']) {
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode($result);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Methode nicht erlaubt']);
            break;
    }
} catch (Exception $e) {
    error_log("Fahrzeuge API Fehler: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Interner Serverfehler']);
}
