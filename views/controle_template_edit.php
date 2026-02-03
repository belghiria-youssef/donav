<?php
/**
 * Edit Controle Template
 * Define phases within a template (structure only, no actual grading)
 */

if (!isset($_SESSION['teacher_id'])) {
    header('Location: index.php?page=login');
    exit;
}

require_once 'classes/Controle.php';

$database = new Database();
$db = $database->getConnection();

$controle = new Controle($db);

$template_id = intval($_GET['id'] ?? 0);

if ($template_id <= 0) {
    header('Location: index.php?page=controle_templates');
    exit;
}

$template = $controle->getTemplateById($template_id);

if (!$template) {
    header('Location: index.php?page=controle_templates');
    exit;
}

// Verify ownership
if ($template['created_by'] != $_SESSION['teacher_id']) {
    header('Location: index.php?page=controle_templates');
    exit;
}

$year_levels = $controle->getYearLevels($_SESSION['teacher_id']);
$message = '';
$error = '';

// Build year level lookup
$year_lookup = [];
foreach ($year_levels as $yl) {
    $year_lookup[$yl['id']] = $yl['name'];
}

// Handle duplicate template
if (isset($_GET['duplicate']) && $_GET['duplicate'] == 1) {
    $new_title = $template['title'] . ' (copie)';
    $new_id = $controle->createTemplate($new_title, $template['year_level_id'], $template['total_points'], $template['description'], $_SESSION['teacher_id']);
    
    if ($new_id) {
        // Copy phases
        $phases = $controle->getPhases($template_id);
        foreach ($phases as $phase) {
            $controle->addPhase($new_id, $phase['title'], $phase['points'], $phase['grading_mode']);
        }
        header("Location: index.php?page=controle_template_edit&id=" . $new_id);
        exit;
    }
}

// Handle adding a phase
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_phase'])) {
    $title = trim($_POST['phase_title'] ?? '');
    $points = floatval($_POST['points'] ?? 0);
    $grading_mode = $_POST['grading_mode'] ?? 'individual';
    
    if (!empty($title) && $points > 0) {
        $phase_id = $controle->addPhase($template_id, $title, $points, $grading_mode);
        if ($phase_id) {
            $message = "Phase ajoutée avec succès!";
        } else {
            $error = "Erreur lors de l'ajout de la phase.";
        }
    } else {
        $error = "Veuillez remplir tous les champs correctement.";
    }
}

// Handle updating a phase
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_phase'])) {
    $phase_id = intval($_POST['phase_id'] ?? 0);
    $title = trim($_POST['phase_title'] ?? '');
    $points = floatval($_POST['points'] ?? 0);
    $grading_mode = $_POST['grading_mode'] ?? 'individual';
    
    if ($controle->updatePhase($phase_id, $title, $points, $grading_mode)) {
        $message = "Phase mise à jour!";
    } else {
        $error = "Erreur lors de la mise à jour.";
    }
}

// Handle deleting a phase
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_phase'])) {
    $phase_id = intval($_POST['phase_id'] ?? 0);
    if ($controle->deletePhase($phase_id)) {
        $message = "Phase supprimée.";
    } else {
        $error = "Erreur lors de la suppression.";
    }
}

// Handle updating template details
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_template'])) {
    $title = trim($_POST['title'] ?? $template['title']);
    $year_level_id = intval($_POST['year_level_id'] ?? $template['year_level_id']);
    $total_points = floatval($_POST['total_points'] ?? $template['total_points']);
    $description = trim($_POST['description'] ?? '');
    
    if ($controle->updateTemplate($template_id, $title, $year_level_id, $total_points, $description)) {
        $message = "Modèle mis à jour!";
        $template = $controle->getTemplateById($template_id); // Refresh
    } else {
        $error = "Erreur lors de la mise à jour.";
    }
}

// Refresh phases
$phases = $controle->getPhases($template_id);
$total_phase_points = array_sum(array_column($phases, 'points'));
$is_valid = abs($total_phase_points - $template['total_points']) < 0.01;

include 'views/partials/header.php';
include 'views/partials/sidebar.php';
?>

<main class="main-content" style="margin-left: 212px; padding-top: 68px; min-height: 100vh; background: #f9f9fa;">
    <div class="container-fluid" style="padding: 28px;">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb" style="font-size: 14px;">
                <li class="breadcrumb-item"><a href="index.php?page=controle_templates" class="text-decoration-none">Modèles</a></li>
                <li class="breadcrumb-item active"><?php echo htmlspecialchars($template['title']); ?></li>
            </ol>
        </nav>

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1" style="font-weight: 600;">
                    <?php echo htmlspecialchars($template['title']); ?>
                </h4>
                <p class="text-muted mb-0" style="font-size: 14px;">
                    <span class="badge bg-secondary me-2"><?php echo htmlspecialchars($template['year_level_name'] ?? 'Non défini'); ?></span>
                    Note sur <?php echo $template['total_points']; ?>
                </p>
            </div>
            <a href="index.php?page=controle_templates" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>Retour
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

        <!-- Validation Status -->
        <div class="alert <?php echo $is_valid ? 'alert-success' : 'alert-warning'; ?> d-flex align-items-center mb-4">
            <i class="bi bi-<?php echo $is_valid ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
            <div>
                <strong>Total des points: <?php echo $total_phase_points; ?>/<?php echo $template['total_points']; ?></strong>
                <?php if (!$is_valid): ?>
                    <br><small>La somme des points des phases doit égaler le total du modèle (<?php echo $template['total_points']; ?> pts).</small>
                <?php else: ?>
                    <br><small>Ce modèle est prêt à être utilisé pour la notation.</small>
                <?php endif; ?>
            </div>
        </div>

        <div class="row g-4">
            <!-- Template Details -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm" style="border-radius: 20px;">
                    <div class="card-header bg-transparent">
                        <h6 class="mb-0" style="font-weight: 600;">Détails du Modèle</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Titre</label>
                                <input type="text" name="title" class="form-control" 
                                       value="<?php echo htmlspecialchars($template['title']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Niveau d'année</label>
                                <select name="year_level_id" class="form-select" required>
                                    <?php foreach ($year_levels as $yl): ?>
                                        <option value="<?php echo $yl['id']; ?>" <?php echo $template['year_level_id'] == $yl['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($yl['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Note maximale</label>
                                <input type="number" name="total_points" class="form-control" 
                                       value="<?php echo $template['total_points']; ?>" 
                                       min="1" max="100" step="0.5" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="2"><?php echo htmlspecialchars($template['description'] ?? ''); ?></textarea>
                            </div>
                            <button type="submit" name="update_template" class="btn btn-dark w-100">
                                <i class="bi bi-check-lg me-2"></i>Enregistrer
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Add Phase Card -->
                <div class="card border-0 shadow-sm mt-4" style="border-radius: 20px;">
                    <div class="card-header bg-transparent">
                        <h6 class="mb-0" style="font-weight: 600;">Ajouter une Phase</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Titre de la phase</label>
                                <input type="text" name="phase_title" class="form-control" 
                                       placeholder="Ex: Rapport écrit" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Points</label>
                                <input type="number" name="points" class="form-control" 
                                       min="0.5" max="<?php echo $template['total_points']; ?>" step="0.5" value="5" required>
                                <small class="text-muted">Nombre de points que vaut cette phase</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Mode de notation</label>
                                <select name="grading_mode" class="form-select" required>
                                    <option value="individual">Individuel (par élève)</option>
                                    <option value="team">Par équipe (note commune)</option>
                                </select>
                                <small class="text-muted">
                                    <i class="bi bi-info-circle"></i> 
                                    Mode équipe: tous les membres reçoivent la même note
                                </small>
                            </div>
                            <button type="submit" name="add_phase" class="btn btn-outline-dark w-100">
                                <i class="bi bi-plus-lg me-2"></i>Ajouter la Phase
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Phases List -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm" style="border-radius: 20px;">
                    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                        <h6 class="mb-0" style="font-weight: 600;">
                            Phases du Contrôle
                            <span class="badge bg-secondary ms-2"><?php echo count($phases); ?></span>
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($phases)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-list-task" style="font-size: 48px; color: rgba(0,0,0,0.2);"></i>
                                <p class="text-muted mt-3">Aucune phase définie.<br>Ajoutez des phases pour structurer votre contrôle.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 40px;">#</th>
                                            <th>Phase</th>
                                            <th style="width: 120px;">Points</th>
                                            <th style="width: 130px;">Mode</th>
                                            <th style="width: 100px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($phases as $index => $phase): ?>
                                            <tr>
                                                <td class="text-muted"><?php echo $index + 1; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($phase['title']); ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo $phase['points']; ?> pts</span>
                                                </td>
                                                <td>
                                                    <?php if ($phase['grading_mode'] === 'team'): ?>
                                                        <span class="badge bg-info text-dark">
                                                            <i class="bi bi-people"></i> Équipe
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">
                                                            <i class="bi bi-person"></i> Individuel
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" 
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
                                                                    <label class="form-label">Titre de la phase</label>
                                                                    <input type="text" name="phase_title" class="form-control" 
                                                                           value="<?php echo htmlspecialchars($phase['title']); ?>" required>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label">Points</label>
                                                                    <input type="number" name="points" class="form-control" 
                                                                           value="<?php echo $phase['points']; ?>" 
                                                                           min="0.5" max="<?php echo $template['total_points']; ?>" step="0.5" required>
                                                                    <small class="text-muted">Nombre de points que vaut cette phase</small>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label">Mode de notation</label>
                                                                    <select name="grading_mode" class="form-select" required>
                                                                        <option value="individual" <?php echo $phase['grading_mode'] === 'individual' ? 'selected' : ''; ?>>
                                                                            Individuel (par élève)
                                                                        </option>
                                                                        <option value="team" <?php echo $phase['grading_mode'] === 'team' ? 'selected' : ''; ?>>
                                                                            Par équipe (note commune)
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
                                    <tfoot class="table-light">
                                        <tr>
                                            <td></td>
                                            <td><strong>Total</strong></td>
                                            <td>
                                                <strong class="<?php echo $is_valid ? 'text-success' : 'text-danger'; ?>">
                                                    <?php echo $total_phase_points; ?>/<?php echo $template['total_points']; ?> pts
                                                </strong>
                                            </td>
                                            <td colspan="2">
                                                <?php if ($is_valid): ?>
                                                    <span class="text-success"><i class="bi bi-check-circle"></i> Valide</span>
                                                <?php else: ?>
                                                    <span class="text-danger"><i class="bi bi-x-circle"></i> Doit = <?php echo $template['total_points']; ?> pts</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Preview -->
                <?php if (!empty($phases) && $is_valid): ?>
                    <div class="card border-0 shadow-sm mt-4" style="border-radius: 20px;">
                        <div class="card-header bg-transparent">
                            <h6 class="mb-0" style="font-weight: 600;">
                                <i class="bi bi-eye me-2"></i>Aperçu du calcul
                            </h6>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">Exemple avec des notes maximales:</p>
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Phase</th>
                                        <th>Note obtenue</th>
                                        <th>Sur</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $example_total = 0;
                                    foreach ($phases as $phase): 
                                        $example_total += $phase['points'];
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($phase['title']); ?></td>
                                            <td><?php echo $phase['points']; ?> pts</td>
                                            <td><?php echo $phase['points']; ?> pts</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-success">
                                        <td><strong>Note finale</strong></td>
                                        <td><strong><?php echo round($example_total, 2); ?> pts</strong></td>
                                        <td><strong><?php echo $template['total_points']; ?> pts</strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php include 'views/partials/footer.php'; ?>
