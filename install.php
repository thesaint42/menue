<?php
/**
 * install.php - Installation und Setup des Menüwahl-Systems
 */

require_once 'db.php';
require_once 'script/schema.php';

$message = "";
$messageType = "danger";
$install_success = false;
$step = isset($_POST['step']) ? (int)$_POST['step'] : 1;

// UMGEBUNGSPRÜFUNGEN
$script_dir = __DIR__ . '/script';
$storage_dir = __DIR__ . '/storage';
$test_file = $script_dir . '/.write_test';
$can_write_script = false;
$can_write_storage = false;

if (is_dir($script_dir) && @file_put_contents($test_file, 'test')) {
    $can_write_script = true;
    @unlink($test_file);
}

if (is_dir($storage_dir) && is_writable($storage_dir)) {
    $can_write_storage = true;
}

$checks = [
    'php_version' => version_compare(PHP_VERSION, '8.0.0', '>='),
    'pdo_mysql'   => extension_loaded('pdo_mysql'),
    'mbstring'    => extension_loaded('mbstring'),
    'script_dir'  => $can_write_script,
    'storage_dir' => $can_write_storage
];

$system_ready = !in_array(false, $checks, true);

// SCHRITT 2: DATENBANK VERBINDUNG TESTEN UND TABELLEN ERSTELLEN
if (isset($_POST['test_connection'])) {
    $host = trim($_POST['db_host']);
    $name = trim($_POST['db_name']);
    $user = trim($_POST['db_user']);
    $pass = trim($_POST['db_pass']);
    $prefix = trim($_POST['db_prefix']);

    // Validierung
    if (empty($prefix)) {
        $prefix = 'menu_';
    }
    if (!preg_match('/^[a-z0-9_]+$/', $prefix)) {
        $message = "Präfix darf nur Kleinbuchstaben, Zahlen und Unterstriche enthalten.";
        $messageType = "danger";
    } else {
        try {
            $test_pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            
            // Datenbank erstellen
            $test_pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $test_pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            // Tabellen erstellen
            $schemas = getMenuSelectionSchema($prefix);
            foreach ($schemas as $sql) {
                $test_pdo->exec($sql);
            }

            // Initialdaten einfügen
            $initData = getMenuSelectionInitData($prefix);
            foreach ($initData as $sql) {
                $test_pdo->exec($sql);
            }

            // SMTP Config Tabelle initialisieren
            $test_pdo->exec("INSERT IGNORE INTO `{$prefix}smtp_config` (id, smtp_host, smtp_port, smtp_user, smtp_pass, smtp_secure, sender_email, sender_name) 
                             VALUES (1, 'smtp.example.com', 587, '', '', 'tls', '', 'Menüwahl System')");

            $message = "✓ Datenbankverbindung erfolgreich und Tabellen erstellt!";
            $messageType = "success";

            // Config speichern
            $_SESSION['db_host'] = $host;
            $_SESSION['db_name'] = $name;
            $_SESSION['db_user'] = $user;
            $_SESSION['db_pass'] = $pass;
            $_SESSION['db_prefix'] = $prefix;
            $_SESSION['step'] = 2;

        } catch (PDOException $e) {
            $message = "Fehler: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// SCHRITT 3: ADMIN BENUTZER ERSTELLEN
if (isset($_POST['create_admin'])) {
    $host = $_SESSION['db_host'];
    $name = $_SESSION['db_name'];
    $user = $_SESSION['db_user'];
    $pass = $_SESSION['db_pass'];
    $prefix = $_SESSION['db_prefix'];

    try {
        $test_pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        require_once 'script/auth.php';

        $admin_firstname = trim($_POST['admin_firstname']);
        $admin_lastname = trim($_POST['admin_lastname']);
        $admin_email = trim($_POST['admin_email']);
        $admin_password = $_POST['admin_password'];
        $admin_password_confirm = $_POST['admin_password_confirm'];

        // Validierung
        if (empty($admin_firstname) || empty($admin_lastname) || empty($admin_email) || empty($admin_password)) {
            $message = "Alle Felder sind erforderlich.";
            $messageType = "danger";
        } elseif ($admin_password !== $admin_password_confirm) {
            $message = "Passwörter stimmen nicht überein.";
            $messageType = "danger";
        } elseif (strlen($admin_password) < 8) {
            $message = "Passwort muss mindestens 8 Zeichen lang sein.";
            $messageType = "danger";
        } elseif (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
            $message = "Ungültige E-Mail Adresse.";
            $messageType = "danger";
        } else {
            // Admin Benutzer erstellen
            $pw_hash = hashPassword($admin_password);
            $stmt = $test_pdo->prepare("INSERT INTO `{$prefix}users` (firstname, lastname, email, password_hash, role_id, is_active) VALUES (?, ?, ?, ?, 1, 1)");
            $stmt->execute([$admin_firstname, $admin_lastname, $admin_email, $pw_hash]);

            $_SESSION['step'] = 3;
            $message = "✓ Admin Benutzer erfolgreich erstellt!";
            $messageType = "success";
        }

    } catch (PDOException $e) {
        $message = "Fehler: " . $e->getMessage();
        $messageType = "danger";
    }
}

// SCHRITT 4: SMTP KONFIGURATION SPEICHERN
if (isset($_POST['save_smtp'])) {
    $host = $_SESSION['db_host'];
    $name = $_SESSION['db_name'];
    $user = $_SESSION['db_user'];
    $pass = $_SESSION['db_pass'];
    $prefix = $_SESSION['db_prefix'];

    try {
        $test_pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        $stmt = $test_pdo->prepare("UPDATE `{$prefix}smtp_config` SET smtp_host = ?, smtp_port = ?, smtp_user = ?, smtp_pass = ?, smtp_secure = ?, sender_email = ?, sender_name = ? WHERE id = 1");
        $stmt->execute([
            trim($_POST['smtp_host']),
            (int)$_POST['smtp_port'],
            trim($_POST['smtp_user']),
            trim($_POST['smtp_pass']),
            $_POST['smtp_secure'],
            trim($_POST['admin_email_mail']),
            trim($_POST['sender_name'])
        ]);

        // Config-Datei schreiben
        $configContent = "# Menüwahl-System Konfiguration\n";
        $configContent .= "# Automatisch generiert bei der Installation\n\n";
        $configContent .= "database:\n";
        $configContent .= "  host: \"" . $host . "\"\n";
        $configContent .= "  db_name: \"" . $name . "\"\n";
        $configContent .= "  user: \"" . $user . "\"\n";
        $configContent .= "  pass: \"" . $pass . "\"\n";
        $configContent .= "  prefix: \"" . $prefix . "\"\n\n";
        $configContent .= "mail:\n";
        $configContent .= "  admin_email: \"" . trim($_POST['admin_email_mail']) . "\"\n";
        $configContent .= "  sender_name: \"" . trim($_POST['sender_name']) . "\"\n\n";
        $configContent .= "system:\n";
        $configContent .= "  language: \"de\"\n";
        $configContent .= "  timezone: \"Europe/Berlin\"\n";

        file_put_contents(__DIR__ . '/script/config.yaml', $configContent);

        $_SESSION['step'] = 4;
        $message = "✓ SMTP Konfiguration gespeichert und config.yaml erstellt!";
        $messageType = "success";
        $install_success = true;

    } catch (PDOException $e) {
        $message = "Fehler: " . $e->getMessage();
        $messageType = "danger";
    }
}
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation - Menüwahl System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { min-height: 100vh; }
        .step-indicator { display: flex; justify-content: space-between; margin-bottom: 2rem; }
        .step { text-align: center; flex: 1; }
        .step-number { width: 50px; height: 50px; border-radius: 50%; background: #6c757d; color: white; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; font-weight: bold; }
        .step.active .step-number { background: #0d6efd; }
        .step.completed .step-number { background: #198754; }
        .step-line { position: absolute; top: 25px; width: calc(100% - 50px); height: 2px; background: #dee2e6; }
    </style>
</head>
<body class="bg-body-tertiary">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h1 class="text-center mb-4">🍽️ Menüwahl System - Installation</h1>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- SCHRITT 1: UMGEBUNGSPRÜFUNG -->
            <?php if ($step == 1): ?>
                <div class="card border-0 shadow">
                    <div class="card-body p-5">
                        <h2 class="mb-4">Schritt 1: Umgebungsprüfung</h2>
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>PHP Version (mindestens 8.0)</strong></td>
                                <td><?php echo version_compare(PHP_VERSION, '8.0.0', '>=') ? '<span class="badge bg-success">✓ OK</span>' : '<span class="badge bg-danger">✗ Fehler</span>'; ?> (<?php echo PHP_VERSION; ?>)</td>
                            </tr>
                            <tr>
                                <td><strong>PDO MySQL Extension</strong></td>
                                <td><?php echo extension_loaded('pdo_mysql') ? '<span class="badge bg-success">✓ OK</span>' : '<span class="badge bg-danger">✗ Fehler</span>'; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Mbstring Extension</strong></td>
                                <td><?php echo extension_loaded('mbstring') ? '<span class="badge bg-success">✓ OK</span>' : '<span class="badge bg-danger">✗ Fehler</span>'; ?></td>
                            </tr>
                            <tr>
                                <td><strong>script/ Verzeichnis schreibbar</strong></td>
                                <td><?php echo $can_write_script ? '<span class="badge bg-success">✓ OK</span>' : '<span class="badge bg-danger">✗ Fehler</span>'; ?></td>
                            </tr>
                            <tr>
                                <td><strong>storage/ Verzeichnis schreibbar</strong></td>
                                <td><?php echo $can_write_storage ? '<span class="badge bg-success">✓ OK</span>' : '<span class="badge bg-danger">✗ Fehler</span>'; ?></td>
                            </tr>
                        </table>

                        <?php if ($system_ready): ?>
                            <form method="post" class="mt-4">
                                <input type="hidden" name="step" value="2">
                                <h3 class="mb-3">Datenbank Verbindung</h3>
                                <div class="mb-3">
                                    <label class="form-label">Datenbank Host</label>
                                    <input type="text" name="db_host" class="form-control" value="localhost" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Datenbankname</label>
                                    <input type="text" name="db_name" class="form-control" placeholder="z.B. menuselection" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Datenbankbenutzer</label>
                                    <input type="text" name="db_user" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Datenbankpasswort</label>
                                    <input type="password" name="db_pass" class="form-control">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Tabellenpräfix</label>
                                    <input type="text" name="db_prefix" class="form-control" value="menu_" placeholder="z.B. menu_" required>
                                    <small class="form-text">Alle Tabellen werden mit diesem Präfix erstellt (z.B. menu_users, menu_projects)</small>
                                </div>
                                <button type="submit" name="test_connection" class="btn btn-primary btn-lg w-100">Verbindung testen & Tabellen erstellen</button>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-danger mt-4">
                                <strong>Fehler:</strong> Ihr System erfüllt nicht alle Anforderungen. Bitte prüfen Sie die fehlgeschlagenen Tests.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- SCHRITT 2: ADMIN BENUTZER -->
            <?php if ($_SESSION['step'] >= 2 && !$install_success): ?>
                <div class="card border-0 shadow">
                    <div class="card-body p-5">
                        <h2 class="mb-4">Schritt 2: Administrator anlegen</h2>
                        <form method="post">
                            <input type="hidden" name="step" value="3">
                            <div class="mb-3">
                                <label class="form-label">Vorname</label>
                                <input type="text" name="admin_firstname" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nachname</label>
                                <input type="text" name="admin_lastname" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">E-Mail Adresse</label>
                                <input type="email" name="admin_email" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Passwort (mindestens 8 Zeichen)</label>
                                <input type="password" name="admin_password" class="form-control" minlength="8" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Passwort wiederholen</label>
                                <input type="password" name="admin_password_confirm" class="form-control" minlength="8" required>
                            </div>
                            <button type="submit" name="create_admin" class="btn btn-primary btn-lg w-100">Administrator erstellen</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <!-- SCHRITT 3: SMTP KONFIGURATION -->
            <?php if ($_SESSION['step'] >= 3 && !$install_success): ?>
                <div class="card border-0 shadow">
                    <div class="card-body p-5">
                        <h2 class="mb-4">Schritt 3: SMTP & Mail-Einstellungen</h2>
                        <form method="post">
                            <input type="hidden" name="step" value="4">
                            
                            <h4 class="mb-3 mt-4">SMTP Server</h4>
                            <div class="mb-3">
                                <label class="form-label">SMTP Host</label>
                                <input type="text" name="smtp_host" class="form-control" placeholder="z.B. smtp.example.com" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">SMTP Port</label>
                                <input type="number" name="smtp_port" class="form-control" value="587" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">SMTP Benutzername</label>
                                <input type="text" name="smtp_user" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">SMTP Passwort</label>
                                <input type="password" name="smtp_pass" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Verschlüsselung</label>
                                <select name="smtp_secure" class="form-select" required>
                                    <option value="tls">TLS (Port 587)</option>
                                    <option value="ssl">SSL (Port 465)</option>
                                    <option value="none">Keine (Port 25)</option>
                                </select>
                            </div>

                            <h4 class="mb-3 mt-4">Mail-Einstellungen</h4>
                            <div class="mb-3">
                                <label class="form-label">Admin E-Mail Adresse (für BCC Versand)</label>
                                <input type="email" name="admin_email_mail" class="form-control" required>
                                <small class="form-text">Diese Adresse erhält automatisch eine Kopie aller Gast-Formulare</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Absendername</label>
                                <input type="text" name="sender_name" class="form-control" value="Menüwahl System" required>
                            </div>

                            <button type="submit" name="save_smtp" class="btn btn-success btn-lg w-100">Installation abschließen</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <!-- INSTALLATION ERFOLGREICH -->
            <?php if ($install_success): ?>
                <div class="card border-0 shadow bg-success text-white">
                    <div class="card-body p-5 text-center">
                        <h2 class="mb-4">✓ Installation erfolgreich abgeschlossen!</h2>
                        <p class="mb-4">Das Menüwahl System ist nun einsatzbereit.</p>
                        <div class="alert alert-light border-0 mb-4">
                            <strong>Nächste Schritte:</strong><br>
                            1. Melden Sie sich im Admin-Bereich an<br>
                            2. Legen Sie ein neues Projekt an<br>
                            3. Fügen Sie Menü-Gerichte hinzu<br>
                            4. Teilen Sie den Gast-Link mit Ihren Gästen
                        </div>
                        <a href="admin/login.php" class="btn btn-light btn-lg">Zum Admin-Login</a>
                        <a href="index.php" class="btn btn-light btn-lg">Zum Gast-Formular</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
