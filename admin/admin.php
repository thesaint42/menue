<?php
/**
 * admin/admin.php - Admin Dashboard
 */

require_once '../db.php';
require_once '../script/auth.php';

checkLogin();
$prefix = $config['database']['prefix'] ?? 'menu_';

// Statistiken
$stmt = $pdo->query("SELECT COUNT(*) as count FROM {$prefix}projects WHERE is_active = 1");
$project_count = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM {$prefix}guests");
$guest_count = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM {$prefix}orders");
$order_count = $stmt->fetch()['count'];

// Aktuelle Projekte
$stmt = $pdo->query("SELECT * FROM {$prefix}projects WHERE is_active = 1 ORDER BY created_at DESC LIMIT 5");
$recent_projects = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Menüwahl</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include '../nav/top_nav.php'; ?>

<div class="container py-4">
    <div class="row">
        <div class="col-lg-12">
            <h2 class="mb-4">Willkommen, <?php echo htmlspecialchars($_SESSION['user_name']); ?>! 👋</h2>
        </div>
    </div>

    <!-- STATISTIK KARTEN -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-primary text-white">
                <div class="card-body text-center">
                    <h6 class="text-uppercase opacity-75 small">Projekte</h6>
                    <h2 class="display-5 fw-bold"><?php echo $project_count; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-success text-white">
                <div class="card-body text-center">
                    <h6 class="text-uppercase opacity-75 small">Gäste</h6>
                    <h2 class="display-5 fw-bold"><?php echo $guest_count; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-info text-white">
                <div class="card-body text-center">
                    <h6 class="text-uppercase opacity-75 small">Bestellungen</h6>
                    <h2 class="display-5 fw-bold"><?php echo $order_count; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-warning text-dark">
                <div class="card-body text-center">
                    <h6 class="text-uppercase opacity-75 small">Plätze belegt</h6>
                    <h2 class="display-5 fw-bold">
                        <?php 
                            $stmt = $pdo->query("SELECT SUM(max_guests) as total FROM {$prefix}projects WHERE is_active = 1");
                            $max = $stmt->fetch()['total'] ?? 0;
                            echo $max > 0 ? round(($guest_count / $max) * 100) : 0;
                        ?>%
                    </h2>
                </div>
            </div>
        </div>
    </div>

    <!-- SCHNELLZUGRIFF -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow">
                <div class="card-body">
                    <h5 class="card-title">Neue Aktion</h5>
                    <div class="d-grid gap-2">
                        <a href="projects.php" class="btn btn-outline-primary">+ Neues Projekt</a>
                        <a href="dishes.php" class="btn btn-outline-primary">+ Menü hinzufügen</a>
                        <a href="settings_mail.php" class="btn btn-outline-primary">⚙️ Mail-Einstellungen</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card border-0 shadow">
                <div class="card-body">
                    <h5 class="card-title">Verwaltung</h5>
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
                            <th>Ort</th>
                            <th>Gäste</th>
                            <th>Max. Plätze</th>
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
                                <td><?php echo htmlspecialchars($p['location'] ?? '–'); ?></td>
                                <td><?php echo $p_guests; ?></td>
                                <td><?php echo $p['max_guests']; ?></td>
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
