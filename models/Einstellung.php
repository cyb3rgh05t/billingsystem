<?php

/**
 * KFZ Fac Pro - Einstellung Model
 */

require_once 'Model.php';

class Einstellung extends Model
{
    protected $table = 'einstellungen';
    protected $primaryKey = 'key';
    protected $timestamps = false;

    // Standard-Einstellungen
    private $defaults = [
        // Firma
        'firmen_name' => 'KFZ Werkstatt GmbH',
        'firmen_strasse' => 'Musterstraße 1',
        'firmen_plz' => '12345',
        'firmen_ort' => 'Musterstadt',
        'firmen_telefon' => '0123/456789',
        'firmen_email' => 'info@kfz-werkstatt.de',
        'firmen_website' => 'www.kfz-werkstatt.de',
        'firmen_logo' => '',

        // Bank
        'bank_name' => 'Musterbank',
        'bank_iban' => 'DE12 3456 7890 1234 5678 90',
        'bank_bic' => 'DEUTDEFF',

        // Steuer
        'steuernummer' => '123/456/78901',
        'ustid' => 'DE123456789',
        'mwst_satz' => '19',

        // Preise
        'basis_stundenpreis' => '110',
        'anfahrt_kosten' => '25',
        'express_aufschlag' => '50',
        'wochenend_aufschlag' => '75',

        // Dokumente
        'rechnung_prefix' => 'R',
        'auftrag_prefix' => 'A',
        'kunde_prefix' => 'K',
        'zahlungsziel_tage' => '14',

        // E-Mail
        'email_smtp_host' => '',
        'email_smtp_port' => '587',
        'email_smtp_user' => '',
        'email_smtp_password' => '',
        'email_smtp_secure' => 'tls',
        'email_from_name' => 'KFZ Werkstatt',
        'email_from_address' => 'info@kfz-werkstatt.de',

        // System
        'backup_auto' => '1',
        'backup_interval' => 'daily',
        'backup_keep_days' => '30',
        'session_timeout' => '86400',
        'wartungsmodus' => '0',

        // Layout
        'layout_color_primary' => '#667eea',
        'layout_color_secondary' => '#764ba2',
        'layout_sidebar_collapsed' => '0',
        'layout_dark_mode' => '0',
        'layout_font_size_normal' => '14px',
        'layout_font_size_small' => '12px',
        'layout_font_size_large' => '16px'
    ];

    /**
     * Alle Einstellungen abrufen
     */
    public function getAll()
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY key";

        try {
            $stmt = $this->db->query($sql);
            $results = $stmt->fetchAll();

            // In Key-Value-Array umwandeln
            $settings = [];
            foreach ($results as $row) {
                $settings[$row['key']] = $row['value'];
            }

            // Defaults hinzufügen falls nicht vorhanden
            foreach ($this->defaults as $key => $defaultValue) {
                if (!isset($settings[$key])) {
                    $settings[$key] = $defaultValue;
                }
            }

            return $settings;
        } catch (PDOException $e) {
            error_log("getAll Fehler: " . $e->getMessage());
            return $this->defaults;
        }
    }

    /**
     * Einzelne Einstellung abrufen
     */
    public function get($key, $default = null)
    {
        $sql = "SELECT value FROM {$this->table} WHERE key = ?";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$key]);
            $result = $stmt->fetch();

            if ($result) {
                return $result['value'];
            }

            // Default zurückgeben
            return $default !== null ? $default : ($this->defaults[$key] ?? null);
        } catch (PDOException $e) {
            error_log("get Fehler: " . $e->getMessage());
            return $default;
        }
    }

    /**
     * Einstellung speichern (Insert oder Update)
     */
    public function set($key, $value, $beschreibung = null)
    {
        // Prüfen ob Key existiert
        $existing = $this->get($key);

        if ($existing !== null) {
            // Update
            $sql = "UPDATE {$this->table} 
                    SET value = ?, aktualisiert_am = CURRENT_TIMESTAMP 
                    WHERE key = ?";
            $params = [$value, $key];
        } else {
            // Insert
            $sql = "INSERT INTO {$this->table} (key, value, beschreibung, aktualisiert_am) 
                    VALUES (?, ?, ?, CURRENT_TIMESTAMP)";
            $params = [$key, $value, $beschreibung];
        }

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return ['success' => true];
        } catch (PDOException $e) {
            error_log("set Fehler: " . $e->getMessage());
            return ['success' => false, 'error' => 'Fehler beim Speichern'];
        }
    }

    /**
     * Mehrere Einstellungen auf einmal speichern
     */
    public function setBulk($settings)
    {
        try {
            $this->db->beginTransaction();

            foreach ($settings as $key => $value) {
                $this->set($key, $value);
            }

            $this->db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("setBulk Fehler: " . $e->getMessage());
            return ['success' => false, 'error' => 'Fehler beim Speichern'];
        }
    }

    /**
     * Einstellung löschen
     */
    public function remove($key)
    {
        $sql = "DELETE FROM {$this->table} WHERE key = ?";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$key]);
            return ['success' => true];
        } catch (PDOException $e) {
            error_log("remove Fehler: " . $e->getMessage());
            return ['success' => false, 'error' => 'Fehler beim Löschen'];
        }
    }

    /**
     * Auf Standardwerte zurücksetzen
     */
    public function reset($keys = null)
    {
        try {
            $this->db->beginTransaction();

            if ($keys === null) {
                // Alle zurücksetzen
                $this->db->exec("DELETE FROM {$this->table}");

                // Defaults einfügen
                foreach ($this->defaults as $key => $value) {
                    $this->set($key, $value);
                }
            } else {
                // Nur bestimmte Keys zurücksetzen
                foreach ($keys as $key) {
                    if (isset($this->defaults[$key])) {
                        $this->set($key, $this->defaults[$key]);
                    }
                }
            }

            $this->db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("reset Fehler: " . $e->getMessage());
            return ['success' => false, 'error' => 'Fehler beim Zurücksetzen'];
        }
    }

    /**
     * Firmenlogo speichern (Base64)
     */
    public function saveLogo($base64Data)
    {
        // Validierung
        if (!preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
            return ['success' => false, 'error' => 'Ungültiges Bildformat'];
        }

        // Größenbeschränkung (5MB)
        $sizeInBytes = strlen(base64_decode(str_replace($type[0], '', $base64Data)));
        if ($sizeInBytes > 5 * 1024 * 1024) {
            return ['success' => false, 'error' => 'Bild zu groß (max. 5MB)'];
        }

        return $this->set('firmen_logo', $base64Data);
    }

    /**
     * Export aller Einstellungen
     */
    public function export()
    {
        $settings = $this->getAll();

        return [
            'version' => '2.0',
            'exported_at' => date('Y-m-d H:i:s'),
            'settings' => $settings
        ];
    }

    /**
     * Import von Einstellungen
     */
    public function import($data)
    {
        if (!isset($data['settings']) || !is_array($data['settings'])) {
            return ['success' => false, 'error' => 'Ungültiges Import-Format'];
        }

        try {
            $this->db->beginTransaction();

            foreach ($data['settings'] as $key => $value) {
                // Nur bekannte Keys importieren
                if (array_key_exists($key, $this->defaults)) {
                    $this->set($key, $value);
                }
            }

            $this->db->commit();
            return ['success' => true, 'imported' => count($data['settings'])];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("import Fehler: " . $e->getMessage());
            return ['success' => false, 'error' => 'Import fehlgeschlagen'];
        }
    }

    /**
     * Backup-Einstellungen abrufen
     */
    public function getBackupSettings()
    {
        return [
            'enabled' => $this->get('backup_auto', '1') === '1',
            'interval' => $this->get('backup_interval', 'daily'),
            'keep_days' => intval($this->get('backup_keep_days', '30'))
        ];
    }

    /**
     * E-Mail-Einstellungen abrufen
     */
    public function getEmailSettings()
    {
        return [
            'smtp_host' => $this->get('email_smtp_host'),
            'smtp_port' => $this->get('email_smtp_port', '587'),
            'smtp_user' => $this->get('email_smtp_user'),
            'smtp_password' => $this->get('email_smtp_password'),
            'smtp_secure' => $this->get('email_smtp_secure', 'tls'),
            'from_name' => $this->get('email_from_name', 'KFZ Werkstatt'),
            'from_address' => $this->get('email_from_address')
        ];
    }

    /**
     * Preiseinstellungen abrufen
     */
    public function getPreisSettings()
    {
        return [
            'basis_stundenpreis' => floatval($this->get('basis_stundenpreis', '110')),
            'anfahrt_kosten' => floatval($this->get('anfahrt_kosten', '25')),
            'express_aufschlag' => floatval($this->get('express_aufschlag', '50')),
            'wochenend_aufschlag' => floatval($this->get('wochenend_aufschlag', '75')),
            'mwst_satz' => floatval($this->get('mwst_satz', '19'))
        ];
    }
}
