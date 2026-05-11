const IMG_BASE = "http://localhost:8888/1kR-Webshop/backend/product_pictures/";

const cartContent = document.getElementById("cartContent");
const messageBox = document.getElementById("messageBox");

function showMessage(message, type = "danger") {
    messageBox.className = `alert alert-${type}`;
    messageBox.textContent = message;
    messageBox.classList.remove("d-none");
}

function renderCart(items, total) {
    if (items.length === 0) {
        cartContent.innerHTML = `<p class="text-muted">Dein Warenkorb ist leer.</p>`;
        return;
    }

    let rows = "";
    for (const it of items) {
        rows += `
            <tr>
                <td><img src="${IMG_BASE}${it.image}" alt="${it.name}" style="width:60px;height:auto;"></td>
                <td>${it.name}</td>
                <td>${it.price.toFixed(2)} €</td>
                <td>${it.quantity}</td>
                <td class="fw-bold">${it.subtotal.toFixed(2)} €</td>
            </tr>
        `;
    }

    cartContent.innerHTML = `
        <table class="table align-middle bg-white shadow-sm">
            <thead>
                <tr>
                    <th>Bild</th>
                    <th>Produkt</th>
                    <th>Preis</th>
                    <th>Anzahl</th>
                    <th>Zwischensumme</th>
                </tr>
            </thead>
            <tbody>${rows}</tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="text-end fw-bold">Gesamt:</td>
                    <td class="fw-bold">${total.toFixed(2)} €</td>
                </tr>
            </tfoot>
        </table>
    `;
}

function loadCart() {
    $.ajax({
        url: `${API_BASE}?method=getCart`,
        method: "GET",
        dataType: "json",
        xhrFields: { withCredentials: true },
        success: function (data) {
            if (!data.success) {
                showMessage(data.message);
                return;
            }
            renderCart(data.data.items, data.data.total);
        },
        error: function () {
            showMessage("Warenkorb konnte nicht geladen werden.");
        }
    });
}

loadCart();
