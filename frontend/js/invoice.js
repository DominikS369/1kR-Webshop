//invoice.js lädt und rendert für User die ausgewählte Rechnung und lässt diese auch per Print methode drucken

const invoiceContent = document.getElementById("invoiceContent");
const messageBox = document.getElementById("messageBox");
const printBtn = document.getElementById("printBtn");

function showMessage(message, type = "danger") {
    messageBox.className = `alert alert-${type} no-print`;
    messageBox.textContent = message;
    messageBox.classList.remove("d-none");
}

function formatDate(dateStr) {
    const d = new Date(dateStr.replace(" ", "T"));
    return d.toLocaleDateString("de-DE");
}

function renderInvoice(invoice, order, items) {
    let rows = "";
    for (const it of items) {
        rows += `
            <tr>
                <td>${it.name}</td>
                <td class="text-end">${it.quantity}</td>
                <td class="text-end">${it.price.toFixed(2)} €</td>
                <td class="text-end">${it.subtotal.toFixed(2)} €</td>
            </tr>
        `;
    }

    invoiceContent.innerHTML = `
        <div class="d-flex justify-content-between mb-4">
            <div>
                <h2 class="h4 mb-1">Tausend Rosen Webshop</h2>
                <p class="text-muted small mb-0">Merchandise für Tausend Rosen Fans</p>
            </div>
            <div class="text-end">
                <h2 class="h4 mb-1">Rechnung</h2>
                <p class="mb-0"><strong>${invoice.invoice_number}</strong></p>
                <p class="text-muted small mb-0">Rechnungsdatum: ${formatDate(invoice.invoice_date)}</p>
            </div>
        </div>

        <div class="mb-4">
            <strong>Rechnung an:</strong><br>
            ${order.firstname} ${order.lastname}<br>
            ${order.address}<br>
            ${order.zip} ${order.city}
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>Produkt</th>
                    <th class="text-end">Menge</th>
                    <th class="text-end">Einzelpreis</th>
                    <th class="text-end">Summe</th>
                </tr>
            </thead>
            <tbody>${rows}</tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="text-end fw-bold">Gesamt:</td>
                    <td class="text-end fw-bold">${order.total.toFixed(2)} €</td>
                </tr>
            </tfoot>
        </table>

        <div class="mt-4 small text-muted">
            <strong>Zahlungsart:</strong> ${order.payment_method}<br>
            <strong>Bestelldatum:</strong> ${formatDate(order.order_date)}
        </div>
    `;
}

function loadInvoice() {
    const params = new URLSearchParams(window.location.search);
    const orderId = parseInt(params.get("order"), 10);

    if (!orderId) {
        showMessage("Keine Bestellung angegeben.");
        return;
    }

    $.ajax({
        url: `${API_BASE}?method=getInvoice&order=${orderId}`,
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
            renderInvoice(data.data.invoice, data.data.order, data.data.items);
        },
        error: function () {
            showMessage("Rechnung konnte nicht geladen werden.");
        }
    });
}

printBtn.addEventListener("click", function () {
    window.print();
});

loadInvoice();
