<?php

/**
 * KFZ Fac Pro - Fahrzeughandel Model
 */

require_once 'Model.php';

class FahrzeugHandel extends Model
{
    protected $table = 'fahrzeug_handel';
    protected $fillable = [
        'handel_nr',
        'typ',
        'kunden_id',
        'kaeufer_id',
        'fahrzeug_id',
        'datum',
        'status',
        'ankaufspreis',
        'verkaufspreis',
        'gewinn',
        'kennzeichen',
        'marke',
        'modell',
        'baujahr',
        'kilometerstand',
        'farbe',
        'vin',
        'zustand',
        'tuev_bis',
        'au_bis',
        'papiere_vollstaendig',
        'bemerkungen',
        'interne_notizen',
        'verkauft_an',
        'abgeschlossen_am',
        'dokumente'
    ];

    /**
     * Konstruktor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Handel mit Details laden
     */
    public function getWithDetails($id)
    {
        try {
            $sql = "SELECT h.*,
                           k.anrede, k.titel, k.vorname, k.nachname, k.firma as kunden_firma,
                           k.telefon, k.mobil, k.email,
                           k2.vorname as kaeufer_vorname, k2.nachname as kaeufer_nachname,
                           k2.firma as kaeufer_firma,
                           f.kennzeichen as fahrzeug_kennzeichen
                    FROM {$this->table} h
                    LEFT JOIN kunden k ON h.kunden_id = k.id
                    LEFT JOIN kunden k2 ON h.kaeufer_id = k2.id
                    LEFT JOIN fahrzeuge f ON h.fahrzeug_id = f.id
                    WHERE h.id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("getWithDetails Fehler: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Offene Geschäfte
     */
    public function getOffene()
    {
        $sql = "SELECT h.*, 
                       k.vorname, k.nachname, k.firma,
                       k2.vorname as kaeufer_vorname, k2.nachname as kaeufer_nachname
                FROM {$this->table} h
                LEFT JOIN kunden k ON h.kunden_id = k.id
                LEFT JOIN kunden k2 ON h.kaeufer_id = k2.id
                WHERE h.status = 'offen'
                ORDER BY h.datum DESC";

        try {
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getOffene Fehler: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Nach Typ filtern
     */
    public function getByTyp($typ)
    {
        $sql = "SELECT h.*, 
                       k.vorname, k.nachname, k.firma,
                       k2.vorname as kaeufer_vorname, k2.nachname as kaeufer_nachname
                FROM {$this->table} h
                LEFT JOIN kunden k ON h.kunden_id = k.id
                LEFT JOIN kunden k2 ON h.kaeufer_id = k2.id
                WHERE h.typ = ?
                ORDER BY h.datum DESC";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$typ]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getByTyp Fehler: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Dashboard-Statistiken
     */
    public function getDashboardStats()
    {
        try {
            $stats = [];

            // Gesamt-Statistiken
            $sql = "SELECT 
                        COUNT(*) as gesamt,
                        COUNT(CASE WHEN typ = 'ankauf' THEN 1 END) as ankaeufe,
                        COUNT(CASE WHEN typ = 'verkauf' THEN 1 END) as verkaeufe,
                        COUNT(CASE WHEN status = 'offen' THEN 1 END) as offen,
                        COUNT(CASE WHEN status = 'abgeschlossen' THEN 1 END) as abgeschlossen,
                        SUM(CASE WHEN typ = 'ankauf' THEN ankaufspreis ELSE 0 END) as gesamt_ankauf,
                        SUM(CASE WHEN typ = 'verkauf' THEN verkaufspreis ELSE 0 END) as gesamt_verkauf,
                        SUM(gewinn) as gesamt_gewinn
                    FROM {$this->table}
                    WHERE status != 'storniert'";

            $stmt = $this->db->query($sql);
            $stats = $stmt->fetch();

            // Durchschnittswerte
            if ($stats['verkaeufe'] > 0) {
                $stats['durchschnitt_verkaufspreis'] = round($stats['gesamt_verkauf'] / $stats['verkaeufe'], 2);
                $stats['durchschnitt_gewinn'] = round($stats['gesamt_gewinn'] / $stats['verkaeufe'], 2);
            } else {
                $stats['durchschnitt_verkaufspreis'] = 0;
                $stats['durchschnitt_gewinn'] = 0;
            }

            // Aktueller Monat
            $sql = "SELECT 
                        COUNT(CASE WHEN typ = 'ankauf' THEN 1 END) as ankaeufe_monat,
                        COUNT(CASE WHEN typ = 'verkauf' THEN 1 END) as verkaeufe_monat,
                        SUM(CASE WHEN typ = 'verkauf' THEN gewinn ELSE 0 END) as gewinn_monat
                    FROM {$this->table}
                    WHERE MONTH(datum) = MONTH(CURRENT_DATE)
                      AND YEAR(datum) = YEAR(CURRENT_DATE)
                      AND status != 'storniert'";

            $stmt = $this->db->query($sql);
            $monat = $stmt->fetch();
            $stats['monat'] = $monat;

            // Top Marken
            $sql = "SELECT marke, COUNT(*) as anzahl, AVG(gewinn) as avg_gewinn
                    FROM {$this->table}
                    WHERE marke IS NOT NULL AND status = 'abgeschlossen'
                    GROUP BY marke
                    ORDER BY anzahl DESC
                    LIMIT 5";

            $stmt = $this->db->query($sql);
            $stats['top_marken'] = $stmt->fetchAll();

            // Lagerbestand
            $sql = "SELECT COUNT(*) as lagerbestand
                    FROM {$this->table}
                    WHERE typ = 'ankauf' 
                      AND status = 'offen'
                      AND id NOT IN (
                          SELECT fahrzeug_id FROM {$this->table} 
                          WHERE typ = 'verkauf' AND fahrzeug_id IS NOT NULL
                      )";

            $stmt = $this->db->query($sql);
            $stats['lagerbestand'] = $stmt->fetch()['lagerbestand'];

            return $stats;
        } catch (PDOException $e) {
            error_log("getDashboardStats Fehler: " . $e->getMessage());
            return [
                'gesamt' => 0,
                'ankaeufe' => 0,
                'verkaeufe' => 0,
                'offen' => 0,
                'abgeschlossen' => 0,
                'gesamt_ankauf' => 0,
                'gesamt_verkauf' => 0,
                'gesamt_gewinn' => 0,
                'lagerbestand' => 0
            ];
        }
    }

    /**
     * Dropdown-Optionen für Kunden
     */
    public function getKundenOptions()
    {
        try {
            $sql = "SELECT id, vorname, nachname, firma, kundennummer 
                    FROM kunden 
                    ORDER BY nachname, vorname";

            $stmt = $this->db->query($sql);
            $kunden = $stmt->fetchAll();

            // Formatierung für Dropdown
            $options = [];
            foreach ($kunden as $kunde) {
                $name = trim($kunde['vorname'] . ' ' . $kunde['nachname']);
                if (!empty($kunde['firma'])) {
                    $name = $kunde['firma'] . ($name ? ' - ' . $name : '');
                }
                $options[] = [
                    'id' => $kunde['id'],
                    'text' => $name,
                    'kundennummer' => $kunde['kundennummer']
                ];
            }

            return $options;
        } catch (PDOException $e) {
            error_log("getKundenOptions Fehler: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Dropdown-Optionen für Fahrzeuge
     */
    public function getFahrzeugeOptions()
    {
        try {
            $sql = "SELECT id, kennzeichen, marke, modell, baujahr, kunden_id 
                    FROM fahrzeuge 
                    ORDER BY kennzeichen";

            $stmt = $this->db->query($sql);
            $fahrzeuge = $stmt->fetchAll();

            // Formatierung für Dropdown
            $options = [];
            foreach ($fahrzeuge as $fahrzeug) {
                $text = $fahrzeug['kennzeichen'];
                if ($fahrzeug['marke'] || $fahrzeug['modell']) {
                    $text .= ' - ' . trim($fahrzeug['marke'] . ' ' . $fahrzeug['modell']);
                }
                if ($fahrzeug['baujahr']) {
                    $text .= ' (' . $fahrzeug['baujahr'] . ')';
                }

                $options[] = [
                    'id' => $fahrzeug['id'],
                    'text' => $text,
                    'kennzeichen' => $fahrzeug['kennzeichen'],
                    'kunden_id' => $fahrzeug['kunden_id']
                ];
            }

            return $options;
        } catch (PDOException $e) {
            error_log("getFahrzeugeOptions Fehler: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Nächste Handel-Nummer generieren
     */
    public function generateHandelNr()
    {
        try {
            $sql = "SELECT MAX(CAST(SUBSTR(handel_nr, 2) AS INTEGER)) as max_nr
                    FROM {$this->table}
                    WHERE handel_nr LIKE 'H%'";

            $stmt = $this->db->query($sql);
            $result = $stmt->fetch();

            $nextNr = ($result && $result['max_nr']) ? $result['max_nr'] + 1 : 1;

            return 'H' . str_pad($nextNr, 6, '0', STR_PAD_LEFT);
        } catch (PDOException $e) {
            error_log("generateHandelNr Fehler: " . $e->getMessage());
            return 'H' . date('ymdHis'); // Fallback
        }
    }

    /**
     * Validierung
     */
    public function validate($data)
    {
        $errors = [];

        // Typ
        if (empty($data['typ']) || !in_array($data['typ'], ['ankauf', 'verkauf'])) {
            $errors[] = 'Typ muss Ankauf oder Verkauf sein';
        }

        // Bei Verkauf muss Verkaufspreis vorhanden sein
        if ($data['typ'] === 'verkauf' && empty($data['verkaufspreis'])) {
            $errors[] = 'Verkaufspreis ist erforderlich';
        }

        // Bei Ankauf muss Ankaufspreis vorhanden sein
        if ($data['typ'] === 'ankauf' && empty($data['ankaufspreis'])) {
            $errors[] = 'Ankaufspreis ist erforderlich';
        }

        // Kennzeichen
        if (empty($data['kennzeichen'])) {
            $errors[] = 'Kennzeichen ist erforderlich';
        }

        return $errors;
    }

    /**
     * Vor dem Erstellen
     */
    public function beforeCreate(&$data)
    {
        // Handel-Nummer generieren
        if (empty($data['handel_nr'])) {
            $data['handel_nr'] = $this->generateHandelNr();
        }

        // Datum setzen
        if (empty($data['datum'])) {
            $data['datum'] = date('Y-m-d');
        }

        // Status setzen
        if (empty($data['status'])) {
            $data['status'] = 'offen';
        }

        // Gewinn berechnen bei Verkauf
        if ($data['typ'] === 'verkauf' && !empty($data['verkaufspreis']) && !empty($data['ankaufspreis'])) {
            $data['gewinn'] = floatval($data['verkaufspreis']) - floatval($data['ankaufspreis']);
        }

        // Kennzeichen normalisieren
        if (!empty($data['kennzeichen'])) {
            $data['kennzeichen'] = strtoupper(str_replace(' ', '', $data['kennzeichen']));
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

        // Gewinn neu berechnen
        if (isset($data['verkaufspreis']) || isset($data['ankaufspreis'])) {
            $current = $this->findById($id);
            $verkaufspreis = isset($data['verkaufspreis']) ? $data['verkaufspreis'] : $current['verkaufspreis'];
            $ankaufspreis = isset($data['ankaufspreis']) ? $data['ankaufspreis'] : $current['ankaufspreis'];

            if ($verkaufspreis && $ankaufspreis) {
                $data['gewinn'] = floatval($verkaufspreis) - floatval($ankaufspreis);
            }
        }

        // Status-Änderung tracken
        if (isset($data['status']) && $data['status'] === 'abgeschlossen') {
            $data['abgeschlossen_am'] = date('Y-m-d H:i:s');
        }

        // Parent update aufrufen
        return parent::update($id, $data);
    }

    /**
     * Monats-Statistiken
     */
    public function getMonthlyStats($jahr = null)
    {
        if (!$jahr) {
            $jahr = date('Y');
        }

        try {
            $sql = "SELECT 
                        MONTH(datum) as monat,
                        COUNT(CASE WHEN typ = 'ankauf' THEN 1 END) as ankaeufe,
                        COUNT(CASE WHEN typ = 'verkauf' THEN 1 END) as verkaeufe,
                        SUM(CASE WHEN typ = 'ankauf' THEN ankaufspreis ELSE 0 END) as ankauf_summe,
                        SUM(CASE WHEN typ = 'verkauf' THEN verkaufspreis ELSE 0 END) as verkauf_summe,
                        SUM(CASE WHEN typ = 'verkauf' THEN gewinn ELSE 0 END) as gewinn_summe
                    FROM {$this->table}
                    WHERE YEAR(datum) = ?
                      AND status != 'storniert'
                    GROUP BY MONTH(datum)
                    ORDER BY monat";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$jahr]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getMonthlyStats Fehler: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Export als CSV
     */
    public function exportCsv()
    {
        $handel = $this->findAll('datum DESC');

        $csv = "Handel-Nr;Typ;Datum;Status;Kennzeichen;Marke;Modell;Baujahr;Ankaufspreis;Verkaufspreis;Gewinn\n";

        foreach ($handel as $h) {
            $csv .= sprintf(
                "%s;%s;%s;%s;%s;%s;%s;%s;%.2f;%.2f;%.2f\n",
                $h['handel_nr'],
                $h['typ'],
                $h['datum'],
                $h['status'],
                $h['kennzeichen'],
                $h['marke'] ?? '',
                $h['modell'] ?? '',
                $h['baujahr'] ?? '',
                $h['ankaufspreis'] ?? 0,
                $h['verkaufspreis'] ?? 0,
                $h['gewinn'] ?? 0
            );
        }

        return $csv;
    }
}
