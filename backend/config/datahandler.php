<?php

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

    default:
        sendJson(false, "Unknown method");
}