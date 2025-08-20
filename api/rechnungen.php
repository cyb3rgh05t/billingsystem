<?php

/**
 * KFZ Fac Pro - Rechnungen API
 * Vollständige CRUD-Operationen für Rechnungen
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

// Dummy-Daten
function getDummyRechnungen()
{
    return [
        [
            'id' => 1,
            'rechnung_nr' => 'R2024-0001',
            'kunden_id' => 1,
            'kunde_name' => 'Max Mustermann',
            'fahrzeug_id' => 1,
            'kennzeichen' => 'B-XY 1234',
            'auftrag_id' => 1,
            'auftrag_nr' => 'A2024-0001',
            'datum' => '2024-01-15',
            'faellig_am' => '2024-01-29',
            'status' => 'offen',
            'zwischensumme' => 275.00,
            'mwst_satz' => 19,
            'mwst_betrag' => 52.25,
            'gesamtbetrag' => 327.25,
            'gezahlt_am' => null,
            'zahlungsart' => null
        ],
        [
            'id' => 2,
            'rechnung_nr' => 'R2024-0002',
            'kunden_id' => 2,
            'kunde_name' => 'Anna Schmidt',
            'fahrzeug_id' => 2,
            'kennzeichen' => 'M-AB 5678',
            'auftrag_id' => 2,
            'auftrag_nr' => 'A2024-0002',
            'datum' => '2024-01-10',
            'faellig_am' => '2024-01-24',
            'status' => 'bezahlt',
            'zwischensumme' => 450.00,
            'mwst_satz' => 19,
            'mwst_betrag' => 85.50,
            'gesamtbetrag' => 535.50,
            'gezahlt_am' => '2024-01-20',
            'zahlungsart' => 'Überweisung'
        ],
        [
            'id' => 3,
            'rechnung_nr' => 'R2024-0003',
            'kunden_id' => 1,
            'kunde_name' => 'Max Mustermann',
            'fahrzeug_id' => 1,
            'kennzeichen' => 'B-XY 1234',
            'auftrag_id' => 3,
            'auftrag_nr' => 'A2024-0003',
            'datum' => '2024-01-05',
            'faellig_am' => '2024-01-19',
            'status' => 'ueberfaellig',
            'zwischensumme' => 180.00,
            'mwst_satz' => 19,
            'mwst_betrag' => 34.20,
            'gesamtbetrag' => 214.20,
            'gezahlt_am' => null,
            'zahlungsart' => null
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
                    'data' => getDummyRechnungen(),
                    'format' => 'json',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            } elseif ($id === 'statistik') {
                // Statistiken
                $year = $_GET['year'] ?? date('Y');
                $month = $_GET['month'] ?? null;

                sendResponse([
                    'anzahl' => 125,
                    'bezahlt' => 95000.00,
                    'offen' => 12500.00,
                    'ueberfaellig' => 3200.00,
                    'gesamt' => 110700.00,
                    'durchschnitt' => 885.60,
                    'year' => $year,
                    'month' => $month
                ]);
            } elseif ($id === 'monatlich') {
                // Monatliche Umsätze
                $year = $_GET['year'] ?? date('Y');
                $monate = [];

                for ($i = 1; $i <= 12; $i++) {
                    $monate[] = [
                        'monat' => $i,
                        'monat_name' => date('F', mktime(0, 0, 0, $i, 1)),
                        'anzahl' => rand(8, 15),
                        'umsatz' => rand(5000, 15000)
                    ];
                }

                sendResponse($monate);
            } elseif ($id === 'ueberfaellig') {
                // Überfällige Rechnungen
                $rechnungen = getDummyRechnungen();
                $ueberfaellige = array_filter($rechnungen, function ($r) {
                    return $r['status'] === 'ueberfaellig' ||
                        ($r['status'] === 'offen' && strtotime($r['faellig_am']) < time());
                });

                sendResponse(array_values($ueberfaellige));
            } elseif ($id === 'generate-nr') {
                // Neue Rechnungsnummer
                $year = date('Y');
                $nr = 'R' . $year . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                sendResponse(['rechnung_nr' => $nr]);
            } else if (is_numeric($id)) {
                // Einzelne Rechnung
                $rechnungen = getDummyRechnungen();
                foreach ($rechnungen as $rechnung) {
                    if ($rechnung['id'] == $id) {
                        // Mit Positionen
                        if ($action === 'pdf') {
                            // PDF generieren (Platzhalter)
                            header('Content-Type: application/pdf');
                            header('Content-Disposition: attachment; filename="rechnung_' . $rechnung['rechnung_nr'] . '.pdf"');
                            echo "%PDF-1.4\n"; // Dummy PDF
                            exit;
                        }

                        $rechnung['positionen'] = [
                            [
                                'id' => 1,
                                'kategorie' => 'arbeitszeit',
                                'beschreibung' => 'Ölwechsel',
                                'menge' => 0.5,
                                'einheit' => 'Std.',
                                'einzelpreis' => 110.00,
                                'mwst_prozent' => 19,
                                'gesamt' => 65.45
                            ],
                            [
                                'id' => 2,
                                'kategorie' => 'material',
                                'beschreibung' => 'Motoröl 5W30',
                                'menge' => 5,
                                'einheit' => 'Liter',
                                'einzelpreis' => 12.00,
                                'mwst_prozent' => 19,
                                'gesamt' => 71.40
                            ]
                        ];
                        sendResponse($rechnung);
                    }
                }
                sendResponse(['error' => 'Rechnung nicht gefunden'], 404);
            }
        } else {
            // Alle Rechnungen
            $rechnungen = getDummyRechnungen();

            // Filter anwenden
            if (isset($_GET['status'])) {
                $rechnungen = array_filter($rechnungen, function ($r) {
                    return $r['status'] === $_GET['status'];
                });
            }

            if (isset($_GET['kunden_id'])) {
                $rechnungen = array_filter($rechnungen, function ($r) {
                    return $r['kunden_id'] == $_GET['kunden_id'];
                });
            }

            if (isset($_GET['year'])) {
                $rechnungen = array_filter($rechnungen, function ($r) {
                    return date('Y', strtotime($r['datum'])) == $_GET['year'];
                });
            }

            if (isset($_GET['month'])) {
                $rechnungen = array_filter($rechnungen, function ($r) {
                    return date('m', strtotime($r['datum'])) == $_GET['month'];
                });
            }

            sendResponse(array_values($rechnungen));
        }
        break;

    case 'POST':
        $data = getRequestData();

        // Validierung
        if (empty($data['kunden_id'])) {
            sendResponse(['error' => 'Kunde ist erforderlich'], 400);
        }

        // Neue Rechnung erstellen
        $newRechnung = [
            'id' => rand(100, 999),
            'rechnung_nr' => 'R' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
            'kunden_id' => $data['kunden_id'],
            'fahrzeug_id' => $data['fahrzeug_id'] ?? null,
            'auftrag_id' => $data['auftrag_id'] ?? null,
            'datum' => $data['datum'] ?? date('Y-m-d'),
            'faellig_am' => $data['faellig_am'] ?? date('Y-m-d', strtotime('+14 days')),
            'status' => 'offen',
            'zwischensumme' => $data['zwischensumme'] ?? 0,
            'mwst_satz' => $data['mwst_satz'] ?? 19,
            'mwst_betrag' => 0,
            'gesamtbetrag' => 0,
            'erstellt_am' => date('Y-m-d H:i:s')
        ];

        // MwSt und Gesamt berechnen
        $newRechnung['mwst_betrag'] = $newRechnung['zwischensumme'] * ($newRechnung['mwst_satz'] / 100);
        $newRechnung['gesamtbetrag'] = $newRechnung['zwischensumme'] + $newRechnung['mwst_betrag'];

        sendResponse($newRechnung, 201);
        break;

    case 'PUT':
        if (!$id) {
            sendResponse(['error' => 'ID erforderlich'], 400);
        }

        $data = getRequestData();

        // Bezahlt markieren
        if ($action === 'bezahlt') {
            sendResponse([
                'success' => true,
                'message' => 'Rechnung als bezahlt markiert',
                'gezahlt_am' => $data['gezahlt_am'] ?? date('Y-m-d'),
                'zahlungsart' => $data['zahlungsart'] ?? 'Überweisung'
            ]);
        }

        // Status ändern
        if ($action === 'status') {
            sendResponse([
                'success' => true,
                'message' => 'Status aktualisiert',
                'new_status' => $data['status']
            ]);
        }

        // Rechnung aktualisieren
        sendResponse([
            'success' => true,
            'message' => 'Rechnung aktualisiert',
            'id' => $id
        ]);
        break;

    case 'DELETE':
        if (!$id) {
            sendResponse(['error' => 'ID erforderlich'], 400);
        }

        sendResponse([
            'success' => true,
            'message' => 'Rechnung gelöscht',
            'id' => $id
        ]);
        break;

    default:
        sendResponse(['error' => 'Methode nicht erlaubt'], 405);
}
