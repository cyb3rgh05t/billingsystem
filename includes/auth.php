<?php

/**
 * Authentication Class
 * Handles user login, logout, and session management
 */

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Session Cookie Einstellungen VOR session_start()
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'secure' => isset($_SERVER['HTTPS']),
        'samesite' => 'Strict'
    ]);

    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/logger.class.php';

class Auth
{
    private $db;
    private $logger;
    private $sessionTimeout = 3600; // 1 Stunde

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = new Logger();

        // Session Security
        $this->initializeSession();
    }

    /**
     * Session Security initialisieren
     */
    private function initializeSession()
    {
        // Session ID regenerieren für Sicherheit (nur wenn noch nicht initialisiert)
        if (!isset($_SESSION['initialized'])) {
            session_regenerate_id(true);
            $_SESSION['initialized'] = true;
        }
    }

    /**
     * User Login
     */
    public function login($username, $password)
    {
        // Validate input
        if (empty($username) || empty($password)) {
            $this->logger->warning("Login attempt with empty credentials");
            return ['success' => false, 'message' => 'Bitte alle Felder ausfüllen'];
        }

        // Get user from database
        $user = $this->db->selectOne(
            "SELECT * FROM users WHERE username = :username AND is_active = 1",
            [':username' => $username]
        );

        // Check password
        if ($user && password_verify($password, $user['password'])) {
            // Successful login
            $this->createSession($user);

            // Update last login
            $this->db->update(
                'users',
                ['last_login' => date('Y-m-d H:i:s')],
                'id = :id',
                [':id' => $user['id']]
            );

            // Log successful login
            $this->logger->success(
                "User '{$username}' logged in successfully",
                $user['id'],
                'AUTH'
            );

            return ['success' => true, 'message' => 'Login erfolgreich'];
        }

        // Log failed login
        $this->logger->error(
            "Failed login attempt for user '{$username}'",
            null,
            'AUTH'
        );

        return ['success' => false, 'message' => 'Ungültiger Benutzername oder Passwort'];
    }

    /**
     * Create user session
     */
    private function createSession($user)
    {
        // Regenerate session ID for security
        session_regenerate_id(true);

        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();

        // Create session entry in database
        $sessionId = session_id();
        $this->db->insert('sessions', [
            'session_id' => $sessionId,
            'user_id' => $user['id'],
            'ip_address' => $this->getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
    }

    /**
     * User Logout
     */
    public function logout()
    {
        $userId = $_SESSION['user_id'] ?? null;
        $username = $_SESSION['username'] ?? 'Unknown';

        // Remove session from database
        if ($userId) {
            $this->db->delete(
                'sessions',
                'session_id = :session_id',
                [':session_id' => session_id()]
            );
        }

        // Log logout
        $this->logger->info(
            "User '{$username}' logged out",
            $userId,
            'AUTH'
        );

        // Clear session
        $_SESSION = [];

        // Destroy session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }

        // Destroy session
        session_destroy();

        return true;
    }

    /**
     * Check if user is logged in
     */
    public function isLoggedIn()
    {
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            return false;
        }

        // Check session timeout
        if ($this->isSessionExpired()) {
            $this->logout();
            return false;
        }

        // Update last activity
        $_SESSION['last_activity'] = time();

        // Update session in database
        $this->db->update(
            'sessions',
            ['last_activity' => date('Y-m-d H:i:s')],
            'session_id = :session_id',
            [':session_id' => session_id()]
        );

        return true;
    }

    /**
     * Check if session is expired
     */
    private function isSessionExpired()
    {
        if (isset($_SESSION['last_activity'])) {
            $inactive = time() - $_SESSION['last_activity'];
            if ($inactive > $this->sessionTimeout) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if user is admin
     */
    public function isAdmin()
    {
        return $this->isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }

    /**
     * Check if user has specific role
     */
    public function hasRole($role)
    {
        return $this->isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === $role;
    }

    /**
     * Require login (redirect if not logged in)
     */
    public function requireLogin()
    {
        if (!$this->isLoggedIn()) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            header('Location: /login.php');
            exit;
        }
    }

    /**
     * Require admin role
     */
    public function requireAdmin()
    {
        $this->requireLogin();

        if (!$this->isAdmin()) {
            $this->logger->warning(
                "Unauthorized admin access attempt",
                $_SESSION['user_id'] ?? null,
                'AUTH'
            );

            header('HTTP/1.0 403 Forbidden');
            die('
                <div style="text-align: center; padding: 50px; font-family: Arial;">
                    <h1 style="color: #dc3545;">403 - Zugriff verweigert</h1>
                    <p>Sie benötigen Administrator-Rechte für diese Seite.</p>
                    <a href="/dashboard.php">Zurück zum Dashboard</a>
                </div>
            ');
        }
    }

    /**
     * Get current user info
     */
    public function getCurrentUser()
    {
        if (!$this->isLoggedIn()) {
            return null;
        }

        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'email' => $_SESSION['email'],
            'full_name' => $_SESSION['full_name'],
            'role' => $_SESSION['role']
        ];
    }

    /**
     * Get current user ID
     */
    public function getUserId()
    {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Register new user
     */
    public function register($data)
    {
        // Validate required fields
        $required = ['username', 'password', 'email', 'full_name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'message' => "Feld '{$field}' ist erforderlich"];
            }
        }

        // Check if username exists
        $existingUser = $this->db->selectOne(
            "SELECT id FROM users WHERE username = :username",
            [':username' => $data['username']]
        );

        if ($existingUser) {
            return ['success' => false, 'message' => 'Benutzername bereits vergeben'];
        }

        // Check if email exists
        $existingEmail = $this->db->selectOne(
            "SELECT id FROM users WHERE email = :email",
            [':email' => $data['email']]
        );

        if ($existingEmail) {
            return ['success' => false, 'message' => 'E-Mail bereits registriert'];
        }

        // Hash password
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

        // Insert user
        $userId = $this->db->insert('users', [
            'username' => $data['username'],
            'password' => $hashedPassword,
            'email' => $data['email'],
            'full_name' => $data['full_name'],
            'role' => $data['role'] ?? 'user',
            'is_active' => 1
        ]);

        if ($userId) {
            $this->logger->success(
                "New user registered: {$data['username']}",
                $userId,
                'AUTH'
            );

            return ['success' => true, 'message' => 'Benutzer erfolgreich erstellt', 'user_id' => $userId];
        }

        return ['success' => false, 'message' => 'Fehler beim Erstellen des Benutzers'];
    }

    /**
     * Change user password
     */
    public function changePassword($userId, $oldPassword, $newPassword)
    {
        // Get user
        $user = $this->db->selectOne(
            "SELECT password FROM users WHERE id = :id",
            [':id' => $userId]
        );

        if (!$user) {
            return ['success' => false, 'message' => 'Benutzer nicht gefunden'];
        }

        // Verify old password
        if (!password_verify($oldPassword, $user['password'])) {
            return ['success' => false, 'message' => 'Altes Passwort ist falsch'];
        }

        // Hash new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        // Update password
        $result = $this->db->update(
            'users',
            ['password' => $hashedPassword, 'updated_at' => date('Y-m-d H:i:s')],
            'id = :id',
            [':id' => $userId]
        );

        if ($result) {
            $this->logger->info(
                "Password changed for user ID: {$userId}",
                $userId,
                'AUTH'
            );

            return ['success' => true, 'message' => 'Passwort erfolgreich geändert'];
        }

        return ['success' => false, 'message' => 'Fehler beim Ändern des Passworts'];
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

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Get active sessions for user
     */
    public function getUserSessions($userId)
    {
        return $this->db->select(
            "SELECT * FROM sessions WHERE user_id = :user_id ORDER BY last_activity DESC",
            [':user_id' => $userId]
        );
    }

    /**
     * Terminate specific session
     */
    public function terminateSession($sessionId)
    {
        return $this->db->delete(
            'sessions',
            'session_id = :session_id',
            [':session_id' => $sessionId]
        );
    }

    /**
     * Clean up old sessions
     */
    public function cleanupSessions()
    {
        $expiredTime = date('Y-m-d H:i:s', time() - $this->sessionTimeout);

        $deleted = $this->db->delete(
            'sessions',
            'last_activity < :expired',
            [':expired' => $expiredTime]
        );

        if ($deleted) {
            $this->logger->info(
                "Cleaned up expired sessions",
                null,
                'AUTH'
            );
        }
    }
}

// Create global auth instance
$auth = new Auth();
