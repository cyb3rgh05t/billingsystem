<?php

/**
 * KFZ Fac Pro - Fahrzeug Model
 */

require_once 'Model.php';

class Fahrzeug extends Model
{
    protected $table = 'fahrzeuge';
    protected $fillable = [
        'kunden_id',
        'kennzeichen',
        'marke',
        'modell',
        'typ',
        'baujahr',
        'erstzulassung',
        'vin',
        'fin',
        'motorcode',
        'hubraum',
        'leistung_kw',
        'leistung_ps',
        'kraftstoff',
        'getriebe',
        'farbe',
        'farbe_code',
        'innenausstattung',
        'kilometerstand',
        'tuev_bis',
        'au_bis',
        'service_intervall_km',
        'service_intervall_monate',
        'letzter_service',
        'naechster_service',
        'versicherung',
        'versicherungsnummer',
        'schluessel_nummer',
        'radio_code',
        'bemerkungen',
        'sonderausstattung',
        'reifen_sommer',
        'reifen_winter',
        'reifen_zustand',
        'bremsen_vorne_prozent',
        'bremsen_hinten_prozent',
        'batterie_datum',
        'oelwechsel_bei_km',
        'oelwechsel_datum',
        'zahnriemen_bei_km',
        'zahnriemen_datum'
    ];

    /**
     * Konstruktor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Fahrzeuge nach Kennzeichen suchen
     */
    public function findByKennzeichen($kennzeichen)
    {
        $sql = "SELECT * FROM {$this->table} WHERE kennzeichen = ?";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$kennzeichen]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("findByKennzeichen Fehler: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Fahrzeuge eines Kunden
     */
    public function findByKundeId($kundeId)
    {
        $sql = "SELECT * FROM {$this->table} WHERE kunden_id = ? ORDER BY kennzeichen";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$kundeId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("findByKundeId Fehler: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Fahrzeug mit Kunde laden
     */
    public function getWithKunde($id)
    {
        try {
            $sql = "SELECT f.*, k.vorname, k.nachname, k.firma, k.telefon, k.mobil, k.email 
                    FROM {$this->table} f
                    LEFT JOIN kunden k ON f.kunden_id = k.id
                    WHERE f.id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("getWithKunde Fehler: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Fahrzeug mit Service-Historie
     */
    public function getWithServiceHistory($id)
    {
        try {
            // Fahrzeug laden
            $fahrzeug = $this->findById($id);
            if (!$fahrzeug) {
                return null;
            }

            // Service-Historie aus Aufträgen laden
            $sql = "SELECT a.id, a.datum, a.beschreibung, a.kilometerstand, 
                           a.gesamtbetrag, a.status
                    FROM auftraege a
                    WHERE a.fahrzeug_id = ?
                    ORDER BY a.datum DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $fahrzeug['service_historie'] = $stmt->fetchAll();

            return $fahrzeug;
        } catch (PDOException $e) {
            error_log("getWithServiceHistory Fehler: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Fahrzeuge suchen
     */
    public function search($query)
    {
        $sql = "SELECT f.*, k.vorname, k.nachname, k.firma 
                FROM {$this->table} f
                LEFT JOIN kunden k ON f.kunden_id = k.id
                WHERE f.kennzeichen LIKE ? 
                   OR f.marke LIKE ? 
                   OR f.modell LIKE ? 
                   OR f.vin LIKE ?
                   OR k.nachname LIKE ?
                   OR k.firma LIKE ?
                ORDER BY f.kennzeichen";

        $searchTerm = '%' . $query . '%';

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $searchTerm,
                $searchTerm,
                $searchTerm,
                $searchTerm,
                $searchTerm,
                $searchTerm
            ]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Fahrzeug-Suche Fehler: " . $e->getMessage());
            return [];
        }
    }

    /**
     * TÜV/AU fällige Fahrzeuge
     */
    public function getTuevAuFaellig($tage = 30)
    {
        $datum = date('Y-m-d', strtotime("+{$tage} days"));

        $sql = "SELECT f.*, k.vorname, k.nachname, k.firma, k.telefon, k.email
                FROM {$this->table} f
                LEFT JOIN kunden k ON f.kunden_id = k.id
                WHERE f.tuev_bis <= ? OR f.au_bis <= ?
                ORDER BY f.tuev_bis, f.au_bis";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$datum, $datum]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getTuevAuFaellig Fehler: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Service fällige Fahrzeuge
     */
    public function getServiceFaellig()
    {
        $sql = "SELECT f.*, k.vorname, k.nachname, k.firma, k.telefon, k.email
                FROM {$this->table} f
                LEFT JOIN kunden k ON f.kunden_id = k.id
                WHERE f.naechster_service <= CURRENT_DATE
                   OR (f.service_intervall_km > 0 
                       AND f.kilometerstand >= f.letzter_service + f.service_intervall_km)
                ORDER BY f.naechster_service";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getServiceFaellig Fehler: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Validierung
     */
    public function validate($data)
    {
        $errors = [];

        // Pflichtfelder
        if (empty($data['kennzeichen'])) {
            $errors[] = 'Kennzeichen ist erforderlich';
        }

        // Kennzeichen Format (vereinfacht)
        if (!empty($data['kennzeichen'])) {
            $kennzeichen = strtoupper(str_replace(' ', '', $data['kennzeichen']));
            if (!preg_match('/^[A-Z]{1,3}[A-Z]{1,2}[0-9]{1,4}[A-Z]{0,1}$/', $kennzeichen)) {
                $errors[] = 'Ungültiges Kennzeichen-Format';
            }
        }

        // Baujahr
        if (!empty($data['baujahr'])) {
            $baujahr = intval($data['baujahr']);
            $currentYear = date('Y');
            if ($baujahr < 1900 || $baujahr > $currentYear + 1) {
                $errors[] = 'Ungültiges Baujahr';
            }
        }

        // Kilometerstand
        if (isset($data['kilometerstand']) && $data['kilometerstand'] < 0) {
            $errors[] = 'Kilometerstand kann nicht negativ sein';
        }

        // VIN/FIN Länge
        if (!empty($data['vin']) && strlen($data['vin']) != 17) {
            $errors[] = 'VIN muss 17 Zeichen lang sein';
        }

        return $errors;
    }

    /**
     * Vor dem Erstellen
     */
    public function beforeCreate(&$data)
    {
        // Kennzeichen normalisieren
        if (!empty($data['kennzeichen'])) {
            $data['kennzeichen'] = strtoupper(str_replace(' ', '', $data['kennzeichen']));
        }

        // PS aus KW berechnen falls nicht angegeben
        if (!empty($data['leistung_kw']) && empty($data['leistung_ps'])) {
            $data['leistung_ps'] = round($data['leistung_kw'] * 1.36);
        }

        // KW aus PS berechnen falls nicht angegeben
        if (!empty($data['leistung_ps']) && empty($data['leistung_kw'])) {
            $data['leistung_kw'] = round($data['leistung_ps'] / 1.36);
        }
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

        // Prüfen ob Kennzeichen bereits existiert
        $existing = $this->findByKennzeichen($data['kennzeichen']);
        if ($existing) {
            return ['success' => false, 'error' => 'Kennzeichen existiert bereits'];
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

        // Wenn Kennzeichen geändert wird, prüfen ob es bereits existiert
        if (!empty($data['kennzeichen'])) {
            $data['kennzeichen'] = strtoupper(str_replace(' ', '', $data['kennzeichen']));

            $existing = $this->findByKennzeichen($data['kennzeichen']);
            if ($existing && $existing['id'] != $id) {
                return ['success' => false, 'error' => 'Kennzeichen existiert bereits'];
            }
        }

        // PS/KW berechnen
        if (!empty($data['leistung_kw']) && empty($data['leistung_ps'])) {
            $data['leistung_ps'] = round($data['leistung_kw'] * 1.36);
        }
        if (!empty($data['leistung_ps']) && empty($data['leistung_kw'])) {
            $data['leistung_kw'] = round($data['leistung_ps'] / 1.36);
        }

        // Parent update aufrufen
        return parent::update($id, $data);
    }

    /**
     * Fahrzeug kann gelöscht werden?
     */
    public function canDelete($id)
    {
        try {
            // Prüfen ob noch Aufträge existieren
            $sql = "SELECT COUNT(*) as count FROM auftraege WHERE fahrzeug_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $result = $stmt->fetch();
            if ($result['count'] > 0) {
                return ['success' => false, 'error' => 'Fahrzeug hat noch Aufträge'];
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
     * Statistiken
     */
    public function getStatistiken()
    {
        try {
            $stats = [];

            // Gesamt-Anzahl
            $sql = "SELECT COUNT(*) as gesamt FROM {$this->table}";
            $stmt = $this->db->query($sql);
            $stats['gesamt'] = $stmt->fetch()['gesamt'];

            // Nach Marke
            $sql = "SELECT marke, COUNT(*) as anzahl 
                    FROM {$this->table} 
                    GROUP BY marke 
                    ORDER BY anzahl DESC 
                    LIMIT 10";
            $stmt = $this->db->query($sql);
            $stats['top_marken'] = $stmt->fetchAll();

            // Nach Kraftstoff
            $sql = "SELECT kraftstoff, COUNT(*) as anzahl 
                    FROM {$this->table} 
                    WHERE kraftstoff IS NOT NULL 
                    GROUP BY kraftstoff";
            $stmt = $this->db->query($sql);
            $stats['kraftstoff'] = $stmt->fetchAll();

            // Durchschnittliches Alter
            $sql = "SELECT AVG(YEAR(CURRENT_DATE) - baujahr) as durchschnittsalter 
                    FROM {$this->table} 
                    WHERE baujahr IS NOT NULL";
            $stmt = $this->db->query($sql);
            $stats['durchschnittsalter'] = round($stmt->fetch()['durchschnittsalter'], 1);

            return $stats;
        } catch (PDOException $e) {
            error_log("getStatistiken Fehler: " . $e->getMessage());
            return [];
        }
    }
}
