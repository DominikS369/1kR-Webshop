const IMG_BASE = "/1kR-Webshop/frontend/res/img/";

const grid = document.getElementById("productGrid");
const messageBox = document.getElementById("messageBox");
const filterBox = document.getElementById("categoryFilter");

let activeCategory = null;
let searchTimeout = null;

function showMessage(message, type = "danger") {
    messageBox.className = `alert alert-${type}`;
    messageBox.textContent = message;
    messageBox.classList.remove("d-none");
}

function ratingStars(rating) {
    const full = Math.round(rating);
    let out = "";
    for (let i = 1; i <= 5; i++) {
        out += i <= full ? "★" : "☆";
    }
    return out;
}

function renderProducts(products) {
    grid.innerHTML = "";

    if (products.length === 0) {
        grid.innerHTML = `<div class="col-12"><p class="text-muted">Keine Produkte gefunden.</p></div>`;
        return;
    }

    for (const p of products) {
        const card = document.createElement("div");
        card.className = "col-sm-6 col-md-4 col-lg-3";
        card.innerHTML = `
            <div class="card h-100 shadow-sm" draggable="true" data-product-id="${p.id}" style="cursor: grab;">
                <img src="${IMG_BASE}${p.image}" class="card-img-top" alt="${p.name}" draggable="false">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title h6">${p.name}</h5>
                    <p class="card-text small text-muted flex-grow-1">${p.description ?? ""}</p>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="fw-bold">${p.price.toFixed(2)} €</span>
                        <span class="text-warning small" title="${p.rating}">${ratingStars(p.rating)}</span>
                    </div>
                    <button class="btn btn-primary btn-sm w-100 mt-3" data-product-id="${p.id}">
                        In den Warenkorb
                    </button>
                </div>
            </div>
        `;

        const inner = card.querySelector(".card");
        inner.addEventListener("dragstart", (e) => {
            e.dataTransfer.setData("text/plain", String(p.id));
            e.dataTransfer.effectAllowed = "copy";
            inner.classList.add("opacity-50");
        });
        inner.addEventListener("dragend", () => {
            inner.classList.remove("opacity-50");
        });

        const btn = card.querySelector("button[data-product-id]");
        btn.addEventListener("click", () => addToCart(p.id, btn));

        grid.appendChild(card);
    }
}

function addToCart(productId, btn) {
    if (btn.disabled) return;
    btn.disabled = true;

    $.ajax({
        url: `${API_BASE}?method=addToCart`,
        method: "POST",
        contentType: "application/json",
        dataType: "json",
        xhrFields: { withCredentials: true },
        data: JSON.stringify({ product_id: productId }),
        success: function (data) {
            if (!data.success) {
                showMessage(data.message);
                btn.disabled = false;
                return;
            }

            if (typeof updateCartCount === "function") {
                updateCartCount();
            }

            const original = btn.textContent;
            btn.classList.replace("btn-primary", "btn-success");
            btn.textContent = "✓ Hinzugefügt";

            setTimeout(() => {
                btn.classList.replace("btn-success", "btn-primary");
                btn.textContent = original;
                btn.disabled = false;
            }, 1200);
        },
        error: function () {
            showMessage("Hinzufügen fehlgeschlagen.");
            btn.disabled = false;
        }
    });
}

function loadProducts(categoryId) {
    const url = categoryId
        ? API_BASE + `?method=getProducts&category=${categoryId}`
        : API_BASE + "?method=getProducts";

    $.ajax({
        url: url,
        method: "GET",
        dataType: "json",
        xhrFields: { withCredentials: true },
        success: function (data) {
            if (!data.success) {
                showMessage(data.message);
                return;
            }
            renderProducts(data.data);
        },
        error: function () {
            showMessage("Produkte konnten nicht geladen werden.");
        }
    });
}

function updateActiveButton() {
    const buttons = filterBox.querySelectorAll("button");
    for (const b of buttons) {
        const isActive = Number(b.dataset.categoryId) === activeCategory;
        b.classList.toggle("btn-primary", isActive);
        b.classList.toggle("btn-outline-primary", !isActive);
    }
}

function renderCategoryButtons(categories) {
    filterBox.innerHTML = "";

    for (const cat of categories) {
        const btn = document.createElement("button");
        btn.className = "btn btn-outline-primary btn-sm";
        btn.textContent = cat.name;
        btn.dataset.categoryId = cat.id;
        btn.addEventListener("click", () => {
            if (cat.id === activeCategory) return;
            activeCategory = cat.id;
            updateActiveButton();
            loadProducts(activeCategory);
        });
        filterBox.appendChild(btn);
    }

    updateActiveButton();
}

function initSearch() {
    const searchInput = document.getElementById("searchInput");
    if (!searchInput) return;

    searchInput.addEventListener("input", () => {
        clearTimeout(searchTimeout);

        const q = searchInput.value.trim();

        if (q === "") {
            loadProducts(activeCategory);
            return;
        }

        searchTimeout = setTimeout(() => {
            $.ajax({
                url: `${API_BASE}?method=searchProducts&q=${encodeURIComponent(q)}`,
                method: "GET",
                dataType: "json",
                xhrFields: { withCredentials: true },
                success: function (data) {
                    if (!data.success) {
                        showMessage(data.message);
                        return;
                    }
                    renderProducts(data.data);
                },
                error: function () {
                    showMessage("Suche fehlgeschlagen.");
                }
            });
        }, 300);
    });
}

function loadCategories() {
    $.ajax({
        url: API_BASE + "?method=getCategories",
        method: "GET",
        dataType: "json",
        xhrFields: { withCredentials: true },
        success: function (data) {
            if (!data.success) {
                showMessage(data.message);
                return;
            }

            if (data.data.length === 0) {
                showMessage("Keine Kategorien gefunden.", "warning");
                return;
            }

            activeCategory = data.data[0].id;
            renderCategoryButtons(data.data);
            loadProducts(activeCategory);
        },
        error: function () {
            showMessage("Kategorien konnten nicht geladen werden.");
        }
    });
}

loadCategories();
initSearch();
