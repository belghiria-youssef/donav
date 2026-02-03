<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#ffffff">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="No9ati">
    <meta name="mobile-web-app-capable" content="yes">
    
    <title><?php
        $page_titles = [
            'dashboard' => 'Tableau de bord',
            'manage_classes' => 'Gestion des classes',
            'class_view' => 'Vue de la classe',
            'add_student' => 'Ajouter un élève',
            'import_students' => 'Importer des élèves',
            'points_log' => 'Historique des points',
            'absences' => 'Absences',
            'teams' => 'Équipes',
            'controles' => 'Contrôles',
            'controle_edit' => 'Modifier Contrôle',
            'controle_notes' => 'Saisie des Notes',
            'controle_results' => 'Résultats',
            'profile' => 'Mon profil',
            'generate_certificate' => 'Génération de certificat'
        ];
        $current_page = $_GET['page'] ?? 'dashboard';
        echo ($page_titles[$current_page] ?? 'No9ati') . ' - No9ati';
    ?></title>
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">
    
    <!-- Icons -->
    <link rel="icon" type="image/svg+xml" href="assets/icons/icon-72x72.svg">
    <link rel="apple-touch-icon" href="assets/icons/icon-152x152.svg">
    
    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- PWA CSS -->
    <style>
        :root {
            --vh: 1vh;
        }
        
        .pwa-standalone .navbar {
            padding-top: env(safe-area-inset-top, 0px);
        }
        
        .pwa-standalone .sidebar {
            padding-top: env(safe-area-inset-top, 0px);
        }
        
        .touch-device .btn {
            min-height: 44px;
            padding: 12px 16px;
        }
        
        .touch-device .nav-link {
            padding: 16px 20px;
        }
        
        .touch-device .table td,
        .touch-device .table th {
            padding: 16px 12px;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg position-fixed" style="left: 212px; right: 0; top: 0; z-index: 1020; height: 68px; background: #fff; border-bottom: 0.5px solid rgba(0,0,0,0.1);">
    <div class="container-fluid d-flex justify-content-between align-items-center" style="padding: 20px 28px;">
        <!-- Left side: Mobile menu + Breadcrumb -->
        <div class="d-flex align-items-center gap-2">

            
            
            <!-- Breadcrumb -->
            <div class="d-none d-md-flex align-items-center gap-2 ms-3" style="font-size: 14px; color: rgba(0,0,0,0.4);">
                <span style="color: #1c1c1c;"><?php echo $page_titles[$current_page] ?? 'No9ati'; ?></span>
            </div>
        </div>

        <!-- Right side: Search + Actions -->
        <div class="d-flex align-items-center gap-4">

            

        </div>
    </div>
</nav>