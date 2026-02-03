<?php
/**
 * Certificates Page
 * Generate and download certificates for students who completed all controles
 */

if (!isset($_SESSION['teacher_id'])) {
    header('Location: index.php?page=login');
    exit;
}

require_once 'classes/ClassRoom.php';
require_once 'classes/Controle.php';
require_once 'classes/Student.php';
require_once 'classes/Teacher.php';
require_once 'classes/CertificateGenerator.php';

$database = new Database();
$db = $database->getConnection();

$classroom = new ClassRoom($db);
$controle = new Controle($db);
$student = new Student($db);
$teacher = new Teacher($db);

// Get teacher info
if ($teacher->getById($_SESSION['teacher_id'])) {
    $teacher_name = $teacher->nom;
} else {
    $teacher_name = "Teacher";
}

// Handle certificate generation
if (isset($_GET['generate']) && isset($_GET['student_id']) && isset($_GET['class_id'])) {
    $student_id = intval($_GET['student_id']);
    $class_id = intval($_GET['class_id']);
    
    // Get student info
    $student_query = "SELECT * FROM eleves WHERE id = :student_id";
    $student_stmt = $db->prepare($student_query);
    $student_stmt->bindParam(':student_id', $student_id);
    $student_stmt->execute();
    $student_data = $student_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get class info
    if ($classroom->getById($class_id) && $classroom->enseignant_id == $_SESSION['teacher_id']) {
        $class_name = $classroom->nom;
        
        if ($student_data) {
            // VALIDATE: Check if student completed ALL controles
            $instances_query = "SELECT ci.id, ct.total_points
                                FROM controle_instances ci
                                JOIN controle_templates ct ON ci.template_id = ct.id
                                WHERE ci.class_id = :class_id
                                AND ci.created_by = :teacher_id";
            
            $instances_stmt = $db->prepare($instances_query);
            $instances_stmt->bindParam(':class_id', $class_id);
            $instances_stmt->bindParam(':teacher_id', $_SESSION['teacher_id']);
            $instances_stmt->execute();
            $all_instances = $instances_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $total_instances = count($all_instances);
            $completed_count = 0;
            
            // Check each instance for this student
            foreach ($all_instances as $inst) {
                $check_query = "SELECT final_note FROM controle_results 
                               WHERE instance_id = :instance_id AND student_id = :student_id
                               AND final_note >= 0";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->bindParam(':instance_id', $inst['id']);
                $check_stmt->bindParam(':student_id', $student_id);
                $check_stmt->execute();
                $has_result = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($has_result) {
                    $completed_count++;
                }
            }
            
            // Only generate certificate if ALL controles are completed
            if ($completed_count == $total_instances && $total_instances > 0) {
                $cert = new CertificateGenerator(
                    $student_data['nom'],
                    $class_name,
                    $teacher_name,
                    "Completion of All Controles"
                );
                
                $filename = "Certificate_" . str_replace(' ', '_', $student_data['nom']) . "_" . date('Y-m-d') . ".pdf";
                $cert->generateCertificate($filename);
                exit;
            } else {
                // Student hasn't completed all controles - redirect with error
                $_SESSION['cert_error'] = "Cet élève n'a pas complété tous les contrôles ($completed_count/$total_instances).";
                header("Location: index.php?page=certificates&class_id=$class_id");
                exit;
            }
        }
    }
}

// Get teacher's classes
$classes_stmt = $classroom->getByTeacher($_SESSION['teacher_id']);
$classes = $classes_stmt->fetchAll(PDO::FETCH_ASSOC);

// Selected class filter
$selected_class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

$eligible_students = [];
$inprogress_students = [];

if ($selected_class_id > 0) {
    // Verify class belongs to teacher
    if ($classroom->getById($selected_class_id) && $classroom->enseignant_id == $_SESSION['teacher_id']) {
        // Get all instances for this class
        $instances_query = "SELECT ci.id, ci.template_id, ci.class_id, ct.total_points,
                                   ct.title as template_title
                            FROM controle_instances ci
                            JOIN controle_templates ct ON ci.template_id = ct.id
                            WHERE ci.class_id = :class_id
                            AND ci.created_by = :teacher_id
                            ORDER BY ci.created_at DESC";
        
        $instances_stmt = $db->prepare($instances_query);
        $instances_stmt->bindParam(':class_id', $selected_class_id);
        $instances_stmt->bindParam(':teacher_id', $_SESSION['teacher_id']);
        $instances_stmt->execute();
        $instances = $instances_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($instances)) {
            // Get all students in the class
            $students_stmt = $student->getByClass($selected_class_id);
            $students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $total_instances = count($instances);
            
            // Only process if there are controles
            if ($total_instances > 0) {
                foreach ($students as $s) {
                    // Check how many controles this student has completed
                    $completed_count = 0;
                    $total_score = 0;
                    $max_possible = 0;
                    
                    foreach ($instances as $instance) {
                        // Get student's result for this instance
                        $result_query = "SELECT final_note FROM controle_results 
                                        WHERE instance_id = :instance_id 
                                        AND student_id = :student_id
                                        AND final_note >= 0";
                        $result_stmt = $db->prepare($result_query);
                        $result_stmt->bindParam(':instance_id', $instance['id']);
                        $result_stmt->bindParam(':student_id', $s['id']);
                        $result_stmt->execute();
                        $result = $result_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($result) {
                            $completed_count++;
                            $total_score += $result['final_note'];
                            $max_possible += $instance['total_points'];
                        }
                    }
                    
                    $average = $max_possible > 0 ? round(($total_score / $max_possible) * 20, 2) : 0;
                    $student_data = [
                        'id' => $s['id'],
                        'nom' => $s['nom'],
                        'completed' => $completed_count,
                        'total' => $total_instances,
                        'average' => $average,
                        'total_score' => $total_score,
                        'max_possible' => $max_possible,
                        'progress_percent' => round(($completed_count / $total_instances) * 100)
                    ];
                    
                    // Student is eligible ONLY if they completed ALL controles
                    if ($completed_count == $total_instances) {
                        $eligible_students[] = $student_data;
                    } else {
                        // Track in-progress students for visibility
                        $inprogress_students[] = $student_data;
                    }
                }
            }
        }
    }
}

// Sort in-progress by progress descending
if (!empty($inprogress_students)) {
    usort($inprogress_students, fn($a, $b) => $b['progress_percent'] - $a['progress_percent']);
}

include 'views/partials/header.php';
include 'views/partials/sidebar.php';
?>

<main class="main-content" style="margin-left: 212px; padding-top: 68px; min-height: 100vh; background: #f7f6fb;">
    <div class="container-fluid p-xl-4 p-3">
        <div class="rounded-4 p-4 shadow-sm mb-4" style="background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 60%, #a855f7 100%); color: #fff;">
            <div class="d-flex flex-column flex-md-row justify-content-between gap-3 align-items-start">
                <div>
                    <p class="mb-2 text-uppercase" style="letter-spacing: 1px; font-size: 13px;">Certificats de réussite</p>
                    <h3 class="mb-1" style="font-weight: 600; font-size: 28px;">
                        Certificats d'Achèvement
                    </h3>
                    <p class="mb-0" style="font-size: 14px; opacity: 0.9;">
                        Téléchargez des certificats PDF pour les élèves ayant complété tous les contrôles
                    </p>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <i class="bi bi-award" style="font-size: 48px; opacity: 0.3;"></i>
                </div>
            </div>
        </div>

        <!-- Error Message -->
        <?php if (isset($_SESSION['cert_error'])): ?>
            <div class="alert alert-danger border-0 rounded-4 shadow-sm" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?php 
                echo htmlspecialchars($_SESSION['cert_error']); 
                unset($_SESSION['cert_error']);
                ?>
            </div>
        <?php endif; ?>

        <!-- Class Filter -->
        <div class="card border-0 rounded-4 shadow-sm mb-4">
            <div class="card-body p-4">
                <form method="GET" class="row g-3 align-items-end">
                    <input type="hidden" name="page" value="certificates">
                    <div class="col-md-6">
                        <label class="form-label" style="font-weight: 600; font-size: 14px;">Sélectionner une classe</label>
                        <select name="class_id" class="form-select" onchange="this.form.submit()">
                            <option value="">-- Choisir une classe --</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo $selected_class_id == $c['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <div class="alert alert-info mb-0 rounded-4" style="font-size: 13px;">
                            <i class="bi bi-info-circle me-2"></i>
                            Seuls les élèves ayant complété <strong>tous les contrôles</strong> sont éligibles
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($selected_class_id > 0): ?>
            <?php if (empty($eligible_students) && empty($inprogress_students)): ?>
                <div class="card border-0 rounded-4 shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-inbox" style="font-size: 48px; color: rgba(15,23,42,0.25);"></i>
                        <h5 class="mt-3 mb-2" style="font-weight: 600;">Aucun élève à afficher</h5>
                        <p class="text-muted mb-0">
                            Aucun contrôle n'a été créé pour cette classe ou aucun élève n'est inscrit.
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <!-- Statistics -->
                <?php 
                $total_students = count($eligible_students) + count($inprogress_students);
                $eligible_percent = $total_students > 0 ? round((count($eligible_students) / $total_students) * 100) : 0;
                ?>
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card border-0 rounded-4 shadow-sm p-3">
                            <small class="text-muted text-uppercase" style="letter-spacing: 1px; font-size: 11px;">Total élèves</small>
                            <h4 class="mb-0" style="font-weight: 600;"><?php echo $total_students; ?></h4>
                            <small class="text-muted">dans cette classe</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 rounded-4 shadow-sm p-3 border-success" style="border-left: 4px solid #198754 !important;">
                            <small class="text-success text-uppercase" style="letter-spacing: 1px; font-size: 11px;">Éligibles</small>
                            <h4 class="mb-0 text-success" style="font-weight: 600;"><?php echo count($eligible_students); ?></h4>
                            <small class="text-muted"><?php echo $eligible_percent; ?>% prêts</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 rounded-4 shadow-sm p-3 border-warning" style="border-left: 4px solid #ffc107 !important;">
                            <small class="text-warning text-uppercase" style="letter-spacing: 1px; font-size: 11px;">En cours</small>
                            <h4 class="mb-0 text-warning" style="font-weight: 600;"><?php echo count($inprogress_students); ?></h4>
                            <small class="text-muted">notes incomplètes</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 rounded-4 shadow-sm p-3">
                            <small class="text-muted text-uppercase" style="letter-spacing: 1px; font-size: 11px;">Moyenne (éligibles)</small>
                            <h4 class="mb-0" style="font-weight: 600;">
                                <?php 
                                $avg_sum = array_sum(array_column($eligible_students, 'average'));
                                echo count($eligible_students) > 0 ? round($avg_sum / count($eligible_students), 2) : '-';
                                ?>
                            </h4>
                            <small class="text-muted">sur 20</small>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($eligible_students)): ?>
                <!-- Eligible Students List -->
                <div class="card border-0 rounded-4 shadow-sm overflow-hidden">
                    <div class="card-header bg-white border-0 p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0" style="font-weight: 600; letter-spacing: 1px;">
                                <i class="bi bi-check-circle text-success me-2"></i>
                                Élèves Éligibles (<?php echo count($eligible_students); ?>)
                            </h5>
                            <span class="badge bg-success rounded-pill">Prêts pour certificat</span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-borderless mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 60px;">#</th>
                                        <th>Nom de l'élève</th>
                                        <th class="text-center">Contrôles complétés</th>
                                        <th class="text-center">Score total</th>
                                        <th class="text-center">Moyenne</th>
                                        <th class="text-center" style="width: 180px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($eligible_students as $index => $es): ?>
                                        <tr>
                                            <td class="text-muted"><?php echo $index + 1; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="d-flex align-items-center justify-content-center rounded-circle bg-success text-white" 
                                                         style="width: 32px; height: 32px; font-size: 12px;">
                                                        <i class="bi bi-check-lg"></i>
                                                    </div>
                                                    <strong><?php echo htmlspecialchars($es['nom']); ?></strong>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-success rounded-pill">
                                                    <?php echo $es['completed']; ?> / <?php echo $es['total']; ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <strong><?php echo $es['total_score']; ?></strong>
                                                <small class="text-muted">/ <?php echo $es['max_possible']; ?></small>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge <?php echo $es['average'] >= 10 ? 'bg-success' : 'bg-warning'; ?> rounded-pill">
                                                    <?php echo $es['average']; ?> / 20
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <a href="index.php?page=certificates&generate=1&student_id=<?php echo $es['id']; ?>&class_id=<?php echo $selected_class_id; ?>" 
                                                   class="btn btn-dark btn-sm rounded-pill">
                                                    <i class="bi bi-download me-1"></i> Télécharger PDF
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white border-top p-3">
                        <small class="text-muted">
                            <i class="bi bi-shield-check me-2"></i>
                            Les certificats sont générés au format PDF et incluent le nom de l'élève, la classe et votre signature.
                        </small>
                    </div>
                </div>
                <?php endif; ?>
            
            <?php if (!empty($inprogress_students)): ?>
                <!-- In-Progress Students -->
                <div class="card border-0 rounded-4 shadow-sm overflow-hidden mt-4">
                    <div class="card-header bg-white border-0 p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0" style="font-weight: 600; letter-spacing: 1px;">
                                <i class="bi bi-hourglass-split text-warning me-2"></i>
                                Élèves en Cours (<?php echo count($inprogress_students); ?>)
                            </h5>
                            <span class="badge bg-warning text-dark rounded-pill">Non éligibles</span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-borderless mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 60px;">#</th>
                                        <th>Nom de l'élève</th>
                                        <th class="text-center">Progression</th>
                                        <th class="text-center">Contrôles complétés</th>
                                        <th class="text-center">Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($inprogress_students as $index => $ps): ?>
                                        <tr style="background-color: rgba(255, 193, 7, 0.05);">
                                            <td class="text-muted"><?php echo $index + 1; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="d-flex align-items-center justify-content-center rounded-circle bg-warning text-dark" 
                                                         style="width: 32px; height: 32px; font-size: 12px;">
                                                        <i class="bi bi-hourglass-split"></i>
                                                    </div>
                                                    <strong><?php echo htmlspecialchars($ps['nom']); ?></strong>
                                                </div>
                                            </td>
                                            <td class="text-center" style="width: 200px;">
                                                <div class="progress rounded-pill" style="height: 20px;">
                                                    <div class="progress-bar bg-warning" 
                                                         style="width: <?php echo $ps['progress_percent']; ?>%">
                                                        <?php echo $ps['progress_percent']; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-secondary rounded-pill">
                                                    <?php echo $ps['completed']; ?> / <?php echo $ps['total']; ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-warning text-dark rounded-pill">
                                                    <?php echo $ps['total'] - $ps['completed']; ?> contrôle(s) manquant(s)
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white border-top p-3">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-2"></i>
                            Ces élèves n'ont pas encore complété tous leurs contrôles. Ils deviendront éligibles une fois toutes les phases notées.
                        </small>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php endif; ?>
            
        <?php else: ?>
            <div class="card border-0 rounded-4 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="bi bi-funnel" style="font-size: 48px; color: rgba(15,23,42,0.25);"></i>
                    <h5 class="mt-3 mb-2" style="font-weight: 600;">Sélectionnez une classe</h5>
                    <p class="text-muted mb-0">
                        Veuillez sélectionner une classe dans le menu ci-dessus pour voir les élèves éligibles
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include 'views/partials/footer.php'; ?>
