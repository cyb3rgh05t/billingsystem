<?php
session_start();
require_once "config/config.php";
require_once "includes/logger.php";
require_once "config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = $_POST["username"] ?? "";
    $password = $_POST["password"] ?? "";
    
    if ($username && $password) {
        require_once "includes/auth.php";
        Auth::init();
        
        if (Auth::login($username, $password)) {
            header("Location: index.php");
            exit;
        } else {
            $error = "Ungültige Anmeldedaten";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KFZ Billing Pro - Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --clr-primary-a0: #e6a309;
            --clr-primary-a10: #ebad36;
            --clr-surface-a0: #141414;
            --clr-surface-a10: #292929;
            --clr-surface-a20: #404040;
            --clr-light-a0: #ffffff;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, var(--clr-surface-a0) 0%, var(--clr-surface-a10) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: var(--clr-surface-a10);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            width: 100%;
            max-width: 400px;
            border: 1px solid var(--clr-primary-a0);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header i {
            font-size: 48px;
            color: var(--clr-primary-a0);
            margin-bottom: 16px;
        }
        
        .login-header h1 {
            font-size: 28px;
            color: var(--clr-primary-a10);
            margin-bottom: 8px;
        }
        
        .login-header p {
            color: #8c8c8c;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #8c8c8c;
            font-size: 14px;
        }
        
        .form-input {
            width: 100%;
            padding: 12px 16px;
            background: var(--clr-surface-a20);
            border: 1px solid #585858;
            border-radius: 8px;
            color: var(--clr-light-a0);
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--clr-primary-a0);
            box-shadow: 0 0 0 3px rgba(230, 163, 9, 0.1);
        }
        
        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, var(--clr-primary-a0), var(--clr-primary-a10));
            border: none;
            border-radius: 8px;
            color: #000;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(230, 163, 9, 0.3);
        }
        
        .error-message {
            background: rgba(248, 113, 113, 0.1);
            border: 1px solid #f87171;
            color: #f87171;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .demo-info {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--clr-surface-a20);
            text-align: center;
            color: #8c8c8c;
            font-size: 12px;
        }
        
        .demo-info code {
            background: var(--clr-surface-a20);
            padding: 2px 6px;
            border-radius: 4px;
            color: var(--clr-primary-a10);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <i class="fas fa-car"></i>
            <h1>KFZ Billing Pro</h1>
            <p>Melden Sie sich an, um fortzufahren</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label">Benutzername</label>
                <input type="text" name="username" class="form-input" required autofocus>
            </div>
            
            <div class="form-group">
                <label class="form-label">Passwort</label>
                <input type="password" name="password" class="form-input" required>
            </div>
            
            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Anmelden
            </button>
        </form>
        
        <div class="demo-info">
            Demo-Zugang: <code>admin</code> / <code>admin123</code>
        </div>
    </div>
</body>
</html>
