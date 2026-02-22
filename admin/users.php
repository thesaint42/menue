<?php
/**
 * admin/users.php - Benutzerverwaltung
 */

require_once '../db.php';
require_once '../script/auth.php';

checkLogin();
checkAdmin();

$prefix = $config['database']['prefix'] ?? 'menu_';

// Access-Check: Benutzerverwaltung-Berechtigung erforderlich
requireMenuAccess($pdo, 'users', 'read', $prefix);
$message = "";
$messageType = "info";

// Rollen laden
$stmt = $pdo->query("SELECT * FROM {$prefix}roles ORDER BY name");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lade Rollen-Features (mit Fallback wenn Tabelle nicht existiert)
$role_features = [];
try {
    foreach ($roles as $role) {
        $role_features[$role['id']] = getRoleFeatures($pdo, $role['id'], $prefix);
    }
} catch (Exception $e) {
    // role_features table doesn't exist yet
    $role_features = [];
}

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
            $new_user_id = $pdo->lastInsertId();
            
            // Wenn die Rolle "Projekte schreiben" Berechtigung hat, speichere die zugewiesenen Projekte
            // Systemadmin (ID 1) braucht keine Projekt-Zuweisungen - hat automatisch alle Zugriffe
            // Nur Projektadmin (ID 2) und Reporter (ID 3) benötigen Projekt-Zuweisungen
            $has_projects_write = false;
            if ($role_id === 2 || $role_id === 3) {
                $has_projects_write = true;
            } else if ($role_id !== 1) {
                // Andere Rollen (nicht Systemadmin): Prüfe Datenbank
                $stmt_check = $pdo->prepare("SELECT visible FROM {$prefix}role_menu_access WHERE role_id = ? AND menu_key = 'projects_write'");
                $stmt_check->execute([$role_id]);
                $result = $stmt_check->fetch(PDO::FETCH_ASSOC);
                $has_projects_write = $result && $result['visible'];
            }
            
            if ($has_projects_write) {
                try {
                    if (isset($_POST['assigned_projects']) && is_array($_POST['assigned_projects'])) {
                        $stmt = $pdo->prepare("INSERT INTO {$prefix}user_projects (user_id, project_id) VALUES (?, ?)");
                        foreach ($_POST['assigned_projects'] as $project_id) {
                            $stmt->execute([$new_user_id, (int)$project_id]);
                        }
                    }
                } catch (Exception $e) {
                    error_log("Could not assign projects: " . $e->getMessage());
                }
            }
            
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
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
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
            // Wenn Passwort gesetzt, mit aktualisieren
            if (!empty($password)) {
                $password_hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE {$prefix}users SET firstname = ?, lastname = ?, email = ?, password_hash = ?, role_id = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$firstname, $lastname, $email, $password_hash, $role_id, $is_active, $id]);
            } else {
                // Ohne Passwort aktualisieren
                $stmt = $pdo->prepare("UPDATE {$prefix}users SET firstname = ?, lastname = ?, email = ?, role_id = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$firstname, $lastname, $email, $role_id, $is_active, $id]);
            }
            
            // Wenn die neue Rolle "Projekte schreiben" Berechtigung hat, speichere die zugewiesenen Projekte
            // Systemadmin (ID 1) braucht keine Projekt-Zuweisungen - hat automatisch alle Zugriffe
            // Nur Projektadmin (ID 2) und Reporter (ID 3) benötigen Projekt-Zuweisungen
            $has_projects_write = false;
            if ($role_id === 2 || $role_id === 3) {
                $has_projects_write = true;
            } else if ($role_id !== 1) {
                // Andere Rollen (nicht Systemadmin): Prüfe Datenbank
                $stmt_check = $pdo->prepare("SELECT visible FROM {$prefix}role_menu_access WHERE role_id = ? AND menu_key = 'projects_write'");
                $stmt_check->execute([$role_id]);
                $result = $stmt_check->fetch(PDO::FETCH_ASSOC);
                $has_projects_write = $result && $result['visible'];
            }
            
            if ($has_projects_write) {
                try {
                    // Erst alte Zuordnungen löschen
                    $stmt = $pdo->prepare("DELETE FROM {$prefix}user_projects WHERE user_id = ?");
                    $stmt->execute([$id]);
                    
                    // Neue Zuordnungen speichern
                    if (isset($_POST['assigned_projects']) && is_array($_POST['assigned_projects'])) {
                        $stmt = $pdo->prepare("INSERT INTO {$prefix}user_projects (user_id, project_id) VALUES (?, ?)");
                        foreach ($_POST['assigned_projects'] as $project_id) {
                            $stmt->execute([$id, (int)$project_id]);
                        }
                    }
                } catch (Exception $e) {
                    // user_projects table not available yet
                    error_log("Could not update user_projects: " . $e->getMessage());
                }
            } else {
                // Wenn Rolle wechselt weg von "Projektverwaltung", lösche Zuordnungen
                try {
                    $stmt = $pdo->prepare("DELETE FROM {$prefix}user_projects WHERE user_id = ?");
                    $stmt->execute([$id]);
                } catch (Exception $e) {
                    // user_projects table not available yet
                    error_log("Could not delete user_projects: " . $e->getMessage());
                }
            }
            
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

// Lade alle Projekte
$stmt = $pdo->query("SELECT id, name FROM {$prefix}projects ORDER BY name");
$all_projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lade Rollen mit projects_write Berechtigung (oder Projektadmin/Reporter)
$roles_with_projects_write = [];
try {
    $stmt = $pdo->prepare("SELECT DISTINCT role_id FROM {$prefix}role_menu_access WHERE menu_key = 'projects_write' AND visible = 1");
    $stmt->execute();
    $roles_with_projects_write = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
    // Fallback: Projektadmin (ID 2) und Reporter (ID 3) benötigen Projekt-Zuweisungen
    // Systemadmin (ID 1) hat automatisch alle Zugriffe und braucht keine Zuweisungen
    if (!in_array(2, $roles_with_projects_write)) {
        $roles_with_projects_write[] = 2; // Projektadmin
    }
    if (!in_array(3, $roles_with_projects_write)) {
        $roles_with_projects_write[] = 3; // Reporter
    }
} catch (Exception $e) {
    error_log("Could not load roles with projects_write: " . $e->getMessage());
    // Fallback bei Fehler: Projektadmin und Reporter
    $roles_with_projects_write = [2, 3];
}

// Lade für jeden User die zugewiesenen Projekte (nur wenn Rolle Projekt-Zuweisungen braucht)
$user_projects = [];
try {
    // Nutze die bereits geladenen $roles_with_projects_write (inkl. Fallback für IDs 2 und 3)
    if (!empty($roles_with_projects_write)) {
        $placeholders = implode(',', array_fill(0, count($roles_with_projects_write), '?'));
        $stmt = $pdo->prepare("SELECT user_id, project_id FROM {$prefix}user_projects 
                             WHERE user_id IN (SELECT id FROM {$prefix}users WHERE role_id IN ($placeholders))");
        $stmt->execute($roles_with_projects_write);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!isset($user_projects[$row['user_id']])) {
                $user_projects[$row['user_id']] = [];
            }
            $user_projects[$row['user_id']][] = $row['project_id'];
        }
    }
} catch (Exception $e) {
    // Table might not exist yet - that's ok
    error_log("user_projects table not ready: " . $e->getMessage());
    $user_projects = [];
}
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
        
        /* Ansichtsmodus (disabled): Graue Felder mit weißer Schrift */
        .form-control:disabled, .form-select:disabled { 
            background-color: #333; 
            color: #fff; 
            border-color: #555; 
            opacity: 1;
        }
        
        /* Bearbeitungsmodus (enabled): Weiße Felder mit schwarzer Schrift */
        .form-control:not(:disabled), .form-select:not(:disabled) { 
            background-color: #fff; 
            color: #333; 
            border-color: #555; 
        }
        
        .form-control:focus, .form-select:focus { 
            background-color: #fff; 
            color: #333; 
            border-color: #0d6efd; 
        }
        
        /* Passwort-Feld: Placeholder-Farbe anpassen */
        .password-field:disabled::placeholder {
            color: #aaa;
            opacity: 1;
        }
        .password-field:not(:disabled)::placeholder {
            color: #999;
            opacity: 1;
        }
        
        .card .form-label { color: #888; }
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
        .btn i { margin-right: 0.25rem; }
        .form-check-label { 
            user-select: none;
            color: #fff;
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
                            <select name="role_id" id="role_id" class="form-select form-select-sm" required onchange="toggleProjectsCreation()">
                                <option value="">Wählen...</option>
                                <?php foreach ($roles as $role): 
                                    $has_projects_write = in_array($role['id'], $roles_with_projects_write);
                                ?>
                                    <option value="<?php echo $role['id']; ?>" data-has-projects-write="<?php echo $has_projects_write ? '1' : '0'; ?>"><?php echo htmlspecialchars($role['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 projects-creation-section" style="display: none;">
                            <label class="form-label small">Verwaltbare Projekte:</label>
                            <div class="ps-2">
                                <?php foreach ($all_projects as $project): ?>
                                <div class="form-check">
                                    <input type="checkbox" name="assigned_projects[]" value="<?php echo $project['id']; ?>" id="creation_project_<?php echo $project['id']; ?>" class="form-check-input">
                                    <label class="form-check-label" for="creation_project_<?php echo $project['id']; ?>"><?php echo htmlspecialchars($project['name']); ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-12">
                            <button type="submit" name="create_user" class="btn btn-primary w-100">Erstellen</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-8">
            <div class="mb-3">
                <h5>Benutzer (<?php echo count($users); ?>)</h5>
            </div>
            
            <?php if (empty($users)): ?>
                <div class="alert alert-info">Noch keine Benutzer vorhanden.</div>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                <div class="card border-0 shadow mb-3">
                    <div class="card-body">
                        <form method="post" id="form_<?php echo $user['id']; ?>">
                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                            
                            <div class="row g-3">
                                <!-- Name und Aktiv-Status -->
                                <div class="col-12">
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                        <div class="d-flex flex-column flex-md-row gap-2 flex-grow-1">
                                            <input type="text" name="firstname" value="<?php echo htmlspecialchars($user['firstname']); ?>" class="form-control form-control-sm" placeholder="Vorname" disabled>
                                            <input type="text" name="lastname" value="<?php echo htmlspecialchars($user['lastname']); ?>" class="form-control form-control-sm" placeholder="Nachname" disabled>
                                        </div>
                                        <div class="form-check d-flex align-items-center gap-2">
                                            <input type="checkbox" name="is_active" id="active_<?php echo $user['id']; ?>" class="form-check-input mt-0" <?php echo $user['is_active'] ? 'checked' : ''; ?> disabled>
                                            <label class="form-check-label mb-0" for="active_<?php echo $user['id']; ?>" style="white-space: nowrap;">Aktiv</label>
                                        </div>
                                    </div>
                                </div>

                                <!-- E-Mail -->
                                <div class="col-12">
                                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="form-control form-control-sm" placeholder="E-Mail" disabled>
                                </div>

                                <!-- Passwort -->
                                <div class="col-12">
                                    <input type="password" name="password" placeholder="Neues Passwort (optional)" class="form-control form-control-sm password-field" disabled>
                                    <small class="d-block mt-1" style="color: #888;">Leer lassen, um Passwort nicht zu ändern</small>
                                </div>

                                <!-- Rolle -->
                                <div class="col-12">
                                    <label class="form-label small mb-1" style="color: #888;">Rolle</label>
                                    <select name="role_id" class="form-select form-select-sm role-select" onchange="toggleProjectsSection(this, <?php echo $user['id']; ?>)" disabled>
                                        <?php foreach ($roles as $role): 
                                            $has_projects_write = in_array($role['id'], $roles_with_projects_write);
                                        ?>
                                            <option value="<?php echo $role['id']; ?>" data-has-project-admin="<?php echo $has_projects_write ? '1' : '0'; ?>" <?php echo $user['role_id'] == $role['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($role['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Projekt-Zuweisungen -->
                                <?php $has_projects_write = in_array($user['role_id'], $roles_with_projects_write); ?>
                                <div class="col-12 projects-section-<?php echo $user['id']; ?>" style="<?php echo $has_projects_write ? '' : 'display: none;'; ?>">
                                    <label class="form-label small mb-2" style="color: #888;">Verwaltbare Projekte</label>
                                    <div class="ps-2">
                                        <?php if (empty($all_projects)): ?>
                                            <small style="color: #888;">Keine Projekte vorhanden</small>
                                        <?php else: ?>
                                            <?php foreach ($all_projects as $project): ?>
                                            <div class="form-check mb-1">
                                                <input type="checkbox" name="assigned_projects[]" value="<?php echo $project['id']; ?>" id="project_<?php echo $user['id']; ?>_<?php echo $project['id']; ?>" class="form-check-input" <?php echo in_array($project['id'], $user_projects[$user['id']] ?? []) ? 'checked' : ''; ?> disabled>
                                                <label class="form-check-label" for="project_<?php echo $user['id']; ?>_<?php echo $project['id']; ?>"><?php echo htmlspecialchars($project['name']); ?></label>
                                            </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="col-12">
                                    <div class="d-flex justify-content-end gap-2">
                                        <!-- Löschen-Button (außerhalb des Edit-Forms) -->
                                        <button type="button" class="btn btn-sm btn-danger" style="min-width: 110px;" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname'], ENT_QUOTES); ?>')">
                                            <i class="bi bi-trash-fill"></i> <span>Löschen</span>
                                        </button>
                                        
                                        <!-- Bearbeiten/Speichern-Buttons -->
                                        <button type="button" class="btn btn-sm btn-success edit-btn" style="min-width: 110px;" onclick="toggleEdit(this, <?php echo $user['id']; ?>)">
                                            <i class="bi bi-pencil-fill"></i> <span>Bearbeiten</span>
                                        </button>
                                        <button type="submit" name="update_user" class="btn btn-sm btn-warning d-none save-btn-<?php echo $user['id']; ?>" style="min-width: 110px;">
                                            <i class="bi bi-check-lg"></i> <span>Speichern</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                        
                        <!-- Hidden form für Löschen -->
                        <form method="post" id="delete_form_<?php echo $user['id']; ?>" style="display: none;">
                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                            <input type="hidden" name="delete_user" value="1">
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
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
    const card = form.closest('.card-body');
    
    // Toggle Text-, Password- und Select-Felder
    const inputs = card.querySelectorAll('input[type=text], input[type=email], input[type=password], select');
    inputs.forEach(input => {
        input.toggleAttribute('disabled');
    });

    // Toggle is_active checkbox (separat)
    const isActiveCheckbox = card.querySelector('input[name="is_active"]');
    if (isActiveCheckbox) {
        isActiveCheckbox.toggleAttribute('disabled');
    }

    // Toggle Projekt-Checkboxen (nur wenn Projektverwaltung-Rolle)
    const projectCheckboxes = card.querySelectorAll('input[name="assigned_projects[]"]');
    projectCheckboxes.forEach(checkbox => {
        checkbox.toggleAttribute('disabled');
    });

    const editBtn = btn;
    const saveBtn = card.querySelector(`.save-btn-${id}`);
    if (saveBtn) {
        editBtn.classList.toggle('d-none');
        saveBtn.classList.toggle('d-none');
    }
}

function toggleProjectsCreation() {
    const roleSelect = document.getElementById('role_id');
    const projectsSection = document.querySelector('.projects-creation-section');
    if (!roleSelect || !projectsSection) return;
    
    const selectedOption = roleSelect.options[roleSelect.selectedIndex];
    const hasProjectsWrite = selectedOption && selectedOption.dataset.hasProjectsWrite === '1';
    
    projectsSection.style.display = hasProjectsWrite ? 'block' : 'none';
}

function toggleProjectsSection(roleSelect, userId) {
    const projectsSection = document.querySelector(`.projects-section-${userId}`);
    if (!projectsSection) return;
    
    // Check if the selected role has 'project_admin' feature
    const selectedOption = roleSelect.options[roleSelect.selectedIndex];
    const hasProjectAdmin = selectedOption && selectedOption.dataset.hasProjectAdmin === '1';
    
    // Prüfe ob wir im Bearbeitungsmodus sind (andere Felder sind nicht disabled)
    const card = roleSelect.closest('.card-body');
    const otherInputs = card.querySelectorAll('input[type=text], input[type=email]');
    const isEditMode = otherInputs.length > 0 && !otherInputs[0].disabled;
    
    if (hasProjectAdmin) {
        projectsSection.style.display = 'block';
        // Aktiviere Checkboxen nur wenn im Bearbeitungsmodus
        projectsSection.querySelectorAll('input[type=checkbox]').forEach(cb => {
            cb.disabled = !isEditMode;
        });
    } else {
        projectsSection.style.display = 'none';
        // Alle Checkboxes deselektieren und deaktivieren
        projectsSection.querySelectorAll('input[type=checkbox]').forEach(cb => {
            cb.checked = false;
            cb.disabled = true;
        });
    }
}

function deleteUser(userId, userName) {
    if (confirm('⚠️ Benutzer ' + userName + ' wirklich löschen?\n\nDiese Aktion kann nicht rückgängig gemacht werden.')) {
        document.getElementById('delete_form_' + userId).submit();
    }
}
</script>
</body>
</html>
