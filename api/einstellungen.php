<?php

/**
 * KFZ Fac Pro - Einstellungen API
 * Verwaltung aller System-Einstellungen
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
$key = isset($segments[0]) ? $segments[0] : null;

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

// Standard-Einstellungen
function getDefaultSettings()
{
    return [
        // Firma
        'firmen_name' => 'KFZ Werkstatt GmbH',
        'firmen_strasse' => 'Musterstraße 1',
        'firmen_plz' => '12345',
        'firmen_ort' => 'Musterstadt',
        'firmen_telefon' => '0123/456789',
        'firmen_fax' => '0123/456790',
        'firmen_email' => 'info@kfz-werkstatt.de',
        'firmen_website' => 'www.kfz-werkstatt.de',
        'firmen_logo' => '',
        'geschaeftsfuehrer' => 'Max Mustermann',
        'handelsregister' => 'HRB 12345',
        'amtsgericht' => 'Amtsgericht Musterstadt',

        // Bank
        'bank_name' => 'Musterbank',
        'bank_iban' => 'DE12 3456 7890 1234 5678 90',
        'bank_bic' => 'DEUTDEFF',
        'kontoinhaber' => 'KFZ Werkstatt GmbH',

        // Steuer
        'steuernummer' => '123/456/78901',
        'ustid' => 'DE123456789',
        'mwst_satz' => '19',
        'kleinunternehmer' => '0',

        // Preise
        'basis_stundenpreis' => '110',
        'anfahrt_kosten' => '25',
        'express_aufschlag' => '50',
        'wochenend_aufschlag' => '75',
        'nacht_aufschlag' => '100',
        'notdienst_pauschale' => '150',

        // Dokumente
        'rechnung_prefix' => 'R',
        'auftrag_prefix' => 'A',
        'angebot_prefix' => 'AN',
        'kunde_prefix' => 'K',
        'zahlungsziel_tage' => '14',
        'mahnung_nach_tagen' => '7',
        'skonto_prozent' => '2',
        'skonto_tage' => '7',

        // E-Mail
        'email_smtp_host' => 'smtp.gmail.com',
        'email_smtp_port' => '587',
        'email_smtp_user' => '',
        'email_smtp_password' => '',
        'email_smtp_secure' => 'tls',
        'email_from_name' => 'KFZ Werkstatt',
        'email_from_address' => 'info@kfz-werkstatt.de',
        'email_signature' => 'Mit freundlichen Grüßen\nIhre KFZ Werkstatt',

        // System
        'backup_auto' => '1',
        'backup_interval' => 'daily',
        'backup_keep_days' => '30',
        'backup_time' => '03:00',
        'session_timeout' => '86400',
        'wartungsmodus' => '0',
        'wartungsmodus_text' => 'System wird gewartet. Bitte versuchen Sie es später erneut.',
        'debug_mode' => '0',

        // Layout/Design
        'layout_color_primary' => '#667eea',
        'layout_color_secondary' => '#764ba2',
        'layout_color_success' => '#48bb78',
        'layout_color_danger' => '#f56565',
        'layout_color_warning' => '#ed8936',
        'layout_sidebar_collapsed' => '0',
        'layout_dark_mode' => '0',
        'layout_font_size_normal' => '14px',
        'layout_font_size_small' => '12px',
        'layout_font_size_large' => '16px',
        'layout_logo_position' => 'left',

        // Benachrichtigungen
        'notify_new_order' => '1',
        'notify_payment_received' => '1',
        'notify_order_completed' => '1',
        'notify_backup_created' => '0',
        'notify_email' => 'admin@kfz-werkstatt.de',

        // Sonstiges
        'currency' => 'EUR',
        'currency_symbol' => '€',
        'decimal_separator' => ',',
        'thousand_separator' => '.',
        'date_format' => 'd.m.Y',
        'time_format' => 'H:i',
        'timezone' => 'Europe/Berlin',
        'language' => 'de_DE'
    ];
}

// Einstellungen-Datei
$settingsFile = __DIR__ . '/../data/settings.json';

// Einstellungen laden
function loadSettings()
{
    global $settingsFile;

    if (file_exists($settingsFile)) {
        $content = file_get_contents($settingsFile);
        $settings = json_decode($content, true);

        if ($settings) {
            // Mit Defaults mergen
            return array_merge(getDefaultSettings(), $settings);
        }
    }

    return getDefaultSettings();
}

// Einstellungen speichern
function saveSettings($settings)
{
    global $settingsFile;

    // data-Verzeichnis erstellen falls nicht vorhanden
    $dir = dirname($settingsFile);
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }

    return file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
}

// Routing
switch ($method) {
    case 'GET':
        if ($key) {
            // Spezielle Endpunkte
            if ($key === 'export') {
                // Export
                $settings = loadSettings();
                sendResponse([
                    'version' => '2.0',
                    'exported_at' => date('Y-m-d H:i:s'),
                    'settings' => $settings
                ]);
            } elseif ($key === 'backup') {
                // Backup-Einstellungen
                $settings = loadSettings();
                sendResponse([
                    'enabled' => $settings['backup_auto'] === '1',
                    'interval' => $settings['backup_interval'],
                    'keep_days' => intval($settings['backup_keep_days']),
                    'time' => $settings['backup_time']
                ]);
            } elseif ($key === 'email') {
                // E-Mail-Einstellungen
                $settings = loadSettings();
                sendResponse([
                    'smtp_host' => $settings['email_smtp_host'],
                    'smtp_port' => $settings['email_smtp_port'],
                    'smtp_user' => $settings['email_smtp_user'],
                    'smtp_secure' => $settings['email_smtp_secure'],
                    'from_name' => $settings['email_from_name'],
                    'from_address' => $settings['email_from_address']
                ]);
            } elseif ($key === 'preise') {
                // Preis-Einstellungen
                $settings = loadSettings();
                sendResponse([
                    'basis_stundenpreis' => floatval($settings['basis_stundenpreis']),
                    'anfahrt_kosten' => floatval($settings['anfahrt_kosten']),
                    'express_aufschlag' => floatval($settings['express_aufschlag']),
                    'wochenend_aufschlag' => floatval($settings['wochenend_aufschlag']),
                    'mwst_satz' => floatval($settings['mwst_satz'])
                ]);
            } else {
                // Einzelne Einstellung
                $settings = loadSettings();
                if (isset($settings[$key])) {
                    sendResponse([
                        'key' => $key,
                        'value' => $settings[$key]
                    ]);
                } else {
                    sendResponse(['error' => 'Einstellung nicht gefunden'], 404);
                }
            }
        } else {
            // Alle Einstellungen
            $settings = loadSettings();
            sendResponse($settings);
        }
        break;

    case 'PUT':
        $data = getRequestData();
        $settings = loadSettings();

        if ($key) {
            // Einzelne Einstellung aktualisieren
            if ($key === 'batch') {
                // Batch-Update
                foreach ($data as $k => $v) {
                    $settings[$k] = $v;
                }
                saveSettings($settings);
                sendResponse([
                    'success' => true,
                    'message' => 'Einstellungen aktualisiert',
                    'updated' => count($data)
                ]);
            } elseif ($key === 'logo') {
                // Logo speichern (Base64)
                $settings['firmen_logo'] = $data['logo'] ?? $data['value'] ?? '';
                saveSettings($settings);
                sendResponse([
                    'success' => true,
                    'message' => 'Logo gespeichert'
                ]);
            } else {
                // Einzelne Einstellung
                $settings[$key] = $data['value'] ?? $data[$key] ?? '';
                saveSettings($settings);
                sendResponse([
                    'success' => true,
                    'message' => 'Einstellung aktualisiert',
                    'key' => $key,
                    'value' => $settings[$key]
                ]);
            }
        } else {
            // Alle Einstellungen aktualisieren (Legacy)
            foreach ($data as $k => $v) {
                $settings[$k] = $v;
            }
            saveSettings($settings);
            sendResponse([
                'success' => true,
                'message' => 'Alle Einstellungen aktualisiert'
            ]);
        }
        break;

    case 'POST':
        $data = getRequestData();

        if ($key === 'import') {
            // Import
            if (!isset($data['settings']) || !is_array($data['settings'])) {
                sendResponse(['error' => 'Ungültiges Import-Format'], 400);
            }

            $settings = loadSettings();
            $imported = 0;

            foreach ($data['settings'] as $k => $v) {
                if (array_key_exists($k, getDefaultSettings())) {
                    $settings[$k] = $v;
                    $imported++;
                }
            }

            saveSettings($settings);
            sendResponse([
                'success' => true,
                'message' => 'Import erfolgreich',
                'imported' => $imported
            ]);
        } elseif ($key === 'reset') {
            // Zurücksetzen
            $keys = $data['keys'] ?? null;

            if ($keys && is_array($keys)) {
                // Nur bestimmte Keys zurücksetzen
                $settings = loadSettings();
                $defaults = getDefaultSettings();
                $reset = 0;

                foreach ($keys as $k) {
                    if (isset($defaults[$k])) {
                        $settings[$k] = $defaults[$k];
                        $reset++;
                    }
                }

                saveSettings($settings);
                sendResponse([
                    'success' => true,
                    'message' => 'Einstellungen zurückgesetzt',
                    'reset' => $reset
                ]);
            } else {
                // Alle zurücksetzen
                saveSettings(getDefaultSettings());
                sendResponse([
                    'success' => true,
                    'message' => 'Alle Einstellungen auf Standard zurückgesetzt'
                ]);
            }
        } else {
            sendResponse(['error' => 'Unbekannte Aktion'], 400);
        }
        break;

    default:
        sendResponse(['error' => 'Methode nicht erlaubt'], 405);
}
