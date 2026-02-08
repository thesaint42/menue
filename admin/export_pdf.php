<?php
/**
 * admin/export_pdf.php - PDF Export der Bestellungsübersicht
 */

require_once '../db.php';
require_once '../script/auth.php';

// Prüfe, ob TCPDF verfügbar ist
$tcpdf_available = file_exists('../script/tcpdf/tcpdf.php');

checkLogin();

$prefix = $config['database']['prefix'] ?? 'menu_';
$project_id = isset($_GET['project']) ? (int)$_GET['project'] : null;
$message = "";
$messageType = "info";

if (!$project_id) {
    $projects = $pdo->query("SELECT * FROM {$prefix}projects WHERE is_active = 1 ORDER BY name")->fetchAll();
    if (empty($projects)) {
        echo "<div class='container mt-5'><div class='alert alert-warning'>Keine Projekte vorhanden.</div></div>";
        exit;
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

// Gäste und Bestellungen laden
$stmt = $pdo->prepare("SELECT g.*, COUNT(o.id) as order_count FROM {$prefix}guests g 
                       LEFT JOIN {$prefix}orders o ON g.id = o.guest_id 
                       WHERE g.project_id = ? GROUP BY g.id ORDER BY g.created_at DESC");
$stmt->execute([$project_id]);
$guests = $stmt->fetchAll();

// PDF Download
if (isset($_GET['download']) && $tcpdf_available) {
    require_once '../script/tcpdf/tcpdf.php';

    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator('Event Menue Order System (EMOS)');
    $pdf->SetTitle('Bestellungsübersicht - ' . $project['name']);
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();
    
    // Header
    $pdf->SetFillColor(13, 110, 253);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Bestellungsübersicht - ' . $project['name'], 0, 1, 'C', true);
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, 'Erstellt am: ' . date('d.m.Y H:i:s'), 0, 1, 'R');
    $pdf->Ln(5);

    // Projekt Info
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 6, 'Projektdetails:', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, 'Name: ' . $project['name'], 0, 1);
    if ($project['location']) {
        $pdf->Cell(0, 5, 'Ort: ' . $project['location'], 0, 1);
    }
    $pdf->Cell(0, 5, 'Anmeldungen: ' . count($guests) . ' / ' . $project['max_guests'], 0, 1);
    $pdf->Ln(5);

    // Tabelle
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(200, 200, 200);
    $pdf->Cell(40, 7, 'Name', 1, 0, 'L', true);
    $pdf->Cell(40, 7, 'Email', 1, 0, 'L', true);
    $pdf->Cell(30, 7, 'Telefon', 1, 0, 'L', true);
    $pdf->Cell(30, 7, 'Typ', 1, 0, 'C', true);
    $pdf->Cell(25, 7, 'Bestellungen', 1, 1, 'C', true);

    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetFillColor(245, 245, 245);
    $fill = false;

    foreach ($guests as $g) {
        $pdf->SetFillColor($fill ? 245 : 255, $fill ? 245 : 255, $fill ? 245 : 255);
        $pdf->MultiCell(40, 7, $g['firstname'] . ' ' . $g['lastname'], 1, 'L', $fill);
        $pdf->SetXY(50, $pdf->GetY() - 7);
        $pdf->MultiCell(40, 7, $g['email'], 1, 'L', $fill);
        $pdf->SetXY(90, $pdf->GetY() - 7);
        $pdf->MultiCell(30, 7, $g['phone'] ?? '–', 1, 'C', $fill);
        $pdf->SetXY(120, $pdf->GetY() - 7);
        $guest_type = $g['guest_type'] === 'family' ? 'Fam.' : 'Einz.';
        if ($g['guest_type'] === 'family') {
            $guest_type .= '(' . $g['family_size'] . ')';
        }
        $pdf->MultiCell(30, 7, $guest_type, 1, 'C', $fill);
        $pdf->SetXY(150, $pdf->GetY() - 7);
        $pdf->MultiCell(25, 7, $g['order_count'], 1, 'C', $fill);
        $pdf->Ln();
        $fill = !$fill;
    }

    $filename = 'bestellungen_' . $project_id . '_' . date('Ymd_Hi') . '.pdf';
    $pdf->Output($filename, 'D');
    exit;
}

// Projekte für Dropdown
$projects = $pdo->query("SELECT * FROM {$prefix}projects WHERE is_active = 1 ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>PDF Export - Menüwahl</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include '../nav/top_nav.php'; ?>

<div class="container py-4">
    <h2 class="mb-4">📄 PDF Export</h2>

    <!-- PROJEKT FILTER -->
    <div class="mb-4">
        <label class="form-label">Projekt auswählen:</label>
        <div class="btn-group w-100" role="group">
            <?php foreach ($projects as $p): ?>
                <a href="?project=<?php echo $p['id']; ?>" class="btn btn-outline-secondary <?php echo $p['id'] == $project_id ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($p['name']); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="row g-4">
        <!-- PREVIEW -->
        <div class="col-lg-8">
            <div class="card border-0 shadow">
                <div class="card-header bg-info text-white py-3">
                    <h5 class="mb-0">Vorschau: <?php echo htmlspecialchars($project['name']); ?></h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Tel.</th>
                                <th>Typ</th>
                                <th>Bestellungen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($guests)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-3">Keine Gäste</td></tr>
                            <?php else: ?>
                                <?php foreach ($guests as $g): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($g['firstname'] . ' ' . $g['lastname']); ?></td>
                                        <td><?php echo htmlspecialchars($g['email']); ?></td>
                                        <td><?php echo htmlspecialchars($g['phone'] ?? '–'); ?></td>
                                        <td>
                                            <?php 
                                                echo $g['guest_type'] === 'family' ? 'Familie' : 'Einzelperson';
                                                if ($g['guest_type'] === 'family') {
                                                    echo ' (' . $g['family_size'] . ' Pers.)';
                                                }
                                            ?>
                                        </td>
                                        <td><?php echo $g['order_count']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- EXPORT OPTIONEN -->
        <div class="col-lg-4">
            <div class="card border-0 shadow">
                <div class="card-header bg-success text-white py-3">
                    <h5 class="mb-0">Export-Optionen</h5>
                </div>
                <div class="card-body">
                    <?php if ($tcpdf_available): ?>
                        <p class="text-muted small mb-3">Wählen Sie ein Export-Format:</p>
                        
                        <a href="?project=<?php echo $project_id; ?>&download=1" class="btn btn-outline-danger w-100 mb-3">
                            📄 Als PDF herunterladen
                        </a>
                        
                        <button onclick="window.print()" class="btn btn-outline-secondary w-100 mb-3">
                            🖨️ Drucken / Seite speichern
                        </button>

                        <hr>

                        <h6>CSV Export</h6>
                        <button onclick="exportCSV()" class="btn btn-outline-info w-100">
                            📊 Als CSV exportieren
                        </button>

                        <hr class="my-4">

                        <div class="alert alert-info small">
                            <strong>💡 Hinweis:</strong> PDF beinhaltet:
                            <ul class="mb-0 mt-2">
                                <li>Gästeübersicht</li>
                                <li>Kontaktinfos</li>
                                <li>Gäste-Statistik</li>
                            </ul>
                        </div>

                    <?php else: ?>
                        <div class="alert alert-warning">
                            <strong>⚠️ TCPDF nicht verfügbar</strong><br>
                            PDF-Export ist deaktiviert. Bitte installieren Sie TCPDF im Verzeichnis script/tcpdf/
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

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
