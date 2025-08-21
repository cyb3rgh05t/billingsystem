<?php

/**
 * KFZ Fac Pro - System API
 * System-Status, Health-Check, Backup, etc.
 */

// Headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Bei OPTIONS-Request direkt beenden
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Includes
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

// Request-Methode und Pfad ermitteln
$method = $_SERVER['REQUEST_METHOD'];
$pathInfo = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
$segments = array_filter(explode('/', $pathInfo));
$action = isset($segments[1]) ? $segments[1] : null;

// Router
try {
    switch ($action) {
        case 'health':
            // GET /api/system/health - Öffentlicher Health-Check
            $db = Database::getInstance()->getConnection();

            $health = [
                'status' => 'healthy',
                'timestamp' => date('Y-m-d H:i:s'),
                'version' => '2.0.0',
                'php_version' => phpversion(),
                'database' => false,
                'writable_dirs' => []
            ];

            // Datenbank-Check
            try {
                $stmt = $db->query("SELECT 1");
                $health['database'] = true;
            } catch (Exception $e) {
                $health['status'] = 'degraded';
                $health['database_error'] = $e->getMessage();
            }

            // Verzeichnis-Checks
            $dirs = ['data', 'backups', 'uploads', 'logs'];
            foreach ($dirs as $dir) {
                $path = dirname(__DIR__) . '/' . $dir;
                $health['writable_dirs'][$dir] = is_writable($path);
                if (!$health['writable_dirs'][$dir]) {
                    $health['status'] = 'degraded';
                }
            }

            echo json_encode($health);
            break;

        case 'status':
            // GET /api/system/status - Detaillierter Status (Auth erforderlich)
            Auth::requireAuth();

            $db = Database::getInstance()->getConnection();
            $config = Database::getInstance()->getConfig();

            $status = [
                'status' => 'ok',
                'timestamp' => date('Y-m-d H:i:s'),
                'server' => [
                    'php_version' => phpversion(),
                    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                    'server_name' => $_SERVER['SERVER_NAME'] ?? 'Unknown',
                    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
                    'memory_limit' => ini_get('memory_limit'),
                    'max_execution_time' => ini_get('max_execution_time'),
                    'upload_max_filesize' => ini_get('upload_max_filesize'),
                    'post_max_size' => ini_get('post_max_size')
                ],
                'database' => [],
                'storage' => [],
                'statistics' => [],
                'user' => Auth::getCurrentUser()
            ];

            // Datenbank-Informationen
            try {
                // Datenbank-Größe
                $dbPath = $config['db_path'];
                if (file_exists($dbPath)) {
                    $dbSize = filesize($dbPath);
                    $status['database']['size_bytes'] = $dbSize;
                    $status['database']['size_mb'] = round($dbSize / 1024 / 1024, 2);
                }

                // Tabellen-Statistiken
                $tables = ['kunden', 'fahrzeuge', 'auftraege', 'rechnungen', 'users'];
                foreach ($tables as $table) {
                    $stmt = $db->query("SELECT COUNT(*) as count FROM $table");
                    $result = $stmt->fetch();
                    $status['statistics'][$table] = $result['count'];
                }

                // SQLite Version
                $stmt = $db->query("SELECT sqlite_version()");
                $status['database']['sqlite_version'] = $stmt->fetchColumn();
            } catch (Exception $e) {
                $status['database']['error'] = $e->getMessage();
            }

            // Speicher-Informationen
            $dirs = ['data', 'backups', 'uploads', 'logs'];
            foreach ($dirs as $dir) {
                $path = dirname(__DIR__) . '/' . $dir;
                if (is_dir($path)) {
                    $status['storage'][$dir] = [
                        'exists' => true,
                        'writable' => is_writable($path),
                        'files' => count(glob($path . '/*'))
                    ];

                    // Verzeichnisgröße berechnen
                    $size = 0;
                    $files = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
                    );
                    foreach ($files as $file) {
                        if ($file->isFile()) {
                            $size += $file->getSize();
                        }
                    }
                    $status['storage'][$dir]['size_bytes'] = $size;
                    $status['storage'][$dir]['size_mb'] = round($size / 1024 / 1024, 2);
                } else {
                    $status['storage'][$dir] = ['exists' => false];
                }
            }

            echo json_encode($status);
            break;

        case 'backup':
            // POST /api/system/backup - Backup erstellen
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Methode nicht erlaubt']);
                break;
            }

            Auth::requireAuth();

            // Admin-Rechte erforderlich
            if (!Auth::isAdmin()) {
                http_response_code(403);
                echo json_encode(['error' => 'Keine Berechtigung']);
                break;
            }

            $result = Database::getInstance()->createBackup();
            echo json_encode($result);
            break;

        case 'backups':
            // GET /api/system/backups - Backup-Liste
            Auth::requireAuth();

            $backupPath = Database::getInstance()->getConfig('backup_path');
            $backups = [];

            if (is_dir($backupPath)) {
                $files = glob($backupPath . 'backup_*.db');
                foreach ($files as $file) {
                    $backups[] = [
                        'filename' => basename($file),
                        'path' => $file,
                        'size' => filesize($file),
                        'size_mb' => round(filesize($file) / 1024 / 1024, 2),
                        'created' => date('Y-m-d H:i:s', filemtime($file))
                    ];
                }

                // Nach Datum sortieren (neueste zuerst)
                usort($backups, function ($a, $b) {
                    return strtotime($b['created']) - strtotime($a['created']);
                });
            }

            echo json_encode([
                'success' => true,
                'backups' => $backups,
                'count' => count($backups)
            ]);
            break;

        case 'restore':
            // POST /api/system/restore - Backup wiederherstellen
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Methode nicht erlaubt']);
                break;
            }

            Auth::requireAuth();

            // Admin-Rechte erforderlich
            if (!Auth::isAdmin()) {
                http_response_code(403);
                echo json_encode(['error' => 'Keine Berechtigung']);
                break;
            }

            $input = json_decode(file_get_contents('php://input'), true);

            if (empty($input['filename'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Backup-Dateiname fehlt']);
                break;
            }

            $backupPath = Database::getInstance()->getConfig('backup_path');
            $backupFile = $backupPath . $input['filename'];

            // Sicherheitsprüfung
            if (!file_exists($backupFile) || !is_file($backupFile)) {
                http_response_code(404);
                echo json_encode(['error' => 'Backup-Datei nicht gefunden']);
                break;
            }

            // Aktuelles Backup erstellen vor Wiederherstellung
            $currentBackup = Database::getInstance()->createBackup();
            if (!$currentBackup['success']) {
                http_response_code(500);
                echo json_encode(['error' => 'Konnte aktuelles Backup nicht erstellen']);
                break;
            }

            // Wiederherstellung
            $dbPath = Database::getInstance()->getConfig('db_path');

            try {
                // Datenbank schließen
                Database::getInstance()->getConnection()->exec('PRAGMA wal_checkpoint(TRUNCATE)');
                Database::getInstance()->getConnection()->exec('VACUUM');

                // Datei kopieren
                if (copy($backupFile, $dbPath)) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Backup erfolgreich wiederhergestellt',
                        'current_backup' => $currentBackup['path']
                    ]);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Wiederherstellung fehlgeschlagen']);
                }
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Wiederherstellung fehlgeschlagen: ' . $e->getMessage()]);
            }
            break;

        case 'logs':
            // GET /api/system/logs - Log-Dateien
            Auth::requireAuth();

            // Admin-Rechte erforderlich
            if (!Auth::isAdmin()) {
                http_response_code(403);
                echo json_encode(['error' => 'Keine Berechtigung']);
                break;
            }

            $logPath = dirname(__DIR__) . '/logs/';
            $logs = [];

            if (is_dir($logPath)) {
                $files = glob($logPath . '*.log');
                foreach ($files as $file) {
                    $logs[] = [
                        'filename' => basename($file),
                        'size' => filesize($file),
                        'size_kb' => round(filesize($file) / 1024, 2),
                        'modified' => date('Y-m-d H:i:s', filemtime($file)),
                        'lines' => count(file($file))
                    ];
                }
            }

            echo json_encode([
                'success' => true,
                'logs' => $logs
            ]);
            break;

        case 'phpinfo':
            // GET /api/system/phpinfo - PHP-Info (nur Admin)
            Auth::requireAuth();

            if (!Auth::isAdmin()) {
                http_response_code(403);
                echo json_encode(['error' => 'Keine Berechtigung']);
                break;
            }

            // PHPInfo als Array
            ob_start();
            phpinfo(INFO_GENERAL | INFO_CONFIGURATION | INFO_MODULES);
            $phpinfo = ob_get_clean();

            // In strukturiertes Format konvertieren (vereinfacht)
            echo json_encode([
                'php_version' => phpversion(),
                'extensions' => get_loaded_extensions(),
                'ini_values' => ini_get_all()
            ]);
            break;

        case 'clear-cache':
            // POST /api/system/clear-cache - Cache leeren
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Methode nicht erlaubt']);
                break;
            }

            Auth::requireAuth();

            if (!Auth::isAdmin()) {
                http_response_code(403);
                echo json_encode(['error' => 'Keine Berechtigung']);
                break;
            }

            // Session-Cache leeren
            $sessionPath = session_save_path();
            if ($sessionPath && is_dir($sessionPath)) {
                $files = glob($sessionPath . '/sess_*');
                $deleted = 0;
                foreach ($files as $file) {
                    if (filemtime($file) < time() - 86400) { // Älter als 24h
                        unlink($file);
                        $deleted++;
                    }
                }
            }

            // Logs aufräumen (älter als 30 Tage)
            $logPath = dirname(__DIR__) . '/logs/';
            if (is_dir($logPath)) {
                $files = glob($logPath . '*.log');
                foreach ($files as $file) {
                    if (filemtime($file) < time() - (30 * 86400)) {
                        unlink($file);
                    }
                }
            }

            echo json_encode([
                'success' => true,
                'sessions_deleted' => $deleted ?? 0
            ]);
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpunkt nicht gefunden']);
            break;
    }
} catch (Exception $e) {
    error_log("System API Fehler: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Interner Serverfehler']);
}
