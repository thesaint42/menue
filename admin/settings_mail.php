<?php
/**
 * admin/settings_mail.php - SMTP Mail-Einstellungen
 */

require_once '../db.php';
require_once '../script/auth.php';
require_once '../script/mailer.php';

checkLogin();
checkAdmin();

$prefix = $config['database']['prefix'] ?? 'menu_';
$message = "";
$messageType = "info";

// SMTP Einstellungen speichern
if (isset($_POST['save_smtp'])) {
    $stmt = $pdo->prepare("UPDATE {$prefix}smtp_config SET smtp_host = ?, smtp_port = ?, smtp_user = ?, smtp_pass = ?, smtp_secure = ?, sender_email = ?, sender_name = ? WHERE id = 1");
    $stmt->execute([
        trim($_POST['smtp_host']),
        (int)$_POST['smtp_port'],
        trim($_POST['smtp_user']),
        trim($_POST['smtp_pass']),
        $_POST['smtp_secure'],
        trim($_POST['sender_email']),
        trim($_POST['sender_name'])
    ]);

    $message = "✓ SMTP-Konfiguration gespeichert.";
    $messageType = "success";
    logAction($pdo, $prefix, 'smtp_updated', 'SMTP Konfiguration aktualisiert');
}

// Test Email versenden
if (isset($_POST['send_test_mail'])) {
    $test_email = trim($_POST['test_email']) ?: $_SESSION['email'];
    $result = sendTestMail($pdo, $prefix, $test_email);
    if ($result['status']) {
        $message = "✓ Test-Email erfolgreich an " . htmlspecialchars($test_email) . " versendet.";
        $messageType = "success";
    } else {
        $message = "✗ Fehler: " . $result['error'];
        $messageType = "danger";
    }
}

// SMTP Config laden
$smtp = $pdo->query("SELECT * FROM {$prefix}smtp_config WHERE id = 1")->fetch();

// Mail Logs laden
$logs = $pdo->query("SELECT * FROM {$prefix}mail_logs ORDER BY sent_at DESC LIMIT 10")->fetchAll();
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>Mail-Einstellungen - Event Menue Order System (EMOS)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include '../nav/top_nav.php'; ?>

<div class="container py-4">
    <h2 class="mb-4">⚙️ Mail-Einstellungen</h2>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- SMTP CONFIG -->
        <div class="col-lg-6">
            <div class="card border-0 shadow">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0">SMTP Server Konfiguration</h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">SMTP Host</label>
                            <input type="text" name="smtp_host" class="form-control" value="<?php echo htmlspecialchars($smtp['smtp_host']); ?>" required>
                            <small class="text-muted">z.B. smtp.gmail.com, smtp.strato.de</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">SMTP Port</label>
                            <input type="number" name="smtp_port" class="form-control" value="<?php echo $smtp['smtp_port']; ?>" required>
                            <small class="text-muted">Meist 587 (TLS) oder 465 (SSL)</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">SMTP Benutzername</label>
                            <input type="text" name="smtp_user" class="form-control" value="<?php echo htmlspecialchars($smtp['smtp_user']); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">SMTP Passwort</label>
                            <input type="password" name="smtp_pass" class="form-control" value="<?php echo htmlspecialchars($smtp['smtp_pass']); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Verschlüsselung</label>
                            <select name="smtp_secure" class="form-select" required>
                                <option value="tls" <?php echo $smtp['smtp_secure'] === 'tls' ? 'selected' : ''; ?>>TLS (Port 587)</option>
                                <option value="ssl" <?php echo $smtp['smtp_secure'] === 'ssl' ? 'selected' : ''; ?>>SSL (Port 465)</option>
                                <option value="none" <?php echo $smtp['smtp_secure'] === 'none' ? 'selected' : ''; ?>>Keine (Port 25)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Absender Email *</label>
                            <input type="email" name="sender_email" class="form-control" value="<?php echo htmlspecialchars($smtp['sender_email']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Absender Name *</label>
                            <input type="text" name="sender_name" class="form-control" value="<?php echo htmlspecialchars($smtp['sender_name']); ?>" required>
                        </div>
                        <button type="submit" name="save_smtp" class="btn btn-primary w-100 fw-bold">Speichern</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- TEST MAIL -->
        <div class="col-lg-6">
            <div class="card border-0 shadow">
                <div class="card-header bg-success text-white py-3">
                    <h5 class="mb-0">SMTP Test</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Senden Sie eine Test-Email, um Ihre SMTP-Konfiguration zu überprüfen.</p>
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Test Email Empfänger</label>
                            <input type="email" name="test_email" class="form-control" value="<?php echo htmlspecialchars($_SESSION['email']); ?>" required>
                        </div>
                        <button type="submit" name="send_test_mail" class="btn btn-success w-100 fw-bold">Test-Email versenden</button>
                    </form>

                    <hr>

                    <h6 class="mt-4 mb-3">📊 Letzte E-Mails</h6>
                    <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                        <table class="table table-sm table-hover">
                            <thead class="table-dark sticky-top">
                                <tr>
                                    <th>Empfänger</th>
                                    <th>Status</th>
                                    <th>Zeit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><small><?php echo htmlspecialchars(substr($log['recipient'], 0, 25)); ?></small></td>
                                        <td>
                                            <span class="badge bg-<?php echo $log['status'] === 'success' ? 'success' : 'danger'; ?>">
                                                <?php echo $log['status'] === 'success' ? '✓' : '✗'; ?>
                                            </span>
                                        </td>
                                        <td><small><?php echo date('d.m. H:i', strtotime($log['sent_at'])); ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- INFO BOX -->
    <div class="alert alert-info mt-4">
        <strong>💡 Hinweis:</strong> Die Gäste-Bestätigungsmails werden automatisch versendet, wenn ein Gast sein Formular absendet. 
        Die Admin-Email erhält eine BCC-Kopie aller Bestellungen.
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
