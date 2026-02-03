<div class="sidebar d-flex flex-column position-fixed vh-100 d-none d-lg-flex" style="width: 212px; z-index: 1030; background: #fff; border-right: 0.5px solid rgba(0,0,0,0.1);">
    <div class="sidebar-header p-3" style="border-bottom: 0.5px solid rgba(0,0,0,0.1);">
        <a href="index.php?page=dashboard" class="text-decoration-none d-flex align-items-center" style="color: #1c1c1c;">
            <div class="logo-icon me-2 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: rgba(0,0,0,0.04); border-radius: 8px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                </svg>
            </div>
            <span style="font-weight: 600; font-size: 14px;">No9ati</span>
        </a>
    </div>

    <nav class="nav flex-column flex-grow-1 p-3" style="gap: 4px;">
        <!-- Favorites Section -->
        <div style="font-size: 12px; color: rgba(0,0,0,0.4); padding: 8px 12px; margin-top: 4px;">Favorites</div>
        
        <a href="index.php?page=dashboard" class="nav-link d-flex align-items-center <?php echo ($_GET['page'] ?? '') === 'dashboard' || empty($_GET['page']) ? 'active' : ''; ?>" style="color: #1c1c1c; padding: 8px 12px; border-radius: 12px; font-size: 14px; <?php echo ($_GET['page'] ?? '') === 'dashboard' || empty($_GET['page']) ? 'background: rgba(0,0,0,0.04);' : ''; ?>">
            <svg class="me-2" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>
            Tableau de bord
        </a>

        <!-- Dashboards Section -->
        <div style="font-size: 12px; color: rgba(0,0,0,0.4); padding: 8px 12px; margin-top: 12px;">Dashboards</div>

        <a href="index.php?page=manage_classes" class="nav-link d-flex align-items-center <?php echo ($_GET['page'] ?? '') === 'manage_classes' ? 'active' : ''; ?>" style="color: #1c1c1c; padding: 8px 12px; border-radius: 12px; font-size: 14px; <?php echo ($_GET['page'] ?? '') === 'manage_classes' ? 'background: rgba(0,0,0,0.04);' : ''; ?>">
            <svg class="me-2" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
            </svg>
            Mes Classes
        </a>

        <a href="index.php?page=add_student" class="nav-link d-flex align-items-center <?php echo ($_GET['page'] ?? '') === 'add_student' ? 'active' : ''; ?>" style="color: #1c1c1c; padding: 8px 12px; border-radius: 12px; font-size: 14px; <?php echo ($_GET['page'] ?? '') === 'add_student' ? 'background: rgba(0,0,0,0.04);' : ''; ?>">
            <svg class="me-2" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="8.5" cy="7" r="4"></circle>
                <line x1="20" y1="8" x2="20" y2="14"></line>
                <line x1="23" y1="11" x2="17" y2="11"></line>
            </svg>
            Ajouter Élève
        </a>

        <!-- Pages Section -->
        <div style="font-size: 12px; color: rgba(0,0,0,0.4); padding: 8px 12px; margin-top: 12px;">Pages</div>

        <a href="index.php?page=points_log" class="nav-link d-flex align-items-center <?php echo ($_GET['page'] ?? '') === 'points_log' ? 'active' : ''; ?>" style="color: #1c1c1c; padding: 8px 12px; border-radius: 12px; font-size: 14px; <?php echo ($_GET['page'] ?? '') === 'points_log' ? 'background: rgba(0,0,0,0.04);' : ''; ?>">
            <svg class="me-2" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="20" x2="18" y2="10"></line>
                <line x1="12" y1="20" x2="12" y2="4"></line>
                <line x1="6" y1="20" x2="6" y2="14"></line>
            </svg>
            Historique Points
        </a>

        <a href="index.php?page=absences" class="nav-link d-flex align-items-center <?php echo ($_GET['page'] ?? '') === 'absences' ? 'active' : ''; ?>" style="color: #1c1c1c; padding: 8px 12px; border-radius: 12px; font-size: 14px; <?php echo ($_GET['page'] ?? '') === 'absences' ? 'background: rgba(0,0,0,0.04);' : ''; ?>">
            <svg class="me-2" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="6" x2="12" y2="12"></line>
                <line x1="12" y1="12" x2="15" y2="15"></line>
            </svg>
            Absences
        </a>

        <a href="index.php?page=teams" class="nav-link d-flex align-items-center <?php echo ($_GET['page'] ?? '') === 'teams' ? 'active' : ''; ?>" style="color: #1c1c1c; padding: 8px 12px; border-radius: 12px; font-size: 14px; <?php echo ($_GET['page'] ?? '') === 'teams' ? 'background: rgba(0,0,0,0.04);' : ''; ?>">
            <svg class="me-2" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
            Équipes
        </a>

        <!-- Contrôles Section with submenu -->
        <div style="font-size: 12px; color: rgba(0,0,0,0.4); padding: 8px 12px; margin-top: 12px;">Contrôles</div>

        <a href="index.php?page=controle_templates" class="nav-link d-flex align-items-center <?php echo in_array($_GET['page'] ?? '', ['controle_templates', 'controle_template_edit']) ? 'active' : ''; ?>" style="color: #1c1c1c; padding: 8px 12px; border-radius: 12px; font-size: 14px; <?php echo in_array($_GET['page'] ?? '', ['controle_templates', 'controle_template_edit']) ? 'background: rgba(0,0,0,0.04);' : ''; ?>">
            <svg class="me-2" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="16" y1="13" x2="8" y2="13"></line>
                <line x1="16" y1="17" x2="8" y2="17"></line>
            </svg>
            Modèles
        </a>

        <a href="index.php?page=controle_fill_points" class="nav-link d-flex align-items-center <?php echo in_array($_GET['page'] ?? '', ['controle_fill_points', 'controle_fill_notes', 'controle_instance_results']) ? 'active' : ''; ?>" style="color: #1c1c1c; padding: 8px 12px; border-radius: 12px; font-size: 14px; <?php echo in_array($_GET['page'] ?? '', ['controle_fill_points', 'controle_fill_notes', 'controle_instance_results']) ? 'background: rgba(0,0,0,0.04);' : ''; ?>">
            <svg class="me-2" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
            </svg>
            Saisie des Notes
        </a>

        <a href="index.php?page=certificates" class="nav-link d-flex align-items-center <?php echo ($_GET['page'] ?? '') === 'certificates' ? 'active' : ''; ?>" style="color: #1c1c1c; padding: 8px 12px; border-radius: 12px; font-size: 14px; <?php echo ($_GET['page'] ?? '') === 'certificates' ? 'background: rgba(0,0,0,0.04);' : ''; ?>">
            <svg class="me-2" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="8" r="7"></circle>
                <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline>
            </svg>
            Certificats
        </a>

        <a href="index.php?page=profile" class="nav-link d-flex align-items-center <?php echo ($_GET['page'] ?? '') === 'profile' ? 'active' : ''; ?>" style="color: #1c1c1c; padding: 8px 12px; border-radius: 12px; font-size: 14px; <?php echo ($_GET['page'] ?? '') === 'profile' ? 'background: rgba(0,0,0,0.04);' : ''; ?>">
            <svg class="me-2" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
            </svg>
            Mon Profil
        </a>

        <div class="mt-auto">
            <a href="index.php?page=logout" class="nav-link d-flex align-items-center" style="color: #1c1c1c; padding: 8px 12px; border-radius: 12px; font-size: 14px;">
                <svg class="me-2" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
                Déconnexion
            </a>
        </div>
    </nav>
</div>

<!-- Mobile Offcanvas Sidebar -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarOffcanvasLabel" style="width: 212px; background: #fff;">
    <div class="offcanvas-header" style="border-bottom: 0.5px solid rgba(0,0,0,0.1);">
        <h5 class="offcanvas-title d-flex align-items-center" id="sidebarOffcanvasLabel" style="font-size: 14px; font-weight: 600; color: #1c1c1c;">
            <div class="logo-icon me-2 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: rgba(0,0,0,0.04); border-radius: 8px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                </svg>
            </div>
            No9ati
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fermer"></button>
    </div>
    <div class="offcanvas-body p-3">
        <nav class="nav flex-column" style="gap: 4px;">
            <div style="font-size: 12px; color: rgba(0,0,0,0.4); padding: 8px 12px;">Favorites</div>
            
            <a href="index.php?page=dashboard" class="nav-link d-flex align-items-center <?php echo ($_GET['page'] ?? '') === 'dashboard' || empty($_GET['page']) ? 'active' : ''; ?>" style="color: #1c1c1c; padding: 8px 12px; border-radius: 12px; font-size: 14px; <?php echo ($_GET['page'] ?? '') === 'dashboard' || empty($_GET['page']) ? 'background: rgba(0,0,0,0.04);' : ''; ?>">
                <svg class="me-2" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
                Tableau de bord
            </a>

            <div style="font-size: 12px; color: rgba(0,0,0,0.4); padding: 8px 12px; margin-top: 12px;">Dashboards</div>

            <a href="index.php?page=manage_classes" class="nav-link d-flex align-items-center <?php echo ($_GET['page'] ?? '') === 'manage_classes' ? 'active' : ''; ?>" style="color: #1c1c1c; padding: 8px 12px; border-radius: 12px; font-size: 14px; <?php echo ($_GET['page'] ?? '') === 'manage_classes' ? 'background: rgba(0,0,0,0.04);' : ''; ?>">
                <svg class="me-2" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                </svg>
                Mes Classes
            </a>

            <a href="index.php?page=add_student" class="nav-link d-flex align-items-center <?php echo ($_GET['page'] ?? '') === 'add_student' ? 'active' : ''; ?>" style="color: #1c1c1c; padding: 8px 12px; border-radius: 12px; font-size: 14px; <?php echo ($_GET['page'] ?? '') === 'add_student' ? 'background: rgba(0,0,0,0.04);' : ''; ?>">
                <svg class="me-2" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="8.5" cy="7" r="4"></circle>
                    <line x1="20" y1="8" x2="20" y2="14"></line>
                    <line x1="23" y1="11" x2="17" y2="11"></line>
                </svg>
                Ajouter Élève
            </a>

            <div style="font-size: 12px; color: rgba(0,0,0,0.4); padding: 8px 12px; margin-top: 12px;">Pages</div>

            <a href="index.php?page=points_log" class="nav-link d-flex align-items-center <?php echo ($_GET['page'] ?? '') === 'points_log' ? 'active' : ''; ?>" style="color: #1c1c1c; padding: 8px 12px; border-radius: 12px; font-size: 14px; <?php echo ($_GET['page'] ?? '') === 'points_log' ? 'background: rgba(0,0,0,0.04);' : ''; ?>">
                <svg class="me-2" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="20" x2="18" y2="10"></line>
                    <line x1="12" y1="20" x2="12" y2="4"></line>
                    <line x1="6" y1="20" x2="6" y2="14"></line>
                </svg>
                Historique Points
            </a>

            <a href="index.php?page=absences" class="nav-link d-flex align-items-center <?php echo ($_GET['page'] ?? '') === 'absences' ? 'active' : ''; ?>" style="color: #1c1c1c; padding: 8px 12px; border-radius: 12px; font-size: 14px; <?php echo ($_GET['page'] ?? '') === 'absences' ? 'background: rgba(0,0,0,0.04);' : ''; ?>">
                <svg class="me-2" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="6" x2="12" y2="12"></line>
                    <line x1="12" y1="12" x2="15" y2="15"></line>
                </svg>
                Absences
            </a>

            <a href="index.php?page=teams" class="nav-link d-flex align-items-center <?php echo ($_GET['page'] ?? '') === 'teams' ? 'active' : ''; ?>" style="color: #1c1c1c; padding: 8px 12px; border-radius: 12px; font-size: 14px; <?php echo ($_GET['page'] ?? '') === 'teams' ? 'background: rgba(0,0,0,0.04);' : ''; ?>">
                <svg class="me-2" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                Équipes
            </a>

            <div style="font-size: 12px; color: rgba(0,0,0,0.4); padding: 8px 12px; margin-top: 12px;">Contrôles</div>

            <a href="index.php?page=controle_templates" class="nav-link d-flex align-items-center <?php echo in_array($_GET['page'] ?? '', ['controle_templates', 'controle_template_edit']) ? 'active' : ''; ?>" style="color: #1c1c1c; padding: 8px 12px; border-radius: 12px; font-size: 14px; <?php echo in_array($_GET['page'] ?? '', ['controle_templates', 'controle_template_edit']) ? 'background: rgba(0,0,0,0.04);' : ''; ?>">
                <svg class="me-2" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                </svg>
                Modèles
            </a>

            <a href="index.php?page=controle_fill_points" class="nav-link d-flex align-items-center <?php echo in_array($_GET['page'] ?? '', ['controle_fill_points', 'controle_fill_notes', 'controle_instance_results']) ? 'active' : ''; ?>" style="color: #1c1c1c; padding: 8px 12px; border-radius: 12px; font-size: 14px; <?php echo in_array($_GET['page'] ?? '', ['controle_fill_points', 'controle_fill_notes', 'controle_instance_results']) ? 'background: rgba(0,0,0,0.04);' : ''; ?>">
                <svg class="me-2" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
                Saisie des Notes
            </a>

            <a href="index.php?page=certificates" class="nav-link d-flex align-items-center <?php echo ($_GET['page'] ?? '') === 'certificates' ? 'active' : ''; ?>" style="color: #1c1c1c; padding: 8px 12px; border-radius: 12px; font-size: 14px; <?php echo ($_GET['page'] ?? '') === 'certificates' ? 'background: rgba(0,0,0,0.04);' : ''; ?>">
                <svg class="me-2" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="8" r="7"></circle>
                    <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline>
                </svg>
                Certificats
            </a>

            <a href="index.php?page=profile" class="nav-link d-flex align-items-center <?php echo ($_GET['page'] ?? '') === 'profile' ? 'active' : ''; ?>" style="color: #1c1c1c; padding: 8px 12px; border-radius: 12px; font-size: 14px; <?php echo ($_GET['page'] ?? '') === 'profile' ? 'background: rgba(0,0,0,0.04);' : ''; ?>">
                <svg class="me-2" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                Mon Profil
            </a>

            <a href="index.php?page=logout" class="nav-link d-flex align-items-center mt-4" style="color: #1c1c1c; padding: 8px 12px; border-radius: 12px; font-size: 14px;">
                <svg class="me-2" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
                Déconnexion
            </a>
        </nav>
    </div>
</div>