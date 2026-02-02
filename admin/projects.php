<?php
/**
 * admin/projects.php - Projektverwaltung
 */

require_once '../db.php';
require_once '../script/auth.php';

checkLogin();
checkAdmin();

$prefix = $config['database']['prefix'] ?? 'menu_';
$message = "";
$messageType = "info";

// Projekt erstellen
if (isset($_POST['create_project'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $location = trim($_POST['location']);
    $contact_person = trim($_POST['contact_person']);
    $contact_phone = trim($_POST['contact_phone']);
    $contact_email = trim($_POST['contact_email']);
    $max_guests = (int)$_POST['max_guests'];
    $admin_email = trim($_POST['admin_email']);

    if (empty($name) || empty($admin_email) || $max_guests < 1) {
        $message = "Bitte füllen Sie alle erforderlichen Felder aus.";
        $messageType = "danger";
    } elseif (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
        $message = "Ungültige Admin E-Mail.";
        $messageType = "danger";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO {$prefix}projects 
                (name, description, location, contact_person, contact_phone, contact_email, max_guests, admin_email, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $name, $description, $location, $contact_person, $contact_phone, $contact_email, $max_guests, $admin_email, $_SESSION['user_id']
            ]);

            $message = "✓ Projekt erfolgreich erstellt!";
            $messageType = "success";
            logAction($pdo, $prefix, 'project_created', "Projekt: $name");

        } catch (Exception $e) {
            $message = "Fehler: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Projekt deaktivieren
if (isset($_GET['deactivate'])) {
    $id = (int)$_GET['deactivate'];
    $stmt = $pdo->prepare("UPDATE {$prefix}projects SET is_active = 0 WHERE id = ?");
    $stmt->execute([$id]);
    $message = "Projekt deaktiviert.";
    $messageType = "success";
    logAction($pdo, $prefix, 'project_deactivated', "Projekt ID: $id");
}

// Alle Projekte laden
$projects = $pdo->query("SELECT * FROM {$prefix}projects ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>Projektenverwaltung - Menüwahl</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include '../nav/top_nav.php'; ?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Projekte verwalten</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProjectModal">+ Neues Projekt</button>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- PROJEKTE TABELLE -->
    <div class="card border-0 shadow">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Name</th>
                        <th>Ort</th>
                        <th>Gäste</th>
                        <th>Max</th>
                        <th>Admin Email</th>
                        <th>Status</th>
                        <th>Aktion</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $p): ?>
                        <?php 
                            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM {$prefix}guests WHERE project_id = ?");
                            $stmt->execute([$p['id']]);
                            $guest_count = $stmt->fetch()['count'];
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($p['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($p['location'] ?? '–'); ?></td>
                            <td><?php echo $guest_count; ?></td>
                            <td><?php echo $p['max_guests']; ?></td>
                            <td><small><?php echo htmlspecialchars($p['admin_email']); ?></small></td>
                            <td>
                                <?php if ($p['is_active']): ?>
                                    <span class="badge bg-success">Aktiv</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inaktiv</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="../index.php?project=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-info" target="_blank">🔗 Link</a>
                                <a href="dishes.php?project=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-secondary">Menu</a>
                                <a href="guests.php?project=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-secondary">Gäste</a>
                                <?php if ($p['is_active']): ?>
                                    <a href="?deactivate=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Sicher?')">Deakt.</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL: NEUES PROJEKT -->
<div class="modal fade" id="addProjectModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Neues Projekt anlegen</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Projektname *</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ort</label>
                            <input type="text" name="location" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Beschreibung</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ansprechpartner</label>
                            <input type="text" name="contact_person" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Telefon</label>
                            <input type="tel" name="contact_phone" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Kontakt Email</label>
                            <input type="email" name="contact_email" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Max. Gäste *</label>
                            <input type="number" name="max_guests" class="form-control" value="100" min="1" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Admin E-Mail (für BCC) *</label>
                            <input type="email" name="admin_email" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" name="create_project" class="btn btn-primary">Projekt erstellen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
