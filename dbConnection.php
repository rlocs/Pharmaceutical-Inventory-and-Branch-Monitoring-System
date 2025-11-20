<?php
class Database {
    // This IP MUST be the address of the computer running the MySQL database.
    private $host = '100.71.125.101';

    private $db_name = 'pharmaceutical_db';
    private $username = 'pharma_user'; // The new user you created for network access
    private $password = '2003'; // The new password you created (removed typo 'a$')
    private $conn;

    public function __construct() {
        $this->connect();
    }

    private function connect() {
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            // Hide sensitive error from users, log it instead
            error_log("DB Connection error in dbconnection.php: " . $e->getMessage());
            // Re-throw the exception so the calling script can handle it.
            throw $e;
        }
    }

    public function getConnection() {
        return $this->conn;
    }
}
?>