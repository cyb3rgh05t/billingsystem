<?php

/**
 * KFZ Fac Pro - Kunden Model
 */

require_once 'Model.php';

class Kunde extends Model
{
    protected $table = 'kunden';
    protected $primaryKey = 'id';
    protected $fillable = [
        'kunden_nr',
        'name',
        'strasse',
        'plz',
        'ort',
        'telefon',
        'email'
    ];

    /**
     * Generiert neue Kundennummer
     */
    public function generateKundenNr()
    {
        $year = date('Y');
        $prefix = 'K' . $year . '-';

        // Höchste Nummer des Jahres finden
        $sql = "SELECT kunden_nr FROM {$this->table} 
                WHERE kunden_nr LIKE ? 
                ORDER BY kunden_nr DESC 
                LIMIT 1";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$prefix . '%']);
            $result = $stmt->fetch();

            if ($result) {
                // Nummer extrahieren und erhöhen
                $lastNr = str_replace($prefix, '', $result['kunden_nr']);
                $newNr = intval($lastNr) + 1;
            } else {
                $newNr = 1;
            }

            return $prefix . str_pad($newNr, 4, '0', STR_PAD_LEFT);
        } catch (PDOException $e) {
            error_log("generateKundenNr Fehler: " . $e->getMessage());
            return $prefix . '0001';
        }
    }

    /**
     * Kunde mit allen Fahrzeugen abrufen
     */
    public function findWithFahrzeuge($id)
    {
        $kunde = $this->findById($id);

        if (!$kunde) {
            return null;
        }

        // Fahrzeuge laden
        $sql = "SELECT * FROM fahrzeuge WHERE kunden_id = ? ORDER BY kennzeichen";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $kunde['fahrzeuge'] = $stmt->fetchAll();
        } catch (PDOException $e) {
            $kunde['fahrzeuge'] = [];
        }

        return $kunde;
    }

    /**
     * Kunde mit Statistiken abrufen
     */
    public function findWithStats($id)
    {
        $kunde = $this->findById($id);

        if (!$kunde) {
            return null;
        }

        // Statistiken sammeln
        try {
            // Anzahl Fahrzeuge
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM fahrzeuge WHERE kunden_id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch();
            $kunde['stats']['fahrzeuge'] = $result['count'];

            // Anzahl Aufträge
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM auftraege WHERE kunden_id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch();
            $kunde['stats']['auftraege'] = $result['count'];

            // Anzahl Rechnungen
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM rechnungen WHERE kunden_id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch();
            $kunde['stats']['rechnungen'] = $result['count'];

            // Gesamtumsatz
            $stmt = $this->db->prepare("
                SELECT SUM(gesamtbetrag) as total 
                FROM rechnungen 
                WHERE kunden_id = ? AND status = 'bezahlt'
            ");
            $stmt->execute([$id]);
            $result = $stmt->fetch();
            $kunde['stats']['umsatz'] = $result['total'] ?: 0;
        } catch (PDOException $e) {
            error_log("findWithStats Fehler: " . $e->getMessage());
            $kunde['stats'] = [
                'fahrzeuge' => 0,
                'auftraege' => 0,
                'rechnungen' => 0,
                'umsatz' => 0
            ];
        }

        return $kunde;
    }

    /**
     * Suche nach Kunden
     */
    public function search($query)
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE name LIKE ? 
                   OR kunden_nr LIKE ? 
                   OR email LIKE ? 
                   OR telefon LIKE ?
                ORDER BY name";

        $searchTerm = '%' . $query . '%';

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("search Fehler: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Überschriebene create-Methode mit Auto-Kundennummer
     */
    public function create($data)
    {
        // Kundennummer generieren falls nicht vorhanden
        if (empty($data['kunden_nr'])) {
            $data['kunden_nr'] = $this->generateKundenNr();
        }

        return parent::create($data);
    }

    /**
     * Kunde löschen mit Abhängigkeiten prüfen
     */
    public function delete($id)
    {
        // Prüfe ob Kunde Aufträge oder Rechnungen hat
        $hasAuftraege = $this->count(['kunden_id' => $id]);

        if ($hasAuftraege > 0) {
            return [
                'success' => false,
                'error' => 'Kunde kann nicht gelöscht werden - es existieren noch Aufträge'
            ];
        }

        // Fahrzeuge werden durch CASCADE automatisch gelöscht
        return parent::delete($id);
    }

    /**
     * Top-Kunden nach Umsatz
     */
    public function getTopKunden($limit = 10)
    {
        $sql = "SELECT 
                    k.*,
                    COUNT(DISTINCT r.id) as anzahl_rechnungen,
                    SUM(r.gesamtbetrag) as gesamt_umsatz
                FROM {$this->table} k
                LEFT JOIN rechnungen r ON k.id = r.kunden_id AND r.status = 'bezahlt'
                GROUP BY k.id
                HAVING gesamt_umsatz > 0
                ORDER BY gesamt_umsatz DESC
                LIMIT ?";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getTopKunden Fehler: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Exportiere Kunden als Array
     */
    public function export()
    {
        $sql = "SELECT 
                    k.*,
                    COUNT(DISTINCT f.id) as anzahl_fahrzeuge,
                    COUNT(DISTINCT a.id) as anzahl_auftraege,
                    COUNT(DISTINCT r.id) as anzahl_rechnungen
                FROM {$this->table} k
                LEFT JOIN fahrzeuge f ON k.id = f.kunden_id
                LEFT JOIN auftraege a ON k.id = a.kunden_id
                LEFT JOIN rechnungen r ON k.id = r.kunden_id
                GROUP BY k.id
                ORDER BY k.name";

        try {
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("export Fehler: " . $e->getMessage());
            return [];
        }
    }
}
