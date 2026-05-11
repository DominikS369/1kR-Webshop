const IMG_BASE = "/1kR-Webshop/backend/product_pictures/";

const grid = document.getElementById("productGrid");
const messageBox = document.getElementById("messageBox");
const filterBox = document.getElementById("categoryFilter");
let searchTimeout = null;
let activeCategory = null;

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
        grid.innerHTML = `<div class="col-12"><p class="text-muted">Keine Produkte in dieser Kategorie.</p></div>`;
        return;
    }

    for (const p of products) {
        const card = document.createElement("div");
        card.className = "col-sm-6 col-md-4 col-lg-3";
        card.innerHTML = `
            <div class="card h-100 shadow-sm">
                <img src="${IMG_BASE}${p.image}" class="card-img-top" alt="${p.name}">
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

        const btn = card.querySelector("button[data-product-id]");
        btn.addEventListener("click", () => addToCart(p.id, btn));

        grid.appendChild(card);
    }
}

async function addToCart(productId, btn) {
    if (btn.disabled) return;
    btn.disabled = true;

    try {
        const response = await fetch(`${API_BASE}?method=addToCart`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "include",
            body: JSON.stringify({ product_id: productId })
        });

        const data = await response.json();

        if (!data.success) {
            showMessage(data.message);
            btn.disabled = false;
            return;
        }

        const original = btn.textContent;
        btn.classList.replace("btn-primary", "btn-success");
        btn.textContent = "✓ Hinzugefügt";

        setTimeout(() => {
            btn.classList.replace("btn-success", "btn-primary");
            btn.textContent = original;
            btn.disabled = false;
        }, 1200);
    } catch (error) {
        showMessage("Hinzufügen fehlgeschlagen.");
        btn.disabled = false;
        console.error(error);
    }
}

async function loadProducts(categoryId) {
    try {
        const url = categoryId
            ? `${API_BASE}?method=getProducts&category=${categoryId}`
            : `${API_BASE}?method=getProducts`;

        const response = await fetch(url, { credentials: "include" });
        const data = await response.json();

        if (!data.success) {
            showMessage(data.message);
            return;
        }

        renderProducts(data.data);
    } catch (error) {
        showMessage("Produkte konnten nicht geladen werden.");
        console.error(error);
    }
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

    searchInput.addEventListener("input", () => {
        clearTimeout(searchTimeout);

        const q = searchInput.value.trim();

        if (q === "") {
            loadProducts(activeCategory);
            return;
        }
        searchTimeout = setTimeout(async () => {
            $.ajax({
                url:`${API_BASE}?method=searchProducts&q=${encodeURIComponent(q)}`,
                method: "GET",
                xhrFields: { withCredentials: true },
                success: function(data) {
                    if (!data.success) {
                        showMessage(data.message);
                        return;
                    }
                    renderProducts(data.data);
                },
                error: function() {
                    showMessage("Suche fehlgeschlagen.");
                }
            });
        }, 300);
    });
}
async function loadCategories() {
    try {
        const response = await fetch(`${API_BASE}?method=getCategories`, { credentials: "include" });
        const data = await response.json();

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
    } catch (error) {
        showMessage("Kategorien konnten nicht geladen werden.");
        console.error(error);
    }
}


loadCategories();
initSearch();