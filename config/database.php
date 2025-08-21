<?php
require_once __DIR__ . "/../includes/logger.php";

class Database {
    private static $instance = null;
    private $connection;
    private $db_file;
    
    private function __construct() {
        $this->db_file = __DIR__ . "/../database/kfz_billing.db";
        
        try {
            $this->connection = new PDO("sqlite:" . $this->db_file);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Enable foreign keys
            $this->connection->exec("PRAGMA foreign_keys = ON");
            
            Logger::info("Database connection established");
        } catch (PDOException $e) {
            Logger::error("Database connection failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function initializeDatabase() {
        $sql = file_get_contents(__DIR__ . "/../database/init.sql");
        try {
            $this->connection->exec($sql);
            Logger::info("Database initialized successfully");
            return true;
        } catch (PDOException $e) {
            Logger::error("Database initialization failed: " . $e->getMessage());
            return false;
        }
    }
}
