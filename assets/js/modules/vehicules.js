class VehicleManager {
  constructor() {
    this.api = new API();
    this.vehicles = [];
  }

  async loadVehicles(customerId = null) {
    try {
      const params = customerId ? { customer_id: customerId } : {};
      const response = await this.api.get("/vehicles.php", params);

      if (response.success) {
        this.vehicles = response.data;
        this.renderVehicleTable();
      }
    } catch (error) {
      showNotification("Fehler beim Laden der Fahrzeuge", "error");
    }
  }

  async createVehicle(vehicleData) {
    try {
      const response = await this.api.post("/vehicles.php", vehicleData);

      if (response.success) {
        showNotification("Fahrzeug erfolgreich angelegt", "success");
        await this.loadVehicles();
        return response.id;
      }
    } catch (error) {
      showNotification("Fehler beim Anlegen des Fahrzeugs", "error");
      return false;
    }
  }

  renderVehicleTable() {
    const tableBody = document.getElementById("vehiclesTableBody");
    if (!tableBody) return;

    tableBody.innerHTML = "";

    this.vehicles.forEach((vehicle) => {
      const row = document.createElement("tr");
      row.innerHTML = `
                <td>${vehicle.license_plate}</td>
                <td>${vehicle.manufacturer || "-"}</td>
                <td>${vehicle.model || "-"}</td>
                <td>${vehicle.year || "-"}</td>
                <td>${
                  vehicle.mileage
                    ? vehicle.mileage.toLocaleString() + " km"
                    : "-"
                }</td>
                <td>${vehicle.customer_name || "-"}</td>
                <td>
                    <div class="action-buttons">
                        <button class="action-btn" onclick="vehicleManager.viewVehicle(${
                          vehicle.id
                        })">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="action-btn" onclick="vehicleManager.editVehicle(${
                          vehicle.id
                        })">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="action-btn" onclick="vehicleManager.deleteVehicle(${
                          vehicle.id
                        })">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            `;
      tableBody.appendChild(row);
    });
  }
}
