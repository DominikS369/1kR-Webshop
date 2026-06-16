<?php // Jeder API-Case erzeugt new DBAccess() und holt sich mit getConnection() die mysqli-Verbindung. dbaccess.php ist somit die Verbindun verbindungsklasse zur DB und datahandler

require_once __DIR__ . "/dbconfig.php";

class DBAccess
{
    private mysqli $conn;

    public function __construct()
    {
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

        if ($this->conn->connect_error) {
            die("DB Fehler: " . $this->conn->connect_error);
        }

        $this->conn->set_charset("utf8mb4");
    }

    public function getConnection(): mysqli
    {
        return $this->conn;
    }
}