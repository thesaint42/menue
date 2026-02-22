<?php
/**
 * admin/admin.php - Admin Dashboard
 */

require_once '../db.php';
require_once '../script/auth.php';

checkLogin();
$prefix = $config['database']['prefix'] ?? 'menu_';

// Access-Check: Dashboard-Berechtigung erforderlich
requireMenuAccess($pdo, 'dashboard', 'read', $prefix);

// Check v2.2.0 tables
$tables_check = checkV220Tables($pdo, $prefix);
$v220_ready = !in_array(false, $tables_check, true);

// Benutzer-Rolle und Berechtigungen ermitteln
$user_role_id = $_SESSION['role_id'] ?? null;
$is_admin = ($user_role_id === 1);

// Zugängliche Projekt-IDs ermitteln
$accessible_project_ids = [];
if ($is_admin) {
    // Systemadmin: Alle aktiven Projekte
    $stmt = $pdo->query("SELECT id FROM {$prefix}projects WHERE is_active = 1");
    $accessible_project_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
} else if (hasMenuAccess($pdo, 'projects_write', $prefix) || hasMenuAccess($pdo, 'projects_read', $prefix)) {
    // Projekt Admin oder Reporting User: nur zugewiesene Projekte
    $assigned = getUserProjects($pdo, $prefix);
    $accessible_project_ids = array_column($assigned, 'id');
}

// Statistiken basierend auf zugänglichen Projekten
// Projekte zählen
$project_count = count($accessible_project_ids);

// Gäste aus Bestellungen zählen (nicht Besteller)
$guest_count = 0;
if (!empty($accessible_project_ids)) {
    $placeholders = implode(',', array_fill(0, count($accessible_project_ids), '?'));
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT g.id) as count 
        FROM {$prefix}guests g
        INNER JOIN {$prefix}orders o ON g.order_id = o.id
        WHERE o.project_id IN ($placeholders)
    ");
    $stmt->execute($accessible_project_ids);
    $result = $stmt->fetch();
    $guest_count = $result ? $result['count'] : 0;
}

// Bestellungen zählen
$order_count = 0;
if (!empty($accessible_project_ids)) {
    $placeholders = implode(',', array_fill(0, count($accessible_project_ids), '?'));
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM {$prefix}orders WHERE project_id IN ($placeholders)");
    $stmt->execute($accessible_project_ids);
    $result = $stmt->fetch();
    $order_count = $result ? $result['count'] : 0;
}

// Aktuelle Projekte (nur zugängliche)
$recent_projects = [];
if (!empty($accessible_project_ids)) {
    $placeholders = implode(',', array_fill(0, count($accessible_project_ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM {$prefix}projects WHERE is_active = 1 AND id IN ($placeholders) ORDER BY created_at DESC LIMIT 5");
    $stmt->execute($accessible_project_ids);
    $recent_projects = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard - Event Menue Order System (EMOS)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include '../nav/top_nav.php'; ?>

<div class="container py-4">
    <?php if (!$v220_ready): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <strong>⚙️ System-Update erforderlich!</strong> Die neuen Features für v2.2.0 werden initialisiert... 
        <br><small>Bitte aktualisieren Sie die Seite nach wenigen Sekunden.</small>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <script>
        setTimeout(() => { location.reload(); }, 2000);
    </script>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-12">
            <h2 class="mb-4">Willkommen, <?php echo htmlspecialchars($_SESSION['user_name']); ?>! 👋</h2>
        </div>
    </div>

    <!-- STATISTIK KARTEN -->
    <div class="row g-3 g-md-4 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm bg-primary text-white">
                <div class="card-body text-center p-3">
                    <h6 class="text-uppercase opacity-75 small mb-2">Projekte</h6>
                    <h2 class="display-6 display-md-5 fw-bold mb-0"><?php echo $project_count; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm bg-success text-white">
                <div class="card-body text-center p-3">
                    <h6 class="text-uppercase opacity-75 small mb-2">Gäste</h6>
                    <h2 class="display-6 display-md-5 fw-bold mb-0"><?php echo $guest_count; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm bg-info text-white">
                <div class="card-body text-center p-3">
                    <h6 class="text-uppercase opacity-75 small mb-2">Bestellungen</h6>
                    <h2 class="display-6 display-md-5 fw-bold mb-0"><?php echo $order_count; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm bg-warning text-dark">
                <div class="card-body text-center p-3">
                    <h6 class="text-uppercase opacity-75 small mb-2">Plätze belegt</h6>
                    <h2 class="display-6 display-md-5 fw-bold mb-0">
                        <?php 
                            $max = 0;
                            if (!empty($accessible_project_ids)) {
                                $placeholders = implode(',', array_fill(0, count($accessible_project_ids), '?'));
                                $stmt = $pdo->prepare("SELECT SUM(max_guests) as total FROM {$prefix}projects WHERE is_active = 1 AND id IN ($placeholders)");
                                $stmt->execute($accessible_project_ids);
                                $result = $stmt->fetch();
                                $max = ($result && isset($result['total'])) ? $result['total'] : 0;
                            }
                            echo $max > 0 ? round(($guest_count / $max) * 100) : 0;
                        ?>%
                    </h2>
                </div>
            </div>
        </div>
    </div>

    <!-- SCHNELLZUGRIFF -->
    <div class="row g-3 g-md-4 mb-4">
        <div class="col-12 col-md-6">
            <div class="card border-0 shadow">
                <div class="card-body">
                    <h5 class="card-title mb-3">Neue Aktion</h5>
                    <div class="d-grid gap-2">
                        <a href="projects.php" class="btn btn-outline-primary">+ Neues Projekt</a>
                        <a href="dishes.php" class="btn btn-outline-primary">+ Menü hinzufügen</a>
                        <a href="settings_mail.php" class="btn btn-outline-primary">⚙️ Mail-Einstellungen</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6">
            <div class="card border-0 shadow">
                <div class="card-body">
                    <h5 class="card-title mb-3">Verwaltung</h5>
                    <div class="d-grid gap-2">
                        <a href="projects.php" class="btn btn-outline-info">📋 Projekte verwalten</a>
                        <a href="guests.php" class="btn btn-outline-info">👥 Gäste anzeigen</a>
                        <a href="export_pdf.php" class="btn btn-outline-info">📄 PDF exportieren</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- AKTUELLE PROJEKTE -->
    <?php if (!empty($recent_projects)): ?>
        <div class="card border-0 shadow">
            <div class="card-header bg-info text-white py-3">
                <h5 class="mb-0">Aktuelle Projekte</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Name</th>
                            <th class="d-none d-md-table-cell">Ort</th>
                            <th>Gäste</th>
                            <th class="d-none d-sm-table-cell">Max. Plätze</th>
                            <th>Aktion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_projects as $p): ?>
                            <?php 
                                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM {$prefix}guests WHERE project_id = ?");
                                $stmt->execute([$p['id']]);
                                $p_guests = $stmt->fetch()['count'];
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($p['name']); ?></strong></td>
                                <td class="d-none d-md-table-cell"><?php echo htmlspecialchars($p['location'] ?? '–'); ?></td>
                                <td><?php echo $p_guests; ?></td>
                                <td class="d-none d-sm-table-cell"><?php echo $p['max_guests']; ?></td>
                                <td>
                                    <a href="guests.php?project=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-info">Details</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
