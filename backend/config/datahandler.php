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
        $payment_info = trim($input["payment_info"] ?? "");

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

            $defaultMethods = ["Auf Rechnung", "Kreditkarte", "PayPal"];
            $methodStmt = $conn->prepare("INSERT INTO user_payment_methods (user_id, method) VALUES (?, ?)");
            foreach ($defaultMethods as $method) {
                $methodStmt->bind_param("is", $newUserId, $method);
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

    default:
        sendJson(false, "Unknown method: '" . $method . "'. GET params: " . json_encode($_GET));
}