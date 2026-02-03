<?php
/**
 * Fill Points - Controle Instances
 * This tab is for applying templates to classes and filling in actual grades
 */

if (!isset($_SESSION['teacher_id'])) {
    header('Location: index.php?page=login');
    exit;
}

require_once 'classes/ClassRoom.php';
require_once 'classes/Controle.php';

$database = new Database();
$db = $database->getConnection();

$classroom = new ClassRoom($db);
$controle = new Controle($db);

$message = '';
$error = '';

$year_levels = $controle->getYearLevels($_SESSION['teacher_id']);

// Build year level lookup
$year_lookup = [];
foreach ($year_levels as $yl) {
    $year_lookup[$yl['id']] = $yl['name'];
}

// Get teacher's classes
$classes_stmt = $classroom->getByTeacher($_SESSION['teacher_id']);
$classes = $classes_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle creating a new instance (apply template to class)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_instance'])) {
    $template_id = intval($_POST['template_id'] ?? 0);
    $class_id = intval($_POST['class_id'] ?? 0);
    $session_name = trim($_POST['session_name'] ?? '');
    
    if ($template_id > 0 && $class_id > 0) {
        // Verify class belongs to teacher
        if ($classroom->getById($class_id) && $classroom->enseignant_id == $_SESSION['teacher_id']) {
            $instance_id = $controle->createInstance($template_id, $class_id, $_SESSION['teacher_id'], $session_name);
            
            if ($instance_id) {
                $message = "Contrôle créé avec succès!";
                // Redirect to fill notes
                header("Location: index.php?page=controle_fill_notes&id=" . $instance_id);
                exit;
            } else {
                $error = "Erreur: Le niveau de la classe doit correspondre au niveau du modèle.";
            }
        } else {
            $error = "Classe non autorisée.";
        }
    } else {
        $error = "Veuillez sélectionner un modèle et une classe.";
    }
}

// Handle deleting an instance
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_instance'])) {
    $instance_id = intval($_POST['instance_id'] ?? 0);
    
    if ($instance_id > 0) {
        $instance = $controle->getInstanceById($instance_id);
        if ($instance && $instance['created_by'] == $_SESSION['teacher_id']) {
            if (!$instance['is_locked']) {
                if ($controle->deleteInstance($instance_id)) {
                    $message = "Contrôle supprimé.";
                } else {
                    $error = "Erreur lors de la suppression.";
                }
            } else {
                $error = "Impossible de supprimer un contrôle verrouillé.";
            }
        }
    }
}

// Handle lock/unlock
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_lock'])) {
    $instance_id = intval($_POST['instance_id'] ?? 0);
    
    if ($instance_id > 0) {
        $instance = $controle->getInstanceById($instance_id);
        if ($instance && $instance['created_by'] == $_SESSION['teacher_id']) {
            if ($instance['is_locked']) {
                $controle->unlockInstance($instance_id);
                $message = "Contrôle déverrouillé.";
            } else {
                $controle->lockInstance($instance_id);
                $message = "Contrôle verrouillé.";
            }
        }
    }
}

// Filter by class
$filter_class = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

// Get instances
if ($filter_class > 0) {
    $instances = $controle->getInstancesByClass($filter_class);
} else {
    $instances = $controle->getInstancesByTeacher($_SESSION['teacher_id']);
}

include 'views/partials/header.php';
include 'views/partials/sidebar.php';
?>

<main class="main-content" style="margin-left: 212px; padding-top: 68px; min-height: 100vh; background: #f9f9fa;">
    <div class="container-fluid" style="padding: 28px;">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1" style="font-weight: 600;">
                    <i class="bi bi-journal-check me-2"></i>Saisie des Notes
                </h4>
                <p class="text-muted mb-0" style="font-size: 14px;">
                    Appliquez vos modèles de contrôle à une classe et saisissez les notes
                </p>
            </div>
            <button type="button" class="btn btn-dark d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#createInstanceModal">
                <i class="bi bi-plus-lg"></i>
                Nouveau Contrôle
            </button>
        </div>

        <!-- Filter by Class -->
        <div class="mb-4">
            <div class="btn-group" role="group">
                <a href="index.php?page=controle_fill_points" 
                   class="btn <?php echo $filter_class == 0 ? 'btn-dark' : 'btn-outline-dark'; ?>">
                    Tous
                </a>
                <?php foreach ($classes as $cls): ?>
                    <a href="index.php?page=controle_fill_points&class_id=<?php echo $cls['id']; ?>" 
                       class="btn <?php echo $filter_class == $cls['id'] ? 'btn-dark' : 'btn-outline-dark'; ?>">
                        <?php echo htmlspecialchars($cls['nom']); ?>
                    </a>
                <?php endforeach; ?>
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

        <!-- Instances List -->
        <?php if (empty($instances)): ?>
            <div class="card border-0 shadow-sm" style="border-radius: 20px;">
                <div class="card-body text-center py-5">
                    <div class="mb-3">
                        <i class="bi bi-journal-check" style="font-size: 48px; color: rgba(0,0,0,0.2);"></i>
                    </div>
                    <h5 style="font-weight: 600;">Aucun contrôle en cours</h5>
                    <p class="text-muted">Créez un contrôle en appliquant un modèle à une classe.</p>
                    <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#createInstanceModal">
                        <i class="bi bi-plus-lg me-2"></i>Créer un contrôle
                    </button>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($instances as $inst): 
                    $progress = $controle->getGradingProgress($inst['id']);
                ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card border-0 shadow-sm h-100" style="border-radius: 20px;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h5 class="card-title mb-1" style="font-weight: 600;">
                                            <?php echo htmlspecialchars($inst['template_title']); ?>
                                        </h5>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($inst['class_name']); ?>
                                            <?php if ($inst['session_name']): ?>
                                                • <?php echo htmlspecialchars($inst['session_name']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <?php if ($inst['is_locked']): ?>
                                        <span class="badge bg-secondary">
                                            <i class="bi bi-lock"></i> Verrouillé
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <!-- Progress -->
                                <?php if ($progress): ?>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1" style="font-size: 12px;">
                                            <span>Progression</span>
                                            <span><?php echo $progress['overall_percentage']; ?>%</span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar <?php echo $progress['overall_percentage'] == 100 ? 'bg-success' : 'bg-primary'; ?>" 
                                                 style="width: <?php echo $progress['overall_percentage']; ?>%"></div>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo $progress['total_filled']; ?>/<?php echo $progress['total_expected']; ?> notes saisies
                                        </small>
                                    </div>
                                <?php endif; ?>

                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <div class="p-2 rounded" style="background: rgba(0,0,0,0.04);">
                                            <small class="text-muted d-block">Phases</small>
                                            <strong><?php echo $inst['phase_count'] ?? 0; ?></strong>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-2 rounded" style="background: rgba(0,0,0,0.04);">
                                            <small class="text-muted d-block">Note sur</small>
                                            <strong><?php echo $inst['total_points']; ?></strong>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex gap-2">
                                    <a href="index.php?page=controle_fill_notes&id=<?php echo $inst['id']; ?>" 
                                       class="btn btn-dark btn-sm flex-grow-1">
                                        <i class="bi bi-pencil-square"></i> Saisir Notes
                                    </a>
                                    <a href="index.php?page=controle_instance_results&id=<?php echo $inst['id']; ?>" 
                                       class="btn btn-outline-dark btn-sm flex-grow-1">
                                        <i class="bi bi-graph-up"></i> Résultats
                                    </a>
                                    <div class="dropdown">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="dropdown">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="instance_id" value="<?php echo $inst['id']; ?>">
                                                    <button type="submit" name="toggle_lock" class="dropdown-item">
                                                        <i class="bi bi-<?php echo $inst['is_locked'] ? 'unlock' : 'lock'; ?> me-2"></i>
                                                        <?php echo $inst['is_locked'] ? 'Déverrouiller' : 'Verrouiller'; ?>
                                                    </button>
                                                </form>
                                            </li>
                                            <?php if (!$inst['is_locked']): ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form method="POST" class="d-inline" 
                                                          onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce contrôle?');">
                                                        <input type="hidden" name="instance_id" value="<?php echo $inst['id']; ?>">
                                                        <button type="submit" name="delete_instance" class="dropdown-item text-danger">
                                                            <i class="bi bi-trash me-2"></i>Supprimer
                                                        </button>
                                                    </form>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent border-0 pt-0">
                                <small class="text-muted">
                                    Créé le <?php echo date('d/m/Y', strtotime($inst['created_at'])); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Create Instance Modal -->
<div class="modal fade" id="createInstanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nouveau Contrôle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="alert alert-info" style="font-size: 13px;">
                        <i class="bi bi-info-circle me-2"></i>
                        Sélectionnez une classe puis un modèle de contrôle compatible avec le niveau de cette classe.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Classe <span class="text-danger">*</span></label>
                        <select name="class_id" id="selectClass" class="form-select" required>
                            <option value="">Sélectionner une classe</option>
                            <?php foreach ($classes as $cls): ?>
                                <option value="<?php echo $cls['id']; ?>" 
                                        data-year-id="<?php echo $cls['year_level_id'] ?? ''; ?>"
                                        <?php echo $filter_class == $cls['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cls['nom']); ?>
                                    <?php if ($cls['year_level_id'] && isset($year_lookup[$cls['year_level_id']])): ?>
                                        (<?php echo htmlspecialchars($year_lookup[$cls['year_level_id']]); ?>)
                                    <?php else: ?>
                                        (Niveau non défini)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Modèle de contrôle <span class="text-danger">*</span></label>
                        <select name="template_id" id="selectTemplate" class="form-select" required>
                            <option value="">Sélectionner d'abord une classe</option>
                        </select>
                        <small class="text-muted" id="templateHelp">
                            Les modèles disponibles dépendent du niveau de la classe
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nom de session (optionnel)</label>
                        <input type="text" name="session_name" class="form-control" 
                               placeholder="Ex: Session Normale 2026, Rattrapage, etc.">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="create_instance" class="btn btn-dark">Créer le contrôle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Templates data for dynamic loading
const templatesByYearId = <?php 
    $all_templates = $controle->getAllTemplates();
    $templates_by_year_js = [];
    foreach ($all_templates as $t) {
        $year_id = $t['year_level_id'] ?? 0;
        if (!isset($templates_by_year_js[$year_id])) {
            $templates_by_year_js[$year_id] = [];
        }
        $templates_by_year_js[$year_id][] = [
            'id' => $t['id'],
            'title' => $t['title'],
            'total_points' => $t['total_points'],
            'total_phase_points' => $t['total_phase_points']
        ];
    }
    echo json_encode($templates_by_year_js);
?>;

document.getElementById('selectClass').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const yearLevelId = selectedOption.dataset.yearId || '';
    const templateSelect = document.getElementById('selectTemplate');
    
    // Clear existing options
    templateSelect.innerHTML = '<option value="">Sélectionner un modèle</option>';
    
    if (!yearLevelId) {
        templateSelect.innerHTML = '<option value="">Cette classe n\'a pas de niveau défini</option>';
        document.getElementById('templateHelp').textContent = 'Définissez d\'abord le niveau de cette classe';
        return;
    }
    
    // Add templates for this year level
    const templates = templatesByYearId[yearLevelId] || [];
    
    if (templates.length === 0) {
        templateSelect.innerHTML = '<option value="">Aucun modèle disponible pour ce niveau</option>';
        document.getElementById('templateHelp').textContent = 'Créez d\'abord un modèle pour ce niveau d\'année';
    } else {
        templates.forEach(t => {
            const option = document.createElement('option');
            option.value = t.id;
            option.textContent = t.title + ' (' + t.total_points + ' pts)';
            if (Math.abs((t.total_phase_points || 0) - t.total_points) > 0.01) {
                option.textContent += ' ⚠️ Incomplet';
                option.disabled = true;
            }
            templateSelect.appendChild(option);
        });
        document.getElementById('templateHelp').textContent = templates.length + ' modèle(s) disponible(s)';
    }
});

// Trigger change on page load if class is pre-selected
if (document.getElementById('selectClass').value) {
    document.getElementById('selectClass').dispatchEvent(new Event('change'));
}
</script>

<?php include 'views/partials/footer.php'; ?>
