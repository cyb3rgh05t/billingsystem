<?php

/**
 * KFZ Fac Pro - Fahrzeughandel API
 * REST-API für Fahrzeughandel (Ankauf/Verkauf)
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
require_once dirname(__DIR__) . '/models/FahrzeugHandel.php';

// Auth-Check
Auth::requireAuth();

// Model initialisieren
$handelModel = new FahrzeugHandel();

// Request-Methode und Pfad ermitteln
$method = $_SERVER['REQUEST_METHOD'];
$pathInfo = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
$segments = array_filter(explode('/', $pathInfo));
$id = isset($segments[1]) ? $segments[1] : null;
$action = isset($segments[2]) ? $segments[2] : null;
$subaction = isset($segments[3]) ? $segments[3] : null;

// Input-Daten
$input = json_decode(file_get_contents('php://input'), true);

// Router
try {
    // Spezielle Routen zuerst
    if ($id === 'stats' && $action === 'dashboard') {
        // GET /api/fahrzeughandel/stats/dashboard
        $stats = $handelModel->getDashboardStats();
        echo json_encode($stats);
        exit;
    }

    if ($id === 'stats' && $action === 'monthly') {
        // GET /api/fahrzeughandel/stats/monthly?jahr=2024
        $jahr = isset($_GET['jahr']) ? intval($_GET['jahr']) : date('Y');
        $stats = $handelModel->getMonthlyStats($jahr);
        echo json_encode($stats);
        exit;
    }

    if ($id === 'options' && $action === 'kunden') {
        // GET /api/fahrzeughandel/options/kunden
        $options = $handelModel->getKundenOptions();
        echo json_encode($options);
        exit;
    }

    if ($id === 'options' && $action === 'fahrzeuge') {
        // GET /api/fahrzeughandel/options/fahrzeuge
        $options = $handelModel->getFahrzeugeOptions();
        echo json_encode($options);
        exit;
    }

    if ($id === 'export' && $action === 'csv') {
        // GET /api/fahrzeughandel/export/csv
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="fahrzeughandel_' . date('Y-m-d') . '.csv"');
        echo $handelModel->exportCsv();
        exit;
    }

    // Standard REST-Routen
    switch ($method) {
        case 'GET':
            if ($id && is_numeric($id)) {
                // GET /api/fahrzeughandel/{id}
                $result = $handelModel->getWithDetails($id);

                if ($result) {
                    echo json_encode($result);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Handel nicht gefunden']);
                }
            } else {
                // Filter und Sortierung
                if (isset($_GET['typ'])) {
                    // GET /api/fahrzeughandel?typ=ankauf
                    $result = $handelModel->getByTyp($_GET['typ']);
                } elseif (isset($_GET['status']) && $_GET['status'] === 'offen') {
                    // GET /api/fahrzeughandel?status=offen
                    $result = $handelModel->getOffene();
                } elseif (isset($_GET['kunde_id'])) {
                    // GET /api/fahrzeughandel?kunde_id=123
                    $conditions = ['kunden_id' => $_GET['kunde_id']];
                    $result = $handelModel->findWhere($conditions, 'datum DESC');
                } elseif (isset($_GET['fahrzeug_id'])) {
                    // GET /api/fahrzeughandel?fahrzeug_id=123
                    $conditions = ['fahrzeug_id' => $_GET['fahrzeug_id']];
                    $result = $handelModel->findWhere($conditions, 'datum DESC');
                } else {
                    // GET /api/fahrzeughandel
                    $orderBy = isset($_GET['sort']) ? $_GET['sort'] : 'datum DESC';
                    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : null;

                    $result = $handelModel->findAll($orderBy, $limit);
                }

                echo json_encode($result);
            }
            break;

        case 'POST':
            if ($id && $action) {
                // Spezielle Aktionen
                if ($action === 'abschliessen') {
                    // POST /api/fahrzeughandel/{id}/abschliessen
                    $result = $handelModel->update($id, [
                        'status' => 'abgeschlossen',
                        'abgeschlossen_am' => date('Y-m-d H:i:s')
                    ]);

                    if ($result['success']) {
                        echo json_encode($result);
                    } else {
                        http_response_code(400);
                        echo json_encode($result);
                    }
                } elseif ($action === 'stornieren') {
                    // POST /api/fahrzeughandel/{id}/stornieren
                    $result = $handelModel->update($id, [
                        'status' => 'storniert'
                    ]);

                    if ($result['success']) {
                        echo json_encode($result);
                    } else {
                        http_response_code(400);
                        echo json_encode($result);
                    }
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'Unbekannte Aktion']);
                }
            } else {
                // POST /api/fahrzeughandel - Neuen Handel erstellen
                if (!$input) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Keine Daten erhalten']);
                    break;
                }

                $result = $handelModel->create($input);

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
            // PUT /api/fahrzeughandel/{id}
            if (!$id || !is_numeric($id)) {
                http_response_code(400);
                echo json_encode(['error' => 'ID fehlt']);
                break;
            }

            if (!$input) {
                http_response_code(400);
                echo json_encode(['error' => 'Keine Daten erhalten']);
                break;
            }

            $result = $handelModel->update($id, $input);

            if ($result['success']) {
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode($result);
            }
            break;

        case 'DELETE':
            // DELETE /api/fahrzeughandel/{id}
            if (!$id || !is_numeric($id)) {
                http_response_code(400);
                echo json_encode(['error' => 'ID fehlt']);
                break;
            }

            // Nur offene oder stornierte Geschäfte können gelöscht werden
            $handel = $handelModel->findById($id);
            if (!$handel) {
                http_response_code(404);
                echo json_encode(['error' => 'Handel nicht gefunden']);
                break;
            }

            if ($handel['status'] === 'abgeschlossen') {
                http_response_code(400);
                echo json_encode(['error' => 'Abgeschlossene Geschäfte können nicht gelöscht werden']);
                break;
            }

            $result = $handelModel->delete($id);

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
    error_log("Fahrzeughandel API Fehler: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Interner Serverfehler']);
}
