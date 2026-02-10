<?php
/**
 * admin/reports.php - Reporting & Export
 */

require_once '../db.php';
require_once '../script/auth.php';

checkLogin();

$prefix = $config['database']['prefix'] ?? 'menu_';
$project_id = isset($_GET['project']) ? (int)$_GET['project'] : null;

if (!$project_id) {
    $projects = $pdo->query("SELECT * FROM {$prefix}projects WHERE is_active = 1 ORDER BY name")->fetchAll();
    if (empty($projects)) {
        die("Keine Projekte vorhanden.");
    }
    $project_id = $projects[0]['id'];
}

// Projekt laden
$stmt = $pdo->prepare("SELECT * FROM {$prefix}projects WHERE id = ?");
$stmt->execute([$project_id]);
$project = $stmt->fetch();

if (!$project) {
    die("Projekt nicht gefunden.");
}

// Gäste laden
$stmt = $pdo->prepare("SELECT * FROM {$prefix}guests WHERE project_id = ? ORDER BY created_at DESC");
$stmt->execute([$project_id]);
$guests = $stmt->fetchAll();

// Projekte für Dropdown
$projects = $pdo->query("SELECT * FROM {$prefix}projects WHERE is_active = 1 ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporting - EMOS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .report-icon-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 150px;
            text-decoration: none;
            border-radius: 12px;
            padding: 20px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .report-icon-btn:hover {
            background: rgba(255,255,255,0.1);
            border-color: rgba(255,255,255,0.2);
            transform: translateY(-5px);
            text-decoration: none;
            color: inherit;
        }
        
        .report-icon-btn .icon {
            font-size: 3rem;
            margin-bottom: 10px;
        }
        
        .report-icon-btn .title {
            font-weight: 600;
            font-size: 1.1rem;
            text-align: center;
            margin-bottom: 5px;
        }
        
        .report-icon-btn .subtitle {
            font-size: 0.85rem;
            color: #aaa;
            text-align: center;
        }
        
        @media (max-width: 576px) {
            .report-icon-btn {
                min-height: 120px;
            }
            
            .report-icon-btn .icon {
                font-size: 2.5rem;
            }
            
            .report-icon-btn .title {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>

<?php include '../nav/top_nav.php'; ?>

<div class="container py-4" style="max-width: 1000px;">
    <div class="row mb-4">
        <div class="col">
            <h1 class="mb-2">📊 Reporting</h1>
            <p class="text-muted">Wählen Sie ein Projekt und einen Report-Typ</p>
        </div>
    </div>

    <!-- PROJEKT FILTER -->
    <div class="card border-0 shadow mb-4">
        <div class="card-body">
            <label class="form-label fw-bold mb-3">Projekt auswählen:</label>
            <select class="form-select form-select-lg" onchange="window.location.href='?project=' + this.value">
                <?php foreach ($projects as $p): ?>
                    <option value="<?php echo $p['id']; ?>" <?php echo $p['id'] == $project_id ? 'selected' : ''; ?>>
                        [ID: <?php echo $p['id']; ?>] <?php echo htmlspecialchars($p['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- REPORT OPTIONEN ALS ICONS -->
    <div class="row g-3 g-md-4">
        <!-- Bestellungsübersicht -->
        <div class="col-12 col-sm-6 col-lg-4">
            <a href="?project=<?php echo $project_id; ?>&view=orders" class="report-icon-btn">
                <div class="icon">📋</div>
                <div class="title">Bestellungsübersicht</div>
                <div class="subtitle">Alle Bestellungen anzeigen</div>
            </a>
        </div>

        <!-- CSV Export -->
        <div class="col-12 col-sm-6 col-lg-4">
            <a onclick="exportCSV()" class="report-icon-btn" style="cursor: pointer;">
                <div class="icon">📊</div>
                <div class="title">CSV Export</div>
                <div class="subtitle">Als CSV herunterladen</div>
            </a>
        </div>

        <!-- Drucken -->
        <div class="col-12 col-sm-6 col-lg-4">
            <a onclick="window.print()" class="report-icon-btn" style="cursor: pointer;">
                <div class="icon">🖨️</div>
                <div class="title">Drucken</div>
                <div class="subtitle">Seite drucken / speichern</div>
            </a>
        </div>

        <!-- Statistiken -->
        <div class="col-12 col-sm-6 col-lg-4">
            <a href="?project=<?php echo $project_id; ?>&view=stats" class="report-icon-btn">
                <div class="icon">📈</div>
                <div class="title">Statistiken</div>
                <div class="subtitle">Auswertungen & Analysen</div>
            </a>
        </div>

        <!-- Gästeübersicht -->
        <div class="col-12 col-sm-6 col-lg-4">
            <a href="?project=<?php echo $project_id; ?>&view=guests" class="report-icon-btn">
                <div class="icon">👥</div>
                <div class="title">Gästeübersicht</div>
                <div class="subtitle">Alle Gäste anzeigen</div>
            </a>
        </div>
    </div>

    <!-- INHALTSBEREICH -->
    <?php if (isset($_GET['view'])): ?>
        <div class="card border-0 shadow mt-5">
            <?php if ($_GET['view'] === 'orders'): ?>
                <div class="card-header bg-info text-white py-3">
                    <h5 class="mb-0">Bestellungsübersicht: <?php echo htmlspecialchars($project['name']); ?></h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th class="d-none d-md-table-cell">Tel.</th>
                                    <th>Typ</th>
                                    <th class="d-none d-lg-table-cell">Bestellungen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($guests)): ?>
                                    <tr><td colspan="5" class="text-center text-muted py-3">Keine Gäste</td></tr>
                                <?php else: ?>
                                    <?php foreach ($guests as $g): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($g['firstname'] . ' ' . $g['lastname']); ?></td>
                                            <td><small><?php echo htmlspecialchars($g['email']); ?></small></td>
                                            <td class="d-none d-md-table-cell"><small><?php echo htmlspecialchars($g['phone'] ?? '–'); ?></small></td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo $g['guest_type'] === 'family' ? 'Familie' : 'Einzeln'; ?>
                                                    <?php if ($g['guest_type'] === 'family' && $g['family_size']): ?>(<?php echo $g['family_size']; ?>)<?php endif; ?>
                                                </span>
                                            </td>
                                            <td class="d-none d-lg-table-cell">–</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($_GET['view'] === 'stats'): ?>
                <div class="card-header bg-success text-white py-3">
                    <h5 class="mb-0">Statistiken: <?php echo htmlspecialchars($project['name']); ?></h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-sm-6 col-lg-4">
                            <div class="bg-light p-3 rounded">
                                <div class="text-muted small">Gesamt Gäste</div>
                                <div class="fs-3 fw-bold"><?php echo count($guests); ?> / <?php echo $project['max_guests']; ?></div>
                                <div class="progress mt-2" style="height: 8px;">
                                    <div class="progress-bar" style="width: <?php echo ($project['max_guests'] > 0) ? (count($guests) / $project['max_guests']) * 100 : 0; ?>%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-4">
                            <div class="bg-light p-3 rounded">
                                <div class="text-muted small">Einzelpersonen</div>
                                <div class="fs-3 fw-bold"><?php echo count(array_filter($guests, fn($g) => $g['guest_type'] === 'individual')); ?></div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-4">
                            <div class="bg-light p-3 rounded">
                                <div class="text-muted small">Familien</div>
                                <div class="fs-3 fw-bold"><?php echo count(array_filter($guests, fn($g) => $g['guest_type'] === 'family')); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($_GET['view'] === 'guests'): ?>
                <div class="card-header bg-warning text-dark py-3">
                    <h5 class="mb-0">Gästeübersicht: <?php echo htmlspecialchars($project['name']); ?></h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php if (empty($guests)): ?>
                            <div class="col-12"><p class="text-muted text-center py-5">Keine Gäste vorhanden</p></div>
                        <?php else: ?>
                            <?php foreach ($guests as $g): ?>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="card border-0 bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo htmlspecialchars($g['firstname'] . ' ' . $g['lastname']); ?></h6>
                                        <p class="card-text small text-muted mb-2">
                                            📧 <a href="mailto:<?php echo htmlspecialchars($g['email']); ?>"><?php echo htmlspecialchars($g['email']); ?></a>
                                        </p>
                                        <?php if ($g['phone']): ?>
                                            <p class="card-text small text-muted mb-2">
                                                📞 <?php echo htmlspecialchars($g['phone']); ?>
                                            </p>
                                        <?php endif; ?>
                                        <p class="card-text small mb-0">
                                            <span class="badge bg-secondary"><?php echo $g['guest_type'] === 'family' ? 'Familie' : 'Einzeln'; ?></span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<?php include '../nav/footer.php'; ?>

<script>
function exportCSV() {
    let csv = 'Name,Email,Telefon,Typ,Bestellungen\n';
    const rows = document.querySelectorAll('table tbody tr');
    
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length > 0) {
            const data = Array.from(cells).map(cell => {
                let text = cell.textContent.trim();
                // CSV-Escaping
                if (text.includes(',') || text.includes('"') || text.includes('\n')) {
                    text = '"' + text.replace(/"/g, '""') + '"';
                }
                return text;
            });
            csv += data.join(',') + '\n';
        }
    });

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', 'bestellungen_<?php echo $project_id; ?>_<?php echo date('Ymd_Hi'); ?>.csv');
    link.click();
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
