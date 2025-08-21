<?php

/**
 * KFZ Fac Pro - Auftrag Model
 */

require_once 'Model.php';

class Auftrag extends Model
{
    protected $table = 'auftraege';
    protected $fillable = [
        'auftragsnummer',
        'kunden_id',
        'fahrzeug_id',
        'datum',
        'status',
        'prioritaet',
        'art',
        'beschreibung',
        'symptome',
        'diagnose',
        'arbeiten',
        'material',
        'arbeitszeit_geplant',
        'arbeitszeit_tatsaechlich',
        'kostenvoranschlag',
        'freigabe_betrag',
        'freigabe_datum',
        'freigabe_von',
        'termin_start',
        'termin_ende',
        'mechaniker',
        'werkstatt_platz',
        'kilometerstand',
        'tankfuellung',
        'bemerkungen_intern',
        'bemerkungen_kunde',
        'teile_bestellt',
        'teile_geliefert',
        'rechnung_erstellt',
        'rechnung_id',
        'garantie',
        'garantie_bis',
        'kulanz',
        'versicherungsfall',
        'versicherung_name',
        'schadensnummer',
        'abgeschlossen_am',
        'abgeholt_am',
        'unterschrift_kunde',
        'positionen'
    ];

    /**
     * Konstruktor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Auftrag mit Kunde und Fahrzeug laden
     */
    public function getWithDetails($id)
    {
        try {
            $sql = "SELECT a.*,
                           k.anrede, k.titel, k.vorname, k.nachname, k.firma,
                           k.telefon, k.mobil, k.email,
                           f.kennzeichen, f.marke, f.modell, f.baujahr, f.vin
                    FROM {$this->table} a
                    LEFT JOIN kunden k ON a.kunden_id = k.id
                    LEFT JOIN fahrzeuge f ON a.fahrzeug_id = f.id
                    WHERE a.id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $auftrag = $stmt->fetch();

            if ($auftrag && !empty($auftrag['positionen'])) {
                $auftrag['positionen'] = json_decode($auftrag['positionen'], true);
            }

            return $auftrag;
        } catch (PDOException $e) {
            error_log("getWithDetails Fehler: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Offene Aufträge
     */
    public function getOffene()
    {
        $sql = "SELECT a.*, k.vorname, k.nachname, k.firma, f.kennzeichen
                FROM {$this->table} a
                LEFT JOIN kunden k ON a.kunden_id = k.id
                LEFT JOIN fahrzeuge f ON a.fahrzeug_id = f.id
                WHERE a.status IN ('offen', 'in_bearbeitung', 'warte_auf_teile')
                ORDER BY a.prioritaet DESC, a.datum";

        try {
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getOffene Fehler: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Aufträge nach Status
     */
    public function getByStatus($status)
    {
        $sql = "SELECT a.*, k.vorname, k.nachname, k.firma, f.kennzeichen
                FROM {$this->table} a
                LEFT JOIN kunden k ON a.kunden_id = k.id
                LEFT JOIN fahrzeuge f ON a.fahrzeug_id = f.id
                WHERE a.status = ?
                ORDER BY a.datum DESC";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$status]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getByStatus Fehler: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Heute fällige Aufträge
     */
    public function getHeuteFaellig()
    {
        $sql = "SELECT a.*, k.vorname, k.nachname, k.firma, k.telefon, f.kennzeichen
                FROM {$this->table} a
                LEFT JOIN kunden k ON a.kunden_id = k.id
                LEFT JOIN fahrzeuge f ON a.fahrzeug_id = f.id
                WHERE DATE(a.termin_start) = CURRENT_DATE
                   OR DATE(a.termin_ende) = CURRENT_DATE
                ORDER BY a.termin_start";

        try {
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getHeuteFaellig Fehler: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Überfällige Aufträge
     */
    public function getUeberfaellig()
    {
        $sql = "SELECT a.*, k.vorname, k.nachname, k.firma, k.telefon, f.kennzeichen,
                       DATEDIFF(CURRENT_DATE, a.termin_ende) as tage_ueberfaellig
                FROM {$this->table} a
                LEFT JOIN kunden k ON a.kunden_id = k.id
                LEFT JOIN fahrzeuge f ON a.fahrzeug_id = f.id
                WHERE a.termin_ende < CURRENT_DATE
                  AND a.status NOT IN ('abgeschlossen', 'abgeholt', 'storniert')
                ORDER BY a.termin_ende";

        try {
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getUeberfaellig Fehler: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Aufträge eines Kunden
     */
    public function getByKunde($kundeId)
    {
        $sql = "SELECT a.*, f.kennzeichen
                FROM {$this->table} a
                LEFT JOIN fahrzeuge f ON a.fahrzeug_id = f.id
                WHERE a.kunden_id = ?
                ORDER BY a.datum DESC";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$kundeId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getByKunde Fehler: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Aufträge eines Fahrzeugs
     */
    public function getByFahrzeug($fahrzeugId)
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE fahrzeug_id = ?
                ORDER BY datum DESC";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$fahrzeugId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getByFahrzeug Fehler: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Nächste Auftragsnummer generieren
     */
    public function generateAuftragsnummer()
    {
        try {
            // Präfix aus Einstellungen
            $stmt = $this->db->prepare("SELECT value FROM einstellungen WHERE key = 'auftrag_prefix'");
            $stmt->execute();
            $result = $stmt->fetch();
            $prefix = $result ? $result['value'] : 'A';

            // Jahr
            $jahr = date('Y');

            // Höchste Nummer im aktuellen Jahr
            $sql = "SELECT MAX(CAST(SUBSTR(auftragsnummer, ?) AS INTEGER)) as max_nr
                    FROM {$this->table}
                    WHERE auftragsnummer LIKE ?";

            $prefixLength = strlen($prefix) + 5; // Präfix + Jahr + -
            $pattern = $prefix . $jahr . '-%';

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$prefixLength, $pattern]);
            $result = $stmt->fetch();

            $nextNr = ($result && $result['max_nr']) ? $result['max_nr'] + 1 : 1;

            return $prefix . $jahr . '-' . str_pad($nextNr, 5, '0', STR_PAD_LEFT);
        } catch (PDOException $e) {
            error_log("generateAuftragsnummer Fehler: " . $e->getMessage());
            return 'A' . date('Y') . '-' . time(); // Fallback
        }
    }

    /**
     * Validierung
     */
    public function validate($data)
    {
        $errors = [];

        // Pflichtfelder
        if (empty($data['kunden_id'])) {
            $errors[] = 'Kunde muss ausgewählt werden';
        }

        // Status
        $validStatus = ['offen', 'in_bearbeitung', 'warte_auf_teile', 'fertig', 'abgeschlossen', 'abgeholt', 'storniert'];
        if (!empty($data['status']) && !in_array($data['status'], $validStatus)) {
            $errors[] = 'Ungültiger Status';
        }

        // Priorität
        $validPrioritaet = ['niedrig', 'normal', 'hoch', 'dringend'];
        if (!empty($data['prioritaet']) && !in_array($data['prioritaet'], $validPrioritaet)) {
            $errors[] = 'Ungültige Priorität';
        }

        // Termine
        if (!empty($data['termin_start']) && !empty($data['termin_ende'])) {
            if (strtotime($data['termin_ende']) < strtotime($data['termin_start'])) {
                $errors[] = 'End-Termin kann nicht vor Start-Termin liegen';
            }
        }

        return $errors;
    }

    /**
     * Vor dem Erstellen
     */
    public function beforeCreate(&$data)
    {
        // Auftragsnummer generieren wenn nicht vorhanden
        if (empty($data['auftragsnummer'])) {
            $data['auftragsnummer'] = $this->generateAuftragsnummer();
        }

        // Datum setzen wenn nicht vorhanden
        if (empty($data['datum'])) {
            $data['datum'] = date('Y-m-d');
        }

        // Standard-Status
        if (empty($data['status'])) {
            $data['status'] = 'offen';
        }

        // Standard-Priorität
        if (empty($data['prioritaet'])) {
            $data['prioritaet'] = 'normal';
        }

        // Positionen als JSON speichern
        if (isset($data['positionen']) && is_array($data['positionen'])) {
            $data['positionen'] = json_encode($data['positionen']);
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
        $result = parent::create($data);

        // Wenn Fahrzeug angegeben, Kilometerstand aktualisieren
        if ($result['success'] && !empty($data['fahrzeug_id']) && !empty($data['kilometerstand'])) {
            $this->updateFahrzeugKilometerstand($data['fahrzeug_id'], $data['kilometerstand']);
        }

        return $result;
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

        // Positionen als JSON speichern
        if (isset($data['positionen']) && is_array($data['positionen'])) {
            $data['positionen'] = json_encode($data['positionen']);
        }

        // Status-Änderungen tracken
        if (isset($data['status'])) {
            $current = $this->findById($id);
            if ($current && $current['status'] !== $data['status']) {
                if ($data['status'] === 'abgeschlossen') {
                    $data['abgeschlossen_am'] = date('Y-m-d H:i:s');
                } elseif ($data['status'] === 'abgeholt') {
                    $data['abgeholt_am'] = date('Y-m-d H:i:s');
                }
            }
        }

        // Parent update aufrufen
        $result = parent::update($id, $data);

        // Wenn Kilometerstand geändert, Fahrzeug aktualisieren
        if ($result['success'] && !empty($data['fahrzeug_id']) && !empty($data['kilometerstand'])) {
            $this->updateFahrzeugKilometerstand($data['fahrzeug_id'], $data['kilometerstand']);
        }

        return $result;
    }

    /**
     * Fahrzeug Kilometerstand aktualisieren
     */
    private function updateFahrzeugKilometerstand($fahrzeugId, $kilometerstand)
    {
        try {
            $sql = "UPDATE fahrzeuge SET kilometerstand = ? WHERE id = ? AND kilometerstand < ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$kilometerstand, $fahrzeugId, $kilometerstand]);
        } catch (PDOException $e) {
            error_log("updateFahrzeugKilometerstand Fehler: " . $e->getMessage());
        }
    }

    /**
     * Status ändern
     */
    public function changeStatus($id, $status)
    {
        $validStatus = ['offen', 'in_bearbeitung', 'warte_auf_teile', 'fertig', 'abgeschlossen', 'abgeholt', 'storniert'];

        if (!in_array($status, $validStatus)) {
            return ['success' => false, 'error' => 'Ungültiger Status'];
        }

        $data = ['status' => $status];

        // Spezielle Felder je nach Status
        if ($status === 'abgeschlossen') {
            $data['abgeschlossen_am'] = date('Y-m-d H:i:s');
        } elseif ($status === 'abgeholt') {
            $data['abgeholt_am'] = date('Y-m-d H:i:s');
        }

        return $this->update($id, $data);
    }

    /**
     * Rechnung erstellen
     */
    public function createRechnung($id)
    {
        try {
            // Auftrag laden
            $auftrag = $this->getWithDetails($id);
            if (!$auftrag) {
                return ['success' => false, 'error' => 'Auftrag nicht gefunden'];
            }

            if ($auftrag['rechnung_erstellt']) {
                return ['success' => false, 'error' => 'Rechnung bereits erstellt'];
            }

            // Rechnung Model laden
            require_once dirname(__DIR__) . '/models/Rechnung.php';
            $rechnungModel = new Rechnung();

            // Rechnung erstellen
            $rechnungData = [
                'kunden_id' => $auftrag['kunden_id'],
                'auftrag_id' => $id,
                'fahrzeug_id' => $auftrag['fahrzeug_id'],
                'betreff' => 'Rechnung für Auftrag ' . $auftrag['auftragsnummer'],
                'positionen' => $auftrag['positionen']
            ];

            $result = $rechnungModel->create($rechnungData);

            if ($result['success']) {
                // Auftrag aktualisieren
                $this->update($id, [
                    'rechnung_erstellt' => 1,
                    'rechnung_id' => $result['id']
                ]);
            }

            return $result;
        } catch (Exception $e) {
            error_log("createRechnung Fehler: " . $e->getMessage());
            return ['success' => false, 'error' => 'Fehler beim Erstellen der Rechnung'];
        }
    }

    /**
     * Statistiken
     */
    public function getStatistiken($monat = null, $jahr = null)
    {
        try {
            $stats = [];

            // Basis-WHERE-Clause
            $where = "WHERE 1=1";
            $params = [];

            if ($monat && $jahr) {
                $where .= " AND MONTH(datum) = ? AND YEAR(datum) = ?";
                $params[] = $monat;
                $params[] = $jahr;
            } elseif ($jahr) {
                $where .= " AND YEAR(datum) = ?";
                $params[] = $jahr;
            }

            // Gesamt-Statistik
            $sql = "SELECT 
                        COUNT(*) as gesamt,
                        COUNT(CASE WHEN status = 'offen' THEN 1 END) as offen,
                        COUNT(CASE WHEN status = 'in_bearbeitung' THEN 1 END) as in_bearbeitung,
                        COUNT(CASE WHEN status = 'warte_auf_teile' THEN 1 END) as warte_auf_teile,
                        COUNT(CASE WHEN status = 'fertig' THEN 1 END) as fertig,
                        COUNT(CASE WHEN status = 'abgeschlossen' THEN 1 END) as abgeschlossen,
                        COUNT(CASE WHEN status = 'storniert' THEN 1 END) as storniert,
                        AVG(arbeitszeit_tatsaechlich) as durchschnitt_arbeitszeit
                    FROM {$this->table} 
                    $where";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $stats = $stmt->fetch();

            // Nach Priorität
            $sql = "SELECT prioritaet, COUNT(*) as anzahl 
                    FROM {$this->table} 
                    $where AND status NOT IN ('abgeschlossen', 'storniert')
                    GROUP BY prioritaet";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $stats['prioritaeten'] = $stmt->fetchAll();

            return $stats;
        } catch (PDOException $e) {
            error_log("getStatistiken Fehler: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Kann gelöscht werden?
     */
    public function canDelete($id)
    {
        try {
            // Prüfen ob Rechnung existiert
            $auftrag = $this->findById($id);
            if ($auftrag && $auftrag['rechnung_erstellt']) {
                return ['success' => false, 'error' => 'Auftrag hat bereits eine Rechnung'];
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
}
