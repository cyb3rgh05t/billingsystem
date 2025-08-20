<?php

/**
 * dev-router.php
 * Development-Router für PHP Built-in Server
 * 
 * Verwendung:
 * php -S localhost:8000 dev-router.php
 */

// Request-URI bereinigen
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Debug-Ausgabe
error_log("Requested: " . $uri);

// ============================================
// ROUTING-REGELN
// ============================================

// 1. Statische Dateien (CSS, JS, Bilder etc.)
// Diese liegen im public-Ordner, werden aber ohne /public/ aufgerufen
$staticExtensions = ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'pdf', 'json', 'xml', 'txt'];
$extension = strtolower(pathinfo($uri, PATHINFO_EXTENSION));

if (in_array($extension, $staticExtensions)) {
    // Datei im public-Ordner suchen
    $file = __DIR__ . '/public' . $uri;

    if (file_exists($file) && is_file($file)) {
        // MIME-Types setzen
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
            'pdf' => 'application/pdf',
            'xml' => 'application/xml',
            'txt' => 'text/plain'
        ];

        $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($file));

        // Cache für Development deaktivieren
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile($file);
        return;
    } else {
        // 404 für nicht gefundene statische Dateien
        http_response_code(404);
        echo "404 - Datei nicht gefunden: " . $uri;
        error_log("Datei nicht gefunden: " . $file);
        return;
    }
}

// 2. API-Routen
if (strpos($uri, '/api/') === 0) {
    // API-Pfad extrahieren
    $apiPath = substr($uri, 5); // "/api/" entfernen
    $apiSegments = explode('/', $apiPath);

    // CORS-Headers für API
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');

    // Bei OPTIONS-Request direkt beenden
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        return;
    }

    // API-Datei bestimmen
    $apiFile = null;

    // Auth-API
    if ($apiSegments[0] === 'auth' && isset($apiSegments[1])) {
        $apiFile = __DIR__ . '/api/auth/' . $apiSegments[1] . '.php';
        // PATH_INFO für die API setzen
        if (isset($apiSegments[2])) {
            $_SERVER['PATH_INFO'] = '/' . implode('/', array_slice($apiSegments, 2));
        }
    }
    // Andere APIs
    else {
        $apiFile = __DIR__ . '/api/' . $apiSegments[0] . '.php';
        // PATH_INFO für die API setzen
        if (isset($apiSegments[1])) {
            $_SERVER['PATH_INFO'] = '/' . implode('/', array_slice($apiSegments, 1));
        }
    }

    if ($apiFile && file_exists($apiFile)) {
        require $apiFile;
        return;
    } else {
        // 404 für nicht gefundene API
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'API-Endpunkt nicht gefunden',
            'endpoint' => $apiPath,
            'file' => $apiFile
        ]);
        return;
    }
}

// 3. HTML-Seiten
session_start();

switch ($uri) {
    case '/':
    case '/index':
        // Prüfen ob eingeloggt
        if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
            header('Location: /login');
            return;
        }

        $file = __DIR__ . '/public/index.html';
        if (file_exists($file)) {
            readfile($file);
        } else {
            echo '<h1>Willkommen bei KFZ Fac Pro</h1>';
            echo '<p>index.html wurde nicht gefunden. Bitte kopieren Sie die Dateien aus Ihrer Node.js-Installation.</p>';
            echo '<p>Erwartet bei: ' . $file . '</p>';
            echo '<p><a href="/logout">Logout</a></p>';
        }
        return;

    case '/login':
        $file = __DIR__ . '/public/login.html';
        if (file_exists($file)) {
            readfile($file);
        } else {
            // Fallback-Login-Seite
?>
            <!DOCTYPE html>
            <html lang="de">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Login - KFZ Fac Pro</title>
                <style>
                    * {
                        margin: 0;
                        padding: 0;
                        box-sizing: border-box;
                    }

                    body {
                        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        min-height: 100vh;
                        display: flex;
                        justify-content: center;
                        align-items: center;
                    }

                    .login-container {
                        background: white;
                        padding: 40px;
                        border-radius: 10px;
                        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                        width: 90%;
                        max-width: 400px;
                    }

                    h1 {
                        text-align: center;
                        color: #333;
                        margin-bottom: 10px;
                        font-size: 28px;
                    }

                    h2 {
                        text-align: center;
                        color: #666;
                        font-weight: normal;
                        font-size: 16px;
                        margin-bottom: 30px;
                    }

                    .form-group {
                        margin-bottom: 20px;
                    }

                    label {
                        display: block;
                        margin-bottom: 5px;
                        color: #555;
                        font-weight: 500;
                    }

                    input {
                        width: 100%;
                        padding: 12px;
                        border: 1px solid #ddd;
                        border-radius: 5px;
                        font-size: 16px;
                        transition: all 0.3s;
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
                        font-size: 16px;
                        font-weight: 600;
                        cursor: pointer;
                        transition: all 0.3s;
                    }

                    button:hover {
                        background: #5a67d8;
                        transform: translateY(-2px);
                        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
                    }

                    button:disabled {
                        opacity: 0.6;
                        cursor: not-allowed;
                    }

                    .error {
                        background: #fee;
                        color: #c53030;
                        padding: 10px;
                        border-radius: 5px;
                        margin-top: 15px;
                        text-align: center;
                        display: none;
                    }

                    .error.show {
                        display: block;
                    }

                    .success {
                        background: #e6fffa;
                        color: #00835c;
                        padding: 10px;
                        border-radius: 5px;
                        margin-top: 15px;
                        text-align: center;
                    }
                </style>
            </head>

            <body>
                <div class="login-container">
                    <h1>🔧 KFZ Fac Pro</h1>
                    <h2>Rechnungs- und Auftragsverwaltung</h2>

                    <form id="loginForm">
                        <div class="form-group">
                            <label for="username">Benutzername</label>
                            <input type="text" id="username" name="username" value="admin" required autofocus>
                        </div>

                        <div class="form-group">
                            <label for="password">Passwort</label>
                            <input type="password" id="password" name="password" value="admin123" required>
                        </div>

                        <button type="submit" id="submitBtn">Anmelden</button>
                    </form>

                    <div id="message" class="error"></div>
                </div>

                <script>
                    const form = document.getElementById('loginForm');
                    const message = document.getElementById('message');
                    const submitBtn = document.getElementById('submitBtn');

                    form.addEventListener('submit', async (e) => {
                        e.preventDefault();

                        // Reset message
                        message.className = 'error';
                        message.style.display = 'none';

                        // Disable button
                        submitBtn.disabled = true;
                        submitBtn.textContent = 'Anmeldung läuft...';

                        const formData = {
                            username: document.getElementById('username').value,
                            password: document.getElementById('password').value
                        };

                        try {
                            const response = await fetch('/api/auth/login', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify(formData)
                            });

                            const data = await response.json();

                            if (data.success) {
                                message.className = 'success';
                                message.textContent = 'Anmeldung erfolgreich! Weiterleitung...';
                                message.style.display = 'block';

                                setTimeout(() => {
                                    window.location.href = '/';
                                }, 500);
                            } else {
                                throw new Error(data.error || 'Anmeldung fehlgeschlagen');
                            }
                        } catch (error) {
                            message.className = 'error show';
                            message.textContent = error.message;
                            message.style.display = 'block';

                            // Re-enable button
                            submitBtn.disabled = false;
                            submitBtn.textContent = 'Anmelden';
                        }
                    });
                </script>
            </body>

            </html>
        <?php
        }
        return;

    case '/logout':
        session_destroy();
        header('Location: /login');
        return;

    case '/setup':
    case '/setup.php':
        require __DIR__ . '/setup.php';
        return;

    case '/profile':
        if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
            header('Location: /login');
            return;
        }

        $file = __DIR__ . '/public/profile.html';
        if (file_exists($file)) {
            readfile($file);
        } else {
            echo '<h1>Profil</h1>';
            echo '<p>Benutzer: ' . ($_SESSION['username'] ?? 'Unbekannt') . '</p>';
            echo '<p><a href="/">Zurück</a> | <a href="/logout">Logout</a></p>';
        }
        return;

    default:
        // 404
        http_response_code(404);
        ?>
        <!DOCTYPE html>
        <html lang="de">

        <head>
            <meta charset="UTF-8">
            <title>404 - Nicht gefunden</title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: #f5f5f5;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                    margin: 0;
                }

                .error-container {
                    text-align: center;
                    padding: 40px;
                    background: white;
                    border-radius: 10px;
                    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                }

                h1 {
                    color: #e53e3e;
                    font-size: 72px;
                    margin: 0;
                }

                p {
                    color: #666;
                    margin: 20px 0;
                }

                a {
                    color: #667eea;
                    text-decoration: none;
                }

                a:hover {
                    text-decoration: underline;
                }

                .debug {
                    margin-top: 30px;
                    padding: 15px;
                    background: #f7fafc;
                    border-radius: 5px;
                    text-align: left;
                    font-family: monospace;
                    font-size: 12px;
                }
            </style>
        </head>

        <body>
            <div class="error-container">
                <h1>404</h1>
                <p>Die angeforderte Seite wurde nicht gefunden.</p>
                <p>
                    <a href="/">Startseite</a> |
                    <a href="/login">Login</a>
                </p>
                <div class="debug">
                    <strong>Debug Info:</strong><br>
                    Requested: <?php echo htmlspecialchars($uri); ?><br>
                    Method: <?php echo $_SERVER['REQUEST_METHOD']; ?>
                </div>
            </div>
        </body>

        </html>
<?php
        return;
}
?>