<?php
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

$controle_id = intval($_GET['id'] ?? 0);

if ($controle_id <= 0 || !$controle->getById($controle_id)) {
    header('Location: index.php?page=controles');
    exit;
}

// Verify ownership
if ($controle->created_by != $_SESSION['teacher_id']) {
    header('Location: index.php?page=controles');
    exit;
}

$message = '';
$error = '';

// Get class info
$classroom->getById($controle->class_id);

// Get phases
$phases = $controle->getPhases();

// Get students
$students_stmt = $student->getByClass($controle->class_id);
$students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get teams with members
$teams_stmt = $team->getByClass($controle->class_id);
$teams = [];
while ($t = $teams_stmt->fetch(PDO::FETCH_ASSOC)) {
    $team->id = $t['id'];
    $members_stmt = $team->getMembers();
    $t['members'] = $members_stmt->fetchAll(PDO::FETCH_ASSOC);
    $teams[$t['id']] = $t;
}

// Get selected phase (default to first)
$selected_phase_id = intval($_GET['phase'] ?? ($phases[0]['id'] ?? 0));
$selected_phase = null;
foreach ($phases as $p) {
    if ($p['id'] == $selected_phase_id) {
        $selected_phase = $p;
        break;
    }
}

// Handle saving individual notes
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_individual_notes'])) {
    $phase_id = intval($_POST['phase_id'] ?? 0);
    $notes = $_POST['notes'] ?? [];
    
    $success_count = 0;
    foreach ($notes as $student_id => $note) {
        if ($note !== '' && is_numeric($note)) {
            if ($controle->saveIndividualNote($phase_id, $student_id, floatval($note))) {
                $success_count++;
            }
        }
    }
    
    // Recompute results
    $controle->computeAllResults();
    
    $message = "$success_count note(s) enregistrée(s).";
    
    // Refresh data
    $selected_phase_id = $phase_id;
    foreach ($phases as $p) {
        if ($p['id'] == $selected_phase_id) {
            $selected_phase = $p;
            break;
        }
    }
}

// Handle saving team notes
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_team_notes'])) {
    $phase_id = intval($_POST['phase_id'] ?? 0);
    $notes = $_POST['team_notes'] ?? [];
    
    $success_count = 0;
    foreach ($notes as $team_id => $note) {
        if ($note !== '' && is_numeric($note)) {
            if ($controle->saveTeamNote($phase_id, $team_id, floatval($note))) {
                $success_count++;
            }
        }
    }
    
    // Recompute results
    $controle->computeAllResults();
    
    $message = "$success_count note(s) d'équipe enregistrée(s).";
    
    // Refresh data
    $selected_phase_id = $phase_id;
    foreach ($phases as $p) {
        if ($p['id'] == $selected_phase_id) {
            $selected_phase = $p;
            break;
        }
    }
}

// Get existing notes for selected phase
$existing_notes = [];
if ($selected_phase) {
    if ($selected_phase['grading_mode'] === 'individual') {
        $notes_data = $controle->getIndividualNotes($selected_phase_id);
        foreach ($notes_data as $n) {
            $existing_notes[$n['student_id']] = $n['note'];
        }
    } else {
        $notes_data = $controle->getTeamNotes($selected_phase_id);
        foreach ($notes_data as $n) {
            $existing_notes[$n['team_id']] = $n['note'];
        }
    }
}

include 'views/partials/header.php';
include 'views/partials/sidebar.php';
?>

<main class="main-content" style="margin-left: 212px; padding-top: 68px; min-height: 100vh; background: #fafafa;">
    <div class="container-fluid p-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb" style="font-size: 14px;">
                <li class="breadcrumb-item"><a href="index.php?page=controles" class="text-decoration-none">Contrôles</a></li>
                <li class="breadcrumb-item"><a href="index.php?page=controle_edit&id=<?php echo $controle_id; ?>" class="text-decoration-none"><?php echo htmlspecialchars($controle->title); ?></a></li>
                <li class="breadcrumb-item active">Saisie des Notes</li>
            </ol>
        </nav>

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1" style="font-weight: 600;">Saisie des Notes</h4>
                <p class="text-muted mb-0" style="font-size: 14px;">
                    <?php echo htmlspecialchars($controle->title); ?> • <?php echo htmlspecialchars($classroom->nom); ?>
                </p>
            </div>
            <a href="index.php?page=controle_instance_results&id=<?php echo $controle_id; ?>" class="btn btn-dark">
                <i class="bi bi-graph-up me-2"></i>Voir les Résultats
            </a>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (empty($phases)): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Aucune phase n'est définie pour ce contrôle. 
                <a href="index.php?page=controle_edit&id=<?php echo $controle_id; ?>">Ajouter des phases</a>.
            </div>
        <?php else: ?>

        <!-- Phase Tabs -->
        <ul class="nav nav-tabs mb-4">
            <?php foreach ($phases as $phase): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $phase['id'] == $selected_phase_id ? 'active' : ''; ?>" 
                       href="?page=controle_notes&id=<?php echo $controle_id; ?>&phase=<?php echo $phase['id']; ?>">
                        <?php echo htmlspecialchars($phase['title']); ?>
                        <span class="badge bg-<?php echo $phase['grading_mode'] === 'team' ? 'info' : 'secondary'; ?> ms-2">
                            <?php echo $phase['percentage']; ?>%
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($selected_phase): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1" style="font-weight: 600;"><?php echo htmlspecialchars($selected_phase['title']); ?></h5>
                        <small class="text-muted">
                            Mode: 
                            <?php if ($selected_phase['grading_mode'] === 'team'): ?>
                                <span class="badge bg-info"><i class="bi bi-people"></i> Équipe</span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><i class="bi bi-person"></i> Individuel</span>
                            <?php endif; ?>
                            • Note max: <?php echo $selected_phase['max_points']; ?>
                        </small>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($selected_phase['grading_mode'] === 'individual'): ?>
                        <!-- Individual Notes Form -->
                        <form method="POST">
                            <input type="hidden" name="phase_id" value="<?php echo $selected_phase['id']; ?>">
                            
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead style="background: rgba(0,0,0,0.02);">
                                        <tr>
                                            <th style="font-weight: 600;">#</th>
                                            <th style="font-weight: 600;">Élève</th>
                                            <th style="font-weight: 600; width: 150px;">Note / <?php echo $selected_phase['max_points']; ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $i = 1; foreach ($students as $s): ?>
                                            <tr>
                                                <td><?php echo $i++; ?></td>
                                                <td><?php echo htmlspecialchars($s['nom']); ?></td>
                                                <td>
                                                    <input type="number" 
                                                           name="notes[<?php echo $s['id']; ?>]" 
                                                           class="form-control form-control-sm"
                                                           value="<?php echo $existing_notes[$s['id']] ?? ''; ?>"
                                                           min="0" 
                                                           max="<?php echo $selected_phase['max_points']; ?>" 
                                                           step="0.25"
                                                           placeholder="-">
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="d-flex justify-content-end gap-2 mt-3">
                                <button type="submit" name="save_individual_notes" class="btn btn-dark">
                                    <i class="bi bi-save me-2"></i>Enregistrer les Notes
                                </button>
                            </div>
                        </form>

                    <?php else: ?>
                        <!-- Team Notes Form -->
                        <?php if (empty($teams)): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                Aucune équipe n'existe pour cette classe. 
                                <a href="index.php?page=teams&class_id=<?php echo $controle->class_id; ?>">Créer des équipes</a>.
                            </div>
                        <?php else: ?>
                            <form method="POST">
                                <input type="hidden" name="phase_id" value="<?php echo $selected_phase['id']; ?>">
                                
                                <div class="row g-4">
                                    <?php foreach ($teams as $t): ?>
                                        <div class="col-md-6 col-lg-4">
                                            <div class="card h-100">
                                                <div class="card-header bg-transparent">
                                                    <h6 class="mb-0" style="font-weight: 600;">
                                                        <i class="bi bi-people me-2"></i><?php echo htmlspecialchars($t['name']); ?>
                                                    </h6>
                                                </div>
                                                <div class="card-body">
                                                    <div class="mb-3">
                                                        <label class="form-label">Note / <?php echo $selected_phase['max_points']; ?></label>
                                                        <input type="number" 
                                                               name="team_notes[<?php echo $t['id']; ?>]" 
                                                               class="form-control"
                                                               value="<?php echo $existing_notes[$t['id']] ?? ''; ?>"
                                                               min="0" 
                                                               max="<?php echo $selected_phase['max_points']; ?>" 
                                                               step="0.25"
                                                               placeholder="-">
                                                    </div>
                                                    <small class="text-muted">Membres:</small>
                                                    <ul class="list-unstyled mb-0 mt-1">
                                                        <?php 
                                                        $members = $t['members'] ?? [];
                                                        if (empty($members)): 
                                                        ?>
                                                            <li><small class="text-muted fst-italic">Aucun membre</small></li>
                                                        <?php else: ?>
                                                            <?php foreach ($members as $member): ?>
                                                                <li><small><i class="bi bi-person me-1"></i><?php echo htmlspecialchars($member['nom']); ?></small></li>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="d-flex justify-content-end gap-2 mt-4">
                                    <button type="submit" name="save_team_notes" class="btn btn-dark">
                                        <i class="bi bi-save me-2"></i>Enregistrer les Notes d'Équipe
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<script>
// Auto-tab to next input on Enter
document.querySelectorAll('input[type="number"]').forEach((input, index, inputs) => {
    input.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const nextInput = inputs[index + 1];
            if (nextInput) {
                nextInput.focus();
                nextInput.select();
            }
        }
    });
});
</script>

<?php include 'views/partials/footer.php'; ?>
