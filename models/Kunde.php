<?php

/**
 * KFZ Fac Pro - Kunde Model
 */

require_once 'Model.php';

class Kunde extends Model
{
    protected $table = 'kunden';
    protected $fillable = [
        'anrede',
        'titel',
        'vorname',
        'nachname',
        'firma',
        'strasse',
        'hausnummer',
        'plz',
        'ort',
        'land',
        'telefon',
        'mobil',
        'email',
        'geburtsdatum',
        'steuernummer',
        'ustid',
        'bemerkungen',
        'rabatt',
        'zahlungsziel',
        'kreditlimit',
        'gesperrt',
        'sperr_grund',
        'newsletter',
        'dsgvo_einwilligung',
        'dsgvo_datum',
        'kunde_seit',
        'bewertung',
        'umsatz_gesamt',
        'offene_posten',
        'letzter_kontakt',
        'bevorzugte_kontaktart',
        'kundennummer'
    ];

    /**
     * Konstruktor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Vollständigen Namen generieren
     */
    public function getFullName($kunde)
    {
        $parts = [];

        if (!empty($kunde['anrede'])) {
            $parts[] = $kunde['anrede'];
        }
        if (!empty($kunde['titel'])) {
            $parts[] = $kunde['titel'];
        }
        if (!empty($kunde['vorname'])) {
            $parts[] = $kunde['vorname'];
        }
        if (!empty($kunde['nachname'])) {
            $parts[] = $kunde['nachname'];
        }

        return implode(' ', $parts);
    }

    /**
     * Kunden suchen
     */
    public function search($query)
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE vorname LIKE ? 
                   OR nachname LIKE ? 
                   OR firma LIKE ? 
                   OR email LIKE ? 
                   OR telefon LIKE ? 
                   OR mobil LIKE ?
                   OR kundennummer LIKE ?
                ORDER BY nachname, vorname";

        $searchTerm = '%' . $query . '%';

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $searchTerm,
                $searchTerm,
                $searchTerm,
                $searchTerm,
                $searchTerm,
                $searchTerm,
                $searchTerm
            ]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Kunden-Suche Fehler: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Kunde mit Fahrzeugen laden
     */
    public function getWithFahrzeuge($id)
    {
        try {
            // Kunde laden
            $kunde = $this->findById($id);
            if (!$kunde) {
                return null;
            }

            // Fahrzeuge laden
            $sql = "SELECT * FROM fahrzeuge WHERE kunden_id = ? ORDER BY kennzeichen";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $kunde['fahrzeuge'] = $stmt->fetchAll();

            return $kunde;
        } catch (PDOException $e) {
            error_log("Kunde mit Fahrzeugen Fehler: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Kunde mit Aufträgen laden
     */
    public function getWithAuftraege($id)
    {
        try {
            // Kunde laden
            $kunde = $this->findById($id);
            if (!$kunde) {
                return null;
            }

            // Aufträge laden
            $sql = "SELECT * FROM auftraege WHERE kunden_id = ? ORDER BY datum DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $kunde['auftraege'] = $stmt->fetchAll();

            return $kunde;
        } catch (PDOException $e) {
            error_log("Kunde mit Aufträgen Fehler: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Kunde mit Rechnungen laden
     */
    public function getWithRechnungen($id)
    {
        try {
            // Kunde laden
            $kunde = $this->findById($id);
            if (!$kunde) {
                return null;
            }

            // Rechnungen laden
            $sql = "SELECT * FROM rechnungen WHERE kunden_id = ? ORDER BY datum DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $kunde['rechnungen'] = $stmt->fetchAll();

            return $kunde;
        } catch (PDOException $e) {
            error_log("Kunde mit Rechnungen Fehler: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Umsatz-Statistiken
     */
    public function getUmsatzStatistik($id)
    {
        try {
            $sql = "SELECT 
                        COUNT(*) as anzahl_rechnungen,
                        SUM(gesamtbetrag) as umsatz_gesamt,
                        AVG(gesamtbetrag) as umsatz_durchschnitt,
                        MAX(gesamtbetrag) as hoechste_rechnung,
                        MIN(gesamtbetrag) as niedrigste_rechnung,
                        SUM(CASE WHEN bezahlt = 0 THEN gesamtbetrag ELSE 0 END) as offene_posten
                    FROM rechnungen 
                    WHERE kunden_id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Umsatz-Statistik Fehler: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Vor dem Erstellen
     */
    public function beforeCreate(&$data)
    {
        // Kundennummer generieren wenn nicht vorhanden
        if (empty($data['kundennummer'])) {
            $data['kundennummer'] = $this->generateKundennummer();
        }

        // Kunde seit Datum setzen
        if (empty($data['kunde_seit'])) {
            $data['kunde_seit'] = date('Y-m-d');
        }

        // DSGVO-Datum setzen wenn Einwilligung
        if (!empty($data['dsgvo_einwilligung']) && empty($data['dsgvo_datum'])) {
            $data['dsgvo_datum'] = date('Y-m-d H:i:s');
        }
    }

    /**
     * Kundennummer generieren
     */
    private function generateKundennummer()
    {
        try {
            // Präfix aus Einstellungen holen
            $stmt = $this->db->prepare("SELECT value FROM einstellungen WHERE key = 'kunde_prefix'");
            $stmt->execute();
            $result = $stmt->fetch();
            $prefix = $result ? $result['value'] : 'K';

            // Höchste Nummer finden
            $sql = "SELECT MAX(CAST(REPLACE(kundennummer, ?, '') AS INTEGER)) as max_nr 
                    FROM {$this->table} 
                    WHERE kundennummer LIKE ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$prefix, $prefix . '%']);
            $result = $stmt->fetch();

            $nextNr = ($result && $result['max_nr']) ? $result['max_nr'] + 1 : 1;

            return $prefix . str_pad($nextNr, 5, '0', STR_PAD_LEFT);
        } catch (PDOException $e) {
            error_log("Kundennummer generieren Fehler: " . $e->getMessage());
            return 'K' . time(); // Fallback
        }
    }

    /**
     * Validierung
     */
    public function validate($data)
    {
        $errors = [];

        // Pflichtfelder
        if (empty($data['nachname']) && empty($data['firma'])) {
            $errors[] = 'Nachname oder Firma muss angegeben werden';
        }

        // E-Mail Format
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Ungültiges E-Mail Format';
        }

        // PLZ Format (5-stellig für Deutschland)
        if (!empty($data['plz']) && !empty($data['land'])) {
            if ($data['land'] === 'Deutschland' && !preg_match('/^\d{5}$/', $data['plz'])) {
                $errors[] = 'PLZ muss 5-stellig sein';
            }
        }

        // Rabatt zwischen 0 und 100
        if (isset($data['rabatt'])) {
            $rabatt = floatval($data['rabatt']);
            if ($rabatt < 0 || $rabatt > 100) {
                $errors[] = 'Rabatt muss zwischen 0 und 100 liegen';
            }
        }

        return $errors;
    }

    /**
     * Erstellen mit Validierung
     */
    public function create($data)
    {
        // Validierung
        $errors = $this->validate($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Vor dem Erstellen
        $this->beforeCreate($data);

        // Parent create aufrufen
        return parent::create($data);
    }

    /**
     * Update mit Validierung
     */
    public function update($id, $data)
    {
        // Validierung
        $errors = $this->validate($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // DSGVO-Datum aktualisieren wenn sich Einwilligung ändert
        if (isset($data['dsgvo_einwilligung']) && $data['dsgvo_einwilligung']) {
            $current = $this->findById($id);
            if ($current && !$current['dsgvo_einwilligung']) {
                $data['dsgvo_datum'] = date('Y-m-d H:i:s');
            }
        }

        // Parent update aufrufen
        return parent::update($id, $data);
    }

    /**
     * Kunde kann gelöscht werden?
     */
    public function canDelete($id)
    {
        try {
            // Prüfen ob noch Aufträge existieren
            $sql = "SELECT COUNT(*) as count FROM auftraege WHERE kunden_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $result = $stmt->fetch();
            if ($result['count'] > 0) {
                return ['success' => false, 'error' => 'Kunde hat noch Aufträge'];
            }

            // Prüfen ob noch Rechnungen existieren
            $sql = "SELECT COUNT(*) as count FROM rechnungen WHERE kunden_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $result = $stmt->fetch();
            if ($result['count'] > 0) {
                return ['success' => false, 'error' => 'Kunde hat noch Rechnungen'];
            }

            return ['success' => true];
        } catch (PDOException $e) {
            error_log("canDelete Fehler: " . $e->getMessage());
            return ['success' => false, 'error' => 'Datenbankfehler'];
        }
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
     * Export als CSV
     */
    public function exportCsv()
    {
        $kunden = $this->findAll('nachname, vorname');

        $csv = "Kundennummer;Anrede;Titel;Vorname;Nachname;Firma;Strasse;PLZ;Ort;Telefon;Mobil;E-Mail;Kunde seit\n";

        foreach ($kunden as $kunde) {
            $csv .= sprintf(
                "%s;%s;%s;%s;%s;%s;%s %s;%s;%s;%s;%s;%s;%s\n",
                $kunde['kundennummer'],
                $kunde['anrede'],
                $kunde['titel'],
                $kunde['vorname'],
                $kunde['nachname'],
                $kunde['firma'],
                $kunde['strasse'],
                $kunde['hausnummer'],
                $kunde['plz'],
                $kunde['ort'],
                $kunde['telefon'],
                $kunde['mobil'],
                $kunde['email'],
                $kunde['kunde_seit']
            );
        }

        return $csv;
    }
}
