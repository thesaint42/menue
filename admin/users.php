<?php
/**
 * admin/users.php - Benutzerverwaltung
 */

require_once '../db.php';
require_once '../script/auth.php';

checkLogin();
checkAdmin();

$prefix = $config['database']['prefix'] ?? 'menu_';
$message = "";
$messageType = "info";

// Benutzer erstellen
if (isset($_POST['create_user'])) {
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $role_id = (int)$_POST['role_id'];
    
    if (empty($firstname) || empty($lastname) || empty($email) || empty($password)) {
        $message = "Alle Felder sind erforderlich.";
        $messageType = "danger";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Ungültige E-Mail-Adresse.";
        $messageType = "danger";
    } else {
        try {
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO {$prefix}users (firstname, lastname, email, password_hash, role_id, is_active) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([$firstname, $lastname, $email, $password_hash, $role_id]);
            $message = "Benutzer erstellt.";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Fehler beim Erstellen: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Benutzer aktualisieren
if (isset($_POST['update_user'])) {
    $id = (int)$_POST['id'];
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $email = trim($_POST['email']);
    $role_id = (int)$_POST['role_id'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($firstname) || empty($lastname) || empty($email)) {
        $message = "Alle Felder sind erforderlich.";
        $messageType = "danger";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Ungültige E-Mail-Adresse.";
        $messageType = "danger";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE {$prefix}users SET firstname = ?, lastname = ?, email = ?, role_id = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$firstname, $lastname, $email, $role_id, $is_active, $id]);
            $message = "Benutzer aktualisiert.";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Fehler beim Aktualisieren: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Benutzer löschen
if (isset($_POST['delete_user'])) {
    $id = (int)$_POST['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM {$prefix}users WHERE id = ?");
        $stmt->execute([$id]);
        $message = "Benutzer gelöscht.";
        $messageType = "success";
    } catch (Exception $e) {
        $message = "Fehler beim Löschen: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Benutzer laden
$stmt = $pdo->query("SELECT u.*, r.name as role_name FROM {$prefix}users u LEFT JOIN {$prefix}roles r ON u.role_id = r.id ORDER BY u.firstname, u.lastname");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Rollen laden
$stmt = $pdo->query("SELECT * FROM {$prefix}roles ORDER BY name");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Benutzerverwaltung - EMOS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #1a1a1a; color: #fff; }
        .card { background-color: #222; border-color: #444; }
        .table-dark { background-color: #222; }
        .form-control, .form-select { background-color: #333; color: #fff; border-color: #555; }
        .form-control:focus, .form-select:focus { background-color: #333; color: #fff; border-color: #0d6efd; }
        .card .form-label { color: #fff; }
        .users-table .form-control,
        .users-table .form-select {
            color: #fff;
            background-color: #333;
        }
        .users-table tbody tr { border-bottom: 12px solid #1a1a1a; }
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
                    <h5 class="mb-0">Neuer Benutzer</h5>
                </div>
                <div class="card-body">
                    <form method="post" class="row g-2">
                        <div class="col-12 col-sm-6">
                            <label for="firstname" class="form-label">Vorname</label>
                            <input type="text" name="firstname" id="firstname" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label for="lastname" class="form-label">Nachname</label>
                            <input type="text" name="lastname" id="lastname" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-12">
                            <label for="email" class="form-label">E-Mail</label>
                            <input type="email" name="email" id="email" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-12">
                            <label for="password" class="form-label">Passwort</label>
                            <input type="password" name="password" id="password" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-12">
                            <label for="role_id" class="form-label">Rolle</label>
                            <select name="role_id" id="role_id" class="form-select form-select-sm" required>
                                <option value="">Wählen...</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" name="create_user" class="btn btn-primary w-100">Erstellen</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-8">
            <div class="card">
                <div class="card-header bg-secondary">
                    <h5 class="mb-0">Benutzer (<?php echo count($users); ?>)</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-dark table-hover table-sm mb-0 align-middle users-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Aktiv</th>
                                <th class="actions-col">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <form method="post" id="form_<?php echo $user['id']; ?>" class="d-inline">
                                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                            <div class="d-flex flex-column flex-md-row gap-2">
                                                <input type="text" name="firstname" value="<?php echo htmlspecialchars($user['firstname']); ?>" class="form-control form-control-sm w-100" disabled>
                                                <input type="text" name="lastname" value="<?php echo htmlspecialchars($user['lastname']); ?>" class="form-control form-control-sm w-100" disabled>
                                            </div>
                                            <div class="mt-2">
                                                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="form-control form-control-sm w-100" disabled>
                                            </div>
                                            <div class="mt-2">
                                                <select name="role_id" class="form-select form-select-sm w-100" disabled>
                                                    <?php foreach ($roles as $role): ?>
                                                        <option value="<?php echo $role['id']; ?>" <?php echo $user['role_id'] == $role['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($role['name']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                    </td>
                                    <td class="text-center">
                                            <input type="checkbox" name="is_active" class="form-check-input" <?php echo $user['is_active'] ? 'checked' : ''; ?> disabled>
                                    </td>
                                        <td class="actions-col">
                                            <div class="action-row">
                                                <div class="action-buttons">
                                                    <button type="button" class="btn btn-sm btn-success btn-action edit-btn" onclick="toggleEdit(this, <?php echo $user['id']; ?>)">Bearbeiten</button>
                                                    <button type="submit" name="update_user" form="form_<?php echo $user['id']; ?>" class="btn btn-sm btn-warning btn-action d-none" data-id="<?php echo $user['id']; ?>">Speichern</button>
                                                </div>
                                        </form>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Benutzer löschen?');">
                                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="delete_user" class="btn btn-sm btn-danger btn-action">Löschen</button>
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
    const inputs = row.querySelectorAll('input[type=text], input[type=email], input[type=checkbox], select');
    inputs.forEach(input => {
        input.toggleAttribute('disabled');
    });

    const editBtn = btn;
    const saveBtn = row.querySelector(`button[name=update_user][data-id="${id}"]`);
    if (saveBtn) {
        editBtn.classList.toggle('d-none');
        saveBtn.classList.toggle('d-none');
    }
}
</script>
</body>
</html>
