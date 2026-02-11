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
        // Saubere Fehlerseite: kein Projekt angelegt
        ?>
        <!DOCTYPE html>
        <html lang="de" data-bs-theme="dark">
        <head>
            <meta charset="UTF-8">
            <title>Kein Projekt - EMOS</title>
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
                            <h3 class="mb-3">Kein Projekt angelegt</h3>
                            <p class="text-muted">Bitte erstellen Sie zuerst ein Projekt, bevor Sie Menüs oder Gerichte verwalten.</p>
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

    // Erstes aktives Projekt nehmen
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
                        <p class="text-muted">Das ausgewählte Projekt existiert nicht oder wurde gelöscht.</p>
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

// Gericht erstellen
if (isset($_POST['create_dish'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $category_id = (int)$_POST['category_id'];
    $sort_order = (int)$_POST['sort_order'];
    $price_raw = trim($_POST['price'] ?? '');
    $price = $price_raw === '' ? null : (float)str_replace(',', '.', $price_raw);

    if (empty($name) || $category_id < 1) {
        $message = "Bitte füllen Sie alle erforderlichen Felder aus.";
        $messageType = "danger";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO {$prefix}dishes (project_id, category_id, name, description, sort_order, price, is_active) 
                                  VALUES (?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$project_id, $category_id, $name, $description, $sort_order, $price]);

            $message = "✓ Gericht erfolgreich hinzugefügt!";
            $messageType = "success";
            logAction($pdo, $prefix, 'dish_created', "Gericht: $name");

        } catch (Exception $e) {
            $message = "Fehler: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Drag-and-Drop Sortierung verarbeiten (AJAX)
if (isset($_POST['update_sort_order'])) {
    header('Content-Type: application/json');
    
    $order = isset($_POST['order']) ? json_decode($_POST['order'], true) : [];
    
    if (!is_array($order) || empty($order)) {
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
        exit;
    }
    
    try {
        foreach ($order as $index => $dish_id) {
            $dish_id = (int)$dish_id;
            $stmt = $pdo->prepare("UPDATE {$prefix}dishes SET sort_order = ? WHERE id = ? AND project_id = ?");
            $stmt->execute([$index, $dish_id, $project_id]);
        }
        echo json_encode(['success' => true, 'message' => 'Reihenfolge gespeichert']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Gericht löschen
if (isset($_GET['delete'])) {
    $dish_id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM {$prefix}dishes WHERE id = ? AND project_id = ?");
    $stmt->execute([$dish_id, $project_id]);
    $message = "Gericht gelöscht.";
    $messageType = "success";
}

// Gericht aktualisieren
if (isset($_POST['update_dish'])) {
    $dish_id = (int)$_POST['id'];
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $category_id = (int)$_POST['category_id'];
    $sort_order = (int)$_POST['sort_order'];
    $price_raw = trim($_POST['price'] ?? '');
    $price = $price_raw === '' ? null : (float)str_replace(',', '.', $price_raw);

    if (empty($name) || $category_id < 1) {
        $message = "Bitte füllen Sie alle erforderlichen Felder aus.";
        $messageType = "danger";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE {$prefix}dishes SET category_id = ?, name = ?, description = ?, sort_order = ?, price = ? WHERE id = ? AND project_id = ?");
            $stmt->execute([$category_id, $name, $description, $sort_order, $price, $dish_id, $project_id]);
            $message = "Gericht aktualisiert.";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Fehler beim Aktualisieren: " . $e->getMessage();
            $messageType = "danger";
        }
    }
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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Menüverwaltung - EMOS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        body { background-color: #1a1a1a; color: #fff; }
        .card { background-color: #222; border-color: #444; }
        .table-dark { background-color: #222; }
        .form-control, .form-select, textarea { background-color: #333; color: #fff; border-color: #555; }
        .form-control:focus, .form-select:focus, textarea:focus { background-color: #333; color: #fff; border-color: #0d6efd; }
        .card .form-label { color: #fff; }
        .dishes-table .form-control,
        .dishes-table .form-select {
            color: #fff;
            background-color: #333;
        }
        .dishes-table tbody tr { border-bottom: 12px solid #1a1a1a; }
        .alert { display: flex; align-items: center; justify-content: space-between; padding-right: 20px; }
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
        .close-button-wrapper:hover { background-color: #bb2d3b; }
        .action-buttons { display: flex; gap: 0.5rem; justify-content: center; align-items: center; }
        .action-buttons .btn { width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: bold; }
        .btn-action { width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: bold; }
        .drag-indicator { display: inline-block; margin-left: 8px; color: #888; cursor: grab; user-select: none; font-weight: bold; font-size: 0.9rem; letter-spacing: -2px; }
        .dishes-table tbody tr:hover .drag-indicator { color: #aaa; }
        .dishes-table { table-layout: fixed; }
        .dishes-table th:nth-child(1) { width: 55px; }
        .dishes-table td:nth-child(1) { width: 55px; text-align: center; }
        .dishes-table th:nth-child(2) { width: 45%; }
        .dishes-table td:nth-child(2) { width: 45%; }
        .dishes-table th:nth-child(3) { width: 25%; }
        .dishes-table td:nth-child(3) { width: 25%; }
        .dishes-table th:nth-child(4) { width: 20%; text-align: right !important; padding-right: 2.5rem !important; }
        .dishes-table td:nth-child(4) { width: 20%; text-align: right; padding-right: 0.75rem !important; }
        .checkbox-col { text-align: center !important; display: flex !important; align-items: center !important; justify-content: center !important; padding-top: 0 !important; min-height: 40px; }
        .dishes-table .checkbox-col { vertical-align: middle !important; }
        .dishes-table thead .checkbox-col { display: flex !important; align-items: center !important; justify-content: center !important; }
        .checkbox-col input[type="checkbox"] { width: 16px; height: 16px; cursor: pointer; margin: 0 !important; }
        .dishes-table td:nth-child(4) input[type="text"] { text-align: right; }
        .bulk-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; }
        .bulk-actions .btn { padding: 0.5rem 1rem; font-size: 0.9rem; }
        .action-cell-wrapper { display: none; }
        .table-wrapper { position: relative; display: block; }
        .table-container { margin-bottom: 1rem; }
        .dishes-table tbody tr { cursor: move; user-select: none; }
        .dishes-table tbody tr.dragging { opacity: 0.5; background-color: #444; }
        .dishes-table tbody tr.drag-over { background-color: #2d5016; border-top: 2px solid #90ee90; }
        .dishes-table tbody tr { border-bottom: 6px solid #1a1a1a; vertical-align: top; }
        .dishes-table tbody td { vertical-align: top; }
        .dishes-table thead th { vertical-align: middle !important; }
        .dishes-table thead { vertical-align: middle !important; }
        .dishes-table .category-separator { height: auto; }
        .dishes-table .category-separator td { padding: 15px 0; border: none; border-top: 3px dashed #888; background-color: transparent; }
        .column-resizer { display: none; }
        @media (max-width: 576px) {
            .action-buttons { flex-direction: row; gap: 0.5rem; }
            .action-buttons .btn { width: 32px; height: 32px; }
        }
    </style>
</head>
<body>

<?php include '../nav/top_nav.php'; ?>

<div class="container mt-4" style="max-width: 1000px;">
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <span><?php echo htmlspecialchars($message); ?></span>
            <button type="button" class="close-button-wrapper" data-bs-dismiss="alert" aria-label="Close">×</button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-12 col-lg-4">
            <div class="card">
                <div class="card-header bg-primary">
                    <h5 class="mb-0">Neues Gericht</h5>
                </div>
                <div class="card-body">
                    <form method="post" class="row g-2">
                        <div class="col-12">
                            <label for="category_id" class="form-label">Kategorie *</label>
                            <select name="category_id" id="category_id" class="form-select form-select-sm" required>
                                <option value="">Wählen...</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="name" class="form-label">Gericht *</label>
                            <input type="text" name="name" id="name" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-12">
                            <label for="description" class="form-label">Beschreibung</label>
                            <textarea name="description" id="description" class="form-control form-control-sm" rows="2"></textarea>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label for="price" class="form-label">Preis (€)</label>
                            <input type="text" name="price" id="price" class="form-control form-control-sm" placeholder="z.B. 12,50">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label for="sort_order" class="form-label">Reihenfolge</label>
                            <input type="number" name="sort_order" id="sort_order" class="form-control form-control-sm" value="0">
                        </div>
                        <div class="col-12">
                            <button type="submit" name="create_dish" class="btn btn-primary w-100">Erstellen</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-8">
            <div class="card">
                <div class="card-header bg-secondary">
                    <label class="form-label mb-0">Projekt auswählen</label>
                    <form method="get" class="mt-2">
                        <select name="project" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">-- Bitte wählen --</option>
                            <?php foreach ($projects as $p): ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo ($p['id'] == $project_id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <div class="card-body">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <h5 class="mb-0">Gerichte (<?php echo count($dishes); ?>)</h5>
                        <?php if (!empty($dishes)): ?>
                        <div class="bulk-actions" style="margin-bottom: 0; margin-left: auto !important;">
                            <button type="button" class="btn btn-primary" id="bulk-edit-btn" onclick="bulkEdit()">Bearbeiten</button>
                            <button type="button" class="btn btn-warning d-none" id="bulk-save-btn" onclick="bulkSave()">Speichern</button>
                            <button type="button" class="btn btn-danger" id="bulk-delete-btn" onclick="bulkDelete()">Löschen</button>
                            <span class="ms-3" id="selection-count" style="color: #999; padding-top: 0.5rem;"></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (empty($dishes)): ?>
                        <p class="text-muted text-center py-4">Noch keine Gerichte angelegt.</p>
                    <?php else: ?>
                        <div class="table-wrapper">
                            <div class="table-container">
                                <table class="table table-dark table-hover table-sm mb-0 dishes-table">
                                    <thead>
                                        <tr>
                                            <th class="checkbox-col"><input type="checkbox" id="select-all-checkbox" onchange="toggleSelectAll(this)"></th>
                                            <th>Name</th>
                                            <th>Kategorie</th>
                                            <th>Preis</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $prev_category = null;
                                        foreach ($dishes as $d): 
                                            if ($prev_category !== null && $prev_category != $d['category_id']): 
                                        ?>
                                            <tr class="category-separator"><td colspan="4"></td></tr>
                                        <?php endif; ?>
                                            <tr draggable="true" data-dish-id="<?php echo $d['id']; ?>" data-category-id="<?php echo $d['category_id']; ?>">
                                                <td class="checkbox-col"><input type="checkbox" class="dish-checkbox" data-dish-id="<?php echo $d['id']; ?>" onchange="updateSelectionCount()"></td>
                                                <td>
                                                    <div>
                                                        <input type="text" data-field="name" value="<?php echo htmlspecialchars($d['name']); ?>" class="form-control form-control-sm" disabled style="display: inline-block; width: 100%;">
                                                        <?php if ($d['description']): ?>
                                                            <small class="text-muted d-block mt-1" style="margin-left: 0;"><?php echo htmlspecialchars($d['description']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <select data-field="category_id" class="form-select form-select-sm" disabled>
                                                        <?php foreach ($categories as $cat): ?>
                                                            <option value="<?php echo $cat['id']; ?>" <?php echo $d['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($cat['name']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                <td>
                                                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                                                        <input type="text" data-field="price" value="<?php echo is_null($d['price']) ? '' : number_format((float)$d['price'], 2, ',', '.'); ?>" class="form-control form-control-sm" placeholder="–" disabled style="flex: 1;">
                                                        <span class="drag-indicator">=</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php 
                                            $prev_category = $d['category_id'];
                                        endforeach; 
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check if there's a save message from localStorage
    const saveMessage = localStorage.getItem('save_message');
    if (saveMessage) {
        // Create alert element
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-success alert-dismissible fade show';
        alertDiv.role = 'alert';
        alertDiv.innerHTML = `
            <span>${saveMessage}</span>
            <button type="button" class="close-button-wrapper" data-bs-dismiss="alert" aria-label="Close">×</button>
        `;
        // Insert at the top of the message container
        const container = document.querySelector('.container');
        if (container) {
            container.insertBefore(alertDiv, container.firstChild);
        }
        // Remove from localStorage
        localStorage.removeItem('save_message');
        
        // Auto-dismiss after 3 seconds
        setTimeout(() => {
            alertDiv.remove();
        }, 3000);
    }
    
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.remove();
        }, 3000);
    });
});

function editDish(id) {
    const row = document.querySelector(`tr[data-dish-id="${id}"]`);
    if (!row) return;
    
    const inputs = row.querySelectorAll('input, select');
    inputs.forEach(input => input.disabled = false);
    
    const editBtn = row.querySelector('.edit-btn');
    const saveBtn = row.querySelector('.save-btn');
    
    editBtn?.classList.add('d-none');
    saveBtn?.classList.remove('d-none');
}

function saveDish(id) {
    const row = document.querySelector(`tr[data-dish-id="${id}"]`);
    if (!row) return;
    
    const formData = new FormData();
    formData.append('id', id);
    formData.append('update_dish', '1');
    formData.append('name', row.querySelector('input[data-field="name"]').value);
    formData.append('category_id', row.querySelector('select[data-field="category_id"]').value);
    formData.append('price', row.querySelector('input[data-field="price"]').value);
    formData.append('sort_order', 0);
    formData.append('description', '');

    fetch(window.location.pathname + window.location.search, {
        method: 'POST',
        body: formData
    })
    .then(() => {
        // Store success message in localStorage and reload
        localStorage.setItem('save_message', 'Erfolgreich gespeichert!');
        location.reload();
    })
    .catch(err => {
        alert('Fehler beim Speichern: ' + err);
    });
}

function deleteDish(id) {
    if (!confirm('Gericht wirklich löschen?')) return;
    
    const params = new URLSearchParams(window.location.search);
    params.append('delete', id);
    window.location.href = window.location.pathname + '?' + params.toString();
}

// Checkbox-Funktionen für Bulk-Operationen
function toggleSelectAll(checkbox) {
    const dishCheckboxes = document.querySelectorAll('.dish-checkbox');
    dishCheckboxes.forEach(cb => cb.checked = checkbox.checked);
    updateSelectionCount();
}

function updateSelectionCount() {
    const selected = document.querySelectorAll('.dish-checkbox:checked').length;
    const total = document.querySelectorAll('.dish-checkbox').length;
    const countSpan = document.getElementById('selection-count');
    countSpan.textContent = selected > 0 ? `${selected} von ${total} ausgewählt` : '';
    
    // Aktualisiere Select-All-Checkbox
    const selectAll = document.getElementById('select-all-checkbox');
    selectAll.checked = selected === total && total > 0;
    selectAll.indeterminate = selected > 0 && selected < total;
}

function getSelectedDishes() {
    return Array.from(document.querySelectorAll('.dish-checkbox:checked'))
        .map(cb => cb.getAttribute('data-dish-id'))
        .map(id => parseInt(id));
}

function bulkEdit() {
    const selected = getSelectedDishes();
    if (selected.length === 0) {
        alert('Bitte wählen Sie mindestens einen Eintrag aus.');
        return;
    }
    
    // Aktiviere Bearbeitung für alle ausgewählten
    selected.forEach(id => {
        const row = document.querySelector(`tr[data-dish-id="${id}"]`);
        if (row) {
            row.querySelectorAll('input, select').forEach(field => {
                if (field.type !== 'checkbox') field.disabled = false;
            });
        }
    });
    
    // Buttons wechseln
    document.getElementById('bulk-edit-btn').classList.add('d-none');
    document.getElementById('bulk-save-btn').classList.remove('d-none');
}

function bulkSave() {
    const selected = getSelectedDishes();
    if (selected.length === 0) return;
    
    // Speichere alle ausgewählten Einträge nacheinander
    const savePromises = selected.map(id => {
        const row = document.querySelector(`tr[data-dish-id="${id}"]`);
        if (!row) return Promise.resolve();
        
        const formData = new FormData();
        formData.append('id', id);
        formData.append('update_dish', '1');
        formData.append('name', row.querySelector('input[data-field="name"]').value);
        formData.append('category_id', row.querySelector('select[data-field="category_id"]').value);
        formData.append('price', row.querySelector('input[data-field="price"]').value);
        formData.append('sort_order', 0);
        formData.append('description', '');
        
        return fetch(window.location.pathname + window.location.search, {
            method: 'POST',
            body: formData
        });
    });
    
    Promise.all(savePromises).then(() => {
        localStorage.setItem('save_message', 'Erfolgreich gespeichert!');
        location.reload();
    }).catch(err => {
        alert('Fehler beim Speichern: ' + err);
    });
}

function bulkDelete() {
    const selected = getSelectedDishes();
    if (selected.length === 0) {
        alert('Bitte wählen Sie mindestens einen Eintrag aus.');
        return;
    }
    
    const message = selected.length === 1 
        ? 'Gericht wirklich löschen?' 
        : `${selected.length} Gerichte wirklich löschen?`;
    
    if (!confirm(message)) return;
    
    // Lösche alle nacheinander
    const deletePromises = selected.map(id => {
        const params = new URLSearchParams(window.location.search);
        params.append('delete', id);
        return fetch(window.location.pathname + '?' + params.toString());
    });
    
    Promise.all(deletePromises).then(() => {
        location.reload();
    }).catch(err => {
        alert('Fehler beim Löschen: ' + err);
    });
}

// Synchronisiere Button-Höhen mit Tabellenzeilen
function alignActionButtons() {
    const table = document.querySelector('.dishes-table');
    const actionsOverlay = document.querySelector('.actions-overlay');
    if (!table || !actionsOverlay) return;
    
    const rows = table.querySelectorAll('tbody tr:not(.category-separator)');
    const actionCells = actionsOverlay.querySelectorAll('.action-cell-wrapper');
    
    // Stelle sicher, dass die Anzahl übereinstimmt
    if (rows.length !== actionCells.length) {
        console.warn(`Row count mismatch: table=${rows.length}, actions=${actionCells.length}`);
    }
    
    rows.forEach((row, index) => {
        if (actionCells[index]) {
            const rowHeight = row.offsetHeight;
            // Berechne die genaue Höhe inkl. Padding/Margin
            const computedStyle = window.getComputedStyle(row);
            const marginTop = parseFloat(computedStyle.marginTop) || 0;
            const marginBottom = parseFloat(computedStyle.marginBottom) || 0;
            const totalHeight = rowHeight + marginTop + marginBottom;
            
            actionCells[index].style.height = rowHeight + 'px';
            actionCells[index].style.minHeight = rowHeight + 'px';
            actionCells[index].style.display = 'flex';
            actionCells[index].style.alignItems = 'center';
        }
    });
}

// Initial alignment mit Verzögerung
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(alignActionButtons, 50);
    setTimeout(alignActionButtons, 200);
});

// Re-align bei Änderungen
window.addEventListener('resize', alignActionButtons);
window.addEventListener('load', alignActionButtons);

// MutationObserver für Änderungen am DOM
const observer = new MutationObserver(function() {
    setTimeout(alignActionButtons, 10);
});
observer.observe(document.querySelector('.dishes-table') || document.body, {
    childList: true,
    subtree: true,
    characterData: false
});

// Drag-and-Drop Setup
document.addEventListener('DOMContentLoaded', function() {
    const table = document.querySelector('.dishes-table');
    if (!table) return;
    
    setupDragAndDrop();
});

let draggedRow = null;
let draggedFromIndex = null;

function setupDragAndDrop() {
    const table = document.querySelector('.dishes-table');
    const rows = table.querySelectorAll('tbody tr:not(.category-separator)');
    
    rows.forEach((row, index) => {
        row.addEventListener('dragstart', handleDragStart);
        row.addEventListener('dragover', handleDragOver);
        row.addEventListener('drop', handleDrop);
        row.addEventListener('dragleave', handleDragLeave);
        row.addEventListener('dragend', handleDragEnd);
    });
}

function handleDragStart(e) {
    draggedRow = this;
    draggedFromIndex = Array.from(this.parentNode.children).filter(r => !r.classList.contains('category-separator')).indexOf(this);
    this.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
}

function handleDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    if (this !== draggedRow && !this.classList.contains('category-separator')) {
        this.classList.add('drag-over');
    }
}

function handleDragLeave(e) {
    this.classList.remove('drag-over');
}

function handleDrop(e) {
    e.preventDefault();
    if (draggedRow && this !== draggedRow && !this.classList.contains('category-separator')) {
        const tbody = this.parentNode;
        const allRows = Array.from(tbody.children).filter(r => !r.classList.contains('category-separator'));
        const draggedIndex = allRows.indexOf(draggedRow);
        const targetIndex = allRows.indexOf(this);
        
        if (draggedIndex < targetIndex) {
            this.parentNode.insertBefore(draggedRow, this.nextSibling);
        } else {
            this.parentNode.insertBefore(draggedRow, this);
        }
        
        saveSortOrder();
    }
}

function handleDragEnd(e) {
    this.classList.remove('dragging');
    const rows = document.querySelectorAll('.dishes-table tbody tr');
    rows.forEach(row => row.classList.remove('drag-over'));
}

function saveSortOrder() {
    const table = document.querySelector('.dishes-table');
    const rows = table.querySelectorAll('tbody tr:not(.category-separator)');
    const order = Array.from(rows).map(row => row.getAttribute('data-dish-id'));
    
    const formData = new FormData();
    formData.append('update_sort_order', '1');
    formData.append('order', JSON.stringify(order));
    
    fetch(window.location.pathname + window.location.search, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Reihenfolge gespeichert');
            // Re-align buttons nach Drag-and-Drop mit mehrfachen Versuchen
            setTimeout(alignActionButtons, 10);
            setTimeout(alignActionButtons, 100);
            setTimeout(alignActionButtons, 300);
        }
    })
    .catch(err => console.error('Fehler beim Speichern:', err));
}
</script>
</body>
</html>
