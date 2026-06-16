//account.js definiert den User Account mit der Möglichkeit zum Lesen, Erstellen und Löschen der User Dtaen und zeigt auch noch die Bestellhistorie, die ebenfalls einsehbar ist

const IMG_BASE = "/1kR-Webshop/frontend/res/img/";

const ordersList = document.getElementById("ordersList");
const messageBox = document.getElementById("messageBox");

function showMessage(message, type = "danger") {
    messageBox.className = `alert alert-${type}`;
    messageBox.textContent = message;
    messageBox.classList.remove("d-none");
}


function loadAccountdetails() {
    $.ajax({
        url: `${API_BASE}?method=getAccountdetails`,
        method: "GET",
        dataType: "json",
        xhrFields: { withCredentials: true },
        success: function (data) {
            if (!data.success) {
                showMessage(data.message);
                return;
            }

            const u = data.data;
            window._accountData = u;
            window._rawEmail = data.data.email;
            currentPaymentMethods = [...data.payment_methods];

            const methodsList = data.payment_methods.length > 0
                ? data.payment_methods.map(m => `<li class="list-group-item">${m}</li>`).join("")
                : `<li class="list-group-item text-muted">Keine Zahlungsmethoden hinterlegt</li>`;

            document.getElementById("accountDetails").innerHTML = `
                <ul class="list-group mb-4">
                    <li class="list-group-item"><strong>Anrede:</strong> ${u.salutation ?? ""}</li>
                    <li class="list-group-item"><strong>Vorname:</strong> ${u.firstname}</li>
                    <li class="list-group-item"><strong>Nachname:</strong> ${u.lastname}</li>
                    <li class="list-group-item"><strong>E-Mail:</strong> ${u.email}</li>
                    <li class="list-group-item"><strong>Benutzername:</strong> ${u.username}</li>
                    <li class="list-group-item"><strong>Adresse:</strong> ${u.address}, ${u.zip} ${u.city}</li>
                    <li class="list-group-item"><strong>Passwort:</strong> ${u.password}</li>
                </ul>
                 <h5>Zahlungsmethoden</h5>
                 <ul class="list-group mb-4">
                    ${methodsList}
                 </ul>
            `;
        },
        error: function () {
            showMessage("Accountdaten konnten nicht geladen werden.");
        }
    });
}

loadAccountdetails();

let currentPaymentMethods = [];

document.getElementById("editBtn").addEventListener("click", () => {
    const u = window._accountData;
    document.getElementById("form-salutation").value = u.salutation ?? "";
    document.getElementById("form-firstname").value = u.firstname;
    document.getElementById("form-lastname").value = u.lastname;
    document.getElementById("form-email").value = window._rawEmail;
    document.getElementById("form-username").value = u.username;
    document.getElementById("form-address").value = u.address;
    document.getElementById("form-zip").value = u.zip;
    document.getElementById("form-city").value = u.city;
    document.getElementById("form-password").value = "";

    renderPaymentList();

    document.getElementById("accountDetails").classList.add("d-none");
    document.getElementById("editBtn").classList.add("d-none");
    document.getElementById("accountForm").classList.remove("d-none");
});

document.getElementById("cancelBtn").addEventListener("click", () => {
    messageBox.classList.add("d-none");
    document.getElementById("accountForm").classList.add("d-none");
    document.getElementById("accountDetails").classList.remove("d-none");
    document.getElementById("editBtn").classList.remove("d-none");
});

document.getElementById("addPaymentBtn").addEventListener("click", () => {
    const val = document.getElementById("form-new-payment").value.trim();
    if (val === "") return;
    if (currentPaymentMethods.includes(val)) {
        showMessage("Diese Zahlungsmethode ist bereits hinzugefügt.", "warning");
        return;
    }
    currentPaymentMethods.push(val);
    renderPaymentList();
});

function renderPaymentList() {
    const list = document.getElementById("form-payment-list");
    list.innerHTML = currentPaymentMethods.map((m, i) => `
        <li class="list-group-item d-flex justify-content-between align-items-center">
            ${m}
            <button class="btn btn-danger btn-sm" onclick="removePayment(${i})">Entfernen</button>
        </li>
    `).join("");
}

function removePayment(index) {
    currentPaymentMethods.splice(index, 1);
    renderPaymentList();
}

document.getElementById("saveBtn").addEventListener("click", () => {
    const password = document.getElementById("form-password").value;
    if (password === "") {
        showMessage("Bitte Passwort eingeben.");
        return;
    }
    $.ajax({
        url: `${API_BASE}?method=editAccount`,
        method: "POST",
        contentType: "application/json",
        xhrFields: {withCredentials: true},
        data: JSON.stringify({
            salutation: document.getElementById("form-salutation").value,
            firstname: document.getElementById("form-firstname").value.trim(),
            lastname: document.getElementById("form-lastname").value.trim(),
            email: document.getElementById("form-email").value.trim(),
            username: document.getElementById("form-username").value.trim(),
            address: document.getElementById("form-address").value.trim(),
            zip: document.getElementById("form-zip").value.trim(),
            city: document.getElementById("form-city").value.trim(),
            password: password,
            payment_methods: currentPaymentMethods
        }),
        success: function (data) {
            if (!data.success) {
                showMessage(data.message);
                document.getElementById("form-password").value = "";
                return;
            }
            showMessage("Daten erfolgreich gespeichert.", "success");
            document.getElementById("accountForm").classList.add("d-none");
            document.getElementById("accountDetails").classList.remove("d-none");
            document.getElementById("editBtn").classList.remove("d-none");
            loadAccountdetails(); // Ansicht neu laden
        },
        error: function () {
            showMessage("Fehler beim Speichern.");
        }
    });
});

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
                            <span>
                                Bestellung #${o.id} – ${formatDate(o.order_date)}
                                ${o.status === "storniert" ? `<span class="badge bg-danger ms-2">Storniert</span>` : ""}
                            </span>
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
        <div class="mt-3">
            <a href="invoice.html?order=${order.id}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-printer"></i> Rechnung drucken
            </a>
        </div>
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
