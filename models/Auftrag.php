<?php

/**
 * KFZ Fac Pro - Auftrag Model
 */

require_once 'Model.php';

class Auftrag extends Model
{
    protected $table = 'auftraege';
    protected $primaryKey = 'id';
    protected $fillable = [
        'auftrag_nr',
        'kunden_id',
        'fahrzeug_id',
        'datum',
        'status',
        'basis_stundenpreis',
        'gesamt_zeit',
        'gesamt_kosten',
        'arbeitszeiten_kosten',
        'mwst_betrag',
        'anfahrt_aktiv',
        'express_aktiv',
        'wochenend_aktiv',
        'anfahrt_betrag',
        'express_betrag',
        'wochenend_betrag',
        'bemerkungen'
    ];

    /**
     * Generiert neue Auftragsnummer
     */
    public function generateAuftragNr()
    {
        $year = date('Y');
        $prefix = 'A' . $year . '-';

        $sql = "SELECT auftrag_nr FROM {$this->table} 
                WHERE auftrag_nr LIKE ? 
                ORDER BY auftrag_nr DESC 
                LIMIT 1";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$prefix . '%']);
            $result = $stmt->fetch();

            if ($result) {
                $lastNr = str_replace($prefix, '', $result['auftrag_nr']);
                $newNr = intval($lastNr) + 1;
            } else {
                $newNr = 1;
            }

            return $prefix . str_pad($newNr, 4, '0', STR_PAD_LEFT);
        } catch (PDOException $e) {
            error_log("generateAuftragNr Fehler: " . $e->getMessage());
            return $prefix . '0001';
        }
    }

    /**
     * Auftrag mit allen Details laden
     */
    public function findWithDetails($id)
    {
        $sql = "SELECT 
                    a.*,
                    k.name as kunde_name,
                    k.kunden_nr,
                    k.strasse,
                    k.plz,
                    k.ort,
                    k.telefon,
                    k.email,
                    f.kennzeichen,
                    f.marke,
                    f.modell,
                    f.vin,
                    f.farbe,
                    f.farbcode,
                    f.baujahr
                FROM {$this->table} a
                LEFT JOIN kunden k ON a.kunden_id = k.id
                LEFT JOIN fahrzeuge f ON a.fahrzeug_id = f.id
                WHERE a.id = ?";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $auftrag = $stmt->fetch();

            if ($auftrag) {
                // Positionen laden
                $auftrag['positionen'] = $this->getPositionen($id);
            }

            return $auftrag;
        } catch (PDOException $e) {
            error_log("findWithDetails Fehler: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Alle Aufträge mit Basis-Details
     */
    public function findAllWithDetails($orderBy = 'a.datum DESC, a.auftrag_nr DESC')
    {
        $sql = "SELECT 
                    a.*,
                    k.name as kunde_name,
                    f.kennzeichen,
                    f.marke,
                    f.modell
                FROM {$this->table} a
                LEFT JOIN kunden k ON a.kunden_id = k.id
                LEFT JOIN fahrzeuge f ON a.fahrzeug_id = f.id
                ORDER BY {$orderBy}";

        try {
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("findAllWithDetails Fehler: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Auftragspositionen abrufen
     */
    public function getPositionen($auftragId)
    {
        $sql = "SELECT * FROM auftrag_positionen 
                WHERE auftrag_id = ? 
                ORDER BY reihenfolge";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$auftragId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getPositionen Fehler: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Auftragspositionen speichern
     */
    public function savePositionen($auftragId, $positionen)
    {
        try {
            $this->db->beginTransaction();

            // Alte Positionen löschen
            $deleteStmt = $this->db->prepare("DELETE FROM auftrag_positionen WHERE auftrag_id = ?");
            $deleteStmt->execute([$auftragId]);

            // Neue Positionen einfügen
            $insertStmt = $this->db->prepare("
                INSERT INTO auftrag_positionen 
                (auftrag_id, beschreibung, stundenpreis, zeit, einheit, kosten, mwst_satz, mwst_betrag, gesamt, reihenfolge) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($positionen as $index => $position) {
                $insertStmt->execute([
                    $auftragId,
                    $position['beschreibung'],
                    $position['stundenpreis'] ?? null,
                    $position['zeit'] ?? null,
                    $position['einheit'] ?? 'Std.',
                    $position['kosten'] ?? 0,
                    $position['mwst_satz'] ?? 19,
                    $position['mwst_betrag'] ?? 0,
                    $position['gesamt'] ?? 0,
                    $index + 1
                ]);
            }

            $this->db->commit();
            return ['success' => true];
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("savePositionen Fehler: " . $e->getMessage());
            return ['success' => false, 'error' => 'Fehler beim Speichern der Positionen'];
        }
    }

    /**
     * Auftrag erstellen mit Positionen
     */
    public function createWithPositionen($data, $positionen = [])
    {
        try {
            $this->db->beginTransaction();

            // Auftragsnummer generieren falls nicht vorhanden
            if (empty($data['auftrag_nr'])) {
                $data['auftrag_nr'] = $this->generateAuftragNr();
            }

            // Auftrag erstellen
            $result = $this->create($data);

            if (!$result['success']) {
                throw new Exception($result['error']);
            }

            $auftragId = $result['id'];

            // Positionen speichern
            if (!empty($positionen)) {
                $posResult = $this->savePositionen($auftragId, $positionen);
                if (!$posResult['success']) {
                    throw new Exception($posResult['error']);
                }
            }

            $this->db->commit();

            return [
                'success' => true,
                'id' => $auftragId,
                'auftrag_nr' => $data['auftrag_nr']
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("createWithPositionen Fehler: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Status aktualisieren
     */
    public function updateStatus($auftragId, $status)
    {
        $validStatuses = ['offen', 'in_bearbeitung', 'abgeschlossen', 'storniert'];

        if (!in_array($status, $validStatuses)) {
            return ['success' => false, 'error' => 'Ungültiger Status'];
        }

        $sql = "UPDATE {$this->table} 
                SET status = ?, aktualisiert_am = CURRENT_TIMESTAMP 
                WHERE id = ?";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$status, $auftragId]);
            return ['success' => true];
        } catch (PDOException $e) {
            error_log("updateStatus Fehler: " . $e->getMessage());
            return ['success' => false, 'error' => 'Fehler beim Status-Update'];
        }
    }

    /**
     * Offene Aufträge eines Kunden
     */
    public function getOffeneByKunde($kundenId)
    {
        return $this->findWhere(
            ['kunden_id' => $kundenId, 'status' => 'offen'],
            'datum DESC'
        );
    }

    /**
     * Aufträge nach Zeitraum
     */
    public function getByDateRange($startDate, $endDate)
    {
        $sql = "SELECT a.*, k.name as kunde_name, f.kennzeichen
                FROM {$this->table} a
                LEFT JOIN kunden k ON a.kunden_id = k.id
                LEFT JOIN fahrzeuge f ON a.fahrzeug_id = f.id
                WHERE a.datum BETWEEN ? AND ?
                ORDER BY a.datum DESC";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$startDate, $endDate]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getByDateRange Fehler: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Statistiken
     */
    public function getStatistiken($year = null)
    {
        if (!$year) {
            $year = date('Y');
        }

        $stats = [
            'gesamt' => 0,
            'offen' => 0,
            'abgeschlossen' => 0,
            'umsatz' => 0,
            'durchschnitt' => 0
        ];

        try {
            // Gesamt-Anzahl
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM {$this->table} 
                WHERE strftime('%Y', datum) = ?
            ");
            $stmt->execute([$year]);
            $result = $stmt->fetch();
            $stats['gesamt'] = $result['count'];

            // Nach Status
            $stmt = $this->db->prepare("
                SELECT status, COUNT(*) as count, SUM(gesamt_kosten) as summe
                FROM {$this->table}
                WHERE strftime('%Y', datum) = ?
                GROUP BY status
            ");
            $stmt->execute([$year]);

            while ($row = $stmt->fetch()) {
                if ($row['status'] === 'offen') {
                    $stats['offen'] = $row['count'];
                } elseif ($row['status'] === 'abgeschlossen') {
                    $stats['abgeschlossen'] = $row['count'];
                    $stats['umsatz'] = $row['summe'] ?: 0;
                }
            }

            // Durchschnitt
            if ($stats['abgeschlossen'] > 0) {
                $stats['durchschnitt'] = $stats['umsatz'] / $stats['abgeschlossen'];
            }
        } catch (PDOException $e) {
            error_log("getStatistiken Fehler: " . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Export-Funktion
     */
    public function export($year = null)
    {
        $sql = "SELECT 
                    a.*,
                    k.name as kunde_name,
                    k.kunden_nr,
                    f.kennzeichen,
                    f.marke,
                    f.modell
                FROM {$this->table} a
                LEFT JOIN kunden k ON a.kunden_id = k.id
                LEFT JOIN fahrzeuge f ON a.fahrzeug_id = f.id";

        if ($year) {
            $sql .= " WHERE strftime('%Y', a.datum) = ?";
        }

        $sql .= " ORDER BY a.datum DESC, a.auftrag_nr DESC";

        try {
            if ($year) {
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$year]);
            } else {
                $stmt = $this->db->query($sql);
            }
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("export Fehler: " . $e->getMessage());
            return [];
        }
    }
}
