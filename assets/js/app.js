// Global logger instance
const logger = {
  info: (message, data = null) => {
    const timestamp = new Date().toISOString();
    console.log(`%c[INFO] ${timestamp}: ${message}`, "color: #60a5fa");
    if (data) console.log(data);
  },
  success: (message, data = null) => {
    const timestamp = new Date().toISOString();
    console.log(`%c[SUCCESS] ${timestamp}: ${message}`, "color: #4ade80");
    if (data) console.log(data);
  },
  warning: (message, data = null) => {
    const timestamp = new Date().toISOString();
    console.warn(`%c[WARNING] ${timestamp}: ${message}`, "color: #fbbf24");
    if (data) console.log(data);
  },
  error: (message, data = null) => {
    const timestamp = new Date().toISOString();
    console.error(`%c[ERROR] ${timestamp}: ${message}`, "color: #f87171");
    if (data) console.log(data);
  },
};

// Global notification system
function showNotification(message, type = "info") {
  const notification = document.createElement("div");

  const colors = {
    success: "#4ade80",
    error: "#f87171",
    warning: "#fbbf24",
    info: "#60a5fa",
  };

  notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 16px 24px;
        background: ${colors[type] || colors.info};
        color: #000;
        border-radius: 8px;
        font-weight: 600;
        z-index: 9999;
        animation: slideIn 0.3s ease;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    `;

  notification.textContent = message;
  document.body.appendChild(notification);

  setTimeout(() => {
    notification.style.animation = "slideOut 0.3s ease";
    setTimeout(() => notification.remove(), 300);
  }, 3000);
}

// Initialize managers
let customerManager, vehicleManager, invoiceManager;

// Initialize application
document.addEventListener("DOMContentLoaded", () => {
  logger.info("KFZ Billing Pro Application initialized");

  // Initialize managers
  customerManager = new CustomerManager();
  vehicleManager = new VehicleManager();
  invoiceManager = new InvoiceManager();

  // Initialize navigation
  initializeNavigation();

  // Load initial data based on current page
  loadCurrentPageData();

  // Setup global event listeners
  setupEventListeners();
});

// Navigation handler
function initializeNavigation() {
  const navLinks = document.querySelectorAll(".nav-link");

  navLinks.forEach((link) => {
    link.addEventListener("click", (e) => {
      e.preventDefault();
      const page = link.dataset.page;

      // Update active state
      navLinks.forEach((l) => l.classList.remove("active"));
      link.classList.add("active");

      // Load page content
      loadPage(page);
    });
  });
}

// Page loader
function loadPage(page) {
  logger.info(`Loading page: ${page}`);

  // Update URL without reload
  history.pushState({ page }, "", `#${page}`);

  // Load page specific data
  switch (page) {
    case "customers":
      customerManager.loadCustomers();
      break;
    case "vehicles":
      vehicleManager.loadVehicles();
      break;
    case "invoices":
      invoiceManager.loadInvoices();
      break;
    case "dashboard":
      loadDashboard();
      break;
    default:
      logger.warning(`Unknown page: ${page}`);
  }
}

// Load dashboard data
async function loadDashboard() {
  try {
    const api = new API();
    const response = await api.get("/dashboard.php");

    if (response.success) {
      updateDashboardStats(response.data);
    }
  } catch (error) {
    logger.error("Failed to load dashboard data", error);
  }
}

// Update dashboard statistics
function updateDashboardStats(data) {
  // Update stat cards
  if (data.stats) {
    Object.keys(data.stats).forEach((key) => {
      const element = document.getElementById(key);
      if (element) {
        element.textContent = data.stats[key];
      }
    });
  }

  // Update charts if available
  if (data.chartData && typeof updateCharts === "function") {
    updateCharts(data.chartData);
  }
}

// Setup global event listeners
function setupEventListeners() {
  // Search functionality
  const searchInput = document.getElementById("globalSearch");
  if (searchInput) {
    searchInput.addEventListener(
      "input",
      debounce((e) => {
        const searchTerm = e.target.value;
        performGlobalSearch(searchTerm);
      }, 300)
    );
  }

  // Form submissions
  const forms = document.querySelectorAll("form[data-ajax]");
  forms.forEach((form) => {
    form.addEventListener("submit", handleFormSubmit);
  });
}

// Form submission handler
async function handleFormSubmit(e) {
  e.preventDefault();

  const form = e.target;
  const formData = new FormData(form);
  const data = Object.fromEntries(formData);

  const mode = form.dataset.mode;
  const entityType = form.dataset.entity;

  logger.info(`Submitting ${entityType} form in ${mode} mode`, data);

  try {
    let result;

    switch (entityType) {
      case "customer":
        if (mode === "edit") {
          result = await customerManager.updateCustomer(
            form.dataset.customerId,
            data
          );
        } else {
          result = await customerManager.createCustomer(data);
        }
        break;
      case "vehicle":
        if (mode === "edit") {
          result = await vehicleManager.updateVehicle(
            form.dataset.vehicleId,
            data
          );
        } else {
          result = await vehicleManager.createVehicle(data);
        }
        break;
      case "invoice":
        result = await invoiceManager.createInvoice(data);
        break;
    }

    if (result) {
      closeModal();
      form.reset();
    }
  } catch (error) {
    logger.error("Form submission failed", error);
    showNotification("Fehler beim Speichern", "error");
  }
}

// Utility functions
function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

function closeModal() {
  const modals = document.querySelectorAll(".modal.active");
  modals.forEach((modal) => modal.classList.remove("active"));
}

function loadCurrentPageData() {
  const hash = window.location.hash.substring(1) || "dashboard";
  const navLink = document.querySelector(`[data-page="${hash}"]`);
  if (navLink) {
    navLink.click();
  }
}

// Export for global access
window.customerManager = customerManager;
window.vehicleManager = vehicleManager;
window.invoiceManager = invoiceManager;
window.showNotification = showNotification;
window.closeModal = closeModal;
