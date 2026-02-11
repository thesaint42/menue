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
        // Saubere Fehlerseite anzeigen: kein Projekt angelegt
        ?>
        <!DOCTYPE html>
        <html lang="de" data-bs-theme="dark">
        <head>
            <meta charset="UTF-8">
            <title>Keine Projekte - EMOS</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="../assets/css/style.css" rel="stylesheet">
        </head>
        <body>
        <?php include '../nav/top_nav.php'; ?>
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-12 col-md-8">
                    <div class="card border-0 shadow">
                        <div class="card-body text-center py-5">
                            <h3 class="mb-3">Keine Projekte vorhanden</h3>
                            <p class="text-muted">Es wurde noch kein Projekt angelegt. Bitte legen Sie zuerst ein Projekt an, damit Gäste verwaltet werden können.</p>
                                <a href="projects.php" class="btn btn-primary mt-3">Projekt anlegen</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include '../nav/footer.php'; ?>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        </body>
        </html>
        <?php
        exit;
    }
    $project_id = $projects[0]['id'];
}

// Projekt laden
$stmt = $pdo->prepare("SELECT * FROM {$prefix}projects WHERE id = ?");
$stmt->execute([$project_id]);
$project = $stmt->fetch();

if (!$project) {
    // Projekt nicht gefunden – saubere Fehlerseite
    ?>
    <!DOCTYPE html>
    <html lang="de" data-bs-theme="dark">
    <head>
        <meta charset="UTF-8">
        <title>Projekt nicht gefunden - EMOS</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="../assets/css/style.css" rel="stylesheet">
    </head>
    <body>
    <?php include '../nav/top_nav.php'; ?>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8">
                <div class="card border-0 shadow">
                    <div class="card-body text-center py-5">
                        <h3 class="mb-3">Projekt nicht gefunden</h3>
                        <p class="text-muted">Das angeforderte Projekt existiert nicht oder wurde gelöscht.</p>
                        <a href="projects.php" class="btn btn-primary mt-3">Zur Projektübersicht</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include '../nav/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    exit;
}

// Tabellenverfügbarkeit für Snapshot prüfen
$stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
$stmt->execute(["{$prefix}order_guest_data"]);
$has_order_guest_data = $stmt->fetchColumn() > 0;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
$stmt->execute(["{$prefix}order_people"]);
$has_order_people = $stmt->fetchColumn() > 0;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'order_id'");
$stmt->execute(["{$prefix}guests"]);
$has_guest_order_id = $stmt->fetchColumn() > 0;

// Gast löschen (inkl. Bestellung)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_guest_id'])) {
    $delete_guest_id = (int)$_POST['delete_guest_id'];
    $delete_order_id = trim($_POST['delete_order_id'] ?? '');

    if ($delete_guest_id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM {$prefix}guests WHERE id = ? AND project_id = ? LIMIT 1");
        $stmt->execute([$delete_guest_id, $project_id]);
        $guest_row = $stmt->fetch();

        if ($delete_order_id !== '') {
            if ($has_order_people) {
                $stmt = $pdo->prepare("DELETE FROM {$prefix}order_people WHERE order_id = ?");
                $stmt->execute([$delete_order_id]);
            }
            if ($has_order_guest_data) {
                $stmt = $pdo->prepare("DELETE FROM {$prefix}order_guest_data WHERE order_id = ?");
                $stmt->execute([$delete_order_id]);
            }
            $stmt = $pdo->prepare("DELETE FROM {$prefix}orders WHERE order_id = ?");
            $stmt->execute([$delete_order_id]);
            $stmt = $pdo->prepare("DELETE FROM {$prefix}order_sessions WHERE order_id = ?");
            $stmt->execute([$delete_order_id]);
        } elseif ($guest_row) {
            // Legacy: alle Bestellungen dieses Gasts löschen
            $stmt = $pdo->prepare("SELECT order_id FROM {$prefix}order_sessions WHERE project_id = ? AND email = ?");
            $stmt->execute([$project_id, $guest_row['email']]);
            $order_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($order_ids as $oid) {
                if ($has_order_people) {
                    $stmt = $pdo->prepare("DELETE FROM {$prefix}order_people WHERE order_id = ?");
                    $stmt->execute([$oid]);
                }
                if ($has_order_guest_data) {
                    $stmt = $pdo->prepare("DELETE FROM {$prefix}order_guest_data WHERE order_id = ?");
                    $stmt->execute([$oid]);
                }
                $stmt = $pdo->prepare("DELETE FROM {$prefix}orders WHERE order_id = ?");
                $stmt->execute([$oid]);
                $stmt = $pdo->prepare("DELETE FROM {$prefix}order_sessions WHERE order_id = ?");
                $stmt->execute([$oid]);
            }
        }

        $stmt = $pdo->prepare("DELETE FROM {$prefix}family_members WHERE guest_id = ?");
        $stmt->execute([$delete_guest_id]);
        $stmt = $pdo->prepare("DELETE FROM {$prefix}guests WHERE id = ?");
        $stmt->execute([$delete_guest_id]);
    }
}

// Gäste laden (Orders via order_sessions -> orders)
$order_id_select = $has_guest_order_id ? 'g.order_id' : 'NULL';
$stmt = $pdo->prepare("SELECT g.*, p.name as project_name,
                       COALESCE({$order_id_select}, MAX(os.order_id)) as order_id_display,
                       COUNT(DISTINCT os.order_id) as order_count
                       FROM {$prefix}guests g
                       JOIN {$prefix}projects p ON p.id = g.project_id
                       LEFT JOIN {$prefix}order_sessions os ON g.email = os.email AND os.project_id = g.project_id
                       LEFT JOIN {$prefix}orders o ON os.order_id = o.order_id
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

    <!-- PROJEKT FILTER (Select wie in orders.php) -->
    <div class="card border-0 shadow mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Projekt auswählen</label>
                    <select name="project" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Bitte wählen --</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo ($p['id'] == $project_id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
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
                        <th>Projekt</th>
                        <th>Bestell-Nr.</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Tel.</th>
                        <th>Typ</th>
                        <th>Alter</th>
                        <th>Bestellungen</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($guests)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">Noch keine Gäste angemeldet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($guests as $g): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($g['project_name'] ?? '–'); ?></td>
                                <td><?php echo htmlspecialchars($g['order_id_display'] ?? '–'); ?></td>
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
                                    <?php 
                                        $ageGroup = $g['person_type'] ?? 'adult';
                                        $childAge = $g['child_age'] ?? null;
                                        echo $ageGroup === 'child' && $childAge ? 'Kind ' . $childAge . 'J.' : 'Erwachsen';
                                    ?>
                                </td>
                                <td><?php echo $g['order_count']; ?></td>
                                <td>
                                    <form method="post" onsubmit="return confirm('Gast und zugehörige Bestellung wirklich löschen?');">
                                        <input type="hidden" name="delete_guest_id" value="<?php echo (int)$g['id']; ?>">
                                        <input type="hidden" name="delete_order_id" value="<?php echo htmlspecialchars($g['order_id_display'] ?? ''); ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Löschen</button>
                                    </form>
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
