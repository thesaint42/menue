<?php
/**
 * admin/dishes.php - Menü/Gerichte Verwaltung
 */

require_once '../db.php';
require_once '../script/auth.php';

checkLogin();

// Feature-basierte Zugriffskontrolle (zentrale Funktion)
requireMenuAccess($pdo, ['menus_read', 'menus_write'], 'read', $config['database']['prefix'] ?? 'menu_');

$prefix = $config['database']['prefix'] ?? 'menu_';

// Get individual permissions for UI
$can_read_menus = hasMenuAccess($pdo, 'menus_read', $prefix);
$can_write_menus = hasMenuAccess($pdo, 'menus_write', $prefix);

// Projekte laden (nur zugängliche für project_admin Users)
$user_role_id = $_SESSION['role_id'] ?? null;

if ($user_role_id === 1) {
    // Admin: alle Projekte
    $projects = $pdo->query("SELECT * FROM {$prefix}projects WHERE is_active = 1 ORDER BY name")->fetchAll();
} else if (hasMenuAccess($pdo, 'projects_write', $prefix)) {
    // Project Admin: nur zugewiesene Projekte
    $assigned = getUserProjects($pdo, $prefix);
    if (!empty($assigned)) {
        $project_ids = array_column($assigned, 'id');
        $placeholders = implode(',', array_fill(0, count($project_ids), '?'));
        $stmt = $pdo->prepare("SELECT * FROM {$prefix}projects WHERE is_active = 1 AND id IN ($placeholders) ORDER BY name");
        $stmt->execute($project_ids);
        $projects = $stmt->fetchAll();
    } else {
        $projects = [];
    }
} else {
    $projects = [];
}

// Projekt aus GET oder POST holen
$project_id = isset($_GET['project']) ? (int)$_GET['project'] : (isset($_POST['project_id']) ? (int)$_POST['project_id'] : null);

// Wenn nur ein Projekt vorhanden, automatisch vorauswählen
if (!$project_id && count($projects) === 1) {
    $project_id = $projects[0]['id'];
}

$message = "";
$messageType = "info";

// Gericht erstellen
if (isset($_POST['create_dish'])) {
    if (!$can_write_menus) {
        $message = "⚠️ Keine Berechtigung zum Erstellen von Gerichten.";
        $messageType = "danger";
    } elseif (!$project_id) {
        $message = "Bitte wählen Sie zuerst ein Projekt.";
        $messageType = "danger";
    } else {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $category_id = (int)$_POST['category_id'];
        $sort_order = isset($_POST['sort_order']) && $_POST['sort_order'] !== '' ? (int)$_POST['sort_order'] : 0;
        $price_raw = trim($_POST['price'] ?? '');
        $price = $price_raw === '' ? null : (float)str_replace(',', '.', $price_raw);
        
        if (empty($name) || $category_id < 1) {
            $message = "Gericht-Name und Kategorie sind erforderlich.";
            $messageType = "danger";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO {$prefix}dishes (project_id, category_id, name, description, sort_order, price, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([$project_id, $category_id, $name, $description, $sort_order, $price]);
                $message = "Gericht erstellt.";
                $messageType = "success";
            } catch (Exception $e) {
                $message = "Fehler beim Erstellen: " . $e->getMessage();
                $messageType = "danger";
            }
        }
    }
}

// Gericht bearbeiten
if (isset($_POST['update_dish'])) {
    if (!$can_write_menus) {
        $message = "⚠️ Keine Berechtigung zum Bearbeiten von Gerichten.";
        $messageType = "danger";
    } else {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $category_id = (int)$_POST['category_id'];
        $sort_order = (int)$_POST['sort_order'];
        $price_raw = trim($_POST['price'] ?? '');
        $price = $price_raw === '' ? null : (float)str_replace(',', '.', $price_raw);
        
        if (empty($name) || $category_id < 1) {
            $message = "Gericht-Name und Kategorie sind erforderlich.";
            $messageType = "danger";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE {$prefix}dishes SET category_id = ?, name = ?, description = ?, sort_order = ?, price = ? WHERE id = ? AND project_id = ?");
                $stmt->execute([$category_id, $name, $description, $sort_order, $price, $id, $project_id]);
                $message = "Gericht aktualisiert.";
                $messageType = "success";
            } catch (Exception $e) {
                $message = "Fehler beim Aktualisieren: " . $e->getMessage();
                $messageType = "danger";
            }
        }
    }
}

// Gericht löschen
if (isset($_POST['delete_dish'])) {
    if (!$can_write_menus) {
        $message = "⚠️ Keine Berechtigung zum Löschen von Gerichten.";
        $messageType = "danger";
    } else {
        $id = (int)$_POST['id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM {$prefix}dishes WHERE id = ? AND project_id = ?");
            $stmt->execute([$id, $project_id]);
            $message = "Gericht gelöscht.";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Fehler beim Löschen: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Kategorien laden (für aktuelles Projekt)
$categories = [];
if ($project_id) {
    $stmt = $pdo->prepare("SELECT * FROM {$prefix}menu_categories WHERE project_id = ? ORDER BY sort_order ASC, name ASC");
    $stmt->execute([$project_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Gerichte laden (für aktuelles Projekt)
$dishes = [];
if ($project_id) {
    $stmt = $pdo->prepare("SELECT d.*, c.name as category_name FROM {$prefix}dishes d 
                           JOIN {$prefix}menu_categories c ON d.category_id = c.id 
                           WHERE d.project_id = ? ORDER BY c.sort_order, d.sort_order, d.name");
    $stmt->execute([$project_id]);
    $dishes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gerichte - EMOS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #1a1a1a; color: #fff; }
        .card { background-color: #222; border-color: #444; }
        .table-dark { background-color: #222; }
        .form-control, .form-select, textarea { background-color: #333; color: #fff; border-color: #555; }
        .form-control:focus, .form-select:focus, textarea:focus { background-color: #333; color: #fff; border-color: #0d6efd; }
        .form-control:disabled { background-color: #444; color: #fff; border-color: #555; opacity: 1; }
        .form-select:disabled { background-color: #444; color: #fff; border-color: #555; opacity: 1; }
        .form-control::placeholder { color: #b0b0b0; opacity: 1; }
        .form-control::-webkit-input-placeholder { color: #b0b0b0; }
        .form-control:-ms-input-placeholder { color: #b0b0b0; }
        .btn-close-white {
            background-color: #dc3545 !important;
            border: none !important;
            opacity: 1 !important;
            filter: brightness(1.3);
        }
        .close-button-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            width: clamp(20px, 5vw, 30px);
            height: clamp(20px, 5vw, 30px);
            background-color: #dc3545;
            border-radius: 4px;
            cursor: pointer;
            flex-shrink: 0;
            padding: 0;
            border: none;
            font-size: 0.6rem;
            color: white;
            margin-left: auto;
        }
        .alert {
            padding-right: 20px;
        }
        .close-button-wrapper:hover {
            background-color: #bb2d3b;
        }
        .btn-close-white:hover {
            background-color: #bb2d3b;
        }
    </style>
</head>
<body>
<?php include '../nav/top_nav.php'; ?>

<div class="container mt-4" style="max-width: 1200px;">
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show mb-4" role="alert" style="display: flex; align-items: center; justify-content: space-between;">
            <span><?php echo htmlspecialchars($message); ?></span>
            <button type="button" class="close-button-wrapper" data-bs-dismiss="alert" aria-label="Close">×</button>
        </div>
    <?php endif; ?>

    <!-- Projektauswahl -->
    <div class="card mb-4">
        <div class="card-header bg-info">
            <h5 class="mb-0">Projekt auswählen</h5>
        </div>
        <div class="card-body">
            <form method="get" class="row g-2">
                <div class="col-12">
                    <select name="project" class="form-select" onchange="this.form.submit()" required>
                        <option value="">-- Bitte wählen --</option>
                        <?php foreach ($projects as $proj): ?>
                            <option value="<?php echo $proj['id']; ?>" <?php echo $project_id == $proj['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($proj['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <?php if ($project_id): ?>
    <div class="card mb-4">
        <div class="card-header bg-primary">
            <h5 class="mb-0">Neues Gericht</h5>
        </div>
        <div class="card-body">
            <?php if (!$can_write_menus): ?>
            <div class="alert alert-warning" role="alert">
                <strong>ℹ️ Hinweis:</strong> Sie haben nur Leseberechtigung für Gerichte.
            </div>
            <?php endif; ?>
            <form method="post" class="row g-2">
                <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
                <div class="col-12 col-md-3">
                    <select name="category_id" class="form-select" required <?php echo !$can_write_menus ? 'disabled' : ''; ?>>
                        <option value="">Kategorie wählen</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <input type="text" name="name" class="form-control" placeholder="Gericht-Name" required <?php echo !$can_write_menus ? 'disabled' : ''; ?>>
                </div>
                <div class="col-12 col-md-2">
                    <input type="text" name="price" class="form-control" placeholder="Preis (€)" <?php echo !$can_write_menus ? 'disabled' : ''; ?>>
                </div>
                <div class="col-12 col-md-1">
                    <input type="number" name="sort_order" class="form-control" placeholder="Sort" min="0" value="0" <?php echo !$can_write_menus ? 'disabled' : ''; ?>>
                </div>
                <div class="col-12 col-md-2">
                    <button type="submit" name="create_dish" class="btn btn-primary w-100" <?php echo !$can_write_menus ? 'disabled' : ''; ?>>Erstellen</button>
                </div>
                <div class="col-12">
                    <textarea name="description" class="form-control" placeholder="Beschreibung (optional)" rows="2" <?php echo !$can_write_menus ? 'disabled' : ''; ?>></textarea>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabelle -->
    <div class="card">
        <div class="card-header bg-secondary">
            <h5 class="mb-0">Gerichte <span class="badge bg-primary ms-2"><?php echo count($dishes); ?></span></h5>
        </div>
        <div class="table-responsive">
            <table class="table table-dark table-hover table-sm mb-0">
                <thead>
                    <tr>
                        <th style="width: 20%">Kategorie</th>
                        <th style="width: 25%">Gericht</th>
                        <th style="width: 35%">Beschreibung</th>
                        <th style="width: 10%">Preis</th>
                        <th style="width: 10%">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dishes)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-3">Keine Gerichte vorhanden</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($dishes as $dish): ?>
                            <tr>
                                <td>
                                    <form method="post" id="form_<?php echo $dish['id']; ?>" style="display: inline;">
                                        <input type="hidden" name="id" value="<?php echo $dish['id']; ?>">
                                        <input type="hidden" name="sort_order" value="<?php echo $dish['sort_order']; ?>">
                                        <select name="category_id" class="form-select form-select-sm" disabled>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?php echo $cat['id']; ?>" <?php echo $dish['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($cat['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                </td>
                                <td>
                                    <input type="text" name="name" value="<?php echo htmlspecialchars($dish['name']); ?>" class="form-control form-control-sm" required disabled>
                                </td>
                                <td>
                                    <textarea name="description" class="form-control form-control-sm" rows="2" disabled><?php echo htmlspecialchars($dish['description']); ?></textarea>
                                </td>
                                <td>
                                    <input type="text" name="price" value="<?php echo is_null($dish['price']) ? '' : number_format((float)$dish['price'], 2, ',', '.'); ?>" class="form-control form-control-sm" disabled>
                                </td>
                                <td class="text-nowrap">
                                    <?php if ($can_write_menus): ?>
                                    <button type="button" class="btn btn-sm btn-success edit-btn" onclick="toggleEdit(this, <?php echo $dish['id']; ?>)" data-id="<?php echo $dish['id']; ?>">Bearbeiten</button>
                                    <button type="submit" name="update_dish" form="form_<?php echo $dish['id']; ?>" class="btn btn-sm btn-warning d-none" data-id="<?php echo $dish['id']; ?>">Speichern</button>
                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" onclick="document.getElementById('deleteForm').innerHTML = '<input type=hidden name=id value=<?php echo $dish['id']; ?>><input type=hidden name=delete_dish value=1>'">Löschen</button>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">Nur Lesezugriff</span>
                                    <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-info">
        <strong>ℹ️ Hinweis:</strong> Bitte wählen Sie ein Projekt aus, um die Gerichte zu verwalten.
    </div>
    <?php endif; ?>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Gericht löschen?</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Dieses Gericht wird permanent gelöscht.</p>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                <form method="post" class="d-inline">
                    <div id="deleteForm"></div>
                    <button type="submit" class="btn btn-danger">Löschen</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-dismiss alerts after 3 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.remove();
        }, 3000);
    });
});

function toggleEdit(btn, id) {
    const form = document.getElementById('form_' + id);
    const row = form.closest('tr');
    
    // Finde alle Input-Felder in dieser Reihe (außer dem hidden input für id)
    const inputs = row.querySelectorAll('input[type=text], select, textarea');
    const editBtn = btn;
    const saveBtn = document.querySelector(`button[name=update_dish][data-id="${id}"]`);
    
    inputs.forEach(input => {
        input.toggleAttribute('disabled');
    });
    
    editBtn.classList.toggle('d-none');
    saveBtn.classList.toggle('d-none');
}
</script>

</body>
</html>
