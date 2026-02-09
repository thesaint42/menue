<?php
/**
 * nav/top_nav.php - Hauptnavigation für Admin-Bereich
 */

$is_admin_dir = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false);
$root = $is_admin_dir ? '../' : './';
$current_page = basename($_SERVER['PHP_SELF'], ".php");

$page_names = [
    'admin' => 'Dashboard',
    'projects' => 'Projekte',
    'dishes' => 'Menüs',
    'guests' => 'Gäste',
    'orders' => 'Bestellungen',
    'reports' => 'Reporting',
    'settings_mail' => 'Mail Einstellungen',
    'profile' => 'Mein Profil'
    , 'vvt' => 'VVT'
    , 'migrate' => 'Migration'
    , 'backup' => 'Backup'
];

$display_name = $page_names[$current_page] ?? ucfirst($current_page);
?>

<!-- Single EMOS-branded top navigation -->
<nav class="navbar navbar-dark bg-dark border-bottom border-secondary mb-4">
            <div class="container-fluid px-4">
                <?php
                @include_once __DIR__ . '/../script/auth.php';
                $is_logged_in = function_exists('isLoggedIn') && isLoggedIn();
                $home_href = (function() use ($root, $is_logged_in) {
                    if ($is_logged_in && function_exists('isAdmin') && isAdmin()) {
                        return $root . 'admin/admin.php';
                    }
                    return $root . 'index.php';
                })();
                ?>

                <div class="navbar-brand fw-bold d-flex align-items-center">
                    <a class="nav-home-link d-flex align-items-center text-decoration-none text-light" href="<?php echo $home_href; ?>" aria-label="Zur Startseite">
                        <img src="<?php echo $root; ?>img/logo.png" alt="EMOS" style="height:32px; width:auto; margin-right:10px;" />
                        <span class="fw-bold nav-brand-names">
                            <span class="full-name d-none d-md-inline">Event Menue Order System (EMOS)</span>
                            <span class="short-name d-inline d-md-none">EMOS</span>
                        </span>
                    </a>
                    <span class="fw-normal text-secondary mx-2">|</span>
                    <span class="fw-semibold text-info"><?php echo $display_name; ?></span>
                </div>

                <div class="d-flex align-items-center gap-2 ms-auto">
                    <?php if ($is_logged_in): ?>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" style="border: none;">
                            <span style="font-size: 1.2em;">👤</span>
                            <span class="d-none d-sm-inline text-light small"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end bg-dark border-secondary">
                            <li><a class="dropdown-item" href="<?php echo $root; ?>admin/profile.php">Profil bearbeiten</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?php echo $root; ?>admin/logout.php">Logout</a></li>
                        </ul>
                    </div>
                    <?php else: ?>
                    <div class="d-flex align-items-center text-light small me-2">Gast</div>
                    <?php endif; ?>
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                </div>
            </div>

                <div class="collapse navbar-collapse" id="navbarNav">
                <div class="container-fluid px-4">
                    <ul class="navbar-nav ms-auto mt-2 mb-2">
                        <?php if ($is_logged_in && function_exists('isAdmin') && isAdmin()): ?>
                            <li class="nav-item"><a class="nav-link text-end" href="<?php echo $root; ?>index.php">Zur Startseite</a></li>
                            <li class="nav-item">
                                <div class="nav-separator my-2"></div>
                            </li>
                            <li class="nav-item"><span class="nav-link text-end project-header small">Projektverwaltung</span></li>
                            <li class="nav-item"><a class="nav-link text-end" href="<?php echo $root; ?>admin/projects.php">Projekte</a></li>
                            <li class="nav-item"><a class="nav-link text-end" href="<?php echo $root; ?>admin/dishes.php">Menüs</a></li>
                            <li class="nav-item"><a class="nav-link text-end" href="<?php echo $root; ?>admin/guests.php">Gäste</a></li>
                            <li class="nav-item"><a class="nav-link text-end" href="<?php echo $root; ?>admin/orders.php">Bestellungen</a></li>
                            <li class="nav-item"><a class="nav-link text-end" href="<?php echo $root; ?>admin/reports.php">Reporting</a></li>

                            <li class="nav-item">
                                <div class="nav-separator my-2"></div>
                            </li>
                            <li class="nav-item"><span class="nav-link text-end system-header small">System</span></li>
                            <li class="nav-item"><a class="nav-link text-end" href="<?php echo $root; ?>admin/settings_mail.php">Mail Einstellungen</a></li>
                            <li class="nav-item"><a class="nav-link text-end" href="<?php echo $root; ?>migrate.php">Migration</a></li>
                            <li class="nav-item"><a class="nav-link text-end" href="<?php echo $root; ?>admin/backup.php">Backup</a></li>
                        <?php else: ?>
                            <li class="nav-item"><a class="nav-link text-end" href="<?php echo $root; ?>index.php">Startseite</a></li>
                            <li class="nav-item"><a class="nav-link text-end" href="<?php echo $root; ?>admin/login.php">Admin Login</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>

        <style>
            /* Brand name: full on md+, short on small screens */
            .nav-brand-names { line-height: 1; }
            .nav-brand-names .full-name { white-space: nowrap; }
            .nav-brand-names .short-name { white-space: nowrap; }
            .dropdown-item { color: #dee2e6 !important; }
            .dropdown-item:hover { background-color: #343a40 !important; color: #fff !important; }
            .dropdown-menu { background-color: #212529 !important; }
            .navbar-collapse { position: fixed !important; top: 60px; right: 0; left: auto; background-color: #212529; border-left: 1px solid #495057; border-bottom: 1px solid #495057; z-index: 1000; width: auto; min-width: 250px; box-shadow: -2px 2px 8px rgba(0,0,0,0.3); visibility: hidden; opacity: 0; transition: opacity 0.3s ease-in-out, visibility 0.3s ease-in-out; }
            .navbar-collapse.show { visibility: visible; opacity: 1; }
            .navbar-backdrop { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 999; visibility: hidden; opacity: 0; transition: opacity 0.3s ease-in-out, visibility 0.3s ease-in-out; }
            .navbar-backdrop.show { visibility: visible; opacity: 1; }

            /* Visible separator in collapsed burger menu */
            .navbar-nav .nav-separator { width: 100%; height: 1px; background-color: #6c757d; }
            /* Section headers: bold + uppercase */
            .nav-section-header { font-weight: 700; text-transform: uppercase; letter-spacing: 0.02em; }
            .project-header { color: #ff7a00 !important; }
            /* System header highlight */
            .system-header { color: #ff7a00 !important; }
            .system-header, .project-header { font-weight: 700; text-transform: uppercase; }
        </style>

        <div class="navbar-backdrop" id="navbarBackdrop"></div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const navCollapse = document.querySelector('.navbar-collapse');
            const navBackdrop = document.getElementById('navbarBackdrop');
            const navLinks = document.querySelectorAll('.navbar-collapse .nav-link');
            if (!navCollapse) return;
            navCollapse.addEventListener('show.bs.collapse', function() { navBackdrop.classList.add('show'); });
            navCollapse.addEventListener('hide.bs.collapse', function() { navBackdrop.classList.remove('show'); });
            navBackdrop.addEventListener('click', function() { navCollapse.classList.remove('show'); navBackdrop.classList.remove('show'); });
            navLinks.forEach(link => link.addEventListener('click', function() { navCollapse.classList.remove('show'); navBackdrop.classList.remove('show'); }));
        });
        </script>
