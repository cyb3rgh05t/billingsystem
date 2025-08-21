<?php

/**
 * KFZ Fac Pro - Dashboard API
 * Liefert alle Dashboard-Daten
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

// Nur GET erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Methode nicht erlaubt']);
    exit;
}

// Includes
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

// Auth-Check
Auth::requireAuth();

// Datenbank-Verbindung
$db = Database::getInstance()->getConnection();

try {
    $dashboard = [
        'statistiken' => [],
        'aktivitaeten' => [],
        'diagramme' => [],
        'schnellzugriff' => []
    ];

    // === STATISTIKEN ===

    // Kunden
    $stmt = $db->query("SELECT COUNT(*) as gesamt, 
                               COUNT(CASE WHEN gesperrt = 0 OR gesperrt IS NULL THEN 1 END) as aktiv
                        FROM kunden");
    $dashboard['statistiken']['kunden'] = $stmt->fetch();

    // Fahrzeuge
    $stmt = $db->query("SELECT COUNT(*) as gesamt FROM fahrzeuge");
    $dashboard['statistiken']['fahrzeuge'] = $stmt->fetch();

    // Aufträge
    $stmt = $db->query("SELECT COUNT(*) as gesamt,
                               COUNT(CASE WHEN status = 'offen' THEN 1 END) as offen,
                               COUNT(CASE WHEN status = 'in_bearbeitung' THEN 1 END) as in_bearbeitung,
                               COUNT(CASE WHEN status = 'fertig' THEN 1 END) as fertig
                        FROM auftraege");
    $dashboard['statistiken']['auftraege'] = $stmt->fetch();

    // Rechnungen
    $stmt = $db->query("SELECT COUNT(*) as gesamt,
                               COUNT(CASE WHEN bezahlt = 0 THEN 1 END) as offen,
                               SUM(CASE WHEN bezahlt = 0 THEN gesamtbetrag ELSE 0 END) as offen_betrag,
                               COUNT(CASE WHEN bezahlt = 0 AND faellig_am < DATE('now') THEN 1 END) as ueberfaellig
                        FROM rechnungen 
                        WHERE storniert = 0");
    $dashboard['statistiken']['rechnungen'] = $stmt->fetch();

    // Fahrzeughandel (falls Tabelle existiert)
    try {
        $stmt = $db->query("SELECT COUNT(*) as gesamt,
                                   COUNT(CASE WHEN typ = 'ankauf' THEN 1 END) as ankaeufe,
                                   COUNT(CASE WHEN typ = 'verkauf' THEN 1 END) as verkaeufe,
                                   SUM(gewinn) as gesamt_gewinn
                            FROM fahrzeug_handel 
                            WHERE status != 'storniert'");
        $dashboard['statistiken']['fahrzeughandel'] = $stmt->fetch();
    } catch (Exception $e) {
        // Tabelle existiert möglicherweise nicht
        $dashboard['statistiken']['fahrzeughandel'] = [
            'gesamt' => 0,
            'ankaeufe' => 0,
            'verkaeufe' => 0,
            'gesamt_gewinn' => 0
        ];
    }

    // === LETZTE AKTIVITÄTEN ===

    // Letzte Aufträge
    $stmt = $db->query("SELECT a.id, a.auftragsnummer, a.datum, a.status, a.beschreibung,
                               k.vorname, k.nachname, k.firma
                        FROM auftraege a
                        LEFT JOIN kunden k ON a.kunden_id = k.id
                        ORDER BY a.erstellt_am DESC
                        LIMIT 5");
    $dashboard['aktivitaeten']['auftraege'] = $stmt->fetchAll();

    // Letzte Rechnungen
    $stmt = $db->query("SELECT r.id, r.rechnungsnummer, r.datum, r.gesamtbetrag, r.bezahlt,
                               k.vorname, k.nachname, k.firma
                        FROM rechnungen r
                        LEFT JOIN kunden k ON r.kunden_id = k.id
                        ORDER BY r.erstellt_am DESC
                        LIMIT 5");
    $dashboard['aktivitaeten']['rechnungen'] = $stmt->fetchAll();

    // Neue Kunden
    $stmt = $db->query("SELECT id, vorname, nachname, firma, kunde_seit
                        FROM kunden
                        ORDER BY erstellt_am DESC
                        LIMIT 5");
    $dashboard['aktivitaeten']['neue_kunden'] = $stmt->fetchAll();

    // === DIAGRAMM-DATEN ===

    // Umsatz letzte 12 Monate
    $stmt = $db->query("SELECT 
                            strftime('%Y-%m', datum) as monat,
                            SUM(gesamtbetrag) as umsatz,
                            COUNT(*) as anzahl
                        FROM rechnungen
                        WHERE datum >= date('now', '-12 months')
                          AND storniert = 0
                        GROUP BY strftime('%Y-%m', datum)
                        ORDER BY monat");
    $dashboard['diagramme']['umsatz_monate'] = $stmt->fetchAll();

    // Aufträge nach Status
    $stmt = $db->query("SELECT status, COUNT(*) as anzahl
                        FROM auftraege
                        WHERE status != 'storniert'
                        GROUP BY status");
    $dashboard['diagramme']['auftraege_status'] = $stmt->fetchAll();

    // Top 5 Kunden nach Umsatz
    $stmt = $db->query("SELECT 
                            k.id, k.vorname, k.nachname, k.firma,
                            SUM(r.gesamtbetrag) as umsatz,
                            COUNT(r.id) as anzahl_rechnungen
                        FROM kunden k
                        JOIN rechnungen r ON k.id = r.kunden_id
                        WHERE r.storniert = 0
                        GROUP BY k.id
                        ORDER BY umsatz DESC
                        LIMIT 5");
    $dashboard['diagramme']['top_kunden'] = $stmt->fetchAll();

    // === SCHNELLZUGRIFF / TODOS ===

    // Heute fällige Aufträge
    $stmt = $db->query("SELECT COUNT(*) as anzahl
                        FROM auftraege
                        WHERE DATE(termin_start) = DATE('now')
                           OR DATE(termin_ende) = DATE('now')");
    $result = $stmt->fetch();
    $dashboard['schnellzugriff']['heute_faellig'] = $result['anzahl'];

    // Überfällige Rechnungen
    $stmt = $db->query("SELECT COUNT(*) as anzahl, SUM(gesamtbetrag) as betrag
                        FROM rechnungen
                        WHERE bezahlt = 0 
                          AND storniert = 0
                          AND faellig_am < DATE('now')");
    $result = $stmt->fetch();
    $dashboard['schnellzugriff']['ueberfaellige_rechnungen'] = $result;

    // TÜV/AU fällig (nächste 30 Tage)
    $stmt = $db->query("SELECT COUNT(*) as anzahl
                        FROM fahrzeuge
                        WHERE tuev_bis <= DATE('now', '+30 days')
                           OR au_bis <= DATE('now', '+30 days')");
    $result = $stmt->fetch();
    $dashboard['schnellzugriff']['tuev_au_faellig'] = $result['anzahl'];

    // Warte auf Teile
    $stmt = $db->query("SELECT COUNT(*) as anzahl
                        FROM auftraege
                        WHERE status = 'warte_auf_teile'");
    $result = $stmt->fetch();
    $dashboard['schnellzugriff']['warte_auf_teile'] = $result['anzahl'];

    // === META-INFORMATIONEN ===

    // Einstellungen für Dashboard
    $stmt = $db->query("SELECT key, value FROM einstellungen");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[] = [
            'key' => $row['key'],
            'value' => $row['value']
        ];
    }
    $dashboard['einstellungen'] = $settings;

    $dashboard['meta'] = [
        'timestamp' => date('Y-m-d H:i:s'),
        'user' => Auth::getCurrentUser(),
        'version' => '2.0.0'
    ];

    // Erfolgreiche Antwort
    echo json_encode($dashboard);
} catch (Exception $e) {
    error_log("Dashboard API Fehler: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Fehler beim Laden der Dashboard-Daten']);
}
