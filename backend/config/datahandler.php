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

    default:
        break;
}

echo json_encode($response);