<?php

/**
 * KFZ Fac Pro - Auth Include
 * Diese Datei wird von allen geschützten Seiten/APIs eingebunden
 */

// Session starten falls noch nicht gestartet
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auth-Klasse laden falls noch nicht geladen
if (!class_exists('Auth')) {

    class Auth
    {
        private static $db;
        private static $sessionTimeout = 86400; // 24 Stunden
        private static $initialized = false;

        /**
         * Initialisierung
         */
        public static function init()
        {
            if (self::$initialized) {
                return;
            }

            require_once dirname(__DIR__) . '/config/database.php';
            self::$db = Database::getInstance()->getConnection();

            // Session-Timeout aus Einstellungen laden
            try {
                $stmt = self::$db->prepare("SELECT value FROM einstellungen WHERE key = 'session_timeout'");
                $stmt->execute();
                $result = $stmt->fetch();
                if ($result) {
                    self::$sessionTimeout = intval($result['value']);
                }
            } catch (Exception $e) {
                // Fallback auf Standard
            }

            self::$initialized = true;
        }

        /**
         * Login
         */
        public static function login($username, $password)
        {
            self::init();

            try {
                // Benutzer suchen
                $stmt = self::$db->prepare("
                    SELECT id, username, password_hash, role, is_active 
                    FROM users 
                    WHERE username = ? AND is_active = 1
                ");
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if (!$user) {
                    return ['success' => false, 'error' => 'Benutzername oder Passwort falsch'];
                }

                // Passwort prüfen
                if (!password_verify($password, $user['password_hash'])) {
                    return ['success' => false, 'error' => 'Benutzername oder Passwort falsch'];
                }

                // Session setzen
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['logged_in'] = true;
                $_SESSION['login_time'] = time();
                $_SESSION['last_activity'] = time();

                // Session-ID regenerieren für Sicherheit
                session_regenerate_id(true);

                // Last Login aktualisieren
                $stmt = self::$db->prepare("
                    UPDATE users 
                    SET last_login_at = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $stmt->execute([$user['id']]);

                return [
                    'success' => true,
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'role' => $user['role']
                    ]
                ];
            } catch (PDOException $e) {
                error_log("Login-Fehler: " . $e->getMessage());
                return ['success' => false, 'error' => 'Datenbankfehler'];
            }
        }

        /**
         * Logout
         */
        public static function logout()
        {
            $_SESSION = [];

            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params["path"],
                    $params["domain"],
                    $params["secure"],
                    $params["httponly"]
                );
            }

            session_destroy();
            return ['success' => true];
        }

        /**
         * Prüft ob Benutzer eingeloggt ist
         */
        public static function check()
        {
            self::init();

            if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
                return false;
            }

            // Session-Timeout prüfen
            if (isset($_SESSION['last_activity'])) {
                $inactive = time() - $_SESSION['last_activity'];
                if ($inactive > self::$sessionTimeout) {
                    self::logout();
                    return false;
                }
            }

            $_SESSION['last_activity'] = time();
            return true;
        }

        /**
         * Aktuellen Benutzer abrufen
         */
        public static function getCurrentUser()
        {
            if (!self::check()) {
                return null;
            }

            return [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'role' => $_SESSION['role']
            ];
        }

        /**
         * Prüft Admin-Rechte
         */
        public static function isAdmin()
        {
            return self::check() && $_SESSION['role'] === 'admin';
        }

        /**
         * Middleware für geschützte Routen
         */
        public static function requireAuth()
        {
            if (!self::check()) {
                header('Content-Type: application/json');
                http_response_code(401);
                echo json_encode(['error' => 'Nicht authentifiziert']);
                exit;
            }
        }

        /**
         * Middleware für Admin-Routen
         */
        public static function requireAdmin()
        {
            self::requireAuth();

            if (!self::isAdmin()) {
                header('Content-Type: application/json');
                http_response_code(403);
                echo json_encode(['error' => 'Keine Admin-Berechtigung']);
                exit;
            }
        }

        /**
         * Passwort ändern
         */
        public static function changePassword($userId, $oldPassword, $newPassword)
        {
            self::init();

            try {
                // Altes Passwort prüfen
                $stmt = self::$db->prepare("SELECT password_hash FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();

                if (!$user || !password_verify($oldPassword, $user['password_hash'])) {
                    return ['success' => false, 'error' => 'Altes Passwort falsch'];
                }

                // Neues Passwort setzen
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = self::$db->prepare("
                    UPDATE users 
                    SET password_hash = ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $stmt->execute([$newHash, $userId]);

                return ['success' => true];
            } catch (PDOException $e) {
                error_log("Passwort ändern Fehler: " . $e->getMessage());
                return ['success' => false, 'error' => 'Datenbankfehler'];
            }
        }

        /**
         * Benutzer erstellen (nur Admin)
         */
        public static function createUser($username, $password, $role = 'user')
        {
            self::init();

            if (!self::isAdmin()) {
                return ['success' => false, 'error' => 'Keine Berechtigung'];
            }

            try {
                // Prüfen ob Username existiert
                $stmt = self::$db->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    return ['success' => false, 'error' => 'Benutzername existiert bereits'];
                }

                // Benutzer erstellen
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = self::$db->prepare("
                    INSERT INTO users (username, password_hash, role, created_at, updated_at, is_active) 
                    VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 1)
                ");
                $stmt->execute([$username, $passwordHash, $role]);

                return [
                    'success' => true,
                    'id' => self::$db->lastInsertId()
                ];
            } catch (PDOException $e) {
                error_log("Benutzer erstellen Fehler: " . $e->getMessage());
                return ['success' => false, 'error' => 'Datenbankfehler'];
            }
        }
    }
}

// Auth initialisieren
Auth::init();
