const IMG_BASE = "/1kR-Webshop/frontend/res/img/";

const messageBox = document.getElementById("messageBox");
const cartSummary = document.getElementById("cartSummary");
const form = document.getElementById("checkoutForm");

function showMessage(message, type = "danger") {
    messageBox.className = `alert alert-${type}`;
    messageBox.textContent = message;
    messageBox.classList.remove("d-none");
}

function clearMessage() {
    messageBox.classList.add("d-none");
    messageBox.textContent = "";
}

function renderCartSummary(items, total) {
    if (items.length === 0) {
        cartSummary.innerHTML = `<p class="text-muted">Dein Warenkorb ist leer.</p>`;
        return;
    }

    let rows = "";
    for (const it of items) {
        rows += `
            <tr>
                <td><img src="${IMG_BASE}${it.image}" alt="${it.name}" style="width:50px;height:auto;"></td>
                <td>${it.name}</td>
                <td>${it.quantity}×</td>
                <td class="text-end">${it.subtotal.toFixed(2)} €</td>
            </tr>
        `;
    }

    cartSummary.innerHTML = `
        <table class="table align-middle bg-white shadow-sm">
            <tbody>${rows}</tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="text-end fw-bold">Gesamt:</td>
                    <td class="text-end fw-bold">${total.toFixed(2)} €</td>
                </tr>
            </tfoot>
        </table>
    `;
}

function fillForm(userData) {
    document.getElementById("firstname").value = userData.firstname || "";
    document.getElementById("lastname").value = userData.lastname || "";
    document.getElementById("address").value = userData.address || "";
    document.getElementById("zip").value = userData.zip || "";
    document.getElementById("city").value = userData.city || "";
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
            renderCartSummary(data.data.items, data.data.total);
        },
        error: function () {
            showMessage("Warenkorb konnte nicht geladen werden.");
        }
    });
}

function loadPaymentMethods() {
    $.ajax({
        url: `${API_BASE}?method=getUserPaymentMethods`,
        method: "GET",
        dataType: "json",
        xhrFields: { withCredentials: true },
        success: function (data) {
            if (!data.success) return;
            const select = document.getElementById("paymentMethod");
            for (const method of data.data) {
                const opt = document.createElement("option");
                opt.value = method;
                opt.textContent = method;
                select.appendChild(opt);
            }
        }
    });
}

function loadUserData() {
    $.ajax({
        url: `${API_BASE}?method=getUserData`,
        method: "GET",
        dataType: "json",
        xhrFields: { withCredentials: true },
        success: function (data) {
            if (!data.success) {
                window.location.href = "login.html";
                return;
            }
            fillForm(data.data);
        },
        error: function () {
            showMessage("Benutzerdaten konnten nicht geladen werden.");
        }
    });
}

form.addEventListener("submit", function (event) {
    event.preventDefault();
    clearMessage();

    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const firstname = document.getElementById("firstname").value.trim();
    const lastname = document.getElementById("lastname").value.trim();
    const address = document.getElementById("address").value.trim();
    const zip = document.getElementById("zip").value.trim();
    const city = document.getElementById("city").value.trim();
    const paymentMethod = document.getElementById("paymentMethod").value;

    if (paymentMethod === "") {
        showMessage("Bitte eine Zahlungsart wählen.");
        return;
    }

    if (!/^\d{4,5}$/.test(zip)) {
        showMessage("Bitte eine gültige PLZ eingeben.");
        return;
    }

    if (!/^[A-Za-zÄÖÜäöüß\s-]+$/.test(firstname)) {
        showMessage("Der Vorname enthält ungültige Zeichen.");
        return;
    }

    if (!/^[A-Za-zÄÖÜäöüß\s-]+$/.test(lastname)) {
        showMessage("Der Nachname enthält ungültige Zeichen.");
        return;
    }

    $.ajax({
        url: `${API_BASE}?method=placeOrder`,
        method: "POST",
        contentType: "application/json",
        dataType: "json",
        xhrFields: { withCredentials: true },
        data: JSON.stringify({
            firstname: firstname,
            lastname: lastname,
            address: address,
            zip: zip,
            city: city,
            payment_method: paymentMethod
        }),
        success: function (data) {
            if (!data.success) {
                showMessage(data.message);
                return;
            }
            showMessage("Bestellung erfolgreich aufgegeben! Du wirst weitergeleitet ...", "success");
            if (typeof updateCartCount === "function") updateCartCount();
            setTimeout(() => {
                window.location.href = "index.html";
            }, 2000);
        },
        error: function () {
            showMessage("Bestellung fehlgeschlagen.");
        }
    });
});

loadUserData();
loadPaymentMethods();
loadCart();
