<?php
/**
 * admin/guests.php - Gästeübersicht
 */

require_once '../db.php';
require_once '../script/auth.php';

checkLogin();

$prefix = $config['database']['prefix'] ?? 'menu_';
$project_id = isset($_GET['project']) ? (int)$_GET['project'] : null;
$message = "";
$messageType = "info";

// Projekte IMMER laden für Dropdown
$projects = $pdo->query("SELECT * FROM {$prefix}projects WHERE is_active = 1 ORDER BY name")->fetchAll();

if (!$project_id) {
    if (empty($projects)) {
        // Saubere Fehlerseite anzeigen: kein Projekt angelegt
        ?>
        <!DOCTYPE html>
        <html lang="de" data-bs-theme="dark">
        <head>
            <meta charset="UTF-8">
            <title>Keine Projekte - EMOS</title>
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
                            <h3 class="mb-3">Keine Projekte vorhanden</h3>
                            <p class="text-muted">Es wurde noch kein Projekt angelegt. Bitte legen Sie zuerst ein Projekt an, damit Gäste verwaltet werden können.</p>
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
}

// Projekt laden - nur wenn project_id gesetzt ist
$project = null;
if ($project_id) {
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
                        <p class="text-muted">Das angeforderte Projekt existiert nicht oder wurde gelöscht.</p>
                        <a href="projects.php" class="btn btn-primary mt-3">Zur Projektübersicht</a>
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
}

// Weitere Verarbeitung nur wenn project_id gesetzt ist
if ($project_id) {
    // Tabellenverfügbarkeit für Snapshot prüfen
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute(["{$prefix}order_guest_data"]);
    $has_order_guest_data = $stmt->fetchColumn() > 0;

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute(["{$prefix}order_people"]);
    $has_order_people = $stmt->fetchColumn() > 0;

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'order_id'");
    $stmt->execute(["{$prefix}guests"]);
    $has_guest_order_id = $stmt->fetchColumn() > 0;

    // Bestellung oder Einzelperson löschen
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            // Szenario 1: Ganze Bestellung löschen
            if (isset($_POST['delete_order_id'])) {
                $delete_order_id = trim($_POST['delete_order_id'] ?? '');
                if ($delete_order_id !== '') {
                    // Lösche Menüauswahlen (orders)
                $stmt = $pdo->prepare("DELETE FROM {$prefix}orders WHERE order_id = ?");
                $stmt->execute([$delete_order_id]);
                
                // Lösche order_people
                if ($has_order_people) {
                    $stmt = $pdo->prepare("DELETE FROM {$prefix}order_people WHERE order_id = ?");
                    $stmt->execute([$delete_order_id]);
                }
                
                // Lösche order_guest_data
                if ($has_order_guest_data) {
                    $stmt = $pdo->prepare("DELETE FROM {$prefix}order_guest_data WHERE order_id = ?");
                    $stmt->execute([$delete_order_id]);
                }
                
                // Lösche order_sessions
                $stmt = $pdo->prepare("DELETE FROM {$prefix}order_sessions WHERE order_id = ?");
                $stmt->execute([$delete_order_id]);
                
                // Finde und lösche guest + alle family_members
                $stmt = $pdo->prepare("SELECT id FROM {$prefix}guests WHERE project_id = ? AND order_id = ? LIMIT 1");
                $stmt->execute([$project_id, $delete_order_id]);
                $guest = $stmt->fetch();
                if ($guest) {
                    // Lösche alle family_members
                    $stmt = $pdo->prepare("DELETE FROM {$prefix}family_members WHERE guest_id = ?");
                    $stmt->execute([$guest['id']]);
                    
                    // Lösche den Gast selbst
                    $stmt = $pdo->prepare("DELETE FROM {$prefix}guests WHERE id = ?");
                    $stmt->execute([$guest['id']]);
                }
                
                // Nach erfolgreichem Löschen Redirect
                header("Location: " . $_SERVER['PHP_SELF'] . "?project=" . $project_id);
                exit;
            }
        }
        
        // Szenario 2: Einzelne Person aus Bestellung löschen
        if (isset($_POST['delete_person_order_id']) && isset($_POST['delete_person_index'])) {
            $person_order_id = trim($_POST['delete_person_order_id'] ?? '');
            $person_index = (int)$_POST['delete_person_index'];
            
            if ($person_order_id !== '' && $person_index >= 0) {
                try {
                    // WICHTIG: orders.person_id speichert den person_index!
                    // Lösche Menüauswahlen dieser Person mit person_id
                    $stmt = $pdo->prepare("DELETE FROM {$prefix}orders WHERE order_id = ? AND person_id = ?");
                    $stmt->execute([$person_order_id, $person_index]);
                    
                    // WENN Haupt-Person (person_index = 0): Lösche KOMPLETTE BESTELLUNG
                    if ($person_index === 0) {
                        // Lösche order_guest_data
                        if ($has_order_guest_data) {
                            $stmt = $pdo->prepare("DELETE FROM {$prefix}order_guest_data WHERE order_id = ?");
                            $stmt->execute([$person_order_id]);
                        }
                        
                        // Lösche order_people
                        if ($has_order_people) {
                            $stmt = $pdo->prepare("DELETE FROM {$prefix}order_people WHERE order_id = ?");
                            $stmt->execute([$person_order_id]);
                        }
                        
                        // Lösche order_sessions
                        $stmt = $pdo->prepare("DELETE FROM {$prefix}order_sessions WHERE order_id = ?");
                        $stmt->execute([$person_order_id]);
                        
                        // Finde und lösche guest + alle family_members
                        $stmt = $pdo->prepare("SELECT id FROM {$prefix}guests WHERE project_id = ? AND order_id = ? LIMIT 1");
                        $stmt->execute([$project_id, $person_order_id]);
                        $guest = $stmt->fetch();
                        if ($guest) {
                            $stmt = $pdo->prepare("DELETE FROM {$prefix}family_members WHERE guest_id = ?");
                            $stmt->execute([$guest['id']]);
                            
                            $stmt = $pdo->prepare("DELETE FROM {$prefix}guests WHERE id = ?");
                            $stmt->execute([$guest['id']]);
                        }
                    } else {
                        // FAMILIENMITGLIED (person_index > 0): Lösche NUR diese Person
                        // Lösche aus order_people
                        if ($has_order_people) {
                            $stmt = $pdo->prepare("DELETE FROM {$prefix}order_people WHERE order_id = ? AND person_index = ?");
                            $stmt->execute([$person_order_id, $person_index]);
                        }
                        
                        // Finde guest und lösche das Familienmitglied MIT PASSENDEM INDEX
                        $stmt = $pdo->prepare("SELECT id FROM {$prefix}guests WHERE project_id = ? AND order_id = ? LIMIT 1");
                        $stmt->execute([$project_id, $person_order_id]);
                        $guest = $stmt->fetch();
                        if ($guest) {
                            // Lösche das family_member mit der richtigen Position
                            $stmt = $pdo->prepare("SELECT id FROM {$prefix}family_members WHERE guest_id = ? ORDER BY id LIMIT 1 OFFSET ?");
                            $stmt->execute([$guest['id'], $person_index - 1]);
                            $fam_member = $stmt->fetch();
                            if ($fam_member) {
                                $stmt = $pdo->prepare("DELETE FROM {$prefix}family_members WHERE id = ?");
                                $stmt->execute([$fam_member['id']]);
                            }
                        }
                    }
                    
                    // Nach erfolgreichem Löschen Redirect
                    header("Location: " . $_SERVER['PHP_SELF'] . "?project=" . $project_id);
                    exit;
                } catch (Exception $e) {
                    error_log("Delete person error: " . $e->getMessage());
                    die("Fehler beim Löschen: " . htmlspecialchars($e->getMessage()));
                }
            }
        }
    } catch (Exception $e) {
        error_log("Delete error: " . $e->getMessage());
        die("Fehler beim Löschen: " . htmlspecialchars($e->getMessage()));
    }
}

    // Bestellungen laden (gruppiert nach order_id)
    $stmt = $pdo->prepare("SELECT DISTINCT os.order_id, os.created_at, os.email
                           FROM {$prefix}order_sessions os
                       WHERE os.project_id = ?
                       ORDER BY os.created_at DESC");
    $stmt->execute([$project_id]);
    $orders_list = $stmt->fetchAll();

    // Strukturiere Bestellungen mit Personen
    $orders_with_people = [];
    foreach ($orders_list as $order_row) {
        $order_id = $order_row['order_id'];
        
        // Zähle Menüauswahlen (Gerichte) dieser Bestellung
        $stmt = $pdo->prepare("SELECT COUNT(*) as dish_count FROM {$prefix}orders WHERE order_id = ?");
        $stmt->execute([$order_id]);
        $dish_count = (int)$stmt->fetchColumn();
        
        // Lade alle Personen dieser Bestellung
        $people = [];
        
        if ($has_order_people) {
            $stmt = $pdo->prepare("SELECT * FROM {$prefix}order_people WHERE order_id = ? ORDER BY person_index");
            $stmt->execute([$order_id]);
            $people = $stmt->fetchAll();
        }
        
        // Fallback: Versuche aus order_guest_data + legacy guests zu laden
        if (empty($people) && $has_order_guest_data) {
            // Lade Haupt-Gast aus order_guest_data
            $stmt = $pdo->prepare("SELECT * FROM {$prefix}order_guest_data WHERE order_id = ? LIMIT 1");
            $stmt->execute([$order_id]);
            $guest_data = $stmt->fetch();
            
            if ($guest_data) {
                // Nutzer das Haupt-Gast als person_index 0
                $people[] = [
                    'person_index' => 0,
                    'name' => ($guest_data['firstname'] ?? '') . ' ' . ($guest_data['lastname'] ?? ''),
                    'email' => $guest_data['email'] ?? '',
                    'person_type' => $guest_data['person_type'] ?? 'adult',
                    'child_age' => $guest_data['child_age'] ?? null,
                    'highchair_needed' => $guest_data['highchair_needed'] ?? 0
                ];
            }
        }
        
        $orders_with_people[] = [
            'order_id' => $order_id,
            'created_at' => $order_row['created_at'],
            'email' => $order_row['email'],
            'dish_count' => $dish_count,
            'people' => $people,
            'highchair_count' => count(array_filter($people, fn($p) => isset($p['highchair_needed']) && $p['highchair_needed']))
        ];
    }

    // DEBUG auf Seite anzeigen
    $debug_info = "Project: $project_id | Found: " . count($orders_list) . " orders | Loaded: " . count($orders_with_people) . " with people";
    if (!empty($orders_with_people)) {
        $first_order = $orders_with_people[0];
        $debug_info .= " | First: ID=" . $first_order['order_id'] . ", dishes=" . $first_order['dish_count'] . ", people=" . count($first_order['people']);
    }
} else {
    // Initialisiere $orders_with_people wenn project_id nicht gesetzt ist
    $orders_with_people = [];
    $debug_info = "";
}
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>Gästeübersicht - Event Menue Order System (EMOS)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include '../nav/top_nav.php'; ?>

<div class="container py-4">
    <h2 class="mb-4">Gästeübersicht</h2>

    <!-- PROJEKT FILTER (Select wie in orders.php) -->
    <div class="card border-0 shadow mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Projekt auswählen</label>
                    <select name="project" class="form-select" onchange="this.form.submit()">
                        <option value="" <?php echo ($project_id === null) ? 'selected' : ''; ?>>-- Bitte wählen --</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo ($project_id !== null && $p['id'] == $project_id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <!-- GÄSTE TABELLE - Nur wenn Projekt ausgewählt -->
    <?php if ($project_id): ?>
    <div class="card border-0 shadow">
        <div class="card-header bg-success text-white py-3">
            <h5 class="mb-0"><?php echo htmlspecialchars($project['name']); ?> - <?php echo count($orders_with_people); ?> Bestellung(en)</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th colspan="2">Bestell-Nr. / Person</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Typ</th>
                        <th>Alter</th>
                        <th class="text-center">Bestellung</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders_with_people)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">Noch keine Bestellungen vorhanden.</td></tr>
                    <?php else: ?>
                        <?php foreach ($orders_with_people as $order_data): ?>
                            <!-- BESTELLUNGS-HAUPTZEILE -->
                            <tr class="order-header" style="background-color: #3d3d3d; font-weight: bold;">
                                <td colspan="2">
                                    📦 Bestellung #<?php echo htmlspecialchars($order_data['order_id']); ?>
                                    <small class="text-muted ms-2"><?php echo date('d.m.Y H:i', strtotime($order_data['created_at'])); ?></small>
                                </td>
                                <td></td>
                                <td><?php echo htmlspecialchars($order_data['email']); ?></td>
                                <td>
                                    <?php if ($order_data['highchair_count'] > 0): ?>
                                        <?php echo $order_data['highchair_count']; ?> x 🪑
                                    <?php endif; ?>
                                </td>
                                <td></td>
                                <td class="text-center fw-bold" style="background-color: #4d4d4d;">
                                    <?php echo count($order_data['people']); ?>
                                </td>
                                <td>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Bestellung und alle Personen/Gerichte wirklich löschen?');">
                                        <input type="hidden" name="delete_order_id" value="<?php echo htmlspecialchars($order_data['order_id']); ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Alles löschen</button>
                                    </form>
                                </td>
                            </tr>
                            
                            <!-- PERSONEN-UNTERZEILEN -->
                            <?php foreach ($order_data['people'] as $person): ?>
                                <tr class="order-person" style="padding-left: 2em;">
                                    <td style="width: 1%; color: #888;">└─</td>
                                    <td style="padding-left: 1em;">
                                        Person <?php echo ($person['person_index'] + 1); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($person['name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($person['email'] ?? '–'); ?></td>
                                    <td>
                                        <?php 
                                            $type = strtolower($person['person_type'] ?? 'adult');
                                            echo $type === 'child' ? 'Kind' : 'Erwachsen';
                                            if ($type === 'child' && isset($person['highchair_needed']) && $person['highchair_needed']) {
                                                echo ' 🪑';
                                            }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                            if (strtolower($person['person_type'] ?? 'adult') === 'child') {
                                                echo htmlspecialchars($person['child_age'] ?? '–');
                                            } else {
                                                echo '–';
                                            }
                                        ?>
                                    </td>
                                    <td class="text-center">1</td>
                                    <td>
                                        <form method="post" style="display: inline;" onsubmit="return confirm('Person und zugehörige Gerichte löschen?');">
                                            <input type="hidden" name="delete_person_order_id" value="<?php echo htmlspecialchars($order_data['order_id']); ?>">
                                            <input type="hidden" name="delete_person_index" value="<?php echo (int)$person['person_index']; ?>">
                                            <button type="submit" class="btn btn-sm btn-warning">Löschen</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
