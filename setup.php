<?php

/**
 * KFZ Fac Pro - Setup & Installation
 * Erstellt Datenbank und Basis-Konfiguration
 */

// Fehlerberichterstattung
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Setup-Klasse
class Setup
{
    private $db;
    private $dbPath;
    private $errors = [];
    private $success = [];

    public function __construct()
    {
        $this->dbPath = __DIR__ . '/data/kfz.db';
    }

    /**
     * Führt komplettes Setup aus
     */
    public function run()
    {
        echo $this->getHeader();

        // POST-Request verarbeiten
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->processSetup();
        }

        // Status prüfen
        $this->checkStatus();

        // Formular anzeigen
        echo $this->getForm();
        echo $this->getFooter();
    }

    /**
     * Prüft aktuellen Status
     */
    private function checkStatus()
    {
        // PHP-Version
        if (version_compare(PHP_VERSION, '7.4.0', '<')) {
            $this->errors[] = 'PHP 7.4 oder höher erforderlich (Aktuell: ' . PHP_VERSION . ')';
        } else {
            $this->success[] = 'PHP-Version OK (' . PHP_VERSION . ')';
        }

        // PDO SQLite
        if (!extension_loaded('pdo_sqlite')) {
            $this->errors[] = 'PDO SQLite-Erweiterung nicht installiert';
        } else {
            $this->success[] = 'PDO SQLite verfügbar';
        }

        // Schreibrechte
        $dirs = ['data', 'backups', 'uploads', 'logs'];
        foreach ($dirs as $dir) {
            $path = __DIR__ . '/' . $dir;
            if (!file_exists($path)) {
                if (!@mkdir($path, 0755, true)) {
                    $this->errors[] = "Konnte Verzeichnis '$dir' nicht erstellen";
                } else {
                    $this->success[] = "Verzeichnis '$dir' erstellt";
                }
            } elseif (!is_writable($path)) {
                $this->errors[] = "Verzeichnis '$dir' nicht beschreibbar";
            } else {
                $this->success[] = "Verzeichnis '$dir' OK";
            }
        }

        // Datenbank-Status
        if (file_exists($this->dbPath)) {
            $this->success[] = 'Datenbank existiert bereits';
        }
    }

    /**
     * Setup durchführen
     */
    private function processSetup()
    {
        $action = $_POST['action'] ?? '';

        if ($action === 'install') {
            $this->installDatabase();
            $this->createAdminUser();
        } elseif ($action === 'reset') {
            $this->resetDatabase();
        }
    }

    /**
     * Datenbank installieren
     */
    private function installDatabase()
    {
        try {
            // Backup falls vorhanden
            if (file_exists($this->dbPath)) {
                $backupPath = __DIR__ . '/backups/backup_' . date('Y-m-d_H-i-s') . '.db';
                copy($this->dbPath, $backupPath);
                $this->success[] = 'Backup erstellt: ' . basename($backupPath);
            }

            // Neue Datenbank
            $this->db = new PDO('sqlite:' . $this->dbPath);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Optimierungen
            $this->db->exec("PRAGMA journal_mode=WAL");
            $this->db->exec("PRAGMA foreign_keys=ON");

            // Tabellen erstellen
            $this->createTables();

            // Standarddaten
            $this->insertDefaultData();

            $this->success[] = 'Datenbank erfolgreich installiert!';
        } catch (Exception $e) {
            $this->errors[] = 'Datenbankfehler: ' . $e->getMessage();
        }
    }

    /**
     * Tabellen erstellen
     */
    private function createTables()
    {
        // Users-Tabelle
        $this->db->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'user',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_login_at DATETIME,
            is_active BOOLEAN DEFAULT 1
        )");

        // Kunden-Tabelle
        $this->db->exec("CREATE TABLE IF NOT EXISTS kunden (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            kunden_nr TEXT UNIQUE NOT NULL,
            vorname TEXT NOT NULL,
            nachname TEXT NOT NULL,
            strasse TEXT,
            plz TEXT,
            ort TEXT,
            telefon TEXT,
            email TEXT,
            erstellt_am DATETIME DEFAULT CURRENT_TIMESTAMP,
            aktualisiert_am DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Fahrzeuge-Tabelle
        $this->db->exec("CREATE TABLE IF NOT EXISTS fahrzeuge (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            kunden_id INTEGER,
            kennzeichen TEXT NOT NULL,
            marke TEXT,
            modell TEXT,
            vin TEXT,
            baujahr INTEGER,
            farbe TEXT,
            farbcode TEXT,
            kilometerstand INTEGER DEFAULT 0,
            erstellt_am DATETIME DEFAULT CURRENT_TIMESTAMP,
            aktualisiert_am DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (kunden_id) REFERENCES kunden(id) ON DELETE CASCADE
        )");

        // Aufträge-Tabelle
        $this->db->exec("CREATE TABLE IF NOT EXISTS auftraege (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            auftrag_nr TEXT UNIQUE NOT NULL,
            kunden_id INTEGER,
            fahrzeug_id INTEGER,
            datum DATE NOT NULL,
            status TEXT DEFAULT 'offen',
            basis_stundenpreis DECIMAL(10,2) DEFAULT 110.00,
            gesamt_zeit DECIMAL(10,2) DEFAULT 0,
            gesamt_kosten DECIMAL(10,2) DEFAULT 0,
            arbeitszeiten_kosten DECIMAL(10,2) DEFAULT 0,
            mwst_betrag DECIMAL(10,2) DEFAULT 0,
            anfahrt_aktiv BOOLEAN DEFAULT 0,
            express_aktiv BOOLEAN DEFAULT 0,
            wochenend_aktiv BOOLEAN DEFAULT 0,
            anfahrt_betrag DECIMAL(10,2) DEFAULT 0,
            express_betrag DECIMAL(10,2) DEFAULT 0,
            wochenend_betrag DECIMAL(10,2) DEFAULT 0,
            bemerkungen TEXT,
            erstellt_am DATETIME DEFAULT CURRENT_TIMESTAMP,
            aktualisiert_am DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (kunden_id) REFERENCES kunden(id) ON DELETE SET NULL,
            FOREIGN KEY (fahrzeug_id) REFERENCES fahrzeuge(id) ON DELETE SET NULL
        )");

        // Rechnungen-Tabelle
        $this->db->exec("CREATE TABLE IF NOT EXISTS rechnungen (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            rechnung_nr TEXT UNIQUE NOT NULL,
            kunden_id INTEGER,
            fahrzeug_id INTEGER,
            auftrag_id INTEGER,
            datum DATE NOT NULL,
            faellig_am DATE,
            status TEXT DEFAULT 'offen',
            zwischensumme DECIMAL(10,2) DEFAULT 0,
            mwst_satz DECIMAL(5,2) DEFAULT 19,
            mwst_betrag DECIMAL(10,2) DEFAULT 0,
            gesamtbetrag DECIMAL(10,2) DEFAULT 0,
            gezahlt_am DATE,
            zahlungsart TEXT,
            bemerkungen TEXT,
            erstellt_am DATETIME DEFAULT CURRENT_TIMESTAMP,
            aktualisiert_am DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (kunden_id) REFERENCES kunden(id) ON DELETE SET NULL,
            FOREIGN KEY (fahrzeug_id) REFERENCES fahrzeuge(id) ON DELETE SET NULL,
            FOREIGN KEY (auftrag_id) REFERENCES auftraege(id) ON DELETE SET NULL
        )");

        $this->db->exec("CREATE TABLE IF NOT EXISTS fahrzeug_handel (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    handel_nr TEXT UNIQUE NOT NULL,
    typ TEXT NOT NULL CHECK (typ IN ('ankauf', 'verkauf')),
    kunden_id INTEGER,
    kaeufer_id INTEGER,
    fahrzeug_id INTEGER,
    datum DATE NOT NULL DEFAULT (date('now')),
    status TEXT DEFAULT 'offen',
    ankaufspreis DECIMAL(10,2),
    verkaufspreis DECIMAL(10,2),
    gewinn DECIMAL(10,2),
    kennzeichen TEXT,
    marke TEXT,
    modell TEXT,
    baujahr INTEGER,
    kilometerstand INTEGER,
    farbe TEXT,
    vin TEXT,
    zustand TEXT,
    bemerkungen TEXT,
    interne_notizen TEXT,
    verkauft_an TEXT,
    abgeschlossen_am DATETIME,
    erstellt_am DATETIME DEFAULT CURRENT_TIMESTAMP,
    aktualisiert_am DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kunden_id) REFERENCES kunden(id),
    FOREIGN KEY (kaeufer_id) REFERENCES kunden(id),
    FOREIGN KEY (fahrzeug_id) REFERENCES fahrzeuge(id)
)");

        // Einstellungen-Tabelle
        $this->db->exec("CREATE TABLE IF NOT EXISTS einstellungen (
            key TEXT PRIMARY KEY,
            value TEXT,
            beschreibung TEXT,
            aktualisiert_am DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->success[] = 'Alle Tabellen erstellt';
    }

    /**
     * Standarddaten einfügen
     */
    private function insertDefaultData()
    {
        // Einstellungen
        $settings = [
            ['mwst_satz', '19', 'Standard MwSt-Satz'],
            ['basis_stundenpreis', '110', 'Standard Stundenpreis'],
            ['anfahrt_kosten', '25', 'Standard Anfahrtskosten'],
            ['express_aufschlag', '50', 'Express-Aufschlag in %'],
            ['wochenend_aufschlag', '75', 'Wochenend-Aufschlag in %'],
            ['firmen_name', 'KFZ Werkstatt GmbH', 'Firmenname'],
            ['firmen_strasse', 'Musterstraße 1', 'Firmenadresse'],
            ['firmen_plz', '12345', 'Firmen-PLZ'],
            ['firmen_ort', 'Musterstadt', 'Firmen-Ort'],
            ['firmen_telefon', '0123/456789', 'Firmen-Telefon'],
            ['firmen_email', 'info@kfz-werkstatt.de', 'Firmen-E-Mail'],
            ['firmen_website', 'www.kfz-werkstatt.de', 'Firmen-Website'],
            ['bank_name', 'Musterbank', 'Bankname'],
            ['bank_iban', 'DE12 3456 7890 1234 5678 90', 'IBAN'],
            ['bank_bic', 'DEUTDEFF', 'BIC'],
            ['steuernummer', '123/456/78901', 'Steuernummer'],
            ['ustid', 'DE123456789', 'USt-ID']
        ];

        $stmt = $this->db->prepare("INSERT OR IGNORE INTO einstellungen (key, value, beschreibung) VALUES (?, ?, ?)");
        foreach ($settings as $setting) {
            $stmt->execute($setting);
        }

        $this->success[] = 'Standardeinstellungen eingefügt';
    }

    /**
     * Admin-Benutzer erstellen
     */
    private function createAdminUser()
    {
        $username = $_POST['admin_username'] ?? 'admin';
        $password = $_POST['admin_password'] ?? 'admin123';

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $this->db->prepare("INSERT OR REPLACE INTO users (username, password_hash, role, is_active) VALUES (?, ?, 'admin', 1)");
            $stmt->execute([$username, $passwordHash]);

            $this->success[] = "Admin-Benutzer erstellt: $username";
        } catch (Exception $e) {
            $this->errors[] = 'Fehler beim Erstellen des Admin-Benutzers: ' . $e->getMessage();
        }
    }

    /**
     * HTML-Header
     */
    private function getHeader()
    {
        return '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KFZ Fac Pro - Setup</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            padding: 40px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }
        .status {
            margin-bottom: 30px;
        }
        .success, .error {
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 500;
        }
        input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        button {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            margin-right: 10px;
            margin-top: 10px;
        }
        button:hover {
            background: #5a67d8;
        }
        .warning {
            background: #ff6b6b;
        }
        .warning:hover {
            background: #ff5252;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 KFZ Fac Pro Setup</h1>
        <p class="subtitle">PHP/SQLite Version - Installation & Konfiguration</p>';
    }

    /**
     * HTML-Formular
     */
    private function getForm()
    {
        $html = '<div class="status">';

        // Erfolgsmeldungen
        foreach ($this->success as $msg) {
            $html .= '<div class="success">✅ ' . $msg . '</div>';
        }

        // Fehlermeldungen
        foreach ($this->errors as $msg) {
            $html .= '<div class="error">❌ ' . $msg . '</div>';
        }

        $html .= '</div>';

        // Nur Formular anzeigen wenn keine kritischen Fehler
        if (empty($this->errors) || count($this->errors) < 2) {
            $html .= '
            <form method="post">
                <h3>Admin-Benutzer erstellen</h3>
                <div class="form-group">
                    <label for="admin_username">Benutzername:</label>
                    <input type="text" id="admin_username" name="admin_username" value="admin" required>
                </div>
                <div class="form-group">
                    <label for="admin_password">Passwort:</label>
                    <input type="password" id="admin_password" name="admin_password" value="admin123" required>
                </div>
                
                <button type="submit" name="action" value="install">Installation starten</button>
                <button type="submit" name="action" value="reset" class="warning" 
                        onclick="return confirm(\'Wirklich zurücksetzen? Alle Daten gehen verloren!\')">
                    Datenbank zurücksetzen
                </button>
            </form>';
        }

        // Link zur App wenn erfolgreich
        if (!empty($this->success) && strpos(implode('', $this->success), 'erfolgreich installiert') !== false) {
            $html .= '<hr style="margin: 30px 0;"><p><strong>✅ Installation erfolgreich!</strong></p>';
            $html .= '<p>Sie können sich jetzt <a href="/login">hier einloggen</a>.</p>';
        }

        return $html;
    }

    /**
     * HTML-Footer
     */
    private function getFooter()
    {
        return '
    </div>
</body>
</html>';
    }

    /**
     * Datenbank zurücksetzen
     */
    private function resetDatabase()
    {
        try {
            if (file_exists($this->dbPath)) {
                // Backup
                $backupPath = __DIR__ . '/backups/backup_reset_' . date('Y-m-d_H-i-s') . '.db';
                copy($this->dbPath, $backupPath);
                $this->success[] = 'Backup erstellt: ' . basename($backupPath);

                // Löschen
                unlink($this->dbPath);
            }

            // Neu installieren
            $this->installDatabase();
            $this->createAdminUser();

            $this->success[] = 'Datenbank wurde zurückgesetzt!';
        } catch (Exception $e) {
            $this->errors[] = 'Reset fehlgeschlagen: ' . $e->getMessage();
        }
    }
}

// Setup ausführen
$setup = new Setup();
$setup->run();
