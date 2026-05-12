const REGISTER_API = "http://localhost:8888/1kR-Webshop/backend/config/dataHandler.php";

const form = document.getElementById("registerForm");
const messageBox = document.getElementById("messageBox");

function showMessage(message, type = "danger") {
    messageBox.className = `alert alert-${type} mt-4`;
    messageBox.textContent = message;
    messageBox.classList.remove("d-none");
}

form.addEventListener("submit", function (event) {
    event.preventDefault();

    messageBox.classList.add("d-none");

    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const password = document.getElementById("password").value;
    const password2 = document.getElementById("password2").value;
    const username = document.getElementById("username").value.trim();
    const zip = document.getElementById("zip").value.trim();
    const firstname = document.getElementById("firstname").value.trim();
    const lastname = document.getElementById("lastname").value.trim();

    if (password !== password2) {
        showMessage("Die Passwörter stimmen nicht überein.");
        return;
    }

    if (password.length < 6) {
        showMessage("Das Passwort muss mindestens 6 Zeichen lang sein.");
        return;
    }

    if (username.length < 3) {
        showMessage("Der Benutzername muss mindestens 3 Zeichen lang sein.");
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

    const formData = {
        salutation: document.getElementById("salutation").value,
        firstname: firstname,
        lastname: lastname,
        address: document.getElementById("address").value.trim(),
        zip: zip,
        city: document.getElementById("city").value.trim(),
        email: document.getElementById("email").value.trim(),
        username: username,
        password: password,
        password2: password2,
        payment_info: document.getElementById("payment_info").value.trim()
    };

    $.ajax({
        url: `${REGISTER_API}?method=register`,
        method: "POST",
        contentType: "application/json",
        dataType: "json",
        data: JSON.stringify(formData),
        success: function (data) {
            if (data.success) {
                showMessage(data.message, "success");
                setTimeout(() => {
                    window.location.href = "index.html";
                }, 1000);
            } else {
                showMessage(data.message, "danger");
            }
        },
        error: function () {
            showMessage("Verbindungsfehler zum Server.");
        }
    });
});