<?php
/**
 * admin/menu_categories.php - Menükategorien-Verwaltung
 */

require_once '../db.php';
require_once '../script/auth.php';

checkLogin();
checkAdmin();

$prefix = $config['database']['prefix'] ?? 'menu_';
$message = "";
$messageType = "info";

// Kategorie erstellen
if (isset($_POST['create_category'])) {
    $name = trim($_POST['name']);
    $sort_order = isset($_POST['sort_order']) && $_POST['sort_order'] !== '' ? (int)$_POST['sort_order'] : 0;
    
    if (empty($name)) {
        $message = "Kategoriename ist erforderlich.";
        $messageType = "danger";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO {$prefix}menu_categories (name, sort_order) VALUES (?, ?)");
            $stmt->execute([$name, $sort_order]);
            $message = "Kategorie erstellt.";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Fehler beim Erstellen: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Kategorie bearbeiten
if (isset($_POST['update_category'])) {
    $id = (int)$_POST['id'];
    $name = trim($_POST['name']);
    $sort_order = (int)$_POST['sort_order'];
    
    if (empty($name)) {
        $message = "Kategoriename ist erforderlich.";
        $messageType = "danger";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE {$prefix}menu_categories SET name = ?, sort_order = ? WHERE id = ?");
            $stmt->execute([$name, $sort_order, $id]);
            $message = "Kategorie aktualisiert.";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Fehler beim Aktualisieren: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Kategorie löschen
if (isset($_POST['delete_category'])) {
    $id = (int)$_POST['id'];
    try {
        $pdo->beginTransaction();
        
        // Lösche Gerichte in dieser Kategorie
        $stmt = $pdo->prepare("DELETE FROM {$prefix}dishes WHERE category_id = ?");
        $stmt->execute([$id]);
        
        // Lösche Kategorie
        $stmt = $pdo->prepare("DELETE FROM {$prefix}menu_categories WHERE id = ?");
        $stmt->execute([$id]);
        
        $pdo->commit();
        $message = "Kategorie gelöscht.";
        $messageType = "success";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Fehler beim Löschen: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Kategorien laden
$stmt = $pdo->query("SELECT * FROM {$prefix}menu_categories ORDER BY sort_order ASC, name ASC");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Menükategorien - EMOS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #1a1a1a; color: #fff; }
        .card { background-color: #222; border-color: #444; }
        .table-dark { background-color: #222; }
        .form-control, .form-select { background-color: #333; color: #fff; border-color: #555; }
        .form-control:focus, .form-select:focus { background-color: #333; color: #fff; border-color: #0d6efd; }
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

<div class="container mt-4" style="max-width: 900px;">
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show mb-4" role="alert" style="display: flex; align-items: center; justify-content: space-between;">
            <span><?php echo htmlspecialchars($message); ?></span>
            <button type="button" class="close-button-wrapper" data-bs-dismiss="alert" aria-label="Close">×</button>
        </div>
    <?php endif; ?>

    <!-- Form -->
    <div class="card mb-4">
        <div class="card-header bg-primary">
            <h5 class="mb-0">Neue Kategorie</h5>
        </div>
        <div class="card-body">
            <form method="post" class="row g-2">
                <div class="col-12 col-md-6">
                    <input type="text" name="name" class="form-control" placeholder="Kategoriename" required>
                </div>
                <div class="col-12 col-md-3">
                    <input type="number" name="sort_order" class="form-control" placeholder="Reihenfolge" min="0" required>
                </div>
                <div class="col-12 col-md-3">
                    <button type="submit" name="create_category" class="btn btn-primary w-100">Erstellen</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabelle -->
    <div class="card">
        <div class="card-header bg-secondary">
            <h5 class="mb-0">Kategorien <span class="badge bg-primary ms-2"><?php echo count($categories); ?></span></h5>
        </div>
        <div class="table-responsive">
            <table class="table table-dark table-hover table-sm mb-0">
                <thead>
                    <tr>
                        <th style="width: 50%">Name</th>
                        <th style="width: 20%">Reihenfolge</th>
                        <th style="width: 30%">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categories)): ?>
                        <tr>
                            <td colspan="3" class="text-center text-muted py-3">Keine Kategorien vorhanden</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td>
                                    <form method="post" id="form_<?php echo $cat['id']; ?>" style="display: inline;">
                                        <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                                        <input type="text" name="name" value="<?php echo htmlspecialchars($cat['name']); ?>" class="form-control form-control-sm" required disabled>
                                </td>
                                <td>
                                    <input type="number" name="sort_order" value="<?php echo $cat['sort_order']; ?>" class="form-control form-control-sm" min="0" disabled>
                                </td>
                                <td class="text-nowrap">
                                    <button type="button" class="btn btn-sm btn-success edit-btn" onclick="toggleEdit(this, <?php echo $cat['id']; ?>)" data-id="<?php echo $cat['id']; ?>">Bearbeiten</button>
                                    <button type="submit" name="update_category" form="form_<?php echo $cat['id']; ?>" class="btn btn-sm btn-warning d-none" data-id="<?php echo $cat['id']; ?>">Speichern</button>
                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" onclick="document.getElementById('deleteForm').innerHTML = '<input type=hidden name=id value=<?php echo $cat['id']; ?>><input type=hidden name=delete_category value=1>'">Löschen</button>
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

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Kategorie löschen?</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Diese Kategorie und alle Gerichte darin werden gelöscht.</p>
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
    const inputs = row.querySelectorAll('input[type=text], input[type=number]');
    const editBtn = btn;
    const saveBtn = document.querySelector(`button[name=update_category][data-id="${id}"]`);
    
    inputs.forEach(input => {
        input.toggleAttribute('disabled');
    });
    
    editBtn.classList.toggle('d-none');
    saveBtn.classList.toggle('d-none');
}
</script>
