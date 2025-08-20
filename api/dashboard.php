<?php

/**
 * KFZ Fac Pro - Dashboard API
 * Statistiken und Übersicht
 */

// Headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Bei OPTIONS-Request direkt beenden
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Session prüfen
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    http_response_code(401);
    echo json_encode(['error' => 'Nicht autorisiert']);
    exit;
}

// Dashboard-Daten sammeln
$dashboard = [
    // Übersicht
    'overview' => [
        'date' => date('Y-m-d'),
        'time' => date('H:i:s'),
        'user' => $_SESSION['username'] ?? 'Admin',
        'role' => $_SESSION['role'] ?? 'admin',
        'version' => '2.0.0'
    ],

    // Kunden
    'kunden' => [
        'gesamt' => 156,
        'neu_heute' => 2,
        'neu_woche' => 8,
        'neu_monat' => 23,
        'aktiv' => 142,
        'inaktiv' => 14
    ],

    // Fahrzeuge
    'fahrzeuge' => [
        'gesamt' => 203,
        'in_werkstatt' => 5,
        'neu_monat' => 18
    ],

    // Aufträge
    'auftraege' => [
        'gesamt' => 1245,
        'offen' => 12,
        'in_bearbeitung' => 8,
        'abgeschlossen_heute' => 4,
        'abgeschlossen_woche' => 28,
        'abgeschlossen_monat' => 95,
        'storniert' => 3
    ],

    // Rechnungen
    'rechnungen' => [
        'gesamt' => 1198,
        'offen' => 15,
        'teilbezahlt' => 3,
        'bezahlt_heute' => 6,
        'bezahlt_woche' => 31,
        'bezahlt_monat' => 89,
        'ueberfaellig' => 4,
        'mahnung' => 2,
        'storniert' => 5
    ],

    // Umsatz
    'umsatz' => [
        'heute' => 2456.80,
        'gestern' => 3234.50,
        'woche' => 15678.90,
        'letzte_woche' => 14234.60,
        'monat' => 45678.30,
        'letzter_monat' => 42345.70,
        'jahr' => 523456.80,
        'letztes_jahr' => 498234.50,
        'durchschnitt_tag' => 1856.40,
        'durchschnitt_auftrag' => 285.60
    ],

    // Top-Listen
    'top' => [
        'kunden' => [
            ['name' => 'Max Mustermann', 'umsatz' => 12456.80, 'auftraege' => 23],
            ['name' => 'Anna Schmidt', 'umsatz' => 9234.50, 'auftraege' => 18],
            ['name' => 'Peter Weber', 'umsatz' => 7890.30, 'auftraege' => 15],
            ['name' => 'Maria Fischer', 'umsatz' => 6543.20, 'auftraege' => 12],
            ['name' => 'Thomas Müller', 'umsatz' => 5678.90, 'auftraege' => 10]
        ],
        'leistungen' => [
            ['name' => 'Ölwechsel', 'anzahl' => 145, 'umsatz' => 8700.00],
            ['name' => 'Inspektion', 'anzahl' => 89, 'umsatz' => 13350.00],
            ['name' => 'Bremsen', 'anzahl' => 67, 'umsatz' => 20100.00],
            ['name' => 'Reifen', 'anzahl' => 234, 'umsatz' => 35100.00],
            ['name' => 'TÜV/AU', 'anzahl' => 56, 'umsatz' => 5040.00]
        ]
    ],

    // Aktivitäten
    'aktivitaeten' => [
        ['zeit' => '09:15', 'typ' => 'auftrag', 'text' => 'Neuer Auftrag A2024-0145 erstellt'],
        ['zeit' => '09:42', 'typ' => 'rechnung', 'text' => 'Rechnung R2024-0089 bezahlt'],
        ['zeit' => '10:23', 'typ' => 'kunde', 'text' => 'Neuer Kunde: Hans Meyer'],
        ['zeit' => '11:05', 'typ' => 'auftrag', 'text' => 'Auftrag A2024-0143 abgeschlossen'],
        ['zeit' => '11:30', 'typ' => 'system', 'text' => 'Backup erfolgreich erstellt']
    ],

    // Termine
    'termine' => [
        ['zeit' => '14:00', 'kunde' => 'Max Mustermann', 'fahrzeug' => 'B-XY 1234', 'leistung' => 'Inspektion'],
        ['zeit' => '15:30', 'kunde' => 'Anna Schmidt', 'fahrzeug' => 'M-AB 5678', 'leistung' => 'Reifenwechsel'],
        ['zeit' => '16:00', 'kunde' => 'Peter Weber', 'fahrzeug' => 'S-CD 9012', 'leistung' => 'TÜV/AU']
    ],

    // Warnungen
    'warnungen' => [
        'ueberfaellige_rechnungen' => 4,
        'offene_auftraege_alt' => 2,
        'backup_veraltet' => false,
        'lizenz_ablauf' => false,
        'lagerbestand_niedrig' => 3
    ],

    // Chart-Daten
    'charts' => [
        'umsatz_woche' => [
            ['tag' => 'Mo', 'umsatz' => 2345.60],
            ['tag' => 'Di', 'umsatz' => 3456.80],
            ['tag' => 'Mi', 'umsatz' => 2890.40],
            ['tag' => 'Do', 'umsatz' => 3234.50],
            ['tag' => 'Fr', 'umsatz' => 2456.80],
            ['tag' => 'Sa', 'umsatz' => 1294.80],
            ['tag' => 'So', 'umsatz' => 0]
        ],
        'auftraege_status' => [
            ['status' => 'Offen', 'anzahl' => 12, 'farbe' => '#ed8936'],
            ['status' => 'In Bearbeitung', 'anzahl' => 8, 'farbe' => '#4299e1'],
            ['status' => 'Abgeschlossen', 'anzahl' => 95, 'farbe' => '#48bb78'],
            ['status' => 'Storniert', 'anzahl' => 3, 'farbe' => '#f56565']
        ]
    ]
];

// Response senden
echo json_encode($dashboard);
