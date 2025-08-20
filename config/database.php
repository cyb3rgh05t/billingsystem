<?php

/**
 * KFZ Fac Pro - Datenbankverbindung
 * SQLite-Verbindung mit PDO
 */

class Database
{
    private static $instance = null;
    private $db;
    private $config;

    private function __construct()
    {
        $this->config = [
            'db_path' => dirname(__DIR__) . '/data/kfz.db',
            'backup_path' => dirname(__DIR__) . '/backups/',
            'upload_path' => dirname(__DIR__) . '/uploads/'
        ];

        $this->connect();
    }

    private function connect()
    {
        try {
            // Stelle sicher, dass das data-Verzeichnis existiert
            $dataDir = dirname($this->config['db_path']);
            if (!file_exists($dataDir)) {
                mkdir($dataDir, 0755, true);
            }

            // SQLite-Verbindung mit PDO
            $this->db = new PDO('sqlite:' . $this->config['db_path']);

            // Error-Modus setzen
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Default Fetch-Modus
            $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // SQLite-Optimierungen (wie im Original)
            $this->db->exec("PRAGMA journal_mode=WAL");
            $this->db->exec("PRAGMA foreign_keys=ON");
            $this->db->exec("PRAGMA synchronous=NORMAL");
            $this->db->exec("PRAGMA cache_size=10000");
        } catch (PDOException $e) {
            die("Datenbankverbindung fehlgeschlagen: " . $e->getMessage());
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->db;
    }

    public function getConfig($key = null)
    {
        if ($key === null) {
            return $this->config;
        }
        return isset($this->config[$key]) ? $this->config[$key] : null;
    }

    // Hilfsmethoden für Transaktionen
    public function beginTransaction()
    {
        return $this->db->beginTransaction();
    }

    public function commit()
    {
        return $this->db->commit();
    }

    public function rollback()
    {
        return $this->db->rollBack();
    }

    // Prepared Statement Helper
    public function prepare($sql)
    {
        return $this->db->prepare($sql);
    }

    // Direkte Query-Ausführung (für einfache Queries)
    public function query($sql)
    {
        return $this->db->query($sql);
    }

    // Last Insert ID
    public function lastInsertId()
    {
        return $this->db->lastInsertId();
    }

    // Backup-Funktion
    public function createBackup()
    {
        $timestamp = date('Y-m-d_H-i-s');
        $backupPath = $this->config['backup_path'] . 'backup_' . $timestamp . '.db';

        // Stelle sicher, dass Backup-Verzeichnis existiert
        if (!file_exists($this->config['backup_path'])) {
            mkdir($this->config['backup_path'], 0755, true);
        }

        // Kopiere Datenbank
        if (copy($this->config['db_path'], $backupPath)) {
            return [
                'success' => true,
                'path' => $backupPath,
                'timestamp' => $timestamp
            ];
        }

        return ['success' => false, 'error' => 'Backup konnte nicht erstellt werden'];
    }
}

// Globale Datenbankinstanz für einfachen Zugriff
$db = Database::getInstance()->getConnection();
