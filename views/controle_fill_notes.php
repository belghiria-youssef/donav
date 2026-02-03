<?php
/**
 * Fill Notes for a Controle Instance
 * Enter actual grades for students (individual or team based on phase mode)
 */

if (!isset($_SESSION['teacher_id'])) {
    header('Location: index.php?page=login');
    exit;
}

require_once 'classes/ClassRoom.php';
require_once 'classes/Controle.php';
require_once 'classes/Team.php';
require_once 'classes/Student.php';

$database = new Database();
$db = $database->getConnection();

$classroom = new ClassRoom($db);
$controle = new Controle($db);
$team = new Team($db);
$student = new Student($db);

$instance_id = intval($_GET['id'] ?? 0);

if ($instance_id <= 0) {
    header('Location: index.php?page=controle_fill_points');
    exit;
}

$instance = $controle->getInstanceById($instance_id);

if (!$instance) {
    header('Location: index.php?page=controle_fill_points');
    exit;
}

// Verify ownership
if ($instance['created_by'] != $_SESSION['teacher_id']) {
    header('Location: index.php?page=controle_fill_points');
    exit;
}

$message = '';
$error = '';

// Get phases for this template
$phases = $controle->getPhases($instance['template_id']);

// Get students in the class
$students_stmt = $student->getByClass($instance['class_id']);
$students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get teams in the class
$teams_stmt = $team->getByClass($instance['class_id']);
$teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);

// Build a lookup of student -> team
$student_teams = [];
foreach ($teams as $t) {
    $team->id = $t['id'];
    $members_stmt = $team->getMembers();
    $members = $members_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($members as $m) {
        $student_teams[$m['id']] = $t['id'];
    }
}

// Handle saving notes
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_notes'])) {
    if ($instance['is_locked']) {
        $error = "Ce contrôle est verrouillé.";
    } else {
        $phase_id = intval($_POST['phase_id'] ?? 0);
        $phase = $controle->getPhaseById($phase_id);
        
        if ($phase) {
            $success_count = 0;
            
            if ($phase['grading_mode'] === 'individual') {
                // Save individual notes
                if (isset($_POST['notes']) && is_array($_POST['notes'])) {
                    foreach ($_POST['notes'] as $student_id => $note) {
                        if ($note !== '' && is_numeric($note)) {
                            $remarks = $_POST['remarks'][$student_id] ?? null;
                            if ($controle->saveIndividualNote($instance_id, $phase_id, $student_id, floatval($note), $remarks)) {
                                $success_count++;
                            }
                        }
                    }
                }
            } else {
                // Save team notes
                if (isset($_POST['team_notes']) && is_array($_POST['team_notes'])) {
                    foreach ($_POST['team_notes'] as $team_id => $note) {
                        if ($note !== '' && is_numeric($note)) {
                            $remarks = $_POST['team_remarks'][$team_id] ?? null;
                            if ($controle->saveTeamNote($instance_id, $phase_id, $team_id, floatval($note), $remarks)) {
                                $success_count++;
                            }
                        }
                    }
                }
            }
            
            $message = "$success_count note(s) enregistrée(s) avec succès!";
            
            // Recompute results
            $controle->computeAllResults($instance_id);
        } else {
            $error = "Phase non trouvée.";
        }
    }
}

// Current phase (for tab display)
$current_phase_id = isset($_GET['phase']) ? intval($_GET['phase']) : ($phases[0]['id'] ?? 0);
$current_phase = null;
foreach ($phases as $p) {
    if ($p['id'] == $current_phase_id) {
        $current_phase = $p;
        break;
    }
}

// Get existing notes for current phase
$existing_notes = [];
if ($current_phase) {
    if ($current_phase['grading_mode'] === 'individual') {
        $notes = $controle->getInstanceIndividualNotes($instance_id, $current_phase_id);
        foreach ($notes as $n) {
            $existing_notes[$n['student_id']] = $n;
        }
    } else {
        $notes = $controle->getInstanceTeamNotes($instance_id, $current_phase_id);
        foreach ($notes as $n) {
            $existing_notes[$n['team_id']] = $n;
        }
    }
}

// Get grading progress
$progress = $controle->getGradingProgress($instance_id);

include 'views/partials/header.php';
include 'views/partials/sidebar.php';
?>

<main class="main-content" style="margin-left: 212px; padding-top: 68px; min-height: 100vh; background: #f9f9fa;">
    <div class="container-fluid" style="padding: 28px;">
        <div class="rounded-4 p-4 shadow-sm mb-4" style="background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 60%, #a855f7 100%); color: #fff;">
            <div class="d-flex flex-column flex-md-row justify-content-between gap-3 align-items-start">
                <div>
                    <p class="mb-2 text-uppercase" style="letter-spacing: 1px; font-size: 13px;">Saisie des notes</p>
                    <h3 class="mb-1" style="font-weight: 600; font-size: 28px;">
                        <?php echo htmlspecialchars($instance['template_title']); ?>
                    </h3>
                    <div class="d-flex flex-wrap gap-2 align-items-center" style="font-size: 14px;">
                        <span class="badge bg-white text-dark py-2 px-3 rounded-pill">
                            <?php echo htmlspecialchars($instance['class_name']); ?>
                        </span>
                        <?php if (!empty($instance['year_level_name'])): ?>
                        <span class="badge bg-white text-dark py-2 px-3 rounded-pill">
                            <?php echo htmlspecialchars($instance['year_level_name']); ?>
                        </span>
                        <?php endif; ?>
                        <span class="badge bg-white text-dark py-2 px-3 rounded-pill">
                            Sur <?php echo $instance['total_points']; ?> pts
                        </span>
                        <?php if ($instance['session_name']): ?>
                        <span class="badge bg-white text-dark py-2 px-3 rounded-pill">
                            <?php echo htmlspecialchars($instance['session_name']); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a href="index.php?page=controle_instance_results&id=<?php echo $instance_id; ?>" class="btn btn-outline-light px-4">
                        <i class="bi bi-graph-up"></i> Résultats
                    </a>
                    <a href="index.php?page=controle_fill_points" class="btn btn-light px-4 text-dark">
                        <i class="bi bi-arrow-left"></i> Retour
                    </a>
                </div>
            </div>
            <?php if ($instance['is_locked']): ?>
            <div class="mt-3 d-flex align-items-center gap-2" style="font-size: 13px;">
                <i class="bi bi-lock"></i>
                Contrôle verrouillé • les notes ne peuvent plus être modifiées tant qu'il reste verrouillé.
            </div>
            <?php endif; ?>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success border-0 rounded-4 shadow-sm" role="alert">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger border-0 rounded-4 shadow-sm" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="row g-4 align-items-stretch">
            <div class="col-12">
                <div class="row g-3">
                    <?php $stats = [
                        ['label' => 'Progression', 'value' => ($progress['overall_percentage'] ?? 0) . '%', 'muted' => ($progress['overall_percentage'] ?? 0) == 100 ? 'Complète' : 'En cours'],
                        ['label' => 'Notes saisies', 'value' => $progress['total_filled'] ?? 0, 'muted' => 'sur ' . ($progress['total_expected'] ?? 0)],
                        ['label' => 'Phases', 'value' => count($phases), 'muted' => 'totales'],
                    ]; ?>
                    <?php foreach ($stats as $stat): ?>
                        <div class="col-md-4">
                            <div class="card border-0 rounded-4 shadow-sm p-3 h-100">
                                <small class="text-muted text-uppercase" style="letter-spacing: 1px; font-size: 11px;">
                                    <?php echo $stat['label']; ?>
                                </small>
                                <h4 class="mb-0" style="font-weight: 600;"><?php echo $stat['value']; ?></h4>
                                <small class="text-muted"><?php echo $stat['muted']; ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-2">
            <div class="col-xl-3">
                <div class="card border-0 rounded-4 shadow-sm h-100">
                    <div class="card-header bg-transparent border-0">
                        <h6 class="mb-0" style="font-weight: 600; letter-spacing: 1px;">Phases</h6>
                    </div>
                    <div class="list-group list-group-flush px-3 pb-3">
                        <?php foreach ($phases as $p): 
                            $phase_progress = null;
                            foreach ($progress['phases'] ?? [] as $pp) {
                                if ($pp['phase_id'] == $p['id']) {
                                    $phase_progress = $pp;
                                    break;
                                }
                            }
                        ?>
                            <a href="index.php?page=controle_fill_notes&id=<?php echo $instance_id; ?>&phase=<?php echo $p['id']; ?>" 
                               class="list-group-item list-group-item-action rounded-3 mb-2 <?php echo $current_phase_id == $p['id'] ? 'active text-white' : 'text-dark'; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong><?php echo htmlspecialchars($p['title']); ?></strong>
                                        <p class="mb-0" style="font-size: 12px;">
                                            <?php echo $p['points']; ?> pts • <?php echo $p['grading_mode'] === 'team' ? 'Équipe' : 'Individuel'; ?>
                                        </p>
                                    </div>
                                    <?php if ($phase_progress): ?>
                                        <span class="badge <?php echo $phase_progress['percentage'] == 100 ? 'bg-success' : 'bg-secondary'; ?> align-self-center">
                                            <?php echo $phase_progress['percentage']; ?>%
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="col-xl-9">
                <?php if (!$current_phase): ?>
                    <div class="card border-0 rounded-4 shadow-sm">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-exclamation-circle" style="font-size: 48px; color: rgba(15,23,42,0.25);"></i>
                            <p class="text-muted mt-3 mb-0">Aucune phase définie pour ce modèle.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card border-0 rounded-4 shadow-sm overflow-hidden">
                        <div class="d-flex justify-content-between align-items-center p-4 bg-white border-bottom">
                            <div>
                                <h5 class="mb-1" style="font-weight: 600;"><?php echo htmlspecialchars($current_phase['title']); ?></h5>
                                <p class="mb-0 text-muted" style="font-size: 14px;">
                                    <span>Vaut: <?php echo $current_phase['points']; ?> points</span>
                                    <span class="mx-2">•</span>
                                    <span><?php echo $current_phase['grading_mode'] === 'team' ? 'Mode Équipe' : 'Mode Individuel'; ?></span>
                                </p>
                            </div>
                            <span class="badge <?php echo $current_phase['grading_mode'] === 'team' ? 'bg-info text-dark' : 'bg-secondary'; ?> rounded-pill">
                                <i class="bi <?php echo $current_phase['grading_mode'] === 'team' ? 'bi-people' : 'bi-person'; ?>"></i>
                                <?php echo $current_phase['grading_mode'] === 'team' ? 'Équipe' : 'Individuel'; ?>
                            </span>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="phase_id" value="<?php echo $current_phase['id']; ?>">
                            <div class="card-body p-0">
                                <?php if ($current_phase['grading_mode'] === 'individual'): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover table-borderless mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="text-muted" style="width: 60px;">#</th>
                                                    <th>Élève</th>
                                                    <th style="width: 160px;">Note /<?php echo $current_phase['points']; ?></th>
                                                    <th>Remarques</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($students as $index => $s): 
                                                    $existing = $existing_notes[$s['id']] ?? null;
                                                ?>
                                                    <tr <?php echo !$existing ? 'class="table-warning" style="background-color: rgba(255, 193, 7, 0.1);"' : ''; ?>>
                                                        <td class="text-muted"><?php echo $index + 1; ?></td>
                                                        <td>
                                                            <div class="d-flex align-items-center gap-2">
                                                                <strong><?php echo htmlspecialchars($s['nom']); ?></strong>
                                                                <?php if (!$existing): ?>
                                                                    <span class="badge bg-warning text-dark" style="font-size: 10px;">Non noté</span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <?php if (isset($student_teams[$s['id']])): ?>
                                                                <p class="mb-0" style="font-size: 12px; color: rgba(15,23,42,0.6);">
                                                                    <i class="bi bi-people"></i>
                                                                    <?php 
                                                                    foreach ($teams as $t) {
                                                                        if ($t['id'] == $student_teams[$s['id']]) {
                                                                            echo htmlspecialchars($t['name']);
                                                                            break;
                                                                        }
                                                                    }
                                                                    ?>
                                                                </p>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <input type="number" 
                                                                   name="notes[<?php echo $s['id']; ?>]" 
                                                                   class="form-control form-control-sm <?php echo !$existing ? 'border-warning' : ''; ?>"
                                                                   value="<?php echo $existing ? $existing['note'] : ''; ?>"
                                                                   placeholder="<?php echo !$existing ? 'Pas encore noté' : ''; ?>"
                                                                   min="0" max="<?php echo $current_phase['points']; ?>" 
                                                                   step="0.25"
                                                                   <?php echo $instance['is_locked'] ? 'disabled' : ''; ?>>
                                                        </td>
                                                        <td>
                                                            <input type="text" 
                                                                   name="remarks[<?php echo $s['id']; ?>]" 
                                                                   class="form-control form-control-sm"
                                                                   value="<?php echo $existing ? htmlspecialchars($existing['remarks'] ?? '') : ''; ?>"
                                                                   placeholder="Optionnel"
                                                                   <?php echo $instance['is_locked'] ? 'disabled' : ''; ?>>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <?php if (empty($teams)): ?>
                                        <div class="text-center py-5">
                                            <i class="bi bi-exclamation-triangle text-warning" style="font-size: 48px;"></i>
                                            <p class="text-muted mt-3 mb-0">
                                                Aucune équipe définie pour cette classe.<br>
                                                <a href="index.php?page=teams&class_id=<?php echo $instance['class_id']; ?>">
                                                    Créer des équipes
                                                </a>
                                            </p>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover table-borderless mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th class="text-muted" style="width: 60px;">#</th>
                                                        <th>Équipe</th>
                                                        <th>Membres</th>
                                                        <th style="width: 160px;">Note /<?php echo $current_phase['points']; ?></th>
                                                        <th>Remarques</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($teams as $index => $t): 
                                                        $existing = $existing_notes[$t['id']] ?? null;
                                                        $team->id = $t['id'];
                                                        $members_stmt = $team->getMembers();
                                                        $members = $members_stmt->fetchAll(PDO::FETCH_ASSOC);
                                                    ?>
                                                        <tr <?php echo !$existing ? 'class="table-warning" style="background-color: rgba(255, 193, 7, 0.1);"' : ''; ?>>
                                                            <td class="text-muted"><?php echo $index + 1; ?></td>
                                                            <td>
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <strong><?php echo htmlspecialchars($t['name']); ?></strong>
                                                                    <?php if (!$existing): ?>
                                                                        <span class="badge bg-warning text-dark" style="font-size: 10px;">Non noté</span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <small class="text-muted">
                                                                    <?php 
                                                                    $member_names = array_map(fn($m) => $m['nom'], $members);
                                                                    echo htmlspecialchars(implode(', ', $member_names));
                                                                    ?>
                                                                </small>
                                                            </td>
                                                            <td>
                                                                <input type="number" 
                                                                       name="team_notes[<?php echo $t['id']; ?>]" 
                                                                       class="form-control form-control-sm <?php echo !$existing ? 'border-warning' : ''; ?>"
                                                                       value="<?php echo $existing ? $existing['note'] : ''; ?>"
                                                                       placeholder="<?php echo !$existing ? 'Pas encore noté' : ''; ?>"
                                                                       min="0" max="<?php echo $current_phase['points']; ?>" 
                                                                       step="0.25"
                                                                       <?php echo $instance['is_locked'] ? 'disabled' : ''; ?>>
                                                            </td>
                                                            <td>
                                                                <input type="text" 
                                                                       name="team_remarks[<?php echo $t['id']; ?>]" 
                                                                       class="form-control form-control-sm"
                                                                       value="<?php echo $existing ? htmlspecialchars($existing['remarks'] ?? '') : ''; ?>"
                                                                       placeholder="Optionnel"
                                                                       <?php echo $instance['is_locked'] ? 'disabled' : ''; ?>>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="alert alert-info m-3 rounded-4" style="font-size: 13px;">
                                            <i class="bi bi-info-circle me-2"></i>
                                            Mode équipe : la note saisie est appliquée à tous les membres.
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer bg-white border-top d-flex justify-content-between align-items-center">
                                <small class="text-muted">Les modifications sont enregistrées par phase</small>
                                <?php if (!$instance['is_locked']): ?>
                                    <button type="submit" name="save_notes" class="btn btn-dark rounded-pill px-4">
                                        <i class="bi bi-check-lg me-2"></i>Enregistrer la phase
                                    </button>
                                <?php else: ?>
                                    <div class="alert alert-warning mb-0 rounded-4" style="font-size: 13px;">
                                        <i class="bi bi-lock me-2"></i>Contrôle verrouillé, déverrouillez-le pour modifier.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php include 'views/partials/footer.php'; ?>
