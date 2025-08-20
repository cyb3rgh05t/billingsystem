<?php
// ===============================================
// api/backup.php - Backup-Verwaltung
// ===============================================
/**
 * KFZ Fac Pro - Backup API
 */

// Headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
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

// Nur Admins
if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Keine Berechtigung']);
    exit;
}

// Request-Methode und Pfad
$method = $_SERVER['REQUEST_METHOD'];
$pathInfo = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
$segments = array_filter(explode('/', trim($pathInfo, '/')));
$action = isset($segments[0]) ? $segments[0] : null;

// Backup-Verzeichnis
$backupDir = __DIR__ . '/../backups/';
if (!file_exists($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Response senden
function sendResponse($data, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

// Routing
switch ($method) {
    case 'GET':
        if ($action === 'list') {
            // Backup-Liste
            $backups = [];
            $files = glob($backupDir . '*.{db,sql,json}', GLOB_BRACE);

            foreach ($files as $file) {
                $backups[] = [
                    'file' => basename($file),
                    'size' => filesize($file),
                    'size_formatted' => round(filesize($file) / 1024 / 1024, 2) . ' MB',
                    'created' => date('Y-m-d H:i:s', filemtime($file)),
                    'type' => pathinfo($file, PATHINFO_EXTENSION)
                ];
            }

            // Nach Datum sortieren (neueste zuerst)
            usort($backups, function ($a, $b) {
                return strtotime($b['created']) - strtotime($a['created']);
            });

            sendResponse($backups);
        } elseif ($action === 'download') {
            // Backup herunterladen
            $file = isset($segments[1]) ? $segments[1] : '';
            $filepath = $backupDir . basename($file);

            if (!file_exists($filepath)) {
                sendResponse(['error' => 'Backup nicht gefunden'], 404);
            }

            // Datei zum Download senden
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file) . '"');
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
            exit;
        } else {
            // Status
            sendResponse([
                'backup_dir' => $backupDir,
                'writable' => is_writable($backupDir),
                'count' => count(glob($backupDir . '*.{db,sql,json}', GLOB_BRACE)),
                'total_size' => array_sum(array_map('filesize', glob($backupDir . '*')))
            ]);
        }
        break;

    case 'POST':
        if ($action === 'create') {
            // Backup erstellen
            $timestamp = date('Y-m-d_H-i-s');

            // SQLite-Datenbank kopieren
            $dbSource = __DIR__ . '/../data/kfz.db';
            $dbBackup = $backupDir . 'backup_' . $timestamp . '.db';

            if (file_exists($dbSource)) {
                copy($dbSource, $dbBackup);
            }

            // Einstellungen exportieren
            $settingsFile = __DIR__ . '/../data/settings.json';
            $settingsBackup = $backupDir . 'settings_' . $timestamp . '.json';

            if (file_exists($settingsFile)) {
                copy($settingsFile, $settingsBackup);
            } else {
                // Dummy-Einstellungen
                file_put_contents($settingsBackup, json_encode([
                    'backup_created' => $timestamp,
                    'version' => '2.0'
                ], JSON_PRETTY_PRINT));
            }

            sendResponse([
                'success' => true,
                'message' => 'Backup erstellt',
                'files' => [
                    'database' => basename($dbBackup),
                    'settings' => basename($settingsBackup)
                ],
                'timestamp' => $timestamp
            ]);
        } elseif ($action === 'restore') {
            // Backup wiederherstellen
            $data = json_decode(file_get_contents('php://input'), true);
            $backupFile = $data['backup_file'] ?? '';
            $filepath = $backupDir . basename($backupFile);

            if (!file_exists($filepath)) {
                sendResponse(['error' => 'Backup nicht gefunden'], 404);
            }

            // Aktuelles Backup erstellen vor Wiederherstellung
            $emergencyBackup = $backupDir . 'emergency_' . date('Y-m-d_H-i-s') . '.db';
            $dbPath = __DIR__ . '/../data/kfz.db';

            if (file_exists($dbPath)) {
                copy($dbPath, $emergencyBackup);
            }

            // Wiederherstellen
            copy($filepath, $dbPath);

            sendResponse([
                'success' => true,
                'message' => 'Backup wiederhergestellt',
                'emergency_backup' => basename($emergencyBackup)
            ]);
        } else {
            sendResponse(['error' => 'Unbekannte Aktion'], 400);
        }
        break;

    case 'DELETE':
        if ($action) {
            // Backup löschen
            $filepath = $backupDir . basename($action);

            if (!file_exists($filepath)) {
                sendResponse(['error' => 'Backup nicht gefunden'], 404);
            }

            unlink($filepath);

            sendResponse([
                'success' => true,
                'message' => 'Backup gelöscht',
                'file' => basename($action)
            ]);
        } else {
            sendResponse(['error' => 'Dateiname erforderlich'], 400);
        }
        break;

    default:
        sendResponse(['error' => 'Methode nicht erlaubt'], 405);
}
