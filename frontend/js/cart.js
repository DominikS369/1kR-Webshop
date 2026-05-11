const IMG_BASE = "/1kR-Webshop/backend/product_pictures/";

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
async function changeQuantity(cartId, newQuantity) {
    if (newQuantity <= 0) {
        await removeItem(cartId);
        return;
    }

    try {
        const response = await fetch(`${API_BASE}?method=updateCart`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "include",
            body: JSON.stringify({ cart_id: cartId, quantity: newQuantity })
        });
        const data = await response.json();
        if (data.success) loadCart();
        else showMessage(data.message);
    } catch (error) {
        showMessage("Fehler beim Aktualisieren des Warenkorbs.");
        console.error(error);
    }
}

async function removeItem(cartId) {
    try {
        const response = await fetch(`${API_BASE}?method=removeFromCart`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "include",
            body: JSON.stringify({ cart_id: cartId })
        });
        const data = await response.json();
        if (data.success) loadCart();
        else showMessage(data.message);
    } catch (error) {
        showMessage("Fehler beim Entfernen.");
        console.error(error);
    }
}
async function loadCart() {
    try {
        const response = await fetch(`${API_BASE}?method=getCart`, { credentials: "include" });
        const data = await response.json();

        if (!data.success) {
            showMessage(data.message);
            return;
        }

        renderCart(data.data.items, data.data.total);
    } catch (error) {
        showMessage("Warenkorb konnte nicht geladen werden.");
        console.error(error);
    }
}

loadCart();
