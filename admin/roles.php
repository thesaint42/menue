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
    
    // Systemadmin-Rolle (ID 1) kann nicht bearbeitet werden
    if ($id === 1) {
        $message = "Die Systemadmin-Rolle kann nicht bearbeitet werden.";
        $messageType = "danger";
    } else {
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
}

// Rolle löschen
if (isset($_POST['delete_role'])) {
    $id = (int)$_POST['id'];
    
    // Systemadmin-Rolle (ID 1) kann nicht gelöscht werden
    if ($id === 1) {
        $message = "Die Systemadmin-Rolle kann nicht gelöscht werden.";
        $messageType = "danger";
    } else {
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
}

// Feature togglen (via AJAX)
if (isset($_POST['toggle_feature'])) {
    $role_id = (int)$_POST['role_id'];
    $feature_name = trim($_POST['feature_name']);
    $enabled = isset($_POST['enabled']) && $_POST['enabled'] !== '' ? 1 : 0;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO {$prefix}role_features (role_id, feature_name, enabled) 
                             VALUES (?, ?, ?) 
                             ON DUPLICATE KEY UPDATE enabled = ?");
        $stmt->execute([$role_id, $feature_name, $enabled, $enabled]);
        echo json_encode(['success' => true]);
        exit;
    } catch (Exception $e) {
        error_log("Could not update feature: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Menu Access togglen (via AJAX)
if (isset($_POST['toggle_menu_access'])) {
    $role_id = (int)$_POST['role_id'];
    $menu_key = trim($_POST['menu_key']);
    $visible = isset($_POST['visible']) && $_POST['visible'] !== '' ? 1 : 0;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO {$prefix}role_menu_access (role_id, menu_key, visible) 
                             VALUES (?, ?, ?) 
                             ON DUPLICATE KEY UPDATE visible = ?");
        $stmt->execute([$role_id, $menu_key, $visible, $visible]);
        echo json_encode(['success' => true]);
        exit;
    } catch (Exception $e) {
        error_log("Could not update menu access: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Rollen laden
$stmt = $pdo->query("SELECT r.*, COUNT(u.id) as user_count FROM {$prefix}roles r LEFT JOIN {$prefix}users u ON u.role_id = r.id GROUP BY r.id ORDER BY r.name");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lade Features für jede Rolle
$role_features = [];
try {
    foreach ($roles as $role) {
        // Systemadmin (ID 1) hat immer alle Features
        if ($role['id'] === 1) {
            $role_features[$role['id']] = ['project_admin' => 1];
        } else {
            $role_features[$role['id']] = getRoleFeatures($pdo, $role['id'], $prefix);
        }
    }
} catch (Exception $e) {
    error_log("Could not load role_features: " . $e->getMessage());
}

// Definiere verfügbare Features
$available_features = [
    'project_admin' => 'Projektadmin - Kann Projekte verwalten und zuweisen'
];

// Definiere verfügbare Burger-Menü-Punkte
$available_menu_items = [
    'projects' => 'Projekte (Projektverwaltung)',
    'guests' => 'Gästeübersicht',
    'orders' => 'Bestellübersicht',
    'dishes' => 'Speisen',
    'reports' => 'Berichte',
    'export_pdf' => 'PDF Export',
    'users' => 'Benutzer',
    'roles' => 'Rollen',
    'settings_mail' => 'Mail-Einstellungen'
];
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
        .roles-table th:nth-child(1) { width: 30%; min-width: 200px; }
        .roles-table th:nth-child(2) { width: 35%; }
        .roles-table th:nth-child(3) { width: 10%; text-align: center; }
        .roles-table th:nth-child(4) { width: 25%; }
        .action-buttons { 
            display: flex; 
            gap: 0.25rem; 
            flex-wrap: wrap;
            align-items: center;
        }
        .action-btn-group { 
            display: flex; 
            gap: 0.25rem; 
            align-items: center;
        }
        .btn-action { 
            font-size: 0.875rem;
            padding: 0.25rem 0.5rem;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }
        .btn-action-icon { 
            font-size: 1rem;
            display: inline-block;
        }
        .btn-action-text { 
            display: inline;
        }
        @media (max-width: 768px) {
            .roles-table th:nth-child(1) { width: 25%; }
            .roles-table th:nth-child(2) { width: 30%; }
            .roles-table th:nth-child(3) { width: 15%; }
            .roles-table th:nth-child(4) { width: 30%; }
            .btn-action-text { 
                display: none;
            }
            .btn-action { 
                padding: 0.35rem;
            }
        }
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
        /* Feature und Menü Styling */
        .features-section { color: #fff; }
        .features-section .form-check-label { color: #fff; }
        .features-section .form-check-label small { color: #bbb; }
        .menu-section { color: #fff; }
        .menu-section .form-check-label { color: #fff; }
        .menu-section .form-check-label small { color: #bbb; }
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
                                <th class="text-center">Benutzer</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($roles as $role): 
                                $is_system_admin = $role['id'] === 1;
                                $is_protected = $is_system_admin;
                            ?>
                                <tr<?php echo $is_system_admin ? ' style="opacity: 0.9; background-color: #2a3f3a;"' : ''; ?>>
                                    <td>
                                        <form method="post" id="form_<?php echo $role['id']; ?>" class="d-inline">
                                            <input type="hidden" name="id" value="<?php echo $role['id']; ?>">
                                            <input type="text" name="name" value="<?php echo htmlspecialchars($role['name']); ?><?php echo $is_system_admin ? ' 🔒' : ''; ?>" class="form-control form-control-sm w-100" disabled>
                                    </td>
                                    <td>
                                            <textarea name="description" class="form-control form-control-sm w-100" rows="2" disabled><?php echo htmlspecialchars($role['description']); ?></textarea>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info"><?php echo $role['user_count']; ?></span>
                                    </td>
                                    <td>
                                            <div class="action-buttons">
                                                <?php if (!$is_protected): ?>
                                                <button type="button" class="btn btn-sm btn-success btn-action edit-btn" onclick="toggleEdit(this, <?php echo $role['id']; ?>)" title="Bearbeiten">
                                                    <span class="btn-action-icon">✏️</span>
                                                    <span class="btn-action-text">Bearbeiten</span>
                                                </button>
                                                <button type="submit" name="update_role" form="form_<?php echo $role['id']; ?>" class="btn btn-sm btn-warning btn-action d-none" data-id="<?php echo $role['id']; ?>" title="Speichern">
                                                    <span class="btn-action-icon">💾</span>
                                                    <span class="btn-action-text">Speichern</span>
                                                </button>
                                                <?php endif; ?>
                                                <button type="button" class="btn btn-sm btn-info btn-action" data-bs-toggle="collapse" data-bs-target="#features_<?php echo $role['id']; ?>" title="Features">
                                                    <span class="btn-action-icon">⚙️</span>
                                                    <span class="btn-action-text">Features</span>
                                                </button>
                                                <?php if (!$is_protected): ?>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Rolle löschen?');">
                                                    <input type="hidden" name="id" value="<?php echo $role['id']; ?>">
                                                    <button type="submit" name="delete_role" class="btn btn-sm btn-danger btn-action" <?php echo $role['user_count'] > 0 ? 'disabled' : ''; ?> title="Löschen">
                                                        <span class="btn-action-icon">🗑️</span>
                                                        <span class="btn-action-text">Löschen</span>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                                <!-- Features Row -->
                                <tr class="table-secondary"<?php echo $is_system_admin ? ' style="opacity: 0.9; background-color: #1f2f2a;"' : ''; ?>>
                                    <td colspan="4">
                                        <div class="collapse" id="features_<?php echo $role['id']; ?>">
                                            <div class="card card-body bg-dark border-secondary p-3 features-section">
                                                <?php if ($is_system_admin): ?>
                                                <div class="alert alert-info mb-3">
                                                    🔒 <strong>Systemadmin-Rolle</strong> - Diese Rolle hat standardmäßig alle verfügbaren Rechte und kann nicht verändert werden.
                                                </div>
                                                <?php else: ?>
                                                <h6 class="mb-3 text-white">📋 Verfügbare Features:</h6>
                                                <?php endif; ?>
                                                <?php foreach ($available_features as $feature_key => $feature_label): 
                                                    $is_enabled = isset($role_features[$role['id']][$feature_key]) && $role_features[$role['id']][$feature_key];
                                                ?>
                                                <div class="form-check mb-3">
                                                    <input type="checkbox" id="feature_<?php echo $role['id']; ?>_<?php echo $feature_key; ?>" class="form-check-input feature-checkbox" 
                                                           data-role-id="<?php echo $role['id']; ?>" data-feature-key="<?php echo $feature_key; ?>" 
                                                           <?php echo $is_enabled ? 'checked' : ''; ?>
                                                           <?php echo $is_system_admin ? 'disabled' : ''; ?>>
                                                    <label class="form-check-label" for="feature_<?php echo $role['id']; ?>_<?php echo $feature_key; ?>">
                                                        <strong><?php echo htmlspecialchars(explode(' - ', $feature_label)[0]); ?></strong><br>
                                                        <small><?php echo htmlspecialchars(isset($feature_label) ? explode(' - ', $feature_label)[1] : ''); ?></small>
                                                    </label>
                                                </div>
                                                <?php endforeach; ?>
                                                
                                                <?php if (!$is_system_admin): ?>
                                                <hr class="bg-secondary">
                                                <h6 class="mb-3 text-white">🍔 Burger-Menü Punkte:</h6>
                                                <?php endif; ?>
                                                <?php foreach ($available_menu_items as $menu_key => $menu_label): 
                                                    try {
                                                        $stmt = $pdo->prepare("SELECT visible FROM {$prefix}role_menu_access WHERE role_id = ? AND menu_key = ?");
                                                        $stmt->execute([$role['id'], $menu_key]);
                                                        $menu_access = $stmt->fetch(PDO::FETCH_ASSOC);
                                                        $is_visible = $menu_access && $menu_access['visible'];
                                                    } catch (Exception $e) {
                                                        $is_visible = true;
                                                    }
                                                ?>
                                                <div class="form-check mb-2"<?php echo $is_system_admin ? ' style="display: none;"' : ''; ?>>
                                                    <input type="checkbox" id="menu_<?php echo $role['id']; ?>_<?php echo $menu_key; ?>" class="form-check-input menu-checkbox" 
                                                           data-role-id="<?php echo $role['id']; ?>" data-menu-key="<?php echo $menu_key; ?>" 
                                                           <?php echo $is_visible ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="menu_<?php echo $role['id']; ?>_<?php echo $menu_key; ?>">
                                                        <?php echo htmlspecialchars($menu_label); ?>
                                                    </label>
                                                </div>
                                                <?php endforeach; ?>
                                                
                                                <hr class="bg-secondary">
                                                <div class="d-flex gap-2 mt-3">
                                                    <button type="button" class="btn btn-sm btn-secondary" data-bs-toggle="collapse" data-bs-target="#features_<?php echo $role['id']; ?>">✕ Schließen</button>
                                                </div>
                                            </div>
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

// Handle feature checkboxes - save via AJAX
document.querySelectorAll('.feature-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const roleId = this.dataset.roleId;
        const featureKey = this.dataset.featureKey;
        const enabled = this.checked ? '1' : '0';
        
        const formData = new FormData();
        formData.append('toggle_feature', '1');
        formData.append('role_id', roleId);
        formData.append('feature_name', featureKey);
        if (this.checked) {
            formData.append('enabled', '1');
        }
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        }).catch(err => console.error('Error:', err));
    });
});

// Handle menu checkboxes - save via AJAX
document.querySelectorAll('.menu-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const roleId = this.dataset.roleId;
        const menuKey = this.dataset.menuKey;
        
        const formData = new FormData();
        formData.append('toggle_menu_access', '1');
        formData.append('role_id', roleId);
        formData.append('menu_key', menuKey);
        if (this.checked) {
            formData.append('visible', '1');
        }
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        }).catch(err => console.error('Error:', err));
    });
});
</script>
</body>
</html>
