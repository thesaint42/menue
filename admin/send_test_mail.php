<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../db.php';
require_once '../script/auth.php';

checkLogin();
checkAdmin();

$prefix = $config['database']['prefix'] ?? 'menu_';

$recipient = trim($_POST['recipient'] ?? '');
$mode = $_POST['mode'] ?? 'test';
$project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : null;

if (empty($recipient) || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => false, 'error' => 'Ungültige Empfänger-E-Mail']);
    exit;
}

// Nur Testmail
if ($mode !== 'invite' || !$project_id) {
    require_once __DIR__ . '/../script/mailer.php';
    $res = sendTestMail($pdo, $prefix, $recipient);
    if ($res['status']) {
        echo json_encode(['status' => true, 'message' => 'Testmail gesendet.']);
    } else {
        echo json_encode(['status' => false, 'error' => $res['error'] ?? 'Fehler beim Versand']);
    }
    exit;
}

// Einladung senden (Projekt-basiert)
$stmt = $pdo->prepare("SELECT * FROM {$prefix}projects WHERE id = ?");
$stmt->execute([$project_id]);
$project = $stmt->fetch();

if (!$project) {
    echo json_encode(['status' => false, 'error' => 'Projekt nicht gefunden']);
    exit;
}

require_once __DIR__ . '/../script/mailer.php';

// Lade SMTP-Konfiguration
$stmt = $pdo->query("SELECT * FROM {$prefix}smtp_config WHERE id = 1");
$smtp = $stmt->fetch();

if (!$smtp) {
    echo json_encode(['status' => false, 'error' => 'SMTP nicht konfiguriert']);
    exit;
}

// Baue Zugangsdaten
$base_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$access_url = $base_url . dirname($_SERVER['PHP_SELF'], 2) . '/index.php?pin=' . urlencode($project['access_pin']);

$mail = new PHPMailer\PHPMailer\PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = $smtp['smtp_host'];
    $mail->SMTPAuth = true;
    $mail->Username = $smtp['smtp_user'];
    $mail->Password = $smtp['smtp_pass'];
    $mail->SMTPSecure = $smtp['smtp_secure'] !== 'none' ? $smtp['smtp_secure'] : false;
    $mail->Port = (int)$smtp['smtp_port'];
    $mail->CharSet = 'UTF-8';

    $mail->setFrom($smtp['sender_email'], $smtp['sender_name']);
    $mail->addAddress($recipient);
    $mail->isHTML(true);
    $mail->Subject = "Einladung: " . $project['name'];

    $mail->Body = "<h2>Event Menue Order System (EMOS) - Einladung</h2>" .
        "<p>Sie sind zu <strong>" . htmlspecialchars($project['name']) . "</strong> eingeladen!</p>" .
        "<div style='border:2px solid #007bff;padding:20px;margin:20px 0;'>" .
        "<p style='font-size:18px;margin-bottom:10px;'><strong>Zugangs-PIN:</strong></p>" .
        "<p style='font-size:32px;font-weight:bold;font-family:monospace;letter-spacing:5px;color:#007bff;'>" . htmlspecialchars($project['access_pin']) . "</p>" .
        "</div>" .
        "<p><a href='" . $access_url . "' style='background-color:#007bff;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>Zur Menüwahl</a></p>";

    $mail->send();
    logMailSent($pdo, $prefix, $smtp['sender_email'], $recipient, $mail->Subject, 'success');

    echo json_encode(['status' => true, 'message' => 'Einladung gesendet']);
} catch (Exception $e) {
    logMailSent($pdo, $prefix, $smtp['sender_email'], $recipient, $mail->Subject ?? 'Einladung', 'failed', $mail->ErrorInfo ?? $e->getMessage());
    echo json_encode(['status' => false, 'error' => $mail->ErrorInfo ?? $e->getMessage()]);
}

exit;
