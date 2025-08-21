<?php

/**
 * KFZ Fac Pro - Rechnung Model
 */

require_once 'Model.php';

class Rechnung extends Model
{
    protected $table = 'rechnungen';
    protected $fillable = [
        'rechnungsnummer',
        'kunden_id',
        'auftrag_id',
        'fahrzeug_id',
        'datum',
        'faellig_am',
        'bezahlt',
        'bezahlt_am',
        'zahlungsart',
        'status',
        'netto',
        'mwst_satz',
        'mwst_betrag',
        'gesamtbetrag',
        'rabatt_prozent',
        'rabatt_betrag',
        'versand_kosten',
        'betreff',
        'einleitung',
        'schlusstext',
        'interne_notiz',
        'positionen',
        'mahnstufe',
        'gemahnt_am',
        'storniert',
        'storno_datum',
        'storno_grund',
        'anzahlung_betrag',
        'anzahlung_datum',
        'restbetrag',
        'anzahlung_aktiv',
        'skonto_aktiv',
        'skonto_prozent',
        'skonto_betrag',
        'skonto_faellig_bis',
        'leistungsdatum_start',
        'leistungsdatum_ende'
    ];

    /**
     * Konstruktor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Rechnung mit Kunde laden
     */
    public function getWithKunde($id)
    {
        try {
            $sql = "SELECT r.*, 
                           k.anrede, k.titel, k.vorname, k.nachname, k.firma,
                           k.strasse, k.hausnummer, k.plz, k.ort, k.land,
                           k.telefon, k.mobil, k.email, k.steuernummer, k.ustid
                    FROM {$this->table} r
                    LEFT JOIN kunden k ON r.kunden_id = k.id
                    WHERE r.id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $rechnung = $stmt->fetch();

            if ($rechnung && !empty($rechnung['positionen'])) {
                $rechnung['positionen'] = json_decode($rechnung['positionen'], true);
            }

            return $rechnung;
        } catch (PDOException $e) {
            error_log("getWithKunde Fehler: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Offene Rechnungen
     */
    public function getOffene()
    {
        $sql = "SELECT r.*, k.vorname, k.nachname, k.firma
                FROM {$this->table} r
                LEFT JOIN kunden k ON r.kunden_id = k.id
                WHERE r.bezahlt = 0 AND r.storniert = 0
                ORDER BY r.faellig_am";

        try {
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getOffene Fehler: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Überfällige Rechnungen
     */
    public function getUeberfaellige()
    {
        $sql = "SELECT r.*, k.vorname, k.nachname, k.firma, k.telefon, k.email,
                       DATEDIFF(CURRENT_DATE, r.faellig_am) as tage_ueberfaellig
                FROM {$this->table} r
                LEFT JOIN kunden k ON r.kunden_id = k.id
                WHERE r.bezahlt = 0 
                  AND r.storniert = 0 
                  AND r.faellig_am < CURRENT_DATE
                ORDER BY r.faellig_am";

        try {
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getUeberfaellige Fehler: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Rechnungen nach Status
     */
    public function getByStatus($status)
    {
        $sql = "SELECT r.*, k.vorname, k.nachname, k.firma
                FROM {$this->table} r
                LEFT JOIN kunden k ON r.kunden_id = k.id
                WHERE r.status = ?
                ORDER BY r.datum DESC";

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
     * Nächste Rechnungsnummer generieren
     */
    public function generateRechnungsnummer()
    {
        try {
            // Präfix aus Einstellungen
            $stmt = $this->db->prepare("SELECT value FROM einstellungen WHERE key = 'rechnung_prefix'");
            $stmt->execute();
            $result = $stmt->fetch();
            $prefix = $result ? $result['value'] : 'R';

            // Jahr
            $jahr = date('Y');

            // Höchste Nummer im aktuellen Jahr
            $sql = "SELECT MAX(CAST(SUBSTR(rechnungsnummer, ?) AS INTEGER)) as max_nr
                    FROM {$this->table}
                    WHERE rechnungsnummer LIKE ?";

            $prefixLength = strlen($prefix) + 5; // Präfix + Jahr + -
            $pattern = $prefix . $jahr . '-%';

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$prefixLength, $pattern]);
            $result = $stmt->fetch();

            $nextNr = ($result && $result['max_nr']) ? $result['max_nr'] + 1 : 1;

            return $prefix . $jahr . '-' . str_pad($nextNr, 5, '0', STR_PAD_LEFT);
        } catch (PDOException $e) {
            error_log("generateRechnungsnummer Fehler: " . $e->getMessage());
            return 'R' . date('Y') . '-' . time(); // Fallback
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

        // Beträge
        if (isset($data['gesamtbetrag']) && $data['gesamtbetrag'] < 0) {
            $errors[] = 'Gesamtbetrag kann nicht negativ sein';
        }

        // MwSt-Satz
        if (isset($data['mwst_satz'])) {
            $mwst = floatval($data['mwst_satz']);
            if ($mwst < 0 || $mwst > 100) {
                $errors[] = 'MwSt-Satz muss zwischen 0 und 100 liegen';
            }
        }

        // Rabatt
        if (isset($data['rabatt_prozent'])) {
            $rabatt = floatval($data['rabatt_prozent']);
            if ($rabatt < 0 || $rabatt > 100) {
                $errors[] = 'Rabatt muss zwischen 0 und 100 liegen';
            }
        }

        // Skonto
        if (isset($data['skonto_prozent'])) {
            $skonto = floatval($data['skonto_prozent']);
            if ($skonto < 0 || $skonto > 100) {
                $errors[] = 'Skonto muss zwischen 0 und 100 liegen';
            }
        }

        return $errors;
    }

    /**
     * Vor dem Erstellen
     */
    public function beforeCreate(&$data)
    {
        // Rechnungsnummer generieren wenn nicht vorhanden
        if (empty($data['rechnungsnummer'])) {
            $data['rechnungsnummer'] = $this->generateRechnungsnummer();
        }

        // Datum setzen wenn nicht vorhanden
        if (empty($data['datum'])) {
            $data['datum'] = date('Y-m-d');
        }

        // Fälligkeitsdatum berechnen
        if (empty($data['faellig_am'])) {
            $stmt = $this->db->prepare("SELECT value FROM einstellungen WHERE key = 'zahlungsziel_tage'");
            $stmt->execute();
            $result = $stmt->fetch();
            $zahlungsziel = $result ? intval($result['value']) : 14;

            $data['faellig_am'] = date('Y-m-d', strtotime($data['datum'] . ' + ' . $zahlungsziel . ' days'));
        }

        // Positionen als JSON speichern
        if (isset($data['positionen']) && is_array($data['positionen'])) {
            $data['positionen'] = json_encode($data['positionen']);
        }

        // Beträge berechnen
        $this->calculateAmounts($data);

        // Status setzen
        if (empty($data['status'])) {
            $data['status'] = 'offen';
        }
    }

    /**
     * Beträge berechnen
     */
    private function calculateAmounts(&$data)
    {
        // Wenn Positionen vorhanden sind
        if (!empty($data['positionen'])) {
            $positionen = is_string($data['positionen'])
                ? json_decode($data['positionen'], true)
                : $data['positionen'];

            $netto = 0;
            foreach ($positionen as $position) {
                $netto += floatval($position['menge']) * floatval($position['einzelpreis']);
            }

            // Rabatt abziehen
            if (!empty($data['rabatt_prozent'])) {
                $data['rabatt_betrag'] = $netto * floatval($data['rabatt_prozent']) / 100;
                $netto -= $data['rabatt_betrag'];
            }

            $data['netto'] = $netto;

            // MwSt berechnen
            if (!empty($data['mwst_satz'])) {
                $data['mwst_betrag'] = $netto * floatval($data['mwst_satz']) / 100;
            } else {
                // Standard MwSt aus Einstellungen
                $stmt = $this->db->prepare("SELECT value FROM einstellungen WHERE key = 'mwst_satz'");
                $stmt->execute();
                $result = $stmt->fetch();
                $data['mwst_satz'] = $result ? floatval($result['value']) : 19;
                $data['mwst_betrag'] = $netto * $data['mwst_satz'] / 100;
            }

            // Gesamtbetrag
            $data['gesamtbetrag'] = $netto + $data['mwst_betrag'];

            // Versandkosten hinzufügen
            if (!empty($data['versand_kosten'])) {
                $data['gesamtbetrag'] += floatval($data['versand_kosten']);
            }

            // Restbetrag = Gesamtbetrag (wird bei Anzahlung angepasst)
            if (!isset($data['restbetrag'])) {
                $data['restbetrag'] = $data['gesamtbetrag'];
            }

            // Skonto berechnen
            if (!empty($data['skonto_aktiv']) && !empty($data['skonto_prozent'])) {
                $data['skonto_betrag'] = $data['gesamtbetrag'] * floatval($data['skonto_prozent']) / 100;

                // Skonto-Fälligkeit (meist 10 Tage)
                if (empty($data['skonto_faellig_bis'])) {
                    $data['skonto_faellig_bis'] = date('Y-m-d', strtotime($data['datum'] . ' + 10 days'));
                }
            }
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

        // Positionen als JSON speichern
        if (isset($data['positionen']) && is_array($data['positionen'])) {
            $data['positionen'] = json_encode($data['positionen']);
        }

        // Beträge neu berechnen wenn Positionen geändert
        if (isset($data['positionen'])) {
            $this->calculateAmounts($data);
        }

        // Parent update aufrufen
        return parent::update($id, $data);
    }

    /**
     * Rechnung als bezahlt markieren
     */
    public function markAsPaid($id, $zahlungsart = 'Überweisung')
    {
        $data = [
            'bezahlt' => 1,
            'bezahlt_am' => date('Y-m-d'),
            'zahlungsart' => $zahlungsart,
            'status' => 'bezahlt'
        ];

        return $this->update($id, $data);
    }

    /**
     * Rechnung stornieren
     */
    public function stornieren($id, $grund = '')
    {
        $data = [
            'storniert' => 1,
            'storno_datum' => date('Y-m-d H:i:s'),
            'storno_grund' => $grund,
            'status' => 'storniert'
        ];

        return $this->update($id, $data);
    }

    /**
     * Anzahlung erfassen
     */
    public function addAnzahlung($id, $betrag, $datum = null)
    {
        try {
            // Rechnung laden
            $rechnung = $this->findById($id);
            if (!$rechnung) {
                return ['success' => false, 'error' => 'Rechnung nicht gefunden'];
            }

            $data = [
                'anzahlung_aktiv' => 1,
                'anzahlung_betrag' => $betrag,
                'anzahlung_datum' => $datum ?: date('Y-m-d'),
                'restbetrag' => $rechnung['gesamtbetrag'] - $betrag
            ];

            // Wenn komplett bezahlt
            if ($data['restbetrag'] <= 0) {
                $data['bezahlt'] = 1;
                $data['bezahlt_am'] = $data['anzahlung_datum'];
                $data['status'] = 'bezahlt';
            } else {
                $data['status'] = 'teilbezahlt';
            }

            return $this->update($id, $data);
        } catch (PDOException $e) {
            error_log("addAnzahlung Fehler: " . $e->getMessage());
            return ['success' => false, 'error' => 'Datenbankfehler'];
        }
    }

    /**
     * Mahnung erstellen
     */
    public function createMahnung($id)
    {
        try {
            // Rechnung laden
            $rechnung = $this->findById($id);
            if (!$rechnung) {
                return ['success' => false, 'error' => 'Rechnung nicht gefunden'];
            }

            if ($rechnung['bezahlt']) {
                return ['success' => false, 'error' => 'Rechnung bereits bezahlt'];
            }

            $mahnstufe = intval($rechnung['mahnstufe']) + 1;

            $data = [
                'mahnstufe' => $mahnstufe,
                'gemahnt_am' => date('Y-m-d'),
                'status' => 'gemahnt'
            ];

            return $this->update($id, $data);
        } catch (PDOException $e) {
            error_log("createMahnung Fehler: " . $e->getMessage());
            return ['success' => false, 'error' => 'Datenbankfehler'];
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
            $where = "WHERE storniert = 0";
            $params = [];

            if ($monat && $jahr) {
                $where .= " AND MONTH(datum) = ? AND YEAR(datum) = ?";
                $params[] = $monat;
                $params[] = $jahr;
            } elseif ($jahr) {
                $where .= " AND YEAR(datum) = ?";
                $params[] = $jahr;
            }

            // Gesamt-Umsatz
            $sql = "SELECT 
                        COUNT(*) as anzahl,
                        SUM(gesamtbetrag) as umsatz,
                        SUM(CASE WHEN bezahlt = 1 THEN gesamtbetrag ELSE 0 END) as bezahlt,
                        SUM(CASE WHEN bezahlt = 0 THEN gesamtbetrag ELSE 0 END) as offen
                    FROM {$this->table} 
                    $where";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $stats = $stmt->fetch();

            // Durchschnittswerte
            $stats['durchschnitt'] = $stats['anzahl'] > 0
                ? round($stats['umsatz'] / $stats['anzahl'], 2)
                : 0;

            return $stats;
        } catch (PDOException $e) {
            error_log("getStatistiken Fehler: " . $e->getMessage());
            return [];
        }
    }
}
