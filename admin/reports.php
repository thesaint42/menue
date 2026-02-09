<?php
/**
 * admin/reports.php - Reporting-Übersicht für Service, Küche und Kosten
 */

@session_start();
require_once '../db.php';
require_once '../script/auth.php';
require_once '../script/order_system.php';

checkLogin();
checkAdmin();

$prefix = $config['database']['prefix'] ?? 'menu_';
$message = "";
$messageType = "info";

// Projekt-Auswahl
$project_id = $_GET['project_id'] ?? null;
$report_type = $_GET['report'] ?? 'service';

// Alle aktiven Projekte laden
$stmt = $pdo->query("SELECT * FROM {$prefix}projects WHERE is_active = 1 ORDER BY name");
$projects = $stmt->fetchAll();

$project = null;
$report_data = [];

if ($project_id) {
    $stmt = $pdo->prepare("SELECT * FROM {$prefix}projects WHERE id = ?");
    $stmt->execute([$project_id]);
    $project = $stmt->fetch();
    
    if ($project) {
        switch ($report_type) {
            case 'service':
                $report_data = generate_service_report($pdo, $prefix, $project_id);
                break;
            case 'kitchen':
                $report_data = generate_kitchen_report($pdo, $prefix, $project_id);
                break;
            case 'cost':
                $report_data = generate_cost_report($pdo, $prefix, $project_id);
                break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Event Menue Order System (EMOS)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include '../nav/top_nav.php'; ?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col">
            <h1>📊 Bestellungs-Reports</h1>
            <p class="text-muted">Service-, Küchen- und Kostenübersicht</p>
        </div>
    </div>

    <!-- Projekt- und Report-Auswahl -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <form method="get" action="reports.php">
                        <div class="mb-3">
                            <label class="form-label">Projekt auswählen</label>
                            <select name="project_id" class="form-select" required onchange="this.form.submit()">
                                <option value="">-- Bitte wählen --</option>
                                <?php foreach ($projects as $p): ?>
                                    <option value="<?php echo $p['id']; ?>" <?php echo ($p['id'] == $project_id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($p['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <?php if ($project_id): ?>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="report" id="report_service" value="service" <?php echo ($report_type === 'service') ? 'checked' : ''; ?> onchange="this.form.submit()">
                            <label class="btn btn-outline-primary" for="report_service">🍽️ Service</label>
                            
                            <input type="radio" class="btn-check" name="report" id="report_kitchen" value="kitchen" <?php echo ($report_type === 'kitchen') ? 'checked' : ''; ?> onchange="this.form.submit()">
                            <label class="btn btn-outline-primary" for="report_kitchen">👨‍🍳 Küche</label>
                            
                            <input type="radio" class="btn-check" name="report" id="report_cost" value="cost" <?php echo ($report_type === 'cost') ? 'checked' : ''; ?> onchange="this.form.submit()">
                            <label class="btn btn-outline-primary" for="report_cost">💰 Kosten</label>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if ($project && !empty($report_data)): ?>
    
    <!-- Service Report -->
    <?php if ($report_type === 'service'): ?>
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">🍽️ Service Report - <?php echo htmlspecialchars($project['name']); ?></h5>
            <small class="text-muted">Personen mit ihren Gerichten</small>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th>Gast</th>
                            <th>Person</th>
                            <th>Typ</th>
                            <th>Gang</th>
                            <th>Gericht</th>
                            <?php if ($project['show_prices']): ?>
                            <th class="text-end">Preis</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $last_guest = null;
                        foreach ($report_data as $row): 
                            $guest_name = $row['firstname'] . ' ' . $row['lastname'];
                        ?>
                        <tr>
                            <td><?php echo ($guest_name !== $last_guest) ? htmlspecialchars($guest_name) : ''; ?></td>
                            <td><?php echo htmlspecialchars($row['person_name'] ?? 'Hauptperson'); ?></td>
                            <td><?php echo $row['member_type'] === 'child' ? '👶 Kind' . ($row['child_age'] ? ' (' . $row['child_age'] . ')' : '') : '👤 Erw.'; ?></td>
                            <td><?php echo htmlspecialchars($row['category']); ?></td>
                            <td><?php echo htmlspecialchars($row['dish']); ?></td>
                            <?php if ($project['show_prices']): ?>
                            <td class="text-end"><?php echo $row['price'] ? number_format($row['price'], 2, ',', '.') . ' €' : '-'; ?></td>
                            <?php endif; ?>
                        </tr>
                        <?php 
                        $last_guest = $guest_name;
                        endforeach; 
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Kitchen Report -->
    <?php if ($report_type === 'kitchen'): ?>
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">👨‍🍳 Küchen Report - <?php echo htmlspecialchars($project['name']); ?></h5>
            <small class="text-muted">Anzahl pro Gericht</small>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th>Gang</th>
                            <th>Gericht</th>
                            <th class="text-end">Anzahl</th>
                            <th>Gäste (Info)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['category']); ?></td>
                            <td><strong><?php echo htmlspecialchars($row['dish']); ?></strong></td>
                            <td class="text-end"><span class="badge bg-primary"><?php echo $row['quantity']; ?>x</span></td>
                            <td class="small text-muted"><?php echo htmlspecialchars(substr($row['guests'], 0, 100)); ?><?php echo strlen($row['guests']) > 100 ? '...' : ''; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Cost Report -->
    <?php if ($report_type === 'cost'): ?>
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">💰 Kosten Report - <?php echo htmlspecialchars($project['name']); ?></h5>
            <small class="text-muted">Gesamtkosten pro Bestellung (Brutto)</small>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th>Gast</th>
                            <th>E-Mail</th>
                            <th>Order-ID</th>
                            <th class="text-end">Gerichte</th>
                            <th class="text-end">Gesamt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_sum = 0;
                        foreach ($report_data as $row): 
                            $total_sum += $row['total_cost'];
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['firstname'] . ' ' . $row['lastname']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td class="small font-monospace"><?php echo htmlspecialchars(substr($row['order_id'], 0, 8)); ?>...</td>
                            <td class="text-end"><?php echo $row['dish_count']; ?></td>
                            <td class="text-end"><strong><?php echo number_format($row['total_cost'], 2, ',', '.'); ?> €</strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-primary">
                            <th colspan="4" class="text-end">Gesamtsumme:</th>
                            <th class="text-end"><?php echo number_format($total_sum, 2, ',', '.'); ?> €</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php elseif ($project): ?>
    <div class="alert alert-info">
        <p class="mb-0">ℹ️ Keine Bestellungen für dieses Projekt vorhanden.</p>
    </div>
    <?php endif; ?>
</div>

<?php include '../nav/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
