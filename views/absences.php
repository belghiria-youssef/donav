<?php
if (!isset($_SESSION['teacher_id'])) {
    header('Location: index.php?page=login');
    exit;
}

require_once 'classes/ClassRoom.php';
require_once 'classes/Student.php';

$database = new Database();
$db = $database->getConnection();

$classroom = new ClassRoom($db);
$student = new Student($db);

$message = '';
$error = '';
$changes_made = 0;
$is_resubmission = false;

// Get teacher's classes
$classes_stmt = $classroom->getByTeacher($_SESSION['teacher_id']);
$classes = $classes_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get selected class (from POST or default to first class)
$selected_class_id = 0;
$students = [];
$selected_class_name = '';
$existing_statuses = [];
$today_submission = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_class'])) {
    $selected_class_id = intval($_POST['class_id'] ?? 0);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_presences'])) {
    $selected_class_id = intval($_POST['class_id'] ?? 0);
}

// If a class is selected, get its students
if ($selected_class_id > 0) {
    // Verify class ownership
    $valid_class = false;
    foreach ($classes as $c) {
        if ($c['id'] == $selected_class_id) {
            $valid_class = true;
            $selected_class_name = $c['nom'];
            break;
        }
    }
    
    if ($valid_class) {
        $students_stmt = $student->getByClass($selected_class_id);
        $students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get existing statuses for today
        $today = date('Y-m-d');
        $existing_query = "SELECT student_id, statut FROM student_absences 
                          WHERE class_id = :class_id AND absence_date = :date";
        $existing_stmt = $db->prepare($existing_query);
        $existing_stmt->execute([':class_id' => $selected_class_id, ':date' => $today]);
        while ($row = $existing_stmt->fetch(PDO::FETCH_ASSOC)) {
            $existing_statuses[$row['student_id']] = $row['statut'];
        }
        
        // Check if there's already a submission for today
        $submission_query = "SELECT * FROM absence_submissions 
                            WHERE class_id = :class_id AND submission_date = :date";
        $submission_stmt = $db->prepare($submission_query);
        $submission_stmt->execute([':class_id' => $selected_class_id, ':date' => $today]);
        $today_submission = $submission_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($today_submission) {
            $is_resubmission = true;
        }
    } else {
        $error = "Classe non autorisée.";
        $selected_class_id = 0;
    }
}

// Handle save presences
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_presences']) && $selected_class_id > 0 && !empty($students)) {
    $date = date('Y-m-d');
    $statuses = $_POST['status'] ?? [];
    
    $present_count = 0;
    $absent_count = 0;
    $late_count = 0;
    $justified_count = 0;
    $changes_made = 0;

    try {
        $db->beginTransaction();
        
        // Create or get submission record
        $submission_query = "INSERT INTO absence_submissions (class_id, submission_date, total_students, submitted_by)
                            VALUES (:class_id, :date, :total, :teacher_id)
                            ON DUPLICATE KEY UPDATE 
                            total_students = VALUES(total_students),
                            updated_at = NOW()";
        $submission_stmt = $db->prepare($submission_query);
        $submission_stmt->execute([
            ':class_id' => $selected_class_id,
            ':date' => $date,
            ':total' => count($students),
            ':teacher_id' => $_SESSION['teacher_id']
        ]);
        
        // Get the submission ID
        $get_submission = $db->prepare("SELECT id FROM absence_submissions WHERE class_id = :class_id AND submission_date = :date");
        $get_submission->execute([':class_id' => $selected_class_id, ':date' => $date]);
        $submission_id = $get_submission->fetchColumn();

        foreach ($students as $s) {
            $student_id = $s['id'];
            $new_status = $statuses[$student_id] ?? 'present';
            $old_status = $existing_statuses[$student_id] ?? null;
            
            // Count by status
            switch ($new_status) {
                case 'present': $present_count++; break;
                case 'absent': $absent_count++; break;
                case 'late': $late_count++; break;
                case 'justified': $justified_count++; break;
            }
            
            // Only update if status changed or new record
            if ($old_status !== $new_status) {
                $query = "INSERT INTO student_absences (student_id, class_id, absence_date, statut, created_by)
                          VALUES (:student_id, :class_id, :absence_date, :statut, :created_by)
                          ON DUPLICATE KEY UPDATE statut = VALUES(statut), created_by = VALUES(created_by)";

                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':student_id' => $student_id,
                    ':class_id' => $selected_class_id,
                    ':absence_date' => $date,
                    ':statut' => $new_status,
                    ':created_by' => $_SESSION['teacher_id'],
                ]);
                
                // Log the change
                $log_query = "INSERT INTO absence_change_log (submission_id, student_id, old_status, new_status, changed_by)
                             VALUES (:submission_id, :student_id, :old_status, :new_status, :changed_by)";
                $log_stmt = $db->prepare($log_query);
                $log_stmt->execute([
                    ':submission_id' => $submission_id,
                    ':student_id' => $student_id,
                    ':old_status' => $old_status,
                    ':new_status' => $new_status,
                    ':changed_by' => $_SESSION['teacher_id']
                ]);
                
                $changes_made++;
            }
        }
        
        // Update submission counts
        $update_counts = $db->prepare("UPDATE absence_submissions SET 
                                       present_count = :present, 
                                       absent_count = :absent, 
                                       late_count = :late, 
                                       justified_count = :justified 
                                       WHERE id = :id");
        $update_counts->execute([
            ':present' => $present_count,
            ':absent' => $absent_count,
            ':late' => $late_count,
            ':justified' => $justified_count,
            ':id' => $submission_id
        ]);
        
        $db->commit();
        
        // Refresh existing statuses after save
        $existing_statuses = [];
        $refresh_existing = $db->prepare("SELECT student_id, statut FROM student_absences WHERE class_id = :class_id AND absence_date = :date");
        $refresh_existing->execute([':class_id' => $selected_class_id, ':date' => $date]);
        while ($row = $refresh_existing->fetch(PDO::FETCH_ASSOC)) {
            $existing_statuses[$row['student_id']] = $row['statut'];
        }
        
        // Refresh submission info
        $refresh_submission = $db->prepare("SELECT * FROM absence_submissions WHERE class_id = :class_id AND submission_date = :date");
        $refresh_submission->execute([':class_id' => $selected_class_id, ':date' => $date]);
        $today_submission = $refresh_submission->fetch(PDO::FETCH_ASSOC);
        $is_resubmission = true;
        
        if ($changes_made > 0) {
            $message = "Présences enregistrées avec succès! ($changes_made modification(s))";
        } else {
            $message = "Aucune modification détectée.";
        }
        
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = "Erreur lors de l'enregistrement: " . $e->getMessage();
    }
}

// Get submission history for selected class
$submission_history = [];
if ($selected_class_id > 0) {
    try {
        $history_query = "SELECT s.*, 
                          (SELECT COUNT(*) FROM absence_change_log WHERE submission_id = s.id) as changes_count
                          FROM absence_submissions s 
                          WHERE s.class_id = :class_id 
                          ORDER BY s.submission_date DESC 
                          LIMIT 10";
        $history_stmt = $db->prepare($history_query);
        $history_stmt->execute([':class_id' => $selected_class_id]);
        $submission_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Tables might not exist yet, ignore
        $submission_history = [];
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#667eea">
    <title>Gestion des Absences - No9ati</title>
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/svg+xml" href="assets/icons/icon-72x72.svg">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        body {
            font-family: 'Inter', sans-serif;
            background: #f9f9fa;
        }

        .hover-elevate {
            transition: all 0.2s ease;
        }

        .hover-elevate:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.06);
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        .status-pill {
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-present { background-color: #22c55e; color: white; }
        .status-absent { background-color: #ef4444; color: white; }

        .class-card {
            border-radius: 16px;
            border: 1px solid rgba(0, 0, 0, 0.08);
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .class-card:hover {
            border-color: #1c1c1c;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .class-card.selected {
            border-color: #1c1c1c;
            background: rgba(0, 0, 0, 0.02);
        }

        .student-row {
            border-radius: 12px;
            border: 1px solid rgba(0, 0, 0, 0.08);
            transition: all 0.2s ease;
        }

        .student-row:hover {
            border-color: rgba(0, 0, 0, 0.15);
        }

        .student-row.absent {
            border-color: #ef4444;
            background: rgba(239, 68, 68, 0.05);
        }

        .btn-toggle {
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
    </style>
</head>
<body>
    <?php include 'views/partials/sidebar.php'; ?>
    
    <div class="main-content d-flex flex-column" style="margin-left: 212px; padding-top: 68px; min-height: 100vh;">
        <nav class="navbar navbar-expand-lg" style="background: #fff; position: fixed; left: 212px; right: 0; top: 0; z-index: 1020; height: 68px; border-bottom: 1px solid rgba(0,0,0,0.1);">
            <div class="container-fluid px-4">
                <button class="btn d-lg-none me-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" style="background: rgba(0,0,0,0.04); border: none; border-radius: 8px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1c1c1c" stroke-width="2">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>
                <h1 style="font-size: 16px; font-weight: 600; color: #1c1c1c; margin: 0;">Gestion des Absences</h1>
                <div class="d-flex align-items-center">
                    <div class="d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; background: #1c1c1c; color: #fff; border-radius: 50%; font-size: 14px; font-weight: 500;">
                        <?php echo strtoupper(substr($_SESSION['teacher_name'] ?? 'U', 0, 1)); ?>
                    </div>
                </div>
            </div>
        </nav>

        <main class="container-fluid" style="padding: 28px;">
            <!-- Page Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <h1 style="font-size: 24px; font-weight: 600; color: #1c1c1c; margin-bottom: 8px;">Gestion des Absences</h1>
                    <p style="font-size: 14px; color: rgba(0,0,0,0.5); margin: 0;">Sélectionnez une classe puis validez les présences du jour</p>
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-dismissible fade show mb-4" role="alert" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border: none; border-radius: 12px; padding: 16px;">
                    <div class="d-flex align-items-center">
                        <svg class="me-2" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Class Selection Panel -->
                <div class="col-12 col-lg-4 mb-4">
                    <div class="card h-100" style="border-radius: 20px; border: 1px solid rgba(0, 0, 0, 0.06); box-shadow: 0 8px 20px rgba(0, 0, 0, 0.04); background: #fff;">
                        <div class="card-body p-4">
                            <h2 style="font-size: 16px; font-weight: 600; color: #1c1c1c; margin-bottom: 16px;">
                                <svg class="me-2" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                                Sélectionner une classe
                            </h2>

                            <?php if (empty($classes)): ?>
                                <div class="text-center py-4">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="rgba(0,0,0,0.2)" stroke-width="2" style="margin-bottom: 16px;">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="9" cy="7" r="4"></circle>
                                    </svg>
                                    <p style="color: rgba(0,0,0,0.4); margin-bottom: 16px;">Aucune classe disponible</p>
                                    <a href="?page=manage_classes" class="btn" style="background: #1c1c1c; color: #fff; border-radius: 8px; padding: 8px 16px; font-size: 14px;">
                                        Créer une classe
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="d-flex flex-column gap-2">
                                    <?php foreach ($classes as $c): ?>
                                        <form method="POST" action="?page=absences" class="m-0">
                                            <input type="hidden" name="select_class" value="1">
                                            <input type="hidden" name="class_id" value="<?php echo $c['id']; ?>">
                                            <button type="submit" class="class-card w-100 text-start p-3 bg-white <?php echo $selected_class_id == $c['id'] ? 'selected' : ''; ?>" style="border: 1px solid <?php echo $selected_class_id == $c['id'] ? '#1c1c1c' : 'rgba(0,0,0,0.08)'; ?>;">
                                                <div class="d-flex align-items-center justify-content-between">
                                                    <div class="d-flex align-items-center gap-3">
                                                        <div class="d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; background: <?php echo $selected_class_id == $c['id'] ? '#1c1c1c' : 'rgba(0,0,0,0.04)'; ?>; color: <?php echo $selected_class_id == $c['id'] ? '#fff' : '#1c1c1c'; ?>; border-radius: 10px; font-size: 14px; font-weight: 600;">
                                                            <?php echo strtoupper(substr($c['nom'], 0, 2)); ?>
                                                        </div>
                                                        <div>
                                                            <p style="font-size: 14px; font-weight: 500; color: #1c1c1c; margin: 0;"><?php echo htmlspecialchars($c['nom']); ?></p>
                                                            <p style="font-size: 12px; color: rgba(0,0,0,0.4); margin: 0;"><?php echo $classroom->getStudentCount($c['id']); ?> élèves</p>
                                                        </div>
                                                    </div>
                                                    <?php if ($selected_class_id == $c['id']): ?>
                                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2">
                                                            <polyline points="20 6 9 17 4 12"></polyline>
                                                        </svg>
                                                    <?php endif; ?>
                                                </div>
                                            </button>
                                        </form>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Attendance Panel -->
                <div class="col-12 col-lg-8">
                    <div class="card" style="border-radius: 20px; border: 1px solid rgba(0, 0, 0, 0.06); box-shadow: 0 8px 20px rgba(0, 0, 0, 0.04); background: #fff;">
                        <?php if ($selected_class_id == 0): ?>
                            <!-- No class selected -->
                            <div class="card-body p-5 text-center">
                                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="rgba(0,0,0,0.15)" stroke-width="1.5" style="margin-bottom: 20px;">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                    <line x1="16" y1="13" x2="8" y2="13"></line>
                                    <line x1="16" y1="17" x2="8" y2="17"></line>
                                    <polyline points="10 9 9 9 8 9"></polyline>
                                </svg>
                                <h3 style="font-size: 18px; font-weight: 600; color: #1c1c1c; margin-bottom: 8px;">Sélectionnez une classe</h3>
                                <p style="font-size: 14px; color: rgba(0,0,0,0.4); margin: 0;">Choisissez une classe dans le panneau de gauche pour commencer la validation des présences.</p>
                            </div>
                        <?php else: ?>
                            <!-- Class selected - Show attendance form -->
                            <div class="card-body p-0">
                                <!-- Header -->
                                <div class="p-4" style="border-bottom: 1px solid rgba(0,0,0,0.08);">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <div>
                                            <h2 style="font-size: 18px; font-weight: 600; color: #1c1c1c; margin-bottom: 4px;"><?php echo htmlspecialchars($selected_class_name); ?></h2>
                                            <p style="font-size: 13px; color: rgba(0,0,0,0.5); margin: 0;">
                                                <svg class="me-1" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                                </svg>
                                                <?php 
                                                    $days_fr = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
                                                    $months_fr = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
                                                    echo $days_fr[date('w')] . ' ' . date('d') . ' ' . $months_fr[date('n')] . ' ' . date('Y');
                                                ?>
                                            </p>
                                        </div>
                                        <span class="badge" style="background: rgba(0,0,0,0.06); color: #1c1c1c; font-size: 13px; font-weight: 500; padding: 8px 12px; border-radius: 8px;">
                                            <?php echo count($students); ?> élèves
                                        </span>
                                    </div>

                                    <div class="d-flex flex-wrap gap-2 align-items-center">
                                        <button type="button" class="btn" id="markAllPresentBtn" style="background: #22c55e; color: #fff; border-radius: 10px; padding: 8px 16px; font-size: 14px;">
                                            <svg class="me-2" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="20 6 9 17 4 12"></polyline>
                                            </svg>
                                            Tous présents
                                        </button>
                                        <div class="position-relative flex-grow-1" style="max-width: 280px;">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="rgba(0,0,0,0.4)" stroke-width="2" class="position-absolute" style="left: 12px; top: 50%; transform: translateY(-50%);">
                                                <circle cx="11" cy="11" r="8"></circle>
                                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                            </svg>
                                            <input type="text" id="studentSearch" class="form-control" placeholder="Rechercher..." style="padding-left: 40px; border-radius: 10px; border: 1px solid rgba(0,0,0,0.1); font-size: 14px;">
                                        </div>
                                    </div>
                                </div>

                                <!-- Students List -->
                                <form method="POST" action="?page=absences" id="attendanceForm">
                                    <input type="hidden" name="save_presences" value="1">
                                    <input type="hidden" name="class_id" value="<?php echo $selected_class_id; ?>">

                                    <div class="p-4 custom-scrollbar" style="max-height: 450px; overflow-y: auto;">
                                        <?php if (empty($students)): ?>
                                            <div class="text-center py-4">
                                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="rgba(0,0,0,0.2)" stroke-width="2" style="margin-bottom: 16px;">
                                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                                    <circle cx="9" cy="7" r="4"></circle>
                                                </svg>
                                                <p style="color: rgba(0,0,0,0.4); margin-bottom: 0;">Aucun élève dans cette classe</p>
                                            </div>
                                        <?php else: ?>
                                            <div class="d-flex flex-column gap-3" id="studentsList">
                                                <?php foreach ($students as $s): ?>
                                                    <div class="student-row d-flex align-items-center justify-content-between p-3 hover-elevate" data-name="<?php echo strtolower($s['nom']); ?>" data-student-id="<?php echo $s['id']; ?>">
                                                        <div class="d-flex align-items-center gap-3">
                                                            <div class="student-avatar d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; background: <?php echo ($existing_statuses[$s['id']] ?? 'present') === 'absent' ? 'rgba(239, 68, 68, 0.1)' : 'rgba(34, 197, 94, 0.1)'; ?>; color: <?php echo ($existing_statuses[$s['id']] ?? 'present') === 'absent' ? '#ef4444' : '#22c55e'; ?>; border-radius: 10px; font-size: 14px; font-weight: 600;">
                                                                <?php echo strtoupper(substr($s['nom'], 0, 2)); ?>
                                                            </div>
                                                            <div>
                                                                <p style="font-size: 14px; font-weight: 500; color: #1c1c1c; margin: 0;"><?php echo htmlspecialchars($s['nom']); ?></p>
                                                                <?php if (!empty($s['totalHeures'])): ?>
                                                                    <p style="font-size: 12px; color: rgba(0,0,0,0.4); margin: 0;"><?php echo intval($s['totalHeures']); ?>h d'absence</p>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>

                                                        <?php 
                                                        $current_status = $existing_statuses[$s['id']] ?? 'present';
                                                        $is_absent = $current_status === 'absent';
                                                        ?>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <span class="status-pill <?php echo $is_absent ? 'status-absent' : 'status-present'; ?>" id="badge-<?php echo $s['id']; ?>"><?php echo $is_absent ? 'Absent' : 'Présent'; ?></span>
                                                            <button type="button" class="btn btn-toggle toggle-btn" data-student-id="<?php echo $s['id']; ?>" style="background: <?php echo $is_absent ? '#ef4444' : 'rgba(0,0,0,0.04)'; ?>; color: <?php echo $is_absent ? '#fff' : '#1c1c1c'; ?>; border: none;">
                                                                <?php if ($is_absent): ?>
                                                                    <svg class="me-1" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                        <polyline points="20 6 9 17 4 12"></polyline>
                                                                    </svg>
                                                                    Présent
                                                                <?php else: ?>
                                                                    <svg class="me-1" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                                                        <circle cx="12" cy="7" r="4"></circle>
                                                                        <line x1="18" y1="8" x2="23" y2="13"></line>
                                                                        <line x1="23" y1="8" x2="18" y2="13"></line>
                                                                    </svg>
                                                                    Absent
                                                                <?php endif; ?>
                                                            </button>
                                                            <input type="hidden" name="status[<?php echo $s['id']; ?>]" id="status-<?php echo $s['id']; ?>" value="<?php echo $current_status; ?>">
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Footer -->
                                    <?php if (!empty($students)): ?>
                                        <div class="p-4 d-flex align-items-center justify-content-between" style="background: rgba(0,0,0,0.02); border-top: 1px solid rgba(0,0,0,0.08);">
                                            <div class="d-flex align-items-center gap-3">
                                                <span id="absentCount" style="font-size: 14px; color: rgba(0,0,0,0.6);">
                                                    <?php echo count(array_filter($existing_statuses, fn($s) => $s === 'absent')); ?> absent(s)
                                                </span>
                                                <?php if ($is_resubmission): ?>
                                                    <span class="badge" style="background: rgba(251, 191, 36, 0.15); color: #d97706; font-size: 12px; font-weight: 500; padding: 4px 10px; border-radius: 6px;">
                                                        <svg class="me-1" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <path d="M1 4v6h6"></path>
                                                            <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
                                                        </svg>
                                                        Modification
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <button type="submit" class="btn" style="background: #1c1c1c; color: #fff; border-radius: 10px; padding: 10px 20px; font-size: 14px;">
                                                <svg class="me-2" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                                    <polyline points="7 3 7 8 15 8"></polyline>
                                                </svg>
                                                <?php echo $is_resubmission ? 'Mettre à jour' : 'Enregistrer'; ?>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Submission History Table -->
            <?php if ($selected_class_id > 0 && !empty($submission_history)): ?>
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card" style="border-radius: 20px; border: 1px solid rgba(0, 0, 0, 0.06); box-shadow: 0 8px 20px rgba(0, 0, 0, 0.04); background: #fff;">
                        <div class="card-body p-4">
                            <h3 style="font-size: 16px; font-weight: 600; color: #1c1c1c; margin-bottom: 16px;">
                                <svg class="me-2" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="12 6 12 12 16 14"></polyline>
                                </svg>
                                Historique des soumissions
                            </h3>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" style="font-size: 14px;">
                                    <thead>
                                        <tr style="border-bottom: 2px solid rgba(0,0,0,0.08);">
                                            <th style="font-weight: 600; color: #1c1c1c; padding: 12px 8px;">Date</th>
                                            <th style="font-weight: 600; color: #1c1c1c; padding: 12px 8px;">Total</th>
                                            <th style="font-weight: 600; color: #1c1c1c; padding: 12px 8px;">Présents</th>
                                            <th style="font-weight: 600; color: #1c1c1c; padding: 12px 8px;">Absents</th>
                                            <th style="font-weight: 600; color: #1c1c1c; padding: 12px 8px;">Retards</th>
                                            <th style="font-weight: 600; color: #1c1c1c; padding: 12px 8px;">Modifications</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($submission_history as $sub): ?>
                                        <tr style="border-bottom: 1px solid rgba(0,0,0,0.05);">
                                            <td style="padding: 12px 8px; color: #1c1c1c;">
                                                <?php 
                                                $sub_date = new DateTime($sub['submission_date']);
                                                echo $sub_date->format('d/m/Y');
                                                ?>
                                                <?php if ($sub['submission_date'] === date('Y-m-d')): ?>
                                                    <span class="badge ms-1" style="background: rgba(34, 197, 94, 0.15); color: #22c55e; font-size: 10px;">Aujourd'hui</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 12px 8px; color: rgba(0,0,0,0.6);"><?php echo $sub['total_students']; ?></td>
                                            <td style="padding: 12px 8px;">
                                                <span style="color: #22c55e; font-weight: 500;"><?php echo $sub['present_count'] ?? 0; ?></span>
                                            </td>
                                            <td style="padding: 12px 8px;">
                                                <span style="color: #ef4444; font-weight: 500;"><?php echo $sub['absent_count'] ?? 0; ?></span>
                                            </td>
                                            <td style="padding: 12px 8px;">
                                                <span style="color: #f59e0b; font-weight: 500;"><?php echo $sub['late_count'] ?? 0; ?></span>
                                            </td>
                                            <td style="padding: 12px 8px;">
                                                <?php if ($sub['changes_count'] > 0): ?>
                                                    <span class="badge" style="background: rgba(99, 102, 241, 0.15); color: #6366f1; font-size: 12px; font-weight: 500;">
                                                        <?php echo $sub['changes_count']; ?> modif(s)
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: rgba(0,0,0,0.3);">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Toast Container -->
    <div class="position-fixed top-0 end-0 p-3" style="z-index: 9999;">
        <div id="successToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="4000">
            <div class="toast-body d-flex align-items-center gap-2" style="background: #22c55e; color: white; border-radius: 12px; padding: 16px 20px; box-shadow: 0 10px 40px rgba(34, 197, 94, 0.3);">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <span id="toastMessage" style="font-weight: 500;"><?php echo htmlspecialchars($message); ?></span>
                <button type="button" class="btn-close btn-close-white ms-2" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!empty($message)): ?>
            // Show success toast
            var toastEl = document.getElementById('successToast');
            var toast = new bootstrap.Toast(toastEl);
            toast.show();
            <?php endif; ?>

            const markAllPresentBtn = document.getElementById('markAllPresentBtn');
            const studentSearch = document.getElementById('studentSearch');
            const studentRows = document.querySelectorAll('.student-row');
            const absentCountElement = document.getElementById('absentCount');

            // Initialize absent count on page load
            updateAbsentCount();

            // Apply existing statuses to row classes
            studentRows.forEach(row => {
                const studentId = row.dataset.studentId;
                const statusInput = document.getElementById('status-' + studentId);
                if (statusInput && statusInput.value === 'absent') {
                    row.classList.add('absent');
                }
            });

            if (markAllPresentBtn) {
                // Mark all students as present
                markAllPresentBtn.addEventListener('click', function() {
                    studentRows.forEach(row => {
                        const studentId = row.dataset.studentId;
                        setStudentPresent(studentId, row);
                    });
                    updateAbsentCount();
                });
            }

            // Toggle individual student status
            document.querySelectorAll('.toggle-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const studentId = this.dataset.studentId;
                    const statusInput = document.getElementById('status-' + studentId);
                    const row = this.closest('.student-row');

                    if (statusInput.value === 'present') {
                        setStudentAbsent(studentId, row);
                    } else {
                        setStudentPresent(studentId, row);
                    }
                    updateAbsentCount();
                });
            });

            // Search functionality
            if (studentSearch) {
                studentSearch.addEventListener('input', function(e) {
                    const query = e.target.value.toLowerCase();
                    studentRows.forEach(row => {
                        const studentName = row.dataset.name;
                        row.style.display = studentName.includes(query) || query === '' ? 'flex' : 'none';
                    });
                });
            }

            function setStudentPresent(studentId, row) {
                const badge = document.getElementById('badge-' + studentId);
                const statusInput = document.getElementById('status-' + studentId);
                const toggleBtn = row.querySelector('.toggle-btn');
                const avatar = row.querySelector('.student-avatar');

                badge.textContent = 'Présent';
                badge.className = 'status-pill status-present';
                statusInput.value = 'present';
                toggleBtn.innerHTML = '<svg class="me-1" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle><line x1="18" y1="8" x2="23" y2="13"></line><line x1="23" y1="8" x2="18" y2="13"></line></svg> Absent';
                toggleBtn.style.background = 'rgba(0,0,0,0.04)';
                toggleBtn.style.color = '#1c1c1c';
                row.classList.remove('absent');
                avatar.style.background = 'rgba(34, 197, 94, 0.1)';
                avatar.style.color = '#22c55e';
            }

            function setStudentAbsent(studentId, row) {
                const badge = document.getElementById('badge-' + studentId);
                const statusInput = document.getElementById('status-' + studentId);
                const toggleBtn = row.querySelector('.toggle-btn');
                const avatar = row.querySelector('.student-avatar');

                badge.textContent = 'Absent';
                badge.className = 'status-pill status-absent';
                statusInput.value = 'absent';
                toggleBtn.innerHTML = '<svg class="me-1" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> Présent';
                toggleBtn.style.background = '#ef4444';
                toggleBtn.style.color = '#fff';
                row.classList.add('absent');
                avatar.style.background = 'rgba(239, 68, 68, 0.1)';
                avatar.style.color = '#ef4444';
            }

            function updateAbsentCount() {
                if (absentCountElement) {
                    const absentCount = document.querySelectorAll('input[name^="status"][value="absent"]').length;
                    absentCountElement.textContent = absentCount + ' absent(s)';
                }
            }
        });
    </script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
