const LOGIN_API = API_BASE + "?method=login";

const form = document.getElementById("loginForm");
const messageBox = document.getElementById("messageBox");

function showMessage(message, type = "danger") {
    messageBox.className = `alert alert-${type} mt-3`;
    messageBox.textContent = message;
    messageBox.classList.remove("d-none");
}

function clearMessage() {
    messageBox.classList.add("d-none");
    messageBox.textContent = "";
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

     console.log("🔵 Login attempt started");
     console.log("API URL:", LOGIN_API);

     $.ajax({
         url: LOGIN_API,
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
             console.log("✅ Server Response:", data);
             if (data.success) {
                 showMessage(data.message, "success");
                 console.log("✅ Login successful, redirecting...");
                 setTimeout(() => {
                     window.location.href = "index.html";
                 }, 1000);
             } else {
                 console.log("❌ Server returned error:", data.message);
                 showMessage(data.message, "danger");
             }
         },
         error: function (jqXHR, textStatus, errorThrown) {
             console.error("❌ AJAX Error Details:");
             console.error("Status:", jqXHR.status);
             console.error("Text Status:", textStatus);
             console.error("Error Thrown:", errorThrown);
             console.error("Response Text:", jqXHR.responseText);
             showMessage("Verbindungsfehler zum Server: " + textStatus);
         }
     });
});