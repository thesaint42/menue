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
        
        <a class="navbar-brand fw-bold d-flex align-items-center" href="<?php echo $root; ?>admin/admin.php">
            <span style="font-size: 1.5em; margin-right: 10px;">🍽️</span>
            Menüwahl
            <span class="fw-normal text-secondary mx-2">|</span>
            <span class="fw-semibold text-info"><?php echo $display_name; ?></span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="<?php echo $root; ?>admin/projects.php">Projekte</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo $root; ?>admin/dishes.php">Menüs</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo $root; ?>admin/guests.php">Gäste</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo $root; ?>admin/orders.php">Bestellungen</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo $root; ?>admin/settings_mail.php">Mail</a></li>
            </ul>
        </div>

        <div class="ms-auto d-flex align-items-center">
            <div class="text-light me-3 d-none d-sm-inline">
                👤 <span class="small"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></span>
            </div>
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    ⚙️
                </button>
                <ul class="dropdown-menu dropdown-menu-end bg-dark border-secondary">
                    <li><a class="dropdown-item" href="<?php echo $root; ?>admin/profile.php">Profil</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?php echo $root; ?>admin/logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<style>
    .dropdown-item { color: #dee2e6 !important; }
    .dropdown-item:hover { background-color: #343a40 !important; color: #fff !important; }
    .dropdown-menu { background-color: #212529 !important; }
</style>
