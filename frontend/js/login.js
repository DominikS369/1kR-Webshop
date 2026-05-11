const form = document.getElementById("loginForm");
const messageBox = document.getElementById("messageBox");
const sessionStatus = document.getElementById("sessionStatus");
const logoutBtn = document.getElementById("logoutBtn");

const API_BASE = "http://localhost:8888/1kR-Webshop/backend/config/datahandler.php";

function showMessage(message, type = "danger") {
    messageBox.className = `alert alert-${type} mt-3`;
    messageBox.textContent = message;
    messageBox.classList.remove("d-none");
}

function clearMessage() {
    messageBox.classList.add("d-none");
    messageBox.textContent = "";
}

function checkSession() {
    $.ajax({
        url: `${API_BASE}?method=checkSession`,
        method: "GET",
        dataType: "json",
        xhrFields: { withCredentials: true },
        success: function (data) {
            if (data.success) {
                sessionStatus.textContent = `Eingeloggt als: ${data.user.username}`;
                logoutBtn.classList.remove("d-none");
            } else {
                sessionStatus.textContent = "Nicht eingeloggt";
                logoutBtn.classList.add("d-none");
            }
        },
        error: function () {
            sessionStatus.textContent = "Session-Status konnte nicht geladen werden";
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

    const username = document.getElementById("username").value.trim();
    const password = document.getElementById("password").value;
    const remember = document.getElementById("remember").checked;

    $.ajax({
        url: `${API_BASE}?method=login`,
        method: "POST",
        contentType: "application/json",
        dataType: "json",
        xhrFields: { withCredentials: true },
        data: JSON.stringify({
            username: username,
            password: password,
            remember: remember
        }),
        success: function (data) {
            if (data.success) {
                showMessage(data.message, "success");

                setTimeout(() => {
                    window.location.href = "/1kR-Webshop/frontend/sites/index.html";
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

logoutBtn.addEventListener("click", function () {
    clearMessage();

    $.ajax({
        url: `${API_BASE}?method=logout`,
        method: "GET",
        dataType: "json",
        xhrFields: { withCredentials: true },
        success: function (data) {
            if (data.success) {
                window.location.href = "/1kR-Webshop/frontend/sites/index.html";
            } else {
                showMessage("Logout fehlgeschlagen.");
            }
        },
        error: function () {
            showMessage("Verbindungsfehler beim Logout.");
        }
    });
});

checkSession();
