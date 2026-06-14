const IMG_BASE = "/1kR-Webshop/frontend/res/img/";
let appliedCoupon = null;



function showMessage(message, type = "danger") {
    document.getElementById("messageBox").className = `alert alert-${type}`;
    document.getElementById("messageBox").textContent = message;
    document.getElementById("messageBox").classList.remove("d-none");
}

function clearMessage() {
    document.getElementById("messageBox").classList.add("d-none");
    document.getElementById("messageBox").textContent = "";
}

function renderCartSummary(items, total) {
    const cartSummary = document.getElementById("cartSummary");
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
                    <td class="text-end fw-bold" id="cartTotal">${total.toFixed(2)} €</td>
                </tr>
            </tfoot>
        </table>
    `;
    window._cartTotal = total;
    initCoupon();
    updateTotal();
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

function initCoupon(){
    const codeInput = document.getElementById("code");
        if (!codeInput) return;
        codeInput.addEventListener("blur", validateCouponManual);
        }
function validateCouponManual() {
    const code = document.getElementById("code").value.trim();
    console.log("Code:", code);
    if (code === "") {
        appliedCoupon = null;
        updateTotal();
        return;
    }
    $.ajax({
        url: `${API_BASE}?method=validateCoupon&code=${encodeURIComponent(code)}`,
        method: "GET",
        dataType: "json",
        xhrFields: { withCredentials: true },
        success: function (data) {
            if (!data.success) {
                console.log("Response:", data);
                appliedCoupon = null;
                showMessage(data.message);
                updateTotal();
                return;
            }
            appliedCoupon = data.data;
            showMessage("Gutschein eingelöst!", "success");
            updateTotal();
        },
        error: function () {
            showMessage("Fehler beim Prüfen des Gutscheins.");
        }
    });
}




function updateTotal() {
    const total = window._cartTotal;
    const totalBox = document.getElementById("cartTotal");
    if (!totalBox) return;

    if (!appliedCoupon) {
        totalBox.textContent = `${total.toFixed(2)} €`;
        return;
    }

    let rabatt = 0;
    if (appliedCoupon.discount_type === "percentage") {
        rabatt = total * (appliedCoupon.discount_value / 100);
    } else {
        rabatt = appliedCoupon.discount_value;
    }

    const newTotal = Math.max(0, total - rabatt);

    totalBox.innerHTML = `
        <span class="text-decoration-line-through text-muted">${total.toFixed(2)} €</span>
        <span class="text-danger ms-2">- ${rabatt.toFixed(2)} €</span>
        <span class="fw-bold ms-2">${newTotal.toFixed(2)} €</span>
    `;
}
document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("checkoutForm");
    if (!form) return;

    document.getElementById("couponBtn").addEventListener("click", validateCouponManual);

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
            xhrFields: {withCredentials: true},
            data: JSON.stringify({
                firstname: firstname,
                lastname: lastname,
                address: address,
                zip: zip,
                city: city,
                payment_method: paymentMethod,
                coupon_code: appliedCoupon ? appliedCoupon.code : ""
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
});