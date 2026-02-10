<?php
/**
 * admin/roles.php - Rollenverwaltung
 */

require_once '../db.php';
require_once '../script/auth.php';

checkLogin();
checkAdmin();

$prefix = $config['database']['prefix'] ?? 'menu_';
$message = "";
$messageType = "info";

// Rolle erstellen
if (isset($_POST['create_role'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    
    if (empty($name)) {
        $message = "Rollenname ist erforderlich.";
        $messageType = "danger";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO {$prefix}roles (name, description) VALUES (?, ?)");
            $stmt->execute([$name, $description]);
            $message = "Rolle erstellt.";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Fehler beim Erstellen: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Rolle bearbeiten
if (isset($_POST['update_role'])) {
    $id = (int)$_POST['id'];
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    
    if (empty($name)) {
        $message = "Rollenname ist erforderlich.";
        $messageType = "danger";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE {$prefix}roles SET name = ?, description = ? WHERE id = ?");
            $stmt->execute([$name, $description, $id]);
            $message = "Rolle aktualisiert.";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Fehler beim Aktualisieren: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Rolle löschen
if (isset($_POST['delete_role'])) {
    $id = (int)$_POST['id'];
    try {
        // Prüfe ob Benutzer diese Rolle haben
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$prefix}users WHERE role_id = ?");
        $stmt->execute([$id]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            $message = "Rolle kann nicht gelöscht werden, da noch Benutzer diese Rolle haben.";
            $messageType = "danger";
        } else {
            $stmt = $pdo->prepare("DELETE FROM {$prefix}roles WHERE id = ?");
            $stmt->execute([$id]);
            $message = "Rolle gelöscht.";
            $messageType = "success";
        }
    } catch (Exception $e) {
        $message = "Fehler beim Löschen: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Rollen laden
$stmt = $pdo->query("SELECT r.*, COUNT(u.id) as user_count FROM {$prefix}roles r LEFT JOIN {$prefix}users u ON u.role_id = r.id GROUP BY r.id ORDER BY r.name");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rollenverwaltung - EMOS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #1a1a1a; color: #fff; }
        .card { background-color: #222; border-color: #444; }
        .table-dark { background-color: #222; }
        .form-control, .form-select, textarea { background-color: #333; color: #fff; border-color: #555; }
        .form-control:focus, .form-select:focus, textarea:focus { background-color: #333; color: #fff; border-color: #0d6efd; }
        .card .form-label { color: #fff; }
        .roles-table .form-control,
        .roles-table textarea {
            color: #fff;
            background-color: #333;
        }
        .roles-table tbody tr { border-bottom: 12px solid #1a1a1a; }
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
        .action-buttons { display: flex; gap: 0.5rem; }
        .action-buttons .btn { min-width: 120px; }
        .btn-action { min-width: 120px; }
        .action-row { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
        .actions-col { padding-left: 0.5rem; }
        @media (max-width: 576px) {
            .action-buttons { flex-direction: column; }
            .action-buttons .btn { width: 100%; }
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
                    <h5 class="mb-0">Neue Rolle</h5>
                </div>
                <div class="card-body">
                    <form method="post" class="row g-2">
                        <div class="col-12">
                            <label for="name" class="form-label">Rollenname</label>
                            <input type="text" name="name" id="name" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-12">
                            <label for="description" class="form-label">Beschreibung</label>
                            <textarea name="description" id="description" class="form-control form-control-sm" rows="3"></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" name="create_role" class="btn btn-primary w-100">Erstellen</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-8">
            <div class="card">
                <div class="card-header bg-secondary">
                    <h5 class="mb-0">Rollen (<?php echo count($roles); ?>)</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-dark table-hover table-sm mb-0 align-middle roles-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Beschreibung</th>
                                <th>Benutzer</th>
                                <th class="actions-col">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($roles as $role): ?>
                                <tr>
                                    <td>
                                        <form method="post" id="form_<?php echo $role['id']; ?>" class="d-inline">
                                            <input type="hidden" name="id" value="<?php echo $role['id']; ?>">
                                            <input type="text" name="name" value="<?php echo htmlspecialchars($role['name']); ?>" class="form-control form-control-sm w-100" disabled>
                                    </td>
                                    <td>
                                            <textarea name="description" class="form-control form-control-sm w-100" rows="2" disabled><?php echo htmlspecialchars($role['description']); ?></textarea>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $role['user_count']; ?></span>
                                    </td>
                                    <td class="actions-col">
                                            <div class="action-row">
                                                <div class="action-buttons">
                                                    <button type="button" class="btn btn-sm btn-success btn-action edit-btn" onclick="toggleEdit(this, <?php echo $role['id']; ?>)">Bearbeiten</button>
                                                    <button type="submit" name="update_role" form="form_<?php echo $role['id']; ?>" class="btn btn-sm btn-warning btn-action d-none" data-id="<?php echo $role['id']; ?>">Speichern</button>
                                                </div>
                                        </form>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Rolle löschen?');">
                                            <input type="hidden" name="id" value="<?php echo $role['id']; ?>">
                                            <button type="submit" name="delete_role" class="btn btn-sm btn-danger btn-action" <?php echo $role['user_count'] > 0 ? 'disabled' : ''; ?>>Löschen</button>
                                        </form>
                                            </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
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
    if (!form) return;
    const row = form.closest('tr');
    const inputs = row.querySelectorAll('input[type=text], textarea');
    inputs.forEach(input => {
        input.toggleAttribute('disabled');
    });

    const editBtn = btn;
    const saveBtn = row.querySelector(`button[name=update_role][data-id="${id}"]`);
    if (saveBtn) {
        editBtn.classList.toggle('d-none');
        saveBtn.classList.toggle('d-none');
    }
}
</script>
</body>
</html>
