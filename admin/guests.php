<?php
/**
 * admin/guests.php - Gästeübersicht
 */

require_once '../db.php';
require_once '../script/auth.php';

checkLogin();

$prefix = $config['database']['prefix'] ?? 'menu_';
$project_id = isset($_GET['project']) ? (int)$_GET['project'] : null;
$message = "";
$messageType = "info";

if (!$project_id) {
    $projects = $pdo->query("SELECT * FROM {$prefix}projects WHERE is_active = 1 ORDER BY name")->fetchAll();
    if (empty($projects)) {
        echo "<div class='container mt-5'><div class='alert alert-warning'>Keine Projekte vorhanden.</div></div>";
        exit;
    }
    $project_id = $projects[0]['id'];
}

// Projekt laden
$stmt = $pdo->prepare("SELECT * FROM {$prefix}projects WHERE id = ?");
$stmt->execute([$project_id]);
$project = $stmt->fetch();

if (!$project) {
    die("Projekt nicht gefunden.");
}

// Gäste laden
$stmt = $pdo->prepare("SELECT g.*, COUNT(o.id) as order_count FROM {$prefix}guests g 
                       LEFT JOIN {$prefix}orders o ON g.id = o.guest_id 
                       WHERE g.project_id = ? GROUP BY g.id ORDER BY g.created_at DESC");
$stmt->execute([$project_id]);
$guests = $stmt->fetchAll();

// Projekte für Dropdown
$projects = $pdo->query("SELECT * FROM {$prefix}projects WHERE is_active = 1 ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>Gästeübersicht - Event Menue Order System (EMOS)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include '../nav/top_nav.php'; ?>

<div class="container py-4">
    <h2 class="mb-4">Gästeübersicht</h2>

    <!-- PROJEKT FILTER -->
    <div class="mb-4">
        <label class="form-label">Projekt auswählen:</label>
        <div class="btn-group w-100" role="group">
            <?php foreach ($projects as $p): ?>
                <a href="?project=<?php echo $p['id']; ?>" class="btn btn-outline-secondary <?php echo $p['id'] == $project_id ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($p['name']); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- GÄSTE TABELLE -->
    <div class="card border-0 shadow">
        <div class="card-header bg-success text-white py-3">
            <h5 class="mb-0"><?php echo htmlspecialchars($project['name']); ?> - <?php echo count($guests); ?> Gäste</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Tel.</th>
                        <th>Typ</th>
                        <th>Alter</th>
                        <th>Bestellungen</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($guests)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Noch keine Gäste angemeldet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($guests as $g): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($g['firstname'] . ' ' . $g['lastname']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($g['email']); ?></td>
                                <td><?php echo htmlspecialchars($g['phone'] ?? '–'); ?></td>
                                <td>
                                    <?php echo $g['guest_type'] === 'family' ? 'Familie' : 'Einzelperson'; ?>
                                    <?php if ($g['guest_type'] === 'family'): ?>
                                        <small class="text-muted">(<?php echo $g['family_size']; ?> Pers.)</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $g['age_group'] === 'child' ? 'Kind ' . $g['child_age'] . 'J.' : 'Erwachsen'; ?>
                                </td>
                                <td><?php echo $g['order_count']; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $g['order_status'] === 'confirmed' ? 'success' : 'warning'; ?>">
                                        <?php 
                                            $status_map = ['pending' => 'Ausstehend', 'confirmed' => 'Bestätigt', 'cancelled' => 'Storniert'];
                                            echo $status_map[$g['order_status']] ?? $g['order_status'];
                                        ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
