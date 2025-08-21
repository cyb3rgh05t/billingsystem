<?php

/**
 * Login Page - Billing System
 * Im gleichen Style wie Finance App
 */

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/auth.php';

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error_message = '';
$success_message = '';

// Check for logout message
if (isset($_SESSION['logout_message'])) {
    $success_message = $_SESSION['logout_message'];
    unset($_SESSION['logout_message']);
}

// Check for other messages
if (isset($_SESSION['error'])) {
    $error_message = $_SESSION['error'];
    unset($_SESSION['error']);
}

if (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Handle Login Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error_message = 'Bitte alle Felder ausfüllen';
        } else {
            $result = $auth->login($username, $password);

            if ($result['success']) {
                // Check for redirect URL
                $redirect = $_SESSION['redirect_after_login'] ?? 'dashboard.php';
                unset($_SESSION['redirect_after_login']);

                header('Location: ' . $redirect);
                exit;
            } else {
                $error_message = $result['message'];
            }
        }
    } elseif ($_POST['action'] === 'register') {
        // Handle registration
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';

        if (empty($username) || empty($email) || empty($password) || empty($password_confirm)) {
            $error_message = 'Bitte alle Felder ausfüllen';
        } elseif ($password !== $password_confirm) {
            $error_message = 'Passwörter stimmen nicht überein';
        } elseif (strlen($password) < 6) {
            $error_message = 'Passwort muss mindestens 6 Zeichen lang sein';
        } else {
            $result = $auth->register([
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'full_name' => $username // Use username as default full name
            ]);

            if ($result['success']) {
                $success_message = 'Registrierung erfolgreich! Sie können sich jetzt anmelden.';
            } else {
                $error_message = $result['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing System - Login</title>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/theme.css">
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--clr-surface-a0) 0%, var(--clr-surface-tonal-a0) 100%);
        }

        .login-card {
            background-color: var(--clr-surface-a10);
            border: 1px solid var(--clr-surface-a20);
            border-radius: 12px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 10px;
        }

        .login-logo-image {
            width: 45px;
            height: 45px;
            object-fit: contain;
            filter: drop-shadow(0 3px 6px rgba(0, 0, 0, 0.3));
        }

        .login-title {
            color: var(--clr-primary-a20);
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
        }

        .login-subtitle {
            color: var(--clr-surface-a50);
            font-size: 14px;
        }

        .tab-buttons {
            display: flex;
            margin-bottom: 30px;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--clr-surface-a20);
        }

        .tab-button {
            flex: 1;
            padding: 12px;
            background-color: var(--clr-surface-a20);
            color: var(--clr-surface-a50);
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .tab-button.active {
            background-color: var(--clr-primary-a0);
            color: var(--clr-dark-a0);
        }

        .tab-button:hover:not(.active) {
            background-color: var(--clr-surface-a30);
        }

        .form-container {
            display: none;
        }

        .form-container.active {
            display: block;
        }

        .alert {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-error {
            background-color: rgba(248, 113, 113, 0.1);
            border: 1px solid #f87171;
            color: #fca5a5;
        }

        .alert-success {
            background-color: rgba(74, 222, 128, 0.1);
            border: 1px solid #4ade80;
            color: #86efac;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 6px;
            color: var(--clr-surface-a50);
            font-weight: 500;
            font-size: 14px;
        }

        .form-input {
            width: 100%;
            padding: 12px;
            background-color: var(--clr-surface-a05);
            border: 1px solid var(--clr-surface-a20);
            border-radius: 6px;
            color: var(--clr-light-a0);
            font-size: 14px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--clr-primary-a0);
            box-shadow: 0 0 0 2px rgba(171, 180, 21, 0.2);
            background-color: var(--clr-surface-a10);
        }

        .form-input::placeholder {
            color: var(--clr-surface-a40);
        }

        .btn-full {
            width: 100%;
            justify-content: center;
            margin-top: 10px;
            padding: 12px 20px;
            background-color: var(--clr-primary-a0);
            color: var(--clr-dark-a0);
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-full:hover {
            background-color: var(--clr-primary-a10);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(171, 180, 21, 0.3);
        }

        .btn-full:active {
            transform: translateY(0);
        }

        .form-footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--clr-surface-a20);
        }

        .form-footer p {
            color: var(--clr-surface-a50);
            font-size: 14px;
        }

        /* Demo Info Box */
        .demo-info {
            background: var(--clr-surface-tonal-a10);
            border: 1px solid var(--clr-primary-a20);
            border-radius: 6px;
            padding: 12px;
            margin-top: 20px;
            font-size: 13px;
        }

        .demo-info-title {
            color: var(--clr-primary-a20);
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .demo-credentials {
            display: flex;
            gap: 20px;
            justify-content: center;
        }

        .demo-credential {
            text-align: center;
        }

        .demo-credential strong {
            color: var(--clr-primary-a20);
            display: block;
            margin-bottom: 2px;
        }

        .demo-credential span {
            color: var(--clr-surface-a50);
            font-size: 12px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .login-card {
                margin: 20px;
                padding: 30px 20px;
            }

            .login-logo {
                flex-direction: column;
                gap: 10px;
            }

            .login-title {
                font-size: 1.3rem;
            }
        }

        @media (max-width: 480px) {
            .login-logo-image {
                width: 40px;
                height: 40px;
            }

            .login-title {
                font-size: 1.2rem;
            }

            .tab-button {
                padding: 10px;
                font-size: 13px;
            }
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">
                    <img src="assets/images/logo.png" alt="Billing System Logo" class="login-logo-image">
                    <h1 class="login-title">Billing System</h1>
                </div>
                <p class="login-subtitle">Professionelle Rechnungsverwaltung</p>
            </div>

            <?php if ($error_message): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>

            <div class="tab-buttons">
                <button class="tab-button active" onclick="switchTab('login')">Anmelden</button>
                <button class="tab-button" onclick="switchTab('register')">Registrieren</button>
            </div>

            <!-- Login Form -->
            <div id="login-form" class="form-container active">
                <form action="" method="POST">
                    <input type="hidden" name="action" value="login">

                    <div class="form-group">
                        <label class="form-label" for="login-username">Benutzername</label>
                        <input type="text" id="login-username" name="username" class="form-input" required autofocus>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="login-password">Passwort</label>
                        <input type="password" id="login-password" name="password" class="form-input" required>
                    </div>

                    <button type="submit" class="btn btn-full">Anmelden</button>
                </form>
            </div>

            <!-- Register Form -->
            <div id="register-form" class="form-container">
                <form action="" method="POST">
                    <input type="hidden" name="action" value="register">

                    <div class="form-group">
                        <label class="form-label" for="reg-username">Benutzername</label>
                        <input type="text" id="reg-username" name="username" class="form-input" required minlength="3">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="reg-email">E-Mail</label>
                        <input type="email" id="reg-email" name="email" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="reg-password">Passwort</label>
                        <input type="password" id="reg-password" name="password" class="form-input" required minlength="6">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="reg-password-confirm">Passwort bestätigen</label>
                        <input type="password" id="reg-password-confirm" name="password_confirm" class="form-input" required>
                    </div>

                    <button type="submit" class="btn btn-full">Registrieren</button>
                </form>
            </div>

            <!-- Demo Credentials -->
            <div class="demo-info">
                <div class="demo-info-title">
                    Demo-Zugangsdaten
                </div>
                <div class="demo-credentials">
                    <div class="demo-credential">
                        <strong>Admin</strong>
                        <span>admin / admin123</span>
                    </div>
                    <div class="demo-credential">
                        <strong>Demo User</strong>
                        <span>demo / demo123</span>
                    </div>
                </div>
            </div>

            <div class="form-footer">
                <?php
                $start_year = 2025;
                $current_year = date('Y');
                ?>
                <p>© <?= $start_year == $current_year ? $current_year : $start_year . ' - ' . $current_year ?> · Billing System</p>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            // Tab buttons
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });

            // Form containers
            document.querySelectorAll('.form-container').forEach(container => {
                container.classList.remove('active');
            });

            // Activate selected tab
            if (tab === 'login') {
                document.querySelector('.tab-button:first-child').classList.add('active');
                document.getElementById('login-form').classList.add('active');
            } else {
                document.querySelector('.tab-button:last-child').classList.add('active');
                document.getElementById('register-form').classList.add('active');
            }
        }

        // Password confirmation validation
        document.getElementById('reg-password-confirm').addEventListener('input', function() {
            const password = document.getElementById('reg-password').value;
            const confirm = this.value;

            if (password !== confirm) {
                this.setCustomValidity('Passwörter stimmen nicht überein');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>

</html>