<?php

/**
 * KFZ Fac Pro - Users Management API
 * Benutzerverwaltung für Admins
 */

// Headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Bei OPTIONS-Request direkt beenden
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Includes
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/models/User.php';

// Auth-Check - Admin erforderlich
Auth::requireAdmin();

// Model initialisieren
$userModel = new User();

// Request-Methode und Pfad ermitteln
$method = $_SERVER['REQUEST_METHOD'];
$pathInfo = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
$segments = array_filter(explode('/', $pathInfo));
$userId = isset($segments[1]) ? intval($segments[1]) : null;
$action = isset($segments[2]) ? $segments[2] : null;

// Input-Daten
$input = json_decode(file_get_contents('php://input'), true);

// Router
try {
    switch ($method) {
        case 'GET':
            if ($userId) {
                // GET /api/auth/users/{id}
                $user = $userModel->findById($userId);

                if ($user) {
                    // Sensible Daten entfernen
                    unset($user['password_hash']);
                    unset($user['password_reset_token']);
                    unset($user['api_token']);

                    echo json_encode($user);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Benutzer nicht gefunden']);
                }
            } else {
                // GET /api/auth/users - Alle Benutzer
                $users = $userModel->findAll('username');

                // Sensible Daten entfernen
                foreach ($users as &$user) {
                    unset($user['password_hash']);
                    unset($user['password_reset_token']);
                    unset($user['api_token']);
                }

                echo json_encode($users);
            }
            break;

        case 'POST':
            if ($userId && $action) {
                // Benutzer-Aktionen
                if ($action === 'activate') {
                    // POST /api/auth/users/{id}/activate
                    $result = $userModel->setActive($userId, true);
                    echo json_encode($result);
                } elseif ($action === 'deactivate') {
                    // POST /api/auth/users/{id}/deactivate
                    // Verhindere Deaktivierung des eigenen Accounts
                    if ($userId == $_SESSION['user_id']) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Sie können sich nicht selbst deaktivieren']);
                        break;
                    }

                    $result = $userModel->setActive($userId, false);
                    echo json_encode($result);
                } elseif ($action === 'reset-password') {
                    // POST /api/auth/users/{id}/reset-password
                    if (empty($input['password'])) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Neues Passwort fehlt']);
                        break;
                    }

                    $result = $userModel->update($userId, [
                        'password' => $input['password']
                    ]);
                    echo json_encode($result);
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'Unbekannte Aktion']);
                }
            } else {
                // POST /api/auth/users - Neuen Benutzer erstellen
                if (empty($input['username']) || empty($input['password'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Benutzername und Passwort erforderlich']);
                    break;
                }

                $result = $userModel->create($input);

                if ($result['success']) {
                    http_response_code(201);

                    // Neuen Benutzer laden und zurückgeben
                    $newUser = $userModel->findById($result['id']);
                    unset($newUser['password_hash']);
                    unset($newUser['password_reset_token']);
                    unset($newUser['api_token']);

                    echo json_encode([
                        'success' => true,
                        'user' => $newUser
                    ]);
                } else {
                    http_response_code(400);
                    echo json_encode($result);
                }
            }
            break;

        case 'DELETE':
            // DELETE /api/auth/users/{id}
            if (!$userId) {
                http_response_code(400);
                echo json_encode(['error' => 'Benutzer-ID fehlt']);
                break;
            }

            // Verhindere Löschung des eigenen Accounts
            if ($userId == $_SESSION['user_id']) {
                http_response_code(400);
                echo json_encode(['error' => 'Sie können sich nicht selbst löschen']);
                break;
            }

            $result = $userModel->delete($userId);

            if ($result['success']) {
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode($result);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Methode nicht erlaubt']);
            break;
    }
} catch (Exception $e) {
    error_log("Users API Fehler: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Interner Serverfehler']);
}
