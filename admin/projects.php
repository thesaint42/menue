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
    $contact_phone_raw = trim($_POST['contact_phone']);
    $contact_phone = normalize_phone_e164($contact_phone_raw, 'DE');
    $contact_email = trim($_POST['contact_email']);
    $max_guests = (int)$_POST['max_guests'];
    $admin_email = trim($_POST['admin_email']);

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
                (name, description, location, contact_person, contact_phone, contact_email, max_guests, admin_email, access_pin, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $name, $description, $location, $contact_person, $contact_phone, $contact_email, $max_guests, $admin_email, $pin, $_SESSION['user_id']
            ]);

            $message = "✓ Projekt erfolgreich erstellt! PIN: <strong>$pin</strong>";
            $messageType = "success";
            logAction($pdo, $prefix, 'project_created', "Projekt: $name, PIN: $pin");

        } catch (Exception $e) {
            $message = "Fehler: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Projekt aktualisieren
if (isset($_POST['update_project'])) {
    $id = (int)$_POST['project_id'];
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $location = trim($_POST['location']);
    $contact_person = trim($_POST['contact_person']);
    $contact_phone_raw = trim($_POST['contact_phone']);
    $contact_phone = normalize_phone_e164($contact_phone_raw, 'DE');
    $contact_email = trim($_POST['contact_email']);
    $max_guests = (int)$_POST['max_guests'];
    $admin_email = trim($_POST['admin_email']);

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
                contact_phone = ?, contact_email = ?, max_guests = ?, admin_email = ?
                WHERE id = ?");
            
            $stmt->execute([
                $name, $description, $location, $contact_person, $contact_phone, $contact_email, $max_guests, $admin_email, $id
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

// Projekt deaktivieren
if (isset($_GET['deactivate'])) {
    $id = (int)$_GET['deactivate'];
    $stmt = $pdo->prepare("UPDATE {$prefix}projects SET is_active = 0 WHERE id = ?");
    $stmt->execute([$id]);
    $message = "Projekt deaktiviert.";
    $messageType = "success";
    logAction($pdo, $prefix, 'project_deactivated', "Projekt ID: $id");
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
                        <h2>Menüwahl - Einladung</h2>
                        <p>Sie sind zu <strong>{$project['name']}</strong> eingeladen!</p>
                        
                        <div style='border: 2px solid #007bff; padding: 20px; margin: 20px 0;'>
                            <p style='font-size: 18px; margin-bottom: 10px;'><strong>Zugangs-PIN:</strong></p>
                            <p style='font-size: 32px; font-weight: bold; font-family: monospace; letter-spacing: 5px; color: #007bff;'>
                                {$project['access_pin']}
                            </p>
                        </div>
                        
                        <p><strong>Oder nutzen Sie diesen Link:</strong></p>
                        <p><a href='{$access_url}' style='background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Zur Menüwahl</a></p>
                        
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
                                <a href="../index.php?pin=<?php echo urlencode($p['access_pin']); ?>" class="btn btn-sm btn-outline-info" target="_blank">🔗 Link</a>
                                <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#pinModal" 
                                        onclick="showPinQR(<?php echo htmlspecialchars(json_encode($p)); ?>)">📱 PIN/QR</button>
                                <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#editProjectModal" 
                                        onclick="loadProjectData(<?php echo htmlspecialchars(json_encode($p)); ?>)">✏️ Bearbeiten</button>
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
                            <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ansprechpartner</label>
                            <input type="text" name="contact_person" id="edit_contact_person" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Telefon</label>
                            <input type="tel" id="edit_contact_phone_visible" class="form-control">
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
                    </div>
                </div>
                <div class="modal-footer border-secondary">
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
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ansprechpartner</label>
                            <input type="text" name="contact_person" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Telefon</label>
                            <input type="tel" id="add_contact_phone_visible" class="form-control">
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
                    <div class="fs-1 fw-bold text-white" style="letter-spacing: 0.5em; font-family: monospace; font-size: 3rem !important;" id="pinDisplay"></div>
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

<script>
let currentProject = null;

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
}
</script>
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
</body>
</html>
