<?php

/**
 * Dashboard - Billing System
 * Im gleichen Style wie die Finance App
 */

require_once 'includes/auth.php';

// Require login
$auth->requireLogin();

// Get current user
$currentUser = $auth->getCurrentUser();

// Get statistics from database
$stats = $db->getStats();

// Get current month stats
$current_month = date('Y-m');
$currentMonthStats = $db->select("
    SELECT 
        COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_invoices,
        COUNT(CASE WHEN status IN ('sent', 'overdue') THEN 1 END) as open_invoices,
        SUM(CASE WHEN status = 'paid' AND DATE_FORMAT(paid_date, '%Y-%m') = :month THEN total_amount ELSE 0 END) as month_revenue,
        SUM(CASE WHEN status IN ('sent', 'overdue') THEN total_amount ELSE 0 END) as outstanding
    FROM invoices
", [':month' => $current_month]);

$monthStats = $currentMonthStats[0] ?? [
    'paid_invoices' => 0,
    'open_invoices' => 0,
    'month_revenue' => 0,
    'outstanding' => 0
];

// Get recent invoices
$recentInvoices = $db->select("
    SELECT i.*, c.company_name 
    FROM invoices i 
    LEFT JOIN customers c ON i.customer_id = c.id 
    ORDER BY i.created_at DESC 
    LIMIT 5
");

// Get top customers
$topCustomers = $db->select("
    SELECT 
        c.company_name,
        c.customer_number,
        COUNT(i.id) as invoice_count,
        SUM(CASE WHEN i.status = 'paid' THEN i.total_amount ELSE 0 END) as total_revenue
    FROM customers c
    LEFT JOIN invoices i ON c.id = i.customer_id
    WHERE c.is_active = 1
    GROUP BY c.id
    ORDER BY total_revenue DESC
    LIMIT 5
");

// Format currency function
function formatCurrency($amount)
{
    return number_format($amount ?? 0, 2, ',', '.');
}

// Success/Error Messages
$message = '';
if (isset($_SESSION['success'])) {
    $message = '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success']) . '</div>';
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $message = '<div class="alert alert-error">' . htmlspecialchars($_SESSION['error']) . '</div>';
    unset($_SESSION['error']);
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Billing System</title>

    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Custom CSS (gleiche Struktur wie Finance App) -->
    <link rel="stylesheet" href="assets/css/theme.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">

</head>

<body>
    <div class="app-layout">
        <!-- Sidebar (exakt wie Finance App) -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="dashboard.php" class="sidebar-logo">
                    <img src="assets/images/logo.png" alt="Billing System" class="sidebar-logo-image">
                </a>
                <p class="sidebar-welcome">Willkommen, <?= htmlspecialchars($currentUser['full_name'] ?? $currentUser['username']) ?></p>
            </div>

            <nav>
                <ul class="sidebar-nav">
                    <li><a href="dashboard.php" class="active"><i class="fa-solid fa-house"></i>&nbsp;&nbsp;Dashboard</a></li>
                    <li><a href="invoices.php"><i class="fa-solid fa-file-invoice"></i>&nbsp;&nbsp;Rechnungen</a></li>
                    <li><a href="customers.php"><i class="fa-solid fa-users"></i>&nbsp;&nbsp;Kunden</a></li>
                    <li><a href="products.php"><i class="fa-solid fa-box"></i>&nbsp;&nbsp;Produkte</a></li>
                    <li><a href="reports.php"><i class="fa-solid fa-chart-bar"></i>&nbsp;&nbsp;Berichte</a></li>

                    <?php if ($auth->isAdmin()): ?>
                        <li>
                            <a style="margin-top: 20px; border-top: 1px solid var(--clr-surface-a20); padding-top: 20px;" href="settings.php">
                                <i class="fa-solid fa-gear"></i>&nbsp;&nbsp;Einstellungen
                            </a>
                        </li>
                        <li><a href="logs.php"><i class="fa-solid fa-terminal"></i>&nbsp;&nbsp;System Logs</a></li>
                    <?php endif; ?>

                    <li>
                        <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i>&nbsp;&nbsp;Logout</a>
                    </li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <!-- Dashboard Header (wie Finance App) -->
            <div class="dashboard-header">
                <div class="welcome-text">
                    <h1><i class="fa-solid fa-house"></i>&nbsp;&nbsp;Dashboard</h1>
                    <p>Übersicht über deine Rechnungen - <?= date('F Y') ?></p>
                </div>
                <div class="quick-actions">
                    <a href="invoices.php?action=new" class="btn btn-primary">+ Neue Rechnung</a>
                    <a href="customers.php?action=new" class="btn btn-secondary">+ Neuer Kunde</a>
                    <a href="products.php?action=new" class="btn" style="background: #22c55e; color: white;">+ Neues Produkt</a>
                    <a href="reports.php" class="btn" style="background: #f97316; color: white;">+ Report erstellen</a>
                </div>
            </div>

            <?= $message ?>

            <!-- Gesamtumsatz (Hauptkarte wie GesamtvermÃ¶gen) -->
            <div class="wealth-card-container">
                <div class="wealth-card">
                    <div class="wealth-card-header">
                        <h2><i class="fa-solid fa-globe"></i> Gesamtumsatz</h2>
                        <div style="color: var(--clr-surface-a50); font-size: 14px;">
                            Stand: <?= date('d.m.Y H:i') ?>
                        </div>
                    </div>

                    <div class="wealth-value">
                        €<?= formatCurrency($stats['total_revenue']) ?>
                    </div>

                    <div class="wealth-breakdown">
                        <div class="breakdown-item">
                            <div class="breakdown-value"><?= $stats['customers'] ?></div>
                            <div class="breakdown-label">Aktive Kunden</div>
                        </div>
                        <div class="breakdown-item">
                            <div class="breakdown-value positive">€<?= formatCurrency($monthStats['month_revenue']) ?></div>
                            <div class="breakdown-label">Umsatz diesen Monat</div>
                        </div>
                        <div class="breakdown-item">
                            <div class="breakdown-value negative">€<?= formatCurrency($stats['outstanding']) ?></div>
                            <div class="breakdown-label">Offene Beträge</div>
                        </div>
                        <div class="breakdown-item">
                            <div class="breakdown-value"><?= $stats['open_invoices'] ?></div>
                            <div class="breakdown-label">Offene Rechnungen</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monatsstatistiken (wie Finance App) -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon"><i class="fa-solid fa-sack-dollar"></i></div>
                        <div class="stat-title">Umsatz diesen Monat</div>
                    </div>
                    <div class="stat-value income">+€<?= formatCurrency($monthStats['month_revenue']) ?></div>
                    <div class="stat-subtitle">Bezahlte Rechnungen</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon"><i class="fa-solid fa-file-invoice"></i></div>
                        <div class="stat-title">Offene Rechnungen</div>
                    </div>
                    <div class="stat-value expense"><?= $monthStats['open_invoices'] ?></div>
                    <div class="stat-subtitle">Noch nicht bezahlt</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon"><i class="fa-solid fa-money-check-dollar"></i></div>
                        <div class="stat-title">Offene Beträge</div>
                    </div>
                    <div class="stat-value negative">€<?= formatCurrency($monthStats['outstanding']) ?></div>
                    <div class="stat-subtitle">Ausstehende Zahlungen</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
                        <div class="stat-title">Aktive Kunden</div>
                    </div>
                    <div class="stat-value positive"><?= $stats['customers'] ?></div>
                    <div class="stat-subtitle">Registrierte Kunden</div>
                </div>
            </div>

            <!-- Content Grid (wie Finance App) -->
            <div class="dashboard-grid">
                <!-- Letzte Rechnungen -->
                <div class="card">
                    <div class="card-header">
                        <h3 style="color: var(--clr-primary-a20);"><i class="fa-solid fa-clock-rotate-left"></i> Letzte Rechnungen</h3>
                        <a href="invoices.php" style="color: var(--clr-primary-a20); text-decoration: none; font-size: 14px;">
                            Alle anzeigen →
                        </a>
                    </div>

                    <?php if (empty($recentInvoices)): ?>
                        <div class="empty-state">
                            <h3>Keine Rechnungen</h3>
                            <p>Erstelle deine erste Rechnung!</p>
                            <a href="invoices.php?action=new" class="btn btn-small">Rechnung erstellen</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentInvoices as $invoice): ?>
                            <div class="transaction-item">
                                <div class="transaction-info">
                                    <div class="transaction-title">
                                        <?= htmlspecialchars($invoice['invoice_number']) ?> - <?= htmlspecialchars($invoice['company_name'] ?? 'N/A') ?>
                                    </div>
                                    <div class="transaction-meta">
                                        <?= date('d.m.Y', strtotime($invoice['issue_date'])) ?> •
                                        <?php
                                        $statusText = [
                                            'draft' => 'Entwurf',
                                            'sent' => 'Versendet',
                                            'paid' => 'Bezahlt',
                                            'overdue' => 'Überfällig',
                                            'cancelled' => 'Storniert'
                                        ];
                                        echo $statusText[$invoice['status']] ?? $invoice['status'];
                                        ?>
                                    </div>
                                </div>
                                <div class="transaction-amount <?= $invoice['status'] == 'paid' ? 'income' : 'expense' ?>">
                                    €<?= formatCurrency($invoice['total_amount']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Top Kunden -->
                <div class="card">
                    <div class="card-header">
                        <h3 style="color: var(--clr-primary-a20);"><i class="fa-solid fa-star"></i> Top Kunden</h3>
                        <a href="customers.php" style="color: var(--clr-primary-a20); text-decoration: none; font-size: 14px;">
                            Alle anzeigen →
                        </a>
                    </div>

                    <?php if (empty($topCustomers)): ?>
                        <div class="empty-state">
                            <h3>Keine Kunden</h3>
                            <p>Füge deinen ersten Kunden hinzu!</p>
                            <a href="customers.php?action=new" class="btn btn-small">Kunde hinzufügen</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($topCustomers as $customer): ?>
                            <div class="investment-item">
                                <div class="investment-info">
                                    <div class="investment-symbol"><?= htmlspecialchars($customer['customer_number']) ?></div>
                                    <div class="investment-name"><?= htmlspecialchars($customer['company_name']) ?></div>
                                </div>
                                <div class="investment-value">
                                    <div class="investment-current">
                                        €<?= formatCurrency($customer['total_revenue']) ?>
                                    </div>
                                    <div class="investment-change positive">
                                        <?= $customer['invoice_count'] ?> Rechnung(en)
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Info-Box für nächste Schritte -->
            <div class="debt-overview-card">
                <div class="card-header-modern">
                    <div class="header-icon">
                        <i class="fas fa-lightbulb"></i>
                    </div>
                    <h3 class="header-title">Schnellzugriff</h3>
                </div>

                <div class="info-box-modern">
                    <div class="info-content">
                        <div class="info-icon-large">
                            <i class="fas fa-bolt"></i>
                        </div>
                        <div class="info-text-content">
                            <div class="info-title-modern">
                                Häufig verwendete Funktionen
                            </div>
                            <div class="info-description">
                                • Neue Rechnung erstellen und direkt versenden<br>
                                • Kunden verwalten und Kontaktdaten pflegen<br>
                                • Berichte generieren für Buchhaltung<br>
                                • Offene Rechnungen nachverfolgen
                            </div>
                            <div style="display: flex; gap: 10px; margin-top: 10px;">
                                <a href="invoices.php?action=new" class="info-link">
                                    <span>Neue Rechnung</span>
                                    <i class="fas fa-arrow-right"></i>
                                </a>
                                <a href="reports.php" class="info-link">
                                    <span>Berichte</span>
                                    <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>

</html>