<?php
// ========================================
// setup.php - Complete System Setup
// ========================================

echo "=====================================\n";
echo "   KFZ Billing System Setup v1.0    \n";
echo "=====================================\n\n";

// Create directory structure
$directories = [
    'config',
    'database',
    'includes',
    'api',
    'models',
    'assets/css',
    'assets/js/modules',
    'assets/images',
    'logs',
    'templates',
    'uploads',
    'backup'
];

echo "[1/6] Creating directory structure...\n";
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
        echo "  ✓ Created: {$dir}\n";
    } else {
        echo "  • Exists: {$dir}\n";
    }
}

// ========================================
// Create includes/logger.php
// ========================================
echo "\n[2/6] Creating Logger class...\n";
$loggerContent = '<?php
class Logger {
    private static $logFile;
    
    public static function init() {
        self::$logFile = __DIR__ . "/../logs/app.log";
        if (!file_exists(dirname(self::$logFile))) {
            mkdir(dirname(self::$logFile), 0777, true);
        }
    }
    
    public static function log($level, $message, $context = []) {
        if (!self::$logFile) {
            self::init();
        }
        
        $timestamp = date("Y-m-d H:i:s");
        $contextStr = !empty($context) ? " " . json_encode($context) : "";
        $logMessage = "[{$timestamp}] [{$level}] {$message}{$contextStr}" . PHP_EOL;
        
        file_put_contents(self::$logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        // Also output to console
        echo "  [{$level}] {$message}\n";
    }
    
    public static function info($message, $context = []) {
        self::log("INFO", $message, $context);
    }
    
    public static function warning($message, $context = []) {
        self::log("WARNING", $message, $context);
    }
    
    public static function error($message, $context = []) {
        self::log("ERROR", $message, $context);
    }
    
    public static function success($message, $context = []) {
        self::log("SUCCESS", $message, $context);
    }
}
';
file_put_contents('includes/logger.php', $loggerContent);
echo "  ✓ Logger class created\n";

// ========================================
// Create config/database.php
// ========================================
echo "\n[3/6] Creating Database class...\n";
$databaseContent = '<?php
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
';
file_put_contents('config/database.php', $databaseContent);
echo "  ✓ Database class created\n";

// ========================================
// Create database/init.sql
// ========================================
echo "\n[4/6] Creating database schema...\n";
$initSQL = '-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    role VARCHAR(20) DEFAULT "user",
    active INTEGER DEFAULT 1,
    last_login DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Customers table
CREATE TABLE IF NOT EXISTS customers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_name VARCHAR(100),
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    street VARCHAR(100),
    house_number VARCHAR(10),
    postal_code VARCHAR(10),
    city VARCHAR(50),
    country VARCHAR(50) DEFAULT "Deutschland",
    tax_id VARCHAR(50),
    notes TEXT,
    created_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Vehicles table
CREATE TABLE IF NOT EXISTS vehicles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL,
    license_plate VARCHAR(20) NOT NULL,
    manufacturer VARCHAR(50),
    model VARCHAR(50),
    year INTEGER,
    vin VARCHAR(17),
    engine_code VARCHAR(20),
    color VARCHAR(30),
    mileage INTEGER,
    fuel_type VARCHAR(20),
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- Orders table
CREATE TABLE IF NOT EXISTS orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_number VARCHAR(20) UNIQUE NOT NULL,
    customer_id INTEGER NOT NULL,
    vehicle_id INTEGER,
    status VARCHAR(20) DEFAULT "pending",
    description TEXT,
    total_amount DECIMAL(10,2),
    tax_rate DECIMAL(5,2) DEFAULT 19.00,
    discount DECIMAL(10,2) DEFAULT 0,
    notes TEXT,
    created_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Order items table
CREATE TABLE IF NOT EXISTS order_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL,
    item_type VARCHAR(20),
    description TEXT NOT NULL,
    quantity DECIMAL(10,2) DEFAULT 1,
    unit_price DECIMAL(10,2),
    total_price DECIMAL(10,2),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- Invoices table
CREATE TABLE IF NOT EXISTS invoices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_number VARCHAR(20) UNIQUE NOT NULL,
    order_id INTEGER,
    customer_id INTEGER NOT NULL,
    status VARCHAR(20) DEFAULT "unpaid",
    due_date DATE,
    paid_date DATE,
    total_amount DECIMAL(10,2),
    tax_amount DECIMAL(10,2),
    payment_method VARCHAR(30),
    notes TEXT,
    created_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Settings table
CREATE TABLE IF NOT EXISTS settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type VARCHAR(20),
    description TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Activity log table
CREATE TABLE IF NOT EXISTS activity_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    action VARCHAR(50),
    entity_type VARCHAR(30),
    entity_id INTEGER,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Vehicle trade table (An- und Verkauf)
CREATE TABLE IF NOT EXISTS vehicle_trades (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    trade_type VARCHAR(10) NOT NULL, -- "purchase" or "sale"
    vehicle_id INTEGER,
    price DECIMAL(10,2),
    trade_date DATE,
    partner_name VARCHAR(100),
    partner_contact VARCHAR(100),
    documents TEXT,
    notes TEXT,
    status VARCHAR(20) DEFAULT "pending",
    created_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_customers_email ON customers(email);
CREATE INDEX IF NOT EXISTS idx_vehicles_license ON vehicles(license_plate);
CREATE INDEX IF NOT EXISTS idx_orders_number ON orders(order_number);
CREATE INDEX IF NOT EXISTS idx_invoices_number ON invoices(invoice_number);
CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);
';
file_put_contents('database/init.sql', $initSQL);
echo "  ✓ Database schema created\n";

// ========================================
// Initialize Database
// ========================================
echo "\n[5/6] Initializing database...\n";
require_once 'includes/logger.php';
require_once 'config/database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Execute the init SQL
    $conn->exec($initSQL);
    echo "  ✓ Database tables created\n";

    // Create admin user
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT OR IGNORE INTO users (username, password, email, first_name, last_name, role) 
                            VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute(['admin', $adminPassword, 'admin@kfz-billing.de', 'Admin', 'User', 'admin']);
    echo "  ✓ Admin user created (Username: admin, Password: admin123)\n";

    // Insert default settings
    $settings = [
        ['company_name', 'KFZ Werkstatt GmbH', 'string', 'Firmenname'],
        ['company_address', 'Musterstraße 1, 12345 Musterstadt', 'string', 'Firmenadresse'],
        ['company_phone', '+49 123 456789', 'string', 'Telefonnummer'],
        ['company_email', 'info@kfz-werkstatt.de', 'string', 'E-Mail Adresse'],
        ['tax_rate', '19', 'number', 'Standard Steuersatz'],
        ['invoice_prefix', 'RE-', 'string', 'Rechnungsnummer Präfix'],
        ['order_prefix', 'AU-', 'string', 'Auftragsnummer Präfix'],
        ['currency', 'EUR', 'string', 'Währung'],
        ['timezone', 'Europe/Berlin', 'string', 'Zeitzone']
    ];

    $stmt = $conn->prepare("INSERT OR IGNORE INTO settings (setting_key, setting_value, setting_type, description) 
                           VALUES (?, ?, ?, ?)");

    foreach ($settings as $setting) {
        $stmt->execute($setting);
    }
    echo "  ✓ Default settings inserted\n";

    // Add some demo data
    echo "\n  Adding demo data...\n";

    // Demo customers
    $customers = [
        ['Müller GmbH', 'Thomas', 'Müller', 'thomas.mueller@mueller-gmbh.de', '+49 123 456789', 'Hauptstraße', '42', '12345', 'Berlin', 'Deutschland', 'DE123456789', 'Stammkunde seit 2020'],
        ['', 'Julia', 'Schmidt', 'julia.schmidt@email.de', '+49 987 654321', 'Gartenweg', '15', '54321', 'Hamburg', 'Deutschland', '', 'Privatkunde'],
        ['Auto Weber', 'Michael', 'Weber', 'info@auto-weber.de', '+49 555 123456', 'Industriestraße', '8', '67890', 'München', 'Deutschland', 'DE987654321', 'Händler']
    ];

    $stmt = $conn->prepare("INSERT INTO customers (company_name, first_name, last_name, email, phone, street, house_number, postal_code, city, country, tax_id, notes, created_by) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");

    foreach ($customers as $customer) {
        $stmt->execute($customer);
    }
    echo "  ✓ Demo customers added\n";

    // Demo vehicles
    $vehicles = [
        [1, 'B-TM-123', 'BMW', '320d', 2020, 'WBA123456789', 'B47D20', 'Schwarz', 45000, 'Diesel', 'Regelmäßige Wartung'],
        [2, 'HH-JS-456', 'VW', 'Golf 8', 2021, 'WVW123456789', 'EA211', 'Silber', 23000, 'Benzin', 'Unfallschaden vorne links'],
        [3, 'M-MW-789', 'Mercedes', 'C200', 2019, 'WDB123456789', 'M264', 'Weiß', 67000, 'Benzin', 'Geschäftsfahrzeug']
    ];

    $stmt = $conn->prepare("INSERT INTO vehicles (customer_id, license_plate, manufacturer, model, year, vin, engine_code, color, mileage, fuel_type, notes) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    foreach ($vehicles as $vehicle) {
        $stmt->execute($vehicle);
    }
    echo "  ✓ Demo vehicles added\n";

    Logger::success("Database setup completed successfully!");
} catch (Exception $e) {
    echo "\n  ✗ Error: " . $e->getMessage() . "\n";
    Logger::error("Setup failed", ['error' => $e->getMessage()]);
    exit(1);
}

// ========================================
// Create additional files
// ========================================
echo "\n[6/6] Creating additional files...\n";

// Create config/config.php
$configContent = '<?php
// General configuration
define("APP_NAME", "KFZ Billing Pro");
define("APP_VERSION", "1.0.0");
define("APP_URL", "http://localhost:8000");
define("APP_PATH", __DIR__ . "/..");

// Session configuration
ini_set("session.cookie_httponly", 1);
ini_set("session.use_only_cookies", 1);
ini_set("session.cookie_secure", 0); // Set to 1 for HTTPS

// Timezone
date_default_timezone_set("Europe/Berlin");

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set("display_errors", 1);

// License Server (optional)
define("LICENSE_SERVER", "https://your-license-server.com/api/verify");
define("LICENSE_KEY", "YOUR_LICENSE_KEY_HERE");
';
file_put_contents('config/config.php', $configContent);
echo "  ✓ Config file created\n";

// Create .htaccess
$htaccessContent = 'RewriteEngine On

# Redirect to HTTPS (uncomment in production)
# RewriteCond %{HTTPS} off
# RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]

# Protect directories
RewriteRule ^(config|database|includes|logs|backup)/ - [F,L]

# Redirect all requests to index.php
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?url=$1 [QSA,L]

# Security headers
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
</IfModule>

# Disable directory browsing
Options -Indexes
';
file_put_contents('.htaccess', $htaccessContent);
echo "  ✓ .htaccess created\n";

// Create index.php
$indexContent = '<?php
require_once "config/config.php";
require_once "includes/logger.php";
require_once "config/database.php";
require_once "includes/auth.php";

// Initialize
session_start();
Auth::init();

// Check if user is logged in
if (!Auth::isLoggedIn() && $_SERVER["REQUEST_URI"] !== "/login.php") {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div id="app">
        <!-- Your HTML from the main artifact goes here -->
        <!-- Include the complete HTML structure -->
    </div>
    
    <script src="assets/js/app.js" type="module"></script>
</body>
</html>
';
file_put_contents('index.php', $indexContent);
echo "  ✓ index.php created\n";

// Create login.php
$loginContent = '<?php
session_start();
require_once "config/config.php";
require_once "includes/logger.php";
require_once "config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = $_POST["username"] ?? "";
    $password = $_POST["password"] ?? "";
    
    if ($username && $password) {
        require_once "includes/auth.php";
        Auth::init();
        
        if (Auth::login($username, $password)) {
            header("Location: index.php");
            exit;
        } else {
            $error = "Ungültige Anmeldedaten";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KFZ Billing Pro - Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --clr-primary-a0: #e6a309;
            --clr-primary-a10: #ebad36;
            --clr-surface-a0: #141414;
            --clr-surface-a10: #292929;
            --clr-surface-a20: #404040;
            --clr-light-a0: #ffffff;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, var(--clr-surface-a0) 0%, var(--clr-surface-a10) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: var(--clr-surface-a10);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            width: 100%;
            max-width: 400px;
            border: 1px solid var(--clr-primary-a0);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header i {
            font-size: 48px;
            color: var(--clr-primary-a0);
            margin-bottom: 16px;
        }
        
        .login-header h1 {
            font-size: 28px;
            color: var(--clr-primary-a10);
            margin-bottom: 8px;
        }
        
        .login-header p {
            color: #8c8c8c;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #8c8c8c;
            font-size: 14px;
        }
        
        .form-input {
            width: 100%;
            padding: 12px 16px;
            background: var(--clr-surface-a20);
            border: 1px solid #585858;
            border-radius: 8px;
            color: var(--clr-light-a0);
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--clr-primary-a0);
            box-shadow: 0 0 0 3px rgba(230, 163, 9, 0.1);
        }
        
        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, var(--clr-primary-a0), var(--clr-primary-a10));
            border: none;
            border-radius: 8px;
            color: #000;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(230, 163, 9, 0.3);
        }
        
        .error-message {
            background: rgba(248, 113, 113, 0.1);
            border: 1px solid #f87171;
            color: #f87171;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .demo-info {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--clr-surface-a20);
            text-align: center;
            color: #8c8c8c;
            font-size: 12px;
        }
        
        .demo-info code {
            background: var(--clr-surface-a20);
            padding: 2px 6px;
            border-radius: 4px;
            color: var(--clr-primary-a10);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <i class="fas fa-car"></i>
            <h1>KFZ Billing Pro</h1>
            <p>Melden Sie sich an, um fortzufahren</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label">Benutzername</label>
                <input type="text" name="username" class="form-input" required autofocus>
            </div>
            
            <div class="form-group">
                <label class="form-label">Passwort</label>
                <input type="password" name="password" class="form-input" required>
            </div>
            
            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Anmelden
            </button>
        </form>
        
        <div class="demo-info">
            Demo-Zugang: <code>admin</code> / <code>admin123</code>
        </div>
    </div>
</body>
</html>
';
file_put_contents('login.php', $loginContent);
echo "  ✓ login.php created\n";

// Create includes/auth.php
$authContent = '<?php
class Auth {
    private static $db;
    
    public static function init() {
        require_once __DIR__ . "/../config/database.php";
        self::$db = Database::getInstance()->getConnection();
    }
    
    public static function login($username, $password) {
        Logger::info("Login attempt for user: {$username}");
        
        $stmt = self::$db->prepare("SELECT * FROM users WHERE username = ? AND active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user["password"])) {
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["role"] = $user["role"];
            $_SESSION["logged_in"] = true;
            
            // Update last login
            $updateStmt = self::$db->prepare("UPDATE users SET last_login = datetime(\'now\') WHERE id = ?");
            $updateStmt->execute([$user["id"]]);
            
            Logger::success("User logged in successfully", ["user_id" => $user["id"]]);
            return true;
        }
        
        Logger::warning("Failed login attempt for user: {$username}");
        return false;
    }
    
    public static function logout() {
        $user_id = $_SESSION["user_id"] ?? null;
        session_destroy();
        Logger::info("User logged out", ["user_id" => $user_id]);
        return true;
    }
    
    public static function isLoggedIn() {
        return isset($_SESSION["logged_in"]) && $_SESSION["logged_in"] === true;
    }
    
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header("Location: /login.php");
            exit;
        }
    }
    
    public static function hasRole($role) {
        return isset($_SESSION["role"]) && $_SESSION["role"] === $role;
    }
    
    public static function isAdmin() {
        return self::hasRole("admin");
    }
}
';
file_put_contents('includes/auth.php', $authContent);
echo "  ✓ auth.php created\n";

echo "\n=====================================\n";
echo "✅ SETUP COMPLETED SUCCESSFULLY!\n";
echo "=====================================\n\n";
echo "📋 Summary:\n";
echo "  • All directories created\n";
echo "  • Database initialized\n";
echo "  • Admin user created\n";
echo "  • Demo data added\n";
echo "  • All necessary files created\n\n";
echo "🚀 Next Steps:\n";
echo "  1. Start the PHP server:\n";
echo "     php -S localhost:8000\n\n";
echo "  2. Open your browser:\n";
echo "     http://localhost:8000\n\n";
echo "  3. Login with:\n";
echo "     Username: admin\n";
echo "     Password: admin123\n\n";
echo "📁 Project Structure:\n";
echo "  • Database: database/kfz_billing.db\n";
echo "  • Logs: logs/app.log\n";
echo "  • Config: config/config.php\n\n";
echo "💡 Tips:\n";
echo "  • Check logs/app.log for debugging\n";
echo "  • Customize settings in config/config.php\n";
echo "  • Add your license key for production\n";
