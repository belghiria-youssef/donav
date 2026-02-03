<?php
if (!isset($_SESSION['teacher_id'])) {
    header('Location: index.php?page=login');
    exit;
}

require_once 'classes/ClassRoom.php';
require_once 'classes/Student.php';
require_once 'classes/Team.php';

$database = new Database();
$db = $database->getConnection();

$classroom = new ClassRoom($db);
$student = new Student($db);
$team = new Team($db);

$message = '';
$error = '';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];
    
    $class_id = intval($_POST['class_id'] ?? 0);
    
    // Verify class belongs to teacher
    if (!$class_id || !$classroom->getById($class_id) || $classroom->enseignant_id != $_SESSION['teacher_id']) {
        $response['message'] = 'Classe non autorisée';
        echo json_encode($response);
        exit;
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_member') {
        $team_id = intval($_POST['team_id'] ?? 0);
        $student_id = intval($_POST['student_id'] ?? 0);
        
        if ($team_id && $student_id && $team->getById($team_id) && $team->class_id == $class_id) {
            $result = $team->addMember($student_id);
            if ($result === true) {
                $response['success'] = true;
                $response['message'] = 'Élève assigné avec succès';
            } elseif ($result === 'already_in_team') {
                $response['message'] = 'Élève déjà dans une autre équipe';
            } else {
                $response['message'] = 'Erreur lors de l\'assignation';
            }
        } else {
            $response['message'] = 'Données invalides';
        }
    } elseif ($action === 'remove_member') {
        $team_id = intval($_POST['team_id'] ?? 0);
        $student_id = intval($_POST['student_id'] ?? 0);
        
        if ($team_id && $student_id && $team->getById($team_id) && $team->class_id == $class_id) {
            if ($team->removeMember($student_id)) {
                $response['success'] = true;
                $response['message'] = 'Élève retiré de l\'équipe';
            } else {
                $response['message'] = 'Erreur lors du retrait';
            }
        } else {
            $response['message'] = 'Données invalides';
        }
    } elseif ($action === 'move_member') {
        $from_team_id = intval($_POST['from_team_id'] ?? 0);
        $to_team_id = intval($_POST['to_team_id'] ?? 0);
        $student_id = intval($_POST['student_id'] ?? 0);
        
        if ($from_team_id && $to_team_id && $student_id && $from_team_id != $to_team_id) {
            if ($team->getById($from_team_id) && $team->class_id == $class_id) {
                if ($team->moveMember($student_id, $to_team_id)) {
                    $response['success'] = true;
                    $response['message'] = 'Élève déplacé avec succès';
                } else {
                    $response['message'] = 'Erreur lors du déplacement';
                }
            } else {
                $response['message'] = 'Équipe non trouvée';
            }
        } else {
            $response['message'] = 'Données invalides';
        }
    }
    
    echo json_encode($response);
    exit;
}

// Get teacher's classes
$classes_stmt = $classroom->getByTeacher($_SESSION['teacher_id']);
$teacher_classes = $classes_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get class ID from URL or default to first class
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

// If no class_id provided and teacher has classes, use the first one
if (!$class_id && !empty($teacher_classes)) {
    $class_id = $teacher_classes[0]['id'];
}

// Verify class belongs to teacher
if (!$class_id || !$classroom->getById($class_id) || $classroom->enseignant_id != $_SESSION['teacher_id']) {
    if (empty($teacher_classes)) {
        // Teacher has no classes - show message
        $class_name = '';
        $teams = [];
        $unassigned_students = [];
        $all_students = [];
    } else {
        header('Location: index.php?page=teams&class_id=' . $teacher_classes[0]['id']);
        exit;
    }
} else {
    $class_name = $classroom->nom;
}

// Handle team creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_team'])) {
    $team_name = trim($_POST['team_name'] ?? '');
    
    if (empty($team_name)) {
        $error = "Le nom de l'équipe est requis.";
    } elseif ($team->nameExistsInClass($team_name, $class_id)) {
        $error = "Une équipe avec ce nom existe déjà dans cette classe.";
    } else {
        $team->name = $team_name;
        $team->class_id = $class_id;
        
        if ($team->create()) {
            $message = "Équipe créée avec succès.";
        } else {
            $error = "Erreur lors de la création de l'équipe.";
        }
    }
}

// Handle team rename
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rename_team'])) {
    $team_id = intval($_POST['team_id'] ?? 0);
    $new_name = trim($_POST['new_name'] ?? '');
    
    if ($team_id && $team->getById($team_id) && $team->class_id == $class_id) {
        if (empty($new_name)) {
            $error = "Le nom de l'équipe est requis.";
        } elseif ($team->nameExistsInClass($new_name, $class_id, $team_id)) {
            $error = "Une équipe avec ce nom existe déjà dans cette classe.";
        } else {
            $team->name = $new_name;
            if ($team->update()) {
                $message = "Équipe renommée avec succès.";
            } else {
                $error = "Erreur lors du renommage de l'équipe.";
            }
        }
    } else {
        $error = "Équipe introuvable.";
    }
}

// Handle team deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_team'])) {
    $team_id = intval($_POST['team_id'] ?? 0);
    
    if ($team_id && $team->getById($team_id) && $team->class_id == $class_id) {
        if ($team->delete()) {
            $message = "Équipe supprimée avec succès. Les élèves restent dans la classe.";
        } else {
            $error = "Erreur lors de la suppression de l'équipe.";
        }
    } else {
        $error = "Équipe introuvable.";
    }
}

// Handle adding member to team
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_member'])) {
    $team_id = intval($_POST['team_id'] ?? 0);
    $student_id = intval($_POST['student_id'] ?? 0);
    
    if ($team_id && $student_id && $team->getById($team_id) && $team->class_id == $class_id) {
        $result = $team->addMember($student_id);
        
        if ($result === true) {
            $message = "Élève ajouté à l'équipe avec succès.";
        } elseif ($result === 'already_in_team') {
            $error = "Cet élève est déjà dans une autre équipe. Retirez-le d'abord.";
        } elseif ($result === 'already_in_this_team') {
            $error = "Cet élève est déjà dans cette équipe.";
        } elseif ($result === 'invalid_student') {
            $error = "Cet élève n'appartient pas à cette classe.";
        } else {
            $error = "Erreur lors de l'ajout de l'élève à l'équipe.";
        }
    } else {
        $error = "Données invalides.";
    }
}

// Handle removing member from team
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remove_member'])) {
    $team_id = intval($_POST['team_id'] ?? 0);
    $student_id = intval($_POST['student_id'] ?? 0);
    
    if ($team_id && $student_id && $team->getById($team_id) && $team->class_id == $class_id) {
        if ($team->removeMember($student_id)) {
            $message = "Élève retiré de l'équipe. Il reste dans la classe.";
        } else {
            $error = "Erreur lors du retrait de l'élève.";
        }
    } else {
        $error = "Données invalides.";
    }
}

// Handle moving member to another team
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['move_member'])) {
    $from_team_id = intval($_POST['from_team_id'] ?? 0);
    $to_team_id = intval($_POST['to_team_id'] ?? 0);
    $student_id = intval($_POST['student_id'] ?? 0);
    
    if ($from_team_id && $to_team_id && $student_id && $from_team_id != $to_team_id) {
        if ($team->getById($from_team_id) && $team->class_id == $class_id) {
            if ($team->moveMember($student_id, $to_team_id)) {
                $message = "Élève déplacé vers la nouvelle équipe avec succès.";
            } else {
                $error = "Erreur lors du déplacement de l'élève.";
            }
        } else {
            $error = "Équipe source introuvable.";
        }
    } else {
        $error = "Données invalides pour le déplacement.";
    }
}

// Get all teams for this class
if ($class_id) {
    $teams_stmt = $team->getByClass($class_id);
    $teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get unassigned students
    $unassigned_stmt = $team->getUnassignedStudents($class_id);
    $unassigned_students = $unassigned_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all students for the class
    $all_students_stmt = $student->getByClass($class_id);
    $all_students = $all_students_stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#667eea">
    <title>Équipes - <?php echo htmlspecialchars($class_name ?: 'No9ati'); ?> - No9ati</title>
    
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/svg+xml" href="assets/icons/icon-72x72.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/toast.css">
    
    <style>
        .kanban-container {
            display: flex;
            gap: 16px;
            overflow-x: auto;
            padding-bottom: 16px;
            min-height: 500px;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        .kanban-container::-webkit-scrollbar {
            display: none;
        }
        .kanban-column {
            min-width: 280px;
            max-width: 280px;
            background: #fff;
            border-radius: 16px;
            border: 1px solid rgba(0,0,0,0.08);
            display: flex;
            flex-direction: column;
            transition: all 0.2s ease;
        }
        .kanban-column.unassigned {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            border: 2px dashed #f59e0b;
        }
        .kanban-column.drag-over {
            border-color: #6366f1;
            background: rgba(99, 102, 241, 0.03);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        .kanban-column.unassigned.drag-over {
            border-color: #f59e0b;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2);
        }
        .kanban-header {
            padding: 16px;
            border-bottom: 1px solid rgba(0,0,0,0.06);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }
        .kanban-header h3 {
            font-size: 14px;
            font-weight: 600;
            color: #1c1c1c;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .kanban-count {
            background: #1c1c1c;
            color: #fff;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 10px;
            transition: all 0.2s;
        }
        .kanban-column.unassigned .kanban-count {
            background: #f59e0b;
        }
        .kanban-search {
            padding: 12px 16px;
            border-bottom: 1px solid rgba(0,0,0,0.06);
        }
        .kanban-search input {
            width: 100%;
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 13px;
            outline: none;
            transition: all 0.15s;
        }
        .kanban-search input:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        .kanban-body {
            flex: 1;
            overflow-y: auto;
            padding: 12px;
            min-height: 200px;
            max-height: 450px;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        .kanban-body::-webkit-scrollbar {
            display: none;
        }
        .student-card {
            background: #fff;
            border: 1px solid rgba(0,0,0,0.08);
            border-radius: 10px;
            padding: 10px 12px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: grab;
            transition: all 0.15s ease;
            user-select: none;
        }
        .student-card:hover {
            border-color: #6366f1;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.15);
            transform: translateY(-1px);
        }
        .student-card:active {
            cursor: grabbing;
        }
        .student-card.dragging {
            opacity: 0.5;
            transform: rotate(3deg) scale(1.02);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .student-card.drag-ghost {
            opacity: 0.4;
            border: 2px dashed #6366f1;
            background: rgba(99, 102, 241, 0.05);
        }
        /* Multi-select styles */
        .student-card.selected {
            border-color: #6366f1;
            background: rgba(99, 102, 241, 0.08);
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.3);
        }
        .student-card .select-checkbox {
            width: 20px;
            height: 20px;
            min-width: 20px;
            min-height: 20px;
            border: 2px solid rgba(0,0,0,0.2);
            border-radius: 4px;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: all 0.15s;
            cursor: pointer;
            flex-shrink: 0;
        }
        .student-card:hover .select-checkbox,
        .student-card.selected .select-checkbox {
            opacity: 1;
        }
        .student-card.selected .select-checkbox {
            background: #6366f1;
            border-color: #6366f1;
        }
        .student-card.selected .select-checkbox svg {
            display: block;
        }
        .student-card .select-checkbox svg {
            display: none;
            width: 14px;
            height: 14px;
            color: #fff;
            stroke-width: 3;
        }
        .selection-info {
            display: none;
            align-items: center;
            gap: 12px;
            padding: 10px 16px;
            background: #6366f1;
            color: #fff;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
        }
        .selection-info.active {
            display: flex;
        }
        .selection-info .clear-selection {
            background: rgba(255,255,255,0.2);
            border: none;
            color: #fff;
            padding: 4px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        .selection-info .clear-selection:hover {
            background: rgba(255,255,255,0.3);
        }
        .kanban-column.unassigned .student-card {
            background: #fff;
        }
        .student-avatar {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 600;
            flex-shrink: 0;
        }
        .student-info {
            flex: 1;
            overflow: hidden;
        }
        .student-name {
            font-size: 14px;
            font-weight: 500;
            color: #1c1c1c;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .drag-handle {
            color: rgba(0,0,0,0.2);
            flex-shrink: 0;
        }
        .student-card:hover .drag-handle {
            color: rgba(0,0,0,0.4);
        }
        .remove-btn.processing {
            opacity: 0.5;
            pointer-events: none;
        }
        .drop-indicator {
            height: 3px;
            background: #6366f1;
            border-radius: 3px;
            margin: 4px 0;
            opacity: 0;
            transition: opacity 0.15s;
        }
        .drop-indicator.active {
            opacity: 1;
        }
        .empty-state {
            text-align: center;
            padding: 40px 16px;
            color: rgba(0,0,0,0.35);
        }
        .empty-state svg {
            opacity: 0.25;
            margin-bottom: 12px;
        }
        .empty-state p {
            font-size: 13px;
            margin: 0;
        }
        .hidden {
            display: none !important;
        }
        /* Remove button for team members */
        .remove-btn {
            opacity: 0;
            width: 24px;
            height: 24px;
            border: none;
            border-radius: 6px;
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s;
            flex-shrink: 0;
            padding: 0;
        }
        .student-card:hover .remove-btn {
            opacity: 1;
        }
        .remove-btn:hover {
            background: #ef4444;
            color: #fff;
        }
        @media (max-width: 768px) {
            .kanban-column {
                min-width: 260px;
                max-width: 260px;
            }
        }
        /* Toast Notifications */
        /* Custom toast styles removed - using global toast system from assets/css/toast.css */
    </style>
</head>
<body style="background: #f9f9fa;">
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

                <div class="d-flex align-items-center">
                    <h1 style="font-size: 16px; font-weight: 600; color: #1c1c1c; margin: 0;">Équipes</h1>
                </div>

                <div class="d-flex align-items-center">
                    <div class="d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; background: #1c1c1c; color: #fff; border-radius: 50%; font-size: 14px; font-weight: 500;">
                        <?php echo strtoupper(substr($_SESSION['teacher_name'], 0, 1)); ?>
                    </div>
                </div>
            </div>
        </nav>

        <main class="container-fluid" style="padding: 28px;">
            <?php if (empty($teacher_classes)): ?>
                <div class="card" style="background: #fff; border-radius: 20px; border: none; padding: 48px; text-align: center;">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="rgba(0,0,0,0.15)" stroke-width="1.5" style="margin: 0 auto 20px;">
                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                    </svg>
                    <h3 style="font-size: 18px; font-weight: 600; color: #1c1c1c; margin-bottom: 8px;">Aucune classe</h3>
                    <p style="font-size: 14px; color: rgba(0,0,0,0.4); margin-bottom: 20px;">Créez d'abord une classe pour gérer les équipes.</p>
                    <a href="index.php?page=manage_classes" class="btn mx-auto" style="background: #1c1c1c; color: #fff; border-radius: 12px; padding: 12px 24px; font-size: 14px;">
                        Créer une classe
                    </a>
                </div>
            <?php else: ?>

            <!-- Header with Class Selector -->
            <div class="row g-3 align-items-center mb-4">
                <div class="col-12 col-md-4">
                    <select id="classSelector" class="form-select" onchange="window.location.href='?page=teams&class_id='+this.value" style="border: 1px solid rgba(0,0,0,0.1); border-radius: 12px; padding: 12px 16px; font-size: 14px; font-weight: 500;">
                        <?php foreach ($teacher_classes as $tc): ?>
                            <option value="<?php echo $tc['id']; ?>" <?php echo $tc['id'] == $class_id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tc['nom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-8">
                    <div class="d-flex flex-wrap gap-2 justify-content-md-end">
                        <div class="d-flex align-items-center gap-3 me-3">
                            <span style="font-size: 13px; color: rgba(0,0,0,0.5);">
                                <strong id="stat-total" style="color: #1c1c1c;"><?php echo count($all_students); ?></strong> élèves
                            </span>
                            <span style="font-size: 13px; color: rgba(0,0,0,0.5);">
                                <strong id="stat-assigned" style="color: #22c55e;"><?php echo count($all_students) - count($unassigned_students); ?></strong> assignés
                            </span>
                            <span style="font-size: 13px; color: rgba(0,0,0,0.5);">
                                <strong id="stat-free" style="color: #f59e0b;"><?php echo count($unassigned_students); ?></strong> libres
                            </span>
                        </div>
                        <button class="btn" data-bs-toggle="modal" data-bs-target="#createTeamModal" style="background: #1c1c1c; color: #fff; border-radius: 10px; padding: 10px 20px; font-size: 14px;">
                            <svg class="me-2" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            Nouvelle équipe
                        </button>
                    </div>
                </div>
            </div>

            <!-- Drag & Drop Hint + Selection Info -->
            <div class="d-flex align-items-center justify-content-between mb-3" style="min-height: 36px;">
                <div class="d-flex align-items-center gap-2" style="color: rgba(0,0,0,0.4); font-size: 13px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 9l-3 3 3 3"></path>
                        <path d="M9 5l3-3 3 3"></path>
                        <path d="M15 19l-3 3-3-3"></path>
                        <path d="M19 9l3 3-3 3"></path>
                        <line x1="2" y1="12" x2="22" y2="12"></line>
                        <line x1="12" y1="2" x2="12" y2="22"></line>
                    </svg>
                    <span>Glissez-déposez • Ctrl+Clic pour sélectionner plusieurs</span>
                </div>
                <div class="selection-info" id="selection-info">
                    <span><strong id="selection-count">0</strong> élèves sélectionnés</span>
                    <button class="clear-selection" onclick="clearSelection()">Annuler</button>
                </div>
            </div>

            <!-- Kanban Board -->
            <div class="kanban-container" id="kanbanContainer">
                <!-- Unassigned Column -->
                <div class="kanban-column unassigned" data-team-id="0" data-team-name="Non assignés" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)" ondrop="handleDrop(event, 0)">
                    <div class="kanban-header">
                        <h3>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                            Non assignés
                            <span class="kanban-count" id="count-0"><?php echo count($unassigned_students); ?></span>
                        </h3>
                    </div>
                    <div class="kanban-search">
                        <input type="text" placeholder="Rechercher un élève..." onkeyup="filterStudents(this, 'unassigned')">
                    </div>
                    <div class="kanban-body" id="unassigned-body">
                        <?php if (empty($unassigned_students)): ?>
                            <div class="empty-state" id="empty-0">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                </svg>
                                <p>Tous les élèves sont assignés</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($unassigned_students as $s): ?>
                                <?php if (trim($s['nom'])): ?>
                                <div class="student-card" 
                                     draggable="true" 
                                     data-student-id="<?php echo $s['id']; ?>" 
                                     data-student-name="<?php echo htmlspecialchars($s['nom']); ?>"
                                     data-current-team="0"
                                     ondragstart="handleDragStart(event)" 
                                     ondragend="handleDragEnd(event)">
                                    <div class="select-checkbox">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                            <polyline points="20 6 9 17 4 12"></polyline>
                                        </svg>
                                    </div>
                                    <div class="student-avatar"><?php echo strtoupper(substr($s['nom'], 0, 1)); ?></div>
                                    <div class="student-info">
                                        <div class="student-name"><?php echo htmlspecialchars($s['nom']); ?></div>
                                    </div>
                                    <div class="drag-handle">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="9" cy="5" r="1"></circle>
                                            <circle cx="9" cy="12" r="1"></circle>
                                            <circle cx="9" cy="19" r="1"></circle>
                                            <circle cx="15" cy="5" r="1"></circle>
                                            <circle cx="15" cy="12" r="1"></circle>
                                            <circle cx="15" cy="19" r="1"></circle>
                                        </svg>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Team Columns -->
                <?php foreach ($teams as $t): ?>
                <?php 
                    $team->getById($t['id']);
                    $members_stmt = $team->getMembers();
                    $members = $members_stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <div class="kanban-column" data-team-id="<?php echo $t['id']; ?>" data-team-name="<?php echo htmlspecialchars($t['name']); ?>" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)" ondrop="handleDrop(event, <?php echo $t['id']; ?>)">
                    <div class="kanban-header">
                        <h3>
                            <?php echo htmlspecialchars($t['name']); ?>
                            <span class="kanban-count" id="count-<?php echo $t['id']; ?>"><?php echo count($members); ?></span>
                        </h3>
                        <div class="dropdown">
                            <button class="btn btn-sm" type="button" data-bs-toggle="dropdown" style="background: transparent; border: none; padding: 4px;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#1c1c1c" stroke-width="2">
                                    <circle cx="12" cy="12" r="1"></circle>
                                    <circle cx="12" cy="5" r="1"></circle>
                                    <circle cx="12" cy="19" r="1"></circle>
                                </svg>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" style="border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                                <li>
                                    <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#renameTeamModal<?php echo $t['id']; ?>" style="font-size: 13px;">
                                        <svg class="me-2" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                        </svg>
                                        Renommer
                                    </button>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" onsubmit="return confirm('Supprimer cette équipe?');" style="margin: 0;">
                                        <input type="hidden" name="delete_team" value="1">
                                        <input type="hidden" name="team_id" value="<?php echo $t['id']; ?>">
                                        <button type="submit" class="dropdown-item text-danger" style="font-size: 13px;">
                                            <svg class="me-2" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="3 6 5 6 21 6"></polyline>
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                            </svg>
                                            Supprimer
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="kanban-search">
                        <input type="text" placeholder="Rechercher..." onkeyup="filterStudents(this, 'team-<?php echo $t['id']; ?>')">
                    </div>
                    <div class="kanban-body" id="team-<?php echo $t['id']; ?>-body">
                        <?php if (empty($members)): ?>
                            <div class="empty-state" id="empty-<?php echo $t['id']; ?>">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                </svg>
                                <p>Glissez des élèves ici</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($members as $m): ?>
                            <div class="student-card" 
                                 draggable="true" 
                                 data-student-id="<?php echo $m['id']; ?>" 
                                 data-student-name="<?php echo htmlspecialchars($m['nom']); ?>"
                                 data-current-team="<?php echo $t['id']; ?>"
                                 ondragstart="handleDragStart(event)" 
                                 ondragend="handleDragEnd(event)">
                                <div class="select-checkbox">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                </div>
                                <div class="student-avatar"><?php echo strtoupper(substr($m['nom'], 0, 1)); ?></div>
                                <div class="student-info">
                                    <div class="student-name"><?php echo htmlspecialchars($m['nom']); ?></div>
                                </div>
                                <button class="remove-btn" data-team-id="<?php echo $t['id']; ?>" data-student-id="<?php echo $m['id']; ?>" title="Retirer">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="18" y1="6" x2="6" y2="18"></line>
                                        <line x1="6" y1="6" x2="18" y2="18"></line>
                                    </svg>
                                </button>
                                <div class="drag-handle">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="9" cy="5" r="1"></circle>
                                        <circle cx="9" cy="12" r="1"></circle>
                                        <circle cx="9" cy="19" r="1"></circle>
                                        <circle cx="15" cy="5" r="1"></circle>
                                        <circle cx="15" cy="12" r="1"></circle>
                                        <circle cx="15" cy="19" r="1"></circle>
                                    </svg>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Rename Modal -->
                <div class="modal fade" id="renameTeamModal<?php echo $t['id']; ?>" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content" style="border-radius: 16px; border: none;">
                            <form method="POST">
                                <div class="modal-body" style="padding: 24px;">
                                    <input type="hidden" name="rename_team" value="1">
                                    <input type="hidden" name="team_id" value="<?php echo $t['id']; ?>">
                                    <h5 style="font-size: 16px; font-weight: 600; margin-bottom: 16px;">Renommer l'équipe</h5>
                                    <input type="text" name="new_name" class="form-control" value="<?php echo htmlspecialchars($t['name']); ?>" required style="border: 1px solid rgba(0,0,0,0.1); border-radius: 10px; padding: 12px 16px; font-size: 14px;">
                                </div>
                                <div class="modal-footer" style="border-top: none; padding: 0 24px 24px;">
                                    <button type="button" class="btn" data-bs-dismiss="modal" style="background: rgba(0,0,0,0.04); color: #1c1c1c; border: none; border-radius: 10px; padding: 10px 20px; font-size: 14px;">Annuler</button>
                                    <button type="submit" class="btn" style="background: #1c1c1c; color: #fff; border-radius: 10px; padding: 10px 20px; font-size: 14px;">Enregistrer</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Add New Team Column -->
                <?php if (empty($teams)): ?>
                <div class="kanban-column" style="background: transparent; border: 2px dashed rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: center;">
                    <div class="text-center" style="padding: 40px;">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="rgba(0,0,0,0.2)" stroke-width="1.5" style="margin-bottom: 16px;">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="12" y1="8" x2="12" y2="16"></line>
                            <line x1="8" y1="12" x2="16" y2="12"></line>
                        </svg>
                        <p style="color: rgba(0,0,0,0.4); font-size: 14px; margin-bottom: 16px;">Créez votre première équipe</p>
                        <button class="btn" data-bs-toggle="modal" data-bs-target="#createTeamModal" style="background: #1c1c1c; color: #fff; border-radius: 10px; padding: 10px 20px; font-size: 14px;">
                            Créer une équipe
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Create Team Modal -->
    <div class="modal fade" id="createTeamModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 16px; border: none;">
                <form method="POST">
                    <div class="modal-body" style="padding: 24px;">
                        <input type="hidden" name="create_team" value="1">
                        <h5 style="font-size: 16px; font-weight: 600; margin-bottom: 16px;">Nouvelle équipe</h5>
                        <input type="text" name="team_name" class="form-control" placeholder="Nom de l'équipe..." required style="border: 1px solid rgba(0,0,0,0.1); border-radius: 10px; padding: 12px 16px; font-size: 14px;">
                    </div>
                    <div class="modal-footer" style="border-top: none; padding: 0 24px 24px;">
                        <button type="button" class="btn" data-bs-dismiss="modal" style="background: rgba(0,0,0,0.04); color: #1c1c1c; border: none; border-radius: 10px; padding: 10px 20px; font-size: 14px;">Annuler</button>
                        <button type="submit" class="btn" style="background: #1c1c1c; color: #fff; border-radius: 10px; padding: 10px 20px; font-size: 14px;">Créer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/toast.js"></script>
    <script>
        const classId = <?php echo $class_id; ?>;
        let draggedElement = null;
        let draggedStudentId = null;
        let draggedFromTeam = null;

        // Using global showToast() from assets/js/toast.js

        // AJAX request handler (no toast here, handled by caller)
        async function sendAction(action, data) {
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', action);
            formData.append('class_id', classId);
            
            for (const key in data) {
                formData.append(key, data[key]);
            }
            
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                return result;
            } catch (error) {
                return { success: false, message: 'Erreur de connexion' };
            }
        }

        // Move card in DOM
        function moveCardToColumn(card, targetTeamId) {
            const targetBodyId = targetTeamId == 0 ? 'unassigned-body' : `team-${targetTeamId}-body`;
            const targetBody = document.getElementById(targetBodyId);
            
            if (!targetBody) return;
            
            // Hide empty state if exists
            const emptyState = targetBody.querySelector('.empty-state');
            if (emptyState) emptyState.style.display = 'none';
            
            // Update card's team data
            card.dataset.currentTeam = targetTeamId;
            
            // Move card
            targetBody.appendChild(card);
            
            // Update counts
            updateCounts();
            
            // Check source column for empty state
            checkEmptyStates();
        }

        // Update all column counts and stats
        function updateCounts() {
            let totalAssigned = 0;
            let totalFree = 0;
            
            document.querySelectorAll('.kanban-column').forEach(column => {
                const teamId = column.dataset.teamId;
                const bodyId = teamId == 0 ? 'unassigned-body' : `team-${teamId}-body`;
                const body = document.getElementById(bodyId);
                const cards = body ? body.querySelectorAll('.student-card').length : 0;
                
                const countEl = document.getElementById(`count-${teamId}`);
                if (countEl) countEl.textContent = cards;
                
                if (teamId == 0) {
                    totalFree = cards;
                } else {
                    totalAssigned += cards;
                }
            });
            
            // Update header stats
            const statAssigned = document.getElementById('stat-assigned');
            const statFree = document.getElementById('stat-free');
            if (statAssigned) statAssigned.textContent = totalAssigned;
            if (statFree) statFree.textContent = totalFree;
        }

        // Get team name by ID
        function getTeamName(teamId) {
            const column = document.querySelector(`[data-team-id="${teamId}"]`);
            return column ? column.dataset.teamName : '';
        }

        // Check and show/hide empty states
        function checkEmptyStates() {
            document.querySelectorAll('.kanban-body').forEach(body => {
                const cards = body.querySelectorAll('.student-card').length;
                const emptyState = body.querySelector('.empty-state');
                
                if (emptyState) {
                    emptyState.style.display = cards === 0 ? 'block' : 'none';
                }
            });
        }

        // Drag Start - includes selected students visual
        function handleDragStart(e) {
            draggedElement = e.target.closest('.student-card');
            draggedStudentId = draggedElement.dataset.studentId;
            draggedFromTeam = draggedElement.dataset.currentTeam;
            
            draggedElement.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', draggedStudentId);
            
            // If dragging a selected card, show all selected as dragging
            if (selectedStudents.has(draggedStudentId)) {
                selectedStudents.forEach(id => {
                    const card = document.querySelector(`[data-student-id="${id}"]`);
                    if (card && card !== draggedElement) {
                        card.classList.add('dragging');
                    }
                });
            }
        }

        // Drag End - clear all dragging states
        function handleDragEnd(e) {
            document.querySelectorAll('.student-card.dragging').forEach(card => {
                card.classList.remove('dragging');
            });
            
            document.querySelectorAll('.kanban-column').forEach(col => {
                col.classList.remove('drag-over');
            });
            
            draggedElement = null;
            draggedStudentId = null;
            draggedFromTeam = null;
        }

        // Drag Over
        function handleDragOver(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            
            const column = e.target.closest('.kanban-column');
            if (column && column.dataset.teamId !== draggedFromTeam) {
                column.classList.add('drag-over');
            }
        }

        // Drag Leave
        function handleDragLeave(e) {
            const column = e.target.closest('.kanban-column');
            if (column && !column.contains(e.relatedTarget)) {
                column.classList.remove('drag-over');
            }
        }

        // Multi-select support
        let selectedStudents = new Set();

        function toggleSelection(card, e) {
            if (e) e.stopPropagation();
            const studentId = card.dataset.studentId;
            
            if (selectedStudents.has(studentId)) {
                selectedStudents.delete(studentId);
                card.classList.remove('selected');
            } else {
                selectedStudents.add(studentId);
                card.classList.add('selected');
            }
            updateSelectionInfo();
        }

        function clearSelection() {
            selectedStudents.clear();
            document.querySelectorAll('.student-card.selected').forEach(card => {
                card.classList.remove('selected');
            });
            updateSelectionInfo();
        }

        function updateSelectionInfo() {
            const info = document.getElementById('selection-info');
            const count = document.getElementById('selection-count');
            if (selectedStudents.size > 0) {
                info.classList.add('active');
                count.textContent = selectedStudents.size;
            } else {
                info.classList.remove('active');
            }
        }

        // Drop - supports multi-select
        async function handleDrop(e, targetTeamId) {
            e.preventDefault();
            
            const column = e.target.closest('.kanban-column');
            if (column) {
                column.classList.remove('drag-over');
            }
            
            // Get students to move (selected or just the dragged one)
            let studentsToMove = [];
            
            if (selectedStudents.size > 0 && selectedStudents.has(draggedStudentId)) {
                // Move all selected students
                studentsToMove = Array.from(selectedStudents);
            } else if (draggedStudentId) {
                // Move just the dragged student
                studentsToMove = [draggedStudentId];
            }
            
            if (studentsToMove.length === 0) return;
            
            let successCount = 0;
            let errorMsg = null;
            
            for (const studentId of studentsToMove) {
                const card = document.querySelector(`[data-student-id="${studentId}"]`);
                if (!card) continue;
                
                const fromTeam = card.dataset.currentTeam;
                if (fromTeam == targetTeamId) continue;
                
                let result;
                
                if (fromTeam == '0' && targetTeamId != 0) {
                    result = await sendAction('add_member', {
                        team_id: targetTeamId,
                        student_id: studentId
                    });
                } else if (fromTeam != '0' && targetTeamId == 0) {
                    result = await sendAction('remove_member', {
                        team_id: fromTeam,
                        student_id: studentId
                    });
                } else {
                    result = await sendAction('move_member', {
                        from_team_id: fromTeam,
                        to_team_id: targetTeamId,
                        student_id: studentId
                    });
                }
                
                if (result.success) {
                    moveCardToColumn(card, targetTeamId);
                    card.classList.remove('selected');
                    successCount++;
                } else {
                    errorMsg = result.message;
                }
            }
            
            // Clear selection after move
            selectedStudents.clear();
            updateSelectionInfo();
            
            // Only show toast on error
            if (errorMsg && successCount === 0) {
                showToast(errorMsg || 'Erreur lors de l\'opération', 'error');
            }
        }

        // Search/Filter students
        function filterStudents(input, columnId) {
            const searchTerm = input.value.toLowerCase();
            const body = document.getElementById(columnId + '-body');
            if (!body) return;
            
            const cards = body.querySelectorAll('.student-card');
            
            cards.forEach(card => {
                const name = card.dataset.studentName.toLowerCase();
                if (name.includes(searchTerm)) {
                    card.classList.remove('hidden');
                } else {
                    card.classList.add('hidden');
                }
            });
        }

        // Handle card click for selection
        document.addEventListener('click', function(e) {
            const checkbox = e.target.closest('.select-checkbox');
            if (checkbox) {
                e.preventDefault();
                e.stopPropagation();
                const card = checkbox.closest('.student-card');
                if (card) toggleSelection(card, e);
                return;
            }
            
            // Ctrl/Cmd click on card to select
            const card = e.target.closest('.student-card');
            if (card && (e.ctrlKey || e.metaKey) && !e.target.closest('.remove-btn')) {
                e.preventDefault();
                toggleSelection(card, e);
                return;
            }
        });

        // Handle remove button click with AJAX
        document.addEventListener('click', async function(e) {
            const removeBtn = e.target.closest('.remove-btn');
            if (!removeBtn) return;
            
            // Prevent double-click
            if (removeBtn.classList.contains('processing')) return;
            
            e.preventDefault();
            e.stopPropagation();
            
            removeBtn.classList.add('processing');
            
            const teamId = removeBtn.dataset.teamId;
            const studentId = removeBtn.dataset.studentId;
            const card = removeBtn.closest('.student-card');
            
            const result = await sendAction('remove_member', {
                team_id: teamId,
                student_id: studentId
            });
            
            removeBtn.classList.remove('processing');
            
            if (result && result.success && card) {
                moveCardToColumn(card, 0);
                card.classList.remove('selected');
                selectedStudents.delete(studentId);
                updateSelectionInfo();
            } else if (result && !result.success) {
                showToast(result.message || 'Erreur lors de la suppression', 'error');
            }
        });

        // Touch support for mobile drag and drop
        let touchStartY = 0;
        let touchStartX = 0;
        let touchElement = null;
        let touchClone = null;

        document.addEventListener('touchstart', function(e) {
            const card = e.target.closest('.student-card');
            if (card && !e.target.closest('.remove-btn')) {
                touchStartX = e.touches[0].clientX;
                touchStartY = e.touches[0].clientY;
                touchElement = card;
            }
        }, { passive: true });

        document.addEventListener('touchmove', function(e) {
            if (!touchElement) return;
            
            const touchX = e.touches[0].clientX;
            const touchY = e.touches[0].clientY;
            
            if (!touchClone && (Math.abs(touchX - touchStartX) > 10 || Math.abs(touchY - touchStartY) > 10)) {
                touchClone = touchElement.cloneNode(true);
                touchClone.style.position = 'fixed';
                touchClone.style.pointerEvents = 'none';
                touchClone.style.zIndex = '9999';
                touchClone.style.opacity = '0.8';
                touchClone.style.transform = 'rotate(3deg) scale(1.05)';
                touchClone.style.boxShadow = '0 8px 25px rgba(0,0,0,0.2)';
                touchClone.style.width = touchElement.offsetWidth + 'px';
                document.body.appendChild(touchClone);
                
                touchElement.style.opacity = '0.3';
                
                draggedElement = touchElement;
                draggedStudentId = touchElement.dataset.studentId;
                draggedFromTeam = touchElement.dataset.currentTeam;
            }
            
            if (touchClone) {
                touchClone.style.left = (touchX - 140) + 'px';
                touchClone.style.top = (touchY - 30) + 'px';
                
                const elemBelow = document.elementFromPoint(touchX, touchY);
                const column = elemBelow?.closest('.kanban-column');
                
                document.querySelectorAll('.kanban-column').forEach(col => col.classList.remove('drag-over'));
                if (column && column.dataset.teamId !== draggedFromTeam) {
                    column.classList.add('drag-over');
                }
            }
        }, { passive: true });

        document.addEventListener('touchend', async function(e) {
            if (touchClone) {
                const touchX = e.changedTouches[0].clientX;
                const touchY = e.changedTouches[0].clientY;
                
                touchClone.remove();
                touchElement.style.opacity = '';
                
                const elemBelow = document.elementFromPoint(touchX, touchY);
                const column = elemBelow?.closest('.kanban-column');
                
                if (column && column.dataset.teamId !== draggedFromTeam) {
                    await handleDrop({ preventDefault: () => {} }, parseInt(column.dataset.teamId));
                }
                
                document.querySelectorAll('.kanban-column').forEach(col => col.classList.remove('drag-over'));
            }
            
            touchElement = null;
            touchClone = null;
            draggedElement = null;
            draggedStudentId = null;
            draggedFromTeam = null;
        });
    </script>
</body>
</html>
