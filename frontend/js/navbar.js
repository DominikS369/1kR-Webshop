const API_BASE = "/1kR-Webshop/backend/config/datahandler.php";

function renderNavbar() {
    const nav = document.getElementById("mainNavbar");
    if (!nav) return;

    $.ajax({
        url: `${API_BASE}?method=checkSession`,
        method: "GET",
        dataType: "json",
        xhrFields: { withCredentials: true },
        success: function (data) {
            const user = data.success ? data.user : null;
            drawNavbar(nav, user);
        },
        error: function (xhr) {
            console.error("Navbar Session-Check fehlgeschlagen:", xhr.status);
            drawNavbar(nav, null);
        }
    });
}

function drawNavbar(nav, user) {
    let links = `
        <a class="navbar-brand" href="index.html">
    <img src="../res/img/placeholder.jpg" alt="Tausend Rosen" height="100"></a>
        <div class="d-flex gap-3 align-items-center">
    `;

    const cartLink = `
        <a href="cart.html" class="position-relative text-decoration-none ms-auto" title="Warenkorb">
            <i class="bi bi-cart3 fs-4"></i>
            <span id="cartCountBadge" class="badge bg-danger rounded-pill position-absolute top-0 start-100 translate-middle d-none">0</span>
        </a>
    `;

    if (!user) {
        links += `
            <a href="index.html">Home</a>
            <a href="products.html">Produkte</a>
            <a href="login.html">Login</a>
            <a href="register.html">Registrieren</a>
            ${cartLink}
        `;
    } else if (Number(user.is_admin) === 1) {
        links += `
            <a href="index.html">Home</a>
            <a href="#">Produkte bearbeiten</a>
            <a href="#">Kunden bearbeiten</a>
            <a href="#">Gutscheine verwalten</a>
            <span>Eingeloggt als: ${user.username}</span>
            <button id="logoutBtn" class="btn btn-sm btn-danger">Logout</button>
        `;
    } else {
        links += `
            <a href="index.html">Home</a>
            <a href="products.html">Produkte</a>
            <a href="account.html">Mein Konto</a>
            <span>Eingeloggt als: ${user.username}</span>
            <button id="logoutBtn" class="btn btn-sm btn-danger">Logout</button>
            ${cartLink}
        `;
    }

    links += `</div>`;

    nav.className = "navbar navbar-expand-lg bg-body-tertiary px-3";
    nav.innerHTML = links;

    const logoutBtn = document.getElementById("logoutBtn");
    if (logoutBtn) {
        logoutBtn.addEventListener("click", function () {
            $.ajax({
                url: `${API_BASE}?method=logout`,
                method: "GET",
                dataType: "json",
                xhrFields: { withCredentials: true },
                complete: function () {
                    window.location.href = "index.html";
                }
            });
        });
    }

    setupCartDropZone();
    updateCartCount();
}

function setupCartDropZone() {
    const cartLink = document.querySelector("a[href='cart.html']");
    if (!cartLink) return;

    cartLink.addEventListener("dragover", (e) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = "copy";
        cartLink.classList.add("text-success");
    });

    cartLink.addEventListener("dragleave", () => {
        cartLink.classList.remove("text-success");
    });

    cartLink.addEventListener("drop", (e) => {
        e.preventDefault();
        cartLink.classList.remove("text-success");

        const productId = parseInt(e.dataTransfer.getData("text/plain"), 10);
        if (!productId) return;

        $.ajax({
            url: `${API_BASE}?method=addToCart`,
            method: "POST",
            contentType: "application/json",
            dataType: "json",
            xhrFields: { withCredentials: true },
            data: JSON.stringify({ product_id: productId }),
            success: function (data) {
                if (!data.success) return;
                updateCartCount();
                pulseCart();
            }
        });
    });
}

function pulseCart() {
    const cartLink = document.querySelector("a[href='cart.html']");
    if (!cartLink) return;

    cartLink.classList.add("text-success");
    setTimeout(() => {
        cartLink.classList.remove("text-success");
    }, 600);
}

function updateCartCount() {
    const badge = document.getElementById("cartCountBadge");
    if (!badge) return;

    $.ajax({
        url: API_URL + "?method=getCartCount",
        method: "GET",
        dataType: "json",
        xhrFields: { withCredentials: true },
        success: function (data) {
            if (!data.success) return;

            const count = data.data.count;
            if (count > 0) {
                badge.textContent = count;
                badge.classList.remove("d-none");
            } else {
                badge.classList.add("d-none");
            }
        }
    });
}

document.addEventListener("DOMContentLoaded", renderNavbar);
