<?php

/**
 * KFZ Fac Pro - Authentifizierungssystem
 * Session-basierte Authentifizierung wie im Original
 */

session_start();

class Auth
{
    private static $instance = null;
    private $db;
    private $sessionTimeout = 86400; // 24 Stunden

    private function __construct()
    {
        require_once dirname(__FILE__) . '/database.php';
        $this->db = Database::getInstance()->getConnection();
        $this->initSessionConfig();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initSessionConfig()
    {
        // Session-Konfiguration
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_samesite', 'Strict');

        // Session-Timeout prüfen
        if (
            isset($_SESSION['last_activity']) &&
            (time() - $_SESSION['last_activity'] > $this->sessionTimeout)
        ) {
            $this->logout();
        }
        $_SESSION['last_activity'] = time();
    }

    /**
     * Login-Funktion
     */
    public function login($username, $password)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT id, username, password_hash, role, is_active 
                FROM users 
                WHERE username = ? AND is_active = 1
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Session-Daten setzen
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['logged_in'] = true;
                $_SESSION['login_time'] = time();
                $_SESSION['last_activity'] = time();

                // Login-Zeit aktualisieren
                $updateStmt = $this->db->prepare("
                    UPDATE users 
                    SET last_login_at = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $updateStmt->execute([$user['id']]);

                return [
                    'success' => true,
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'role' => $user['role']
                    ]
                ];
            }

            return [
                'success' => false,
                'error' => 'Ungültige Anmeldedaten'
            ];
        } catch (PDOException $e) {
            error_log("Login-Fehler: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Anmeldefehler aufgetreten'
            ];
        }
    }

    /**
     * Logout-Funktion
     */
    public function logout()
    {
        $_SESSION = array();

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
    public function isLoggedIn()
    {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    /**
     * Gibt aktuellen Benutzer zurück
     */
    public function getCurrentUser()
    {
        if (!$this->isLoggedIn()) {
            return null;
        }

        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'role' => $_SESSION['role']
        ];
    }

    /**
     * Middleware: Erfordert Login
     */
    public function requireLogin()
    {
        if (!$this->isLoggedIn()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Nicht autorisiert']);
            exit;
        }
    }

    /**
     * Middleware: Erfordert Admin-Rolle
     */
    public function requireAdmin()
    {
        $this->requireLogin();

        if ($_SESSION['role'] !== 'admin') {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Keine Berechtigung']);
            exit;
        }
    }

    /**
     * Passwort ändern
     */
    public function changePassword($userId, $oldPassword, $newPassword)
    {
        try {
            // Altes Passwort prüfen
            $stmt = $this->db->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($oldPassword, $user['password_hash'])) {
                return ['success' => false, 'error' => 'Altes Passwort ist falsch'];
            }

            // Neues Passwort setzen
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = $this->db->prepare("
                UPDATE users 
                SET password_hash = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $updateStmt->execute([$newHash, $userId]);

            return ['success' => true];
        } catch (PDOException $e) {
            error_log("Passwort-Änderung fehlgeschlagen: " . $e->getMessage());
            return ['success' => false, 'error' => 'Fehler beim Ändern des Passworts'];
        }
    }

    /**
     * Benutzer erstellen (Admin-Funktion)
     */
    public function createUser($username, $password, $role = 'user')
    {
        try {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $this->db->prepare("
                INSERT INTO users (username, password_hash, role, created_at, is_active) 
                VALUES (?, ?, ?, CURRENT_TIMESTAMP, 1)
            ");
            $stmt->execute([$username, $passwordHash, $role]);

            return [
                'success' => true,
                'user_id' => $this->db->lastInsertId()
            ];
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'UNIQUE') !== false) {
                return ['success' => false, 'error' => 'Benutzername bereits vergeben'];
            }
            error_log("Benutzer-Erstellung fehlgeschlagen: " . $e->getMessage());
            return ['success' => false, 'error' => 'Fehler beim Erstellen des Benutzers'];
        }
    }
}

// Globale Auth-Instanz
$auth = Auth::getInstance();
