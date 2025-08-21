<?php

header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/logger.php';
require_once '../includes/auth.php';
require_once '../models/Customer.php';

Auth::init();
Auth::requireLogin();

$method = $_SERVER['REQUEST_METHOD'];
$customer = new Customer();

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                $result = $customer->getById($_GET['id']);
            } elseif (isset($_GET['search'])) {
                $result = $customer->search($_GET['search']);
            } else {
                $limit = $_GET['limit'] ?? 100;
                $offset = $_GET['offset'] ?? 0;
                $result = $customer->getAll($limit, $offset);
            }
            echo json_encode(['success' => true, 'data' => $result]);
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $customer->create($data);
            if ($id) {
                echo json_encode(['success' => true, 'id' => $id]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to create customer']);
            }
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $_GET['id'] ?? null;
            if ($id && $customer->update($id, $data)) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update customer']);
            }
            break;

        case 'DELETE':
            $id = $_GET['id'] ?? null;
            if ($id && $customer->delete($id)) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete customer']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    Logger::error("API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
