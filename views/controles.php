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

$message = '';
$error = '';

// Get class_id if provided (for class-specific controles)
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : null;

// Handle creating a new controle
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_controle'])) {
    $title = trim($_POST['title'] ?? '');
    $selected_class_id = intval($_POST['class_id'] ?? 0);
    $total_points = floatval($_POST['total_points'] ?? 20);
    
    if (!empty($title) && $selected_class_id > 0) {
        // Verify class belongs to teacher
        if ($classroom->getById($selected_class_id) && $classroom->enseignant_id == $_SESSION['teacher_id']) {
            $controle->title = $title;
            $controle->class_id = $selected_class_id;
            $controle->total_points = $total_points;
            $controle->created_by = $_SESSION['teacher_id'];
            
            if ($controle->create()) {
                $message = "Contrôle créé avec succès!";
                // Redirect to edit phases
                header("Location: index.php?page=controle_edit&id=" . $controle->id);
                exit;
            } else {
                $error = "Erreur lors de la création du contrôle.";
            }
        } else {
            $error = "Classe non autorisée.";
        }
    } else {
        $error = "Veuillez remplir tous les champs.";
    }
}

// Handle deleting a controle
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_controle'])) {
    $controle_id = intval($_POST['controle_id'] ?? 0);
    
    if ($controle_id > 0 && $controle->getById($controle_id)) {
        if ($controle->created_by == $_SESSION['teacher_id']) {
            if ($controle->delete()) {
                $message = "Contrôle supprimé avec succès.";
            } else {
                $error = "Erreur lors de la suppression.";
            }
        } else {
            $error = "Action non autorisée.";
        }
    }
}

// Handle lock/unlock
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_lock'])) {
    $controle_id = intval($_POST['controle_id'] ?? 0);
    
    if ($controle_id > 0 && $controle->getById($controle_id)) {
        if ($controle->created_by == $_SESSION['teacher_id']) {
            if ($controle->is_locked) {
                $controle->unlock();
                $message = "Contrôle déverrouillé.";
            } else {
                $controle->lock();
                $message = "Contrôle verrouillé.";
            }
        }
    }
}

// Get teacher's classes for dropdown
$classes_stmt = $classroom->getByTeacher($_SESSION['teacher_id']);
$classes = $classes_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get controles based on context
if ($class_id) {
    // Verify class belongs to teacher
    if (!$classroom->getById($class_id) || $classroom->enseignant_id != $_SESSION['teacher_id']) {
        header('Location: index.php?page=dashboard');
        exit;
    }
    $controles_stmt = $controle->getByClass($class_id);
    $page_title = "Contrôles - " . $classroom->nom;
} else {
    $controles_stmt = $controle->getByTeacher($_SESSION['teacher_id']);
    $page_title = "Tous mes Contrôles";
}
$controles = $controles_stmt->fetchAll(PDO::FETCH_ASSOC);

include 'views/partials/header.php';
include 'views/partials/sidebar.php';
?>

<main class="main-content" style="margin-left: 212px; padding-top: 68px; min-height: 100vh; background: #f9f9fa;">
    <div class="container-fluid" style="padding: 28px;">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1" style="font-weight: 600;"><?php echo htmlspecialchars($page_title); ?></h4>
                <p class="text-muted mb-0" style="font-size: 14px;">Gérez vos évaluations et notes</p>
            </div>
            <button type="button" class="btn btn-dark d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#createControleModal">
                <i class="bi bi-plus-lg"></i>
                Nouveau Contrôle
            </button>
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

        <!-- Controles List -->
        <?php if (empty($controles)): ?>
            <div class="card border-0 shadow-sm" style="border-radius: 20px;">
                <div class="card-body text-center py-5">
                    <div class="mb-3">
                        <i class="bi bi-file-earmark-text" style="font-size: 48px; color: rgba(0,0,0,0.2);"></i>
                    </div>
                    <h5 style="font-weight: 600;">Aucun contrôle</h5>
                    <p class="text-muted">Créez votre premier contrôle pour commencer à évaluer vos élèves.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($controles as $c): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card border-0 shadow-sm h-100" style="border-radius: 20px;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h5 class="card-title mb-1" style="font-weight: 600;">
                                            <?php echo htmlspecialchars($c['title']); ?>
                                        </h5>
                                        <small class="text-muted"><?php echo htmlspecialchars($c['class_name'] ?? ''); ?></small>
                                    </div>
                                    <?php if ($c['is_locked']): ?>
                                        <span class="badge bg-secondary">
                                            <i class="bi bi-lock"></i> Verrouillé
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <div class="p-2 rounded" style="background: rgba(0,0,0,0.04);">
                                            <small class="text-muted d-block">Phases</small>
                                            <strong><?php echo $c['phase_count'] ?? 0; ?></strong>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-2 rounded" style="background: rgba(0,0,0,0.04);">
                                            <small class="text-muted d-block">Total pts</small>
                                            <strong class="<?php echo ($c['total_phase_points'] ?? 0) == $c['total_points'] ? 'text-success' : 'text-warning'; ?>">
                                                <?php echo $c['total_phase_points'] ?? 0; ?>/<?php echo $c['total_points']; ?>
                                            </strong>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex gap-2">
                                    <a href="index.php?page=controle_edit&id=<?php echo $c['id']; ?>" 
                                       class="btn btn-outline-dark btn-sm flex-grow-1">
                                        <i class="bi bi-pencil"></i> Modifier
                                    </a>
                                    <a href="index.php?page=controle_notes&id=<?php echo $c['id']; ?>" 
                                       class="btn btn-dark btn-sm flex-grow-1">
                                        <i class="bi bi-journal-check"></i> Notes
                                    </a>
                                    <div class="dropdown">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="dropdown">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a href="index.php?page=controle_instance_results&id=<?php echo $c['id']; ?>" class="dropdown-item">
                                                    <i class="bi bi-graph-up me-2"></i>Résultats
                                                </a>
                                            </li>
                                            <li>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="controle_id" value="<?php echo $c['id']; ?>">
                                                    <button type="submit" name="toggle_lock" class="dropdown-item">
                                                        <i class="bi bi-<?php echo $c['is_locked'] ? 'unlock' : 'lock'; ?> me-2"></i>
                                                        <?php echo $c['is_locked'] ? 'Déverrouiller' : 'Verrouiller'; ?>
                                                    </button>
                                                </form>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce contrôle?');">
                                                    <input type="hidden" name="controle_id" value="<?php echo $c['id']; ?>">
                                                    <button type="submit" name="delete_controle" class="dropdown-item text-danger">
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
                                    Créé le <?php echo date('d/m/Y', strtotime($c['created_at'])); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Create Controle Modal -->
<div class="modal fade" id="createControleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nouveau Contrôle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Titre du contrôle</label>
                        <input type="text" name="title" class="form-control" required placeholder="Ex: Contrôle 1 - Développement Web">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Classe</label>
                        <select name="class_id" class="form-select" required>
                            <option value="">Sélectionner une classe</option>
                            <?php foreach ($classes as $cls): ?>
                                <option value="<?php echo $cls['id']; ?>" <?php echo $class_id == $cls['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cls['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Note maximale</label>
                        <input type="number" name="total_points" class="form-control" value="20" min="1" max="100" step="0.5" required>
                        <small class="text-muted">La note finale sera calculée sur cette base</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="create_controle" class="btn btn-dark">Créer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'views/partials/footer.php'; ?>
