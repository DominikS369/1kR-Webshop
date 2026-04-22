const form = document.getElementById("loginForm");
const messageBox = document.getElementById("messageBox");
const sessionStatus = document.getElementById("sessionStatus");
const logoutBtn = document.getElementById("logoutBtn");

const API_BASE = "http://localhost:8888/1kR-Webshop/backend/config/dataHandler.php";

function showMessage(message, type = "danger") {
    messageBox.className = `alert alert-${type} mt-3`;
    messageBox.textContent = message;
    messageBox.classList.remove("d-none");
}

function clearMessage() {
    messageBox.classList.add("d-none");
    messageBox.textContent = "";
}

async function checkSession() {
    try {
        const response = await fetch(`${API_BASE}?method=checkSession`, {
            method: "GET",
            credentials: "include"
        });

        const data = await response.json();

        if (data.success) {
            sessionStatus.textContent = `Eingeloggt als: ${data.user.username}`;
            logoutBtn.classList.remove("d-none");
        } else {
            sessionStatus.textContent = "Nicht eingeloggt";
            logoutBtn.classList.add("d-none");
        }
    } catch (error) {
        sessionStatus.textContent = "Session-Status konnte nicht geladen werden";
        console.error(error);
    }
}

form.addEventListener("submit", async function (event) {
    event.preventDefault();
    clearMessage();

    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const username = document.getElementById("username").value.trim();
    const password = document.getElementById("password").value;

    try {
        const response = await fetch(`${API_BASE}?method=login`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            credentials: "include",
            body: JSON.stringify({
                username: username,
                password: password
            })
        });

        const data = await response.json();

        if (data.success) {
            showMessage(data.message, "success");
            form.reset();
            await checkSession();
        } else {
            showMessage(data.message, "danger");
        }
    } catch (error) {
        showMessage("Verbindungsfehler zum Server.");
        console.error(error);
    }
});

logoutBtn.addEventListener("click", async function () {
    clearMessage();

    try {
        const response = await fetch(`${API_BASE}?method=logout`, {
            method: "GET",
            credentials: "include"
        });

        const data = await response.json();

        if (data.success) {
            showMessage(data.message, "success");
            await checkSession();
        } else {
            showMessage("Logout fehlgeschlagen.");
        }
    } catch (error) {
        showMessage("Verbindungsfehler beim Logout.");
        console.error(error);
    }
});

checkSession();