<?php
/**
 * admin/dishes.php - Menü/Gerichte Verwaltung
 */

require_once '../db.php';
require_once '../script/auth.php';

checkLogin();
checkAdmin();

$prefix = $config['database']['prefix'] ?? 'menu_';
$project_id = isset($_GET['project']) ? (int)$_GET['project'] : null;
$message = "";
$messageType = "info";

if (!$project_id) {
    // Wenn kein Projekt gewählt, zur Projektliste
    $projects = $pdo->query("SELECT * FROM {$prefix}projects WHERE is_active = 1 ORDER BY name")->fetchAll();
    
    if (empty($projects)) {
        echo "<div class='container mt-5'><div class='alert alert-warning'>Bitte erstellen Sie zunächst ein Projekt.</div></div>";
        exit;
    }

    // Erstes aktives Projekt nehmen
    $project_id = $projects[0]['id'];
}

// Projekt laden
$stmt = $pdo->prepare("SELECT * FROM {$prefix}projects WHERE id = ?");
$stmt->execute([$project_id]);
$project = $stmt->fetch();

if (!$project) {
    die("Projekt nicht gefunden.");
}

// Gericht erstellen
if (isset($_POST['create_dish'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $category_id = (int)$_POST['category_id'];
    $sort_order = (int)$_POST['sort_order'];

    if (empty($name) || $category_id < 1) {
        $message = "Bitte füllen Sie alle erforderlichen Felder aus.";
        $messageType = "danger";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO {$prefix}dishes (project_id, category_id, name, description, sort_order, is_active) 
                                  VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([$project_id, $category_id, $name, $description, $sort_order]);

            $message = "✓ Gericht erfolgreich hinzugefügt!";
            $messageType = "success";
            logAction($pdo, $prefix, 'dish_created', "Gericht: $name");

        } catch (Exception $e) {
            $message = "Fehler: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Gericht löschen
if (isset($_GET['delete'])) {
    $dish_id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM {$prefix}dishes WHERE id = ? AND project_id = ?");
    $stmt->execute([$dish_id, $project_id]);
    $message = "Gericht gelöscht.";
    $messageType = "success";
}

// Gerichte laden
$stmt = $pdo->prepare("SELECT d.*, c.name as category_name FROM {$prefix}dishes d 
                       JOIN {$prefix}menu_categories c ON d.category_id = c.id 
                       WHERE d.project_id = ? ORDER BY c.sort_order, d.sort_order");
$stmt->execute([$project_id]);
$dishes = $stmt->fetchAll();

// Kategorien laden
$categories = $pdo->query("SELECT * FROM {$prefix}menu_categories ORDER BY sort_order")->fetchAll();

// Projekte für Dropdown
$projects = $pdo->query("SELECT * FROM {$prefix}projects WHERE is_active = 1 ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>Menüverwaltung - Menüwahl</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include '../nav/top_nav.php'; ?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Menüs verwalten</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDishModal">+ Gericht hinzufügen</button>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

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

    <!-- GERICHTE NACH KATEGORIEN -->
    <div class="card border-0 shadow">
        <div class="card-header bg-info text-white py-3">
            <h5 class="mb-0"><?php echo htmlspecialchars($project['name']); ?> - Menüs</h5>
        </div>
        <div class="card-body">
            <?php 
                $grouped = [];
                foreach ($dishes as $d) {
                    $grouped[$d['category_name']][] = $d;
                }
            ?>

            <?php if (empty($dishes)): ?>
                <div class="alert alert-info">Noch keine Gerichte hinzugefügt.</div>
            <?php else: ?>
                <?php foreach ($grouped as $cat_name => $cat_dishes): ?>
                    <div class="mb-4">
                        <h5 class="text-info mb-3"><?php echo htmlspecialchars($cat_name); ?></h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <tbody>
                                    <?php foreach ($cat_dishes as $d): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($d['name']); ?></strong>
                                                <?php if ($d['description']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($d['description']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <span class="badge bg-secondary me-2"><?php echo $d['sort_order']; ?></span>
                                                <a href="?project=<?php echo $project_id; ?>&delete=<?php echo $d['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Gericht löschen?')">Löschen</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- MODAL: GERICHT HINZUFÜGEN -->
<div class="modal fade" id="addDishModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Gericht hinzufügen</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Kategorie *</label>
                        <select name="category_id" class="form-select" required>
                            <option value="">-- Wählen --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Gericht *</label>
                        <input type="text" name="name" class="form-control" placeholder="z.B. Hähnchen mit Gemüse" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Beschreibung</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="z.B. Allergene, Zusatzstoffe"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Sortierung</label>
                        <input type="number" name="sort_order" class="form-control" value="0">
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" name="create_dish" class="btn btn-primary">Hinzufügen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
