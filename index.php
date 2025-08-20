<?php

/**
 * KFZ Fac Pro - PHP Version
 * Haupt-Router mit korrektem public-Ordner Handling
 */

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1); // Für Debugging, später auf 0 setzen
ini_set('log_errors', 1);

// Log-Verzeichnis erstellen falls nicht vorhanden
$logDir = __DIR__ . '/logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}
ini_set('error_log', $logDir . '/error.log');

// Zeitzone
date_default_timezone_set('Europe/Berlin');

// Request-URI bereinigen
$requestUri = $_SERVER['REQUEST_URI'];
$requestUri = strtok($requestUri, '?'); // Query-String entfernen
$requestUri = urldecode($requestUri);

// Basis-Pfad ermitteln (falls in Unterverzeichnis installiert)
$scriptName = $_SERVER['SCRIPT_NAME'];
$basePath = dirname($scriptName);

// Basis-Pfad vom Request-URI entfernen
if ($basePath !== '/' && strpos($requestUri, $basePath) === 0) {
    $requestUri = substr($requestUri, strlen($basePath));
}

// Führenden Slash entfernen für einfacheres Routing
$requestUri = ltrim($requestUri, '/');

// Debug (später entfernen)
error_log("Request URI: " . $requestUri);

// ============================================
// STATISCHE DATEIEN (CSS, JS, Bilder, etc.)
// ============================================
// Prüfen ob es eine statische Datei ist (hat eine Extension)
if (preg_match('/\.(css|js|png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf|pdf|json)$/i', $requestUri)) {
    // Datei im public-Ordner suchen
    $publicFile = __DIR__ . '/public/' . $requestUri;

    // Debug
    error_log("Looking for static file: " . $publicFile);

    if (file_exists($publicFile) && is_file($publicFile)) {
        // MIME-Types
        $mimeTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'pdf' => 'application/pdf'
        ];

        $ext = strtolower(pathinfo($publicFile, PATHINFO_EXTENSION));
        $mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';

        // Headers senden
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($publicFile));

        // Cache-Header für statische Dateien
        if (in_array($ext, ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'woff', 'woff2', 'ttf'])) {
            header('Cache-Control: public, max-age=3600');
        }

        // Datei ausgeben
        readfile($publicFile);
        exit;
    } else {
        // 404 für nicht gefundene statische Dateien
        http_response_code(404);
        error_log("Static file not found: " . $publicFile);
        exit;
    }
}

// ============================================
// API-ROUTEN
// ============================================
if (strpos($requestUri, 'api/') === 0) {
    // API-Pfad extrahieren
    $apiPath = substr($requestUri, 4); // "api/" entfernen
    $apiSegments = explode('/', $apiPath);

    // CORS-Headers für API
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');

    // Bei OPTIONS-Request direkt beenden
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    // API-Datei bestimmen
    $apiFile = null;
    $pathInfo = '';

    // Spezielle Routen für auth
    if ($apiSegments[0] === 'auth' && isset($apiSegments[1])) {
        $apiFile = __DIR__ . '/api/auth/' . $apiSegments[1] . '.php';
        if (isset($apiSegments[2])) {
            $pathInfo = '/' . implode('/', array_slice($apiSegments, 2));
        }
    }
    // Standard API-Routen
    else {
        $apiFile = __DIR__ . '/api/' . $apiSegments[0] . '.php';
        if (isset($apiSegments[1])) {
            $pathInfo = '/' . implode('/', array_slice($apiSegments, 1));
        }
    }

    // PATH_INFO setzen für die API
    $_SERVER['PATH_INFO'] = $pathInfo;

    // Debug
    error_log("API File: " . $apiFile);
    error_log("PATH_INFO: " . $pathInfo);

    // API-Datei laden
    if ($apiFile && file_exists($apiFile)) {
        require_once $apiFile;
        exit;
    } else {
        // 404 für nicht gefundene API
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'API-Endpunkt nicht gefunden',
            'endpoint' => $apiPath,
            'method' => $_SERVER['REQUEST_METHOD']
        ]);
        exit;
    }
}

// ============================================
// HTML-SEITEN ROUTING
// ============================================
$route = empty($requestUri) ? 'index' : explode('/', $requestUri)[0];

switch ($route) {
    case 'index':
    case '':
        // Prüfen ob eingeloggt
        session_start();
        if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
            header('Location: /login');
            exit;
        }

        // Index.html ausgeben
        $indexFile = __DIR__ . '/public/index.html';
        if (file_exists($indexFile)) {
            readfile($indexFile);
        } else {
            echo '<h1>index.html nicht gefunden</h1>';
            echo '<p>Bitte kopieren Sie die public-Dateien aus Ihrer Node.js-Installation nach: ' . $indexFile . '</p>';
        }
        break;

    case 'login':
        $loginFile = __DIR__ . '/public/login.html';
        if (file_exists($loginFile)) {
            readfile($loginFile);
        } else {
            // Fallback Login-Seite mit korrekten Pfaden
            echo '<!DOCTYPE html>
            <html lang="de">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Login - KFZ Fac Pro</title>
                <style>
                    body { 
                        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                        display: flex; 
                        justify-content: center; 
                        align-items: center; 
                        height: 100vh; 
                        margin: 0; 
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                    }
                    .login-box { 
                        background: white; 
                        padding: 40px; 
                        border-radius: 10px; 
                        box-shadow: 0 20px 60px rgba(0,0,0,0.3); 
                        width: 100%;
                        max-width: 400px;
                    }
                    h1 { 
                        margin-top: 0; 
                        color: #333; 
                        text-align: center;
                        font-size: 32px;
                    }
                    h2 {
                        color: #666;
                        text-align: center;
                        font-size: 18px;
                        font-weight: normal;
                        margin-bottom: 30px;
                    }
                    input { 
                        width: 100%; 
                        padding: 12px; 
                        margin: 10px 0; 
                        border: 1px solid #ddd; 
                        border-radius: 5px;
                        font-size: 16px;
                        box-sizing: border-box;
                    }
                    input:focus {
                        outline: none;
                        border-color: #667eea;
                        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
                    }
                    button { 
                        width: 100%; 
                        padding: 14px; 
                        background: #667eea; 
                        color: white; 
                        border: none; 
                        border-radius: 5px; 
                        cursor: pointer; 
                        font-size: 16px;
                        font-weight: 600;
                        margin-top: 10px;
                        transition: all 0.3s ease;
                    }
                    button:hover { 
                        background: #5a67d8;
                        transform: translateY(-2px);
                        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
                    }
                    .error { 
                        color: #e74c3c; 
                        margin-top: 15px;
                        text-align: center;
                        padding: 10px;
                        background: #fee;
                        border-radius: 5px;
                        display: none;
                    }
                    .error.show {
                        display: block;
                    }
                    .loading {
                        display: none;
                        text-align: center;
                        margin-top: 10px;
                    }
                    .loading.show {
                        display: block;
                    }
                </style>
            </head>
            <body>
                <div class="login-box">
                    <h1>🔧 KFZ Fac Pro</h1>
                    <h2>Bitte melden Sie sich an</h2>
                    <form id="loginForm">
                        <input type="text" id="username" placeholder="Benutzername" value="admin" required>
                        <input type="password" id="password" placeholder="Passwort" value="admin123" required>
                        <button type="submit">Anmelden</button>
                        <div id="error" class="error"></div>
                        <div id="loading" class="loading">⏳ Anmeldung läuft...</div>
                    </form>
                </div>
                <script>
                    document.getElementById("loginForm").addEventListener("submit", async (e) => {
                        e.preventDefault();
                        
                        const errorDiv = document.getElementById("error");
                        const loadingDiv = document.getElementById("loading");
                        const username = document.getElementById("username").value;
                        const password = document.getElementById("password").value;
                        
                        // UI-Feedback
                        errorDiv.classList.remove("show");
                        loadingDiv.classList.add("show");
                        
                        try {
                            const response = await fetch("/api/auth/login", {
                                method: "POST",
                                headers: { "Content-Type": "application/json" },
                                body: JSON.stringify({ username, password })
                            });
                            
                            const data = await response.json();
                            
                            if (data.success) {
                                window.location.href = "/";
                            } else {
                                errorDiv.textContent = data.error || "Login fehlgeschlagen";
                                errorDiv.classList.add("show");
                            }
                        } catch (error) {
                            errorDiv.textContent = "Verbindungsfehler: " + error.message;
                            errorDiv.classList.add("show");
                        } finally {
                            loadingDiv.classList.remove("show");
                        }
                    });
                </script>
            </body>
            </html>';
        }
        break;

    case 'logout':
        session_start();
        session_destroy();
        header('Location: /login');
        break;

    case 'setup':
        require_once __DIR__ . '/setup.php';
        break;

    case 'profile':
        session_start();
        if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
            header('Location: /login');
            exit;
        }

        $profileFile = __DIR__ . '/public/profile.html';
        if (file_exists($profileFile)) {
            readfile($profileFile);
        } else {
            echo '<h1>Profil</h1><p>profile.html nicht gefunden</p>';
        }
        break;

    // Spezialfall: Dateien direkt im Root (favicon.ico, robots.txt, etc.)
    case 'favicon.ico':
    case 'robots.txt':
    case 'sitemap.xml':
        $file = __DIR__ . '/public/' . $route;
        if (file_exists($file)) {
            readfile($file);
        } else {
            http_response_code(404);
        }
        break;

    default:
        // 404 für unbekannte Routen
        http_response_code(404);
        echo '<!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="UTF-8">
            <title>404 - Seite nicht gefunden</title>
            <style>
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                    text-align: center; 
                    padding: 50px; 
                    background: #f5f5f5; 
                }
                h1 { color: #e74c3c; font-size: 48px; }
                p { color: #666; font-size: 18px; }
                a { color: #3498db; text-decoration: none; }
                a:hover { text-decoration: underline; }
                .debug { 
                    background: white; 
                    padding: 20px; 
                    margin: 30px auto; 
                    border-radius: 5px; 
                    text-align: left;
                    max-width: 600px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                .debug h3 { color: #333; }
                .debug pre { 
                    background: #f0f0f0; 
                    padding: 10px; 
                    overflow-x: auto;
                    border-radius: 3px;
                }
            </style>
        </head>
        <body>
            <h1>404</h1>
            <p>Die angeforderte Seite wurde nicht gefunden.</p>
            <p><a href="/">Zurück zur Startseite</a> | <a href="/login">Zum Login</a></p>
            
            <div class="debug">
                <h3>Debug-Information:</h3>
                <pre>
Angeforderte URL: ' . htmlspecialchars($requestUri) . '
Methode: ' . $_SERVER['REQUEST_METHOD'] . '
Script: ' . $_SERVER['SCRIPT_NAME'] . '
                </pre>
                <p><strong>Hinweis:</strong> Stellen Sie sicher, dass alle Dateien im public-Ordner liegen.</p>
            </div>
        </body>
        </html>';
}
