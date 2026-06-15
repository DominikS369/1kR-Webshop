
const userList = document.getElementById("userTable");
const messageBox = document.getElementById("messageBox");
const userModal = document.getElementById("userModal");
let allUsers = [];


function showMessage(message, type = "danger") {
    messageBox.className = `alert alert-${type}`;
    messageBox.textContent = message;
    messageBox.classList.remove("d-none");
    setTimeout(() => messageBox.classList.add("d-none"), 3000);
}


function renderUsers(users){
    if(users.length === 0){
        userList.innerHTML = "<p>keine Kunden gefunden</p>";
        return;
    }
    let html = "";
    for(const u of users){
         html += `
        <tr id="row-${u.id}">
            <td>${u.id}</td>
            <td>${u.firstname} ${u.lastname}</td>
            <td>${u.email}</td>
            <td>${u.username}</td>
            <td>
                <span class="badge ${u.is_active == 1 ? 'bg-success' : 'bg-danger'}">
                    ${u.is_active == 1 ? 'Aktiv' : 'Deaktiviert'}
                </span>
            </td>
            <td>
                <button class="btn btn-dark btn-s" onclick="showDetails(${u.id})">
                    Details
                </button>
                <button class="btn btn-sm ${u.is_active == 1 ? 'btn-outline-danger' : 'btn-outline-success'}"
                        onclick="toggleUser(${u.id}, ${u.is_active})">
                    ${u.is_active == 1 ? 'Deaktivieren' : 'Aktivieren'}
                </button>
            </td>
        </tr>`;

    }
    userList.innerHTML = html;

}

function showDetails(userId) {
    const u = allUsers.find(u => u.id == userId);
    if (!u) return;
    const existingRow = document.getElementById(`detail-${userId}`);

    if (existingRow) {
        existingRow.remove();
        return;
    }

    const detailRow = document.createElement("tr");
    detailRow.id = `detail-${userId}`;
    detailRow.innerHTML = `
        <td colspan="6" class="bg-light">
            <p><strong>Adresse:</strong> ${u.address ?? "-"}, ${u.zip ?? "-"} ${u.city ?? "-"}</p>
            <p><strong>Zahlungsart:</strong> ${u.payment_info ?? "-"}</p>
            <p><strong>Rolle:</strong> ${u.is_admin == 1 ? "Admin" : "Kunde"}</p>
            <h6>Bestellungen</h6>
            <div id="orderList-${userId}"></div>
        </td>`;

    document.getElementById(`row-${userId}`).insertAdjacentElement("afterend", detailRow);

    loadUserOrders(userId);
}

function loadUserOrders(userId) {
    $.ajax({
        url: API_BASE + "?method=getUserOrders&user_id=" + userId,
        method: "GET",
        dataType: "json",
        xhrFields: { withCredentials: true },
        success: function (data) {
            if (!data.success || data.orders.length === 0) {
                document.getElementById(`orderList-${userId}`).innerHTML = "<p class='text-muted'>Keine Bestellungen vorhanden.</p>";
                return;
            }

            let html = `<table class="table table-sm">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Datum</th>
                        <th>Lieferadresse</th>
                        <th>Zahlung</th>
                        <th>Total</th>
                        <th>Positionen</th>
                        <th>Aktion</th>
                    </tr>
                </thead><tbody>`;

            for (const o of data.orders) {
                html += `
                <tr>
                    <td>${o.id}</td>
                    <td>${new Date(o.order_date).toLocaleDateString("de-AT")}</td>
                    <td>${o.firstname} ${o.lastname}, ${o.address}, ${o.zip} ${o.city}</td>
                    <td>${o.payment_method}</td>
                    <td>${parseFloat(o.total).toFixed(2)} €</td>
                    <td>
                        <button class="btn btn-sm btn-outline-secondary" onclick="toggleOrderItems(${o.id}, ${userId})">
                            <i class="bi bi-list"></i>
                        </button>
                    </td>
                    <td>
                        ${o.status === "storniert"
                            ? `<span class="badge bg-danger">Storniert</span>`
                            : `<button class="btn btn-sm btn-outline-danger" onclick="cancelOrder(${o.id}, ${userId})">Stornieren</button>`}
                    </td>
                </tr>
                <tr id="items-${o.id}"></tr>`;
            }

            html += `</tbody></table>`;
            document.getElementById(`orderList-${userId}`).innerHTML = html;
        },
        error: function () {
            document.getElementById(`orderList-${userId}`).innerHTML = "<p class='text-danger'>Bestellungen konnten nicht geladen werden.</p>";
        }
    });
}

function toggleOrderItems(orderId, userId) {
    const row = document.getElementById(`items-${orderId}`);

    if (row.innerHTML !== "") {
        row.innerHTML = "";
        return;
    }

    $.ajax({
        url: API_BASE + "?method=getOrderItems&order_id=" + orderId,
        method: "GET",
        dataType: "json",
        xhrFields: { withCredentials: true },
        success: function (data) {
            if (!data.success || data.items.length === 0) {
                row.innerHTML = `<td colspan="7" class="text-muted ps-4">Keine Positionen.</td>`;
                return;
            }

            let html = `<td colspan="7" class="p-0">
                <table class="table table-sm mb-0 table-bordered">
                    <thead class="table-secondary">
                        <tr>
                            <th>Produkt</th>
                            <th>Menge</th>
                            <th>Preis</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead><tbody>`;

            for (const item of data.items) {
                html += `
                <tr id="item-row-${item.id}">
                    <td>${item.product_name}</td>
                    <td>${item.quantity}</td>
                    <td>${parseFloat(item.price).toFixed(2)} €</td>
                    <td>
                        <button class="btn btn-outline-danger"
                            data-item-id="${item.id}"
                            data-order-id="${orderId}"
                            data-user-id="${userId}"
                            onclick="removeOrderItem(this)"> 
                            Entfernen
                        </button>
                    </td>
                </tr>`;
            }

            html += `</tbody></table></td>`;
            row.innerHTML = html;
        },
        error: function () {
            row.innerHTML = `<td colspan="7" class="text-danger">Fehler beim Laden.</td>`;
        }
    });
}

function cancelOrder(orderId, userId) {
    if (!confirm(`Bestellung #${orderId} wirklich stornieren?`)) return;

    $.ajax({
        url: API_BASE + "?method=cancelOrder",
        method: "POST",
        dataType: "json",
        xhrFields: { withCredentials: true },
        data: { order_id: orderId },
        success: function (data) {
            if (!data.success) {
                showMessage(data.message);
                return;
            }
            showMessage("Bestellung storniert.", "success");
            loadUserOrders(userId);
        },
        error: function () {
            showMessage("Fehler beim Stornieren der Bestellung.");
        }
    });
}

function removeOrderItem(btn) {
    const itemId = btn.dataset.itemId;
    const orderId = btn.dataset.orderId;
    const userId = btn.dataset.userId;

    if (!confirm("Produkt wirklich entfernen?")) return;

    $.ajax({
        url: API_BASE + "?method=removeOrderItem",
        method: "POST",
        dataType: "json",
        xhrFields: { withCredentials: true },
        data: { item_id: itemId },
        success: function (data) {
            if (!data.success) {
                showMessage(data.message);
                return;
            }
            document.getElementById(`item-row-${itemId}`).remove();
            showMessage("Produkt entfernt.", "success");
        },
        error: function () {
            showMessage("Fehler beim Entfernen.");
        }
    });
}



function toggleUser(userId, currentStatus) {
    const newStatus = currentStatus == 1 ? 0 : 1;
    if (!confirm(newStatus == 0 ? "Wirklich deaktivieren?" : "Wirklich aktivieren?")) return;

    $.ajax({
        url: API_BASE + "?method=toggleUser",
        method: "POST",
        dataType: "json",
        xhrFields: { withCredentials: true },
        data: { user_id: userId, is_active: newStatus },
        success: function (data) {
            if (!data.success) {
                showMessage(data.message);
                return;
            }
            showMessage(newStatus == 1 ? "Kunde aktiviert." : "Kunde deaktiviert.", "success");
            loadUsers();
        },
        error: function () {
            showMessage("Fehler beim Aktualisieren.");
        }
    });
}
function loadUsers(){
    $.ajax({
        url: API_BASE + "?method=getAllUsers",
        method: "GET",
        dataType: "json",
        xhrFields: { withCredentials: true },

        success: function (data) {
            if (!data.success) {
                showMessage(data.message);
                return;
            }
            allUsers = data.users;
            renderUsers(allUsers);
            console.log(data.users[0]);
        },
        error: function (xhr) {
            showMessage("Kundendaten konnten nicht geladen werden.");
        }

    });
}

loadUsers();



