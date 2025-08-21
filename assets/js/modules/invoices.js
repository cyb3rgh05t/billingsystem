class InvoiceManager {
  constructor() {
    this.api = new API();
    this.invoices = [];
  }

  async loadInvoices() {
    try {
      const response = await this.api.get("/invoices.php");

      if (response.success) {
        this.invoices = response.data;
        this.renderInvoiceTable();
        this.updateStatistics();
      }
    } catch (error) {
      showNotification("Fehler beim Laden der Rechnungen", "error");
    }
  }

  async createInvoice(invoiceData) {
    try {
      const response = await this.api.post("/invoices.php", invoiceData);

      if (response.success) {
        showNotification("Rechnung erfolgreich erstellt", "success");
        await this.loadInvoices();
        return response.id;
      }
    } catch (error) {
      showNotification("Fehler beim Erstellen der Rechnung", "error");
      return false;
    }
  }

  async markAsPaid(invoiceId) {
    try {
      const response = await this.api.put(`/invoices.php?id=${invoiceId}`, {
        status: "paid",
        paid_date: new Date().toISOString().split("T")[0],
      });

      if (response.success) {
        showNotification("Rechnung als bezahlt markiert", "success");
        await this.loadInvoices();
      }
    } catch (error) {
      showNotification("Fehler beim Aktualisieren der Rechnung", "error");
    }
  }

  async printInvoice(invoiceId) {
    try {
      window.open(`/print/invoice.php?id=${invoiceId}`, "_blank");
      logger.info(`Printing invoice ${invoiceId}`);
    } catch (error) {
      showNotification("Fehler beim Drucken der Rechnung", "error");
    }
  }

  renderInvoiceTable() {
    const tableBody = document.getElementById("invoicesTableBody");
    if (!tableBody) return;

    tableBody.innerHTML = "";

    this.invoices.forEach((invoice) => {
      const statusClass =
        invoice.status === "paid"
          ? "completed"
          : invoice.status === "overdue"
          ? "negative"
          : "pending";

      const row = document.createElement("tr");
      row.innerHTML = `
                <td>${invoice.invoice_number}</td>
                <td>${invoice.customer_name}</td>
                <td>${new Date(invoice.created_at).toLocaleDateString(
                  "de-DE"
                )}</td>
                <td>${
                  invoice.due_date
                    ? new Date(invoice.due_date).toLocaleDateString("de-DE")
                    : "-"
                }</td>
                <td>€${parseFloat(invoice.total_amount).toFixed(2)}</td>
                <td><span class="status-badge ${statusClass}">${this.getStatusText(
        invoice.status
      )}</span></td>
                <td>
                    <div class="action-buttons">
                        <button class="action-btn" onclick="invoiceManager.viewInvoice(${
                          invoice.id
                        })">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="action-btn" onclick="invoiceManager.printInvoice(${
                          invoice.id
                        })">
                            <i class="fas fa-print"></i>
                        </button>
                        ${
                          invoice.status !== "paid"
                            ? `
                            <button class="action-btn" onclick="invoiceManager.markAsPaid(${invoice.id})">
                                <i class="fas fa-check"></i>
                            </button>
                        `
                            : ""
                        }
                    </div>
                </td>
            `;
      tableBody.appendChild(row);
    });
  }

  getStatusText(status) {
    const statusTexts = {
      unpaid: "Offen",
      paid: "Bezahlt",
      overdue: "Überfällig",
      cancelled: "Storniert",
    };
    return statusTexts[status] || status;
  }

  updateStatistics() {
    const stats = {
      total: this.invoices.length,
      paid: this.invoices.filter((i) => i.status === "paid").length,
      unpaid: this.invoices.filter((i) => i.status === "unpaid").length,
      totalAmount: this.invoices.reduce(
        (sum, i) => sum + parseFloat(i.total_amount || 0),
        0
      ),
      paidAmount: this.invoices
        .filter((i) => i.status === "paid")
        .reduce((sum, i) => sum + parseFloat(i.total_amount || 0), 0),
    };

    // Update dashboard stats if elements exist
    const totalInvoicesEl = document.getElementById("totalInvoices");
    const unpaidInvoicesEl = document.getElementById("unpaidInvoices");
    const totalRevenueEl = document.getElementById("totalRevenue");

    if (totalInvoicesEl) totalInvoicesEl.textContent = stats.total;
    if (unpaidInvoicesEl) unpaidInvoicesEl.textContent = stats.unpaid;
    if (totalRevenueEl)
      totalRevenueEl.textContent = `€${stats.totalAmount.toFixed(2)}`;
  }
}
