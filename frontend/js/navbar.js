const API_BASE = "http://localhost:8888/1kR-Webshop/backend/config/dataHandler.php";

async function renderNavbar() {
    const nav = document.getElementById("mainNavbar");
    if (!nav) return;

    let user = null;

    try {
        const response = await fetch(`${API_BASE}?method=checkSession`, {
            credentials: "include"
        });
        const data = await response.json();
        if (data.success) user = data.user;
    } catch (error) {
        console.error("Navbar Session-Check fehlgeschlagen:", error);
    }

    let links = `
        <a class="navbar-brand" href="/1kR-Webshop/frontend/sites/index.html">Tausend Rosen</a>
        <div class="d-flex gap-3 align-items-center">
    `;

    if (!user) {
        links += `
            <a href="/1kR-Webshop/frontend/sites/index.html">Home</a>
            <a href="/1kR-Webshop/frontend/sites/products.html">Produkte</a>
            <a href="/1kR-Webshop/frontend/sites/cart.html">Warenkorb</a>
            <a href="/1kR-Webshop/frontend/sites/login.html">Login</a>
            <a href="/1kR-Webshop/frontend/sites/register.html">Registrieren</a>
        `;
    } else if (user.is_admin == 1) {
        links += `
            <a href="/1kR-Webshop/frontend/sites/index.html">Home</a>
            <a href="#">Produkte bearbeiten</a>
            <a href="#">Kunden bearbeiten</a>
            <a href="#">Gutscheine verwalten</a>
            <span>Eingeloggt als: ${user.username}</span>
            <button id="logoutBtn" class="btn btn-sm btn-danger">Logout</button>
        `;
    } else {
        links += `
            <a href="/1kR-Webshop/frontend/sites/index.html">Home</a>
            <a href="/1kR-Webshop/frontend/sites/products.html">Produkte</a>
            <a href="#">Mein Konto</a>
            <a href="/1kR-Webshop/frontend/sites/cart.html">Warenkorb</a>
            <span>Eingeloggt als: ${user.username}</span>
            <button id="logoutBtn" class="btn btn-sm btn-danger">Logout</button>
        `;
    }

    links += `</div>`;

    nav.className = "navbar navbar-expand-lg bg-body-tertiary px-3";
    nav.innerHTML = links;

    const logoutBtn = document.getElementById("logoutBtn");
    if (logoutBtn) {
        logoutBtn.addEventListener("click", async () => {
            await fetch(`${API_BASE}?method=logout`, {
                credentials: "include"
            });
            window.location.href = "/1kR-Webshop/frontend/sites/index.html";
        });
    }
}

document.addEventListener("DOMContentLoaded", renderNavbar);