<?php
/**
 * Controle Templates Management
 * This tab is for defining the STRUCTURE of controles (phases, percentages, grading modes)
 * Templates are defined per year level, not per class
 */

if (!isset($_SESSION['teacher_id'])) {
    header('Location: index.php?page=login');
    exit;
}

require_once 'classes/Controle.php';

$database = new Database();
$db = $database->getConnection();

$controle = new Controle($db);

$message = '';
$error = '';

$year_levels = $controle->getYearLevels($_SESSION['teacher_id']);

// Handle creating a new template
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_template'])) {
    $title = trim($_POST['title'] ?? '');
    $year_level_id = intval($_POST['year_level_id'] ?? 0);
    $total_points = floatval($_POST['total_points'] ?? 20);
    $description = trim($_POST['description'] ?? '');
    
    if (!empty($title) && $year_level_id > 0) {
        $template_id = $controle->createTemplate($title, $year_level_id, $total_points, $description, $_SESSION['teacher_id']);
        
        if ($template_id) {
            $message = "Modèle de contrôle créé avec succès!";
            // Redirect to edit phases
            header("Location: index.php?page=controle_template_edit&id=" . $template_id);
            exit;
        } else {
            $error = "Erreur lors de la création du modèle.";
        }
    } else {
        $error = "Veuillez remplir tous les champs obligatoires (titre et niveau d'année).";
    }
}

// Handle deleting a template
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_template'])) {
    $template_id = intval($_POST['template_id'] ?? 0);
    
    if ($template_id > 0) {
        $template = $controle->getTemplateById($template_id);
        if ($template && $template['created_by'] == $_SESSION['teacher_id']) {
            if ($controle->deleteTemplate($template_id)) {
                $message = "Modèle supprimé avec succès.";
            } else {
                $error = "Impossible de supprimer: ce modèle est utilisé par des contrôles.";
            }
        } else {
            $error = "Action non autorisée.";
        }
    }
}

// Handle deactivating a template
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['deactivate_template'])) {
    $template_id = intval($_POST['template_id'] ?? 0);
    
    if ($template_id > 0) {
        $template = $controle->getTemplateById($template_id);
        if ($template && $template['created_by'] == $_SESSION['teacher_id']) {
            $controle->deactivateTemplate($template_id);
            $message = "Modèle désactivé.";
        }
    }
}

// Filter by year level
$filter_year_id = isset($_GET['year_id']) ? intval($_GET['year_id']) : 0;

// Get templates
if ($filter_year_id > 0) {
    $templates = $controle->getTemplatesByYearLevel($filter_year_id);
} else {
    $templates = $controle->getTemplatesByTeacher($_SESSION['teacher_id']);
}

// Group templates by year level for display
$templates_by_year = [];
foreach ($templates as $t) {
    $year_key = $t['year_level_id'] ?? 0;
    $templates_by_year[$year_key][] = $t;
}

// Build year level lookup
$year_lookup = [];
foreach ($year_levels as $yl) {
    $year_lookup[$yl['id']] = $yl['name'];
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
                    <i class="bi bi-file-earmark-ruled me-2"></i>Modèles de Contrôles
                </h4>
                <p class="text-muted mb-0" style="font-size: 14px;">
                    Définissez la structure de vos contrôles (phases, pourcentages, modes de notation)
                </p>
            </div>
            <button type="button" class="btn btn-dark d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#createTemplateModal">
                <i class="bi bi-plus-lg"></i>
                Nouveau Modèle
            </button>
        </div>

        <!-- Navigation Tabs -->
        <ul class="nav nav-pills mb-4">
            <li class="nav-item">
                <a class="nav-link <?php echo $filter_year_id == 0 ? 'active bg-dark' : 'text-dark'; ?>" 
                   href="index.php?page=controle_templates">
                    Tous
                </a>
            </li>
            <?php foreach ($year_levels as $yl): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $filter_year_id == $yl['id'] ? 'active bg-dark' : 'text-dark'; ?>" 
                       href="index.php?page=controle_templates&year_id=<?php echo $yl['id']; ?>">
                        <?php echo htmlspecialchars($yl['name']); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

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

        <!-- Info Card -->
        <div class="alert alert-info d-flex align-items-start mb-4">
            <i class="bi bi-info-circle me-3 mt-1"></i>
            <div>
                <strong>Comment ça marche?</strong>
                <ol class="mb-0 mt-2" style="font-size: 14px;">
                    <li>Créez un <strong>modèle</strong> pour chaque type de contrôle (par niveau d'année)</li>
                    <li>Définissez les <strong>phases</strong> avec leurs pourcentages et modes de notation</li>
                    <li>Dans l'onglet <strong>Saisie des Notes</strong>, appliquez le modèle à une classe pour noter les élèves</li>
                </ol>
            </div>
        </div>

        <!-- Templates List -->
        <?php if (empty($templates)): ?>
            <div class="card border-0 shadow-sm" style="border-radius: 20px;">
                <div class="card-body text-center py-5">
                    <div class="mb-3">
                        <i class="bi bi-file-earmark-ruled" style="font-size: 48px; color: rgba(0,0,0,0.2);"></i>
                    </div>
                    <h5 style="font-weight: 600;">Aucun modèle de contrôle</h5>
                    <p class="text-muted">Créez votre premier modèle pour définir la structure de vos évaluations.</p>
                    <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#createTemplateModal">
                        <i class="bi bi-plus-lg me-2"></i>Créer un modèle
                    </button>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($templates_by_year as $year_id => $year_templates): ?>
                <div class="mb-4">
                    <h5 class="mb-3 text-muted" style="font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">
                        <i class="bi bi-mortarboard me-2"></i><?php echo htmlspecialchars($year_lookup[$year_id] ?? "Non défini"); ?>
                    </h5>
                    
                    <div class="row g-4">
                        <?php foreach ($year_templates as $t): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card border-0 shadow-sm h-100" style="border-radius: 20px;">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <h5 class="card-title mb-1" style="font-weight: 600;">
                                                    <?php echo htmlspecialchars($t['title']); ?>
                                                </h5>
                                                <?php if ($t['description']): ?>
                                                    <small class="text-muted"><?php echo htmlspecialchars($t['description']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <span class="badge bg-light text-dark">
                                                <?php echo $t['total_points']; ?> pts
                                            </span>
                                        </div>

                                        <div class="row g-2 mb-3">
                                            <div class="col-6">
                                                <div class="p-2 rounded" style="background: rgba(0,0,0,0.04);">
                                                    <small class="text-muted d-block">Phases</small>
                                                    <strong><?php echo $t['phase_count'] ?? 0; ?></strong>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="p-2 rounded" style="background: rgba(0,0,0,0.04);">
                                                    <small class="text-muted d-block">Total %</small>
                                                    <strong class="<?php echo ($t['total_percentage'] ?? 0) == 100 ? 'text-success' : 'text-warning'; ?>">
                                                        <?php echo $t['total_percentage'] ?? 0; ?>%
                                                    </strong>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex gap-2">
                                            <a href="index.php?page=controle_template_edit&id=<?php echo $t['id']; ?>" 
                                               class="btn btn-outline-dark btn-sm flex-grow-1">
                                                <i class="bi bi-pencil"></i> Modifier
                                            </a>
                                            <div class="dropdown">
                                                <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="dropdown">
                                                    <i class="bi bi-three-dots-vertical"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li>
                                                        <a href="index.php?page=controle_template_edit&id=<?php echo $t['id']; ?>&duplicate=1" 
                                                           class="dropdown-item">
                                                            <i class="bi bi-copy me-2"></i>Dupliquer
                                                        </a>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <form method="POST" class="d-inline" 
                                                              onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce modèle?');">
                                                            <input type="hidden" name="template_id" value="<?php echo $t['id']; ?>">
                                                            <button type="submit" name="delete_template" class="dropdown-item text-danger">
                                                                <i class="bi bi-trash me-2"></i>Supprimer
                                                            </button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-footer bg-transparent border-0 pt-0">
                                        <small class="text-muted">
                                            Créé le <?php echo date('d/m/Y', strtotime($t['created_at'])); ?>
                                            par <?php echo htmlspecialchars($t['created_by_name']); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<!-- Create Template Modal -->
<div class="modal fade" id="createTemplateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nouveau Modèle de Contrôle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Titre du modèle <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required 
                               placeholder="Ex: Contrôle Continu - Développement Web">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Niveau d'année <span class="text-danger">*</span></label>
                        <select name="year_level_id" class="form-select" required>
                            <option value="">-- Sélectionner un niveau --</option>
                            <?php foreach ($year_levels as $yl): ?>
                                <option value="<?php echo $yl['id']; ?>"><?php echo htmlspecialchars($yl['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($year_levels)): ?>
                            <small class="text-danger">Vous devez d'abord créer des niveaux d'année dans la gestion des classes.</small>
                        <?php else: ?>
                            <small class="text-muted">Ce modèle sera disponible pour toutes les classes de ce niveau</small>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Note maximale</label>
                        <input type="number" name="total_points" class="form-control" value="20" 
                               min="1" max="100" step="0.5" required>
                        <small class="text-muted">La note finale sera calculée sur cette base</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description (optionnel)</label>
                        <textarea name="description" class="form-control" rows="2" 
                                  placeholder="Description ou notes sur ce modèle"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="create_template" class="btn btn-dark" <?php echo empty($year_levels) ? 'disabled' : ''; ?>>Créer le modèle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'views/partials/footer.php'; ?>
