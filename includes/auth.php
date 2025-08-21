<?php
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
            $updateStmt = self::$db->prepare("UPDATE users SET last_login = datetime('now') WHERE id = ?");
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
