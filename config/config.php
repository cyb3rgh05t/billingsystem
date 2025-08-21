<?php
// General configuration
define("APP_NAME", "KFZ Billing Pro");
define("APP_VERSION", "1.0.0");
define("APP_URL", "http://localhost:8000");
define("APP_PATH", __DIR__ . "/..");

// Session configuration
ini_set("session.cookie_httponly", 1);
ini_set("session.use_only_cookies", 1);
ini_set("session.cookie_secure", 0); // Set to 1 for HTTPS

// Timezone
date_default_timezone_set("Europe/Berlin");

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set("display_errors", 1);

// License Server (optional)
define("LICENSE_SERVER", "https://your-license-server.com/api/verify");
define("LICENSE_KEY", "YOUR_LICENSE_KEY_HERE");
