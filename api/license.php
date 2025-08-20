<?php

/**
 * KFZ Fac Pro - Lizenz-System API
 */

// Headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Konfiguration
define('LICENSE_SERVER_URL', 'https://license.meinefirma.dev/api/');
define('LICENSE_FILE', dirname(__DIR__) . '/data/license.json');
define('HARDWARE_ID_FILE', dirname(__DIR__) . '/data/hardware_id.txt');

// Response-Helper
function sendResponse($data, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

// Hardware-ID generieren
function generateHardwareId()
{
    $data = [
        'hostname' => gethostname(),
        'platform' => PHP_OS,
        'php_version' => PHP_VERSION,
        'server_addr' => $_SERVER['SERVER_ADDR'] ?? 'unknown',
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'unknown'
    ];

    // Hash erstellen
    $hardwareId = hash('sha256', json_encode($data));

    // Speichern
    file_put_contents(HARDWARE_ID_FILE, $hardwareId);

    return $hardwareId;
}

// Hardware-ID abrufen oder generieren
function getHardwareId()
{
    if (file_exists(HARDWARE_ID_FILE)) {
        $id = file_get_contents(HARDWARE_ID_FILE);
        if (!empty($id)) {
            return trim($id);
        }
    }

    return generateHardwareId();
}

// Lizenz lokal laden
function loadLocalLicense()
{
    if (!file_exists(LICENSE_FILE)) {
        return null;
    }

    $content = file_get_contents(LICENSE_FILE);
    $license = json_decode($content, true);

    if (!$license) {
        return null;
    }

    // Ablaufdatum prüfen
    if (isset($license['expires_at'])) {
        $expiresAt = strtotime($license['expires_at']);
        if ($expiresAt < time()) {
            return null; // Abgelaufen
        }
    }

    return $license;
}

// Lizenz speichern
function saveLicense($licenseData)
{
    // Verzeichnis erstellen falls nicht vorhanden
    $dir = dirname(LICENSE_FILE);
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }

    // Lizenz mit Timestamp speichern
    $licenseData['saved_at'] = date('Y-m-d H:i:s');
    $licenseData['hardware_id'] = getHardwareId();

    return file_put_contents(LICENSE_FILE, json_encode($licenseData, JSON_PRETTY_PRINT));
}

// Lizenz beim Server validieren
function validateLicenseOnline($licenseKey)
{
    $hardwareId = getHardwareId();

    $data = [
        'license_key' => $licenseKey,
        'hardware_id' => $hardwareId,
        'app_version' => '2.0',
        'php_version' => PHP_VERSION
    ];

    $ch = curl_init(LICENSE_SERVER_URL . 'validate.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Für Entwicklung

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        return ['valid' => false, 'error' => 'Server nicht erreichbar'];
    }

    $result = json_decode($response, true);

    if (!$result) {
        return ['valid' => false, 'error' => 'Ungültige Server-Antwort'];
    }

    return $result;
}

// Request-Methode
$method = $_SERVER['REQUEST_METHOD'];
$pathInfo = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
$segments = explode('/', trim($pathInfo, '/'));
$action = isset($segments[0]) ? $segments[0] : '';

// Routing
switch ($method) {
    case 'GET':
        if ($action === 'status') {
            // Lizenz-Status abrufen
            $license = loadLocalLicense();

            if (!$license) {
                sendResponse([
                    'valid' => false,
                    'message' => 'Keine Lizenz aktiviert'
                ]);
            }

            // Basis-Infos zurückgeben (ohne Key)
            sendResponse([
                'valid' => true,
                'type' => $license['license_type'] ?? 'basic',
                'expires_at' => $license['expires_at'] ?? null,
                'features' => $license['features'] ?? [],
                'customer' => $license['customer_name'] ?? 'Unbekannt',
                'hardware_id' => getHardwareId()
            ]);
        } elseif ($action === 'hardware-id') {
            // Hardware-ID abrufen
            sendResponse([
                'hardware_id' => getHardwareId()
            ]);
        } elseif ($action === 'check') {
            // Lizenz prüfen (für Middleware)
            $license = loadLocalLicense();

            if (!$license) {
                sendResponse(['valid' => false], 401);
            }

            // Tägliche Online-Validierung
            $lastCheck = isset($license['last_check']) ? strtotime($license['last_check']) : 0;
            $now = time();

            if ($now - $lastCheck > 86400) { // 24 Stunden
                // Online validieren
                $result = validateLicenseOnline($license['license_key']);

                if ($result['valid']) {
                    // Lizenz aktualisieren
                    $license['last_check'] = date('Y-m-d H:i:s');
                    $license['expires_at'] = $result['expires_at'] ?? null;
                    $license['features'] = $result['features'] ?? [];
                    saveLicense($license);
                } else {
                    // Bei Fehler alte Lizenz behalten, aber Warnung
                    error_log('Lizenz-Validierung fehlgeschlagen: ' . json_encode($result));
                }
            }

            sendResponse(['valid' => true]);
        } else {
            sendResponse(['error' => 'Unbekannte Aktion'], 400);
        }
        break;

    case 'POST':
        if ($action === 'activate') {
            // Lizenz aktivieren
            $data = json_decode(file_get_contents('php://input'), true);
            $licenseKey = $data['license_key'] ?? '';

            if (empty($licenseKey)) {
                sendResponse(['error' => 'Lizenzschlüssel erforderlich'], 400);
            }

            // Online validieren
            $result = validateLicenseOnline($licenseKey);

            if (!$result['valid']) {
                sendResponse([
                    'success' => false,
                    'error' => $result['error'] ?? 'Ungültiger Lizenzschlüssel'
                ], 400);
            }

            // Lizenz-Daten vorbereiten
            $licenseData = [
                'license_key' => $licenseKey,
                'license_type' => $result['license_type'] ?? 'basic',
                'expires_at' => $result['expires_at'] ?? null,
                'features' => $result['features'] ?? [],
                'customer_name' => $result['customer_name'] ?? 'Unbekannt',
                'activated_at' => date('Y-m-d H:i:s'),
                'last_check' => date('Y-m-d H:i:s')
            ];

            // Speichern
            if (saveLicense($licenseData)) {
                sendResponse([
                    'success' => true,
                    'message' => 'Lizenz erfolgreich aktiviert',
                    'license' => [
                        'type' => $licenseData['license_type'],
                        'expires_at' => $licenseData['expires_at'],
                        'features' => $licenseData['features']
                    ]
                ]);
            } else {
                sendResponse([
                    'success' => false,
                    'error' => 'Fehler beim Speichern der Lizenz'
                ], 500);
            }
        } elseif ($action === 'deactivate') {
            // Lizenz deaktivieren
            $license = loadLocalLicense();

            if (!$license) {
                sendResponse([
                    'success' => false,
                    'error' => 'Keine aktive Lizenz gefunden'
                ], 400);
            }

            // Server informieren
            $data = [
                'license_key' => $license['license_key'],
                'hardware_id' => getHardwareId()
            ];

            $ch = curl_init(LICENSE_SERVER_URL . 'deactivate.php');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            curl_close($ch);

            // Lokal löschen
            if (file_exists(LICENSE_FILE)) {
                unlink(LICENSE_FILE);
            }

            sendResponse([
                'success' => true,
                'message' => 'Lizenz deaktiviert'
            ]);
        } elseif ($action === 'validate') {
            // Manuelle Validierung
            require_once '../config/auth.php';
            $auth->requireAdmin(); // Nur Admin

            $license = loadLocalLicense();

            if (!$license) {
                sendResponse([
                    'valid' => false,
                    'error' => 'Keine Lizenz aktiviert'
                ]);
            }

            // Online validieren
            $result = validateLicenseOnline($license['license_key']);

            if ($result['valid']) {
                // Lizenz aktualisieren
                $license['last_check'] = date('Y-m-d H:i:s');
                $license['expires_at'] = $result['expires_at'] ?? null;
                $license['features'] = $result['features'] ?? [];
                saveLicense($license);

                sendResponse([
                    'valid' => true,
                    'message' => 'Lizenz ist gültig',
                    'expires_at' => $license['expires_at']
                ]);
            } else {
                sendResponse([
                    'valid' => false,
                    'error' => $result['error'] ?? 'Lizenz ungültig'
                ]);
            }
        } else {
            sendResponse(['error' => 'Unbekannte Aktion'], 400);
        }
        break;

    default:
        sendResponse(['error' => 'Methode nicht erlaubt'], 405);
}
