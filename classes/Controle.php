<?php
/**
 * Controle Class
 * Manages controle templates (structure definition) and instances (grading per class)
 * 
 * Architecture:
 * - Templates: Define the structure of a controle (phases, points, grading modes) per year level
 * - Instances: Apply a template to a specific class for actual grading
 * - Phases: Can be individual (each student graded) or team (all team members get same note)
 */
class Controle {
    private $conn;
    
    // Table names
    private $templates_table = "controle_templates";
    private $phases_table = "controle_template_phases";
    private $instances_table = "controle_instances";
    private $notes_individual_table = "controle_notes_individual";
    private $notes_team_table = "controle_notes_team";
    private $results_table = "controle_results";
    private $year_levels_table = "year_levels";

    public function __construct($db) {
        $this->conn = $db;
    }

    // ============================================================================
    // YEAR LEVEL MANAGEMENT - Teacher-defined year levels
    // ============================================================================

    /**
     * Create a new year level
     */
    public function createYearLevel($name, $short_name, $order_index, $created_by) {
        $query = "INSERT INTO " . $this->year_levels_table . " 
                  (name, short_name, order_index, created_by) 
                  VALUES (:name, :short_name, :order_index, :created_by)";
        
        $stmt = $this->conn->prepare($query);
        
        $name = htmlspecialchars(strip_tags($name));
        $short_name = htmlspecialchars(strip_tags($short_name));
        
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':short_name', $short_name);
        $stmt->bindParam(':order_index', $order_index, PDO::PARAM_INT);
        $stmt->bindParam(':created_by', $created_by);
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    /**
     * Get all year levels
     */
    public function getYearLevels($teacher_id = null) {
        $query = "SELECT * FROM " . $this->year_levels_table;
        if ($teacher_id) {
            $query .= " WHERE created_by = :teacher_id";
        }
        $query .= " ORDER BY order_index ASC, name ASC";
        
        $stmt = $this->conn->prepare($query);
        if ($teacher_id) {
            $stmt->bindParam(':teacher_id', $teacher_id);
        }
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get year level by ID
     */
    public function getYearLevelById($id) {
        $query = "SELECT * FROM " . $this->year_levels_table . " WHERE id = :id LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Update year level
     */
    public function updateYearLevel($id, $name, $short_name, $order_index) {
        $query = "UPDATE " . $this->year_levels_table . " 
                  SET name = :name, short_name = :short_name, order_index = :order_index 
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        $name = htmlspecialchars(strip_tags($name));
        $short_name = htmlspecialchars(strip_tags($short_name));
        
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':short_name', $short_name);
        $stmt->bindParam(':order_index', $order_index, PDO::PARAM_INT);
        $stmt->bindParam(':id', $id);
        
        return $stmt->execute();
    }

    /**
     * Delete year level (only if not used by any class or template)
     */
    public function deleteYearLevel($id) {
        // Check if used by classes
        $check = "SELECT COUNT(*) as cnt FROM classes WHERE year_level_id = :id";
        $stmt = $this->conn->prepare($check);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        if ($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] > 0) {
            return false;
        }
        
        // Check if used by templates
        $check = "SELECT COUNT(*) as cnt FROM " . $this->templates_table . " WHERE year_level_id = :id";
        $stmt = $this->conn->prepare($check);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        if ($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] > 0) {
            return false;
        }
        
        $query = "DELETE FROM " . $this->year_levels_table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        
        return $stmt->execute();
    }

    // ============================================================================
    // TEMPLATE MANAGEMENT - Define controle structure per year level
    // ============================================================================

    /**
     * Create a new controle template
     */
    public function createTemplate($title, $year_level_id, $total_points, $description, $created_by) {
        $query = "INSERT INTO " . $this->templates_table . " 
                  (title, year_level_id, total_points, description, created_by) 
                  VALUES (:title, :year_level_id, :total_points, :description, :created_by)";
        
        $stmt = $this->conn->prepare($query);
        
        $title = htmlspecialchars(strip_tags($title));
        $description = htmlspecialchars(strip_tags($description));
        $total_points = $total_points ?: 20;
        
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':year_level_id', $year_level_id);
        $stmt->bindParam(':total_points', $total_points);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':created_by', $created_by);
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    /**
     * Get template by ID
     */
    public function getTemplateById($template_id) {
        $query = "SELECT t.*, e.nom as created_by_name, y.name as year_level_name,
                         (SELECT COUNT(*) FROM " . $this->phases_table . " WHERE template_id = t.id) as phase_count,
                         (SELECT COALESCE(SUM(points), 0) FROM " . $this->phases_table . " WHERE template_id = t.id) as total_phase_points
                  FROM " . $this->templates_table . " t
                  JOIN enseignants e ON t.created_by = e.id
                  LEFT JOIN " . $this->year_levels_table . " y ON t.year_level_id = y.id
                  WHERE t.id = :id LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $template_id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get all templates for a specific year level
     */
    public function getTemplatesByYearLevel($year_level_id) {
        $query = "SELECT t.*, e.nom as created_by_name, y.name as year_level_name,
                         (SELECT COUNT(*) FROM " . $this->phases_table . " WHERE template_id = t.id) as phase_count,
                         (SELECT COALESCE(SUM(points), 0) FROM " . $this->phases_table . " WHERE template_id = t.id) as total_phase_points
                  FROM " . $this->templates_table . " t
                  JOIN enseignants e ON t.created_by = e.id
                  LEFT JOIN " . $this->year_levels_table . " y ON t.year_level_id = y.id
                  WHERE t.year_level_id = :year_level_id AND t.is_active = 1
                  ORDER BY t.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':year_level_id', $year_level_id);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all templates by teacher
     */
    public function getTemplatesByTeacher($teacher_id) {
        $query = "SELECT t.*, e.nom as created_by_name, y.name as year_level_name,
                         (SELECT COUNT(*) FROM " . $this->phases_table . " WHERE template_id = t.id) as phase_count,
                         (SELECT COALESCE(SUM(points), 0) FROM " . $this->phases_table . " WHERE template_id = t.id) as total_phase_points
                  FROM " . $this->templates_table . " t
                  JOIN enseignants e ON t.created_by = e.id
                  LEFT JOIN " . $this->year_levels_table . " y ON t.year_level_id = y.id
                  WHERE t.created_by = :teacher_id
                  ORDER BY y.order_index ASC, t.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':teacher_id', $teacher_id);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all templates (grouped by year)
     */
    public function getAllTemplates() {
        $query = "SELECT t.*, e.nom as created_by_name, y.name as year_level_name,
                         (SELECT COUNT(*) FROM " . $this->phases_table . " WHERE template_id = t.id) as phase_count,
                         (SELECT COALESCE(SUM(points), 0) FROM " . $this->phases_table . " WHERE template_id = t.id) as total_phase_points
                  FROM " . $this->templates_table . " t
                  JOIN enseignants e ON t.created_by = e.id
                  LEFT JOIN " . $this->year_levels_table . " y ON t.year_level_id = y.id
                  WHERE t.is_active = 1
                  ORDER BY y.order_index ASC, t.title ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update a template
     */
    public function updateTemplate($template_id, $title, $year_level_id, $total_points, $description) {
        $query = "UPDATE " . $this->templates_table . " 
                  SET title = :title, year_level_id = :year_level_id, total_points = :total_points, description = :description 
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        $title = htmlspecialchars(strip_tags($title));
        $description = htmlspecialchars(strip_tags($description));
        
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':year_level_id', $year_level_id);
        $stmt->bindParam(':total_points', $total_points);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':id', $template_id);
        
        return $stmt->execute();
    }

    /**
     * Deactivate a template (soft delete)
     */
    public function deactivateTemplate($template_id) {
        $query = "UPDATE " . $this->templates_table . " SET is_active = 0 WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $template_id);
        
        return $stmt->execute();
    }

    /**
     * Delete a template (hard delete - only if no instances exist)
     */
    public function deleteTemplate($template_id) {
        // Check if template has instances
        $check = "SELECT COUNT(*) as cnt FROM " . $this->instances_table . " WHERE template_id = :id";
        $stmt = $this->conn->prepare($check);
        $stmt->bindParam(':id', $template_id);
        $stmt->execute();
        
        if ($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] > 0) {
            return false; // Cannot delete template with instances
        }
        
        $query = "DELETE FROM " . $this->templates_table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $template_id);
        
        return $stmt->execute();
    }

    // ============================================================================
    // PHASE MANAGEMENT - Define phases within a template
    // ============================================================================

    /**
     * Add a phase to a template
     * @param int $points - How many points this phase is worth (out of template total)
     */
    public function addPhase($template_id, $title, $points, $grading_mode = 'individual') {
        // Validate grading mode
        if (!in_array($grading_mode, ['individual', 'team'])) {
            return false;
        }

        // Get current order index
        $order_query = "SELECT COALESCE(MAX(order_index), -1) + 1 as next_order 
                        FROM " . $this->phases_table . " 
                        WHERE template_id = :template_id";
        $stmt = $this->conn->prepare($order_query);
        $stmt->bindParam(':template_id', $template_id);
        $stmt->execute();
        $order = $stmt->fetch(PDO::FETCH_ASSOC)['next_order'];

        $query = "INSERT INTO " . $this->phases_table . " 
                  (template_id, title, points, grading_mode, order_index) 
                  VALUES (:template_id, :title, :points, :grading_mode, :order_index)";
        
        $stmt = $this->conn->prepare($query);
        
        $title = htmlspecialchars(strip_tags($title));
        
        $stmt->bindParam(':template_id', $template_id);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':points', $points);
        $stmt->bindParam(':grading_mode', $grading_mode);
        $stmt->bindParam(':order_index', $order);
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    /**
     * Get all phases for a template
     */
    public function getPhases($template_id) {
        $query = "SELECT * FROM " . $this->phases_table . " 
                  WHERE template_id = :template_id 
                  ORDER BY order_index ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':template_id', $template_id);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get phase by ID
     */
    public function getPhaseById($phase_id) {
        $query = "SELECT p.*, t.total_points as template_total_points
                  FROM " . $this->phases_table . " p
                  JOIN " . $this->templates_table . " t ON p.template_id = t.id
                  WHERE p.id = :id LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $phase_id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Update a phase
     */
    public function updatePhase($phase_id, $title, $points, $grading_mode) {
        if (!in_array($grading_mode, ['individual', 'team'])) {
            return false;
        }

        $query = "UPDATE " . $this->phases_table . " 
                  SET title = :title, points = :points, grading_mode = :grading_mode 
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        $title = htmlspecialchars(strip_tags($title));
        
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':points', $points);
        $stmt->bindParam(':grading_mode', $grading_mode);
        $stmt->bindParam(':id', $phase_id);
        
        return $stmt->execute();
    }

    /**
     * Delete a phase
     */
    public function deletePhase($phase_id) {
        $query = "DELETE FROM " . $this->phases_table . " WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $phase_id);
        
        return $stmt->execute();
    }

    /**
     * Reorder phases
     */
    public function reorderPhases($template_id, $phase_ids_ordered) {
        foreach ($phase_ids_ordered as $index => $phase_id) {
            $query = "UPDATE " . $this->phases_table . " 
                      SET order_index = :order_index 
                      WHERE id = :id AND template_id = :template_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':order_index', $index);
            $stmt->bindParam(':id', $phase_id);
            $stmt->bindParam(':template_id', $template_id);
            $stmt->execute();
        }
        return true;
    }

    /**
     * Validate that phase points sum equals template total_points
     */
    public function validatePhasePoints($template_id) {
        // Get template total points
        $template = $this->getTemplateById($template_id);
        if (!$template) {
            return false;
        }
        
        // Get sum of phase points
        $query = "SELECT COALESCE(SUM(points), 0) as total 
                  FROM " . $this->phases_table . " 
                  WHERE template_id = :template_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':template_id', $template_id);
        $stmt->execute();
        
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        return $total == $template['total_points'];
    }

    // ============================================================================
    // INSTANCE MANAGEMENT - Apply template to a class for grading
    // ============================================================================

    /**
     * Create a controle instance (apply template to a class)
     */
    public function createInstance($template_id, $class_id, $created_by, $session_name = null) {
        // Verify template exists and class year matches template year
        $template = $this->getTemplateById($template_id);
        if (!$template) {
            return false;
        }

        // Verify class exists and get its year level
        $class_query = "SELECT year_level_id FROM classes WHERE id = :class_id";
        $stmt = $this->conn->prepare($class_query);
        $stmt->bindParam(':class_id', $class_id);
        $stmt->execute();
        $class = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$class || $class['year_level_id'] != $template['year_level_id']) {
            return false; // Class year must match template year
        }

        $query = "INSERT INTO " . $this->instances_table . " 
                  (template_id, class_id, session_name, created_by) 
                  VALUES (:template_id, :class_id, :session_name, :created_by)";
        
        $stmt = $this->conn->prepare($query);
        
        $session_name = $session_name ? htmlspecialchars(strip_tags($session_name)) : null;
        
        $stmt->bindParam(':template_id', $template_id);
        $stmt->bindParam(':class_id', $class_id);
        $stmt->bindParam(':session_name', $session_name);
        $stmt->bindParam(':created_by', $created_by);
        
        if ($stmt->execute()) {
            $instance_id = $this->conn->lastInsertId();
            
            // IMPORTANT: Create empty result records for all students in the class
            // This ensures we can track individual student progress for certificates
            $students_query = "SELECT id FROM eleves WHERE classe_id = :class_id";
            $students_stmt = $this->conn->prepare($students_query);
            $students_stmt->bindParam(':class_id', $class_id);
            $students_stmt->execute();
            $students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Insert a result record for each student (with -1 to indicate not yet graded)
            $result_insert = "INSERT INTO " . $this->results_table . " 
                             (instance_id, student_id, final_note, breakdown_json, computed_at) 
                             VALUES (:instance_id, :student_id, -1, '[]', NOW())";
            $result_stmt = $this->conn->prepare($result_insert);
            
            foreach ($students as $student) {
                $result_stmt->bindParam(':instance_id', $instance_id);
                $result_stmt->bindParam(':student_id', $student['id']);
                $result_stmt->execute();
            }
            
            return $instance_id;
        }
        return false;
    }

    /**
     * Get instance by ID with full details
     */
    public function getInstanceById($instance_id) {
        $query = "SELECT i.*, 
                         t.title as template_title, t.total_points, t.year_level_id,
                         y.name as year_level_name,
                         c.nom as class_name, c.year_level_id as class_year_level_id,
                         e.nom as created_by_name
                  FROM " . $this->instances_table . " i
                  JOIN " . $this->templates_table . " t ON i.template_id = t.id
                  JOIN classes c ON i.class_id = c.id
                  JOIN enseignants e ON i.created_by = e.id
                  LEFT JOIN " . $this->year_levels_table . " y ON t.year_level_id = y.id
                  WHERE i.id = :id LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $instance_id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get all instances for a class
     */
    public function getInstancesByClass($class_id) {
        $query = "SELECT i.*, 
                         t.title as template_title, t.total_points,
                         (SELECT COUNT(*) FROM " . $this->phases_table . " WHERE template_id = t.id) as phase_count
                  FROM " . $this->instances_table . " i
                  JOIN " . $this->templates_table . " t ON i.template_id = t.id
                  WHERE i.class_id = :class_id
                  ORDER BY i.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':class_id', $class_id);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all instances by teacher
     */
    public function getInstancesByTeacher($teacher_id) {
        $query = "SELECT i.*, 
                         t.title as template_title, t.total_points, t.year_level_id,
                         y.name as year_level_name,
                         c.nom as class_name
                  FROM " . $this->instances_table . " i
                  JOIN " . $this->templates_table . " t ON i.template_id = t.id
                  LEFT JOIN " . $this->year_levels_table . " y ON t.year_level_id = y.id
                  JOIN classes c ON i.class_id = c.id
                  WHERE i.created_by = :teacher_id
                  ORDER BY i.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':teacher_id', $teacher_id);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lock an instance (prevent further note modifications)
     */
    public function lockInstance($instance_id) {
        $query = "UPDATE " . $this->instances_table . " SET is_locked = 1 WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $instance_id);
        
        return $stmt->execute();
    }

    /**
     * Unlock an instance
     */
    public function unlockInstance($instance_id) {
        $query = "UPDATE " . $this->instances_table . " SET is_locked = 0 WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $instance_id);
        
        return $stmt->execute();
    }

    /**
     * Delete an instance (only if not locked)
     */
    public function deleteInstance($instance_id) {
        $query = "DELETE FROM " . $this->instances_table . " WHERE id = :id AND is_locked = 0";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $instance_id);
        
        return $stmt->execute();
    }

    // ============================================================================
    // NOTES MANAGEMENT - Fill points for students
    // ============================================================================

    /**
     * Save individual note for a student in a phase
     */
    public function saveIndividualNote($instance_id, $phase_id, $student_id, $note, $remarks = null) {
        // Verify instance is not locked
        $instance = $this->getInstanceById($instance_id);
        if (!$instance || $instance['is_locked']) {
            return false;
        }

        // Verify phase exists and is individual mode
        $phase = $this->getPhaseById($phase_id);
        if (!$phase || $phase['grading_mode'] !== 'individual') {
            return false;
        }

        // Validate note range
        if ($note < 0 || $note > $phase['points']) {
            return false;
        }

        $query = "INSERT INTO " . $this->notes_individual_table . " 
                  (instance_id, phase_id, student_id, note, remarks) 
                  VALUES (:instance_id, :phase_id, :student_id, :note, :remarks)
                  ON DUPLICATE KEY UPDATE note = :note2, remarks = :remarks2";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':instance_id', $instance_id);
        $stmt->bindParam(':phase_id', $phase_id);
        $stmt->bindParam(':student_id', $student_id);
        $stmt->bindParam(':note', $note);
        $stmt->bindParam(':remarks', $remarks);
        $stmt->bindParam(':note2', $note);
        $stmt->bindParam(':remarks2', $remarks);
        
        return $stmt->execute();
    }

    /**
     * Save team note for a team in a phase (all team members get this note)
     */
    public function saveTeamNote($instance_id, $phase_id, $team_id, $note, $remarks = null) {
        // Verify instance is not locked
        $instance = $this->getInstanceById($instance_id);
        if (!$instance || $instance['is_locked']) {
            return false;
        }

        // Verify phase exists and is team mode
        $phase = $this->getPhaseById($phase_id);
        if (!$phase || $phase['grading_mode'] !== 'team') {
            return false;
        }

        // Validate note range
        if ($note < 0 || $note > $phase['points']) {
            return false;
        }

        $query = "INSERT INTO " . $this->notes_team_table . " 
                  (instance_id, phase_id, team_id, note, remarks) 
                  VALUES (:instance_id, :phase_id, :team_id, :note, :remarks)
                  ON DUPLICATE KEY UPDATE note = :note2, remarks = :remarks2";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':instance_id', $instance_id);
        $stmt->bindParam(':phase_id', $phase_id);
        $stmt->bindParam(':team_id', $team_id);
        $stmt->bindParam(':note', $note);
        $stmt->bindParam(':remarks', $remarks);
        $stmt->bindParam(':note2', $note);
        $stmt->bindParam(':remarks2', $remarks);
        
        return $stmt->execute();
    }

    /**
     * Get all individual notes for an instance
     */
    public function getInstanceIndividualNotes($instance_id, $phase_id = null) {
        $query = "SELECT ni.*, e.nom as student_name, p.title as phase_title
                  FROM " . $this->notes_individual_table . " ni
                  JOIN eleves e ON ni.student_id = e.id
                  JOIN " . $this->phases_table . " p ON ni.phase_id = p.id
                  WHERE ni.instance_id = :instance_id";
        
        if ($phase_id) {
            $query .= " AND ni.phase_id = :phase_id";
        }
        
        $query .= " ORDER BY p.order_index ASC, e.nom ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':instance_id', $instance_id);
        if ($phase_id) {
            $stmt->bindParam(':phase_id', $phase_id);
        }
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all team notes for an instance
     */
    public function getInstanceTeamNotes($instance_id, $phase_id = null) {
        $query = "SELECT nt.*, t.name as team_name, p.title as phase_title
                  FROM " . $this->notes_team_table . " nt
                  JOIN teams t ON nt.team_id = t.id
                  JOIN " . $this->phases_table . " p ON nt.phase_id = p.id
                  WHERE nt.instance_id = :instance_id";
        
        if ($phase_id) {
            $query .= " AND nt.phase_id = :phase_id";
        }
        
        $query .= " ORDER BY p.order_index ASC, t.name ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':instance_id', $instance_id);
        if ($phase_id) {
            $stmt->bindParam(':phase_id', $phase_id);
        }
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get student's note for a specific phase (handles both modes)
     */
    public function getStudentNoteForPhase($instance_id, $phase_id, $student_id) {
        $phase = $this->getPhaseById($phase_id);
        if (!$phase) {
            return null;
        }

        if ($phase['grading_mode'] === 'individual') {
            $query = "SELECT note, remarks FROM " . $this->notes_individual_table . " 
                      WHERE instance_id = :instance_id AND phase_id = :phase_id AND student_id = :student_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':instance_id', $instance_id);
            $stmt->bindParam(':phase_id', $phase_id);
            $stmt->bindParam(':student_id', $student_id);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            // Team mode - get student's team and return team note
            $query = "SELECT nt.note, nt.remarks
                      FROM " . $this->notes_team_table . " nt
                      JOIN team_members tm ON nt.team_id = tm.team_id
                      WHERE nt.instance_id = :instance_id 
                        AND nt.phase_id = :phase_id 
                        AND tm.student_id = :student_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':instance_id', $instance_id);
            $stmt->bindParam(':phase_id', $phase_id);
            $stmt->bindParam(':student_id', $student_id);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    // ============================================================================
    // RESULTS COMPUTATION
    // ============================================================================

    /**
     * Compute final result for a single student in an instance
     * Returns all_graded=true only when ALL phases have grades
     * Simple calculation: just sum the points earned in each phase
     */
    public function computeStudentResult($instance_id, $student_id) {
        $instance = $this->getInstanceById($instance_id);
        if (!$instance) {
            return null;
        }

        $phases = $this->getPhases($instance['template_id']);
        
        if (empty($phases)) {
            return null;
        }

        $final_note = 0;
        $breakdown = [];
        $all_graded = true; // Track if ALL phases have grades
        $graded_count = 0;

        foreach ($phases as $phase) {
            $note_data = $this->getStudentNoteForPhase($instance_id, $phase['id'], $student_id);
            $raw_note = $note_data ? $note_data['note'] : null;
            
            if ($raw_note === null) {
                // Note not yet entered - student is NOT fully graded
                $all_graded = false;
            } else {
                // Simple: just add the points earned
                $final_note += $raw_note;
                $graded_count++;
            }

            $breakdown[] = [
                'phase_id' => $phase['id'],
                'phase_title' => $phase['title'],
                'mode' => $phase['grading_mode'],
                'raw_note' => $raw_note,
                'points' => $phase['points']
            ];
        }

        return [
            'student_id' => $student_id,
            'final_note' => round($final_note, 2),
            'breakdown' => $breakdown,
            'all_graded' => $all_graded,
            'graded_phases' => $graded_count,
            'total_phases' => count($phases)
        ];
    }

    /**
     * Compute and save all results for an instance
     * Only saves final_note >= 0 when ALL phases have grades (for certificate eligibility)
     */
    public function computeAllResults($instance_id) {
        $instance = $this->getInstanceById($instance_id);
        if (!$instance) {
            return false;
        }

        // Get all students in the class
        $query = "SELECT id FROM eleves WHERE classe_id = :class_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':class_id', $instance['class_id']);
        $stmt->execute();
        
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $results = [];

        foreach ($students as $student) {
            $result = $this->computeStudentResult($instance_id, $student['id']);
            if ($result) {
                // Save to database - pass all_graded flag to determine if student is complete
                $this->saveResult(
                    $instance_id, 
                    $student['id'], 
                    $result['final_note'], 
                    $result['breakdown'],
                    $result['all_graded'] // CRITICAL: Only mark complete if ALL phases graded
                );
                $results[] = $result;
            }
        }

        return $results;
    }

    /**
     * Save computed result to database
     * @param bool $all_graded - if false, saves -1 to indicate incomplete
     */
    private function saveResult($instance_id, $student_id, $final_note, $breakdown, $all_graded = true) {
        $query = "INSERT INTO " . $this->results_table . " 
                  (instance_id, student_id, final_note, breakdown_json) 
                  VALUES (:instance_id, :student_id, :final_note, :breakdown)
                  ON DUPLICATE KEY UPDATE final_note = :final_note2, breakdown_json = :breakdown2";
        
        $stmt = $this->conn->prepare($query);
        
        $breakdown_json = json_encode($breakdown);
        
        // CRITICAL: Only save actual score if ALL phases are graded
        // Otherwise save -1 to indicate incomplete (not eligible for certificate)
        $note_to_save = $all_graded ? $final_note : -1;
        
        $stmt->bindParam(':instance_id', $instance_id);
        $stmt->bindParam(':student_id', $student_id);
        $stmt->bindParam(':final_note', $note_to_save);
        $stmt->bindParam(':breakdown', $breakdown_json);
        $stmt->bindParam(':final_note2', $note_to_save);
        $stmt->bindParam(':breakdown2', $breakdown_json);
        
        return $stmt->execute();
    }

    /**
     * Get all saved results for an instance
     * Ungraded students (final_note = -1) are listed at the bottom
     */
    public function getResults($instance_id) {
        $query = "SELECT cr.*, e.nom as student_name,
                         CASE WHEN cr.final_note >= 0 THEN 1 ELSE 0 END as is_graded
                  FROM " . $this->results_table . " cr
                  JOIN eleves e ON cr.student_id = e.id
                  WHERE cr.instance_id = :instance_id
                  ORDER BY is_graded DESC, cr.final_note DESC, e.nom ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':instance_id', $instance_id);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get result for a specific student
     */
    public function getStudentResult($instance_id, $student_id) {
        $query = "SELECT cr.*, e.nom as student_name 
                  FROM " . $this->results_table . " cr
                  JOIN eleves e ON cr.student_id = e.id
                  WHERE cr.instance_id = :instance_id AND cr.student_id = :student_id
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':instance_id', $instance_id);
        $stmt->bindParam(':student_id', $student_id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['breakdown_json']) {
            $row['breakdown'] = json_decode($row['breakdown_json'], true);
        }
        
        return $row;
    }

    // ============================================================================
    // STATISTICS
    // ============================================================================

    /**
     * Get statistics for an instance
     * Only includes students who have been fully graded (final_note >= 0)
     */
    public function getStatistics($instance_id) {
        $results = $this->getResults($instance_id);
        
        if (empty($results)) {
            return null;
        }

        $instance = $this->getInstanceById($instance_id);
        $total_points = $instance['total_points'];
        
        // CRITICAL: Filter out ungraded students (final_note = -1)
        $graded_results = array_filter($results, fn($r) => $r['final_note'] >= 0);
        
        if (empty($graded_results)) {
            return [
                'count' => 0,
                'total_students' => count($results),
                'graded_count' => 0,
                'ungraded_count' => count($results),
                'average' => 0,
                'min' => 0,
                'max' => 0,
                'median' => 0,
                'passed' => 0,
                'failed' => 0
            ];
        }
        
        $notes = array_column($graded_results, 'final_note');
        
        return [
            'count' => count($graded_results),
            'total_students' => count($results),
            'graded_count' => count($graded_results),
            'ungraded_count' => count($results) - count($graded_results),
            'average' => round(array_sum($notes) / count($notes), 2),
            'min' => min($notes),
            'max' => max($notes),
            'median' => $this->calculateMedian($notes),
            'passed' => count(array_filter($notes, fn($n) => $n >= ($total_points / 2))),
            'failed' => count(array_filter($notes, fn($n) => $n < ($total_points / 2)))
        ];
    }

    private function calculateMedian($arr) {
        sort($arr);
        $count = count($arr);
        $mid = floor(($count - 1) / 2);
        
        if ($count % 2) {
            return $arr[$mid];
        } else {
            return round(($arr[$mid] + $arr[$mid + 1]) / 2, 2);
        }
    }

    /**
     * Export results as array (for JSON/CSV export)
     */
    public function exportResults($instance_id) {
        $query = "SELECT e.nom as student_name, cr.final_note, cr.breakdown_json
                  FROM " . $this->results_table . " cr
                  JOIN eleves e ON cr.student_id = e.id
                  WHERE cr.instance_id = :instance_id
                  ORDER BY e.nom ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':instance_id', $instance_id);
        $stmt->execute();
        
        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result = [
                'student_name' => $row['student_name'],
                'final_note' => $row['final_note']
            ];
            
            if ($row['breakdown_json']) {
                $breakdown = json_decode($row['breakdown_json'], true);
                foreach ($breakdown as $phase) {
                    $result[$phase['phase_title']] = $phase['raw_note'];
                }
            }
            
            $results[] = $result;
        }
        
        return $results;
    }

    // ============================================================================
    // HELPER METHODS
    // ============================================================================

    /**
     * Get available templates for a class (based on year level)
     */
    public function getAvailableTemplatesForClass($class_id) {
        $query = "SELECT t.*, y.name as year_level_name 
                  FROM " . $this->templates_table . " t
                  JOIN classes c ON c.year_level_id = t.year_level_id
                  LEFT JOIN " . $this->year_levels_table . " y ON t.year_level_id = y.id
                  WHERE c.id = :class_id AND t.is_active = 1
                  ORDER BY t.title ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':class_id', $class_id);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get grading progress for an instance
     */
    public function getGradingProgress($instance_id) {
        $instance = $this->getInstanceById($instance_id);
        if (!$instance) {
            return null;
        }

        $phases = $this->getPhases($instance['template_id']);
        
        // Count students in class
        $query = "SELECT COUNT(*) as cnt FROM eleves WHERE classe_id = :class_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':class_id', $instance['class_id']);
        $stmt->execute();
        $student_count = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

        // Count teams in class
        $query = "SELECT COUNT(*) as cnt FROM teams WHERE class_id = :class_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':class_id', $instance['class_id']);
        $stmt->execute();
        $team_count = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

        $progress = [];
        $total_expected = 0;
        $total_filled = 0;

        foreach ($phases as $phase) {
            if ($phase['grading_mode'] === 'individual') {
                $expected = $student_count;
                $query = "SELECT COUNT(*) as cnt FROM " . $this->notes_individual_table . " 
                          WHERE instance_id = :instance_id AND phase_id = :phase_id";
            } else {
                $expected = $team_count;
                $query = "SELECT COUNT(*) as cnt FROM " . $this->notes_team_table . " 
                          WHERE instance_id = :instance_id AND phase_id = :phase_id";
            }

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':instance_id', $instance_id);
            $stmt->bindParam(':phase_id', $phase['id']);
            $stmt->execute();
            $filled = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

            $progress[] = [
                'phase_id' => $phase['id'],
                'phase_title' => $phase['title'],
                'mode' => $phase['grading_mode'],
                'expected' => $expected,
                'filled' => $filled,
                'percentage' => $expected > 0 ? round(($filled / $expected) * 100) : 0
            ];

            $total_expected += $expected;
            $total_filled += $filled;
        }

        return [
            'phases' => $progress,
            'total_expected' => $total_expected,
            'total_filled' => $total_filled,
            'overall_percentage' => $total_expected > 0 ? round(($total_filled / $total_expected) * 100) : 0
        ];
    }
}
?>
