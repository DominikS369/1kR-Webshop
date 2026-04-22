<?php

class DBAccess {
    private mysqli $conn;

    public function __construct() {
        $this->conn = new mysqli(
            "localhost",
            "root",
            "root",
            "Webshop_1kR",
            8889
        );

        if ($this->conn->connect_error) {
            die("DB Fehler: " . $this->conn->connect_error);
        }

        $this->conn->set_charset("utf8mb4");
    }

    public function getConnection(): mysqli {
        return $this->conn;
    }
}