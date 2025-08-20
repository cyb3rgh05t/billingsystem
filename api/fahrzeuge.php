<?php

/**
 * KFZ Fac Pro - Fahrzeuge API
 * RESTful API für Fahrzeugverwaltung
 */

// CORS und Headers
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
require_once '../config/auth.php';
require_once '../models/Fahrzeug.php';

// Auth-Check
$auth->requireLogin();

// Request-Methode und ID ermitteln
$method = $_SERVER['REQUEST_METHOD'];
$pathInfo = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
$segments = explode('/', trim($pathInfo, '/'));
$id = isset($segments[0]) ? $segments[0] : null;

// Model initialisieren
$fahrzeugModel = new Fahrzeug();

// Response-Helper
function sendResponse($data, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

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

// Routing basierend auf Methode
switch ($method) {
    case 'GET':
        if ($id) {
            // Spezielle Endpunkte
            if ($id === 'export') {
                // Export-Funktion
                $fahrzeuge = $fahrzeugModel->export();
                sendResponse($fahrzeuge);
            } elseif ($id === 'search') {
                // Suche
                $query = isset($_GET['q']) ? $_GET['q'] : '';
                if (strlen($query) < 2) {
                    sendResponse(['error' => 'Suchbegriff zu kurz'], 400);
                }
                $results = $fahrzeugModel->search($query);
                sendResponse($results);
            } elseif ($id === 'by-kunde') {
                // Fahrzeuge eines Kunden
                $kundenId = isset($_GET['kunden_id']) ? intval($_GET['kunden_id']) : 0;
                if (!$kundenId) {
                    sendResponse(['error' => 'Kunden-ID erforderlich'], 400);
                }
                $fahrzeuge = $fahrzeugModel->findByKunde($kundenId);
                sendResponse($fahrzeuge);
            } else {
                // Einzelnes Fahrzeug
                $fahrzeug = $fahrzeugModel->findById($id);

                if (!$fahrzeug) {
                    sendResponse(['error' => 'Fahrzeug nicht gefunden'], 404);
                }

                // Mit Details laden wenn gewünscht
                if (isset($_GET['include'])) {
                    $includes = explode(',', $_GET['include']);

                    if (in_array('kunde', $includes)) {
                        $fahrzeug = $fahrzeugModel->findWithKunde($id);
                    }

                    if (in_array('historie', $includes)) {
                        $fahrzeug['historie'] = $fahrzeugModel->getHistorie($id);
                    }

                    if (in_array('statistik', $includes)) {
                        $fahrzeug['statistik'] = $fahrzeugModel->getStatistik($id);
                    }
                }

                sendResponse($fahrzeug);
            }
        } else {
            // Alle Fahrzeuge
            $orderBy = isset($_GET['orderBy']) ? $_GET['orderBy'] : 'kennzeichen ASC';
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : null;

            // Mit Kundeninfos laden?
            if (isset($_GET['withKunden']) && $_GET['withKunden'] === 'true') {
                $fahrzeuge = $fahrzeugModel->findAllWithKunden($orderBy);
            } else {
                $fahrzeuge = $fahrzeugModel->findAll($orderBy, $limit);
            }

            sendResponse($fahrzeuge);
        }
        break;

    case 'POST':
        $data = getRequestData();

        // Validierung
        if (empty($data['kennzeichen'])) {
            sendResponse(['error' => 'Kennzeichen ist erforderlich'], 400);
        }

        // Prüfen ob Kennzeichen bereits existiert
        $existing = $fahrzeugModel->findByKennzeichen($data['kennzeichen']);
        if ($existing) {
            sendResponse(['error' => 'Fahrzeug mit diesem Kennzeichen existiert bereits'], 400);
        }

        // Fahrzeug erstellen
        $result = $fahrzeugModel->create($data);

        if ($result['success']) {
            sendResponse($result['data'], 201);
        } else {
            sendResponse(['error' => $result['error']], 400);
        }
        break;

    case 'PUT':
        if (!$id) {
            sendResponse(['error' => 'ID erforderlich'], 400);
        }

        $data = getRequestData();

        // Spezielle Update-Funktionen
        if (isset($segments[1])) {
            if ($segments[1] === 'kilometerstand') {
                // Kilometerstand aktualisieren
                $kilometerstand = isset($data['kilometerstand']) ? intval($data['kilometerstand']) : 0;
                $result = $fahrzeugModel->updateKilometerstand($id, $kilometerstand);

                if ($result['success']) {
                    sendResponse(['success' => true, 'message' => 'Kilometerstand aktualisiert']);
                } else {
                    sendResponse(['error' => $result['error']], 400);
                }
            }
        }

        // Prüfen ob Fahrzeug existiert
        if (!$fahrzeugModel->exists($id)) {
            sendResponse(['error' => 'Fahrzeug nicht gefunden'], 404);
        }

        // Fahrzeug aktualisieren
        $result = $fahrzeugModel->update($id, $data);

        if ($result['success']) {
            // Aktualisierte Daten zurückgeben
            $fahrzeug = $fahrzeugModel->findById($id);
            sendResponse($fahrzeug);
        } else {
            sendResponse(['error' => $result['error']], 400);
        }
        break;

    case 'DELETE':
        if (!$id) {
            sendResponse(['error' => 'ID erforderlich'], 400);
        }

        // Prüfen ob Fahrzeug existiert
        if (!$fahrzeugModel->exists($id)) {
            sendResponse(['error' => 'Fahrzeug nicht gefunden'], 404);
        }

        // Prüfen ob Fahrzeug in Aufträgen verwendet wird
        $historie = $fahrzeugModel->getHistorie($id);
        if (!empty($historie['auftraege']) || !empty($historie['rechnungen'])) {
            sendResponse([
                'error' => 'Fahrzeug kann nicht gelöscht werden - es existieren noch Aufträge oder Rechnungen'
            ], 400);
        }

        // Fahrzeug löschen
        $result = $fahrzeugModel->delete($id);

        if ($result['success']) {
            sendResponse(['success' => true, 'message' => 'Fahrzeug gelöscht']);
        } else {
            sendResponse(['error' => $result['error']], 400);
        }
        break;

    default:
        sendResponse(['error' => 'Methode nicht erlaubt'], 405);
}
