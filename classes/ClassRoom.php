<?php
class ClassRoom {
    private $conn;
    private $table_name = "classes";

    public $id;
    public $nom;
    public $enseignant_id;
    public $year_level_id = null;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET nom=:nom, enseignant_id=:enseignant_id, year_level_id=:year_level_id";

        $stmt = $this->conn->prepare($query);

        $this->nom = htmlspecialchars(strip_tags($this->nom));

        $stmt->bindParam(":nom", $this->nom);
        $stmt->bindParam(":enseignant_id", $this->enseignant_id);
        $stmt->bindParam(":year_level_id", $this->year_level_id, PDO::PARAM_INT);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function getByTeacher($teacher_id) {
        $query = "SELECT c.*, y.name as year_level_name 
                  FROM " . $this->table_name . " c
                  LEFT JOIN year_levels y ON c.year_level_id = y.id
                  WHERE c.enseignant_id = :teacher_id 
                  ORDER BY c.nom ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':teacher_id', $teacher_id);
        $stmt->execute();

        return $stmt;
    }

    public function getById($id) {
        $query = "SELECT c.*, y.name as year_level_name 
                  FROM " . $this->table_name . " c
                  LEFT JOIN year_levels y ON c.year_level_id = y.id
                  WHERE c.id = :id LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if($row) {
            $this->id = $row['id'];
            $this->nom = $row['nom'];
            $this->enseignant_id = $row['enseignant_id'];
            $this->year_level_id = $row['year_level_id'];
            return true;
        }
        return false;
    }

    public function update() {
        $query = "UPDATE " . $this->table_name . " 
                  SET nom = :nom, year_level_id = :year_level_id 
                  WHERE id = :id AND enseignant_id = :enseignant_id";

        $stmt = $this->conn->prepare($query);

        $this->nom = htmlspecialchars(strip_tags($this->nom));

        $stmt->bindParam(':nom', $this->nom);
        $stmt->bindParam(':year_level_id', $this->year_level_id, PDO::PARAM_INT);
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':enseignant_id', $this->enseignant_id);

        return $stmt->execute();
    }

    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " 
                  WHERE id = :id AND enseignant_id = :enseignant_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':enseignant_id', $this->enseignant_id);

        return $stmt->execute();
    }

    public function getStudentCount($class_id) {
        $query = "SELECT COUNT(*) as count FROM eleves WHERE classe_id = :class_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':class_id', $class_id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['count'];
    }

    public function classNameExists($nom, $enseignant_id, $exclude_id = null) {
        $query = "SELECT COUNT(*) as count FROM " . $this->table_name . " 
                  WHERE nom = :nom AND enseignant_id = :enseignant_id";
        
        if ($exclude_id) {
            $query .= " AND id != :exclude_id";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':nom', $nom);
        $stmt->bindParam(':enseignant_id', $enseignant_id);
        
        if ($exclude_id) {
            $stmt->bindParam(':exclude_id', $exclude_id);
        }
        
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row['count'] > 0;
    }
}
?>