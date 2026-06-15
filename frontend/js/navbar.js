// API_BASE wird aus config.js geladen

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
    const brand = `
        <a class="navbar-brand" href="index.html">
            <img src="../res/img/logo-rune.png" alt="" height="56">
        </a>
    `;

    const cartLink = `
        <a href="cart.html" class="position-relative text-decoration-none" title="Warenkorb">
            <i class="bi bi-cart3 fs-4"></i>
            <span id="cartCountBadge" class="badge bg-danger rounded-pill position-absolute top-0 start-100 translate-middle d-none">0</span>
        </a>
    `;

    let leftLinks, rightLinks;
    if (!user) {
        leftLinks = `
            <a href="index.html">Home</a>
            <a href="products.html">Produkte</a>
            <a href="about.html">About</a>
        `;
        rightLinks = `
            <a href="login.html">Login</a>
            <a href="register.html">Registrieren</a>
            ${cartLink}
        `;
    } else if (Number(user.is_admin) === 1) {
        leftLinks = `
            <a href="index.html">Home</a>
            <a href="admin-products.html">Produkte bearbeiten</a>
            <a href="admin-users.html">Kunden verwalten</a>
            <a href="admin-coupons.html">Gutscheine verwalten</a>
        `;
        rightLinks = `
            <span>Eingeloggt als: ${user.username}</span>
            <button id="logoutBtn" class="btn btn-sm btn-danger">Logout</button>
        `;
    } else {
        leftLinks = `
            <a href="index.html">Home</a>
            <a href="products.html">Produkte</a>
            <a href="account.html">Mein Konto</a>
            <a href="about.html">About</a>
        `;
        rightLinks = `
            <span>Eingeloggt als: ${user.username}</span>
            <button id="logoutBtn" class="btn btn-sm btn-danger">Logout</button>
            ${cartLink}
        `;
    }

    const navContent = `
        <div class="d-flex align-items-center gap-4 flex-wrap">
            ${brand}
            ${leftLinks}
        </div>
        <div class="d-flex align-items-center gap-4 flex-wrap ms-auto">
            ${rightLinks}
        </div>
    `;

    nav.className = "navbar navbar-expand-lg bg-body-tertiary py-2";
    nav.innerHTML = `
        <div class="page-container d-flex align-items-center gap-4 flex-wrap fs-5">
            ${navContent}
        </div>
    `;

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
        url: API_BASE + "?method=getCartCount",
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

function renderFooter() {
    if (document.getElementById("mainFooter")) return;

    const year = new Date().getFullYear();
    const footer = document.createElement("footer");
    footer.id = "mainFooter";
    footer.className = "site-footer mt-5";
    footer.innerHTML = `
        <div class="page-container py-4">
            <div class="row gy-3">
                <div class="col-md-6">
                    <h6 class="text-uppercase"><a href="impressum.html">Impressum</a></h6>
                    <p class="mb-1"><a href="mailto:office@tausendrosen.com">office@tausendrosen.com</a></p>
                </div>
                <div class="col-md-6 text-md-end">
                    <h6 class="text-uppercase">Folge uns</h6>
                    <p class="mb-0 d-flex flex-wrap gap-3 justify-content-md-end">
                        <a href="https://www.tausendrosen.com" target="_blank" rel="noopener">
                            <i class="bi bi-globe me-1"></i>tausendrosen.com
                        </a>
                        <a href="https://open.spotify.com/intl-de/artist/0gwnmTDRvl6WjxDCsJ6evd" target="_blank" rel="noopener">
                            <i class="bi bi-spotify me-1"></i>Spotify
                        </a>
                        <a href="https://www.instagram.com/tausendrosenmusik/" target="_blank" rel="noopener">
                            <i class="bi bi-instagram me-1"></i>Instagram
                        </a>
                        <a href="https://www.facebook.com/tausendrosenmusik/" target="_blank" rel="noopener">
                            <i class="bi bi-facebook me-1"></i>Facebook
                        </a>
                    </p>
                </div>
            </div>
            <hr>
            <p class="small text-muted mb-0 text-center">&copy; ${year} Tausend Rosen. Alle Rechte vorbehalten.</p>
        </div>
    `;
    document.body.appendChild(footer);
}

document.addEventListener("DOMContentLoaded", function () {
    renderNavbar();
    renderFooter();
});
