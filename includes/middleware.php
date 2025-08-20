<?php

/**
 * KFZ Fac Pro - Middleware-Funktionen
 */

/**
 * Lizenz-Check Middleware
 */
function requireValidLicense()
{
    // Lizenz-Datei prüfen
    $licenseFile = dirname(__DIR__) . '/data/license.json';

    if (!file_exists($licenseFile)) {
        sendLicenseError('Keine Lizenz aktiviert');
        return false;
    }

    // Lizenz laden
    $license = json_decode(file_get_contents($licenseFile), true);

    if (!$license) {
        sendLicenseError('Lizenz ungültig');
        return false;
    }

    // Ablaufdatum prüfen
    if (isset($license['expires_at'])) {
        $expiresAt = strtotime($license['expires_at']);
        if ($expiresAt < time()) {
            sendLicenseError('Lizenz abgelaufen');
            return false;
        }
    }

    // Tägliche Online-Validierung (im Hintergrund)
    $lastCheck = isset($license['last_check']) ? strtotime($license['last_check']) : 0;
    $now = time();

    if ($now - $lastCheck > 86400) { // 24 Stunden
        // Asynchron im Hintergrund validieren
        // Blockiert nicht den Request
        scheduleBackgroundValidation($license['license_key']);
    }

    return true;
}

/**
 * Lizenz-Feature prüfen
 */
function hasLicenseFeature($feature)
{
    $licenseFile = dirname(__DIR__) . '/data/license.json';

    if (!file_exists($licenseFile)) {
        return false;
    }

    $license = json_decode(file_get_contents($licenseFile), true);

    if (!$license || !isset($license['features'])) {
        return false;
    }

    return in_array($feature, $license['features']);
}

/**
 * Lizenz-Fehler senden
 */
function sendLicenseError($message)
{
    http_response_code(402); // Payment Required
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Lizenzfehler',
        'message' => $message,
        'license_required' => true
    ]);
    exit;
}

/**
 * Hintergrund-Validierung planen
 */
function scheduleBackgroundValidation($licenseKey)
{
    // Validierungs-Job in Queue schreiben
    $queueFile = dirname(__DIR__) . '/data/validation_queue.txt';
    file_put_contents($queueFile, $licenseKey . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Rate-Limiting
 */
class RateLimiter
{
    private $cacheDir;
    private $maxRequests;
    private $timeWindow;

    public function __construct($maxRequests = 60, $timeWindow = 60)
    {
        $this->cacheDir = dirname(__DIR__) . '/data/rate_limit/';
        $this->maxRequests = $maxRequests;
        $this->timeWindow = $timeWindow;

        // Verzeichnis erstellen
        if (!file_exists($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }

        // Alte Dateien aufräumen
        $this->cleanup();
    }

    public function check($identifier = null)
    {
        if (!$identifier) {
            $identifier = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }

        $file = $this->cacheDir . md5($identifier) . '.txt';
        $now = time();

        // Bisherige Requests laden
        $requests = [];
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $requests = array_filter(explode("\n", $content));
        }

        // Alte Requests entfernen
        $requests = array_filter($requests, function ($timestamp) use ($now) {
            return ($now - intval($timestamp)) < $this->timeWindow;
        });

        // Prüfen ob Limit erreicht
        if (count($requests) >= $this->maxRequests) {
            return false;
        }

        // Neuen Request hinzufügen
        $requests[] = $now;
        file_put_contents($file, implode("\n", $requests));

        return true;
    }

    public function cleanup()
    {
        $files = glob($this->cacheDir . '*.txt');
        $now = time();

        foreach ($files as $file) {
            if (filemtime($file) < ($now - 3600)) { // Älter als 1 Stunde
                unlink($file);
            }
        }
    }
}

/**
 * CORS-Headers setzen
 */
function setCorsHeaders()
{
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Max-Age: 86400');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

/**
 * Request-Logging
 */
function logRequest($action, $data = [])
{
    $logFile = dirname(__DIR__) . '/logs/requests.log';
    $logDir = dirname($logFile);

    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'method' => $_SERVER['REQUEST_METHOD'],
        'uri' => $_SERVER['REQUEST_URI'],
        'action' => $action,
        'user' => $_SESSION['username'] ?? 'guest',
        'data' => $data
    ];

    $logLine = json_encode($entry) . "\n";
    file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);

    // Log-Rotation (max 10MB)
    if (filesize($logFile) > 10 * 1024 * 1024) {
        rename($logFile, $logFile . '.' . date('Y-m-d-H-i-s'));
    }
}

/**
 * Wartungsmodus prüfen
 */
function checkMaintenanceMode()
{
    require_once dirname(__DIR__) . '/models/Einstellung.php';
    $einstellung = new Einstellung();

    if ($einstellung->get('wartungsmodus', '0') === '1') {
        // Admin darf trotzdem rein
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            http_response_code(503); // Service Unavailable
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Wartungsmodus',
                'message' => 'Das System befindet sich derzeit im Wartungsmodus. Bitte versuchen Sie es später erneut.'
            ]);
            exit;
        }
    }
}

/**
 * Backup-Check (täglich)
 */
function checkAutoBackup()
{
    require_once dirname(__DIR__) . '/models/Einstellung.php';
    $einstellung = new Einstellung();

    $backupSettings = $einstellung->getBackupSettings();

    if (!$backupSettings['enabled']) {
        return;
    }

    $lastBackupFile = dirname(__DIR__) . '/data/last_backup.txt';
    $lastBackup = file_exists($lastBackupFile) ? intval(file_get_contents($lastBackupFile)) : 0;
    $now = time();

    $interval = 86400; // Standard: täglich

    switch ($backupSettings['interval']) {
        case 'hourly':
            $interval = 3600;
            break;
        case 'weekly':
            $interval = 604800;
            break;
        case 'monthly':
            $interval = 2592000;
            break;
    }

    if ($now - $lastBackup > $interval) {
        // Backup erstellen
        require_once dirname(__DIR__) . '/config/database.php';
        $db = Database::getInstance();
        $result = $db->createBackup();

        if ($result['success']) {
            file_put_contents($lastBackupFile, $now);
            logRequest('auto_backup', ['file' => $result['path']]);
        }
    }
}

/**
 * Sicherheits-Headers setzen
 */
function setSecurityHeaders()
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // CSP nur in Produktion
    if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'production') {
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';");
    }
}

/**
 * Eingabe-Validierung
 */
function validateInput($data, $rules)
{
    $errors = [];

    foreach ($rules as $field => $rule) {
        $value = isset($data[$field]) ? $data[$field] : null;

        // Required
        if (isset($rule['required']) && $rule['required'] && empty($value)) {
            $errors[$field] = 'Feld ist erforderlich';
            continue;
        }

        // Type
        if (isset($rule['type']) && !empty($value)) {
            switch ($rule['type']) {
                case 'email':
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[$field] = 'Ungültige E-Mail-Adresse';
                    }
                    break;

                case 'number':
                    if (!is_numeric($value)) {
                        $errors[$field] = 'Muss eine Zahl sein';
                    }
                    break;

                case 'date':
                    if (!strtotime($value)) {
                        $errors[$field] = 'Ungültiges Datum';
                    }
                    break;
            }
        }

        // Min Length
        if (isset($rule['min_length']) && strlen($value) < $rule['min_length']) {
            $errors[$field] = "Mindestens {$rule['min_length']} Zeichen erforderlich";
        }

        // Max Length
        if (isset($rule['max_length']) && strlen($value) > $rule['max_length']) {
            $errors[$field] = "Maximal {$rule['max_length']} Zeichen erlaubt";
        }

        // Pattern
        if (isset($rule['pattern']) && !preg_match($rule['pattern'], $value)) {
            $errors[$field] = isset($rule['pattern_message']) ? $rule['pattern_message'] : 'Ungültiges Format';
        }
    }

    return $errors;
}

/**
 * Sanitize Input
 */
function sanitizeInput($data)
{
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }

    // XSS-Schutz
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    $data = strip_tags($data);

    return $data;
}
