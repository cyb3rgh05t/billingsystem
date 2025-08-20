<?php

/**
 * public/index.php
 * Dieser Router leitet alles an den Haupt-Router weiter
 */

// Prüfen ob es eine statische Datei im public-Ordner ist
$requestUri = $_SERVER['REQUEST_URI'];
$requestUri = strtok($requestUri, '?'); // Query-String entfernen
$file = __DIR__ . $requestUri;

// Wenn es eine existierende Datei ist, lass PHP sie direkt ausliefern
if ($requestUri !== '/' && file_exists($file) && is_file($file)) {
    return false; // PHP Built-in Server soll die Datei selbst ausliefern
}

// Ansonsten an den Haupt-Router weiterleiten
require_once __DIR__ . '/../index.php';
