<?php
/**
 * script/mailer.php - PHPMailer Integration für Event Menue Order System (EMOS)
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Autoloader für PHPMailer
if (file_exists(__DIR__ . '/phpmailer/src/PHPMailer.php')) {
    require __DIR__ . '/phpmailer/src/Exception.php';
    require __DIR__ . '/phpmailer/src/PHPMailer.php';
    require __DIR__ . '/phpmailer/src/SMTP.php';
}

/**
 * Versendet E-Mail für Gast-Bestellung
 */
function sendOrderConfirmation($pdo, $prefix, $guest_id) {
    global $config;

    // SMTP Config laden
    $stmt = $pdo->query("SELECT * FROM {$prefix}smtp_config WHERE id = 1");
    $smtp = $stmt->fetch();

    if (!$smtp || empty($smtp['smtp_host'])) {
        return ["status" => false, "error" => "SMTP nicht konfiguriert."];
    }

    // Gast und Bestellung laden
    $stmt = $pdo->prepare("SELECT * FROM {$prefix}guests WHERE id = ?");
    $stmt->execute([$guest_id]);
    $guest = $stmt->fetch();

    if (!$guest) {
        return ["status" => false, "error" => "Gast nicht gefunden."];
    }

    // Bestellungen laden
    $stmt = $pdo->prepare("SELECT o.quantity, d.name, c.name as category FROM {$prefix}orders o 
                           JOIN {$prefix}dishes d ON o.dish_id = d.id 
                           JOIN {$prefix}menu_categories c ON d.category_id = c.id 
                           WHERE o.guest_id = ? ORDER BY c.sort_order");
    $stmt->execute([$guest_id]);
    $orders = $stmt->fetchAll();

    // Projekt Info
    $stmt = $pdo->prepare("SELECT * FROM {$prefix}projects WHERE id = ?");
    $stmt->execute([$guest['project_id']]);
    $project = $stmt->fetch();

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $smtp['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp['smtp_user'];
        $mail->Password   = $smtp['smtp_pass'];
        $mail->SMTPSecure = $smtp['smtp_secure'] !== 'none' ? $smtp['smtp_secure'] : false;
        $mail->Port       = (int)$smtp['smtp_port'];
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($smtp['sender_email'], $smtp['sender_name']);
        $mail->addAddress($guest['email'], $guest['firstname'] . ' ' . $guest['lastname']);
        
        // BCC an Admin
        if (!empty($project['admin_email'])) {
            $mail->addBCC($project['admin_email']);
        }

        $mail->isHTML(true);
        $mail->Subject = "Menübestellung bestätigt - " . htmlspecialchars($project['name']);

        // HTML Body
        $html = "
        <html>
        <head><meta charset='UTF-8'></head>
        <body style='font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px;'>
            <div style='max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
                <h2 style='color: #333; border-bottom: 3px solid #007bff; padding-bottom: 10px;'>Menübestellung bestätigt</h2>
                
                <p>Liebe/r <strong>" . htmlspecialchars($guest['firstname']) . " " . htmlspecialchars($guest['lastname']) . "</strong>,</p>
                
                <p>vielen Dank für Ihre Bestellung für die Veranstaltung <strong>" . htmlspecialchars($project['name']) . "</strong>.</p>
                
                <h3 style='color: #007bff; margin-top: 20px;'>Ihre Bestellungen:</h3>
                <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                    <tr style='background: #f9f9f9; border-bottom: 1px solid #ddd;'>
                        <th style='padding: 10px; text-align: left; font-weight: bold;'>Kategorie</th>
                        <th style='padding: 10px; text-align: left; font-weight: bold;'>Gericht</th>
                        <th style='padding: 10px; text-align: center; font-weight: bold;'>Anzahl</th>
                    </tr>";

        foreach ($orders as $o) {
            $html .= "
                    <tr style='border-bottom: 1px solid #ddd;'>
                        <td style='padding: 10px;'>" . htmlspecialchars($o['category']) . "</td>
                        <td style='padding: 10px;'>" . htmlspecialchars($o['name']) . "</td>
                        <td style='padding: 10px; text-align: center;'><strong>" . $o['quantity'] . "</strong></td>
                    </tr>";
        }

        $html .= "
                </table>
                
                <p style='color: #666; font-size: 14px;'>Gästeart: " . ($guest['guest_type'] === 'family' ? 'Familie (' . $guest['family_size'] . ' Personen)' : 'Einzelperson') . "</p>
                <p style='color: #666; font-size: 14px;'>Alter: " . ($guest['age_group'] === 'child' ? 'Kind (' . $guest['child_age'] . ' Jahre)' : 'Erwachsen') . "</p>
                
                <hr style='margin: 20px 0; border: none; border-top: 1px solid #ddd;'>
                
                <p style='color: #999; font-size: 12px;'>Bei Fragen wenden Sie sich bitte an:<br>
                " . htmlspecialchars($project['contact_person'] ?? 'Veranstalter') . "<br>
                Email: <a href='mailto:" . htmlspecialchars($project['contact_email'] ?? '') . "'>" . htmlspecialchars($project['contact_email'] ?? '') . "</a><br>
                Tel: " . htmlspecialchars($project['contact_phone'] ?? '') . "</p>
                
                <p style='color: #999; font-size: 12px; margin-top: 30px;'>
                    Mit freundlichen Grüßen,<br>
                    Event Menue Order System (EMOS)
                </p>
            </div>
        </body>
        </html>";

        $mail->Body = $html;

        // Alternativtext
        $mail->AltBody = "Bestellung für " . htmlspecialchars($project['name']) . " bestätigt.\n";
        foreach ($orders as $o) {
            $mail->AltBody .= "- " . $o['quantity'] . "x " . $o['name'] . "\n";
        }

        $mail->send();

        // Log
        logMailSent($pdo, $prefix, $smtp['sender_email'], $guest['email'], $mail->Subject, 'success');

        return ["status" => true];

    } catch (Exception $e) {
        logMailSent($pdo, $prefix, $smtp['sender_email'], $guest['email'], $mail->Subject ?? 'Unknown', 'failed', $mail->ErrorInfo);
        return ["status" => false, "error" => $mail->ErrorInfo];
    }
}

/**
 * Testzugriff für SMTP
 */
function sendTestMail($pdo, $prefix, $recipient_email) {
    global $config;

    $stmt = $pdo->query("SELECT * FROM {$prefix}smtp_config WHERE id = 1");
    $smtp = $stmt->fetch();

    if (!$smtp || empty($smtp['smtp_host'])) {
        return ["status" => false, "error" => "SMTP nicht konfiguriert."];
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $smtp['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp['smtp_user'];
        $mail->Password   = $smtp['smtp_pass'];
        $mail->SMTPSecure = $smtp['smtp_secure'] !== 'none' ? $smtp['smtp_secure'] : false;
        $mail->Port       = (int)$smtp['smtp_port'];
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($smtp['sender_email'], $smtp['sender_name']);
        $mail->addAddress($recipient_email);

        $mail->isHTML(true);
        $mail->Subject = "Event Menue Order System (EMOS) - SMTP Testmail";
        $mail->Body = "<h2>✓ SMTP Test erfolgreich!</h2><p>Diese Testmail wurde am " . date('d.m.Y H:i:s') . " versendet.</p>";

        $mail->send();

        logMailSent($pdo, $prefix, $smtp['sender_email'], $recipient_email, $mail->Subject, 'success');

        return ["status" => true];

    } catch (Exception $e) {
        logMailSent($pdo, $prefix, $smtp['sender_email'], $recipient_email, 'Test Mail', 'failed', $mail->ErrorInfo);
        return ["status" => false, "error" => $mail->ErrorInfo];
    }
}

/**
 * Mail Log speichern
 */
function logMailSent($pdo, $prefix, $sender, $recipient, $subject, $status, $error = null) {
    $stmt = $pdo->prepare("INSERT INTO {$prefix}mail_logs (sender, recipient, subject, status, error_message) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$sender, $recipient, $subject, $status, $error]);
}
