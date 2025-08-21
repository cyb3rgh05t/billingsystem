<?php

/**
 * Database Connection Class
 * Singleton Pattern für SQLite Verbindung
 */

class Database
{
    private static $instance = null;
    private $connection;
    private $dbPath;

    /**
     * Private Constructor - verhindert direkte Instanziierung
     */
    private function __construct()
    {
        // Datenbank-Pfad
        $this->dbPath = __DIR__ . '/../database/billing.db';

        try {
            // SQLite Verbindung aufbauen
            $this->connection = new PDO('sqlite:' . $this->dbPath);

            // Error Mode setzen
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Default Fetch Mode
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Foreign Keys aktivieren
            $this->connection->exec('PRAGMA foreign_keys = ON');

            // Journal Mode für bessere Performance
            $this->connection->exec('PRAGMA journal_mode = WAL');

            // Erstelle Tabellen falls sie nicht existieren
            $this->createTablesIfNotExist();
        } catch (PDOException $e) {
            $this->logError("Database connection failed: " . $e->getMessage());
            die("Datenbankverbindung fehlgeschlagen. Bitte Administrator kontaktieren.");
        }
    }

    /**
     * Singleton Instance holen
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * PDO Connection zurückgeben
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Prepared Statement ausführen
     */
    public function query($sql, $params = [])
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $this->logError("Query failed: " . $e->getMessage() . " SQL: " . $sql);
            return false;
        }
    }

    /**
     * Select Query - gibt alle Zeilen zurück
     */
    public function select($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetchAll() : [];
    }

    /**
     * Select One - gibt eine Zeile zurück
     */
    public function selectOne($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetch() : null;
    }

    /**
     * Insert - gibt Last Insert ID zurück
     */
    public function insert($table, $data)
    {
        $columns = array_keys($data);
        $values = array_map(function ($col) {
            return ':' . $col;
        }, $columns);

        $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ") 
                VALUES (" . implode(', ', $values) . ")";

        if ($this->query($sql, $data)) {
            return $this->connection->lastInsertId();
        }
        return false;
    }

    /**
     * Update
     */
    public function update($table, $data, $where, $whereParams = [])
    {
        $setParts = [];
        $params = [];

        foreach ($data as $column => $value) {
            $setParts[] = "{$column} = :{$column}";
            $params[$column] = $value;
        }

        $sql = "UPDATE {$table} SET " . implode(', ', $setParts) . " WHERE {$where}";

        // Where Parameter hinzufügen
        foreach ($whereParams as $key => $value) {
            $params[$key] = $value;
        }

        return $this->query($sql, $params);
    }

    /**
     * Delete
     */
    public function delete($table, $where, $params = [])
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return $this->query($sql, $params);
    }

    /**
     * Transaction starten
     */
    public function beginTransaction()
    {
        return $this->connection->beginTransaction();
    }

    /**
     * Transaction committen
     */
    public function commit()
    {
        return $this->connection->commit();
    }

    /**
     * Transaction rollback
     */
    public function rollback()
    {
        return $this->connection->rollback();
    }

    /**
     * Tabellen erstellen falls nicht vorhanden
     */
    private function createTablesIfNotExist()
    {
        $sql = "
            -- Users Tabelle
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                email TEXT UNIQUE NOT NULL,
                full_name TEXT,
                role TEXT DEFAULT 'user' CHECK(role IN ('admin', 'user', 'viewer')),
                is_active INTEGER DEFAULT 1,
                last_login DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            -- Kunden Tabelle
            CREATE TABLE IF NOT EXISTS customers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                customer_number TEXT UNIQUE NOT NULL,
                company_name TEXT NOT NULL,
                contact_person TEXT,
                email TEXT,
                phone TEXT,
                address TEXT,
                city TEXT,
                postal_code TEXT,
                country TEXT DEFAULT 'Deutschland',
                tax_id TEXT,
                notes TEXT,
                is_active INTEGER DEFAULT 1,
                created_by INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id)
            );

            -- Rechnungen Tabelle
            CREATE TABLE IF NOT EXISTS invoices (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                invoice_number TEXT UNIQUE NOT NULL,
                customer_id INTEGER NOT NULL,
                issue_date DATE NOT NULL,
                due_date DATE NOT NULL,
                subtotal DECIMAL(10,2),
                tax_rate DECIMAL(5,2) DEFAULT 19.00,
                tax_amount DECIMAL(10,2),
                total_amount DECIMAL(10,2),
                status TEXT DEFAULT 'draft' CHECK(status IN ('draft', 'sent', 'paid', 'overdue', 'cancelled')),
                payment_method TEXT,
                paid_date DATE,
                notes TEXT,
                created_by INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (customer_id) REFERENCES customers(id),
                FOREIGN KEY (created_by) REFERENCES users(id)
            );

            -- Rechnungspositionen
            CREATE TABLE IF NOT EXISTS invoice_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                invoice_id INTEGER NOT NULL,
                position INTEGER NOT NULL,
                description TEXT NOT NULL,
                quantity DECIMAL(10,2) NOT NULL,
                unit TEXT DEFAULT 'Stk',
                unit_price DECIMAL(10,2) NOT NULL,
                discount_percent DECIMAL(5,2) DEFAULT 0,
                total DECIMAL(10,2) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
            );

            -- Produkte/Services Tabelle
            CREATE TABLE IF NOT EXISTS products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                product_code TEXT UNIQUE NOT NULL,
                name TEXT NOT NULL,
                description TEXT,
                unit TEXT DEFAULT 'Stk',
                price DECIMAL(10,2) NOT NULL,
                tax_rate DECIMAL(5,2) DEFAULT 19.00,
                category TEXT,
                is_active INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            -- System Logs Tabelle
            CREATE TABLE IF NOT EXISTS system_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                level TEXT NOT NULL CHECK(level IN ('INFO', 'SUCCESS', 'WARNING', 'ERROR', 'CRITICAL')),
                category TEXT,
                message TEXT NOT NULL,
                user_id INTEGER,
                ip_address TEXT,
                user_agent TEXT,
                additional_data TEXT,
                FOREIGN KEY (user_id) REFERENCES users(id)
            );

            -- Sessions Tabelle
            CREATE TABLE IF NOT EXISTS sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                session_id TEXT UNIQUE NOT NULL,
                user_id INTEGER NOT NULL,
                ip_address TEXT,
                user_agent TEXT,
                last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            );

            -- Einstellungen Tabelle
            CREATE TABLE IF NOT EXISTS settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key TEXT UNIQUE NOT NULL,
                setting_value TEXT,
                setting_type TEXT DEFAULT 'string',
                description TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            -- Notifications Tabelle
            CREATE TABLE IF NOT EXISTS notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                type TEXT NOT NULL,
                title TEXT NOT NULL,
                message TEXT,
                is_read INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            );
        ";

        try {
            $this->connection->exec($sql);

            // Indexes erstellen
            $this->createIndexes();

            // Default-Daten einfügen
            $this->insertDefaultData();
        } catch (PDOException $e) {
            $this->logError("Table creation failed: " . $e->getMessage());
        }
    }

    /**
     * Indexes für bessere Performance
     */
    private function createIndexes()
    {
        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_invoices_customer ON invoices(customer_id)",
            "CREATE INDEX IF NOT EXISTS idx_invoices_status ON invoices(status)",
            "CREATE INDEX IF NOT EXISTS idx_invoice_items_invoice ON invoice_items(invoice_id)",
            "CREATE INDEX IF NOT EXISTS idx_logs_timestamp ON system_logs(timestamp)",
            "CREATE INDEX IF NOT EXISTS idx_logs_user ON system_logs(user_id)",
            "CREATE INDEX IF NOT EXISTS idx_sessions_user ON sessions(user_id)",
            "CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(user_id)",
            "CREATE INDEX IF NOT EXISTS idx_customers_number ON customers(customer_number)",
            "CREATE INDEX IF NOT EXISTS idx_invoices_number ON invoices(invoice_number)"
        ];

        foreach ($indexes as $index) {
            try {
                $this->connection->exec($index);
            } catch (PDOException $e) {
                // Index existiert bereits
            }
        }
    }

    /**
     * Default-Daten einfügen
     */
    private function insertDefaultData()
    {
        // Prüfe ob Admin User existiert
        $adminExists = $this->selectOne("SELECT id FROM users WHERE username = 'admin'");

        if (!$adminExists) {
            // Admin User erstellen (Passwort: admin123)
            $this->insert('users', [
                'username' => 'admin',
                'password' => password_hash('admin123', PASSWORD_DEFAULT),
                'email' => 'admin@billing.local',
                'full_name' => 'System Administrator',
                'role' => 'admin',
                'is_active' => 1
            ]);

            // Demo User erstellen (Passwort: demo123)
            $this->insert('users', [
                'username' => 'demo',
                'password' => password_hash('demo123', PASSWORD_DEFAULT),
                'email' => 'demo@billing.local',
                'full_name' => 'Demo User',
                'role' => 'user',
                'is_active' => 1
            ]);
        }

        // Default Settings
        $settingsExist = $this->selectOne("SELECT id FROM settings WHERE setting_key = 'company_name'");

        if (!$settingsExist) {
            $defaultSettings = [
                ['company_name', 'Meine Firma GmbH', 'string', 'Firmenname'],
                ['invoice_prefix', 'INV-', 'string', 'Rechnungsnummer-Präfix'],
                ['invoice_next_number', '1001', 'integer', 'Nächste Rechnungsnummer'],
                ['tax_rate', '19', 'decimal', 'Standard MwSt-Satz'],
                ['currency', 'EUR', 'string', 'Währung'],
                ['currency_symbol', '€', 'string', 'Währungssymbol'],
                ['date_format', 'd.m.Y', 'string', 'Datumsformat'],
                ['payment_terms', '14', 'integer', 'Zahlungsziel in Tagen'],
                ['company_address', 'Musterstraße 123', 'string', 'Firmenadresse'],
                ['company_city', '12345 Musterstadt', 'string', 'PLZ und Stadt'],
                ['company_email', 'info@meinefirma.de', 'string', 'Firma E-Mail'],
                ['company_phone', '+49 123 456789', 'string', 'Firma Telefon'],
                ['company_tax_id', 'DE123456789', 'string', 'Steuernummer'],
                ['invoice_footer', 'Vielen Dank für Ihr Vertrauen!', 'text', 'Rechnungs-Fußzeile']
            ];

            foreach ($defaultSettings as $setting) {
                $this->insert('settings', [
                    'setting_key' => $setting[0],
                    'setting_value' => $setting[1],
                    'setting_type' => $setting[2],
                    'description' => $setting[3]
                ]);
            }
        }
    }

    /**
     * Error Logging
     */
    private function logError($message)
    {
        $logFile = __DIR__ . '/../logs/database_errors.log';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] {$message}\n";

        // Verzeichnis erstellen falls nicht vorhanden
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        error_log($logEntry, 3, $logFile);
    }

    /**
     * Statistiken abrufen
     */
    public function getStats()
    {
        $stats = [];

        // Anzahl Kunden
        $result = $this->selectOne("SELECT COUNT(*) as count FROM customers WHERE is_active = 1");
        $stats['customers'] = $result['count'];

        // Anzahl offene Rechnungen
        $result = $this->selectOne("SELECT COUNT(*) as count FROM invoices WHERE status IN ('draft', 'sent', 'overdue')");
        $stats['open_invoices'] = $result['count'];

        // Gesamtumsatz
        $result = $this->selectOne("SELECT SUM(total_amount) as total FROM invoices WHERE status = 'paid'");
        $stats['total_revenue'] = $result['total'] ?? 0;

        // Offene Beträge
        $result = $this->selectOne("SELECT SUM(total_amount) as total FROM invoices WHERE status IN ('sent', 'overdue')");
        $stats['outstanding'] = $result['total'] ?? 0;

        return $stats;
    }
}

// Automatische Initialisierung beim ersten Include
$db = Database::getInstance();
