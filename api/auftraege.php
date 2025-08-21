<?php

/**
 * KFZ Fac Pro - Aufträge API
 * REST-API für Auftragsverwaltung
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
require_once dirname(__DIR__) . '/models/Auftrag.php';

// Auth-Check
Auth::requireAuth();

// Model initialisieren
$auftragModel = new Auftrag();

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
                // Einzelner Auftrag
                if ($action === 'details') {
                    // GET /api/auftraege/{id}/details
                    $result = $auftragModel->getWithDetails($id);
                } else {
                    // GET /api/auftraege/{id}
                    $result = $auftragModel->findById($id);
                }

                if ($result) {
                    // Positionen dekodieren falls nötig
                    if (isset($result['positionen']) && is_string($result['positionen'])) {
                        $result['positionen'] = json_decode($result['positionen'], true);
                    }
                    echo json_encode($result);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Auftrag nicht gefunden']);
                }
            } else {
                // Spezielle Abfragen
                if (isset($_GET['status'])) {
                    // GET /api/auftraege?status=offen
                    $result = $auftragModel->getByStatus($_GET['status']);
                } elseif (isset($_GET['offen'])) {
                    // GET /api/auftraege?offen=1
                    $result = $auftragModel->getOffene();
                } elseif (isset($_GET['heute'])) {
                    // GET /api/auftraege?heute=1
                    $result = $auftragModel->getHeuteFaellig();
                } elseif (isset($_GET['ueberfaellig'])) {
                    // GET /api/auftraege?ueberfaellig=1
                    $result = $auftragModel->getUeberfaellig();
                } elseif (isset($_GET['kunde_id'])) {
                    // GET /api/auftraege?kunde_id=123
                    $result = $auftragModel->getByKunde($_GET['kunde_id']);
                } elseif (isset($_GET['fahrzeug_id'])) {
                    // GET /api/auftraege?fahrzeug_id=123
                    $result = $auftragModel->getByFahrzeug($_GET['fahrzeug_id']);
                } elseif (isset($_GET['statistiken'])) {
                    // GET /api/auftraege?statistiken=1&monat=12&jahr=2024
                    $monat = isset($_GET['monat']) ? intval($_GET['monat']) : null;
                    $jahr = isset($_GET['jahr']) ? intval($_GET['jahr']) : date('Y');
                    $result = $auftragModel->getStatistiken($monat, $jahr);
                } else {
                    // GET /api/auftraege
                    $orderBy = isset($_GET['sort']) ? $_GET['sort'] : 'datum DESC';
                    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : null;

                    $result = $auftragModel->findAll($orderBy, $limit);
                }

                // Positionen dekodieren
                foreach ($result as &$item) {
                    if (isset($item['positionen']) && is_string($item['positionen'])) {
                        $item['positionen'] = json_decode($item['positionen'], true);
                    }
                }

                echo json_encode($result);
            }
            break;

        case 'POST':
            if ($id && $action) {
                // Spezielle Aktionen
                if ($action === 'status') {
                    // POST /api/auftraege/{id}/status
                    if (empty($input['status'])) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Status fehlt']);
                        break;
                    }
                    $result = $auftragModel->changeStatus($id, $input['status']);
                } elseif ($action === 'rechnung') {
                    // POST /api/auftraege/{id}/rechnung
                    $result = $auftragModel->createRechnung($id);
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'Unbekannte Aktion']);
                    break;
                }

                if ($result['success']) {
                    echo json_encode($result);
                } else {
                    http_response_code(400);
                    echo json_encode($result);
                }
            } else {
                // POST /api/auftraege - Neuen Auftrag erstellen
                if (!$input) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Keine Daten erhalten']);
                    break;
                }

                $result = $auftragModel->create($input);

                if ($result['success']) {
                    http_response_code(201);
                    echo json_encode($result);
                } else {
                    http_response_code(400);
                    echo json_encode($result);
                }
            }
            break;

        case 'PUT':
            // PUT /api/auftraege/{id}
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

            $result = $auftragModel->update($id, $input);

            if ($result['success']) {
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode($result);
            }
            break;

        case 'DELETE':
            // DELETE /api/auftraege/{id}
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'ID fehlt']);
                break;
            }

            $result = $auftragModel->delete($id);

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
    error_log("Aufträge API Fehler: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Interner Serverfehler']);
}
