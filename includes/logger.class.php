<?php

/**
 * Logger Class
 * Handles system logging to database and files
 */

require_once __DIR__ . '/../config/database.php';

class Logger
{
    private $db;
    private $logToFile = true;
    private $logToDatabase = true;
    private $logFile;
    private $maxFileSize = 10485760; // 10 MB

    // Log Levels
    const INFO = 'INFO';
    const SUCCESS = 'SUCCESS';
    const WARNING = 'WARNING';
    const ERROR = 'ERROR';
    const CRITICAL = 'CRITICAL';

    // Log Level Colors for Console
    private $levelColors = [
        'INFO' => "\033[36m",      // Cyan
        'SUCCESS' => "\033[32m",    // Green
        'WARNING' => "\033[33m",    // Yellow
        'ERROR' => "\033[31m",      // Red
        'CRITICAL' => "\033[35m"    // Magenta
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logFile = __DIR__ . '/../logs/system.log';

        // Create log directory if not exists
        $this->createLogDirectory();

        // Rotate log file if too large
        $this->rotateLogIfNeeded();
    }

    /**
     * Create log directory if it doesn't exist
     */
    private function createLogDirectory()
    {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
    }

    /**
     * Rotate log file if it exceeds max size
     */
    private function rotateLogIfNeeded()
    {
        if (file_exists($this->logFile) && filesize($this->logFile) > $this->maxFileSize) {
            $backupFile = $this->logFile . '.' . date('Y-m-d_H-i-s') . '.bak';
            rename($this->logFile, $backupFile);

            // Keep only last 5 backup files
            $this->cleanOldBackups();
        }
    }

    /**
     * Clean old backup files
     */
    private function cleanOldBackups()
    {
        $logDir = dirname($this->logFile);
        $backupFiles = glob($logDir . '/system.log.*.bak');

        if (count($backupFiles) > 5) {
            // Sort by modification time
            usort($backupFiles, function ($a, $b) {
                return filemtime($a) - filemtime($b);
            });

            // Delete oldest files
            $filesToDelete = array_slice($backupFiles, 0, count($backupFiles) - 5);
            foreach ($filesToDelete as $file) {
                unlink($file);
            }
        }
    }

    /**
     * Main log method
     */
    public function log($level, $message, $userId = null, $category = null, $additionalData = null)
    {
        $timestamp = date('Y-m-d H:i:s');

        // Log to database
        if ($this->logToDatabase) {
            $this->logToDatabase($level, $message, $userId, $category, $additionalData);
        }

        // Log to file
        if ($this->logToFile) {
            $this->logToFile($timestamp, $level, $message, $userId, $category);
        }

        // Log to console (if in CLI mode)
        if (php_sapi_name() === 'cli') {
            $this->logToConsole($timestamp, $level, $message);
        }
    }

    /**
     * Log to database
     */
    private function logToDatabase($level, $message, $userId, $category, $additionalData)
    {
        try {
            $this->db->insert('system_logs', [
                'level' => $level,
                'category' => $category,
                'message' => $message,
                'user_id' => $userId,
                'ip_address' => $this->getClientIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'additional_data' => $additionalData ? json_encode($additionalData) : null
            ]);
        } catch (Exception $e) {
            // If database logging fails, at least write to file
            error_log("Database logging failed: " . $e->getMessage());
        }
    }

    /**
     * Log to file
     */
    private function logToFile($timestamp, $level, $message, $userId, $category)
    {
        $userInfo = $userId ? " [User: {$userId}]" : '';
        $categoryInfo = $category ? " [{$category}]" : '';
        $ip = $this->getClientIP();

        $logEntry = sprintf(
            "[%s] [%s]%s%s [IP: %s] %s\n",
            $timestamp,
            $level,
            $categoryInfo,
            $userInfo,
            $ip,
            $message
        );

        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Log to console (for CLI)
     */
    private function logToConsole($timestamp, $level, $message)
    {
        $color = $this->levelColors[$level] ?? "\033[0m";
        $reset = "\033[0m";

        echo "{$color}[{$timestamp}] [{$level}] {$message}{$reset}\n";
    }

    /**
     * Convenience methods for different log levels
     */
    public function info($message, $userId = null, $category = null, $data = null)
    {
        $this->log(self::INFO, $message, $userId, $category, $data);
    }

    public function success($message, $userId = null, $category = null, $data = null)
    {
        $this->log(self::SUCCESS, $message, $userId, $category, $data);
    }

    public function warning($message, $userId = null, $category = null, $data = null)
    {
        $this->log(self::WARNING, $message, $userId, $category, $data);
    }

    public function error($message, $userId = null, $category = null, $data = null)
    {
        $this->log(self::ERROR, $message, $userId, $category, $data);
    }

    public function critical($message, $userId = null, $category = null, $data = null)
    {
        $this->log(self::CRITICAL, $message, $userId, $category, $data);

        // For critical errors, also send notification (if configured)
        $this->sendCriticalNotification($message);
    }

    /**
     * Log exception
     */
    public function logException(Exception $e, $userId = null, $category = null)
    {
        $message = sprintf(
            "Exception: %s in %s:%d\nStack trace:\n%s",
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );

        $this->error($message, $userId, $category, [
            'exception_class' => get_class($e),
            'code' => $e->getCode()
        ]);
    }

    /**
     * Log API request
     */
    public function logApiRequest($endpoint, $method, $params, $response, $userId = null)
    {
        $this->info(
            "API Request: {$method} {$endpoint}",
            $userId,
            'API',
            [
                'endpoint' => $endpoint,
                'method' => $method,
                'params' => $params,
                'response' => $response
            ]
        );
    }

    /**
     * Log database query (for debugging)
     */
    public function logQuery($sql, $params = [], $executionTime = null)
    {
        if ($executionTime > 1) { // Log slow queries
            $this->warning(
                "Slow query ({$executionTime}s): {$sql}",
                null,
                'DATABASE',
                ['params' => $params, 'execution_time' => $executionTime]
            );
        }
    }

    /**
     * Get logs from database
     */
    public function getLogs($filters = [])
    {
        $where = ['1=1'];
        $params = [];

        // Apply filters
        if (!empty($filters['level'])) {
            $where[] = 'level = :level';
            $params[':level'] = $filters['level'];
        }

        if (!empty($filters['category'])) {
            $where[] = 'category = :category';
            $params[':category'] = $filters['category'];
        }

        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = :user_id';
            $params[':user_id'] = $filters['user_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'timestamp >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'timestamp <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }

        $whereClause = implode(' AND ', $where);
        $limit = $filters['limit'] ?? 100;
        $offset = $filters['offset'] ?? 0;

        return $this->db->select(
            "SELECT l.*, u.username, u.full_name 
             FROM system_logs l 
             LEFT JOIN users u ON l.user_id = u.id 
             WHERE {$whereClause} 
             ORDER BY l.timestamp DESC 
             LIMIT :limit OFFSET :offset",
            array_merge($params, [':limit' => $limit, ':offset' => $offset])
        );
    }

    /**
     * Get log statistics
     */
    public function getLogStats($period = 'today')
    {
        $dateCondition = $this->getDateCondition($period);

        $stats = $this->db->select(
            "SELECT 
                level,
                COUNT(*) as count 
             FROM system_logs 
             WHERE {$dateCondition}
             GROUP BY level"
        );

        $result = [
            'total' => 0,
            'by_level' => []
        ];

        foreach ($stats as $stat) {
            $result['by_level'][$stat['level']] = $stat['count'];
            $result['total'] += $stat['count'];
        }

        return $result;
    }

    /**
     * Get date condition for period
     */
    private function getDateCondition($period)
    {
        switch ($period) {
            case 'today':
                return "DATE(timestamp) = DATE('now')";
            case 'yesterday':
                return "DATE(timestamp) = DATE('now', '-1 day')";
            case 'week':
                return "timestamp >= DATE('now', '-7 days')";
            case 'month':
                return "timestamp >= DATE('now', '-30 days')";
            default:
                return "1=1";
        }
    }

    /**
     * Clear old logs
     */
    public function clearOldLogs($daysToKeep = 30)
    {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));

        $deleted = $this->db->delete(
            'system_logs',
            'timestamp < :cutoff',
            [':cutoff' => $cutoffDate]
        );

        if ($deleted) {
            $this->info("Cleared old logs older than {$daysToKeep} days", null, 'MAINTENANCE');
        }

        return $deleted;
    }

    /**
     * Send critical notification
     */
    private function sendCriticalNotification($message)
    {
        // Here you could implement email, SMS, or push notifications
        // For now, just create a notification in the database

        try {
            // Get all admin users
            $admins = $this->db->select(
                "SELECT id FROM users WHERE role = 'admin' AND is_active = 1"
            );

            foreach ($admins as $admin) {
                $this->db->insert('notifications', [
                    'user_id' => $admin['id'],
                    'type' => 'critical',
                    'title' => 'Kritischer Systemfehler',
                    'message' => $message
                ]);
            }
        } catch (Exception $e) {
            // Silent fail for notifications
        }
    }

    /**
     * Get client IP address
     */
    private function getClientIP()
    {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];

        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'CLI';
    }

    /**
     * Export logs to CSV
     */
    public function exportToCSV($filters = [])
    {
        $logs = $this->getLogs($filters);

        $csv = "Timestamp,Level,Category,User,IP Address,Message\n";

        foreach ($logs as $log) {
            $csv .= sprintf(
                '"%s","%s","%s","%s","%s","%s"' . "\n",
                $log['timestamp'],
                $log['level'],
                $log['category'] ?? '',
                $log['username'] ?? '',
                $log['ip_address'] ?? '',
                str_replace('"', '""', $log['message'])
            );
        }

        return $csv;
    }
}

// Create global logger instance
$logger = new Logger();
