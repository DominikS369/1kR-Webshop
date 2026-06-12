const IMG_BASE = "/1kR-Webshop/frontend/res/img/";

const listBox = document.getElementById("adminProductList");
const messageBox = document.getElementById("messageBox");

let categories = [];

function showMessage(message, type = "danger") {
    messageBox.className = `alert alert-${type}`;
    messageBox.textContent = message;
    messageBox.classList.remove("d-none");
    setTimeout(() => messageBox.classList.add("d-none"), 3000);
}

function categoryOptions(selectedId) {
    return categories.map(c =>
        `<option value="${c.id}" ${c.id === selectedId ? "selected" : ""}>${c.name}</option>`
    ).join("");
}

function renderProducts(products) {
    listBox.innerHTML = "";

    if (products.length === 0) {
        listBox.innerHTML = `<p class="text-muted">Keine Produkte vorhanden.</p>`;
        return;
    }

    for (const p of products) {
        const card = document.createElement("div");
        card.className = "card mb-3";
        card.innerHTML = `
            <div class="card-body">
                <div class="row g-3 align-items-start">
                    <div class="col-md-3 text-center">
                        <img data-img src="${IMG_BASE}${p.image}" alt="${p.name}" class="img-fluid mb-2" style="max-height:120px;">
                        <input type="file" class="form-control form-control-sm mb-2" data-imgfile accept="image/png,image/jpeg,image/webp,image/gif">
                        <button class="btn btn-outline-dark btn-sm w-100" data-upload>Bild hochladen</button>
                    </div>
                    <div class="col-md-9">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label mb-1">Name</label>
                                <input class="form-control" data-field="name" value="${p.name}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-1">Preis (€)</label>
                                <input class="form-control" data-field="price" type="number" step="0.01" min="0" value="${p.price.toFixed(2)}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-1">Kategorie</label>
                                <select class="form-select" data-field="category_id">${categoryOptions(p.category_id)}</select>
                            </div>
                            <div class="col-12">
                                <label class="form-label mb-1">Beschreibung</label>
                                <textarea class="form-control" data-field="description" rows="2">${p.description ?? ""}</textarea>
                            </div>
                            <div class="col-12 text-end">
                                <button class="btn btn-outline-danger btn-sm me-2" data-delete>Löschen</button>
                                <button class="btn btn-dark btn-sm" data-save>Speichern</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        const saveBtn = card.querySelector("button[data-save]");
        saveBtn.addEventListener("click", () => {
            const payload = {
                id: p.id,
                name: card.querySelector('[data-field="name"]').value.trim(),
                description: card.querySelector('[data-field="description"]').value.trim(),
                price: parseFloat(card.querySelector('[data-field="price"]').value),
                category_id: parseInt(card.querySelector('[data-field="category_id"]').value, 10)
            };
            saveProduct(payload, saveBtn);
        });

        const uploadBtn = card.querySelector("button[data-upload]");
        uploadBtn.addEventListener("click", () => {
            const fileInput = card.querySelector('[data-imgfile]');
            const imgEl = card.querySelector('[data-img]');
            uploadImage(p.id, fileInput, imgEl, uploadBtn);
        });

        const deleteBtn = card.querySelector("button[data-delete]");
        deleteBtn.addEventListener("click", () => {
            if (!confirm(`„${p.name}" wirklich löschen?`)) return;
            deleteProduct(p.id, card, deleteBtn);
        });

        listBox.appendChild(card);
    }
}

function saveProduct(payload, btn) {
    btn.disabled = true;

    $.ajax({
        url: `${API_BASE}?method=updateProduct`,
        method: "POST",
        contentType: "application/json",
        dataType: "json",
        xhrFields: { withCredentials: true },
        data: JSON.stringify(payload),
        success: function (data) {
            showMessage(data.message, data.success ? "success" : "danger");
            btn.disabled = false;
        },
        error: function () {
            showMessage("Speichern fehlgeschlagen.");
            btn.disabled = false;
        }
    });
}

function uploadImage(productId, fileInput, imgEl, btn) {
    const file = fileInput.files[0];
    if (!file) {
        showMessage("Bitte zuerst eine Datei auswählen.", "warning");
        return;
    }

    const formData = new FormData();
    formData.append("id", productId);
    formData.append("image", file);

    btn.disabled = true;

    $.ajax({
        url: `${API_BASE}?method=uploadProductImage`,
        method: "POST",
        data: formData,
        processData: false,
        contentType: false,
        dataType: "json",
        xhrFields: { withCredentials: true },
        success: function (data) {
            showMessage(data.message, data.success ? "success" : "danger");
            if (data.success && data.image) {
                imgEl.src = `${IMG_BASE}${data.image}?t=${Date.now()}`;
                fileInput.value = "";
            }
            btn.disabled = false;
        },
        error: function () {
            showMessage("Upload fehlgeschlagen.");
            btn.disabled = false;
        }
    });
}

function deleteProduct(productId, card, btn) {
    btn.disabled = true;

    $.ajax({
        url: `${API_BASE}?method=deleteProduct`,
        method: "POST",
        contentType: "application/json",
        dataType: "json",
        xhrFields: { withCredentials: true },
        data: JSON.stringify({ id: productId }),
        success: function (data) {
            showMessage(data.message, data.success ? "success" : "danger");
            if (data.success) {
                card.remove();
            } else {
                btn.disabled = false;
            }
        },
        error: function () {
            showMessage("Löschen fehlgeschlagen.");
            btn.disabled = false;
        }
    });
}

function loadProducts() {
    $.ajax({
        url: API_BASE + "?method=getProducts",
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

function loadCategories() {
    $.ajax({
        url: API_BASE + "?method=getCategories",
        method: "GET",
        dataType: "json",
        xhrFields: { withCredentials: true },
        success: function (data) {
            if (data.success) {
                categories = data.data;
                fillNewCategoryDropdown();
            }
            loadProducts();
        },
        error: function () {
            loadProducts();
        }
    });
}

function fillNewCategoryDropdown() {
    const sel = document.getElementById("new-category");
    sel.innerHTML = categories.map(c => `<option value="${c.id}">${c.name}</option>`).join("");
}

function createProduct() {
    const name = document.getElementById("new-name").value.trim();
    const priceRaw = document.getElementById("new-price").value;
    const categoryId = parseInt(document.getElementById("new-category").value, 10);
    const description = document.getElementById("new-description").value.trim();
    const fileInput = document.getElementById("new-image");

    if (name === "") {
        showMessage("Bitte einen Produktnamen angeben.", "warning");
        return;
    }
    if (priceRaw === "" || isNaN(parseFloat(priceRaw)) || parseFloat(priceRaw) < 0) {
        showMessage("Bitte einen gültigen Preis angeben.", "warning");
        return;
    }
    if (!categoryId) {
        showMessage("Bitte eine Kategorie wählen.", "warning");
        return;
    }

    const btn = document.getElementById("createBtn");
    btn.disabled = true;

    $.ajax({
        url: `${API_BASE}?method=createProduct`,
        method: "POST",
        contentType: "application/json",
        dataType: "json",
        xhrFields: { withCredentials: true },
        data: JSON.stringify({ name, description, price: parseFloat(priceRaw), category_id: categoryId }),
        success: function (data) {
            if (!data.success) {
                showMessage(data.message);
                btn.disabled = false;
                return;
            }

            const finish = () => {
                showMessage("Produkt angelegt.", "success");
                resetCreateForm();
                btn.disabled = false;
                loadProducts();
            };

            if (fileInput.files[0] && data.id) {
                const formData = new FormData();
                formData.append("id", data.id);
                formData.append("image", fileInput.files[0]);
                $.ajax({
                    url: `${API_BASE}?method=uploadProductImage`,
                    method: "POST",
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: "json",
                    xhrFields: { withCredentials: true },
                    complete: finish
                });
            } else {
                finish();
            }
        },
        error: function () {
            showMessage("Produkt konnte nicht angelegt werden.");
            btn.disabled = false;
        }
    });
}

function resetCreateForm() {
    document.getElementById("new-name").value = "";
    document.getElementById("new-price").value = "";
    document.getElementById("new-description").value = "";
    document.getElementById("new-image").value = "";
    if (categories.length) document.getElementById("new-category").value = categories[0].id;
}

document.getElementById("createBtn").addEventListener("click", createProduct);

loadCategories();
