<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KFZ Billing Pro - Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* CSS Custom Properties - Dein Farbschema */
        :root {
            /* Base colors */
            --clr-dark-a0: #000000;
            --clr-light-a0: #ffffff;

            /* Theme primary colors */
            --clr-primary-a0: #e6a309;
            --clr-primary-a10: #ebad36;
            --clr-primary-a20: #f0b753;
            --clr-primary-a30: #f4c16c;
            --clr-primary-a40: #f8cb85;
            --clr-primary-a50: #fbd59d;

            /* Theme surface colors */
            --clr-surface-a0: #141414;
            --clr-surface-a10: #292929;
            --clr-surface-a20: #404040;
            --clr-surface-a30: #585858;
            --clr-surface-a40: #727272;
            --clr-surface-a50: #8c8c8c;

            /* Theme tonal surface colors */
            --clr-surface-tonal-a0: #272017;
            --clr-surface-tonal-a10: #3c352c;
            --clr-surface-tonal-a20: #514b43;
            --clr-surface-tonal-a30: #68625b;
            --clr-surface-tonal-a40: #7f7a74;
            --clr-surface-tonal-a50: #98938e;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--clr-surface-a0);
            color: var(--clr-light-a0);
            min-height: 100vh;
            display: flex;
        }

        /* Sidebar */
        .sidebar {
            width: 260px;
            background: linear-gradient(135deg, var(--clr-surface-a10) 0%, var(--clr-surface-tonal-a0) 100%);
            padding: 20px;
            border-right: 1px solid var(--clr-primary-a0);
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--clr-surface-a20);
        }

        .logo i {
            font-size: 32px;
            color: var(--clr-primary-a0);
        }

        .logo h1 {
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--clr-primary-a0), var(--clr-primary-a20));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav-menu {
            list-style: none;
            flex: 1;
        }

        .nav-item {
            margin-bottom: 8px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: var(--clr-light-a0);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 14px;
            font-weight: 500;
        }

        .nav-link:hover {
            background: var(--clr-surface-a20);
            color: var(--clr-primary-a10);
        }

        .nav-link.active {
            background: linear-gradient(135deg, var(--clr-primary-a0), var(--clr-primary-a10));
            color: var(--clr-dark-a0);
        }

        .nav-link i {
            width: 20px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 30px;
            width: calc(100% - 260px);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--clr-surface-a20);
        }

        .header h2 {
            font-size: 28px;
            font-weight: 600;
            color: var(--clr-primary-a10);
        }

        .header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--clr-primary-a0), var(--clr-primary-a10));
            color: var(--clr-dark-a0);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--clr-primary-a10), var(--clr-primary-a20));
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(230, 163, 9, 0.3);
        }

        .btn-secondary {
            background: var(--clr-surface-a20);
            color: var(--clr-light-a0);
            border: 1px solid var(--clr-surface-a30);
        }

        .btn-secondary:hover {
            background: var(--clr-surface-a30);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, var(--clr-surface-a10), var(--clr-surface-tonal-a10));
            border-radius: 12px;
            padding: 24px;
            border: 1px solid var(--clr-surface-a20);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 30px rgba(230, 163, 9, 0.1);
            border-color: var(--clr-primary-a0);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .stat-title {
            font-size: 14px;
            color: var(--clr-surface-a50);
            font-weight: 500;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--clr-surface-a20);
            color: var(--clr-primary-a10);
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--clr-primary-a0);
            margin-bottom: 8px;
        }

        .stat-change {
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .stat-change.positive {
            color: #4ade80;
        }

        .stat-change.negative {
            color: #f87171;
        }

        /* Data Table */
        .table-container {
            background: linear-gradient(135deg, var(--clr-surface-a10), var(--clr-surface-tonal-a10));
            border-radius: 12px;
            padding: 24px;
            border: 1px solid var(--clr-surface-a20);
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .table-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--clr-primary-a10);
        }

        .search-box {
            position: relative;
            width: 300px;
        }

        .search-box input {
            width: 100%;
            padding: 10px 40px 10px 16px;
            background: var(--clr-surface-a20);
            border: 1px solid var(--clr-surface-a30);
            border-radius: 8px;
            color: var(--clr-light-a0);
            font-size: 14px;
        }

        .search-box i {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--clr-surface-a50);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: var(--clr-surface-a20);
        }

        th {
            padding: 12px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: var(--clr-primary-a10);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 12px;
            border-top: 1px solid var(--clr-surface-a20);
            font-size: 14px;
            color: var(--clr-light-a0);
        }

        tbody tr:hover {
            background: var(--clr-surface-a20);
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .status-badge.active {
            background: rgba(74, 222, 128, 0.1);
            color: #4ade80;
            border: 1px solid #4ade80;
        }

        .status-badge.pending {
            background: rgba(251, 191, 36, 0.1);
            color: #fbbf24;
            border: 1px solid #fbbf24;
        }

        .status-badge.completed {
            background: rgba(96, 165, 250, 0.1);
            color: #60a5fa;
            border: 1px solid #60a5fa;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            border: none;
            background: var(--clr-surface-a20);
            color: var(--clr-light-a0);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            background: var(--clr-primary-a0);
            color: var(--clr-dark-a0);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: var(--clr-surface-a10);
            border-radius: 12px;
            padding: 30px;
            width: 90%;
            max-width: 600px;
            border: 1px solid var(--clr-primary-a0);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--clr-primary-a10);
        }

        .modal-close {
            background: none;
            border: none;
            color: var(--clr-surface-a50);
            font-size: 24px;
            cursor: pointer;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 500;
            color: var(--clr-surface-a50);
        }

        .form-input,
        .form-select {
            width: 100%;
            padding: 10px 16px;
            background: var(--clr-surface-a20);
            border: 1px solid var(--clr-surface-a30);
            border-radius: 8px;
            color: var(--clr-light-a0);
            font-size: 14px;
        }

        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: var(--clr-primary-a0);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: var(--clr-surface-a20);
            border-radius: 8px;
            margin-top: auto;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--clr-primary-a0);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: var(--clr-dark-a0);
        }

        .user-info {
            flex: 1;
        }

        .user-name {
            font-size: 14px;
            font-weight: 600;
        }

        .user-role {
            font-size: 12px;
            color: var(--clr-surface-a50);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                width: 100%;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="logo">
            <i class="fas fa-car"></i>
            <h1>KFZ Pro</h1>
        </div>

        <ul class="nav-menu">
            <li class="nav-item">
                <a href="#" class="nav-link active" data-page="dashboard">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link" data-page="customers">
                    <i class="fas fa-users"></i>
                    <span>Kundenstamm</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link" data-page="vehicles">
                    <i class="fas fa-car-side"></i>
                    <span>Fahrzeugstamm</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link" data-page="invoices">
                    <i class="fas fa-file-invoice"></i>
                    <span>Rechnungen</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link" data-page="orders">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Aufträge</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link" data-page="trade">
                    <i class="fas fa-exchange-alt"></i>
                    <span>An- & Verkauf</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link" data-page="reports">
                    <i class="fas fa-chart-bar"></i>
                    <span>Berichte</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link" data-page="settings">
                    <i class="fas fa-cog"></i>
                    <span>Einstellungen</span>
                </a>
            </li>
        </ul>

        <div class="user-profile">
            <div class="user-avatar">JD</div>
            <div class="user-info">
                <div class="user-name">John Doe</div>
                <div class="user-role">Administrator</div>
            </div>
            <button class="action-btn" onclick="logout()">
                <i class="fas fa-sign-out-alt"></i>
            </button>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="header">
            <h2>Dashboard</h2>
            <div class="header-actions">
                <button class="btn btn-secondary">
                    <i class="fas fa-download"></i>
                    Export
                </button>
                <button class="btn btn-primary" onclick="openModal('new-invoice')">
                    <i class="fas fa-plus"></i>
                    Neue Rechnung
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Gesamtumsatz</span>
                    <div class="stat-icon">
                        <i class="fas fa-euro-sign"></i>
                    </div>
                </div>
                <div class="stat-value">€24,586</div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up"></i>
                    +12.5% gegenüber Vormonat
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Offene Aufträge</span>
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <div class="stat-value">18</div>
                <div class="stat-change negative">
                    <i class="fas fa-arrow-down"></i>
                    -3 seit gestern
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Kunden</span>
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-value">342</div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up"></i>
                    +8 neue diese Woche
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Fahrzeuge</span>
                    <div class="stat-icon">
                        <i class="fas fa-car"></i>
                    </div>
                </div>
                <div class="stat-value">486</div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up"></i>
                    +15 registriert
                </div>
            </div>
        </div>

        <!-- Recent Orders Table -->
        <div class="table-container">
            <div class="table-header">
                <h3 class="table-title">Aktuelle Aufträge</h3>
                <div class="search-box">
                    <input type="text" placeholder="Suche..." id="searchInput">
                    <i class="fas fa-search"></i>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Auftrag Nr.</th>
                        <th>Kunde</th>
                        <th>Fahrzeug</th>
                        <th>Service</th>
                        <th>Betrag</th>
                        <th>Status</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody id="ordersTableBody">
                    <tr>
                        <td>#A2025-001</td>
                        <td>Max Mustermann</td>
                        <td>BMW 320d (B-MM-123)</td>
                        <td>Inspektion</td>
                        <td>€458.00</td>
                        <td><span class="status-badge active">In Bearbeitung</span></td>
                        <td>
                            <div class="action-buttons">
                                <button class="action-btn"><i class="fas fa-eye"></i></button>
                                <button class="action-btn"><i class="fas fa-edit"></i></button>
                                <button class="action-btn"><i class="fas fa-print"></i></button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>#A2025-002</td>
                        <td>Julia Schmidt</td>
                        <td>VW Golf (B-JS-456)</td>
                        <td>Bremsen Service</td>
                        <td>€312.50</td>
                        <td><span class="status-badge pending">Ausstehend</span></td>
                        <td>
                            <div class="action-buttons">
                                <button class="action-btn"><i class="fas fa-eye"></i></button>
                                <button class="action-btn"><i class="fas fa-edit"></i></button>
                                <button class="action-btn"><i class="fas fa-print"></i></button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>#A2025-003</td>
                        <td>Thomas Weber</td>
                        <td>Mercedes C200 (B-TW-789)</td>
                        <td>Ölwechsel</td>
                        <td>€125.00</td>
                        <td><span class="status-badge completed">Abgeschlossen</span></td>
                        <td>
                            <div class="action-buttons">
                                <button class="action-btn"><i class="fas fa-eye"></i></button>
                                <button class="action-btn"><i class="fas fa-edit"></i></button>
                                <button class="action-btn"><i class="fas fa-print"></i></button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Modal -->
    <div class="modal" id="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Neue Rechnung</h3>
                <button class="modal-close" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="modalForm">
                <div class="form-group">
                    <label class="form-label">Kunde</label>
                    <select class="form-select" required>
                        <option value="">Kunde auswählen...</option>
                        <option value="1">Max Mustermann</option>
                        <option value="2">Julia Schmidt</option>
                        <option value="3">Thomas Weber</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Fahrzeug</label>
                    <select class="form-select" required>
                        <option value="">Fahrzeug auswählen...</option>
                        <option value="1">BMW 320d (B-MM-123)</option>
                        <option value="2">VW Golf (B-JS-456)</option>
                        <option value="3">Mercedes C200 (B-TW-789)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Service</label>
                    <input type="text" class="form-input" placeholder="Service beschreibung..." required>
                </div>
                <div class="form-group">
                    <label class="form-label">Betrag (€)</label>
                    <input type="number" class="form-input" placeholder="0.00" step="0.01" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Notizen</label>
                    <textarea class="form-input" rows="3" placeholder="Zusätzliche Notizen..."></textarea>
                </div>
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">
                        Abbrechen
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Speichern
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Console Logging Helper
        const logger = {
            info: (message, data = null) => {
                const timestamp = new Date().toISOString();
                console.log(`%c[INFO] ${timestamp}: ${message}`, 'color: #60a5fa');
                if (data) console.log(data);
            },
            success: (message, data = null) => {
                const timestamp = new Date().toISOString();
                console.log(`%c[SUCCESS] ${timestamp}: ${message}`, 'color: #4ade80');
                if (data) console.log(data);
            },
            warning: (message, data = null) => {
                const timestamp = new Date().toISOString();
                console.warn(`%c[WARNING] ${timestamp}: ${message}`, 'color: #fbbf24');
                if (data) console.log(data);
            },
            error: (message, data = null) => {
                const timestamp = new Date().toISOString();
                console.error(`%c[ERROR] ${timestamp}: ${message}`, 'color: #f87171');
                if (data) console.log(data);
            }
        };

        // Initialize app
        document.addEventListener('DOMContentLoaded', () => {
            logger.info('KFZ Billing System initialized');
            initializeNavigation();
            initializeSearch();
            initializeForm();
        });

        // Navigation
        function initializeNavigation() {
            const navLinks = document.querySelectorAll('.nav-link');
            navLinks.forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const page = link.dataset.page;

                    // Remove active class from all links
                    navLinks.forEach(l => l.classList.remove('active'));
                    link.classList.add('active');

                    logger.info(`Navigating to page: ${page}`);
                    loadPage(page);
                });
            });
        }

        // Page loader
        function loadPage(page) {
            const mainContent = document.querySelector('.main-content');
            const header = mainContent.querySelector('.header h2');

            // Update header based on page
            const pageNames = {
                dashboard: 'Dashboard',
                customers: 'Kundenstamm',
                vehicles: 'Fahrzeugstamm',
                invoices: 'Rechnungen',
                orders: 'Aufträge',
                trade: 'An- & Verkauf',
                reports: 'Berichte',
                settings: 'Einstellungen'
            };

            header.textContent = pageNames[page] || 'Dashboard';
            logger.success(`Page loaded: ${pageNames[page]}`);
        }

        // Search functionality
        function initializeSearch() {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('input', (e) => {
                    const searchTerm = e.target.value.toLowerCase();
                    filterTable(searchTerm);
                });
            }
        }

        function filterTable(searchTerm) {
            const rows = document.querySelectorAll('#ordersTableBody tr');
            let visibleCount = 0;

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            logger.info(`Search filter applied: "${searchTerm}", ${visibleCount} results found`);
        }

        // Modal functions
        function openModal(type) {
            const modal = document.getElementById('modal');
            modal.classList.add('active');
            logger.info(`Modal opened: ${type}`);
        }

        function closeModal() {
            const modal = document.getElementById('modal');
            modal.classList.remove('active');
            logger.info('Modal closed');
        }

        // Form handling
        function initializeForm() {
            const form = document.getElementById('modalForm');
            if (form) {
                form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    handleFormSubmit(form);
                });
            }
        }

        function handleFormSubmit(form) {
            const formData = new FormData(form);
            const data = Object.fromEntries(formData);

            logger.success('Form submitted', data);

            // Here would be the API call to save data
            saveData(data);

            closeModal();
            form.reset();
        }

        // Data operations (placeholder for backend integration)
        function saveData(data) {
            // This would send data to PHP backend
            logger.info('Saving data to backend...', data);

            // Simulate API call
            setTimeout(() => {
                logger.success('Data saved successfully');
                showNotification('Erfolgreich gespeichert!', 'success');
            }, 500);
        }

        // Notification system
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 16px 24px;
                background: ${type === 'success' ? '#4ade80' : type === 'error' ? '#f87171' : '#60a5fa'};
                color: #000;
                border-radius: 8px;
                font-weight: 600;
                z-index: 9999;
                animation: slideIn 0.3s ease;
            `;
            notification.textContent = message;
            document.body.appendChild(notification);

            setTimeout(() => {
                notification.remove();
            }, 3000);
        }

        // Logout function
        function logout() {
            logger.warning('User logging out...');
            if (confirm('Möchten Sie sich wirklich abmelden?')) {
                logger.info('Logout confirmed');
                // Here would be the logout logic
                window.location.href = '/login';
            }
        }

        // Add animation styles
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>

</html>