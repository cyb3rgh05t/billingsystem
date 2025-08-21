<?php

class Customer
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function create($data)
    {
        try {
            $sql = "INSERT INTO customers (
company_name, first_name, last_name, email, phone,
street, house_number, postal_code, city, country,
tax_id, notes, created_by
) VALUES (
:company_name, :first_name, :last_name, :email, :phone,
:street, :house_number, :postal_code, :city, :country,
:tax_id, :notes, :created_by
)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':company_name' => $data['company_name'] ?? null,
                ':first_name' => $data['first_name'],
                ':last_name' => $data['last_name'],
                ':email' => $data['email'],
                ':phone' => $data['phone'],
                ':street' => $data['street'],
                ':house_number' => $data['house_number'],
                ':postal_code' => $data['postal_code'],
                ':city' => $data['city'],
                ':country' => $data['country'] ?? 'Deutschland',
                ':tax_id' => $data['tax_id'] ?? null,
                ':notes' => $data['notes'] ?? null,
                ':created_by' => $_SESSION['user_id']
            ]);

            $customerId = $this->db->lastInsertId();
            Logger::success("Customer created", ['customer_id' => $customerId]);

            return $customerId;
        } catch (PDOException $e) {
            Logger::error("Failed to create customer: " . $e->getMessage());
            return false;
        }
    }

    public function getAll($limit = 100, $offset = 0)
    {
        $sql = "SELECT * FROM customers ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function update($id, $data)
    {
        try {
            $fields = [];
            $values = [];

            foreach ($data as $key => $value) {
                if ($key !== 'id') {
                    $fields[] = "{$key} = :{$key}";
                    $values[":{$key}"] = $value;
                }
            }

            $values[':id'] = $id;
            $sql = "UPDATE customers SET " . implode(', ', $fields) . " WHERE id = :id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);

            Logger::info("Customer updated", ['customer_id' => $id]);
            return true;
        } catch (PDOException $e) {
            Logger::error("Failed to update customer: " . $e->getMessage());
            return false;
        }
    }

    public function delete($id)
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM customers WHERE id = ?");
            $stmt->execute([$id]);

            Logger::warning("Customer deleted", ['customer_id' => $id]);
            return true;
        } catch (PDOException $e) {
            Logger::error("Failed to delete customer: " . $e->getMessage());
            return false;
        }
    }

    public function search($searchTerm)
    {
        $searchTerm = "%{$searchTerm}%";
        $sql = "SELECT * FROM customers
WHERE company_name LIKE :term
OR first_name LIKE :term
OR last_name LIKE :term
OR email LIKE :term
ORDER BY created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':term' => $searchTerm]);

        return $stmt->fetchAll();
    }
}
