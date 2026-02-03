<?php
/**
 * Controle Instance Results
 * View computed final results for all students
 */

if (!isset($_SESSION['teacher_id'])) {
    header('Location: index.php?page=login');
    exit;
}

require_once 'classes/Controle.php';
require_once 'classes/Student.php';

$database = new Database();
$db = $database->getConnection();

$controle = new Controle($db);
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

// Recompute all results
$controle->computeAllResults($instance_id);

// Get results
$results = $controle->getResults($instance_id);

// Get statistics
$stats = $controle->getStatistics($instance_id);

// Get phases for breakdown
$phases = $controle->getPhases($instance['template_id']);

// Handle export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="resultats_' . date('Ymd') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Header row
    $headers = ['Élève', 'Note Finale', 'Statut'];
    foreach ($phases as $p) {
        $headers[] = $p['title'] . ' (' . $p['points'] . ' pts)';
    }
    fputcsv($output, $headers);
    
    // Data rows
    foreach ($results as $r) {
        $is_ungraded = $r['final_note'] < 0;
        $status = $is_ungraded ? 'Incomplet' : ($r['final_note'] >= ($instance['total_points'] / 2) ? 'Admis' : 'Non admis');
        $row = [$r['student_name'], $is_ungraded ? 'N/A' : $r['final_note'], $status];
        $breakdown = json_decode($r['breakdown_json'], true);
        foreach ($breakdown as $b) {
            $row[] = $b['raw_note'] !== null ? $b['raw_note'] : 'Non noté';
        }
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

include 'views/partials/header.php';
include 'views/partials/sidebar.php';
?>

<main class="main-content" style="margin-left: 212px; padding-top: 68px; min-height: 100vh; background: #f9f9fa;">
    <div class="container-fluid" style="padding: 28px;">
        <div class="rounded-4 p-4 shadow-sm mb-4" style="background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 60%, #a855f7 100%); color: #fff;">
            <div class="d-flex flex-column flex-md-row justify-content-between gap-3 align-items-start">
                <div>
                    <p class="mb-2 text-uppercase" style="letter-spacing: 1px; font-size: 13px;">Résultats du contrôle</p>
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
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a href="index.php?page=controle_fill_notes&id=<?php echo $instance_id; ?>" class="btn btn-outline-light px-4">
                        <i class="bi bi-pencil-square"></i> Modifier
                    </a>
                    <a href="index.php?page=controle_instance_results&id=<?php echo $instance_id; ?>&export=csv" class="btn btn-light px-4 text-dark">
                        <i class="bi bi-download"></i> Exporter
                    </a>
                </div>
            </div>
        </div>

        <?php if ($stats): ?>
            <div class="row g-3 mb-4">
                <div class="col-md-2">
                    <div class="card border-0 rounded-4 shadow-sm p-3 h-100">
                        <small class="text-muted text-uppercase" style="letter-spacing: 1px; font-size: 11px;">Notés</small>
                        <h4 class="mb-0" style="font-weight: 600;"><?php echo $stats['graded_count']; ?></h4>
                        <small class="text-muted">sur <?php echo $stats['total_students']; ?> élèves</small>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card border-0 rounded-4 shadow-sm p-3 h-100">
                        <small class="text-muted text-uppercase" style="letter-spacing: 1px; font-size: 11px;">Moyenne</small>
                        <h4 class="mb-0" style="font-weight: 600;"><?php echo $stats['average']; ?></h4>
                        <small class="text-muted">sur <?php echo $instance['total_points']; ?></small>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card border-0 rounded-4 shadow-sm p-3 h-100">
                        <small class="text-muted text-uppercase" style="letter-spacing: 1px; font-size: 11px;">Note min</small>
                        <h4 class="mb-0 text-danger" style="font-weight: 600;"><?php echo $stats['graded_count'] > 0 ? $stats['min'] : '-'; ?></h4>
                        <small class="text-muted">plus basse</small>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card border-0 rounded-4 shadow-sm p-3 h-100">
                        <small class="text-muted text-uppercase" style="letter-spacing: 1px; font-size: 11px;">Note max</small>
                        <h4 class="mb-0 text-success" style="font-weight: 600;"><?php echo $stats['graded_count'] > 0 ? $stats['max'] : '-'; ?></h4>
                        <small class="text-muted">meilleure</small>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card border-0 rounded-4 shadow-sm p-3 h-100">
                        <small class="text-muted text-uppercase" style="letter-spacing: 1px; font-size: 11px;">Réussite</small>
                        <h4 class="mb-0 text-success" style="font-weight: 600;"><?php echo $stats['passed']; ?></h4>
                        <small class="text-muted">admis</small>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card border-0 rounded-4 shadow-sm p-3 h-100">
                        <small class="text-muted text-uppercase" style="letter-spacing: 1px; font-size: 11px;">Échec</small>
                        <h4 class="mb-0 text-danger" style="font-weight: 600;"><?php echo $stats['failed']; ?></h4>
                        <small class="text-muted">non admis</small>
                    </div>
                </div>
            </div>

            <div class="card border-0 rounded-4 shadow-sm mb-4">
                <div class="card-body">
                    <?php if ($stats['ungraded_count'] > 0): ?>
                    <div class="alert alert-warning rounded-4 mb-3" style="font-size: 13px;">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong><?php echo $stats['ungraded_count']; ?> élève(s)</strong> n'ont pas encore été notés dans toutes les phases.
                        <a href="index.php?page=controle_fill_notes&id=<?php echo $instance_id; ?>" class="ms-2">Compléter les notes</a>
                    </div>
                    <?php endif; ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span style="font-weight: 600;">Taux de réussite (élèves notés)</span>
                        <span class="badge bg-dark rounded-pill"><?php echo $stats['graded_count'] > 0 ? round(($stats['passed'] / $stats['graded_count']) * 100) : 0; ?>%</span>
                    </div>
                    <div class="progress rounded-pill" style="height: 24px;">
                        <div class="progress-bar bg-success" style="width: <?php echo $stats['graded_count'] > 0 ? ($stats['passed'] / $stats['graded_count']) * 100 : 0; ?>%">
                            <?php echo $stats['passed']; ?> admis
                        </div>
                        <div class="progress-bar bg-danger" style="width: <?php echo $stats['graded_count'] > 0 ? ($stats['failed'] / $stats['graded_count']) * 100 : 0; ?>%">
                            <?php echo $stats['failed']; ?> échec
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="card border-0 rounded-4 shadow-sm overflow-hidden">
            <div class="card-header bg-white border-0 p-4">
                <h5 class="mb-0" style="font-weight: 600; letter-spacing: 1px;">Détail des notes</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($results)): ?>
                    <div class="text-center py-5">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="rgba(15,23,42,0.2)" stroke-width="2" style="margin-bottom: 16px;">
                            <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        <p class="text-muted mb-0">
                            Aucun résultat calculé. <a href="index.php?page=controle_fill_notes&id=<?php echo $instance_id; ?>">Saisir des notes</a>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-borderless mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-muted" style="width: 60px;">Rang</th>
                                    <th>Élève</th>
                                    <?php foreach ($phases as $p): ?>
                                        <th class="text-center" style="font-size: 12px;">
                                            <?php echo htmlspecialchars($p['title']); ?>
                                            <p class="mb-0 text-muted" style="font-size: 11px;">
                                                <?php echo $p['points']; ?> pts
                                                <?php if ($p['grading_mode'] === 'team'): ?>
                                                    <i class="bi bi-people"></i>
                                                <?php endif; ?>
                                            </p>
                                        </th>
                                    <?php endforeach; ?>
                                    <th class="text-center" style="width: 120px;">
                                        <strong>Note Finale</strong>
                                        <p class="mb-0 text-muted" style="font-size: 11px;">sur <?php echo $instance['total_points']; ?></p>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $rank = 0;
                                $prev_note = null;
                                $graded_index = 0;
                                foreach ($results as $index => $r): 
                                    $breakdown = json_decode($r['breakdown_json'], true);
                                    $is_ungraded = $r['final_note'] < 0;
                                    
                                    // Only count ranks for graded students
                                    if (!$is_ungraded) {
                                        if ($prev_note !== $r['final_note']) {
                                            $rank = $graded_index + 1;
                                        }
                                        $prev_note = $r['final_note'];
                                        $graded_index++;
                                    }
                                    
                                    $passed = !$is_ungraded && $r['final_note'] >= ($instance['total_points'] / 2);
                                ?>
                                    <tr style="<?php echo $is_ungraded ? 'background: rgba(255, 193, 7, 0.1);' : (!$passed ? 'background: rgba(239, 68, 68, 0.05);' : ''); ?>">
                                        <td>
                                            <?php if ($is_ungraded): ?>
                                                <span class="badge bg-warning text-dark rounded-pill">—</span>
                                            <?php elseif ($rank <= 3): ?>
                                                <span class="badge rounded-pill <?php echo $rank == 1 ? 'bg-warning text-dark' : ($rank == 2 ? 'bg-secondary' : 'bg-dark'); ?>">
                                                    <?php echo $rank; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted"><?php echo $rank; ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <strong><?php echo htmlspecialchars($r['student_name']); ?></strong>
                                                <?php if ($is_ungraded): ?>
                                                    <span class="badge bg-warning text-dark" style="font-size: 10px;">Incomplet</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <?php foreach ($breakdown as $b): ?>
                                            <td class="text-center">
                                                <?php if ($b['raw_note'] !== null): ?>
                                                    <strong><?php echo $b['raw_note']; ?></strong>
                                                    <small class="text-muted">/<?php echo $b['points']; ?></small>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark" style="font-size: 10px;">Non noté</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                        <td class="text-center">
                                            <?php if ($is_ungraded): ?>
                                                <span class="badge bg-secondary rounded-pill" style="font-size: 14px; padding: 6px 12px;">—</span>
                                            <?php else: ?>
                                                <span class="badge <?php echo $passed ? 'bg-success' : 'bg-danger'; ?> rounded-pill" style="font-size: 14px; padding: 6px 12px;">
                                                    <?php echo $r['final_note']; ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card border-0 rounded-4 shadow-sm mt-3 p-3">
            <small class="text-muted mb-0">
                <i class="bi bi-people me-1"></i> Note d'équipe (partagée entre membres) • 
                <span class="text-success">Vert</span> = Réussite (≥ <?php echo $instance['total_points'] / 2; ?>) • 
                <span class="text-danger">Rouge</span> = Échec • 
                <span class="badge bg-warning text-dark" style="font-size: 10px;">Incomplet</span> = Notes manquantes dans certaines phases
            </small>
        </div>
    </div>
</main>

<?php include 'views/partials/footer.php'; ?>
