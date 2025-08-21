class CustomerManager {
  constructor() {
    this.api = new API();
    this.currentPage = 1;
    this.itemsPerPage = 20;
    this.customers = [];
  }

  // Load all customers
  async loadCustomers() {
    try {
      const response = await this.api.get("/customers.php", {
        limit: this.itemsPerPage,
        offset: (this.currentPage - 1) * this.itemsPerPage,
      });

      if (response.success) {
        this.customers = response.data;
        this.renderCustomerTable();
      }
    } catch (error) {
      showNotification("Fehler beim Laden der Kunden", "error");
    }
  }

  // Create new customer
  async createCustomer(customerData) {
    try {
      const response = await this.api.post("/customers.php", customerData);

      if (response.success) {
        showNotification("Kunde erfolgreich angelegt", "success");
        await this.loadCustomers();
        return response.id;
      }
    } catch (error) {
      showNotification("Fehler beim Anlegen des Kunden", "error");
      return false;
    }
  }

  // Update customer
  async updateCustomer(id, customerData) {
    try {
      const response = await this.api.put(
        `/customers.php?id=${id}`,
        customerData
      );

      if (response.success) {
        showNotification("Kunde erfolgreich aktualisiert", "success");
        await this.loadCustomers();
        return true;
      }
    } catch (error) {
      showNotification("Fehler beim Aktualisieren des Kunden", "error");
      return false;
    }
  }

  // Delete customer
  async deleteCustomer(id) {
    if (!confirm("Möchten Sie diesen Kunden wirklich löschen?")) {
      return false;
    }

    try {
      const response = await this.api.delete(`/customers.php?id=${id}`);

      if (response.success) {
        showNotification("Kunde erfolgreich gelöscht", "success");
        await this.loadCustomers();
        return true;
      }
    } catch (error) {
      showNotification("Fehler beim Löschen des Kunden", "error");
      return false;
    }
  }

  // Search customers
  async searchCustomers(searchTerm) {
    try {
      const response = await this.api.get("/customers.php", {
        search: searchTerm,
      });

      if (response.success) {
        this.customers = response.data;
        this.renderCustomerTable();
      }
    } catch (error) {
      showNotification("Fehler bei der Suche", "error");
    }
  }

  // Render customer table
  renderCustomerTable() {
    const tableBody = document.getElementById("customersTableBody");
    if (!tableBody) return;

    tableBody.innerHTML = "";

    this.customers.forEach((customer) => {
      const row = document.createElement("tr");
      row.innerHTML = `
                <td>${customer.id}</td>
                <td>${customer.company_name || "-"}</td>
                <td>${customer.first_name} ${customer.last_name}</td>
                <td>${customer.email || "-"}</td>
                <td>${customer.phone || "-"}</td>
                <td>${customer.city || "-"}</td>
                <td>
                    <div class="action-buttons">
                        <button class="action-btn" onclick="customerManager.viewCustomer(${
                          customer.id
                        })">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="action-btn" onclick="customerManager.editCustomer(${
                          customer.id
                        })">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="action-btn" onclick="customerManager.deleteCustomer(${
                          customer.id
                        })">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            `;
      tableBody.appendChild(row);
    });
  }

  // View customer details
  viewCustomer(id) {
    const customer = this.customers.find((c) => c.id === id);
    if (customer) {
      this.showCustomerModal(customer, "view");
    }
  }

  // Edit customer
  editCustomer(id) {
    const customer = this.customers.find((c) => c.id === id);
    if (customer) {
      this.showCustomerModal(customer, "edit");
    }
  }

  // Show customer modal
  showCustomerModal(customer = {}, mode = "create") {
    const modal = document.getElementById("customerModal");
    const form = document.getElementById("customerForm");

    // Set modal title
    document.getElementById("modalTitle").textContent =
      mode === "create"
        ? "Neuer Kunde"
        : mode === "edit"
        ? "Kunde bearbeiten"
        : "Kundendetails";

    // Fill form fields
    form.elements["company_name"].value = customer.company_name || "";
    form.elements["first_name"].value = customer.first_name || "";
    form.elements["last_name"].value = customer.last_name || "";
    form.elements["email"].value = customer.email || "";
    form.elements["phone"].value = customer.phone || "";
    form.elements["street"].value = customer.street || "";
    form.elements["house_number"].value = customer.house_number || "";
    form.elements["postal_code"].value = customer.postal_code || "";
    form.elements["city"].value = customer.city || "";
    form.elements["tax_id"].value = customer.tax_id || "";
    form.elements["notes"].value = customer.notes || "";

    // Set form mode
    form.dataset.mode = mode;
    form.dataset.customerId = customer.id || "";

    // Disable form fields in view mode
    const inputs = form.querySelectorAll("input, textarea, select");
    inputs.forEach((input) => {
      input.disabled = mode === "view";
    });

    // Show/hide save button
    const saveButton = form.querySelector('button[type="submit"]');
    saveButton.style.display = mode === "view" ? "none" : "block";

    modal.classList.add("active");
  }
}
