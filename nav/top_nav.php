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
    'export_pdf' => 'PDF Export',
    'settings_mail' => 'Mail Einstellungen',
    'profile' => 'Mein Profil'
];

$display_name = $page_names[$current_page] ?? ucfirst($current_page);
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark border-bottom border-secondary mb-4">
    <div class="container-fluid px-4">
        
        <a class="navbar-brand fw-bold d-flex align-items-center" href="<?php echo $root; ?>admin/admin.php"
>                                                                                                                       <span style="font-size: 1.5em; margin-right: 10px;">🍽️</span>
            Menüwahl
            <span class="fw-normal text-secondary mx-2">|</span>
            <span class="fw-semibold text-info"><?php echo $display_name; ?></span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="<?php echo $root; ?>admin/projects.php">Proje
kte</a></li>                                                                                                                <li class="nav-item"><a class="nav-link" href="<?php echo $root; ?>admin/dishes.php">Menüs</
a></li>                                                                                                                     <li class="nav-item"><a class="nav-link" href="<?php echo $root; ?>admin/guests.php">Gäste</
a></li>                                                                                                                     <li class="nav-item"><a class="nav-link" href="<?php echo $root; ?>admin/orders.php">Bestell
ungen</a></li>                                                                                                              <li class="nav-item"><a class="nav-link" href="<?php echo $root; ?>admin/settings_mail.php">
Mail</a></li>                                                                                                           </ul>
        </div>

        <div class="ms-auto d-flex align-items-center">
            <div class="text-light me-3 d-none d-sm-inline">
                👤 <span class="small"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></sp
an>                                                                                                                     </div>
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggl
e="dropdown">                                                                                                                   ⚙️
                </button>
                <ul class="dropdown-menu dropdown-menu-end bg-dark border-secondary">
                    <li><a class="dropdown-item" href="<?php echo $root; ?>admin/profile.php">Profil</a></li
>                                                                                                                               <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?php echo $root; ?>admin/logout.php">Log
out</a></li>                                                                                                                </ul>
            </div>
        </div>
    </div>
</nav>

<style>
    .dropdown-item { color: #dee2e6 !important; }
    .dropdown-item:hover { background-color: #343a40 !important; color: #fff !important; }
    .dropdown-menu { background-color: #212529 !important; }
</style>

<nav class="navbar navbar-dark bg-dark border-bottom border-secondary mb-4">
    <div class="container-fluid px-4">
        <?php
        // include auth helpers (use __DIR__ for reliable path resolution)
        @include_once __DIR__ . '/../script/auth.php';

        // decide home link: admin users -> admin dashboard, regular users -> public index
        $home_href = (function() use ($root) {
            if (function_exists('isAdmin') && isAdmin()) {
                return $root . 'admin/admin.php';
            }
            return $root . 'index.php';
        })();
        ?>

        <!-- Logo / Marke (links) -->
        <a class="navbar-brand fw-bold d-flex align-items-center" href="<?php echo $home_href; ?>">
            <img src="<?php echo $root; ?>img/logo.png" alt="Event Menue Order System (EMOS)" style="height:32px; width:auto; margin-right:10px;" />
            <span>Event Menue Order System (EMOS)</span>
            <span class="fw-normal text-secondary mx-2">|</span>
            <span class="fw-semibold text-info"><?php echo $display_name; ?></span>
        </a>

        <!-- User-Info mit Dropdown + Hamburger-Menü (Rechts) -->
        <div class="d-flex align-items-center gap-2 ms-auto">
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle d-flex align-items-center gap-2" 
                        type="button" data-bs-toggle="dropdown" style="border: none;">
                    <span style="font-size: 1.2em;">👤</span>
                    <span class="d-none d-sm-inline text-light small">
                        <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?>
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end bg-dark border-secondary">
                    <li><a class="dropdown-item" href="<?php echo $root; ?>admin/profile.php">Profil bearbeiten</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?php echo $root; ?>admin/logout.php">Logout</a></li>
                </ul>
            </div>
            
            <!-- Hamburger-Menü (Rechts neben User) -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>
    </div>

    <!-- Kollapsible Navigation (unter dem Hamburger) -->
    <div class="collapse navbar-collapse" id="navbarNav">
        <div class="container-fluid px-4">
            <ul class="navbar-nav ms-auto mt-2 mb-2">
                <li class="nav-item"><a class="nav-link text-end" href="<?php echo $root; ?>admin/projects.php">Projekte</a></li>
                <li class="nav-item"><a class="nav-link text-end" href="<?php echo $root; ?>admin/dishes.php">Menüs</a></li>
                <li class="nav-item"><a class="nav-link text-end" href="<?php echo $root; ?>admin/guests.php">Gäste</a></li>
                <li class="nav-item"><a class="nav-link text-end" href="<?php echo $root; ?>admin/orders.php">Bestellungen</a></li>
                <li class="nav-item"><a class="nav-link text-end" href="<?php echo $root; ?>admin/settings_mail.php">Mail Einstellungen</a></li>
                <li class="nav-item"><a class="nav-link text-end" href="<?php echo $root; ?>migrate.php">Migrationen</a></li>
                <li class="nav-item"><a class="nav-link text-end" href="<?php echo $root; ?>admin/backup.php">Backup</a></li>
            </ul>
        </div>
    </div>
</nav>

<style>
    .navbar-collapse {
        position: fixed !important;
        top: 60px;
        right: 0;
        left: auto;
        background-color: #212529;
        border-left: 1px solid #495057;
        border-bottom: 1px solid #495057;
        z-index: 1000;
        width: auto;
        min-width: 250px;
        box-shadow: -2px 2px 8px rgba(0,0,0,0.3);
        visibility: hidden;
        opacity: 0;
        transition: opacity 0.3s ease-in-out, visibility 0.3s ease-in-out;
    }
    
    .navbar-collapse.show {
        visibility: visible;
        opacity: 1;
    }
    
    /* Backdrop/Overlay */
    .navbar-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        z-index: 999;
        visibility: hidden;
        opacity: 0;
        transition: opacity 0.3s ease-in-out, visibility 0.3s ease-in-out;
    }
    
    .navbar-backdrop.show {
        visibility: visible;
        opacity: 1;
    }
    
    .dropdown-item { color: #dee2e6 !important; }
    .dropdown-item:hover { background-color: #343a40 !important; color: #fff !important; }
    .dropdown-menu { background-color: #212529 !important; }
</style>

<!-- Backdrop/Overlay für Menü -->
<div class="navbar-backdrop" id="navbarBackdrop"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const navCollapse = document.querySelector('.navbar-collapse');
    const navBackdrop = document.getElementById('navbarBackdrop');
    const navLinks = document.querySelectorAll('.navbar-collapse .nav-link');
    
    // Backdrop und Menü Zustand synchronisieren (wird von Bootstrap triggered)
    navCollapse.addEventListener('show.bs.collapse', function() {
        navBackdrop.classList.add('show');
    });
    
    navCollapse.addEventListener('hide.bs.collapse', function() {
        navBackdrop.classList.remove('show');
    });
    
    // Menü schließen wenn auf Backdrop geklickt
    navBackdrop.addEventListener('click', function() {
        navCollapse.classList.remove('show');
        navBackdrop.classList.remove('show');
    });
    
    // Menü schließen wenn auf Link geklickt
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            navCollapse.classList.remove('show');
            navBackdrop.classList.remove('show');
        });
    });
});
</script>

<!-- Duplicate intl-tel-input init removed (enhanced version present above) -->

<?php
// Defer footer output: capture footer HTML and append it to the end of the document
ob_start();
@include __DIR__ . '/footer.php';
$footer_html = ob_get_clean();
?>

<template id="site-footer-template" aria-hidden="true">
<?php echo $footer_html; ?>
</template>

<script>
document.addEventListener('DOMContentLoaded', function(){
    try {
        var tpl = document.getElementById('site-footer-template');
        if (tpl && tpl.content) {
            document.body.appendChild(tpl.content.cloneNode(true));
        }
    } catch(e) {
        // fallback: write footer directly
        try { document.body.insertAdjacentHTML('beforeend', `<?php echo str_replace("`","\\`", str_replace("</script>", "<\/script>", $footer_html)); ?>`); } catch(e) {}
    }
});
</script>
