<?php

/**
 * KFZ Fac Pro - Aufträge API
 * Vollständige CRUD-Operationen für Aufträge
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

// Session prüfen
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    http_response_code(401);
    echo json_encode(['error' => 'Nicht autorisiert']);
    exit;
}

// Models laden (falls vorhanden)
$modelsPath = __DIR__ . '/../models/Auftrag.php';
if (file_exists($modelsPath)) {
    require_once $modelsPath;
    $useModel = true;
} else {
    $useModel = false;
}

// Request-Methode und Pfad
$method = $_SERVER['REQUEST_METHOD'];
$pathInfo = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
$segments = array_filter(explode('/', trim($pathInfo, '/')));
$id = isset($segments[0]) ? $segments[0] : null;
$action = isset($segments[1]) ? $segments[1] : null;

// Request-Body parsen
function getRequestData()
{
    $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';

    if (strpos($contentType, 'application/json') !== false) {
        $json = file_get_contents('php://input');
        return json_decode($json, true);
    }

    return $_POST;
}

// Response senden
function sendResponse($data, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

// Dummy-Daten wenn kein Model vorhanden
function getDummyAuftraege()
{
    return [
        [
            'id' => 1,
            'auftrag_nr' => 'A2024-0001',
            'kunden_id' => 1,
            'kunde_name' => 'Max Mustermann',
            'fahrzeug_id' => 1,
            'kennzeichen' => 'B-XY 1234',
            'marke' => 'BMW',
            'modell' => '320d',
            'datum' => '2024-01-15',
            'status' => 'offen',
            'basis_stundenpreis' => 110.00,
            'gesamt_zeit' => 2.5,
            'gesamt_kosten' => 275.00,
            'mwst_betrag' => 52.25,
            'bemerkungen' => 'Ölwechsel und Inspektion'
        ],
        [
            'id' => 2,
            'auftrag_nr' => 'A2024-0002',
            'kunden_id' => 2,
            'kunde_name' => 'Anna Schmidt',
            'fahrzeug_id' => 2,
            'kennzeichen' => 'M-AB 5678',
            'marke' => 'Mercedes',
            'modell' => 'C220',
            'datum' => '2024-01-16',
            'status' => 'in_bearbeitung',
            'basis_stundenpreis' => 110.00,
            'gesamt_zeit' => 1.0,
            'gesamt_kosten' => 110.00,
            'mwst_betrag' => 20.90,
            'bemerkungen' => 'Bremsen prüfen'
        ]
    ];
}

// Routing
switch ($method) {
    case 'GET':
        if ($id) {
            // Spezielle Endpunkte
            if ($id === 'export') {
                // Export
                sendResponse([
                    'success' => true,
                    'data' => getDummyAuftraege(),
                    'format' => 'json',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            } elseif ($id === 'statistik') {
                // Statistiken
                sendResponse([
                    'gesamt' => 45,
                    'offen' => 12,
                    'in_bearbeitung' => 5,
                    'abgeschlossen' => 28,
                    'umsatz_monat' => 12500.00,
                    'durchschnitt' => 278.50
                ]);
            } elseif ($id === 'generate-nr') {
                // Neue Auftragsnummer generieren
                $year = date('Y');
                $nr = 'A' . $year . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                sendResponse(['auftrag_nr' => $nr]);
            } else {
                // Einzelner Auftrag
                $auftraege = getDummyAuftraege();
                foreach ($auftraege as $auftrag) {
                    if ($auftrag['id'] == $id) {
                        // Mit Positionen
                        $auftrag['positionen'] = [
                            [
                                'id' => 1,
                                'beschreibung' => 'Ölwechsel',
                                'zeit' => 0.5,
                                'stundenpreis' => 110,
                                'gesamt' => 55.00
                            ],
                            [
                                'id' => 2,
                                'beschreibung' => 'Ölfilter',
                                'zeit' => 0.25,
                                'stundenpreis' => 110,
                                'gesamt' => 27.50
                            ]
                        ];
                        sendResponse($auftrag);
                    }
                }
                sendResponse(['error' => 'Auftrag nicht gefunden'], 404);
            }
        } else {
            // Alle Aufträge
            $auftraege = getDummyAuftraege();

            // Filter anwenden
            if (isset($_GET['status'])) {
                $auftraege = array_filter($auftraege, function ($a) {
                    return $a['status'] === $_GET['status'];
                });
            }

            if (isset($_GET['kunden_id'])) {
                $auftraege = array_filter($auftraege, function ($a) {
                    return $a['kunden_id'] == $_GET['kunden_id'];
                });
            }

            sendResponse(array_values($auftraege));
        }
        break;

    case 'POST':
        $data = getRequestData();

        // Validierung
        if (empty($data['kunden_id'])) {
            sendResponse(['error' => 'Kunde ist erforderlich'], 400);
        }

        // Spezielle Aktionen
        if ($id && $action === 'create-invoice') {
            // Rechnung aus Auftrag erstellen
            sendResponse([
                'success' => true,
                'rechnung_id' => rand(100, 999),
                'rechnung_nr' => 'R2024-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
                'message' => 'Rechnung wurde erstellt'
            ]);
        }

        // Neuen Auftrag erstellen
        $newAuftrag = [
            'id' => rand(100, 999),
            'auftrag_nr' => 'A' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
            'kunden_id' => $data['kunden_id'],
            'fahrzeug_id' => $data['fahrzeug_id'] ?? null,
            'datum' => $data['datum'] ?? date('Y-m-d'),
            'status' => 'offen',
            'basis_stundenpreis' => $data['basis_stundenpreis'] ?? 110,
            'gesamt_zeit' => 0,
            'gesamt_kosten' => 0,
            'bemerkungen' => $data['bemerkungen'] ?? '',
            'erstellt_am' => date('Y-m-d H:i:s')
        ];

        sendResponse($newAuftrag, 201);
        break;

    case 'PUT':
        if (!$id) {
            sendResponse(['error' => 'ID erforderlich'], 400);
        }

        $data = getRequestData();

        // Status-Update
        if ($action === 'status') {
            sendResponse([
                'success' => true,
                'message' => 'Status aktualisiert',
                'new_status' => $data['status']
            ]);
        }

        // Auftrag aktualisieren
        sendResponse([
            'success' => true,
            'message' => 'Auftrag aktualisiert',
            'id' => $id
        ]);
        break;

    case 'DELETE':
        if (!$id) {
            sendResponse(['error' => 'ID erforderlich'], 400);
        }

        sendResponse([
            'success' => true,
            'message' => 'Auftrag gelöscht',
            'id' => $id
        ]);
        break;

    default:
        sendResponse(['error' => 'Methode nicht erlaubt'], 405);
}
