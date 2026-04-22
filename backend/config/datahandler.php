<?php

header("Content-Type: application/json");

require_once "dbaccess.php";

$method = $_GET["method"] ?? "";

$response = [
    "success" => false,
    "message" => "Unknown method"
];

switch ($method) {

    case "test":
        $response["success"] = true;
        $response["message"] = "API läuft";
        break;

    case "register":
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            $response["message"] = "Nur POST erlaubt";
            echo json_encode($response);
            exit;
        }

        $input = json_decode(file_get_contents("php://input"), true);

        if (!$input) {
            $response["message"] = "Keine gültigen Daten erhalten";
            echo json_encode($response);
            exit;
        }

        $salutation = trim($input["salutation"] ?? "");
        $firstname = trim($input["firstname"] ?? "");
        $lastname = trim($input["lastname"] ?? "");
        $address = trim($input["address"] ?? "");
        $zip = trim($input["zip"] ?? "");
        $city = trim($input["city"] ?? "");
        $email = trim($input["email"] ?? "");
        $username = trim($input["username"] ?? "");
        $password = $input["password"] ?? "";
        $password2 = $input["password2"] ?? "";
        $payment_info = trim($input["payment_info"] ?? "");

        if (
            $firstname === "" || $lastname === "" || $address === "" ||
            $zip === "" || $city === "" || $email === "" ||
            $username === "" || $password === "" || $password2 === ""
        ) {
            $response["message"] = "Bitte alle Pflichtfelder ausfüllen";
            echo json_encode($response);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response["message"] = "Ungültige E-Mail-Adresse";
            echo json_encode($response);
            exit;
        }

        if ($password !== $password2) {
            $response["message"] = "Passwörter stimmen nicht überein";
            echo json_encode($response);
            exit;
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $db = new DBAccess();
        $conn = $db->getConnection();

        $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $checkStmt->bind_param("ss", $email, $username);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            $response["message"] = "E-Mail oder Benutzername bereits vergeben";
            echo json_encode($response);
            exit;
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
            $response["success"] = true;
            $response["message"] = "Registrierung erfolgreich";
        } else {
            $response["message"] = "Fehler beim Speichern";
        }

        break;


    case "login":

        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            $response["message"] = "Nur POST erlaubt";
            echo json_encode($response);
            exit;
        }

        $input = json_decode(file_get_contents("php://input"), true);

        $username = trim($input["username"] ?? "");
        $password = $input["password"] ?? "";

        if ($username === "" || $password === "") {
            $response["message"] = "Bitte Username und Passwort eingeben";
            echo json_encode($response);
            exit;
        }

        $db = new DBAccess();
        $conn = $db->getConnection();

        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $response["message"] = "User nicht gefunden";
            echo json_encode($response);
            exit;
        }

        $user = $result->fetch_assoc();

        if (!password_verify($password, $user["password"])) {
            $response["message"] = "Falsches Passwort";
            echo json_encode($response);
            exit;
        }

        session_start();

        $_SESSION["user_id"] = $user["id"];
        $_SESSION["username"] = $user["username"];
        $_SESSION["is_admin"] = $user["is_admin"];

        $response["success"] = true;
        $response["message"] = "Login erfolgreich";

        break;


    default:
        break;
}

echo json_encode($response);