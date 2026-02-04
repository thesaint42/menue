<?php
/**
 * admin/orders.php - Bestellungsübersicht
 */

require_once '../db.php';
require_once '../script/auth.php';
require_once '../nav/top_nav.php';

// Authentifizierung prüfen
checkLogin();

// Projekt auswählen
$project_id = isset($_GET['project']) ? (int)$_GET['project'] : 0;

// Projekte abrufen
$stmt = $pdo->query("SELECT id, name FROM `{$config['database']['prefix']}projects` WHERE is_active = 1 ORDER BY name");
$projects = $stmt->fetchAll();

// Bestellungen abrufen
$orders = [];
if ($project_id > 0) {
    $stmt = $pdo->prepare("
        SELECT 
            g.id as guest_id,
            g.firstname,
            g.lastname,
            g.email,
            g.phone,
            g.guest_type,
            g.age_group,
            g.family_size,
            d.id as dish_id,
            d.name as dish_name,
            dc.name as category_name,
            o.quantity,
            o.created_at
        FROM `{$config['database']['prefix']}guests` g
        LEFT JOIN `{$config['database']['prefix']}orders` o ON g.id = o.guest_id
        LEFT JOIN `{$config['database']['prefix']}dishes` d ON o.dish_id = d.id
        LEFT JOIN `{$config['database']['prefix']}menu_categories` dc ON d.category_id = dc.id
        WHERE g.project_id = ?
        ORDER BY g.firstname, g.lastname, dc.name, d.name
    ");
    $stmt->execute([$project_id]);
    $orders = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="de" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bestellungen - Menüwahl Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col">
            <h1>Bestellungsübersicht</h1>
        </div>
    </div>

    <!-- Projekt-Filter -->
    <div class="card border-0 shadow mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Projekt auswählen</label>
                    <select name="project" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Bitte wählen --</option>
                        <?php foreach ($projects as $proj): ?>
                            <option value="<?php echo $proj['id']; ?>" <?php echo ($proj['id'] == $project_id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($proj['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <?php if ($project_id > 0): ?>
        <?php if (empty($orders)): ?>
            <div class="alert alert-info">Keine Bestellungen für dieses Projekt vorhanden.</div>
        <?php else: ?>
            <!-- Bestellungen Tabelle -->
            <div class="card border-0 shadow">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Gast</th>
                                <th>E-Mail</th>
                                <th>Telefon</th>
                                <th>Gast-Typ</th>
                                <th>Altersgruppe</th>
                                <th>Kategorie</th>
                                <th>Gericht</th>
                                <th class="text-center">Menge</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $current_guest = null;
                            foreach ($orders as $order): 
                                $guest_name = htmlspecialchars($order['firstname']) . ' ' . htmlspecialchars($order['lastname']);
                                $is_new_guest = ($current_guest !== $order['guest_id']);
                                
                                if ($is_new_guest) {
                                    $current_guest = $order['guest_id'];
                                }
                            ?>
                                <tr>
                                    <td>
                                        <?php if ($is_new_guest): ?>
                                            <strong><?php echo $guest_name; ?></strong>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($is_new_guest): ?>
                                            <a href="mailto:<?php echo htmlspecialchars($order['email']); ?>">
                                                <?php echo htmlspecialchars($order['email']); ?>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($is_new_guest): ?>
                                            <?php echo htmlspecialchars($order['phone']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($is_new_guest): ?>
                                            <span class="badge bg-info">
                                                <?php echo ($order['guest_type'] == 'family') ? 'Familie' : 'Einzeln'; ?>
                                                <?php if ($order['guest_type'] == 'family' && $order['family_size']): ?>
                                                    (<?php echo intval($order['family_size']); ?> P.)
                                                <?php endif; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($is_new_guest): ?>
                                            <span class="badge bg-secondary">
                                                <?php echo ($order['age_group'] == 'child') ? 'Kind' : 'Erwachsener'; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($order['category_name'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($order['dish_name'] ?? '(keine Bestellung)'); ?></td>
                                    <td class="text-center">
                                        <?php echo ($order['quantity'] ?? 0); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Export-Buttons -->
            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-primary" onclick="window.print()">
                    <i class="bi bi-printer"></i> Drucken
                </button>
                <a href="export_pdf.php?project=<?php echo $project_id; ?>" class="btn btn-success">
                    <i class="bi bi-file-pdf"></i> PDF exportieren
                </a>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="alert alert-warning">Bitte wählen Sie ein Projekt aus.</div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
