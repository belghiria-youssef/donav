<?php
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

// Get year levels for dropdowns
$year_levels = $controle->getYearLevels($_SESSION['teacher_id']);

// Handle year level creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_year_level'])) {
    $name = $_POST['year_name'] ?? '';
    $short_name = $_POST['year_short_name'] ?? '';
    $order_index = $_POST['year_order'] ?? 0;
    
    if (empty($name)) {
        $error = 'Le nom du niveau est requis.';
    } else {
        if ($controle->createYearLevel($name, $short_name, $order_index, $_SESSION['teacher_id'])) {
            $message = 'Niveau d\'année créé avec succès!';
            $year_levels = $controle->getYearLevels($_SESSION['teacher_id']); // Refresh
        } else {
            $error = 'Erreur lors de la création du niveau.';
        }
    }
}

// Handle year level deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_year_level'])) {
    $year_id = $_POST['year_level_id'] ?? 0;
    if ($controle->deleteYearLevel($year_id)) {
        $message = 'Niveau supprimé avec succès!';
        $year_levels = $controle->getYearLevels($_SESSION['teacher_id']); // Refresh
    } else {
        $error = 'Impossible de supprimer ce niveau (utilisé par des classes ou modèles).';
    }
}

// Handle class creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_class'])) {
    $classroom->nom = $_POST['nom'] ?? '';
    $classroom->enseignant_id = $_SESSION['teacher_id'];
    $classroom->year_level_id = !empty($_POST['year_level_id']) ? $_POST['year_level_id'] : null;
    
    if (empty($classroom->nom)) {
        $error = 'Le nom de la classe est requis.';
    } elseif ($classroom->classNameExists($classroom->nom, $classroom->enseignant_id)) {
        $error = 'Une classe avec ce nom existe déjà.';
    } else {
        if ($classroom->create()) {
            $message = 'Classe créée avec succès!';
        } else {
            $error = 'Erreur lors de la création de la classe.';
        }
    }
}

// Handle class deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_class'])) {
    $classroom->id = $_POST['class_id'] ?? 0;
    $classroom->enseignant_id = $_SESSION['teacher_id'];
    
    if ($classroom->delete()) {
        $message = 'Classe supprimée avec succès!';
    } else {
        $error = 'Erreur lors de la suppression de la classe.';
    }
}

// Handle class update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_class'])) {
    $classroom->id = $_POST['class_id'] ?? 0;
    $classroom->nom = $_POST['nom'] ?? '';
    $classroom->year_level_id = !empty($_POST['year_level_id']) ? $_POST['year_level_id'] : null;
    $classroom->enseignant_id = $_SESSION['teacher_id'];
    
    if (empty($classroom->nom)) {
        $error = 'Le nom de la classe est requis.';
    } elseif ($classroom->classNameExists($classroom->nom, $classroom->enseignant_id, $classroom->id)) {
        $error = 'Une classe avec ce nom existe déjà.';
    } else {
        if ($classroom->update()) {
            $message = 'Classe modifiée avec succès!';
        } else {
            $error = 'Erreur lors de la modification de la classe.';
        }
    }
}

// Get teacher's classes
$classes_stmt = $classroom->getByTeacher($_SESSION['teacher_id']);
$classes = $classes_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des classes - No9ati</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body style="background: #f9f9fa;">
    <?php include 'views/partials/sidebar.php'; ?>
    
    <div class="main-content d-flex flex-column" style="margin-left: 212px; padding-top: 68px; min-height: 100vh;">
        <nav class="navbar navbar-expand-lg" style="background: #fff; position: fixed; left: 212px; right: 0; top: 0; z-index: 1020; height: 68px; border-bottom: 1px solid rgba(0,0,0,0.1);">
            <div class="container-fluid px-4">
                <h1 style="font-size: 16px; font-weight: 600; color: #1c1c1c; margin: 0;">Gestion des classes</h1>
                <div class="d-flex align-items-center">
                    <div class="d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; background: #1c1c1c; color: #fff; border-radius: 50%; font-size: 14px; font-weight: 500;">
                        <?php echo strtoupper(substr($_SESSION['teacher_name'] ?? 'U', 0, 1)); ?>
                    </div>
                </div>
            </div>
        </nav>
        
        <main class="container-fluid" style="padding: 28px;">
            <div class="row mb-4 align-items-center">
                <div class="col-12 col-md-8">
                    <h1 style="font-size: 24px; font-weight: 600; color: #1c1c1c; margin-bottom: 0;">Gestion des classes</h1>
                </div>
                <div class="col-12 col-md-4 text-md-end mt-3 mt-md-0">
                    <button class="btn" data-bs-toggle="modal" data-bs-target="#createClassModal" style="background: #1c1c1c; color: #fff; border-radius: 12px; padding: 8px 16px; font-size: 14px;">
                        <svg class="me-2" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        Créer une classe
                    </button>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-dismissible fade show" role="alert" style="background: rgba(34, 197, 94, 0.1); color: #22c55e; border: none; border-radius: 12px; padding: 16px;">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-dismissible fade show" role="alert" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border: none; border-radius: 12px; padding: 16px;">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-12">
                    <div class="card" style="background: #fff; border-radius: 20px; border: none;">
                        <div class="card-header" style="background: transparent; border: none; padding: 24px; padding-bottom: 16px;">
                            <h2 style="font-size: 14px; font-weight: 600; color: #1c1c1c; margin: 0;">Mes Classes</h2>
                        </div>
                        <div class="card-body" style="padding: 24px; padding-top: 0;">
                            <?php if (empty($classes)): ?>
                                <div class="text-center py-5">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="rgba(0,0,0,0.2)" stroke-width="2" style="margin-bottom: 16px;">
                                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                                    </svg>
                                    <p style="color: rgba(0,0,0,0.4); margin-bottom: 16px;">Vous n'avez pas encore de classes.</p>
                                    <button class="btn" data-bs-toggle="modal" data-bs-target="#createClassModal" style="background: #1c1c1c; color: #fff; border-radius: 12px; padding: 8px 16px; font-size: 14px;">
                                        Créer votre première classe
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0" id="classesTable">
                                        <thead>
                                            <tr>
                                                <th style="font-size: 12px; font-weight: 500; color: rgba(0,0,0,0.4); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid rgba(0,0,0,0.1); padding: 12px 16px;">Nom de la classe</th>
                                                <th class="text-center" style="font-size: 12px; font-weight: 500; color: rgba(0,0,0,0.4); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid rgba(0,0,0,0.1); padding: 12px 16px;">Année</th>
                                                <th class="text-center" style="font-size: 12px; font-weight: 500; color: rgba(0,0,0,0.4); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid rgba(0,0,0,0.1); padding: 12px 16px;">Nombre d'élèves</th>
                                                <th class="text-center" style="font-size: 12px; font-weight: 500; color: rgba(0,0,0,0.4); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid rgba(0,0,0,0.1); padding: 12px 16px;">Points totaux</th>
                                                <th class="text-center" style="font-size: 12px; font-weight: 500; color: rgba(0,0,0,0.4); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid rgba(0,0,0,0.1); padding: 12px 16px;">Date de création</th>
                                                <th class="text-end" style="font-size: 12px; font-weight: 500; color: rgba(0,0,0,0.4); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid rgba(0,0,0,0.1); padding: 12px 16px;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($classes as $class): ?>
                                                <?php
                                                $student_count = $classroom->getStudentCount($class['id']);
                                                require_once 'classes/PointSystem.php';
                                                $pointSystem = new PointSystem($db);
                                                $total_points = $pointSystem->getTotalPointsByClass($class['id']);
                                                $year_level_name = $class['year_level_name'] ?? 'Non défini';
                                                ?>
                                                <tr>
                                                    <td style="padding: 16px; border-bottom: 1px solid rgba(0,0,0,0.1); font-size: 14px; color: #1c1c1c;">
                                                        <strong><?php echo htmlspecialchars($class['nom']); ?></strong>
                                                    </td>
                                                    <td class="text-center" style="padding: 16px; border-bottom: 1px solid rgba(0,0,0,0.1);">
                                                        <?php if ($class['year_level_id']): ?>
                                                            <span style="background: #fef3c7; color: #d97706; font-size: 12px; font-weight: 500; padding: 4px 8px; border-radius: 8px;"><?php echo htmlspecialchars($year_level_name); ?></span>
                                                        <?php else: ?>
                                                            <span style="background: rgba(0,0,0,0.05); color: rgba(0,0,0,0.4); font-size: 12px; font-weight: 500; padding: 4px 8px; border-radius: 8px;">Non défini</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center" style="padding: 16px; border-bottom: 1px solid rgba(0,0,0,0.1);">
                                                        <span style="background: #edeefc; color: #6366f1; font-size: 12px; font-weight: 500; padding: 4px 8px; border-radius: 8px;"><?php echo $student_count; ?></span>
                                                    </td>
                                                    <td class="text-center" style="padding: 16px; border-bottom: 1px solid rgba(0,0,0,0.1);">
                                                        <span style="background: rgba(34, 197, 94, 0.1); color: #22c55e; font-size: 12px; font-weight: 500; padding: 4px 8px; border-radius: 8px;"><?php echo number_format($total_points); ?></span>
                                                    </td>
                                                    <td class="text-center" style="padding: 16px; border-bottom: 1px solid rgba(0,0,0,0.1);">
                                                        <small style="font-size: 12px; color: rgba(0,0,0,0.4);"><?php echo date('d/m/Y', strtotime($class['created_at'])); ?></small>
                                                    </td>
                                                    <td class="text-end" style="padding: 16px; border-bottom: 1px solid rgba(0,0,0,0.1);">
                                                        <div class="d-flex justify-content-end gap-2">
                                                            <a href="index.php?page=class_view&id=<?php echo $class['id']; ?>" 
                                                               class="btn btn-sm" style="background: #1c1c1c; color: #fff; border-radius: 8px; font-size: 12px; padding: 4px 12px;">
                                                                Voir
                                                            </a>
                                                            <button class="btn btn-sm edit-class-btn" 
                                                                    data-class-id="<?php echo $class['id']; ?>"
                                                                    data-class-name="<?php echo htmlspecialchars($class['nom']); ?>"
                                                                    data-class-year-id="<?php echo $class['year_level_id'] ?? ''; ?>"
                                                                    style="background: rgba(0,0,0,0.04); color: #1c1c1c; border: none; border-radius: 8px; font-size: 12px; padding: 4px 12px;">
                                                                Modifier
                                                            </button>
                                                            <button class="btn btn-sm delete-class-btn" 
                                                                    data-class-id="<?php echo $class['id']; ?>"
                                                                    data-class-name="<?php echo htmlspecialchars($class['nom']); ?>"
                                                                    style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border: none; border-radius: 8px; font-size: 12px; padding: 4px 12px;">
                                                                Supprimer
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Year Levels Management Card -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card" style="background: #fff; border-radius: 20px; border: none;">
                        <div class="card-header d-flex justify-content-between align-items-center" style="background: transparent; border: none; padding: 24px; padding-bottom: 16px;">
                            <h2 style="font-size: 14px; font-weight: 600; color: #1c1c1c; margin: 0;">Niveaux d'année</h2>
                            <button class="btn btn-sm" data-bs-toggle="modal" data-bs-target="#createYearLevelModal" style="background: #1c1c1c; color: #fff; border-radius: 8px; font-size: 12px; padding: 4px 12px;">
                                + Ajouter un niveau
                            </button>
                        </div>
                        <div class="card-body" style="padding: 24px; padding-top: 0;">
                            <?php if (empty($year_levels)): ?>
                                <p class="text-muted text-center py-3">Aucun niveau d'année créé. Créez-en un pour organiser vos classes et modèles de contrôle.</p>
                            <?php else: ?>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($year_levels as $yl): ?>
                                        <div class="d-flex align-items-center gap-2 px-3 py-2" style="background: #fef3c7; border-radius: 8px;">
                                            <span style="color: #d97706; font-size: 14px; font-weight: 500;">
                                                <?php echo htmlspecialchars($yl['name']); ?>
                                                <?php if ($yl['short_name']): ?>
                                                    <small style="color: #92400e;">(<?php echo htmlspecialchars($yl['short_name']); ?>)</small>
                                                <?php endif; ?>
                                            </span>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer ce niveau ?');">
                                                <input type="hidden" name="year_level_id" value="<?php echo $yl['id']; ?>">
                                                <button type="submit" name="delete_year_level" class="btn btn-sm p-0" style="background: none; border: none; color: #ef4444;">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <line x1="18" y1="6" x2="6" y2="18"></line>
                                                        <line x1="6" y1="6" x2="18" y2="18"></line>
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Create Year Level Modal -->
    <div class="modal fade" id="createYearLevelModal" tabindex="-1" aria-labelledby="createYearLevelModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title h5" id="createYearLevelModalLabel">Créer un niveau d'année</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="year_name" class="form-label">Nom du niveau</label>
                            <input type="text" name="year_name" id="year_name" class="form-control" 
                                   placeholder="Ex: 1ère Année, Master 1, Licence 3..." required>
                        </div>
                        <div class="mb-3">
                            <label for="year_short_name" class="form-label">Abréviation (optionnel)</label>
                            <input type="text" name="year_short_name" id="year_short_name" class="form-control" 
                                   placeholder="Ex: 1A, M1, L3...">
                        </div>
                        <div class="mb-3">
                            <label for="year_order" class="form-label">Ordre d'affichage</label>
                            <input type="number" name="year_order" id="year_order" class="form-control" 
                                   value="0" min="0">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="create_year_level" class="btn btn-primary">Créer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Create Class Modal -->
    <div class="modal fade" id="createClassModal" tabindex="-1" aria-labelledby="createClassModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title h5" id="createClassModalLabel">Créer une nouvelle classe</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="nom" class="form-label">Nom de la classe</label>
                            <input type="text" name="nom" id="nom" class="form-control" 
                                   placeholder="Ex: 6ème A, CM2 B..." required>
                        </div>
                        <div class="mb-3">
                            <label for="year_level_id" class="form-label">Niveau d'année</label>
                            <select name="year_level_id" id="year_level_id" class="form-select">
                                <option value="">-- Sélectionner un niveau --</option>
                                <?php foreach ($year_levels as $yl): ?>
                                    <option value="<?php echo $yl['id']; ?>"><?php echo htmlspecialchars($yl['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($year_levels)): ?>
                                <small class="text-muted">Vous devez d'abord créer des niveaux d'année.</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="create_class" class="btn btn-primary">Créer la classe</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Class Modal -->
    <div class="modal fade" id="editClassModal" tabindex="-1" aria-labelledby="editClassModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title h5" id="editClassModalLabel">Modifier la classe</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="class_id" id="edit_class_id">
                        <div class="mb-3">
                            <label for="edit_nom" class="form-label">Nom de la classe</label>
                            <input type="text" name="nom" id="edit_nom" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_year_level_id" class="form-label">Niveau d'année</label>
                            <select name="year_level_id" id="edit_year_level_id" class="form-select">
                                <option value="">-- Sélectionner un niveau --</option>
                                <?php foreach ($year_levels as $yl): ?>
                                    <option value="<?php echo $yl['id']; ?>"><?php echo htmlspecialchars($yl['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="update_class" class="btn btn-primary">Modifier</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteClassModal" tabindex="-1" aria-labelledby="deleteClassModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title h5" id="deleteClassModalLabel">Confirmer la suppression</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="class_id" id="delete_class_id">
                        <p>Êtes-vous sûr de vouloir supprimer la classe <strong id="delete_class_name"></strong> ?</p>
                        <div class="alert alert-warning">
                            <strong>Attention:</strong> Cette action supprimera également tous les élèves et leurs points associés à cette classe.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="delete_class" class="btn btn-danger">Supprimer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        // Handle edit class button clicks
        document.querySelectorAll('.edit-class-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const classId = btn.dataset.classId;
                const className = btn.dataset.className;
                const classYearId = btn.dataset.classYearId || '';
                
                document.getElementById('edit_class_id').value = classId;
                document.getElementById('edit_nom').value = className;
                document.getElementById('edit_year_level_id').value = classYearId;
                
                const modal = new bootstrap.Modal(document.getElementById('editClassModal'));
                modal.show();
            });
        });
        
        // Handle delete class button clicks
        document.querySelectorAll('.delete-class-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const classId = btn.dataset.classId;
                const className = btn.dataset.className;
                
                document.getElementById('delete_class_id').value = classId;
                document.getElementById('delete_class_name').textContent = className;
                
                const modal = new bootstrap.Modal(document.getElementById('deleteClassModal'));
                modal.show();
            });
        });
    </script>
</body>
</html>