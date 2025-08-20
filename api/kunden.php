<?php

/**
 * KFZ Fac Pro - Kunden API
 * RESTful API für Kundenverwaltung
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
require_once '../models/Kunde.php';

// Auth-Check
$auth->requireLogin();

// Request-Methode und ID ermitteln
$method = $_SERVER['REQUEST_METHOD'];
$pathInfo = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
$segments = explode('/', trim($pathInfo, '/'));
$id = isset($segments[0]) ? $segments[0] : null;

// Model initialisieren
$kundeModel = new Kunde();

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
            // Einzelner Kunde
            if ($id === 'export') {
                // Export-Funktion
                $kunden = $kundeModel->export();
                sendResponse($kunden);
            } elseif ($id === 'top') {
                // Top-Kunden
                $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
                $topKunden = $kundeModel->getTopKunden($limit);
                sendResponse($topKunden);
            } elseif ($id === 'search') {
                // Suche
                $query = isset($_GET['q']) ? $_GET['q'] : '';
                if (strlen($query) < 2) {
                    sendResponse(['error' => 'Suchbegriff zu kurz'], 400);
                }
                $results = $kundeModel->search($query);
                sendResponse($results);
            } elseif ($id === 'generate-nr') {
                // Neue Kundennummer generieren
                $kundenNr = $kundeModel->generateKundenNr();
                sendResponse(['kunden_nr' => $kundenNr]);
            } else {
                // Kunde nach ID
                $kunde = $kundeModel->findById($id);

                if (!$kunde) {
                    sendResponse(['error' => 'Kunde nicht gefunden'], 404);
                }

                // Mit Details laden wenn gewünscht
                if (isset($_GET['include'])) {
                    $includes = explode(',', $_GET['include']);

                    if (in_array('fahrzeuge', $includes)) {
                        $kunde = $kundeModel->findWithFahrzeuge($id);
                    }

                    if (in_array('stats', $includes)) {
                        $kunde = $kundeModel->findWithStats($id);
                    }
                }

                sendResponse($kunde);
            }
        } else {
            // Alle Kunden
            $orderBy = isset($_GET['orderBy']) ? $_GET['orderBy'] : 'name ASC';
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : null;

            // Filter anwenden
            if (isset($_GET['filter'])) {
                $filters = json_decode($_GET['filter'], true);
                $kunden = $kundeModel->findWhere($filters, $orderBy, $limit);
            } else {
                $kunden = $kundeModel->findAll($orderBy, $limit);
            }

            sendResponse($kunden);
        }
        break;

    case 'POST':
        $data = getRequestData();

        // Validierung
        if (empty($data['name'])) {
            sendResponse(['error' => 'Name ist erforderlich'], 400);
        }

        // Kunde erstellen
        $result = $kundeModel->create($data);

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

        // Prüfen ob Kunde existiert
        if (!$kundeModel->exists($id)) {
            sendResponse(['error' => 'Kunde nicht gefunden'], 404);
        }

        // Kunde aktualisieren
        $result = $kundeModel->update($id, $data);

        if ($result['success']) {
            // Aktualisierte Daten zurückgeben
            $kunde = $kundeModel->findById($id);
            sendResponse($kunde);
        } else {
            sendResponse(['error' => $result['error']], 400);
        }
        break;

    case 'DELETE':
        if (!$id) {
            sendResponse(['error' => 'ID erforderlich'], 400);
        }

        // Prüfen ob Kunde existiert
        if (!$kundeModel->exists($id)) {
            sendResponse(['error' => 'Kunde nicht gefunden'], 404);
        }

        // Kunde löschen
        $result = $kundeModel->delete($id);

        if ($result['success']) {
            sendResponse(['success' => true, 'message' => 'Kunde gelöscht']);
        } else {
            sendResponse(['error' => $result['error']], 400);
        }
        break;

    default:
        sendResponse(['error' => 'Methode nicht erlaubt'], 405);
}
