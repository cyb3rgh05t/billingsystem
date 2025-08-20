<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    echo json_encode([
        'logged_in' => true,
        'user' => [
            'id' => $_SESSION['user_id'] ?? 1,
            'username' => $_SESSION['username'] ?? 'admin',
            'role' => $_SESSION['role'] ?? 'admin'
        ]
    ]);
} else {
    echo json_encode([
        'logged_in' => false,
        'user' => null
    ]);
}
