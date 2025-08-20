<?php

/**
 * KFZ Fac Pro - Fahrzeug Model
 */

require_once 'Model.php';

class Fahrzeug extends Model
{
    protected $table = 'fahrzeuge';
    protected $primaryKey = 'id';
    protected $fillable = [
        'kunden_id',
        'kennzeichen',
        'marke',
        'modell',
        'vin',
        'baujahr',
        'farbe',
        'farbcode',
        'kilometerstand'
    ];

    /**
     * Fahrzeuge eines Kunden finden
     */
    public function findByKunde($kundenId)
    {
        return $this->findWhere(['kunden_id' => $kundenId], 'kennzeichen ASC');
    }

    /**
     * Fahrzeug mit Kunde laden
     */
    public function findWithKunde($id)
    {
        $sql = "SELECT f.*, k.name as kunde_name, k.kunden_nr
                FROM {$this->table} f
                LEFT JOIN kunden k ON f.kunden_id = k.id
                WHERE f.id = ?";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("findWithKunde Fehler: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Alle Fahrzeuge mit Kundeninfos
     */
    public function findAllWithKunden($orderBy = 'f.kennzeichen ASC')
    {
        $sql = "SELECT f.*, k.name as kunde_name, k.kunden_nr
                FROM {$this->table} f
                LEFT JOIN kunden k ON f.kunden_id = k.id
                ORDER BY {$orderBy}";

        try {
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("findAllWithKunden Fehler: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Fahrzeug nach Kennzeichen suchen
     */
    public function findByKennzeichen($kennzeichen)
    {
        $results = $this->findWhere(['kennzeichen' => $kennzeichen]);
        return !empty($results) ? $results[0] : null;
    }

    /**
     * Fahrzeug-Historie (Aufträge und Rechnungen)
     */
    public function getHistorie($fahrzeugId)
    {
        $historie = [
            'auftraege' => [],
            'rechnungen' => []
        ];

        try {
            // Aufträge laden
            $sql = "SELECT a.*, k.name as kunde_name
                    FROM auftraege a
                    LEFT JOIN kunden k ON a.kunden_id = k.id
                    WHERE a.fahrzeug_id = ?
                    ORDER BY a.datum DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$fahrzeugId]);
            $historie['auftraege'] = $stmt->fetchAll();

            // Rechnungen laden
            $sql = "SELECT r.*, k.name as kunde_name
                    FROM rechnungen r
                    LEFT JOIN kunden k ON r.kunden_id = k.id
                    WHERE r.fahrzeug_id = ?
                    ORDER BY r.datum DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$fahrzeugId]);
            $historie['rechnungen'] = $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getHistorie Fehler: " . $e->getMessage());
        }

        return $historie;
    }

    /**
     * Suche nach Fahrzeugen
     */
    public function search($query)
    {
        $sql = "SELECT f.*, k.name as kunde_name
                FROM {$this->table} f
                LEFT JOIN kunden k ON f.kunden_id = k.id
                WHERE f.kennzeichen LIKE ?
                   OR f.marke LIKE ?
                   OR f.modell LIKE ?
                   OR f.vin LIKE ?
                   OR k.name LIKE ?
                ORDER BY f.kennzeichen";

        $searchTerm = '%' . $query . '%';

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("search Fehler: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Fahrzeug-Statistiken
     */
    public function getStatistik($fahrzeugId)
    {
        $stats = [
            'anzahl_auftraege' => 0,
            'anzahl_rechnungen' => 0,
            'gesamt_kosten' => 0,
            'letzter_service' => null
        ];

        try {
            // Anzahl Aufträge
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM auftraege WHERE fahrzeug_id = ?");
            $stmt->execute([$fahrzeugId]);
            $result = $stmt->fetch();
            $stats['anzahl_auftraege'] = $result['count'];

            // Anzahl Rechnungen und Gesamtkosten
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count, SUM(gesamtbetrag) as total
                FROM rechnungen 
                WHERE fahrzeug_id = ? AND status = 'bezahlt'
            ");
            $stmt->execute([$fahrzeugId]);
            $result = $stmt->fetch();
            $stats['anzahl_rechnungen'] = $result['count'];
            $stats['gesamt_kosten'] = $result['total'] ?: 0;

            // Letzter Service
            $stmt = $this->db->prepare("
                SELECT datum 
                FROM auftraege 
                WHERE fahrzeug_id = ? 
                ORDER BY datum DESC 
                LIMIT 1
            ");
            $stmt->execute([$fahrzeugId]);
            $result = $stmt->fetch();
            if ($result) {
                $stats['letzter_service'] = $result['datum'];
            }
        } catch (PDOException $e) {
            error_log("getStatistik Fehler: " . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Kilometerstand aktualisieren
     */
    public function updateKilometerstand($fahrzeugId, $kilometerstand)
    {
        $sql = "UPDATE {$this->table} 
                SET kilometerstand = ?, aktualisiert_am = CURRENT_TIMESTAMP 
                WHERE id = ?";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$kilometerstand, $fahrzeugId]);
            return ['success' => true];
        } catch (PDOException $e) {
            error_log("updateKilometerstand Fehler: " . $e->getMessage());
            return ['success' => false, 'error' => 'Fehler beim Aktualisieren'];
        }
    }

    /**
     * Export-Funktion
     */
    public function export()
    {
        $sql = "SELECT 
                    f.*,
                    k.name as kunde_name,
                    k.kunden_nr,
                    COUNT(DISTINCT a.id) as anzahl_auftraege,
                    COUNT(DISTINCT r.id) as anzahl_rechnungen,
                    SUM(r.gesamtbetrag) as gesamt_umsatz
                FROM {$this->table} f
                LEFT JOIN kunden k ON f.kunden_id = k.id
                LEFT JOIN auftraege a ON f.id = a.fahrzeug_id
                LEFT JOIN rechnungen r ON f.id = r.fahrzeug_id AND r.status = 'bezahlt'
                GROUP BY f.id
                ORDER BY f.kennzeichen";

        try {
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("export Fehler: " . $e->getMessage());
            return [];
        }
    }
}
