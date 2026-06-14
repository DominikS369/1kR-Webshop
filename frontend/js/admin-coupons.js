const messageBox = document.getElementById("messageBox");
const couponTable = document.getElementById("couponTable");

function showMessage(message, type = "danger") {
    messageBox.className = `alert alert-${type}`;
    messageBox.textContent = message;
    messageBox.classList.remove("d-none");
    setTimeout(() => messageBox.classList.add("d-none"), 3000);
}

function loadCoupons(){
    $.ajax({
        url: API_BASE + "?method=getCoupons",
        method: "GET",
        dataType: "json",
        xhrFields: { withCredentials: true },
        success: function (data) {
            console.log(data.coupons[0])
            if (!data.success) {
                showMessage(data.message);
                return;
            }
            let html = "";
            for (const c of data.coupons) {
                const expired = new Date(c.expires_at) < new Date();
                const used = c.is_used == 1;
                let badge = "";
                if (used) {
                    badge = `<span class="badge bg-secondary">Eingelöst</span>`;
                } else if (expired) {
                    badge = `<span class="badge bg-danger">Abgelaufen</span>`;
                } else {
                    badge = `<span class="badge bg-success">Aktiv</span>`;
                }

                html += `
                <tr>
                    <td><strong>${c.code}</strong></td>
                    <td>${c.discount_type === "fixed" ? "Fixer Betrag" : "Prozent"}</td>
                    <td>${c.discount_type === "fixed" ? c.discount_value + " €" : c.discount_value + " %"}</td>
                    <td>${new Date(c.expires_at).toLocaleDateString("de-AT")}</td>
                    <td>
                       ${badge}
                    </td>
                </tr>`;
            }
            couponTable.innerHTML = html || `<tr><td colspan="5" class="text-muted text-center">Keine Gutscheine vorhanden.</td></tr>`;
        },
        error: function () {
            showMessage("Gutscheine konnten nicht geladen werden.");
        }
    });
}

function createCoupon(){
    const code          = document.getElementById("couponCode").value.trim();
    const discountType  = document.getElementById("discountType").value;
    const discountValue = document.getElementById("discountValue").value;
    const expiresAt     = document.getElementById("expiresAt").value;

    if (!discountValue || discountValue <= 0) {
        showMessage("Bitte einen gültigen Wert eingeben.");
        return;
    }
    if (!expiresAt) {
        showMessage("Bitte ein Ablaufdatum wählen.");
        return;
    }
    if (code !== "" && !/^[A-Za-z0-9_-]+$/.test(code)) {
        showMessage("Code darf nur Buchstaben, Zahlen, - und _ enthalten.");
        return;
    }
    $.ajax({
        url: API_BASE + "?method=createCoupon",
        method: "POST",
        dataType: "json",
        xhrFields: { withCredentials: true },
        data: {
            code: code,
            discount_type: discountType,
            discount_value: discountValue,
            expires_at: expiresAt
        },
        success: function (data) {
            if (!data.success) {
                showMessage(data.message);
                return;
            }
            showMessage(`Gutschein erstellt: ${data.code}`, "success");
            document.getElementById("couponCode").value = "";
            loadCoupons();
        },
        error: function () {
            showMessage("Fehler beim Erstellen des Gutscheins.");
        }
    });
}

loadCoupons();