<?php

/**
 * KFZ Fac Pro - Basis Model
 * Abstrakte Klasse für alle Models
 */

abstract class Model
{
    protected $db;
    protected $table;
    protected $primaryKey = 'id';
    protected $fillable = [];
    protected $timestamps = true;

    public function __construct()
    {
        require_once dirname(__DIR__) . '/config/database.php';
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Alle Datensätze abrufen
     */
    public function findAll($orderBy = null, $limit = null)
    {
        $sql = "SELECT * FROM {$this->table}";

        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }

        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }

        try {
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("findAll Fehler: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Einzelnen Datensatz nach ID finden
     */
    public function findById($id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("findById Fehler: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Datensätze nach Bedingung finden
     */
    public function findWhere($conditions, $orderBy = null, $limit = null)
    {
        $sql = "SELECT * FROM {$this->table} WHERE ";
        $params = [];
        $whereClauses = [];

        foreach ($conditions as $field => $value) {
            if ($value === null) {
                $whereClauses[] = "{$field} IS NULL";
            } else {
                $whereClauses[] = "{$field} = ?";
                $params[] = $value;
            }
        }

        $sql .= implode(' AND ', $whereClauses);

        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }

        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("findWhere Fehler: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Neuen Datensatz erstellen
     */
    public function create($data)
    {
        // Nur erlaubte Felder verwenden
        $filteredData = $this->filterFillable($data);

        // Timestamps hinzufügen
        if ($this->timestamps) {
            $filteredData['erstellt_am'] = date('Y-m-d H:i:s');
            $filteredData['aktualisiert_am'] = date('Y-m-d H:i:s');
        }

        $fields = array_keys($filteredData);
        $placeholders = array_fill(0, count($fields), '?');
        $values = array_values($filteredData);

        $sql = "INSERT INTO {$this->table} (" . implode(', ', $fields) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);

            return [
                'success' => true,
                'id' => $this->db->lastInsertId(),
                'data' => array_merge($filteredData, ['id' => $this->db->lastInsertId()])
            ];
        } catch (PDOException $e) {
            error_log("create Fehler: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $this->getErrorMessage($e)
            ];
        }
    }

    /**
     * Datensatz aktualisieren
     */
    public function update($id, $data)
    {
        // Nur erlaubte Felder verwenden
        $filteredData = $this->filterFillable($data);

        // Timestamp aktualisieren
        if ($this->timestamps) {
            $filteredData['aktualisiert_am'] = date('Y-m-d H:i:s');
        }

        $setClauses = [];
        $values = [];

        foreach ($filteredData as $field => $value) {
            $setClauses[] = "{$field} = ?";
            $values[] = $value;
        }

        $values[] = $id;

        $sql = "UPDATE {$this->table} 
                SET " . implode(', ', $setClauses) . " 
                WHERE {$this->primaryKey} = ?";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);

            return [
                'success' => true,
                'changes' => $stmt->rowCount()
            ];
        } catch (PDOException $e) {
            error_log("update Fehler: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $this->getErrorMessage($e)
            ];
        }
    }

    /**
     * Datensatz löschen
     */
    public function delete($id)
    {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);

            return [
                'success' => true,
                'changes' => $stmt->rowCount()
            ];
        } catch (PDOException $e) {
            error_log("delete Fehler: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $this->getErrorMessage($e)
            ];
        }
    }

    /**
     * Anzahl der Datensätze
     */
    public function count($conditions = [])
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        $params = [];

        if (!empty($conditions)) {
            $whereClauses = [];
            foreach ($conditions as $field => $value) {
                if ($value === null) {
                    $whereClauses[] = "{$field} IS NULL";
                } else {
                    $whereClauses[] = "{$field} = ?";
                    $params[] = $value;
                }
            }
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            return $result['count'];
        } catch (PDOException $e) {
            error_log("count Fehler: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Existiert ein Datensatz?
     */
    public function exists($id)
    {
        $sql = "SELECT 1 FROM {$this->table} WHERE {$this->primaryKey} = ? LIMIT 1";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            error_log("exists Fehler: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Filtert nur erlaubte Felder
     */
    protected function filterFillable($data)
    {
        if (empty($this->fillable)) {
            return $data;
        }

        return array_intersect_key($data, array_flip($this->fillable));
    }

    /**
     * Benutzerfreundliche Fehlermeldung
     */
    protected function getErrorMessage($exception)
    {
        $message = $exception->getMessage();

        if (strpos($message, 'UNIQUE') !== false) {
            return 'Datensatz mit diesem Wert existiert bereits';
        }

        if (strpos($message, 'FOREIGN KEY') !== false) {
            return 'Verknüpfte Daten verhindern diese Aktion';
        }

        if (strpos($message, 'NOT NULL') !== false) {
            return 'Pflichtfeld fehlt';
        }

        return 'Datenbankfehler aufgetreten';
    }

    /**
     * Rohe SQL-Abfrage ausführen
     */
    public function rawQuery($sql, $params = [])
    {
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("rawQuery Fehler: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Transaktion starten
     */
    public function beginTransaction()
    {
        return $this->db->beginTransaction();
    }

    /**
     * Transaktion bestätigen
     */
    public function commit()
    {
        return $this->db->commit();
    }

    /**
     * Transaktion zurückrollen
     */
    public function rollback()
    {
        return $this->db->rollBack();
    }
}
