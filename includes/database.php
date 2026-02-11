<?php
class Database {
    private $host = DB_HOST;
    private $username = DB_USER;
    private $password = DB_PASS;
    private $database = DB_NAME;
    private $conn = null;
    public function __construct() {
        $this->getConnection();
    }

    public function getConnection() {
        if ($this->conn === null) {
            try {
                $this->conn = new PDO(
                    "mysql:host={$this->host};dbname={$this->database};charset=utf8mb4",
                    $this->username,
                    $this->password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
            } catch(PDOException $e) {
                die("Connection failed: " . $e->getMessage());
            }
        }
        return $this->conn;
    }
    
    public function closeConnection() {
        $this->conn = null;
    }
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch(PDOException $e) {
            error_log("Query error: " . $e->getMessage());
            return false;
        }
    }
    
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetch() : false;
    }
    
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetchAll() : false;
    }
    
    public function insert($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt ? $this->conn->lastInsertId() : false;
    }
    
    public function execute($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->rowCount() : false;
    }
}
?>