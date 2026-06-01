const IMG_BASE = "/1kR-Webshop/frontend/res/img/";

const ordersList = document.getElementById("ordersList");
const messageBox = document.getElementById("messageBox");

function showMessage(message, type = "danger") {
    messageBox.className = `alert alert-${type}`;
    messageBox.textContent = message;
    messageBox.classList.remove("d-none");
}

function formatDate(dateStr) {
    const d = new Date(dateStr.replace(" ", "T"));
    return d.toLocaleDateString("de-DE") + " " + d.toLocaleTimeString("de-DE", { hour: "2-digit", minute: "2-digit" });
}

function renderOrders(orders) {
    if (orders.length === 0) {
        ordersList.innerHTML = `<p class="text-muted">Du hast noch keine Bestellungen.</p>`;
        return;
    }

    let html = `<div class="accordion" id="ordersAccordion">`;
    for (const o of orders) {
        const collapseId = `order-${o.id}`;
        html += `
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button"
                            data-bs-toggle="collapse" data-bs-target="#${collapseId}">
                        <div class="d-flex justify-content-between w-100 me-3">
                            <span>Bestellung #${o.id} – ${formatDate(o.order_date)}</span>
                            <span class="fw-bold">${o.total.toFixed(2)} € · ${o.payment_method}</span>
                        </div>
                    </button>
                </h2>
                <div id="${collapseId}" class="accordion-collapse collapse" data-bs-parent="#ordersAccordion">
                    <div class="accordion-body" data-order-id="${o.id}">
                        <p class="text-muted small">Details werden geladen ...</p>
                    </div>
                </div>
            </div>
        `;
    }
    html += `</div>`;

    ordersList.innerHTML = html;

    // Beim Aufklappen Details nachladen
    document.querySelectorAll(".accordion-collapse").forEach(el => {
        el.addEventListener("show.bs.collapse", function () {
            const body = this.querySelector(".accordion-body");
            const orderId = body.dataset.orderId;
            if (!body.dataset.loaded) {
                loadOrderDetails(orderId, body);
            }
        });
    });
}

function loadOrderDetails(orderId, body) {
    $.ajax({
        url: `${API_BASE}?method=getOrderDetails&order=${orderId}`,
        method: "GET",
        dataType: "json",
        xhrFields: { withCredentials: true },
        success: function (data) {
            if (!data.success) {
                body.innerHTML = `<p class="text-danger">${data.message}</p>`;
                return;
            }
            renderOrderDetails(data.data.order, data.data.items, body);
            body.dataset.loaded = "1";
        },
        error: function () {
            body.innerHTML = `<p class="text-danger">Details konnten nicht geladen werden.</p>`;
        }
    });
}

function renderOrderDetails(order, items, body) {
    let rows = "";
    for (const it of items) {
        rows += `
            <tr>
                <td><img src="${IMG_BASE}${it.image}" alt="${it.name}" style="width:50px;height:auto;"></td>
                <td>${it.name}</td>
                <td>${it.quantity}×</td>
                <td>${it.price.toFixed(2)} €</td>
                <td class="text-end fw-bold">${it.subtotal.toFixed(2)} €</td>
            </tr>
        `;
    }

    body.innerHTML = `
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <strong>Lieferadresse:</strong><br>
                ${order.firstname} ${order.lastname}<br>
                ${order.address}<br>
                ${order.zip} ${order.city}
            </div>
            <div class="col-md-6">
                <strong>Zahlungsart:</strong> ${order.payment_method}<br>
                <strong>Bestelldatum:</strong> ${formatDate(order.order_date)}
            </div>
        </div>
        <table class="table align-middle bg-white">
            <thead>
                <tr>
                    <th>Bild</th>
                    <th>Produkt</th>
                    <th>Menge</th>
                    <th>Einzelpreis</th>
                    <th class="text-end">Zwischensumme</th>
                </tr>
            </thead>
            <tbody>${rows}</tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="text-end fw-bold">Gesamt:</td>
                    <td class="text-end fw-bold">${order.total.toFixed(2)} €</td>
                </tr>
            </tfoot>
        </table>
    `;
}

function loadOrders() {
    $.ajax({
        url: `${API_BASE}?method=getOrders`,
        method: "GET",
        dataType: "json",
        xhrFields: { withCredentials: true },
        success: function (data) {
            if (!data.success) {
                if (data.message === "Nicht eingeloggt") {
                    window.location.href = "login.html";
                    return;
                }
                showMessage(data.message);
                return;
            }
            renderOrders(data.data);
        },
        error: function () {
            showMessage("Bestellungen konnten nicht geladen werden.");
        }
    });
}

loadOrders();
