<?php

/**
 * KFZ Fac Pro - Kunden API
 * REST-API für Kundenverwaltung
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
require_once dirname(__DIR__) . '/models/Kunde.php';

// Auth-Check
Auth::requireAuth();

// Model initialisieren
$kundeModel = new Kunde();

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
                // Einzelner Kunde
                if ($action === 'fahrzeuge') {
                    // GET /api/kunden/{id}/fahrzeuge
                    $result = $kundeModel->getWithFahrzeuge($id);
                } elseif ($action === 'auftraege') {
                    // GET /api/kunden/{id}/auftraege
                    $result = $kundeModel->getWithAuftraege($id);
                } elseif ($action === 'rechnungen') {
                    // GET /api/kunden/{id}/rechnungen
                    $result = $kundeModel->getWithRechnungen($id);
                } elseif ($action === 'umsatz') {
                    // GET /api/kunden/{id}/umsatz
                    $result = $kundeModel->getUmsatzStatistik($id);
                } else {
                    // GET /api/kunden/{id}
                    $result = $kundeModel->findById($id);
                }

                if ($result) {
                    echo json_encode($result);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Kunde nicht gefunden']);
                }
            } else {
                // Alle Kunden oder Suche
                if (isset($_GET['search'])) {
                    // GET /api/kunden?search=...
                    $result = $kundeModel->search($_GET['search']);
                } elseif (isset($_GET['export']) && $_GET['export'] === 'csv') {
                    // GET /api/kunden?export=csv
                    header('Content-Type: text/csv');
                    header('Content-Disposition: attachment; filename="kunden_' . date('Y-m-d') . '.csv"');
                    echo $kundeModel->exportCsv();
                    exit;
                } else {
                    // GET /api/kunden
                    $orderBy = isset($_GET['sort']) ? $_GET['sort'] : 'nachname, vorname';
                    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : null;

                    $result = $kundeModel->findAll($orderBy, $limit);

                    // Sicherstellen, dass es ein Array ist (auch wenn leer)
                    if (!is_array($result)) {
                        $result = [];
                    }
                }
                echo json_encode($result);
            }
            break;

        case 'POST':
            // POST /api/kunden
            if (!$input) {
                http_response_code(400);
                echo json_encode(['error' => 'Keine Daten erhalten']);
                break;
            }

            $result = $kundeModel->create($input);

            if ($result['success']) {
                http_response_code(201);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode($result);
            }
            break;

        case 'PUT':
            // PUT /api/kunden/{id}
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

            $result = $kundeModel->update($id, $input);

            if ($result['success']) {
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode($result);
            }
            break;

        case 'DELETE':
            // DELETE /api/kunden/{id}
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'ID fehlt']);
                break;
            }

            $result = $kundeModel->delete($id);

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
    error_log("Kunden API Fehler: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Interner Serverfehler']);
}
