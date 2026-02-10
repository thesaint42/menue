<?php
/**
 * admin/orders.php - Bestellungsübersicht
 */

require_once '../db.php';
require_once '../script/auth.php';

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
    try {
        // v3.0 Schema: order_sessions + family_members + orders
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
            FROM `{$config['database']['prefix']}order_sessions` os
            JOIN `{$config['database']['prefix']}guests` g ON os.email = g.email AND os.project_id = g.project_id
            LEFT JOIN `{$config['database']['prefix']}orders` o ON os.order_id = o.order_id
            LEFT JOIN `{$config['database']['prefix']}family_members` fm ON g.id = fm.guest_id AND o.person_id = fm.id
            LEFT JOIN `{$config['database']['prefix']}dishes` d ON o.dish_id = d.id
            LEFT JOIN `{$config['database']['prefix']}menu_categories` mc ON o.category_id = mc.id
            WHERE os.project_id = ?
            ORDER BY os.created_at DESC, os.order_id, o.person_id, mc.sort_order";
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
        @media (max-width: 576px) {
            .order-header-meta { text-align: left !important; }
        }
    </style>
</head>
<body>

<?php include '../nav/top_nav.php'; ?>

<div class="container py-4 page-container">
    <div class="row mb-4">
        <div class="col">
            <h1>Bestellungsübersicht</h1>
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
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
                        <div>
                            <h5 class="mb-0">
                                <?php echo htmlspecialchars($order_data['firstname'] . ' ' . $order_data['lastname']); ?>
                            </h5>
                            <small>
                                <?php echo htmlspecialchars($order_data['email']); ?>
                                <?php if ($order_data['phone']): ?>
                                    | Tel: <?php echo htmlspecialchars($order_data['phone']); ?>
                                <?php endif; ?>
                            </small>
                        </div>
                        <div class="order-header-meta text-md-end ms-md-auto">
                            <small class="d-block">Order-ID: <code><?php echo htmlspecialchars($order_id); ?></code></small>
                            <small><?php echo date('d.m.Y H:i', strtotime($order_data['order_date'])); ?></small>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php foreach ($order_data['persons'] as $person): ?>
                    <div class="mb-3 pb-3 border-bottom">
                        <h6 class="fw-bold">
                            👤 <?php echo htmlspecialchars($person['name']); ?>
                            <?php if ($person['type'] === 'child'): ?>
                                <span class="badge bg-info">Kind (<?php echo $person['age']; ?> Jahre)</span>
                                <?php if ($person['highchair']): ?>
                                    <span class="badge bg-warning text-dark">🪑 Hochstuhl</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge bg-secondary">Erwachsener</span>
                            <?php endif; ?>
                        </h6>
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

            <!-- Export-Buttons -->
            <div class="mt-4 d-flex flex-column flex-sm-row gap-2">
                <button class="btn btn-primary" onclick="window.print()">
                    🖨️ Drucken
                </button>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="alert alert-warning">Bitte wählen Sie ein Projekt aus.</div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include '../nav/footer.php'; ?>
</body>
</html>
