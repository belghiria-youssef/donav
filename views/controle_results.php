<?php
if (!isset($_SESSION['teacher_id'])) {
    header('Location: index.php?page=login');
    exit;
}

require_once 'classes/ClassRoom.php';
require_once 'classes/Controle.php';
require_once 'classes/Student.php';

$database = new Database();
$db = $database->getConnection();

$classroom = new ClassRoom($db);
$controle = new Controle($db);
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

// Get class info
$classroom->getById($controle->class_id);

// Get phases
$phases = $controle->getPhases();

// Recompute all results
$controle->computeAllResults();

// Get results
$results = $controle->getResults();

// Get statistics
$stats = $controle->getStatistics();

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $controle->title . '_results.csv"');
    
    $output = fopen('php://output', 'w');
    
    // UTF-8 BOM for Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Header row
    $headers = ['Élève', 'Note Finale'];
    foreach ($phases as $phase) {
        $headers[] = $phase['title'] . ' (' . $phase['percentage'] . '%)';
    }
    fputcsv($output, $headers, ';');
    
    // Data rows
    foreach ($results as $result) {
        $row = [$result['student_name'], $result['final_note']];
        $breakdown = json_decode($result['breakdown_json'], true) ?? [];
        
        foreach ($phases as $phase) {
            $found = '-';
            foreach ($breakdown as $b) {
                if ($b['phase_id'] == $phase['id']) {
                    $found = $b['raw_note'] ?? '-';
                    break;
                }
            }
            $row[] = $found;
        }
        fputcsv($output, $row, ';');
    }
    
    fclose($output);
    exit;
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
                <li class="breadcrumb-item active">Résultats</li>
            </ol>
        </nav>

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1" style="font-weight: 600;">Résultats du Contrôle</h4>
                <p class="text-muted mb-0" style="font-size: 14px;">
                    <?php echo htmlspecialchars($controle->title); ?> • <?php echo htmlspecialchars($classroom->nom); ?>
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="index.php?page=controle_notes&id=<?php echo $controle_id; ?>" class="btn btn-outline-dark">
                    <i class="bi bi-pencil me-2"></i>Modifier Notes
                </a>
                <a href="?page=controle_results&id=<?php echo $controle_id; ?>&export=csv" class="btn btn-dark">
                    <i class="bi bi-download me-2"></i>Exporter CSV
                </a>
            </div>
        </div>

        <?php if (empty($phases)): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Aucune phase définie. <a href="index.php?page=controle_edit&id=<?php echo $controle_id; ?>">Configurer le contrôle</a>.
            </div>
        <?php elseif (empty($results)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                Aucun résultat à afficher. <a href="index.php?page=controle_notes&id=<?php echo $controle_id; ?>">Saisir des notes</a>.
            </div>
        <?php else: ?>

        <!-- Statistics Cards -->
        <?php if ($stats): ?>
            <div class="row g-4 mb-4">
                <div class="col-md-3 col-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <div class="display-6 fw-bold text-primary"><?php echo $stats['average']; ?></div>
                            <small class="text-muted">Moyenne / <?php echo $controle->total_points; ?></small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <div class="display-6 fw-bold text-success"><?php echo $stats['max']; ?></div>
                            <small class="text-muted">Note Max</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <div class="display-6 fw-bold text-danger"><?php echo $stats['min']; ?></div>
                            <small class="text-muted">Note Min</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <div class="display-6 fw-bold">
                                <span class="text-success"><?php echo $stats['passed']; ?></span>
                                <small class="text-muted mx-1">/</small>
                                <span class="text-danger"><?php echo $stats['failed']; ?></span>
                            </div>
                            <small class="text-muted">Réussi / Échoué</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pass/Fail Chart -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <span class="text-muted">Taux de réussite:</span>
                        <div class="progress flex-grow-1" style="height: 24px;">
                            <?php $pass_rate = $stats['count'] > 0 ? round(($stats['passed'] / $stats['count']) * 100) : 0; ?>
                            <div class="progress-bar bg-success" style="width: <?php echo $pass_rate; ?>%">
                                <?php echo $pass_rate; ?>% réussi
                            </div>
                            <div class="progress-bar bg-danger" style="width: <?php echo 100 - $pass_rate; ?>%">
                                <?php echo 100 - $pass_rate; ?>% échoué
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Results Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <h6 class="mb-0" style="font-weight: 600;">Détails des Notes</h6>
                <span class="badge bg-dark"><?php echo count($results); ?> élève(s)</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead style="background: rgba(0,0,0,0.02);">
                            <tr>
                                <th style="font-weight: 600;">#</th>
                                <th style="font-weight: 600;">Élève</th>
                                <?php foreach ($phases as $phase): ?>
                                    <th style="font-weight: 600;" class="text-center">
                                        <?php echo htmlspecialchars($phase['title']); ?>
                                        <br>
                                        <small class="text-muted fw-normal">(<?php echo $phase['percentage']; ?>%)</small>
                                    </th>
                                <?php endforeach; ?>
                                <th style="font-weight: 600;" class="text-center">Note Finale</th>
                                <th style="font-weight: 600;" class="text-center">Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $rank = 1;
                            foreach ($results as $result):
                                $breakdown = json_decode($result['breakdown_json'], true) ?? [];
                                $passed = $result['final_note'] >= ($controle->total_points / 2);
                            ?>
                                <tr>
                                    <td>
                                        <?php if ($rank <= 3): ?>
                                            <span class="badge bg-<?php echo $rank == 1 ? 'warning' : ($rank == 2 ? 'secondary' : 'danger'); ?>">
                                                <?php echo $rank; ?>
                                            </span>
                                        <?php else: ?>
                                            <?php echo $rank; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($result['student_name']); ?></strong>
                                    </td>
                                    <?php foreach ($phases as $phase):
                                        $phase_note = '-';
                                        $weighted = 0;
                                        foreach ($breakdown as $b) {
                                            if ($b['phase_id'] == $phase['id']) {
                                                $phase_note = $b['raw_note'] ?? '-';
                                                $weighted = $b['weighted_score'] ?? 0;
                                                break;
                                            }
                                        }
                                    ?>
                                        <td class="text-center">
                                            <?php if ($phase_note !== '-'): ?>
                                                <span class="d-block"><?php echo $phase_note; ?>/<?php echo $phase['max_points']; ?></span>
                                                <small class="text-muted">(+<?php echo $weighted; ?>)</small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="text-center">
                                        <span class="fw-bold fs-5 text-<?php echo $passed ? 'success' : 'danger'; ?>">
                                            <?php echo $result['final_note']; ?>
                                        </span>
                                        <small class="text-muted">/ <?php echo $controle->total_points; ?></small>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($passed): ?>
                                            <span class="badge bg-success">
                                                <i class="bi bi-check-circle"></i> Réussi
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">
                                                <i class="bi bi-x-circle"></i> Échoué
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php $rank++; endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Grade Distribution -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-transparent">
                <h6 class="mb-0" style="font-weight: 600;">Distribution des Notes</h6>
            </div>
            <div class="card-body">
                <?php
                    // Calculate distribution
                    $ranges = [
                        '0-4' => 0,
                        '5-9' => 0,
                        '10-12' => 0,
                        '13-15' => 0,
                        '16-18' => 0,
                        '19-20' => 0
                    ];

                    foreach ($results as $r) {
                        $note = $r['final_note'];
                        if ($note < 5) {
                            $ranges['0-4']++;
                        } elseif ($note < 10) {
                            $ranges['5-9']++;
                        } elseif ($note < 13) {
                            $ranges['10-12']++;
                        } elseif ($note < 16) {
                            $ranges['13-15']++;
                        } elseif ($note < 19) {
                            $ranges['16-18']++;
                        } else {
                            $ranges['19-20']++;
                        }
                    }

                    $max_count = max($ranges) ?: 1;
                    ?>
                    <div class="row g-2">
                        <?php foreach ($ranges as $range => $count): ?>
                            <div class="col-md-2 col-4">
                                <div class="text-center">
                                    <div class="position-relative bg-light rounded" style="height: 100px;">
                                        <div class="position-absolute bottom-0 start-0 end-0 bg-primary rounded-bottom"
                                             style="height: <?php echo ($count / $max_count) * 100; ?>%;">
                                        </div>
                                    </div>
                                    <small class="text-muted d-block mt-2"><?php echo $range; ?></small>
                                    <strong><?php echo $count; ?></strong>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
            </div>
        </div>

        <?php endif; ?>
    </div>
</main>

<?php include 'views/partials/footer.php'; ?>
