<?php

/**
 * KFZ Fac Pro - Rechnungen API
 * REST-API für Rechnungsverwaltung
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
require_once dirname(__DIR__) . '/models/Rechnung.php';

// Auth-Check
Auth::requireAuth();

// Model initialisieren
$rechnungModel = new Rechnung();

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
                // Einzelne Rechnung
                if ($action === 'pdf') {
                    // GET /api/rechnungen/{id}/pdf
                    // PDF-Generierung würde hier erfolgen
                    http_response_code(501);
                    echo json_encode(['error' => 'PDF-Generierung noch nicht implementiert']);
                } elseif ($action === 'print') {
                    // GET /api/rechnungen/{id}/print
                    $result = $rechnungModel->getWithKunde($id);
                    if ($result) {
                        // Positionen dekodieren
                        if (isset($result['positionen']) && is_string($result['positionen'])) {
                            $result['positionen'] = json_decode($result['positionen'], true);
                        }
                        echo json_encode($result);
                    } else {
                        http_response_code(404);
                        echo json_encode(['error' => 'Rechnung nicht gefunden']);
                    }
                } else {
                    // GET /api/rechnungen/{id}
                    $result = $rechnungModel->findById($id);
                    if ($result) {
                        // Positionen dekodieren
                        if (isset($result['positionen']) && is_string($result['positionen'])) {
                            $result['positionen'] = json_decode($result['positionen'], true);
                        }
                        echo json_encode($result);
                    } else {
                        http_response_code(404);
                        echo json_encode(['error' => 'Rechnung nicht gefunden']);
                    }
                }
            } else {
                // Spezielle Abfragen
                if (isset($_GET['offen'])) {
                    // GET /api/rechnungen?offen=1
                    $result = $rechnungModel->getOffene();
                } elseif (isset($_GET['ueberfaellig'])) {
                    // GET /api/rechnungen?ueberfaellig=1
                    $result = $rechnungModel->getUeberfaellige();
                } elseif (isset($_GET['status'])) {
                    // GET /api/rechnungen?status=bezahlt
                    $result = $rechnungModel->getByStatus($_GET['status']);
                } elseif (isset($_GET['statistiken'])) {
                    // GET /api/rechnungen?statistiken=1&monat=12&jahr=2024
                    $monat = isset($_GET['monat']) ? intval($_GET['monat']) : null;
                    $jahr = isset($_GET['jahr']) ? intval($_GET['jahr']) : date('Y');
                    $result = $rechnungModel->getStatistiken($monat, $jahr);
                    echo json_encode($result);
                    break;
                } elseif (isset($_GET['anzahlungs_stats'])) {
                    // GET /api/rechnungen?anzahlungs_stats=1
                    $sql = "SELECT 
                                COUNT(CASE WHEN anzahlung_aktiv = 1 THEN 1 END) as mit_anzahlung,
                                COUNT(CASE WHEN anzahlung_aktiv = 0 OR anzahlung_aktiv IS NULL THEN 1 END) as ohne_anzahlung,
                                SUM(anzahlung_betrag) as gesamt_anzahlungen,
                                AVG(CASE WHEN anzahlung_aktiv = 1 THEN anzahlung_betrag END) as durchschnitt_anzahlung
                            FROM rechnungen 
                            WHERE storniert = 0";

                    $stmt = $rechnungModel->db->query($sql);
                    $result = $stmt->fetch();
                    echo json_encode($result);
                    break;
                } else {
                    // GET /api/rechnungen
                    $orderBy = isset($_GET['sort']) ? $_GET['sort'] : 'datum DESC';
                    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : null;

                    $result = $rechnungModel->findAll($orderBy, $limit);
                }

                // Positionen dekodieren
                if (is_array($result)) {
                    foreach ($result as &$item) {
                        if (isset($item['positionen']) && is_string($item['positionen'])) {
                            $item['positionen'] = json_decode($item['positionen'], true);
                        }
                    }
                }

                echo json_encode($result);
            }
            break;

        case 'POST':
            if ($id && $action) {
                // Spezielle Aktionen
                if ($action === 'bezahlt') {
                    // POST /api/rechnungen/{id}/bezahlt
                    $zahlungsart = isset($input['zahlungsart']) ? $input['zahlungsart'] : 'Überweisung';
                    $result = $rechnungModel->markAsPaid($id, $zahlungsart);
                } elseif ($action === 'storno') {
                    // POST /api/rechnungen/{id}/storno
                    $grund = isset($input['grund']) ? $input['grund'] : '';
                    $result = $rechnungModel->stornieren($id, $grund);
                } elseif ($action === 'anzahlung') {
                    // POST /api/rechnungen/{id}/anzahlung
                    if (empty($input['betrag'])) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Betrag fehlt']);
                        break;
                    }
                    $datum = isset($input['datum']) ? $input['datum'] : null;
                    $result = $rechnungModel->addAnzahlung($id, $input['betrag'], $datum);
                } elseif ($action === 'mahnung') {
                    // POST /api/rechnungen/{id}/mahnung
                    $result = $rechnungModel->createMahnung($id);
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'Unbekannte Aktion']);
                    break;
                }

                if (isset($result)) {
                    if ($result['success']) {
                        echo json_encode($result);
                    } else {
                        http_response_code(400);
                        echo json_encode($result);
                    }
                }
            } else {
                // POST /api/rechnungen - Neue Rechnung erstellen
                if (!$input) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Keine Daten erhalten']);
                    break;
                }

                $result = $rechnungModel->create($input);

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
            // PUT /api/rechnungen/{id}
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
            if ($action === 'anzahlung') {
                // PUT /api/rechnungen/{id}/anzahlung
                $data = [
                    'anzahlung_aktiv' => isset($input['anzahlung_aktiv']) ? $input['anzahlung_aktiv'] : 1,
                    'anzahlung_betrag' => $input['anzahlung_betrag'],
                    'anzahlung_datum' => isset($input['anzahlung_datum']) ? $input['anzahlung_datum'] : date('Y-m-d'),
                    'restbetrag' => $input['restbetrag']
                ];
                $result = $rechnungModel->update($id, $data);
            } else {
                // Standard Update
                $result = $rechnungModel->update($id, $input);
            }

            if ($result['success']) {
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode($result);
            }
            break;

        case 'DELETE':
            // DELETE /api/rechnungen/{id}
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'ID fehlt']);
                break;
            }

            // Nur stornierte oder unbezahlte Rechnungen können gelöscht werden
            $rechnung = $rechnungModel->findById($id);
            if (!$rechnung) {
                http_response_code(404);
                echo json_encode(['error' => 'Rechnung nicht gefunden']);
                break;
            }

            if ($rechnung['bezahlt'] && !$rechnung['storniert']) {
                http_response_code(400);
                echo json_encode(['error' => 'Bezahlte Rechnungen können nicht gelöscht werden']);
                break;
            }

            $result = $rechnungModel->delete($id);

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
    error_log("Rechnungen API Fehler: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Interner Serverfehler']);
}
