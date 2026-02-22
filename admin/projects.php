<?php
/**
 * admin/projects.php - Projektverwaltung
 */

require_once '../db.php';
require_once '../script/auth.php';
// Lade phone helper robust (vermeide Fatal wenn Datei auf dem Server fehlt)
$phone_helper = __DIR__ . '/../script/phone.php';
if (file_exists($phone_helper)) {
    require_once $phone_helper;
} else {
    // Fallback-Implementierung (naiv), damit Seite nicht abstürzt
    function normalize_phone_e164($rawNumber, $defaultCountry = 'DE') {
        $s = trim((string)$rawNumber);
        if ($s === '') return '';
            // Keep only digits and plus
            $clean = preg_replace('/[^\d\+]/', '', $s);
            if (strpos($clean, '00') === 0) { $clean = '+' . substr($clean, 2); }
            if (preg_match('/^\+\d{7,15}$/', $clean)) return $clean;
            if (strpos($clean, '+') === 0) return false;
            $digits = preg_replace('/^0+/', '', $clean);
            if ($digits && strlen($digits) >= 7 && strlen($digits) <= 15) return '+' . '49' . $digits;
            return false;
    }
    function is_valid_e164($number) {
        return is_string($number) && preg_match('/^\+\d{7,15}$/', $number);
    }
}

// Temporäre Debug-Hilfe: Fehler sichtbar machen und Exceptions protokollieren
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});
set_exception_handler(function($e) {
    error_log("Unhandled Exception in admin/projects.php: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    http_response_code(500);
    echo "<h1>Fehler im Script</h1>";
    echo "<pre>" . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString()) . "</pre>";
    exit;
});

checkLogin();

// Feature-basierte Zugriffskontrolle
requireMenuAccess($pdo, ['projects_read', 'projects_write'], 'read', $config['database']['prefix'] ?? 'menu_');

$prefix = $config['database']['prefix'] ?? 'menu_';
if (empty($prefix)) {
    $prefix = 'menu_';
}
$message = "";
$messageType = "info";

function sanitize_project_description($html) {
    $allowed = '<p><br><strong><b><em><i><u><ul><ol><li><a><span>';
    $clean = strip_tags((string)$html, $allowed);
    // Remove inline event handlers and javascript: URLs
    $clean = preg_replace('/\son\w+\s*=\s*"[^"]*"/i', '', $clean);
    $clean = preg_replace("/\son\w+\s*=\s*'[^']*'/i", '', $clean);
    $clean = preg_replace('/javascript:/i', '', $clean);
    return trim($clean);
}

// Projekt erstellen
if (isset($_POST['create_project'])) {
    // Berechtigungsprüfung
    if (!hasMenuAccess($pdo, 'projects_write', $prefix)) {
        $message = "⚠️ Keine Berechtigung zum Erstellen von Projekten.";
        $messageType = "danger";
    } else {
        $name = trim($_POST['name']);
        $description = sanitize_project_description($_POST['description'] ?? '');
        $location = trim($_POST['location']);
    $contact_person = trim($_POST['contact_person']);
    $contact_phone_raw = trim($_POST['contact_phone']);
    $contact_phone = normalize_phone_e164($contact_phone_raw, 'DE');
    $contact_email = trim($_POST['contact_email']);
    $max_guests = (int)$_POST['max_guests'];
    $admin_email = trim($_POST['admin_email']);
    $show_prices = isset($_POST['show_prices']) ? 1 : 0;

    if (empty($name) || empty($admin_email) || $max_guests < 1) {
        $message = "Bitte füllen Sie alle erforderlichen Felder aus.";
        $messageType = "danger";
    } elseif (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
        $message = "Ungültige Admin E-Mail.";
        $messageType = "danger";
    } elseif ($contact_phone_raw !== '' && $contact_phone === false) {
        $message = "Ungültige Telefonnummer. Bitte im Format mit Ländervorwahl eingeben.";
        $messageType = "danger";
    } else {
        try {
            // Generiere eindeutige PIN
            $pin = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            while ($pdo->query("SELECT id FROM {$prefix}projects WHERE access_pin = '$pin'")->rowCount() > 0) {
                $pin = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            }
            
            $stmt = $pdo->prepare("INSERT INTO {$prefix}projects 
                (name, description, location, contact_person, contact_phone, contact_email, max_guests, admin_email, access_pin, created_by, show_prices) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $name, $description, $location, $contact_person, $contact_phone, $contact_email, $max_guests, $admin_email, $pin, $_SESSION['user_id'], $show_prices
            ]);
            
            $project_id = $pdo->lastInsertId();

            // Wenn der aktuelle User die Rolle "Projektverwaltung" hat, 
            // weise das neue Projekt automatisch zu
            try {
                $user_stmt = $pdo->prepare("SELECT r.id FROM {$prefix}users u 
                    JOIN {$prefix}roles r ON u.role_id = r.id 
                    WHERE u.id = ? AND LOWER(r.name) = 'projektverwaltung'");
                $user_stmt->execute([$_SESSION['user_id']]);
                
                if ($user_stmt->rowCount() > 0) {
                    // User hat Projektverwaltung Rolle - weisen Sie das Projekt zu
                    $insert_assignment = $pdo->prepare("INSERT IGNORE INTO {$prefix}user_projects 
                        (user_id, project_id) VALUES (?, ?)");
                    $insert_assignment->execute([$_SESSION['user_id'], $project_id]);
                }
            } catch (Exception $e) {
                // Fehler beim Zuweisen ignorieren - Projekt wurde trotzdem erstellt
                error_log("Fehler beim automatischen Zuweisen des Projekts: " . $e->getMessage());
            }

            $message = "✓ Projekt erfolgreich erstellt! PIN: <strong>$pin</strong>";
            $messageType = "success";
            logAction($pdo, $prefix, 'project_created', "Projekt: $name, PIN: $pin");

        } catch (Exception $e) {
            $message = "Fehler: " . $e->getMessage();
            $messageType = "danger";
        }
    }
    }
}

// Projekt aktualisieren
if (isset($_POST['update_project'])) {
    // Berechtigungsprüfung
    if (!hasMenuAccess($pdo, 'projects_write', $prefix)) {
        $message = "⚠️ Keine Berechtigung zum Bearbeiten von Projekten.";
        $messageType = "danger";
    } else {
        $id = (int)$_POST['project_id'];
        $name = trim($_POST['name']);
        $description = sanitize_project_description($_POST['description'] ?? '');
        $location = trim($_POST['location']);
    $contact_person = trim($_POST['contact_person']);
    $contact_phone_raw = trim($_POST['contact_phone']);
    $contact_phone = normalize_phone_e164($contact_phone_raw, 'DE');
    $contact_email = trim($_POST['contact_email']);
    $max_guests = (int)$_POST['max_guests'];
    $admin_email = trim($_POST['admin_email']);
    $show_prices = isset($_POST['show_prices']) ? 1 : 0;

    if (empty($name) || empty($admin_email) || $max_guests < 1) {
        $message = "Bitte füllen Sie alle erforderlichen Felder aus.";
        $messageType = "danger";
    } elseif (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
        $message = "Ungültige Admin E-Mail.";
        $messageType = "danger";
    } elseif ($contact_phone_raw !== '' && $contact_phone === false) {
        $message = "Ungültige Telefonnummer. Bitte im Format mit Ländervorwahl eingeben.";
        $messageType = "danger";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE {$prefix}projects SET 
                name = ?, description = ?, location = ?, contact_person = ?, 
                contact_phone = ?, contact_email = ?, max_guests = ?, admin_email = ?, show_prices = ?
                WHERE id = ?");
            
            $stmt->execute([
                $name, $description, $location, $contact_person, $contact_phone, $contact_email, $max_guests, $admin_email, $show_prices, $id
            ]);

            $message = "✓ Projekt erfolgreich aktualisiert!";
            $messageType = "success";
            logAction($pdo, $prefix, 'project_updated', "Projekt ID: $id");

        } catch (Exception $e) {
            $message = "Fehler: " . $e->getMessage();
            $messageType = "danger";
        }
    }
    }
}

// Projekt deaktivieren
if (isset($_GET['deactivate'])) {
    if (!hasMenuAccess($pdo, 'projects_write', $prefix)) {
        $message = "⚠️ Keine Berechtigung zum Deaktivieren von Projekten.";
        $messageType = "danger";
    } else {
        $id = (int)$_GET['deactivate'];
        $stmt = $pdo->prepare("UPDATE {$prefix}projects SET is_active = 0 WHERE id = ?");
        $stmt->execute([$id]);
        $message = "Projekt deaktiviert.";
        $messageType = "success";
        logAction($pdo, $prefix, 'project_deactivated', "Projekt ID: $id");
    }
}

// Projekt aktivieren
if (isset($_GET['activate'])) {
    if (!hasMenuAccess($pdo, 'projects_write', $prefix)) {
        $message = "⚠️ Keine Berechtigung zum Aktivieren von Projekten.";
        $messageType = "danger";
    } else {
        $id = (int)$_GET['activate'];
        $stmt = $pdo->prepare("UPDATE {$prefix}projects SET is_active = 1 WHERE id = ?");
        $stmt->execute([$id]);
        $message = "Projekt aktiviert.";
        $messageType = "success";
        logAction($pdo, $prefix, 'project_activated', "Projekt ID: $id");
    }
}

// Projekt löschen (nur wenn inaktiv)
if (isset($_POST['delete_project'])) {
    if (!hasMenuAccess($pdo, 'projects_write', $prefix)) {
        $message = "⚠️ Keine Berechtigung zum Löschen von Projekten.";
        $messageType = "danger";
    } else {
        $id = (int)$_POST['project_id'];

    // Prüfe aktuellen Status
    $stmt = $pdo->prepare("SELECT is_active, name FROM {$prefix}projects WHERE id = ?");
    $stmt->execute([$id]);
    $proj = $stmt->fetch();
    if (!$proj) {
        $message = "Projekt nicht gefunden.";
        $messageType = "danger";
    } elseif ($proj['is_active']) {
        $message = "Ein aktives Projekt kann nicht gelöscht werden. Bitte zuerst deaktivieren.";
        $messageType = "danger";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Finde alle order_sessions für dieses Projekt
            $stmt = $pdo->prepare("SELECT order_id FROM {$prefix}order_sessions WHERE project_id = ?");
            $stmt->execute([$id]);
            $orderIds = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
            
            if (!empty($orderIds)) {
                // Lösche Bestellungen basierend auf order_id
                $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
                $delOrders = $pdo->prepare("DELETE FROM {$prefix}orders WHERE order_id IN ($placeholders)");
                $delOrders->execute($orderIds);
            }

            // Lösche Gäste des Projekts
            $delGuests = $pdo->prepare("DELETE FROM {$prefix}guests WHERE project_id = ?");
            $delGuests->execute([$id]);

            // Lösche Gerichte / Menüs, die diesem Projekt gehören
            $delDishes = $pdo->prepare("DELETE FROM {$prefix}dishes WHERE project_id = ?");
            $delDishes->execute([$id]);
            
            // Lösche order_sessions des Projekts
            $delSessions = $pdo->prepare("DELETE FROM {$prefix}order_sessions WHERE project_id = ?");
            $delSessions->execute([$id]);

            // Projekt selbst löschen
            $delProj = $pdo->prepare("DELETE FROM {$prefix}projects WHERE id = ?");
            $delProj->execute([$id]);

            $pdo->commit();

            $message = "Projekt und alle zugehörigen Datensätze wurden gelöscht.";
            $messageType = "success";
            logAction($pdo, $prefix, 'project_deleted', "Projekt: {$proj['name']} (ID: $id)");
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Fehler beim Löschen: " . $e->getMessage();
            $messageType = "danger";
        }
    }
    }
}

// E-Mail Einladung versenden
if (isset($_POST['send_invite'])) {
    $project_id = (int)$_POST['project_id'];
    $recipient_emails = trim($_POST['recipient_emails']);
    $custom_message = trim($_POST['custom_message'] ?? '');
    
    // Projekt laden
    $stmt = $pdo->prepare("SELECT * FROM {$prefix}projects WHERE id = ?");
    $stmt->execute([$project_id]);
    $project = $stmt->fetch();
    
    if ($project) {
        require_once '../script/mailer.php';
        
        // E-Mails splitten
        $emails = array_filter(array_map('trim', explode(',', $recipient_emails)));
        
        $base_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
        $access_url = $base_url . dirname($_SERVER['PHP_SELF'], 2) . '/index.php?pin=' . urlencode($project['access_pin']);
        $qr_code_url = $_SERVER['PHP_SELF'] . '?project=' . $project_id . '&qr=1';
        
        $sent_count = 0;
        $failed_count = 0;
        
        foreach ($emails as $recipient) {
            if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                $failed_count++;
                continue;
            }
            
            try {
                $mail = new PHPMailer\PHPMailer\PHPMailer();
                // Verwende existierende SMTP-Konfiguration aus Datenbank (nicht config.yaml)
                
                // Lade SMTP-Einstellungen aus Datenbank
                $stmt = $pdo->query("SELECT * FROM {$prefix}smtp_config WHERE id = 1");
                $smtp = $stmt->fetch();
                
                if ($smtp) {
                    $mail->isSMTP();
                    $mail->Host = $smtp['smtp_host'];
                    $mail->Port = $smtp['smtp_port'];
                    $mail->SMTPAuth = true;
                    $mail->Username = $smtp['smtp_user'];
                    $mail->Password = $smtp['smtp_pass'];
                    $mail->SMTPSecure = $smtp['smtp_secure'];
                    $mail->setFrom($smtp['sender_email'], $smtp['sender_name']);
                    
                    $mail->addAddress($recipient);
                    $mail->isHTML(true);
                    $mail->Subject = "Einladung: " . $project['name'];
                    
                    // HTML Body
                    $mail->Body = "
                            <h2>Event Menue Order System (EMOS) - Einladung</h2>
                        <p>Sie sind zu <strong>{$project['name']}</strong> eingeladen!</p>
                        
                        <div style='border: 2px solid #007bff; padding: 20px; margin: 20px 0;'>
                            <p style='font-size: 18px; margin-bottom: 10px;'><strong>Zugangs-PIN:</strong></p>
                            <p style='font-size: 32px; font-weight: bold; font-family: monospace; letter-spacing: 5px; color: #007bff;'>
                                {$project['access_pin']}
                            </p>
                        </div>
                        
                        <p><strong>Oder nutzen Sie diesen Link:</strong></p>
                        <p><a href='{$access_url}' style='background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Zur Event Menue Order System (EMOS)</a></p>
                        
                        " . (!empty($custom_message) ? "<p><strong>Nachricht:</strong></p><p>" . htmlspecialchars($custom_message) . "</p>" : "") . "
                        
                        <hr>
                        <p style='font-size: 12px; color: #666;'>
                            " . ($project['location'] ? "Ort: {$project['location']}<br>" : "") . "
                            " . ($project['contact_person'] ? "Ansprechpartner: {$project['contact_person']}<br>" : "") . "
                        </p>
                    ";
                    
                    if ($mail->send()) {
                        $sent_count++;
                    } else {
                        $failed_count++;
                    }
                }
            } catch (Exception $e) {
                $failed_count++;
            }
        }
        
        $message = "✓ E-Mails versandt: $sent_count erfolgreich";
        if ($failed_count > 0) {
            $message .= ", $failed_count fehlgeschlagen";
        }
        $messageType = $failed_count > 0 ? "warning" : "success";
        logAction($pdo, $prefix, 'invite_sent', "Projekt: {$project['name']}, Empfänger: $sent_count");
    }
}

// Alle Projekte laden - basierend auf Zugriffsrechten
$user_role_id = $_SESSION['role_id'] ?? null;
$projects = [];

// Prüfe ob User Projekte lesen darf
$can_read_projects = hasMenuAccess($pdo, 'projects_read', $prefix);
$can_write_projects = hasMenuAccess($pdo, 'projects_write', $prefix);

if (!$can_read_projects) {
    // Keine Leseberechtigung - keine Projekte anzeigen
    $projects = [];
} else if ($user_role_id === 1) {
    // Systemadmin: alle Projekte
    $projects = $pdo->query("SELECT * FROM {$prefix}projects ORDER BY created_at DESC")->fetchAll();
} else if ($user_role_id === 2 || $user_role_id === 3) {
    // Projektadmin oder Reporter: nur zugewiesene Projekte
    $assigned = getUserProjects($pdo, $prefix);
    if (!empty($assigned)) {
        $project_ids = array_column($assigned, 'id');
        $placeholders = implode(',', array_fill(0, count($project_ids), '?'));
        $stmt = $pdo->prepare("SELECT * FROM {$prefix}projects WHERE id IN ($placeholders) ORDER BY created_at DESC");
        $stmt->execute($project_ids);
        $projects = $stmt->fetchAll();
    }
} else {
    // Andere Rollen mit projects_read: alle Projekte (falls sie die Berechtigung haben)
    $projects = $pdo->query("SELECT * FROM {$prefix}projects ORDER BY created_at DESC")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projektenverwaltung - Event Menue Order System (EMOS)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@24.5.0/build/css/intlTelInput.min.css">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .quill-editor { min-height: 140px; margin-bottom: 1rem; }
        .ql-toolbar.ql-snow, .ql-container.ql-snow { border-color: #495057; }
        .ql-container.ql-snow { background: #1f1f1f; color: #f8f9fa; height: auto; }
        .ql-editor { color: #f8f9fa; min-height: 120px; }
        .ql-snow .ql-picker { color: #f8f9fa; }
        .ql-snow .ql-stroke { stroke: #f8f9fa; }
        .ql-snow .ql-fill { fill: #f8f9fa; }
        .ql-snow .ql-picker-options { background: #2b2b2b; }
        
        /* Project action buttons - uniform size */
        .project-btn {
            display: inline-block;
            flex: 0 0 auto;
            width: 120px;
            white-space: nowrap;
            text-align: center;
        }
        
        .project-btn .btn-icon { margin-right: 0.35rem; }
        
        /* Limit actions column width to fit max 3 buttons per row */
        .projects-table tbody td:last-child,
        .projects-table thead th:last-child {
            max-width: 380px;
            width: 380px;
        }
        
        .projects-table tbody td:last-child > div {
            max-width: 380px;
        }
        
        /* PIN Display - responsive font size to fit in one line */
        .pin-display {
            letter-spacing: 0.4em;
            font-family: monospace;
            font-size: clamp(1.5rem, 8vw, 3.5rem);
            word-break: keep-all;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Mobile: Symbol only for action buttons */
        @media (max-width: 576px) {
            .project-btn {
                width: auto;
                min-width: auto;
                flex: 0 0 auto;
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }
            .project-btn .btn-text { display: none; }
            .project-btn .btn-icon { margin-right: 0; }
        }
    </style>
</head>
<body>

<?php include '../nav/top_nav.php'; ?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Projekte verwalten</h2>
        <?php if ($can_write_projects): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProjectModal">+ Neues Projekt</button>
        <?php endif; ?>
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
            <table class="table table-hover mb-0 projects-table">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Ort</th>
                        <th>Gäste</th>
                        <th>Max</th>
                        <th>PIN</th>
                        <th>Admin Email</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $p): ?>
                        <?php 
                            // Zähle tatsächliche Personen aus order_people oder orders
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
                            $stmt->execute(["{$prefix}order_people"]);
                            $has_order_people = $stmt->fetchColumn() > 0;
                            
                            if ($has_order_people) {
                                // Zähle Personen aus order_people via order_sessions
                                $stmt = $pdo->prepare("
                                    SELECT COUNT(DISTINCT CONCAT(op.order_id, '-', op.person_index)) as count
                                    FROM {$prefix}order_people op
                                    INNER JOIN {$prefix}order_sessions os ON op.order_id = os.order_id
                                    WHERE os.project_id = ?
                                ");
                                $stmt->execute([$p['id']]);
                            } else {
                                // Fallback: Zähle distinct person_id aus orders
                                $stmt = $pdo->prepare("
                                    SELECT COUNT(DISTINCT CONCAT(o.order_id, '-', o.person_id)) as count
                                    FROM {$prefix}orders o
                                    INNER JOIN {$prefix}order_sessions os ON o.order_id = os.order_id
                                    WHERE os.project_id = ?
                                ");
                                $stmt->execute([$p['id']]);
                            }
                            $guest_count = $stmt->fetch()['count'];
                        ?>
                        <tr>
                            <td><?php echo $p['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($p['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($p['location'] ?? '–'); ?></td>
                            <td><?php echo $guest_count; ?></td>
                            <td><?php echo $p['max_guests']; ?></td>
                            <td><code><?php echo htmlspecialchars($p['access_pin']); ?></code></td>
                            <td><small><?php echo htmlspecialchars($p['admin_email']); ?></small></td>
                            <td>
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="../index.php?pin=<?php echo urlencode($p['access_pin']); ?>" class="btn btn-sm btn-outline-info project-btn" target="_blank" title="Link öffnen">
                                        <span class="btn-icon">🔗</span><span class="btn-text">Link</span>
                                    </a>
                                    <button class="btn btn-sm btn-outline-success project-btn" data-bs-toggle="modal" data-bs-target="#pinModal" 
                                            onclick="showPinQR(<?php echo htmlspecialchars(json_encode($p)); ?>)" title="PIN & QR-Code anzeigen">
                                        <span class="btn-icon">📱</span><span class="btn-text">PIN/QR</span>
                                    </button>
                                    <?php if ($can_write_projects): ?>
                                        <?php if ($p['is_active']): ?>
                                            <a href="?deactivate=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-danger project-btn" onclick="return confirm('Projekt deaktivieren?')" title="Deaktivieren">
                                                <span class="btn-icon">🔴</span><span class="btn-text">Deaktivieren</span>
                                            </a>
                                        <?php else: ?>
                                            <a href="?activate=<?php echo $p['id']; ?>" class="btn btn-sm btn-success project-btn" onclick="return confirm('Projekt aktivieren?')" title="Aktivieren">
                                                <span class="btn-icon">✅</span><span class="btn-text">Aktivieren</span>
                                            </a>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-outline-warning project-btn" data-bs-toggle="modal" data-bs-target="#editProjectModal" 
                                                onclick="loadProjectData(<?php echo htmlspecialchars(json_encode($p)); ?>)" title="Bearbeiten">
                                            <span class="btn-icon">✏️</span><span class="btn-text">Bearbeiten</span>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($p['is_active']): ?>
                                        <a href="dishes.php?project=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-secondary project-btn" title="Menü verwalten">
                                            <span class="btn-icon">🍽️</span><span class="btn-text">Menü</span>
                                        </a>
                                        <a href="guests.php?project=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-secondary project-btn" title="Gäste verwalten">
                                            <span class="btn-icon">👥</span><span class="btn-text">Gäste</span>
                                        </a>
                                    <?php else: ?>
                                        <?php if ($can_write_projects): ?>
                                            <button class="btn btn-sm btn-outline-secondary project-btn" onclick="createProjectBackupFor(<?php echo $p['id']; ?>)" title="Projekt-Backup erstellen">
                                                <span class="btn-icon">💾</span><span class="btn-text">Backup</span>
                                            </button>
                                            <button class="btn btn-sm btn-outline-dark project-btn" onclick="confirmDelete(<?php echo $p['id']; ?>)" title="Projekt löschen">
                                                <span class="btn-icon">🗑️</span><span class="btn-text">Löschen</span>
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL: BEARBEITEN PROJEKT -->
<div class="modal fade" id="editProjectModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Projekt bearbeiten</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" id="editProjectForm">
                <input type="hidden" name="project_id" id="edit_project_id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Projektname *</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ort</label>
                            <input type="text" name="location" id="edit_location" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Beschreibung</label>
                            <div id="edit_description_editor" class="quill-editor"></div>
                            <input type="hidden" name="description" id="edit_description">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ansprechpartner</label>
                            <input type="text" name="contact_person" id="edit_contact_person" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label d-block mb-1">Telefon</label>
                            <input type="tel" id="edit_contact_phone_visible" class="form-control" placeholder="z.B. 0151 1234567" autocomplete="tel" inputmode="tel">
                            <input type="hidden" name="contact_phone" id="edit_contact_phone_full">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Kontakt Email</label>
                            <input type="email" name="contact_email" id="edit_contact_email" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Max. Gäste *</label>
                            <input type="number" name="max_guests" id="edit_max_guests" class="form-control" min="1" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Admin E-Mail (für BCC) *</label>
                            <input type="email" name="admin_email" id="edit_admin_email" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_show_prices" name="show_prices" value="1">
                                <label class="form-check-label" for="edit_show_prices">Preise in der Gästemaske anzeigen</label>
                            </div>
                            <div class="form-text text-muted">Wenn aktiv, werden die Gerichtspreise für Gäste sichtbar.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <div class="me-auto">
                        <button type="button" class="btn btn-secondary" id="projectBackupBtn" onclick="createProjectBackup()">Projekt sichern</button>
                    </div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" name="update_project" class="btn btn-primary">Änderungen speichern</button>
                </div>
            </form>
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
            <form method="post" id="addProjectForm">
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
                            <div id="add_description_editor" class="quill-editor"></div>
                            <input type="hidden" name="description" id="add_description">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ansprechpartner</label>
                            <input type="text" name="contact_person" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label d-block mb-1">Telefon</label>
                            <input type="tel" id="add_contact_phone_visible" class="form-control" placeholder="z.B. 0151 1234567" autocomplete="tel" inputmode="tel">
                            <input type="hidden" name="contact_phone" id="add_contact_phone_full">
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
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="add_show_prices" name="show_prices" value="1">
                                <label class="form-check-label" for="add_show_prices">Preise in der Gästemaske anzeigen</label>
                            </div>
                            <div class="form-text text-muted">Wenn aktiv, werden die Gerichtspreise für Gäste sichtbar.</div>
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

<!-- MODAL: PIN & QR-CODE -->
<div class="modal fade" id="pinModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">🔐 PIN & QR-Code - <span id="pinProjectName2"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-4">
                
                <!-- PIN ANZEIGE -->
                <div class="card bg-primary border-primary mb-4 p-5">
                    <h6 class="text-light mb-3">Zugangs-PIN zum Weitergeben:</h6>
                    <div class="pin-display fw-bold text-white" id="pinDisplay"></div>
                    <small class="text-light d-block mt-2">Kopieren oder verbal weitergeben</small>
                </div>
                
                <!-- QR-CODE -->
                <div class="card bg-dark border-secondary p-4 mb-4">
                    <h6 class="mb-3">QR-Code (zum Scannen)</h6>
                    <div style="background: white; padding: 15px; display: inline-block; border-radius: 5px;">
                        <img id="qrcodeImage" src="" alt="QR-Code" class="img-fluid" style="width: 250px; height: 250px;">
                    </div>
                    <div class="mt-3">
                        <a id="downloadQrBtn" href="" download class="btn btn-outline-success w-100">⬇️ QR-Code als Bild herunterladen</a>
                    </div>
                </div>
                
                <!-- QUICK ACTIONS -->
                <div class="row g-2">
                    <div class="col-6">
                        <button type="button" class="btn btn-outline-primary w-100" id="copyPinBtn">
                            📋 PIN Kopieren
                        </button>
                    </div>
                    <div class="col-6">
                        <button type="button" class="btn btn-outline-info w-100" data-bs-toggle="modal" data-bs-target="#emailInviteModal">
                            ✉️ Per E-Mail
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: E-MAIL EINLADUNG -->
<div class="modal fade" id="emailInviteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Einladung versenden</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="emailInviteForm" method="post">
                <input type="hidden" name="send_invite" value="1">
                <input type="hidden" name="project_id" id="emailProjectId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">E-Mail Adressen (kommagetrennt) *</label>
                        <textarea name="recipient_emails" class="form-control" rows="4" placeholder="person1@example.com, person2@example.com" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nachricht (optional)</label>
                        <textarea name="custom_message" class="form-control" rows="3" placeholder="Persönliche Nachricht..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Einladung versenden</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>
<script>
let currentProject = null;
let editQuill = null;
let addQuill = null;

function initQuillEditors() {
    const toolbarOptions = [
        [{ 'header': [1, 2, 3, false] }],
        [{ 'size': ['small', false, 'large', 'huge'] }],
        ['bold', 'italic', 'underline'],
        [{ 'color': [] }, { 'background': [] }],
        [{ 'align': [] }],
        [{'list': 'ordered'}, {'list': 'bullet'}],
        ['link'],
        ['clean']
    ];

    const editEditor = document.getElementById('edit_description_editor');
    if (editEditor) {
        editQuill = new Quill(editEditor, {
            theme: 'snow',
            modules: { toolbar: toolbarOptions }
        });
    }

    const addEditor = document.getElementById('add_description_editor');
    if (addEditor) {
        addQuill = new Quill(addEditor, {
            theme: 'snow',
            modules: { toolbar: toolbarOptions }
        });
    }

    const editForm = document.getElementById('editProjectForm');
    if (editForm) {
        editForm.addEventListener('submit', function(){
            const hidden = document.getElementById('edit_description');
            if (editQuill && hidden) hidden.value = editQuill.root.innerHTML;
        });
    }

    const addForm = document.getElementById('addProjectForm');
    if (addForm) {
        addForm.addEventListener('submit', function(){
            const hidden = document.getElementById('add_description');
            if (addQuill && hidden) hidden.value = addQuill.root.innerHTML;
        });
    }
}

function showPinQR(project) {
    currentProject = project;
    document.getElementById('pinProjectName2').textContent = project.name;
    document.getElementById('pinDisplay').textContent = project.access_pin;
    document.getElementById('emailProjectId').value = project.id;
    
    // QR-Code URL - mit PIN Parameter statt project ID
    const qrUrl = 'generate_qrcode.php?pin=' + project.access_pin + '&project=' + project.id;
    document.getElementById('qrcodeImage').src = qrUrl + '&size=300';
    document.getElementById('downloadQrBtn').href = qrUrl + '&download=1';
    
    // Copy to Clipboard
    document.getElementById('copyPinBtn').onclick = function() {
        navigator.clipboard.writeText(project.access_pin).then(() => {
            this.textContent = '✓ Kopiert!';
            setTimeout(() => {
                this.textContent = '📋 PIN Kopieren';
            }, 2000);
        }).catch(() => {
            // Fallback wenn Clipboard nicht verfügbar
            alert('PIN: ' + project.access_pin);
        });
    };
}

function loadProjectData(project) {
    document.getElementById('edit_project_id').value = project.id;
    document.getElementById('edit_name').value = project.name;
    document.getElementById('edit_location').value = project.location || '';
    document.getElementById('edit_description').value = project.description || '';
    if (editQuill) {
        editQuill.root.innerHTML = project.description || '';
    }
    document.getElementById('edit_contact_person').value = project.contact_person || '';
    var phoneEl = document.getElementById('edit_contact_phone_visible');
    var phoneFull = document.getElementById('edit_contact_phone_full');
    if (phoneEl) {
        try {
            if (phoneEl._iti && typeof phoneEl._iti.setNumber === 'function') {
                phoneEl._iti.setNumber(project.contact_phone || '');
            } else {
                phoneEl.value = project.contact_phone || '';
            }
        } catch(e) {
            phoneEl.value = project.contact_phone || '';
        }
    }
    if (phoneFull) {
        phoneFull.value = project.contact_phone || '';
    }
    document.getElementById('edit_contact_email').value = project.contact_email || '';
    document.getElementById('edit_max_guests').value = project.max_guests;
    document.getElementById('edit_admin_email').value = project.admin_email;
    var showPricesEl = document.getElementById('edit_show_prices');
    if (showPricesEl) {
        showPricesEl.checked = !!parseInt(project.show_prices || 0, 10);
    }
}
</script>
<script>
document.addEventListener('DOMContentLoaded', function(){
    initQuillEditors();
});
</script>
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@24.5.0/build/js/intlTelInput.min.js"></script>
<script>
// Initialize intl-tel-input for admin modal phone fields (if library loaded)
document.addEventListener('DOMContentLoaded', function(){
    try {
        var editVisible = document.getElementById('edit_contact_phone_visible');
        var editFull = document.getElementById('edit_contact_phone_full');
        var addVisible = document.getElementById('add_contact_phone_visible');
        var addFull = document.getElementById('add_contact_phone_full');

        function attach(formId, visibleEl, hiddenEl) {
            var form = document.getElementById(formId);
            if (!form || !visibleEl) return;
            // init intl-tel-input if available
            try {
                if (typeof window.intlTelInput === 'function' && !visibleEl._iti) {
                    var iti = window.intlTelInput(visibleEl, {
                        initialCountry: 'auto',
                        separateDialCode: true,
                        preferredCountries: ['de','at','ch'],
                        utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@24.5.0/build/js/utils.js',
                        geoIpLookup: function(cb){ cb('DE'); },
                        dropdownContainer: visibleEl.closest('.modal') || document.body,
                        autoHideDialCode: false
                    });
                    visibleEl._iti = iti;
                }
            } catch(e) {}

            form.addEventListener('submit', function(){
                try {
                    if (visibleEl && visibleEl._iti && visibleEl._iti.getNumber) {
                        hiddenEl && (hiddenEl.value = visibleEl._iti.getNumber());
                    } else if (visibleEl) {
                        hiddenEl && (hiddenEl.value = visibleEl.value || '');
                    }
                } catch(e) {}
            });
        }

        attach('editProjectForm', editVisible, editFull);
        attach('addProjectForm', addVisible, addFull);
    } catch(e) {}
});
</script>
<script>
function createProjectBackup() {
    var projectId = document.getElementById('edit_project_id').value;
    if (!projectId) return alert('Kein Projekt ausgewählt.');
    if (!confirm('Backup für dieses Projekt erstellen?')) return;

    var btn = document.getElementById('projectBackupBtn');
    var old = btn.textContent;
    btn.textContent = 'Sichere...';
    btn.disabled = true;

    fetch('backup_process.php?action=execute', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'backup_type=project&project_id=' + encodeURIComponent(projectId)
    }).then(r => r.json()).then(data => {
        if (data && data.status === 'completed') {
            alert('Backup erstellt: ' + (data.files_created && data.files_created[0] ? data.files_created[0] : 'OK'));
            // optional: refresh backup page in new tab
        } else if (data && data.status === 'processing') {
            alert('Backup gestartet. Schau auf der Backup-Seite nach dem Ergebnis.');
        } else if (data && data.error) {
            alert('Fehler: ' + data.error);
        } else {
            alert('Backup beendet: ' + (data.message || JSON.stringify(data)));
        }
    }).catch(e => {
        alert('Fehler beim Erstellen des Backups: ' + e.message);
    }).finally(() => {
        btn.textContent = old;
        btn.disabled = false;
    });
}
</script>
<script>
function createProjectBackupFor(projectId) {
    if (!projectId) return alert('Ungültige Projekt-ID');
    if (!confirm('Backup für Projekt #' + projectId + ' erstellen?')) return;

    var original = null;
    // simple UX: show prompt
    fetch('backup_process.php?action=execute', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'backup_type=project&project_id=' + encodeURIComponent(projectId)
    }).then(r => r.json()).then(data => {
        if (!data) return alert('Keine Antwort vom Server');
        if (data.status === 'completed') {
            alert('Backup erstellt: ' + (data.files_created && data.files_created[0] ? data.files_created[0] : 'OK'));
        } else if (data.status === 'processing') {
            alert('Backup gestartet. Schau auf der Backup-Seite nach dem Ergebnis.');
        } else if (data.status === 'error') {
            alert('Fehler: ' + (data.message || 'Unbekannter Fehler'));
        } else {
            alert('Backup: ' + (data.message || JSON.stringify(data)));
        }
    }).catch(e => {
        alert('Fehler beim Erstellen des Backups: ' + e.message);
    });
}
</script>
<!-- DELETE CONFIRM MODAL -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Projekt endgültig löschen?</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="fw-bold">Achtung — Dieser Vorgang ist endgültig.</p>
                <p>Beim Löschen werden alle Gäste, Bestellungen und Gerichte des Projekts entfernt. Bitte erst ein Backup erstellen, falls benötigt.</p>
                <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="confirmBackupCheck">
                        <label class="form-check-label" for="confirmBackupCheck">Ich habe ein Backup erstellt oder möchte fortfahren ohne Backup.</label>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                <button type="button" id="deleteConfirmBtn" class="btn btn-danger" disabled>Endgültig löschen</button>
            </div>
        </div>
    </div>
</div>

<form id="deleteProjectForm" method="post" style="display:none;">
        <input type="hidden" name="project_id" id="delete_project_id" value="">
        <input type="hidden" name="delete_project" value="1">
</form>

<script>
function confirmDelete(id) {
        document.getElementById('confirmBackupCheck').checked = false;
        document.getElementById('deleteConfirmBtn').disabled = true;
        document.getElementById('delete_project_id').value = id;
        var modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
        modal.show();

        // Checkbox Event Listener für Button Enable/Disable
        var checkbox = document.getElementById('confirmBackupCheck');
        var deleteBtn = document.getElementById('deleteConfirmBtn');
        
        checkbox.onchange = function() {
                deleteBtn.disabled = !this.checked;
        };

        deleteBtn.onclick = function(){
                document.getElementById('deleteProjectForm').submit();
        };
}
</script>
<?php include '../nav/footer.php'; ?>
</body>
</html>
