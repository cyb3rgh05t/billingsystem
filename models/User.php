<?php

/**
 * KFZ Fac Pro - User Model
 */

require_once 'Model.php';

class User extends Model
{
    protected $table = 'users';
    protected $fillable = [
        'username',
        'password_hash',
        'role',
        'email',
        'vorname',
        'nachname',
        'telefon',
        'abteilung',
        'position',
        'last_login_at',
        'is_active',
        'einstellungen',
        'avatar',
        'sprache',
        'theme',
        'dashboard_layout',
        'benachrichtigungen',
        'zwei_faktor_auth',
        'api_token',
        'password_reset_token',
        'password_reset_expires'
    ];

    /**
     * Konstruktor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Benutzer nach Username finden
     */
    public function findByUsername($username)
    {
        $sql = "SELECT * FROM {$this->table} WHERE username = ?";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$username]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("findByUsername Fehler: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Benutzer nach E-Mail finden
     */
    public function findByEmail($email)
    {
        $sql = "SELECT * FROM {$this->table} WHERE email = ?";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$email]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("findByEmail Fehler: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Aktive Benutzer abrufen
     */
    public function getActive()
    {
        $sql = "SELECT id, username, role, vorname, nachname, email, last_login_at 
                FROM {$this->table} 
                WHERE is_active = 1 
                ORDER BY username";

        try {
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getActive Fehler: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Login validieren
     */
    public function validateLogin($username, $password)
    {
        try {
            // Benutzer suchen
            $user = $this->findByUsername($username);

            if (!$user) {
                return ['success' => false, 'error' => 'Benutzername oder Passwort falsch'];
            }

            // Aktiv prüfen
            if (!$user['is_active']) {
                return ['success' => false, 'error' => 'Benutzer ist deaktiviert'];
            }

            // Passwort prüfen
            if (!password_verify($password, $user['password_hash'])) {
                return ['success' => false, 'error' => 'Benutzername oder Passwort falsch'];
            }

            // Last Login aktualisieren
            $this->update($user['id'], ['last_login_at' => date('Y-m-d H:i:s')]);

            // Sensible Daten entfernen
            unset($user['password_hash']);
            unset($user['password_reset_token']);
            unset($user['api_token']);

            return [
                'success' => true,
                'user' => $user
            ];
        } catch (PDOException $e) {
            error_log("validateLogin Fehler: " . $e->getMessage());
            return ['success' => false, 'error' => 'Datenbankfehler'];
        }
    }

    /**
     * Passwort ändern
     */
    public function changePassword($userId, $oldPassword, $newPassword)
    {
        try {
            // Benutzer laden
            $user = $this->findById($userId);
            if (!$user) {
                return ['success' => false, 'error' => 'Benutzer nicht gefunden'];
            }

            // Altes Passwort prüfen
            if (!password_verify($oldPassword, $user['password_hash'])) {
                return ['success' => false, 'error' => 'Altes Passwort falsch'];
            }

            // Neues Passwort hashen und speichern
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            return $this->update($userId, ['password_hash' => $newHash]);
        } catch (PDOException $e) {
            error_log("changePassword Fehler: " . $e->getMessage());
            return ['success' => false, 'error' => 'Datenbankfehler'];
        }
    }

    /**
     * Passwort zurücksetzen Token generieren
     */
    public function generatePasswordResetToken($email)
    {
        try {
            // Benutzer nach E-Mail suchen
            $user = $this->findByEmail($email);
            if (!$user) {
                return ['success' => false, 'error' => 'E-Mail-Adresse nicht gefunden'];
            }

            // Token generieren
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Token speichern
            $this->update($user['id'], [
                'password_reset_token' => $token,
                'password_reset_expires' => $expires
            ]);

            return [
                'success' => true,
                'token' => $token,
                'user' => $user
            ];
        } catch (Exception $e) {
            error_log("generatePasswordResetToken Fehler: " . $e->getMessage());
            return ['success' => false, 'error' => 'Fehler beim Generieren des Tokens'];
        }
    }

    /**
     * Passwort mit Token zurücksetzen
     */
    public function resetPasswordWithToken($token, $newPassword)
    {
        try {
            // Benutzer mit Token suchen
            $sql = "SELECT * FROM {$this->table} 
                    WHERE password_reset_token = ? 
                    AND password_reset_expires > NOW()";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$token]);
            $user = $stmt->fetch();

            if (!$user) {
                return ['success' => false, 'error' => 'Ungültiger oder abgelaufener Token'];
            }

            // Neues Passwort setzen und Token löschen
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            return $this->update($user['id'], [
                'password_hash' => $newHash,
                'password_reset_token' => null,
                'password_reset_expires' => null
            ]);
        } catch (PDOException $e) {
            error_log("resetPasswordWithToken Fehler: " . $e->getMessage());
            return ['success' => false, 'error' => 'Datenbankfehler'];
        }
    }

    /**
     * Validierung
     */
    public function validate($data)
    {
        $errors = [];

        // Username
        if (empty($data['username'])) {
            $errors[] = 'Benutzername ist erforderlich';
        } elseif (strlen($data['username']) < 3) {
            $errors[] = 'Benutzername muss mindestens 3 Zeichen lang sein';
        }

        // Passwort (nur bei Erstellung)
        if (isset($data['password'])) {
            if (strlen($data['password']) < 6) {
                $errors[] = 'Passwort muss mindestens 6 Zeichen lang sein';
            }
        }

        // E-Mail
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Ungültige E-Mail-Adresse';
        }

        // Role
        $validRoles = ['admin', 'user', 'viewer'];
        if (!empty($data['role']) && !in_array($data['role'], $validRoles)) {
            $errors[] = 'Ungültige Rolle';
        }

        return $errors;
    }

    /**
     * Benutzer erstellen
     */
    public function create($data)
    {
        // Validierung
        $errors = $this->validate($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Prüfen ob Username bereits existiert
        if ($this->findByUsername($data['username'])) {
            return ['success' => false, 'error' => 'Benutzername existiert bereits'];
        }

        // Passwort hashen
        if (isset($data['password'])) {
            $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            unset($data['password']);
        }

        // Standard-Werte
        if (empty($data['role'])) {
            $data['role'] = 'user';
        }
        if (!isset($data['is_active'])) {
            $data['is_active'] = 1;
        }

        // Parent create aufrufen
        return parent::create($data);
    }

    /**
     * Benutzer aktualisieren
     */
    public function update($id, $data)
    {
        // Validierung
        $errors = $this->validate($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Wenn Username geändert wird, prüfen ob er bereits existiert
        if (!empty($data['username'])) {
            $existing = $this->findByUsername($data['username']);
            if ($existing && $existing['id'] != $id) {
                return ['success' => false, 'error' => 'Benutzername existiert bereits'];
            }
        }

        // Passwort hashen falls gesetzt
        if (isset($data['password'])) {
            $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            unset($data['password']);
        }

        // Parent update aufrufen
        return parent::update($id, $data);
    }

    /**
     * Benutzer aktivieren/deaktivieren
     */
    public function setActive($id, $active)
    {
        return $this->update($id, ['is_active' => $active ? 1 : 0]);
    }

    /**
     * API Token generieren
     */
    public function generateApiToken($userId)
    {
        try {
            $token = bin2hex(random_bytes(32));
            $this->update($userId, ['api_token' => $token]);
            return $token;
        } catch (Exception $e) {
            error_log("generateApiToken Fehler: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Benutzer-Einstellungen speichern
     */
    public function saveSettings($userId, $settings)
    {
        $json = json_encode($settings);
        return $this->update($userId, ['einstellungen' => $json]);
    }

    /**
     * Benutzer-Einstellungen laden
     */
    public function getSettings($userId)
    {
        $user = $this->findById($userId);
        if ($user && !empty($user['einstellungen'])) {
            return json_decode($user['einstellungen'], true);
        }
        return [];
    }

    /**
     * Avatar speichern (Base64)
     */
    public function saveAvatar($userId, $base64Data)
    {
        // Validierung
        if (!preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
            return ['success' => false, 'error' => 'Ungültiges Bildformat'];
        }

        // Größenbeschränkung (2MB)
        $sizeInBytes = strlen(base64_decode(str_replace($type[0], '', $base64Data)));
        if ($sizeInBytes > 2 * 1024 * 1024) {
            return ['success' => false, 'error' => 'Bild zu groß (max. 2MB)'];
        }

        return $this->update($userId, ['avatar' => $base64Data]);
    }

    /**
     * Kann gelöscht werden?
     */
    public function canDelete($id)
    {
        // Letzten Admin nicht löschen
        $user = $this->findById($id);
        if ($user && $user['role'] === 'admin') {
            $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE role = 'admin' AND is_active = 1";
            $stmt = $this->db->query($sql);
            $result = $stmt->fetch();

            if ($result['count'] <= 1) {
                return ['success' => false, 'error' => 'Der letzte Admin kann nicht gelöscht werden'];
            }
        }

        return ['success' => true];
    }

    /**
     * Löschen mit Prüfung
     */
    public function delete($id)
    {
        $canDelete = $this->canDelete($id);
        if (!$canDelete['success']) {
            return $canDelete;
        }

        return parent::delete($id);
    }

    /**
     * Statistiken
     */
    public function getStatistiken()
    {
        try {
            $stats = [];

            // Gesamt
            $sql = "SELECT 
                        COUNT(*) as gesamt,
                        COUNT(CASE WHEN is_active = 1 THEN 1 END) as aktiv,
                        COUNT(CASE WHEN role = 'admin' THEN 1 END) as admins,
                        COUNT(CASE WHEN role = 'user' THEN 1 END) as users,
                        COUNT(CASE WHEN role = 'viewer' THEN 1 END) as viewers
                    FROM {$this->table}";

            $stmt = $this->db->query($sql);
            $stats = $stmt->fetch();

            // Letzte Logins
            $sql = "SELECT username, last_login_at 
                    FROM {$this->table} 
                    WHERE last_login_at IS NOT NULL 
                    ORDER BY last_login_at DESC 
                    LIMIT 5";

            $stmt = $this->db->query($sql);
            $stats['letzte_logins'] = $stmt->fetchAll();

            return $stats;
        } catch (PDOException $e) {
            error_log("getStatistiken Fehler: " . $e->getMessage());
            return [];
        }
    }
}
