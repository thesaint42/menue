<?php
/**
 * admin/reports.php - Reporting & Export
 */

require_once '../db.php';
require_once '../script/auth.php';

checkLogin();

$prefix = $config['database']['prefix'] ?? 'menu_';
$project_id = isset($_GET['project']) ? (int)$_GET['project'] : null;
$no_projects = false;
$project_not_found = false;

// Projekte laden (für Auswahl und Defaults)
$projects = $pdo->query("SELECT * FROM {$prefix}projects WHERE is_active = 1 ORDER BY name")->fetchAll();
if (empty($projects)) {
    $no_projects = true;
}

if (!$project_id && !$no_projects) {
    $project_id = $projects[0]['id'];
}

// Projekt laden
if ($project_id) {
    $stmt = $pdo->prepare("SELECT * FROM {$prefix}projects WHERE id = ?");
    $stmt->execute([$project_id]);
    $project = $stmt->fetch();
    if (!$project) {
        $project_not_found = true;
    }
} else {
    $project = null;
}

// Gäste laden
$guests = [];
if ($project_id && !$project_not_found) {
    $stmt = $pdo->prepare("SELECT * FROM {$prefix}guests WHERE project_id = ? ORDER BY created_at DESC");
    $stmt->execute([$project_id]);
    $guests = $stmt->fetchAll();
}

// Alle Bestellungen laden - neue Logik: strukturiert pro order_id
$orders_by_id = [];
if ($project_id && !$project_not_found) {
    // Lade alle order_sessions mit den entsprechenden Gästen
    $stmt = $pdo->prepare("
        SELECT DISTINCT 
            os.order_id,
            os.email,
            g.id as guest_id,
            g.firstname,
            g.lastname,
            g.phone,
            g.guest_type,
            g.person_type,
            g.child_age,
            g.highchair_needed
        FROM {$prefix}order_sessions os
        LEFT JOIN {$prefix}guests g ON g.email = os.email AND g.project_id = ?
        WHERE os.project_id = ?
        ORDER BY os.order_id, g.id
    ");
    $stmt->execute([$project_id, $project_id]);
    $order_sessions = $stmt->fetchAll();
    
    // Gruppiere nach order_id
    foreach ($order_sessions as $os) {
        $order_id = $os['order_id'];
        
        if (!isset($orders_by_id[$order_id])) {
            $orders_by_id[$order_id] = [
                'email' => $os['email'],
                'firstname' => $os['firstname'],
                'lastname' => $os['lastname'],
                'phone' => $os['phone'],
                'guest_id' => $os['guest_id'],
                'persons' => []
            ];
        }
        
        // Hauptperson (person_id = 0 in orders)
        if ($os['guest_id']) {
            // Lade die Bestellungen der Hauptperson (person_id = 0)
            $stmt_main = $pdo->prepare("
                SELECT DISTINCT mc.name as category, d.name as dish, mc.sort_order
                FROM {$prefix}orders o
                JOIN {$prefix}dishes d ON o.dish_id = d.id
                JOIN {$prefix}menu_categories mc ON d.category_id = mc.id
                WHERE o.order_id = ? AND o.person_id = 0
                ORDER BY mc.sort_order, d.name
            ");
            $stmt_main->execute([$order_id]);
            $main_dishes = $stmt_main->fetchAll();
            
            $main_dishes_by_category = [];
            foreach ($main_dishes as $d) {
                if ($d['dish']) {
                    if (!isset($main_dishes_by_category[$d['category']])) {
                        $main_dishes_by_category[$d['category']] = [];
                    }
                    $main_dishes_by_category[$d['category']][] = $d['dish'];
                }
            }
            
            $main_dishes_text = '';
            foreach ($main_dishes_by_category as $category => $dish_list) {
                if ($main_dishes_text !== '') {
                    $main_dishes_text .= "\n";
                }
                $main_dishes_text .= $category . ': ' . implode(', ', array_unique($dish_list));
            }
            
            // Bestimme person_type mit optional Hochstuhl-Info
            $person_type_value = $os['person_type'] ?? 'adult';
            $person_type = $person_type_value === 'child' ? 'Kind' : 'Erwachsener';
            if ($person_type_value === 'child' && !empty($os['child_age'])) {
                $person_type .= ' (' . $os['child_age'] . 'J';
                if (!empty($os['highchair_needed'])) {
                    $person_type .= ' 🪑';
                }
                $person_type .= ')';
            } elseif (!empty($os['highchair_needed'])) {
                $person_type .= ' 🪑';
            }
            
            $person_key = $os['firstname'] . ' ' . $os['lastname'];
            if (!isset($orders_by_id[$order_id]['persons'][$person_key])) {
                $orders_by_id[$order_id]['persons'][$person_key] = [
                    'name' => $os['firstname'] . ' ' . $os['lastname'],
                    'type' => $person_type,
                    'dishes' => $main_dishes_text ?: '–'
                ];
            }
        }
        
        // Familienmitglieder (person_id > 0)
        if ($os['guest_id']) {
            $stmt_family = $pdo->prepare("SELECT * FROM {$prefix}family_members WHERE guest_id = ? ORDER BY id");
            $stmt_family->execute([$os['guest_id']]);
            $family_members = $stmt_family->fetchAll();
            
            foreach ($family_members as $idx => $member) {
                $person_idx = $idx + 1;
                
                $stmt_member = $pdo->prepare("
                    SELECT DISTINCT mc.name as category, d.name as dish, mc.sort_order
                    FROM {$prefix}orders o
                    JOIN {$prefix}dishes d ON o.dish_id = d.id
                    JOIN {$prefix}menu_categories mc ON d.category_id = mc.id
                    WHERE o.order_id = ? AND o.person_id = ?
                    ORDER BY mc.sort_order, d.name
                ");
                $stmt_member->execute([$order_id, $person_idx]);
                $member_dishes = $stmt_member->fetchAll();
                
                $member_dishes_by_category = [];
                foreach ($member_dishes as $d) {
                    if ($d['dish']) {
                        if (!isset($member_dishes_by_category[$d['category']])) {
                            $member_dishes_by_category[$d['category']] = [];
                        }
                        $member_dishes_by_category[$d['category']][] = $d['dish'];
                    }
                }
                
                $member_dishes_text = '';
                foreach ($member_dishes_by_category as $category => $dish_list) {
                    if ($member_dishes_text !== '') {
                        $member_dishes_text .= "\n";
                    }
                    $member_dishes_text .= $category . ': ' . implode(', ', array_unique($dish_list));
                }
                
                $person_type = $member['member_type'] === 'child' ? 'Kind' : 'Erwachsener';
                if ($member['member_type'] === 'child' && $member['child_age']) {
                    $person_type .= ' (' . $member['child_age'] . 'J';
                    if ($member['highchair_needed']) {
                        $person_type .= ' 🪑';
                    }
                    $person_type .= ')';
                } elseif ($member['highchair_needed']) {
                    $person_type .= ' 🪑';
                }
                
                $person_key = $member['name'];
                if (!isset($orders_by_id[$order_id]['persons'][$person_key])) {
                    $orders_by_id[$order_id]['persons'][$person_key] = [
                        'name' => $member['name'],
                        'type' => $person_type,
                        'dishes' => $member_dishes_text ?: '–'
                    ];
                }
            }
        }
    }
}





// PDF Download oder Anzeige
if (isset($_GET['download']) && $_GET['download'] === 'pdf') {
    if (!$project_id || $project_not_found) {
        http_response_code(400);
        echo 'Projekt nicht gefunden.';
        exit;
    }
    $action = isset($_GET['action']) ? $_GET['action'] : 'download'; // 'download' oder 'view'
    require_once '../script/tcpdf/tcpdf.php';

    // Gäste mit Bestellungen laden
    $stmt = $pdo->prepare("
        SELECT g.id, g.firstname, g.lastname, g.email, g.phone, g.guest_type, g.family_size,
               os.order_id,
               GROUP_CONCAT(DISTINCT CONCAT(mc.name, ': ', d.name) ORDER BY mc.sort_order, d.name SEPARATOR '\n') as dishes
        FROM {$prefix}guests g
        LEFT JOIN {$prefix}order_sessions os ON os.email = g.email AND os.project_id = ?
        LEFT JOIN {$prefix}orders o ON o.order_id = os.order_id
        LEFT JOIN {$prefix}dishes d ON o.dish_id = d.id
        LEFT JOIN {$prefix}menu_categories mc ON d.category_id = mc.id
        WHERE g.project_id = ?
        GROUP BY g.id
        ORDER BY g.created_at DESC
    ");
    $stmt->execute([$project_id, $project_id]);
    $guests_with_orders = $stmt->fetchAll();

    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator('Event Menue Order System (EMOS)');
    $pdf->SetTitle('Gästeübersicht - ' . $project['name']);
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();
    
    // Header
    $pdf->SetFillColor(13, 110, 253);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Gästeübersicht - ' . $project['name'], 0, 1, 'C', true);
    
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
    $pdf->Cell(0, 5, 'Anmeldungen: ' . count($guests_with_orders) . ' / ' . $project['max_guests'], 0, 1);
    $pdf->Ln(5);

    // Tabelle
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(200, 200, 200);
    $pdf->Cell(30, 7, 'Name', 1, 0, 'L', true);
    $pdf->Cell(35, 7, 'Email', 1, 0, 'L', true);
    $pdf->Cell(25, 7, 'Telefon', 1, 0, 'L', true);
    $pdf->Cell(25, 7, 'Typ', 1, 0, 'C', true);
    $pdf->Cell(60, 7, 'Gerichte', 1, 1, 'L', true);

    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetFillColor(245, 245, 245);
    $fill = false;

    foreach ($guests_with_orders as $g) {
        $pdf->SetFillColor($fill ? 245 : 255, $fill ? 245 : 255, $fill ? 245 : 255);
        
        $name = $g['firstname'] . ' ' . $g['lastname'];
        if ($g['order_id']) {
            $name .= "\n(" . $g['order_id'] . ")";
        }
        $email = $g['email'];
        $phone = $g['phone'] ?? '–';
        $type = $g['guest_type'] === 'family' ? 'Familie' : 'Einzeln';
        if ($g['guest_type'] === 'family' && $g['family_size']) {
            $type .= ' (' . $g['family_size'] . ')';
        }
        // Split dishes by newline
        $dishes_text = $g['dishes'] ? $g['dishes'] : '–';
        
        $pdf->MultiCell(30, 7, $name, 1, 'L', $fill);
        $pdf->SetXY(40, $pdf->GetY() - 7);
        $pdf->MultiCell(35, 7, $email, 1, 'L', $fill);
        $pdf->SetXY(75, $pdf->GetY() - 7);
        $pdf->MultiCell(25, 7, $phone, 1, 'L', $fill);
        $pdf->SetXY(100, $pdf->GetY() - 7);
        $pdf->MultiCell(25, 7, $type, 1, 'C', $fill);
        $pdf->SetXY(125, $pdf->GetY() - 7);
        $pdf->MultiCell(60, 7, $dishes_text, 1, 'L', $fill);
        $pdf->Ln();
        $fill = !$fill;
    }

    $filename = 'gaeste_' . $project_id . '_' . date('Ymd_Hi') . '.pdf';
    // 'D' = Download, 'I' = Inline (Anzeige im Browser)
    $pdf->Output($filename, $action === 'view' ? 'I' : 'D');
    exit;
}

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
            <?php if ($no_projects): ?>
                <div class="alert alert-info mb-3">Keine Projekte vorhanden.</div>
                <select class="form-select form-select-lg" disabled>
                    <option selected>Keine Projekte verfügbar</option>
                </select>
            <?php elseif ($project_not_found): ?>
                <div class="alert alert-warning mb-3">Projekt nicht gefunden.</div>
                <select class="form-select form-select-lg" onchange="window.location.href='?project=' + this.value">
                    <?php foreach ($projects as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo $p['id'] == $project_id ? 'selected' : ''; ?>>
                            [ID: <?php echo $p['id']; ?>] <?php echo htmlspecialchars($p['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <select class="form-select form-select-lg" onchange="window.location.href='?project=' + this.value">
                    <?php foreach ($projects as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo $p['id'] == $project_id ? 'selected' : ''; ?>>
                            [ID: <?php echo $p['id']; ?>] <?php echo htmlspecialchars($p['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>
    </div>

    <!-- REPORT OPTIONEN ALS ICONS -->
    <?php if (!$no_projects && !$project_not_found): ?>
    <div class="row g-3 g-md-4">
        <!-- Bestellungen -->
        <div class="col-12 col-sm-6 col-lg-4">
            <a href="?project=<?php echo $project_id; ?>&view=orders" class="report-icon-btn">
                <div class="icon">📋</div>
                <div class="title">Bestellungen</div>
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

        <!-- Drucken / PDF -->
        <div class="col-12 col-sm-6 col-lg-4">
            <a href="#" data-bs-toggle="modal" data-bs-target="#pdfModal" class="report-icon-btn">
                <div class="icon">🖨️</div>
                <div class="title">PDF Report</div>
                <div class="subtitle">Anzeigen oder herunterladen</div>
            </a>
        </div>

        <!-- Gäste -->
        <div class="col-12 col-sm-6 col-lg-4">
            <a href="?project=<?php echo $project_id; ?>&view=guests" class="report-icon-btn">
                <div class="icon">👥</div>
                <div class="title">Gäste</div>
                <div class="subtitle">Alle Gäste anzeigen</div>
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
    </div>
    <?php endif; ?>

    <!-- INHALTSBEREICH -->
    <?php if (isset($_GET['view'])): ?>
        <div class="card border-0 shadow mt-5">
            <?php if ($_GET['view'] === 'orders'): ?>
                <div class="card-header bg-info text-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Bestellungen: <?php echo htmlspecialchars($project['name']); ?></h5>
                        <small class="mb-0">🪑 Hochstuhl erforderlich</small>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($orders_by_id)): ?>
                        <p class="text-center text-muted">Keine Bestellungen vorhanden</p>
                    <?php else: ?>
                        <?php foreach ($orders_by_id as $order_id => $order_data): ?>
                            <div class="mb-4">
                                <!-- Bestellungs-Header -->
                                <div class="bg-secondary text-white p-2 border-bottom border-2 border-info mb-2">
                                    <strong>Bestellung: <?php echo htmlspecialchars($order_id); ?></strong>
                                    <span class="ms-2" style="font-size: 0.9em; opacity: 0.9;">
                                        (<?php echo htmlspecialchars($order_data['firstname'] . ' ' . $order_data['lastname']); ?> | <?php echo htmlspecialchars($order_data['email']); ?><?php if (!empty($order_data['phone'])): ?> | <?php echo htmlspecialchars($order_data['phone']); ?><?php endif; ?>)
                                    </span>
                                    <span class="badge bg-light text-dark ms-2"><?php echo count($order_data['persons']); ?> <?php echo count($order_data['persons']) === 1 ? 'Person' : 'Personen'; ?></span>
                                </div>
                                
                                <!-- Personen-Tabelle für diese Bestellung -->
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0" style="line-height: 0.95;">
                                        <thead class="table-light" style="font-size: 0.9em;">
                                            <tr>
                                                <th style="width: 25%; padding: 0.25rem 0.3rem;">Name</th>
                                                <th style="width: 20%; padding: 0.25rem 0.3rem;">Typ</th>
                                                <th style="width: 55%; padding: 0.25rem 0.3rem;">Bestellungen</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($order_data['persons'] as $person_name => $person): ?>
                                                <tr style="line-height: 0.95;">
                                                    <td style="padding: 0.25rem 0.3rem; vertical-align: top;">
                                                        <small><?php echo htmlspecialchars($person['name']); ?></small>
                                                    </td>
                                                    <td style="padding: 0.25rem 0.3rem; vertical-align: top;">
                                                        <small><?php echo htmlspecialchars($person['type']); ?></small>
                                                    </td>
                                                    <td style="padding: 0.25rem 0.3rem; vertical-align: top;">
                                                        <small style="white-space: pre-wrap; line-height: 1.2;"><?php echo htmlspecialchars($person['dishes']); ?></small>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
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
                    <h5 class="mb-0">Gäste: <?php echo htmlspecialchars($project['name']); ?></h5>
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

<!-- Modal für PDF-Optionen -->
<div class="modal fade" id="pdfModal" tabindex="-1" aria-labelledby="pdfModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pdfModalLabel">📄 PDF Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Wie möchten Sie mit dem PDF Report verfahren?</p>
            </div>
            <div class="modal-footer gap-2">
                <button type="button" class="btn btn-primary" onclick="closePdfModal('view')">
                    <i class="bi bi-printer"></i> Anzeigen & Drucken
                </button>
                <button type="button" class="btn btn-success" onclick="closePdfModal('download')">
                    <i class="bi bi-download"></i> Herunterladen
                </button>
            </div>
        </div>
    </div>
</div>

<?php include '../nav/footer.php'; ?>

<script>
function closePdfModal(action) {
    // Modal schließen
    const modal = bootstrap.Modal.getInstance(document.getElementById('pdfModal'));
    if (modal) {
        modal.hide();
    }
    
    // Navigiere zur PDF
    const url = '?project=<?php echo $project_id; ?>&download=pdf&action=' + action;
    if (action === 'view') {
        window.open(url, '_blank');
    } else {
        window.location.href = url;
    }
}

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
