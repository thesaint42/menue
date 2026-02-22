<?php
/**
 * admin/orders.php - Bestell­übersicht
 */

require_once '../db.php';
require_once '../script/auth.php';

// Authentifizierung prüfen
checkLogin();

// Prefix definieren
$prefix = $config['database']['prefix'] ?? 'menu_';

// Projekt auswählen
$project_id = isset($_GET['project']) ? (int)$_GET['project'] : 0;

// Projekte abrufen (nur zugängliche für Benutzer mit projects_write Berechtigung)
$user_role_id = $_SESSION['role_id'] ?? null;

if ($user_role_id === 1) {
    // Admin: alle Projekte
    $stmt = $pdo->query("SELECT id, name FROM `{$prefix}projects` WHERE is_active = 1 ORDER BY name");
} else if (hasMenuAccess($pdo, 'projects_write', $prefix)) {
    // Project Admin: nur zugewiesene Projekte
    $assigned = getUserProjects($pdo, $prefix);
    if (!empty($assigned)) {
        $project_ids = array_column($assigned, 'id');
        $placeholders = implode(',', array_fill(0, count($project_ids), '?'));
        $stmt = $pdo->prepare("SELECT id, name FROM `{$prefix}projects` WHERE is_active = 1 AND id IN ($placeholders) ORDER BY name");
        $stmt->execute($project_ids);
    } else {
        $stmt = $pdo->prepare("SELECT id, name FROM `{$prefix}projects` WHERE id = 0"); // keine Projekte
    }
} else {
    // Andere Rollen: keine Projekte
    $stmt = $pdo->prepare("SELECT id, name FROM `{$prefix}projects` WHERE id = 0");
}
$projects = $stmt->fetchAll();

// Bestellungen abrufen
$orders = [];
$project = null;
if ($project_id > 0) {
    // Prüfe ob User Zugriff auf dieses Projekt hat
    if (!hasProjectAccess($pdo, $project_id, $prefix)) {
        die("Zugriff verweigert: Sie haben keine Berechtigung für dieses Projekt.");
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id, name, access_pin FROM `{$prefix}projects` WHERE id = ?");
        $stmt->execute([$project_id]);
        $project = $stmt->fetch();

        $prefix = $config['database']['prefix'];
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $stmt->execute(["{$prefix}order_guest_data"]);
        $has_order_guest_data = $stmt->fetchColumn() > 0;

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $stmt->execute(["{$prefix}order_people"]);
        $has_order_people = $stmt->fetchColumn() > 0;

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'order_id'");
        $stmt->execute(["{$prefix}guests"]);
        $has_guest_order_id = $stmt->fetchColumn() > 0;

        // POST-Aktionen: Bestellung oder Person löschen
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['delete_order_id'])) {
                $delete_order_id = trim($_POST['delete_order_id']);
                if ($delete_order_id !== '') {
                    $stmt = $pdo->prepare("SELECT email FROM `{$prefix}order_sessions` WHERE order_id = ? AND project_id = ? LIMIT 1");
                    $stmt->execute([$delete_order_id, $project_id]);
                    $order_email = $stmt->fetchColumn();

                    if ($has_order_people) {
                        $stmt = $pdo->prepare("DELETE FROM `{$prefix}order_people` WHERE order_id = ?");
                        $stmt->execute([$delete_order_id]);
                    }
                    if ($has_order_guest_data) {
                        $stmt = $pdo->prepare("DELETE FROM `{$prefix}order_guest_data` WHERE order_id = ?");
                        $stmt->execute([$delete_order_id]);
                    }

                    $stmt = $pdo->prepare("DELETE FROM `{$prefix}orders` WHERE order_id = ?");
                    $stmt->execute([$delete_order_id]);
                    $stmt = $pdo->prepare("DELETE FROM `{$prefix}order_sessions` WHERE order_id = ?");
                    $stmt->execute([$delete_order_id]);

                    if ($order_email) {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$prefix}order_sessions` WHERE project_id = ? AND email = ?");
                        $stmt->execute([$project_id, $order_email]);
                        $remaining_orders = (int)$stmt->fetchColumn();

                        if ($remaining_orders === 0) {
                            $stmt = $pdo->prepare("SELECT id FROM `{$prefix}guests` WHERE project_id = ? AND email = ?");
                            $stmt->execute([$project_id, $order_email]);
                            $guest_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

                            if (!empty($guest_ids)) {
                                $stmt = $pdo->prepare("DELETE FROM `{$prefix}family_members` WHERE guest_id = ?");
                                foreach ($guest_ids as $gid) {
                                    $stmt->execute([$gid]);
                                }
                            }

                            $stmt = $pdo->prepare("DELETE FROM `{$prefix}guests` WHERE project_id = ? AND email = ?");
                            $stmt->execute([$project_id, $order_email]);
                        }
                    }
                }
            }

            if (isset($_POST['delete_person_order_id'], $_POST['delete_person_index'])) {
                $delete_order_id = trim($_POST['delete_person_order_id']);
                $delete_person_index = (int)$_POST['delete_person_index'];
                if ($delete_order_id !== '') {
                    $stmt = $pdo->prepare("DELETE FROM `{$prefix}orders` WHERE order_id = ? AND person_id = ?");
                    $stmt->execute([$delete_order_id, $delete_person_index]);
                    if ($has_order_people) {
                        $stmt = $pdo->prepare("DELETE FROM `{$prefix}order_people` WHERE order_id = ? AND person_index = ?");
                        $stmt->execute([$delete_order_id, $delete_person_index]);
                    }

                    // Familienmitglied entfernen, wenn keine Bestellungen mehr vorhanden
                    if ($delete_person_index !== 0) {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$prefix}orders` WHERE order_id = ? AND person_id = ?");
                        $stmt->execute([$delete_order_id, $delete_person_index]);
                        $remaining_person_orders = (int)$stmt->fetchColumn();

                        if ($remaining_person_orders === 0) {
                            if ($has_guest_order_id) {
                                $stmt = $pdo->prepare("SELECT id FROM `{$prefix}guests` WHERE project_id = ? AND order_id = ? LIMIT 1");
                                $stmt->execute([$project_id, $delete_order_id]);
                            } else {
                                $stmt = $pdo->prepare("SELECT email FROM `{$prefix}order_sessions` WHERE order_id = ? AND project_id = ? LIMIT 1");
                                $stmt->execute([$delete_order_id, $project_id]);
                                $email = $stmt->fetchColumn();
                                $stmt = $pdo->prepare("SELECT id FROM `{$prefix}guests` WHERE project_id = ? AND email = ? LIMIT 1");
                                $stmt->execute([$project_id, $email]);
                            }
                            $guest_row = $stmt->fetch();
                            if ($guest_row && isset($guest_row['id'])) {
                                $stmt = $pdo->prepare("DELETE FROM `{$prefix}family_members` WHERE id = ? AND guest_id = ?");
                                $stmt->execute([$delete_person_index, $guest_row['id']]);
                            }
                        }
                    }
                }
            }
        }

        if ($has_order_guest_data && $has_order_people) {
            // v3.0 Snapshot: order_sessions + order_guest_data + order_people + orders
            $sql = "SELECT
                    os.order_id,
                    os.email,
                    os.created_at as order_date,
                    og.firstname,
                    og.lastname,
                    og.phone,
                    og.guest_type,
                    op.name as person_name,
                    op.person_type as member_type,
                    op.child_age,
                    op.highchair_needed,
                    o.person_id,
                    d.name as dish_name,
                    mc.name as category_name,
                    mc.sort_order as category_sort
                FROM `{$prefix}order_sessions` os
                LEFT JOIN `{$prefix}order_guest_data` og ON og.order_id = os.order_id
                LEFT JOIN `{$prefix}orders` o ON os.order_id = o.order_id
                LEFT JOIN `{$prefix}order_people` op ON op.order_id = os.order_id AND op.person_index = o.person_id
                LEFT JOIN `{$prefix}dishes` d ON o.dish_id = d.id
                LEFT JOIN `{$prefix}menu_categories` mc ON o.category_id = mc.id
                WHERE os.project_id = ?
                ORDER BY os.created_at DESC, os.order_id, o.person_id, mc.sort_order";
        } else {
            // Legacy: guests + family_members + orders
            $guest_join = $has_guest_order_id
                ? "g.project_id = os.project_id AND g.order_id = os.order_id"
                : "g.project_id = os.project_id AND g.email = os.email";

            $sql = "SELECT
                    os.order_id,
                    os.email,
                    os.created_at as order_date,
                    g.firstname,
                    g.lastname,
                    g.phone,
                    g.guest_type,
                    fm.name as person_name,
                    fm.member_type,
                    fm.child_age,
                    fm.highchair_needed,
                    o.person_id,
                    d.name as dish_name,
                    mc.name as category_name,
                    mc.sort_order as category_sort
                FROM `{$prefix}order_sessions` os
                LEFT JOIN `{$prefix}guests` g ON {$guest_join}
                LEFT JOIN `{$prefix}orders` o ON os.order_id = o.order_id
                LEFT JOIN `{$prefix}family_members` fm ON g.id = fm.guest_id
                LEFT JOIN `{$prefix}dishes` d ON o.dish_id = d.id
                LEFT JOIN `{$prefix}menu_categories` mc ON o.category_id = mc.id
                WHERE os.project_id = ?
                ORDER BY os.created_at DESC, os.order_id, o.person_id, mc.sort_order";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$project_id]);
        $orders = $stmt->fetchAll();
    } catch (Throwable $e) {
        // Write debug information to storage/logs/orders_error.log
        $logDir = __DIR__ . '/../storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $msg = "[" . date('c') . "] Exception fetching orders for project {$project_id}: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n";
        @file_put_contents($logDir . '/orders_error.log', $msg, FILE_APPEND | LOCK_EX);
        http_response_code(500);
        echo '<h2>Interner Serverfehler</h2><p>Fehler beim Laden der Bestellungen. Bitte prüfe die Logs: storage/logs/orders_error.log</p>';
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="de" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bestellungen - Event Menue Order System (EMOS) Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .page-container { max-width: 900px; }
        
        /* Order Header Meta - linksbündig */
        .order-header-meta { text-align: left; }
        
        /* Buttons mit Icons */
        .btn-with-icon .btn-icon {
            margin-right: 0.5rem;
        }
        
        /* Mobile: nur Icons für Buttons, Telefon-Block */
        @media (max-width: 576px) {
            .btn-with-icon .btn-text { display: none; }
            .btn-with-icon .btn-icon { margin-right: 0; }
            .btn-with-icon {
                min-width: auto;
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }
            
            /* Telefonnummer unter E-Mail auf Mobile */
            small.d-block.d-md-inline {
                display: block !important;
            }
            
            /* Hochstuhl-Badge: nur Icon auf Mobile */
            .badge-highchair .badge-text {
                display: none;
            }
            
            /* Person-Aktionen rechtsbündig auf Mobile */
            .person-actions {
                width: 100%;
                justify-content: flex-end !important;
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>

<?php include '../nav/top_nav.php'; ?>

<div class="container py-4 page-container">
    <div class="row mb-4">
        <div class="col">
            <h1>Bestellungen</h1>
        </div>
    </div>

    <!-- Projekt-Filter -->
    <div class="card border-0 shadow mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-12 col-md-6">
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
        <div class="mb-4 d-flex justify-content-between align-items-center">
            <div>
                <h5><?php echo htmlspecialchars($project['name']); ?> - <?php echo count($orders); ?> Bestellung(en)</h5>
            </div>
            <div>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#pdfModal">
                    📄 PDF Export
                </button>
            </div>
        </div>
        
        <?php if (empty($orders)): ?>
            <div class="alert alert-info">Keine Bestellungen für dieses Projekt vorhanden.</div>
        <?php else: ?>
            <!-- Bestellungen gruppiert nach Order-ID -->
            <?php 
            // Gruppiere Bestellungen nach Order-ID
            $grouped_orders = [];
            foreach ($orders as $order) {
                $order_id = $order['order_id'];
                if (!isset($grouped_orders[$order_id])) {
                    $grouped_orders[$order_id] = [
                        'email' => $order['email'],
                        'firstname' => $order['firstname'],
                        'lastname' => $order['lastname'],
                        'phone' => $order['phone'],
                        'guest_type' => $order['guest_type'],
                        'order_date' => $order['order_date'],
                        'order_id' => $order_id,
                        'persons' => []
                    ];
                }
                
                // Gruppiere nach Person
                $person_id = $order['person_id'] ?? 0;
                if (!isset($grouped_orders[$order_id]['persons'][$person_id])) {
                    $grouped_orders[$order_id]['persons'][$person_id] = [
                        'name' => $order['person_name'] ?? ($order['firstname'] . ' ' . $order['lastname']),
                        'type' => $order['member_type'] ?? 'adult',
                        'age' => $order['child_age'] ?? null,
                        'highchair' => $order['highchair_needed'] ?? 0,
                        'person_index' => $person_id,
                        'dishes' => []
                    ];
                }
                
                if ($order['dish_name']) {
                    $grouped_orders[$order_id]['persons'][$person_id]['dishes'][] = [
                        'category' => $order['category_name'],
                        'dish' => $order['dish_name']
                    ];
                }
            }
            ?>
            
            <?php foreach ($grouped_orders as $order_id => $order_data): ?>
            <div class="card border-0 shadow mb-4">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <h5 class="mb-0">
                                <?php echo htmlspecialchars($order_data['firstname'] . ' ' . $order_data['lastname']); ?>
                            </h5>
                            <small>
                                <?php echo htmlspecialchars($order_data['email']); ?>
                            </small>
                            <?php if ($order_data['phone']): ?>
                                <small class="d-block d-md-inline">
                                    <span class="d-none d-md-inline"> | </span><?php echo 'Tel: ' . htmlspecialchars($order_data['phone']); ?>
                                </small>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex gap-2 flex-shrink-0">
                            <a class="btn btn-sm btn-outline-light btn-with-icon" href="../index.php?pin=<?php echo urlencode($project['access_pin']); ?>&action=edit&order_id=<?php echo urlencode($order_id); ?>"><span class="btn-icon">✏️</span><span class="btn-text">Bearbeiten</span></a>
                            <form method="post" onsubmit="return confirm('Diese Bestellung wirklich löschen?');">
                                <input type="hidden" name="delete_order_id" value="<?php echo htmlspecialchars($order_id); ?>">
                                <button type="submit" class="btn btn-sm btn-danger btn-with-icon"><span class="btn-icon">🗑️</span><span class="btn-text">Löschen</span></button>
                            </form>
                        </div>
                    </div>
                    <div class="order-header-meta mt-2">
                        <small class="d-block">Order-ID: <code><?php echo htmlspecialchars($order_id); ?></code></small>
                        <small><?php echo date('d.m.Y H:i', strtotime($order_data['order_date'])); ?></small>
                    </div>
                </div>
                <div class="card-body">
                    <?php foreach ($order_data['persons'] as $person): ?>
                    <div class="mb-3 pb-3 border-bottom">
                        <div class="d-flex justify-content-between align-items-center gap-1">
                            <h6 class="fw-bold mb-0 text-nowrap">
                            👤 <?php echo htmlspecialchars($person['name']); ?>
                            </h6>
                            <div class="d-flex gap-1 align-items-center justify-content-md-end person-actions">
                                <?php if ($person['highchair']): ?>
                                    <span class="badge bg-warning text-dark badge-highchair">🪑<span class="badge-text"> Hochstuhl</span></span>
                                <?php endif; ?>
                                <?php if ($person['type'] === 'child'): ?>
                                    <span class="badge bg-info">Kind (<?php echo $person['age']; ?><span class="d-none d-md-inline"> Jahre</span>)</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Erwachsener</span>
                                <?php endif; ?>
                                <form method="post" onsubmit="return confirm('Diese Person und ihre Auswahl wirklich löschen?');" class="d-inline">
                                    <input type="hidden" name="delete_person_order_id" value="<?php echo htmlspecialchars($order_id); ?>">
                                    <input type="hidden" name="delete_person_index" value="<?php echo (int)$person['person_index']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger btn-with-icon"><span class="btn-icon">🗑️</span><span class="btn-text">Löschen</span></button>
                                </form>
                            </div>
                        </div>
                        <ul class="list-unstyled mb-0 ms-0 ms-md-3">
                            <?php foreach ($person['dishes'] as $dish): ?>
                            <li>
                                <strong><?php echo htmlspecialchars($dish['category']); ?>:</strong>
                                <?php echo htmlspecialchars($dish['dish']); ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php else: ?>
        <div class="alert alert-warning">Bitte wählen Sie ein Projekt aus.</div>
    <?php endif; ?>
</div>

<!-- PDF Export Modal -->
<div class="modal fade" id="pdfModal" tabindex="-1" aria-labelledby="pdfModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pdfModalLabel">📄 PDF Export</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Wie möchten Sie die Bestellungen exportieren?</p>
            </div>
            <div class="modal-footer gap-2">
                <button type="button" class="btn btn-primary" onclick="openPdfModal('view')">
                    <i class="bi bi-printer"></i> Anzeigen & Drucken
                </button>
                <button type="button" class="btn btn-success" onclick="openPdfModal('download')">
                    <i class="bi bi-download"></i> Herunterladen
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openPdfModal(action) {
    // Modal schließen
    const modal = bootstrap.Modal.getInstance(document.getElementById('pdfModal'));
    if (modal) {
        modal.hide();
    }
    
    // Zu reports.php mit view=orders navigieren
    const projectId = <?php echo $project_id; ?>;
    const url = 'reports.php?project=' + projectId + '&view=orders&download=pdf&action=' + action;
    
    if (action === 'view') {
        window.open(url, '_blank');
    } else {
        window.location.href = url;
    }
}
</script>
<?php include '../nav/footer.php'; ?>
</body>
</html>
