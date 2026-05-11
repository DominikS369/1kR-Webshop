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

    default:
        sendJson(false, "Unknown method");
}