<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

header("Content-Type: application/json");

require_once "dbaccess.php";

function sendJson(bool $success, string $message, array $extra = []): void
{
    echo json_encode(array_merge([
        "success" => $success,
        "message" => $message
    ], $extra));
    exit;
}

function requireMethod(string $method): void
{
    if ($_SERVER["REQUEST_METHOD"] !== $method) {
        sendJson(false, "Nur $method erlaubt");
    }
}

function getJsonInput(): array
{
    $input = json_decode(file_get_contents("php://input"), true);

    if (!is_array($input)) {
        sendJson(false, "Keine gültigen Daten erhalten");
    }

    return $input;
}

function startSessionIfNeeded(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

$method = $_GET["method"] ?? "";

// DEBUG: Log die empfangenen Werte
error_log("REQUEST METHOD: " . $_SERVER["REQUEST_METHOD"]);
error_log("GET method param: " . var_export($_GET, true));
error_log("Parsed method: " . $method);

switch ($method) {

    case "test":
        sendJson(true, "API läuft");

    case "register":
        requireMethod("POST");
        $input = getJsonInput();

        $salutation = trim($input["salutation"] ?? "");
        $firstname = trim($input["firstname"] ?? "");
        $lastname = trim($input["lastname"] ?? "");
        $address = trim($input["address"] ?? "");
        $zip = trim($input["zip"] ?? "");
        $city = trim($input["city"] ?? "");
        $email = strtolower(trim($input["email"] ?? ""));
        $username = trim($input["username"] ?? "");
        $password = $input["password"] ?? "";
        $password2 = $input["password2"] ?? "";
        $payment_methods = $input["payment_methods"] ?? [];

        if (
            $firstname === "" || $lastname === "" || $address === "" ||
            $zip === "" || $city === "" || $email === "" ||
            $username === "" || $password === "" || $password2 === ""
        ) {
            sendJson(false, "Bitte alle Pflichtfelder ausfüllen");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendJson(false, "Ungültige E-Mail-Adresse");
        }

        if (!is_array($payment_methods) || count($payment_methods) === 0) {
            sendJson(false, "Bitte mindestens eine Zahlungsart wählen");
        }

        $allowed = ["Auf Rechnung", "Kreditkarte", "PayPal"];
        foreach ($payment_methods as $pm) {
            if (!in_array($pm, $allowed)) {
                sendJson(false, "Ungültige Zahlungsart");
            }
        }

        $payment_info = implode(", ", $payment_methods);

        if ($password !== $password2) {
            sendJson(false, "Passwörter stimmen nicht überein");
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $db = new DBAccess();
        $conn = $db->getConnection();

        $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $checkStmt->bind_param("ss", $email, $username);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            sendJson(false, "E-Mail oder Benutzername bereits vergeben");
        }

        $stmt = $conn->prepare("
            INSERT INTO users
            (salutation, firstname, lastname, address, zip, city, email, username, password, payment_info)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "ssssssssss",
            $salutation,
            $firstname,
            $lastname,
            $address,
            $zip,
            $city,
            $email,
            $username,
            $hashedPassword,
            $payment_info
        );

        if ($stmt->execute()) {
            $newUserId = $conn->insert_id;

            $methodStmt = $conn->prepare("INSERT INTO user_payment_methods (user_id, method) VALUES (?, ?)");
            foreach ($payment_methods as $pm) {
                $methodStmt->bind_param("is", $newUserId, $pm);
                $methodStmt->execute();
            }

            sendJson(true, "Registrierung erfolgreich");
        }

        sendJson(false, "Fehler beim Speichern");

    case "login":
        requireMethod("POST");
        $input = getJsonInput();

        $username = trim($input["username"] ?? "");
        $password = $input["password"] ?? "";
        $remember = (bool)($input["remember"] ?? false);

        if ($username === "" || $password === "") {
            sendJson(false, "Bitte Username und Passwort eingeben");
        }

        startSessionIfNeeded();

        $db = new DBAccess();
        $conn = $db->getConnection();

        $stmt = $conn->prepare("
            SELECT id, username, password, is_admin, is_active
            FROM users
            WHERE username = ? OR email = ?
        ");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            sendJson(false, "User nicht gefunden");
        }

        $user = $result->fetch_assoc();

        if ((int)$user["is_active"] !== 1) {
            sendJson(false, "Benutzer ist deaktiviert");
        }

        if (!password_verify($password, $user["password"])) {
            sendJson(false, "Falsches Passwort");
        }

        $_SESSION["user_id"] = $user["id"];
        $_SESSION["username"] = $user["username"];
        $_SESSION["is_admin"] = $user["is_admin"];

        if ($remember) {
            setcookie("remember_user", (string)$user["id"], time() + (60 * 60 * 24 * 30), "/");
        }

        // Gast-Warenkorb auf den eingeloggten User übertragen
        $userId = $user["id"];
        $sessionId = session_id();

        $guestStmt = $conn->prepare("SELECT product_id, quantity FROM cart WHERE session_id = ? AND user_id IS NULL");
        $guestStmt->bind_param("s", $sessionId);
        $guestStmt->execute();
        $guestItems = $guestStmt->get_result();

        while ($item = $guestItems->fetch_assoc()) {
            $check = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
            $check->bind_param("ii", $userId, $item["product_id"]);
            $check->execute();
            $existing = $check->get_result()->fetch_assoc();

            if ($existing) {
                $newQty = $existing["quantity"] + $item["quantity"];
                $upd = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
                $upd->bind_param("ii", $newQty, $existing["id"]);
                $upd->execute();
            } else {
                $move = $conn->prepare("UPDATE cart SET user_id = ?, session_id = NULL WHERE session_id = ? AND product_id = ?");
                $move->bind_param("isi", $userId, $sessionId, $item["product_id"]);
                $move->execute();
            }
        }

        $cleanup = $conn->prepare("DELETE FROM cart WHERE session_id = ? AND user_id IS NULL");
        $cleanup->bind_param("s", $sessionId);
        $cleanup->execute();

        sendJson(true, "Login erfolgreich");

    case "checkSession":
        startSessionIfNeeded();

        if (!isset($_SESSION["user_id"]) && isset($_COOKIE["remember_user"])) {
            $userId = (int)$_COOKIE["remember_user"];

            $db = new DBAccess();
            $conn = $db->getConnection();

            $stmt = $conn->prepare("
                SELECT id, username, is_admin
                FROM users
                WHERE id = ? AND is_active = 1
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();

                $_SESSION["user_id"] = $user["id"];
                $_SESSION["username"] = $user["username"];
                $_SESSION["is_admin"] = $user["is_admin"];
            } else {
                setcookie("remember_user", "", time() - 3600, "/");
            }
        }

        if (isset($_SESSION["user_id"])) {
            sendJson(true, "Eingeloggt", [
                "user" => [
                    "id" => $_SESSION["user_id"],
                    "username" => $_SESSION["username"],
                    "is_admin" => $_SESSION["is_admin"]
                ]
            ]);
        }

        sendJson(false, "Nicht eingeloggt");

    case "logout":
        startSessionIfNeeded();
        session_unset();
        session_destroy();

        setcookie("remember_user", "", time() - 3600, "/");

        sendJson(true, "Logout erfolgreich");

    case "getCategories":
        $db = new DBAccess();
        $conn = $db->getConnection();

        $result = $conn->query("SELECT id, name FROM categories ORDER BY id");

        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $row["id"] = (int)$row["id"];
            $categories[] = $row;
        }

        sendJson(true, "OK", ["data" => $categories]);

    case "getProducts":
        $categoryId = isset($_GET["category"]) ? (int)$_GET["category"] : 0;

        $db = new DBAccess();
        $conn = $db->getConnection();

        if ($categoryId > 0) {
            $stmt = $conn->prepare("
                SELECT p.id, p.name, p.description, p.price, p.rating, p.image,
                       p.category_id, c.name AS category_name
                FROM products p
                JOIN categories c ON c.id = p.category_id
                WHERE p.is_active = 1 AND p.category_id = ?
                ORDER BY p.id
            ");
            $stmt->bind_param("i", $categoryId);
        } else {
            $stmt = $conn->prepare("
                SELECT p.id, p.name, p.description, p.price, p.rating, p.image,
                       p.category_id, c.name AS category_name
                FROM products p
                JOIN categories c ON c.id = p.category_id
                WHERE p.is_active = 1
                ORDER BY p.id
            ");
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $products = [];
        while ($row = $result->fetch_assoc()) {
            $row["price"] = (float)$row["price"];
            $row["rating"] = (float)$row["rating"];
            $products[] = $row;
        }

        sendJson(true, "OK", ["data" => $products]);

    case "searchProducts":
        $query = trim($_GET["q"] ?? "");
        if ($query === "") {
            sendJson(true, "Kein Suchbegriff angegeben", ["data" => []]);
        }
        $search = '%' . $query . '%';
        $db = new DBAccess();
        $conn = $db->getConnection();
        $stmt = $conn->prepare("
        SELECT p.id, p.name, p.description, p.price, p.rating, p.image, p.category_id, c.name AS category_name
        FROM products p
        JOIN categories c ON c.id = p.category_id
        WHERE p.is_active = 1 
          AND (p.name LIKE ? OR p.description LIKE ?)");
        $stmt->bind_param("ss", $search, $search);
        $stmt->execute();
        $result = $stmt->get_result();
        $products = [];
        while ($row = $result->fetch_assoc()) {
            $row["price"] = (float)$row["price"];
            $row["rating"] = (float)$row["rating"];
            $products[] = $row;
        }
        sendJson(true, "OK", ["data" => $products]);

    case "updateProduct":
        requireMethod("POST");
        startSessionIfNeeded();

        if (empty($_SESSION["user_id"]) || (int)($_SESSION["is_admin"] ?? 0) !== 1) {
            sendJson(false, "Keine Berechtigung");
        }

        $input = getJsonInput();

        $productId   = (int)($input["id"] ?? 0);
        $name        = trim($input["name"] ?? "");
        $description = trim($input["description"] ?? "");
        $price       = (float)($input["price"] ?? 0);
        $categoryId  = (int)($input["category_id"] ?? 0);

        if ($productId <= 0 || $name === "" || $price < 0 || $categoryId <= 0) {
            sendJson(false, "Bitte alle Pflichtfelder korrekt ausfüllen");
        }

        $db = new DBAccess();
        $conn = $db->getConnection();

        $stmt = $conn->prepare("
            UPDATE products
            SET name = ?, description = ?, price = ?, category_id = ?
            WHERE id = ?
        ");
        $stmt->bind_param("ssdii", $name, $description, $price, $categoryId, $productId);
        $stmt->execute();

        sendJson(true, "Produkt gespeichert");

    case "uploadProductImage":
        requireMethod("POST");
        startSessionIfNeeded();

        if (empty($_SESSION["user_id"]) || (int)($_SESSION["is_admin"] ?? 0) !== 1) {
            sendJson(false, "Keine Berechtigung");
        }

        $productId = (int)($_POST["id"] ?? 0);
        if ($productId <= 0) {
            sendJson(false, "Ungültige Produkt-ID");
        }

        if (!isset($_FILES["image"]) || $_FILES["image"]["error"] !== UPLOAD_ERR_OK) {
            sendJson(false, "Kein gültiger Upload erhalten");
        }

        $file = $_FILES["image"];

        if ($file["size"] > 5 * 1024 * 1024) {
            sendJson(false, "Datei zu groß (max. 5 MB)");
        }

        $allowed = [
            "image/jpeg" => "jpg",
            "image/png"  => "png",
            "image/webp" => "webp",
            "image/gif"  => "gif"
        ];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file["tmp_name"]);
        finfo_close($finfo);

        if (!isset($allowed[$mime])) {
            sendJson(false, "Nur JPG, PNG, WEBP oder GIF erlaubt");
        }

        $ext = $allowed[$mime];
        $filename = "product_" . $productId . "_" . time() . "." . $ext;
        $targetDir = __DIR__ . "/../../frontend/res/img/";
        $targetPath = $targetDir . $filename;

        if (!move_uploaded_file($file["tmp_name"], $targetPath)) {
            sendJson(false, "Datei konnte nicht gespeichert werden");
        }

        $db = new DBAccess();
        $conn = $db->getConnection();

        $oldStmt = $conn->prepare("SELECT image FROM products WHERE id = ?");
        $oldStmt->bind_param("i", $productId);
        $oldStmt->execute();
        $oldRow = $oldStmt->get_result()->fetch_assoc();
        $oldImage = $oldRow["image"] ?? "";

        $stmt = $conn->prepare("UPDATE products SET image = ? WHERE id = ?");
        $stmt->bind_param("si", $filename, $productId);
        $stmt->execute();

        if ($oldImage !== "" && $oldImage !== $filename && $oldImage !== "placeholder.jpg") {
            $refStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM products WHERE image = ?");
            $refStmt->bind_param("s", $oldImage);
            $refStmt->execute();
            $stillUsed = (int)($refStmt->get_result()->fetch_assoc()["cnt"] ?? 0);

            $oldPath = $targetDir . basename($oldImage);
            if ($stillUsed === 0 && is_file($oldPath)) {
                @unlink($oldPath);
            }
        }

        sendJson(true, "Bild aktualisiert", ["image" => $filename]);

    case "deleteProduct":
        requireMethod("POST");
        startSessionIfNeeded();

        if (empty($_SESSION["user_id"]) || (int)($_SESSION["is_admin"] ?? 0) !== 1) {
            sendJson(false, "Keine Berechtigung");
        }

        $input = getJsonInput();
        $productId = (int)($input["id"] ?? 0);
        if ($productId <= 0) {
            sendJson(false, "Ungültige Produkt-ID");
        }

        $db = new DBAccess();
        $conn = $db->getConnection();

        $refStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM order_items WHERE product_id = ?");
        $refStmt->bind_param("i", $productId);
        $refStmt->execute();
        $referenced = (int)($refStmt->get_result()->fetch_assoc()["cnt"] ?? 0);

        if ($referenced > 0) {
            $stmt = $conn->prepare("UPDATE products SET is_active = 0 WHERE id = ?");
            $stmt->bind_param("i", $productId);
            $stmt->execute();
            sendJson(true, "Produkt wurde deaktiviert und ist nicht mehr im Shop sichtbar");
        }

        $imgStmt = $conn->prepare("SELECT image FROM products WHERE id = ?");
        $imgStmt->bind_param("i", $productId);
        $imgStmt->execute();
        $image = $imgStmt->get_result()->fetch_assoc()["image"] ?? "";

        $delCart = $conn->prepare("DELETE FROM cart WHERE product_id = ?");
        $delCart->bind_param("i", $productId);
        $delCart->execute();

        $del = $conn->prepare("DELETE FROM products WHERE id = ?");
        $del->bind_param("i", $productId);
        $del->execute();

        if ($image !== "" && $image !== "placeholder.jpg") {
            $usedStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM products WHERE image = ?");
            $usedStmt->bind_param("s", $image);
            $usedStmt->execute();
            $stillUsed = (int)($usedStmt->get_result()->fetch_assoc()["cnt"] ?? 0);

            $imgPath = __DIR__ . "/../../frontend/res/img/" . basename($image);
            if ($stillUsed === 0 && is_file($imgPath)) {
                @unlink($imgPath);
            }
        }

        sendJson(true, "Produkt gelöscht");

    case "createProduct":
        requireMethod("POST");
        startSessionIfNeeded();

        if (empty($_SESSION["user_id"]) || (int)($_SESSION["is_admin"] ?? 0) !== 1) {
            sendJson(false, "Keine Berechtigung");
        }

        $input = getJsonInput();

        $name        = trim($input["name"] ?? "");
        $description = trim($input["description"] ?? "");
        $price       = $input["price"] ?? null;
        $categoryId  = (int)($input["category_id"] ?? 0);

        if ($name === "") {
            sendJson(false, "Bitte einen Produktnamen angeben");
        }
        if (!is_numeric($price) || (float)$price < 0) {
            sendJson(false, "Bitte einen gültigen Preis angeben");
        }
        if ($categoryId <= 0) {
            sendJson(false, "Bitte eine Kategorie wählen");
        }

        $price = (float)$price;

        $db = new DBAccess();
        $conn = $db->getConnection();

        $catStmt = $conn->prepare("SELECT id FROM categories WHERE id = ?");
        $catStmt->bind_param("i", $categoryId);
        $catStmt->execute();
        if ($catStmt->get_result()->num_rows === 0) {
            sendJson(false, "Unbekannte Kategorie");
        }

        $stmt = $conn->prepare("
            INSERT INTO products (category_id, name, description, price, image, is_active)
            VALUES (?, ?, ?, ?, 'placeholder.jpg', 1)
        ");
        $stmt->bind_param("issd", $categoryId, $name, $description, $price);
        $stmt->execute();

        sendJson(true, "Produkt angelegt", ["id" => $conn->insert_id]);


    case "addToCart":
        requireMethod("POST");
        $input = getJsonInput();

        $productId = (int)($input["product_id"] ?? 0);
        if ($productId <= 0) {
            sendJson(false, "Ungültige Produkt-ID");
        }

        startSessionIfNeeded();

        $userId = $_SESSION["user_id"] ?? null;
        $sessionId = session_id();

        $db = new DBAccess();
        $conn = $db->getConnection();

        if ($userId) {
            $stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE product_id = ? AND user_id = ?");
            $stmt->bind_param("ii", $productId, $userId);
        } else {
            $stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE product_id = ? AND session_id = ? AND user_id IS NULL");
            $stmt->bind_param("is", $productId, $sessionId);
        }

        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();

        if ($existing) {
            $newQty = (int)$existing["quantity"] + 1;
            $update = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
            $update->bind_param("ii", $newQty, $existing["id"]);
            $update->execute();

            sendJson(true, "Produkt hinzugefügt", ["data" => ["quantity" => $newQty]]);
        }

        if ($userId) {
            $insert = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1)");
            $insert->bind_param("ii", $userId, $productId);
        } else {
            $insert = $conn->prepare("INSERT INTO cart (session_id, product_id, quantity) VALUES (?, ?, 1)");
            $insert->bind_param("si", $sessionId, $productId);
        }

        $insert->execute();

        sendJson(true, "Produkt hinzugefügt", ["data" => ["quantity" => 1]]);

    case "getCartCount":
        startSessionIfNeeded();

        $userId = $_SESSION["user_id"] ?? null;
        $sessionId = session_id();

        $db = new DBAccess();
        $conn = $db->getConnection();

        if ($userId) {
            $stmt = $conn->prepare("SELECT COALESCE(SUM(quantity), 0) AS count FROM cart WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
        } else {
            $stmt = $conn->prepare("SELECT COALESCE(SUM(quantity), 0) AS count FROM cart WHERE session_id = ? AND user_id IS NULL");
            $stmt->bind_param("s", $sessionId);
        }

        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        sendJson(true, "OK", ["data" => ["count" => (int)$row["count"]]]);

    case "getCart":
        startSessionIfNeeded();

        $userId = $_SESSION["user_id"] ?? null;
        $sessionId = session_id();

        $db = new DBAccess();
        $conn = $db->getConnection();

        if ($userId) {
            $stmt = $conn->prepare("
                SELECT c.id, c.product_id, c.quantity, p.name, p.price, p.image
                FROM cart c
                JOIN products p ON p.id = c.product_id
                WHERE c.user_id = ?
                ORDER BY c.added_at DESC
            ");
            $stmt->bind_param("i", $userId);
        } else {
            $stmt = $conn->prepare("
                SELECT c.id, c.product_id, c.quantity, p.name, p.price, p.image
                FROM cart c
                JOIN products p ON p.id = c.product_id
                WHERE c.session_id = ? AND c.user_id IS NULL
                ORDER BY c.added_at DESC
            ");
            $stmt->bind_param("s", $sessionId);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $items = [];
        $total = 0;
        while ($row = $result->fetch_assoc()) {
            $row["price"] = (float)$row["price"];
            $row["quantity"] = (int)$row["quantity"];
            $row["subtotal"] = round($row["price"] * $row["quantity"], 2);
            $total += $row["subtotal"];
            $items[] = $row;
        }

        sendJson(true, "OK", ["data" => ["items" => $items, "total" => round($total, 2)]]);

    case "updateCart":
        requireMethod("POST");
        $input = getJsonInput();
        $cartId = (int)($input["cart_id"] ?? 0);
        $quantity = (int)($input["quantity"] ?? 0);

        if ($cartId <= 0 || $quantity <= 0) {
            sendJson(false, "Ungültige Daten");
        }

        startSessionIfNeeded();
        $userId = $_SESSION["user_id"] ?? null;
        $sessionId = session_id();
        $db = new DBAccess();
        $conn = $db->getConnection();

        if ($userId) {
            $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param("iii", $quantity, $cartId, $userId);
        } else {
            $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND session_id = ? AND user_id IS NULL");
            $stmt->bind_param("iis", $quantity, $cartId, $sessionId);
        }
        $stmt->execute();
        sendJson(true, "Menge aktualisiert");

    case 'removeFromCart':
        requireMethod("POST");
        $input = getJsonInput();
        $cartId = (int)($input["cart_id"] ?? 0);
        if($cartId <= 0){
            sendJson(false, "Ungültige Daten");
        }
        startSessionIfNeeded();
        $userId = $_SESSION["user_id"] ?? null;
        $sessionId = session_id();
        $db = new DBAccess();
        $conn = $db->getConnection();
        if($userId){
            $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $cartId, $userId);
        } else {
            $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND session_id = ? AND user_id IS NULL");
            $stmt->bind_param("is", $cartId, $sessionId);
        }
        $stmt->execute();
        sendJson(true, "Produkt aus Warenkorb entfernt");

    case "getUserData":
        startSessionIfNeeded();

        $userId = $_SESSION["user_id"] ?? null;
        if (!$userId) {
            sendJson(false, "Nicht eingeloggt");
        }

        $db = new DBAccess();
        $conn = $db->getConnection();

        $stmt = $conn->prepare("
            SELECT firstname, lastname, address, zip, city
            FROM users
            WHERE id = ?
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $userData = $stmt->get_result()->fetch_assoc();

        if (!$userData) {
            sendJson(false, "Benutzer nicht gefunden");
        }

        sendJson(true, "OK", ["data" => $userData]);

    case "getUserPaymentMethods":
        startSessionIfNeeded();

        $userId = $_SESSION["user_id"] ?? null;
        if (!$userId) {
            sendJson(false, "Nicht eingeloggt");
        }

        $db = new DBAccess();
        $conn = $db->getConnection();

        $stmt = $conn->prepare("SELECT method FROM user_payment_methods WHERE user_id = ? ORDER BY id");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        $methods = [];
        while ($row = $result->fetch_assoc()) {
            $methods[] = $row["method"];
        }

        sendJson(true, "OK", ["data" => $methods]);

    case "validateCoupon":

        $code = trim($_GET["code"] ?? "");
        if ($code === "") {
            sendJson(false, "Kein Gutscheincode angegeben");
        }

        $db = new DBAccess();
        $conn = $db->getConnection();

        $stmt = $conn->prepare("
        SELECT id, code, discount_type, discount_value, expires_at, is_active, is_used
        FROM coupons
        WHERE code = ?
    ");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            sendJson(false, "Ungültiger Gutscheincode");
        }
        $coupon = $result->fetch_assoc();
        if ((int)$coupon["is_used"] === 1) {
            sendJson(false, "Gutschein wurde bereits eingelöst");
        }
        if ((int)$coupon["is_active"] !== 1) {
            sendJson(false, "Gutschein ist nicht mehr gültig");
        }
        if ($coupon["expires_at"] < date("Y-m-d")) {
            sendJson(false, "Gutschein ist abgelaufen");
        }
        sendJson(true, "Gutschein gültig", [
            "data" => [
                "code"           => $coupon["code"],
                "discount_type"  => $coupon["discount_type"],
                "discount_value" => (float)$coupon["discount_value"]
            ]
        ]);

    case "placeOrder":
        requireMethod("POST");
        startSessionIfNeeded();

        $userId = $_SESSION["user_id"] ?? null;
        if (!$userId) {
            sendJson(false, "Nicht eingeloggt");
        }

        $input = getJsonInput();

        $firstname = trim($input["firstname"] ?? "");
        $lastname = trim($input["lastname"] ?? "");
        $address = trim($input["address"] ?? "");
        $zip = trim($input["zip"] ?? "");
        $city = trim($input["city"] ?? "");
        $paymentMethod = trim($input["payment_method"] ?? "");

        if (
            $firstname === "" || $lastname === "" || $address === "" ||
            $zip === "" || $city === "" || $paymentMethod === ""
        ) {
            sendJson(false, "Bitte alle Pflichtfelder ausfüllen");
        }

        if (!preg_match('/^\d{4,5}$/', $zip)) {
            sendJson(false, "Ungültige PLZ");
        }

        $db = new DBAccess();
        $conn = $db->getConnection();

        $methodCheck = $conn->prepare("SELECT id FROM user_payment_methods WHERE user_id = ? AND method = ?");
        $methodCheck->bind_param("is", $userId, $paymentMethod);
        $methodCheck->execute();
        if ($methodCheck->get_result()->num_rows === 0) {
            sendJson(false, "Ungültige Zahlungsart");
        }

        $cartStmt = $conn->prepare("
            SELECT c.product_id, c.quantity, p.price
            FROM cart c
            JOIN products p ON p.id = c.product_id
            WHERE c.user_id = ?
        ");
        $cartStmt->bind_param("i", $userId);
        $cartStmt->execute();
        $cartItems = $cartStmt->get_result();

        if ($cartItems->num_rows === 0) {
            sendJson(false, "Dein Warenkorb ist leer");
        }

        $items = [];
        $total = 0;
        while ($row = $cartItems->fetch_assoc()) {
            $total += $row["price"] * $row["quantity"];
            $items[] = $row;
        }
        $couponCode = trim($input["coupon_code"] ?? "");
        $coupon = null;
        if ($couponCode !== "") {
            $couponStmt = $conn->prepare("
            SELECT * FROM coupons 
            WHERE code = ? 
              AND is_used = 0 
              AND expires_at >= CURDATE()
        ");
            $couponStmt->bind_param("s", $couponCode);
            $couponStmt->execute();
            $coupon = $couponStmt->get_result()->fetch_assoc();

            if ($coupon) {
                if ($coupon["discount_type"] === "percentage") {
                    $total -= $total * ($coupon["discount_value"] / 100);
                } else {
                    $total -= $coupon["discount_value"];
                }
                $total = max(0, round($total, 2));
            }
        }


        $orderStmt = $conn->prepare("
            INSERT INTO orders (user_id, total, firstname, lastname, address, zip, city, payment_method)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $orderStmt->bind_param("idssssss", $userId, $total, $firstname, $lastname, $address, $zip, $city, $paymentMethod);
        $orderStmt->execute();

        $orderId = $conn->insert_id;

        $itemStmt = $conn->prepare("
            INSERT INTO order_items (order_id, product_id, quantity, price)
            VALUES (?, ?, ?, ?)
        ");
        foreach ($items as $it) {
            $itemStmt->bind_param("iiid", $orderId, $it["product_id"], $it["quantity"], $it["price"]);
            $itemStmt->execute();
        }

        $clearStmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
        $clearStmt->bind_param("i", $userId);
        $clearStmt->execute();

        if (!empty($coupon)) {
            $markUsed = $conn->prepare("UPDATE coupons SET is_used = 1 WHERE code = ?");
            $markUsed->bind_param("s", $couponCode);
            $markUsed->execute();
        }
        sendJson(true, "Bestellung erfolgreich aufgegeben");


    case "getAccountdetails":
        startSessionIfNeeded();

        $userId = $_SESSION["user_id"] ?? null;
        if (!$userId) {
            sendJson(false, "Nicht eingeloggt");
        }
        $db = new DBAccess();
        $conn = $db->getConnection();

        $stmt = $conn->prepare("SELECT * from users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            sendJson(false, "User nicht gefunden");
        }

        $user = $result->fetch_assoc();
        $user["password"] = "********";
        $parts = explode("@", $user["email"]);
        $user["email"] = substr($parts[0], 0, 1) . str_repeat("*", strlen($parts[0]) - 1) . "@" . $parts[1];

        $payStmt = $conn->prepare("SELECT method FROM user_payment_methods WHERE user_id = ? ORDER BY id");
        $payStmt->bind_param("i", $userId);
        $payStmt->execute();
        $payResult = $payStmt->get_result();

        $methods = [];
        while ($row = $payResult->fetch_assoc()) {
            $methods[] = $row["method"];
        }

        sendJson(true, "OK", ["data" => $user, "payment_methods" => $methods]);

    case "editAccount":
        requireMethod("POST");
        startSessionIfNeeded();

        $userId = $_SESSION["user_id"] ?? null;
        if (!$userId) {
            sendJson(false, "Nicht eingeloggt");
        }
        $input = getJsonInput();

        $salutation     = trim($input["salutation"] ?? "");
        $firstname      = trim($input["firstname"] ?? "");
        $lastname       = trim($input["lastname"] ?? "");
        $email          = strtolower(trim($input["email"] ?? ""));
        $username       = trim($input["username"] ?? "");
        $address        = trim($input["address"] ?? "");
        $zip            = trim($input["zip"] ?? "");
        $city           = trim($input["city"] ?? "");
        $password       = $input["password"] ?? "";
        $paymentMethods = $input["payment_methods"] ?? [];

        if ($firstname === "" || $lastname === "" || $email === "" ||
            $username === "" || $address === "" || $zip === "" ||
            $city === "" || $password === "") {
            sendJson(false, "Bitte alle Pflichtfelder ausfüllen");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendJson(false, "Ungültige E-Mail-Adresse");
        }

        $db = new DBAccess();
        $conn = $db->getConnection();

        $pwStmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $pwStmt->bind_param("i", $userId);
        $pwStmt->execute();
        $pwRow = $pwStmt->get_result()->fetch_assoc();

        if (!password_verify($password, $pwRow["password"])) {
            sendJson(false, "Falsches Passwort");
        }
        $stmt = $conn->prepare("
        UPDATE users SET salutation=?, firstname=?, lastname=?, email=?,
        username=?, address=?, zip=?, city=? WHERE id=?
    ");
        $stmt->bind_param("ssssssssi", $salutation, $firstname, $lastname,
            $email, $username, $address, $zip, $city, $userId);
        $stmt->execute();
        $del = $conn->prepare("DELETE FROM user_payment_methods WHERE user_id = ?");
        $del->bind_param("i", $userId);
        $del->execute();

        if (!empty($paymentMethods)) {
            $ins = $conn->prepare("INSERT INTO user_payment_methods (user_id, method) VALUES (?, ?)");
            foreach ($paymentMethods as $m) {
                $m = trim($m);
                if ($m !== "") {
                    $ins->bind_param("is", $userId, $m);
                    $ins->execute();
                }
            }
        }

        sendJson(true, "Änderungen gespeichert");


    case "getOrders":
        startSessionIfNeeded();

        $userId = $_SESSION["user_id"] ?? null;
        if (!$userId) {
            sendJson(false, "Nicht eingeloggt");
        }

        $db = new DBAccess();
        $conn = $db->getConnection();

        $stmt = $conn->prepare("
            SELECT id, order_date, total, payment_method
            FROM orders
            WHERE user_id = ?
            ORDER BY order_date DESC
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $row["id"] = (int)$row["id"];
            $row["total"] = (float)$row["total"];
            $orders[] = $row;
        }

        sendJson(true, "OK", ["data" => $orders]);

    case "getOrderDetails":
        startSessionIfNeeded();

        $userId = $_SESSION["user_id"] ?? null;
        if (!$userId) {
            sendJson(false, "Nicht eingeloggt");
        }

        $orderId = isset($_GET["order"]) ? (int)$_GET["order"] : 0;
        if ($orderId <= 0) {
            sendJson(false, "Ungültige Bestell-ID");
        }

        $db = new DBAccess();
        $conn = $db->getConnection();

        $orderStmt = $conn->prepare("
            SELECT id, order_date, total, firstname, lastname, address, zip, city, payment_method
            FROM orders
            WHERE id = ? AND user_id = ?
        ");
        $orderStmt->bind_param("ii", $orderId, $userId);
        $orderStmt->execute();
        $order = $orderStmt->get_result()->fetch_assoc();

        if (!$order) {
            sendJson(false, "Bestellung nicht gefunden");
        }

        $itemStmt = $conn->prepare("
            SELECT oi.product_id, oi.quantity, oi.price, p.name, p.image
            FROM order_items oi
            JOIN products p ON p.id = oi.product_id
            WHERE oi.order_id = ?
        ");
        $itemStmt->bind_param("i", $orderId);
        $itemStmt->execute();
        $itemResult = $itemStmt->get_result();

        $items = [];
        while ($row = $itemResult->fetch_assoc()) {
            $row["quantity"] = (int)$row["quantity"];
            $row["price"] = (float)$row["price"];
            $row["subtotal"] = round($row["price"] * $row["quantity"], 2);
            $items[] = $row;
        }

        $order["total"] = (float)$order["total"];

        sendJson(true, "OK", ["data" => ["order" => $order, "items" => $items]]);

    case "getInvoice":
        startSessionIfNeeded();

        $userId = $_SESSION["user_id"] ?? null;
        if (!$userId) {
            sendJson(false, "Nicht eingeloggt");
        }

        $orderId = isset($_GET["order"]) ? (int)$_GET["order"] : 0;
        if ($orderId <= 0) {
            sendJson(false, "Ungültige Bestell-ID");
        }

        $db = new DBAccess();
        $conn = $db->getConnection();

        $orderStmt = $conn->prepare("
            SELECT id, order_date, total, firstname, lastname, address, zip, city, payment_method
            FROM orders
            WHERE id = ? AND user_id = ?
        ");
        $orderStmt->bind_param("ii", $orderId, $userId);
        $orderStmt->execute();
        $order = $orderStmt->get_result()->fetch_assoc();

        if (!$order) {
            sendJson(false, "Bestellung nicht gefunden");
        }

        $invStmt = $conn->prepare("SELECT invoice_number, invoice_date FROM invoices WHERE order_id = ?");
        $invStmt->bind_param("i", $orderId);
        $invStmt->execute();
        $invoice = $invStmt->get_result()->fetch_assoc();

        if (!$invoice) {
            $next = $conn->query("SELECT IFNULL(MAX(id), 0) + 1 AS next_id FROM invoices");
            $nextId = (int)$next->fetch_assoc()["next_id"];
            $invoiceNumber = sprintf("R-%d-%04d", (int)date('Y'), $nextId);

            $newStmt = $conn->prepare("INSERT INTO invoices (order_id, invoice_number) VALUES (?, ?)");
            $newStmt->bind_param("is", $orderId, $invoiceNumber);
            $newStmt->execute();

            $invStmt->execute();
            $invoice = $invStmt->get_result()->fetch_assoc();
        }

        $itemStmt = $conn->prepare("
            SELECT oi.product_id, oi.quantity, oi.price, p.name, p.image
            FROM order_items oi
            JOIN products p ON p.id = oi.product_id
            WHERE oi.order_id = ?
        ");
        $itemStmt->bind_param("i", $orderId);
        $itemStmt->execute();
        $itemResult = $itemStmt->get_result();

        $items = [];
        while ($row = $itemResult->fetch_assoc()) {
            $row["quantity"] = (int)$row["quantity"];
            $row["price"] = (float)$row["price"];
            $row["subtotal"] = round($row["price"] * $row["quantity"], 2);
            $items[] = $row;
        }

        $order["total"] = (float)$order["total"];

        sendJson(true, "OK", ["data" => [
            "invoice" => $invoice,
            "order" => $order,
            "items" => $items
        ]]);


    case "getAllUsers":
        requireMethod("GET");
        startSessionIfNeeded();

        if (empty($_SESSION["user_id"]) || (int)($_SESSION["is_admin"] ?? 0) !== 1) {
            sendJson(false, "Keine Berechtigung");
        }

        $db = new DBAccess();
        $conn = $db->getConnection();

        $stmt = $conn->prepare("SELECT id, username, firstname, lastname, email, salutation, address, zip, city, payment_info, is_admin, is_active FROM users ORDER BY id DESC");
        $stmt->execute();
        $result = $stmt->get_result();

        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        sendJson(true, "OK", ["users" => $users]);

    case "getUserOrders":
        requireMethod("GET");
        startSessionIfNeeded();

        if (empty($_SESSION["user_id"]) || (int)($_SESSION["is_admin"] ?? 0) !== 1) {
            sendJson(false, "Keine Berechtigung");
        }


        $userId = (int)($_GET["user_id"] ?? 0);
        if ($userId === 0) {
            sendJson(false, "Ungültige User ID");
        }

        $db = new DBAccess();
        $conn = $db->getConnection();

        $stmt = $conn->prepare("SELECT id, order_date, total, firstname, lastname, address, zip, city, payment_method FROM orders WHERE user_id = ? ORDER BY order_date DESC");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
        sendJson(true, "OK", ["orders" => $orders]);


    case "toggleUser":
        requireMethod("POST");
        startSessionIfNeeded();

        if (empty($_SESSION["user_id"]) || (int)($_SESSION["is_admin"] ?? 0) !== 1) {
            sendJson(false, "Keine Berechtigung");
        }

        $userId   = (int)($_POST["user_id"] ?? 0);
        $isActive = (int)($_POST["is_active"] ?? 0);

        if ($userId === 0) {
            sendJson(false, "Ungültige User ID");
        }


        if ($userId === (int)$_SESSION["user_id"]) {
            sendJson(false, "Du kannst dich nicht selbst deaktivieren.");
        }

        $db = new DBAccess();
        $conn = $db->getConnection();

        $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ?");
        $stmt->bind_param("ii", $isActive, $userId);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            sendJson(false, "Benutzer nicht gefunden.");
        }

        sendJson(true, $isActive == 1 ? "Kunde aktiviert." : "Kunde deaktiviert.");

    case "getOrderItems":
        requireMethod("GET");
        startSessionIfNeeded();

        if (empty($_SESSION["user_id"]) || (int)($_SESSION["is_admin"] ?? 0) !== 1) {
            sendJson(false, "Keine Berechtigung");
        }

        $orderId = (int)($_GET["order_id"] ?? 0);
        if ($orderId === 0) sendJson(false, "Ungültige Order ID");

        $db = new DBAccess();
        $conn = $db->getConnection();

        $stmt = $conn->prepare("
        SELECT oi.id, oi.quantity, oi.price, p.name AS product_name
        FROM order_items oi
        JOIN products p ON p.id = oi.product_id
        WHERE oi.order_id = ?
    ");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $result = $stmt->get_result();

        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        sendJson(true, "OK", ["items" => $items]);

    case "removeOrderItem":
        requireMethod("POST");
        startSessionIfNeeded();

        if (empty($_SESSION["user_id"]) || (int)($_SESSION["is_admin"] ?? 0) !== 1) {
            sendJson(false, "Keine Berechtigung");
        }

        $itemId = (int)($_POST["item_id"] ?? 0);
        if ($itemId === 0) {
            sendJson(false, "Ungültige Item ID");
        }

        $db = new DBAccess();
        $conn = $db->getConnection();


        $stmt = $conn->prepare("SELECT order_id, price, quantity FROM order_items WHERE id = ?");
        $stmt->bind_param("i", $itemId);
        $stmt->execute();
        $result = $stmt->get_result();
        $item = $result->fetch_assoc();

        if (!$item) {
            sendJson(false, "Produkt nicht gefunden.");
        }

        $stmt = $conn->prepare("DELETE FROM order_items WHERE id = ?");
        $stmt->bind_param("i", $itemId);
        $stmt->execute();

        $stmt = $conn->prepare("UPDATE orders SET total = (SELECT COALESCE(SUM(price * quantity), 0) FROM order_items WHERE order_id = ?) WHERE id = ?");
        $stmt->bind_param("ii", $item["order_id"], $item["order_id"]);
        $stmt->execute();

        sendJson(true, "Produkt aus Bestellung entfernt.");


    case "getCoupons":
        requireMethod("GET");
        startSessionIfNeeded();

        if (empty($_SESSION["user_id"]) || (int)($_SESSION["is_admin"] ?? 0) !== 1) {
            sendJson(false, "Keine Berechtigung");
        }
        $db = new DBAccess();
        $conn = $db->getConnection();

        $stmt = $conn->prepare("SELECT id, code, discount_type, discount_value, expires_at, is_active, is_used  FROM coupons ORDER BY id DESC");
        $stmt->execute();
        $result = $stmt->get_result();

        $coupons = [];
        while ($row = $result->fetch_assoc()) {
            $coupons[] = $row;
        }
        sendJson(true, "OK", ["coupons" => $coupons]);

    case "createCoupon":
        requireMethod("POST");
        startSessionIfNeeded();

        if (empty($_SESSION["user_id"]) || (int)($_SESSION["is_admin"] ?? 0) !== 1) {
            sendJson(false, "Keine Berechtigung");
        }
        $discountType  = $_POST["discount_type"] ?? "";
        $discountValue = (float)($_POST["discount_value"] ?? 0);
        $expiresAt     = $_POST["expires_at"] ?? "";
        $customCode    = strtoupper(trim($_POST["code"] ?? ""));

        if (!in_array($discountType, ["fixed", "percentage"])) {
            sendJson(false, "Ungültiger Rabatttyp");
        }
        if ($discountValue <= 0) {
            sendJson(false, "Ungültiger Rabattwert");
        }
        if (empty($expiresAt)) {
            sendJson(false, "Ablaufdatum fehlt");
        }

        $db = new DBAccess();
        $conn = $db->getConnection();

        if ($customCode !== "") {
            if (!preg_match('/^[A-Z0-9_-]+$/', $customCode)) {
                sendJson(false, "Code darf nur Buchstaben, Zahlen, - und _ enthalten.");
            }
            $check = $conn->prepare("SELECT id FROM coupons WHERE code = ?");
            $check->bind_param("s", $customCode);
            $check->execute();
            $check->store_result();
            if ($check->num_rows > 0) {
                sendJson(false, "Dieser Code existiert bereits.");
            }
            $code = $customCode;
        } else {
            do {
                $code = strtoupper(substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 5));
                $check = $conn->prepare("SELECT id FROM coupons WHERE code = ?");
                $check->bind_param("s", $code);
                $check->execute();
                $check->store_result();
            } while ($check->num_rows > 0);
        }

        $stmt = $conn->prepare("INSERT INTO coupons (code, discount_type, discount_value, expires_at) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssds", $code, $discountType, $discountValue, $expiresAt);
        $stmt->execute();

        sendJson(true, "Gutschein erstellt.", ["code" => $code]);


    default:
        sendJson(false, "Unknown method: '" . $method . "'. GET params: " . json_encode($_GET));
}