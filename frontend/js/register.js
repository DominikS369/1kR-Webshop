const form = document.getElementById("registerForm");
const messageBox = document.getElementById("messageBox");

function showMessage(message, type = "danger") {
    messageBox.className = `alert alert-${type} mt-4`;
    messageBox.textContent = message;
    messageBox.classList.remove("d-none");
}

form.addEventListener("submit", async function (event) {
    event.preventDefault();

    messageBox.classList.add("d-none");

    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const password = document.getElementById("password").value;
    const password2 = document.getElementById("password2").value;

    if (password !== password2) {
        showMessage("Die Passwörter stimmen nicht überein.");
        return;
    }

    const formData = {
        salutation: document.getElementById("salutation").value,
        firstname: document.getElementById("firstname").value.trim(),
        lastname: document.getElementById("lastname").value.trim(),
        address: document.getElementById("address").value.trim(),
        zip: document.getElementById("zip").value.trim(),
        city: document.getElementById("city").value.trim(),
        email: document.getElementById("email").value.trim(),
        username: document.getElementById("username").value.trim(),
        password: password,
        password2: password2,
        payment_info: document.getElementById("payment_info").value.trim()
    };

    try {
        const response = await fetch("http://localhost:8888/1kR-Webshop/backend/config/dataHandler.php?method=register", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(formData)
        });

        const data = await response.json();

        if (data.success) {
            showMessage(data.message, "success");
            form.reset();
        } else {
            showMessage(data.message, "danger");
        }
    } catch (error) {
        showMessage("Verbindungsfehler zum Server.");
        console.error(error);
    }
});