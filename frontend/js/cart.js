//cart.js lässt User den Warenkorb Inhalt anzeigen, bearbeiten und löschen

const IMG_BASE = "/1kR-Webshop/frontend/res/img/";

const cartContent = document.getElementById("cartContent");
const messageBox = document.getElementById("messageBox");
const orderArea = document.getElementById("orderArea");

let loggedIn = false;
let cartHasItems = false;

function showMessage(message, type = "danger") {
    messageBox.className = `alert alert-${type}`;
    messageBox.textContent = message;
    messageBox.classList.remove("d-none");
}

function renderCart(items, total) {
    cartHasItems = items.length > 0;
    updateOrderButton();

    if (items.length === 0) {
        cartContent.innerHTML = `<p class="text-muted">Dein Warenkorb ist leer.</p>`;
        return;
    }

    let rows = "";
    for (const it of items) {
        rows += `
            <tr>
                <td><img src="${IMG_BASE}${it.image}" class="card-img-top" alt="${it.name}" draggable="false" style="width:60px;height:auto;"></td>
                <td>${it.name}</td>
                <td>${it.price.toFixed(2)} €</td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <button class="btn btn-outline-secondary btn-sm" onclick="changeQuantity(${it.id}, ${it.quantity - 1})">−</button>
                        <span>${it.quantity}</span>
                        <button class="btn btn-outline-secondary btn-sm" onclick="changeQuantity(${it.id}, ${it.quantity + 1})">+</button>
                    </div>
                </td>
                <td class="fw-bold">${it.subtotal.toFixed(2)} €</td>
                <td>
                    <button class="btn btn-danger btn-sm" onclick="removeItem(${it.id})">Entfernen</button>
                </td>
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
                    <th></th>
                </tr>
            </thead>
            <tbody>${rows}</tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="text-end fw-bold">Gesamt:</td>
                    <td class="fw-bold">${total.toFixed(2)} €</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    `;
}

function changeQuantity(cartId, newQuantity) {
    if (newQuantity <= 0) {
        removeItem(cartId);
        return;
    }

    $.ajax({
        url: `${API_BASE}?method=updateCart`,
        method: "POST",
        contentType: "application/json",
        dataType: "json",
        xhrFields: { withCredentials: true },
        data: JSON.stringify({ cart_id: cartId, quantity: newQuantity }),
        success: function (data) {
            if (!data.success) {
                showMessage(data.message);
                return;
            }
            loadCart();
            if (typeof updateCartCount === "function") updateCartCount();
        },
        error: function () {
            showMessage("Fehler beim Aktualisieren des Warenkorbs.");
        }
    });
}

function removeItem(cartId) {
    $.ajax({
        url: `${API_BASE}?method=removeFromCart`,
        method: "POST",
        contentType: "application/json",
        dataType: "json",
        xhrFields: { withCredentials: true },
        data: JSON.stringify({ cart_id: cartId }),
        success: function (data) {
            if (!data.success) {
                showMessage(data.message);
                return;
            }
            loadCart();
            if (typeof updateCartCount === "function") updateCartCount();
        },
        error: function () {
            showMessage("Fehler beim Entfernen.");
        }
    });
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

function updateOrderButton() {
    if (loggedIn && cartHasItems) {
        orderArea.classList.remove("d-none");
    } else {
        orderArea.classList.add("d-none");
    }
}

function checkLogin() {
    $.ajax({
        url: `${API_BASE}?method=checkSession`,
        method: "GET",
        dataType: "json",
        xhrFields: { withCredentials: true },
        success: function (data) {
            loggedIn = data.success;
            updateOrderButton();
        }
    });
}

loadCart();
checkLogin();
