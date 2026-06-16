<?php // Jeder API-Case erzeugt new DBAccess() und holt sich mit getConnection() die mysqli-Verbindung. dbaccess.php ist somit die Verbindun verbindungsklasse zur DB und datahandler

/*dbconfig.php notwendig
Für Mac:

<?php
define("DB_HOST", "localhost");
define("DB_USER", "root");
define("DB_PASS", "root");
define("DB_NAME", "Webshop_1kR");
define("DB_PORT", 8889);

Für Windows:

<?php
define("DB_HOST", "localhost");
define("DB_USER", "root");
define("DB_PASS", "");
define("DB_NAME", "Webshop_1kR");
define("DB_PORT", 3306);

*/
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