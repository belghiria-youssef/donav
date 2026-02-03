<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'no9ati_db';
    private $username = 'root';
    private $password = '';
    public $conn;

    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        
        return $this->conn;
    }

    public function createTables() {
        $queries = [
            "CREATE TABLE IF NOT EXISTS enseignants (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nom VARCHAR(100) NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                mot_de_passe VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            
            "CREATE TABLE IF NOT EXISTS classes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nom VARCHAR(50) NOT NULL,
                enseignant_id INT NOT NULL,
                year_level_id INT DEFAULT NULL COMMENT 'Reference to year_levels table',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (enseignant_id) REFERENCES enseignants(id) ON DELETE CASCADE
            )",
            
            "CREATE TABLE IF NOT EXISTS eleves (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nom VARCHAR(100) NOT NULL,
                classe_id INT NOT NULL,
                points INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (classe_id) REFERENCES classes(id) ON DELETE CASCADE
            )",
            
            "CREATE TABLE IF NOT EXISTS journal_points (
                id INT AUTO_INCREMENT PRIMARY KEY,
                eleve_id INT NOT NULL,
                points_ajoutes INT NOT NULL,
                raison VARCHAR(255) NOT NULL,
                date_ajout TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE
            )"
        ];

        $this->conn = $this->getConnection();

        try {
            foreach ($queries as $query) {
                $this->conn->exec($query);
            }
            return true;
        } catch(PDOException $exception) {
            echo "Table creation error: " . $exception->getMessage();
            return false;
        }
    }
}

// (new Database())->createTables();

?>