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

// Handle adding a phase
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_phase'])) {
    if ($controle->is_locked) {
        $error = "Ce contrôle est verrouillé.";
    } else {
        $title = trim($_POST['phase_title'] ?? '');
        $percentage = floatval($_POST['percentage'] ?? 0);
        $max_points = floatval($_POST['max_points'] ?? 20);
        $grading_mode = $_POST['grading_mode'] ?? 'individual';
        
        if (!empty($title) && $percentage > 0 && $percentage <= 100) {
            $phase_id = $controle->addPhase($title, $percentage, $max_points, $grading_mode);
            if ($phase_id) {
                $message = "Phase ajoutée avec succès!";
            } else {
                $error = "Erreur lors de l'ajout de la phase.";
            }
        } else {
            $error = "Veuillez remplir tous les champs correctement.";
        }
    }
}

// Handle updating a phase
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_phase'])) {
    if ($controle->is_locked) {
        $error = "Ce contrôle est verrouillé.";
    } else {
        $phase_id = intval($_POST['phase_id'] ?? 0);
        $title = trim($_POST['phase_title'] ?? '');
        $percentage = floatval($_POST['percentage'] ?? 0);
        $max_points = floatval($_POST['max_points'] ?? 20);
        $grading_mode = $_POST['grading_mode'] ?? 'individual';
        
        if ($controle->updatePhase($phase_id, $title, $percentage, $max_points, $grading_mode)) {
            $message = "Phase mise à jour!";
        } else {
            $error = "Erreur lors de la mise à jour.";
        }
    }
}

// Handle deleting a phase
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_phase'])) {
    if ($controle->is_locked) {
        $error = "Ce contrôle est verrouillé.";
    } else {
        $phase_id = intval($_POST['phase_id'] ?? 0);
        if ($controle->deletePhase($phase_id)) {
            $message = "Phase supprimée.";
        } else {
            $error = "Erreur lors de la suppression.";
        }
    }
}

// Handle updating controle details
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_controle'])) {
    if ($controle->is_locked) {
        $error = "Ce contrôle est verrouillé.";
    } else {
        $controle->title = trim($_POST['title'] ?? $controle->title);
        $controle->total_points = floatval($_POST['total_points'] ?? $controle->total_points);
        
        if ($controle->update()) {
            $message = "Contrôle mis à jour!";
        } else {
            $error = "Erreur lors de la mise à jour.";
        }
    }
}

// Refresh phases
$phases = $controle->getPhases();
$total_percentage = array_sum(array_column($phases, 'percentage'));
$is_valid = abs($total_percentage - 100) < 0.01;

// Get teams for this class (for team mode phases)
$teams_stmt = $team->getByClass($controle->class_id);
$teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);

include 'views/partials/header.php';
include 'views/partials/sidebar.php';
?>

<main class="main-content" style="margin-left: 212px; padding-top: 68px; min-height: 100vh; background: #fafafa;">
    <div class="container-fluid p-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb" style="font-size: 14px;">
                <li class="breadcrumb-item"><a href="index.php?page=controles" class="text-decoration-none">Contrôles</a></li>
                <li class="breadcrumb-item active"><?php echo htmlspecialchars($controle->title); ?></li>
            </ol>
        </nav>

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1" style="font-weight: 600;">
                    <?php echo htmlspecialchars($controle->title); ?>
                    <?php if ($controle->is_locked): ?>
                        <span class="badge bg-secondary ms-2"><i class="bi bi-lock"></i> Verrouillé</span>
                    <?php endif; ?>
                </h4>
                <p class="text-muted mb-0" style="font-size: 14px;">
                    <?php echo htmlspecialchars($classroom->nom); ?> • Note sur <?php echo $controle->total_points; ?>
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="index.php?page=controle_notes&id=<?php echo $controle_id; ?>" class="btn btn-dark">
                    <i class="bi bi-journal-check me-2"></i>Saisir les Notes
                </a>
            </div>
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

        <!-- Validation Status -->
        <div class="alert <?php echo $is_valid ? 'alert-success' : 'alert-warning'; ?> d-flex align-items-center mb-4">
            <i class="bi bi-<?php echo $is_valid ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
            <div>
                <strong>Total des pourcentages: <?php echo $total_percentage; ?>%</strong>
                <?php if (!$is_valid): ?>
                    <br><small>Les pourcentages doivent totaliser 100% pour calculer les notes finales.</small>
                <?php endif; ?>
            </div>
        </div>

        <div class="row g-4">
            <!-- Controle Details -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-transparent">
                        <h6 class="mb-0" style="font-weight: 600;">Détails du Contrôle</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Titre</label>
                                <input type="text" name="title" class="form-control" 
                                       value="<?php echo htmlspecialchars($controle->title); ?>" 
                                       <?php echo $controle->is_locked ? 'disabled' : ''; ?> required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Note maximale</label>
                                <input type="number" name="total_points" class="form-control" 
                                       value="<?php echo $controle->total_points; ?>" 
                                       min="1" max="100" step="0.5" 
                                       <?php echo $controle->is_locked ? 'disabled' : ''; ?> required>
                            </div>
                            <?php if (!$controle->is_locked): ?>
                                <button type="submit" name="update_controle" class="btn btn-dark w-100">
                                    Mettre à jour
                                </button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Add Phase Form -->
                <?php if (!$controle->is_locked): ?>
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-header bg-transparent">
                        <h6 class="mb-0" style="font-weight: 600;">Ajouter une Phase</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Titre de la phase</label>
                                <input type="text" name="phase_title" class="form-control" required 
                                       placeholder="Ex: Quiz, Projet, Présentation">
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label">Pourcentage (%)</label>
                                    <input type="number" name="percentage" class="form-control" 
                                           min="1" max="100" step="0.5" required placeholder="30">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Note max</label>
                                    <input type="number" name="max_points" class="form-control" 
                                           value="20" min="1" max="100" step="0.5" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Mode de notation</label>
                                <select name="grading_mode" class="form-select" required>
                                    <option value="individual">Individuel</option>
                                    <option value="team" <?php echo empty($teams) ? 'disabled' : ''; ?>>
                                        Équipe <?php echo empty($teams) ? '(aucune équipe)' : ''; ?>
                                    </option>
                                </select>
                                <small class="text-muted">
                                    Individuel: chaque élève a sa note. Équipe: la note est partagée par tous les membres.
                                </small>
                            </div>
                            <button type="submit" name="add_phase" class="btn btn-outline-dark w-100">
                                <i class="bi bi-plus-lg me-2"></i>Ajouter
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Phases List -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                        <h6 class="mb-0" style="font-weight: 600;">Phases du Contrôle</h6>
                        <span class="badge bg-dark"><?php echo count($phases); ?> phase(s)</span>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($phases)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-layers" style="font-size: 48px; color: rgba(0,0,0,0.2);"></i>
                                <p class="text-muted mt-3">Aucune phase définie. Ajoutez des phases pour structurer votre contrôle.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead style="background: rgba(0,0,0,0.02);">
                                        <tr>
                                            <th style="font-weight: 600;">Phase</th>
                                            <th style="font-weight: 600;" class="text-center">%</th>
                                            <th style="font-weight: 600;" class="text-center">Note max</th>
                                            <th style="font-weight: 600;" class="text-center">Mode</th>
                                            <th style="font-weight: 600;" class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($phases as $phase): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($phase['title']); ?></strong>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-primary"><?php echo $phase['percentage']; ?>%</span>
                                                </td>
                                                <td class="text-center"><?php echo $phase['max_points']; ?></td>
                                                <td class="text-center">
                                                    <?php if ($phase['grading_mode'] === 'team'): ?>
                                                        <span class="badge bg-info">
                                                            <i class="bi bi-people"></i> Équipe
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">
                                                            <i class="bi bi-person"></i> Individuel
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">
                                                    <?php if (!$controle->is_locked): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-dark" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#editPhaseModal<?php echo $phase['id']; ?>">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <form method="POST" class="d-inline" 
                                                              onsubmit="return confirm('Supprimer cette phase?');">
                                                            <input type="hidden" name="phase_id" value="<?php echo $phase['id']; ?>">
                                                            <button type="submit" name="delete_phase" class="btn btn-sm btn-outline-danger">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>

                                            <!-- Edit Phase Modal -->
                                            <div class="modal fade" id="editPhaseModal<?php echo $phase['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Modifier la Phase</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form method="POST">
                                                            <div class="modal-body">
                                                                <input type="hidden" name="phase_id" value="<?php echo $phase['id']; ?>">
                                                                <div class="mb-3">
                                                                    <label class="form-label">Titre</label>
                                                                    <input type="text" name="phase_title" class="form-control" 
                                                                           value="<?php echo htmlspecialchars($phase['title']); ?>" required>
                                                                </div>
                                                                <div class="row g-2 mb-3">
                                                                    <div class="col-6">
                                                                        <label class="form-label">Pourcentage (%)</label>
                                                                        <input type="number" name="percentage" class="form-control" 
                                                                               value="<?php echo $phase['percentage']; ?>" 
                                                                               min="1" max="100" step="0.5" required>
                                                                    </div>
                                                                    <div class="col-6">
                                                                        <label class="form-label">Note max</label>
                                                                        <input type="number" name="max_points" class="form-control" 
                                                                               value="<?php echo $phase['max_points']; ?>" 
                                                                               min="1" max="100" step="0.5" required>
                                                                    </div>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label">Mode de notation</label>
                                                                    <select name="grading_mode" class="form-select" required>
                                                                        <option value="individual" <?php echo $phase['grading_mode'] === 'individual' ? 'selected' : ''; ?>>
                                                                            Individuel
                                                                        </option>
                                                                        <option value="team" <?php echo $phase['grading_mode'] === 'team' ? 'selected' : ''; ?> 
                                                                                <?php echo empty($teams) ? 'disabled' : ''; ?>>
                                                                            Équipe
                                                                        </option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                                <button type="submit" name="update_phase" class="btn btn-dark">Enregistrer</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot style="background: rgba(0,0,0,0.02);">
                                        <tr>
                                            <td><strong>Total</strong></td>
                                            <td class="text-center">
                                                <strong class="<?php echo $is_valid ? 'text-success' : 'text-danger'; ?>">
                                                    <?php echo $total_percentage; ?>%
                                                </strong>
                                            </td>
                                            <td colspan="3"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Visual Breakdown -->
                <?php if (!empty($phases)): ?>
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-header bg-transparent">
                        <h6 class="mb-0" style="font-weight: 600;">Répartition visuelle</h6>
                    </div>
                    <div class="card-body">
                        <div class="progress" style="height: 30px;">
                            <?php 
                            $colors = ['bg-primary', 'bg-success', 'bg-info', 'bg-warning', 'bg-danger', 'bg-secondary'];
                            $i = 0;
                            foreach ($phases as $phase): 
                                $color = $colors[$i % count($colors)];
                            ?>
                                <div class="progress-bar <?php echo $color; ?>" 
                                     role="progressbar" 
                                     style="width: <?php echo $phase['percentage']; ?>%"
                                     title="<?php echo htmlspecialchars($phase['title']); ?>: <?php echo $phase['percentage']; ?>%">
                                    <?php echo $phase['percentage'] >= 10 ? htmlspecialchars($phase['title']) : ''; ?>
                                </div>
                            <?php $i++; endforeach; ?>
                        </div>
                        <div class="d-flex flex-wrap gap-3 mt-3">
                            <?php 
                            $i = 0;
                            foreach ($phases as $phase): 
                                $color = str_replace('bg-', '', $colors[$i % count($colors)]);
                            ?>
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-<?php echo $color; ?> me-2">&nbsp;</span>
                                    <small><?php echo htmlspecialchars($phase['title']); ?> (<?php echo $phase['percentage']; ?>%)</small>
                                </div>
                            <?php $i++; endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php include 'views/partials/footer.php'; ?>
