<?php
/**
 * install.php - Installation und Setup des Menüwahl-Systems
 */

// Fehlerberichterstattung (für Debugging)
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db.php';
require_once 'script/schema.php';

$message = "";
$messageType = "danger";
$install_success = false;
$step = isset($_POST['step']) ? (int)$_POST['step'] : 1;

// Überprüfe auf Fehlerparameter von der db.php Umleitung
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'not_installed':
            $message = "⚠️ <strong>System nicht installiert</strong><br>Bitte schließen Sie die Installation ab.";
            $messageType = "warning";
            break;
        case 'config_empty':
            $message = "⚠️ <strong>Konfiguration unvollständig</strong><br>Die config.yaml ist leer oder beschädigt. Bitte führen Sie die Installation erneut aus.";
            $messageType = "warning";
            break;
        case 'db_connection_failed':
            $message = "⚠️ <strong>Datenbankverbindung fehlgeschlagen</strong><br>Überprüfen Sie Ihre Datenbankeinstellungen und führen Sie die Installation erneut aus.";
            $messageType = "warning";
            break;
    }
}

// Wenn eine Fehlerseite angezeigt wird, versuche einen kurzen Log-Auszug anzuzeigen
$log_excerpt = '';
if (isset($_GET['error'])) {
    $possible_logs = [__DIR__ . '/storage/logs/error.log', __DIR__ . '/storage/logs/app.log', __DIR__ . '/storage/logs/latest.log'];
    foreach ($possible_logs as $lf) {
        if (file_exists($lf) && is_readable($lf)) {
            // letzte 100 Zeilen lesen (konservativ)
            $lines = @file($lf, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                $start = max(0, count($lines) - 100);
                $excerpt = array_slice($lines, $start);
                $log_excerpt = implode("\n", array_map('htmlspecialchars', $excerpt));
                break;
            }
        }
    }
}

// UMGEBUNGSPRÜFUNGEN
$script_dir = __DIR__ . '/script';
$storage_dir = __DIR__ . '/storage';
$config_file = $script_dir . '/config.yaml';
$test_file = $script_dir . '/.write_test';
$can_write_script = false;
$can_write_storage = false;
$script_write_error = "";
$storage_write_error = "";

// Test 1: Schreibrechte für script/ Verzeichnis
if (is_dir($script_dir)) {
    if (@file_put_contents($test_file, 'test')) {
        $can_write_script = true;
        @unlink($test_file);
    } else {
        $script_write_error = "script/ Verzeichnis ist nicht schreibbar. Bitte setzen Sie die Schreibrechte (chmod 755 oder 775).";
    }
} else {
    $script_write_error = "script/ Verzeichnis existiert nicht!";
}

// Test 2: Schreibrechte für storage/ Verzeichnis (mit tatsächlichem Schreibversuch)
$storage_test_file = $storage_dir . '/.write_test';
if (is_dir($storage_dir)) {
    if (@file_put_contents($storage_test_file, 'test')) {
        $can_write_storage = true;
        @unlink($storage_test_file);
    } else {
        $storage_write_error = "storage/ Verzeichnis ist nicht schreibbar. Bitte setzen Sie die Schreibrechte (chmod 755 oder 775).";
    }
} else {
    $storage_write_error = "storage/ Verzeichnis existiert nicht!";
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

    // Speichere Eingaben in Session (damit sie bei Fehler erhalten bleiben)
    $_SESSION['form_db_host'] = $host;
    $_SESSION['form_db_name'] = $name;
    $_SESSION['form_db_user'] = $user;
    $_SESSION['form_db_pass'] = $pass;
    $_SESSION['form_db_prefix'] = $prefix;

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

            // Prüfe ob Tabellen bereits existieren
            $stmt = $test_pdo->query("SHOW TABLES LIKE '{$prefix}users'");
            $table_exists = $stmt->rowCount() > 0;

            if ($table_exists) {
                // Tabellen existieren - Abfrage ob überschrieben werden sollen
                $_SESSION['tables_exist'] = true;
                $_SESSION['db_host'] = $host;
                $_SESSION['db_name'] = $name;
                $_SESSION['db_user'] = $user;
                $_SESSION['db_pass'] = $pass;
                $_SESSION['db_prefix'] = $prefix;
                $message = "Tabellen existieren bereits. Bitte wählen Sie eine Option unterhalb.";
                $messageType = "warning";
            } else {
                // Keine Tabellen - neue Installation
                $_SESSION['tables_exist'] = false;
                
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
                                 VALUES (1, 'smtp.example.com', 587, '', '', 'tls', '', 'Event Menue Order System (EMOS)')");

                $message = "Datenbankverbindung erfolgreich und Tabellen erstellt!";
                $messageType = "success";
                $_SESSION['step'] = 2;

                // Config speichern
                $_SESSION['db_host'] = $host;
                $_SESSION['db_name'] = $name;
                $_SESSION['db_user'] = $user;
                $_SESSION['db_pass'] = $pass;
                $_SESSION['db_prefix'] = $prefix;
            }

        } catch (PDOException $e) {
            $message = "Fehler: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Wenn Tabellen existieren - Benutzer wählt Überschreiben oder Abbrechen
if (isset($_POST['override_tables'])) {
    $host = $_SESSION['db_host'];
    $name = $_SESSION['db_name'];
    $user = $_SESSION['db_user'];
    $pass = $_SESSION['db_pass'];
    $prefix = $_SESSION['db_prefix'];

    try {
        $test_pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // Deaktiviere Foreign Key Checks um Constraints nicht zu verletzen
        $test_pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

        // Alle Tabellen löschen
        $tables = [
            'roles', 'users', 'password_resets', 'projects', 'menu_categories',
            'dishes', 'guests', 'orders', 'smtp_config', 'mail_logs', 'logs'
        ];

        foreach ($tables as $table) {
            $test_pdo->exec("DROP TABLE IF EXISTS `{$prefix}{$table}`");
        }

        // Reaktiviere Foreign Key Checks
        $test_pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

        // Tabellen neu erstellen
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
                 VALUES (1, 'smtp.example.com', 587, '', '', 'tls', '', 'Event Menue Order System (EMOS)')");

        $message = "Bestehende Tabellen überschrieben und neu erstellt!";
        $messageType = "success";
        $_SESSION['tables_exist'] = false;
        $_SESSION['step'] = 2;

    } catch (PDOException $e) {
        $message = "Fehler beim Überschreiben: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Wenn Benutzer Abbruch wählt
if (isset($_POST['cancel_install'])) {
    $_SESSION['tables_exist'] = false;
    $message = "Installation abgebrochen. Die bestehende Installation bleibt erhalten.";
    $messageType = "info";
    // Nutzer kann erneut versuchen, die Installation zu starten
}

// SCHRITT 3: ADMIN BENUTZER ERSTELLEN
if (isset($_POST['create_admin'])) {
    $host = $_SESSION['db_host'];
    $name = $_SESSION['db_name'];
    $user = $_SESSION['db_user'];
    $pass = $_SESSION['db_pass'];
    $prefix = $_SESSION['db_prefix'];

    $admin_firstname = trim($_POST['admin_firstname']);
    $admin_lastname = trim($_POST['admin_lastname']);
    $admin_email = trim($_POST['admin_email']);
    $admin_password = $_POST['admin_password'];
    $admin_password_confirm = $_POST['admin_password_confirm'];

    // Speichere Eingaben in Session (damit sie bei Fehler erhalten bleiben)
    $_SESSION['form_admin_firstname'] = $admin_firstname;
    $_SESSION['form_admin_lastname'] = $admin_lastname;
    $_SESSION['form_admin_email'] = $admin_email;

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
        try {
            $test_pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            require_once 'script/auth.php';

            // Admin Benutzer erstellen
            $pw_hash = hashPassword($admin_password);
            $stmt = $test_pdo->prepare("INSERT INTO `{$prefix}users` (firstname, lastname, email, password_hash, role_id, is_active) VALUES (?, ?, ?, ?, 1, 1)");
            $stmt->execute([$admin_firstname, $admin_lastname, $admin_email, $pw_hash]);

            $_SESSION['step'] = 3;
            $message = "Admin Benutzer erfolgreich erstellt!";
            $messageType = "success";
            
            // Leere die gespeicherten Eingaben
            unset($_SESSION['form_admin_firstname']);
            unset($_SESSION['form_admin_lastname']);
            unset($_SESSION['form_admin_email']);

        } catch (PDOException $e) {
            // Prüfe ob es ein Duplikat-Fehler ist
            if (strpos($e->getMessage(), '1062') !== false || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $message = "⚠️ <strong>E-Mail Adresse existiert bereits!</strong><br>Ein Administrator mit dieser E-Mail Adresse existiert bereits. Bitte geben Sie eine andere E-Mail Adresse ein.";
                $messageType = "danger";
            } else {
                $message = "Fehler: " . $e->getMessage();
                $messageType = "danger";
            }
        }
    }
}

// SCHRITT 4: SMTP KONFIGURATION SPEICHERN
if (isset($_POST['save_smtp'])) {
    // Prüfe ob Session-Daten vorhanden sind
    if (!isset($_SESSION['db_host']) || !isset($_SESSION['db_name']) || !isset($_SESSION['db_user']) || !isset($_SESSION['db_pass']) || !isset($_SESSION['db_prefix'])) {
        $message = "Fehler: Datenbank-Einstellungen fehlen. Bitte starten Sie die Installation neu.";
        $messageType = "danger";
    } else {
        $host = $_SESSION['db_host'];
        $name = $_SESSION['db_name'];
        $user = $_SESSION['db_user'];
        $pass = $_SESSION['db_pass'];
        $prefix = $_SESSION['db_prefix'];

        $smtp_host = trim($_POST['smtp_host'] ?? '');
        $smtp_port = (int)($_POST['smtp_port'] ?? 587);
        $smtp_user = trim($_POST['smtp_user'] ?? '');
        $smtp_pass = trim($_POST['smtp_pass'] ?? '');
        $smtp_secure = $_POST['smtp_secure'] ?? 'tls';
        $admin_email_mail = trim($_POST['admin_email_mail'] ?? '');
        $sender_name = trim($_POST['sender_name'] ?? 'Event Menue Order System (EMOS)');

        // Speichere Eingaben in Session
        $_SESSION['form_smtp_host'] = $smtp_host;
        $_SESSION['form_smtp_port'] = $smtp_port;
        $_SESSION['form_smtp_user'] = $smtp_user;
        $_SESSION['form_smtp_pass'] = $smtp_pass;
        $_SESSION['form_smtp_secure'] = $smtp_secure;
        $_SESSION['form_admin_email_mail'] = $admin_email_mail;
        $_SESSION['form_sender_name'] = $sender_name;

        try {
            $test_pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            $stmt = $test_pdo->prepare("UPDATE `{$prefix}smtp_config` SET smtp_host = ?, smtp_port = ?, smtp_user = ?, smtp_pass = ?, smtp_secure = ?, sender_email = ?, sender_name = ? WHERE id = 1");
            $stmt->execute([
                $smtp_host,
                $smtp_port,
                $smtp_user,
                $smtp_pass,
                $smtp_secure,
                $admin_email_mail,
                $sender_name
            ]);

            // Config-Datei schreiben (mit Escaping für spezielle Zeichen)
            $configContent = "# Event Menue Order System (EMOS) Konfiguration\n";
            $configContent .= "# Automatisch generiert bei der Installation\n\n";
            $configContent .= "database:\n";
            $configContent .= "  host: \"" . addslashes($host) . "\"\n";
            $configContent .= "  db_name: \"" . addslashes($name) . "\"\n";
            $configContent .= "  user: \"" . addslashes($user) . "\"\n";
            $configContent .= "  pass: \"" . addslashes($pass) . "\"\n";
            $configContent .= "  prefix: \"" . addslashes($prefix) . "\"\n\n";
            $configContent .= "mail:\n";
            $configContent .= "  admin_email: \"" . addslashes($admin_email_mail) . "\"\n";
            $configContent .= "  sender_name: \"" . addslashes($sender_name) . "\"\n\n";
            $configContent .= "system:\n";
            $configContent .= "  language: \"de\"\n";
            $configContent .= "  timezone: \"Europe/Berlin\"\n";

            $config_file = __DIR__ . '/script/config.yaml';
            if (@file_put_contents($config_file, $configContent) === false) {
                throw new Exception("Fehler beim Schreiben von config.yaml. Überprüfen Sie die Schreibrechte des script/ Verzeichnisses.");
            }

            $_SESSION['step'] = 4;
            $message = "SMTP Konfiguration gespeichert und config.yaml erstellt!";
            $messageType = "success";
            $install_success = true;

            // Leere die gespeicherten Eingaben
            unset($_SESSION['form_smtp_host']);
            unset($_SESSION['form_smtp_port']);
            unset($_SESSION['form_smtp_user']);
            unset($_SESSION['form_smtp_pass']);
            unset($_SESSION['form_smtp_secure']);
            unset($_SESSION['form_admin_email_mail']);
            unset($_SESSION['form_sender_name']);

        } catch (PDOException $e) {
            $message = "Datenbankfehler: " . $e->getMessage();
            $messageType = "danger";
        } catch (Exception $e) {
            $message = "Fehler: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation - Event Menue Order System (EMOS)</title>
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
            <h1 class="text-center mb-4">🍽️ Event Menue Order System (EMOS) - Installation</h1>

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
                            <?php if (!$can_write_script && $script_write_error): ?>
                                <tr>
                                    <td colspan="2"><small class="text-danger"><strong>⚠️ Fehler:</strong> <?php echo htmlspecialchars($script_write_error); ?></small></td>
                                </tr>
                            <?php endif; ?>
                            <tr>
                                <td><strong>storage/ Verzeichnis schreibbar</strong></td>
                                <td><?php echo $can_write_storage ? '<span class="badge bg-success">✓ OK</span>' : '<span class="badge bg-danger">✗ Fehler</span>'; ?></td>
                            </tr>
                            <?php if (!$can_write_storage && $storage_write_error): ?>
                                <tr>
                                    <td colspan="2"><small class="text-danger"><strong>⚠️ Fehler:</strong> <?php echo htmlspecialchars($storage_write_error); ?></small></td>
                                </tr>
                            <?php endif; ?>
                        </table>

                        <?php if ($system_ready): ?>
                            <form method="post" class="mt-4">
                                <input type="hidden" name="step" value="2">
                                <h3 class="mb-3">Datenbank Verbindung</h3>
                                <div class="mb-3">
                                    <label class="form-label">Datenbank Host</label>
                                    <input type="text" name="db_host" class="form-control" value="<?php echo isset($_SESSION['form_db_host']) ? htmlspecialchars($_SESSION['form_db_host']) : 'localhost'; ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Datenbankname</label>
                                    <input type="text" name="db_name" class="form-control" placeholder="z.B. menuselection" value="<?php echo isset($_SESSION['form_db_name']) ? htmlspecialchars($_SESSION['form_db_name']) : ''; ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Datenbankbenutzer</label>
                                    <input type="text" name="db_user" class="form-control" value="<?php echo isset($_SESSION['form_db_user']) ? htmlspecialchars($_SESSION['form_db_user']) : ''; ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Datenbankpasswort</label>
                                    <input type="password" name="db_pass" class="form-control" value="<?php echo isset($_SESSION['form_db_pass']) ? htmlspecialchars($_SESSION['form_db_pass']) : ''; ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Tabellenpräfix</label>
                                    <input type="text" name="db_prefix" class="form-control" value="<?php echo isset($_SESSION['form_db_prefix']) ? htmlspecialchars($_SESSION['form_db_prefix']) : 'menu_'; ?>" placeholder="z.B. menu_" required>
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

            <!-- ABFRAGE: BESTEHENDE TABELLEN ÜBERSCHREIBEN -->
            <?php if (isset($_SESSION['tables_exist']) && $_SESSION['tables_exist']): ?>
                <div class="card border-0 shadow border-warning mt-4">
                    <div class="card-body p-5 bg-light">
                        <h3 class="mb-4">⚠️ Tabellen bereits vorhanden</h3>
                        <p class="lead">Es wurden bereits Tabellen mit dem Präfix <strong><?php echo htmlspecialchars($_SESSION['db_prefix']); ?></strong> in der Datenbank gefunden.</p>
                        
                        <div class="alert alert-warning">
                            <strong>Was möchten Sie tun?</strong>
                            <ul class="mt-2">
                                <li><strong>Überschreiben:</strong> Alle vorhandenen Daten werden gelöscht und die Installation neu gestartet.</li>
                                <li><strong>Abbrechen:</strong> Die bestehende Installation bleibt erhalten und wird nicht verändert.</li>
                            </ul>
                        </div>

                        <div class="d-grid gap-2 d-sm-flex justify-content-sm-end">
                            <form method="post" class="d-inline">
                                <button type="submit" name="cancel_install" class="btn btn-secondary btn-lg">Abbrechen</button>
                            </form>
                            <form method="post" class="d-inline">
                                <button type="submit" name="override_tables" class="btn btn-danger btn-lg">Überschreiben & Neu installieren</button>
                            </form>
                        </div>
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
                                <input type="text" name="admin_firstname" class="form-control" value="<?php echo isset($_SESSION['form_admin_firstname']) ? htmlspecialchars($_SESSION['form_admin_firstname']) : ''; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nachname</label>
                                <input type="text" name="admin_lastname" class="form-control" value="<?php echo isset($_SESSION['form_admin_lastname']) ? htmlspecialchars($_SESSION['form_admin_lastname']) : ''; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">E-Mail Adresse</label>
                                <input type="email" name="admin_email" class="form-control" value="<?php echo isset($_SESSION['form_admin_email']) ? htmlspecialchars($_SESSION['form_admin_email']) : ''; ?>" required>
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
                            
                            <h4 class="mb-3 mt-4">SMTP Server</h4>
                            <div class="mb-3">
                                <label class="form-label">SMTP Host</label>
                                <input type="text" name="smtp_host" class="form-control" placeholder="z.B. smtp.example.com" value="<?php echo isset($_SESSION['form_smtp_host']) ? htmlspecialchars($_SESSION['form_smtp_host']) : ''; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">SMTP Port</label>
                                <input type="number" name="smtp_port" class="form-control" value="<?php echo isset($_SESSION['form_smtp_port']) ? htmlspecialchars($_SESSION['form_smtp_port']) : '587'; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">SMTP Benutzername</label>
                                <input type="text" name="smtp_user" class="form-control" value="<?php echo isset($_SESSION['form_smtp_user']) ? htmlspecialchars($_SESSION['form_smtp_user']) : ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">SMTP Passwort</label>
                                <input type="password" name="smtp_pass" class="form-control" value="<?php echo isset($_SESSION['form_smtp_pass']) ? htmlspecialchars($_SESSION['form_smtp_pass']) : ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Verschlüsselung</label>
                                <select name="smtp_secure" class="form-select" required>
                                    <option value="tls" <?php echo (isset($_SESSION['form_smtp_secure']) && $_SESSION['form_smtp_secure'] == 'tls') ? 'selected' : ''; ?>>TLS (Port 587)</option>
                                    <option value="ssl" <?php echo (isset($_SESSION['form_smtp_secure']) && $_SESSION['form_smtp_secure'] == 'ssl') ? 'selected' : ''; ?>>SSL (Port 465)</option>
                                    <option value="none" <?php echo (isset($_SESSION['form_smtp_secure']) && $_SESSION['form_smtp_secure'] == 'none') ? 'selected' : ''; ?>>Keine (Port 25)</option>
                                </select>
                            </div>

                            <h4 class="mb-3 mt-4">Mail-Einstellungen</h4>
                            <div class="mb-3">
                                <label class="form-label">Admin E-Mail Adresse (für BCC Versand)</label>
                                <input type="email" name="admin_email_mail" class="form-control" value="<?php echo isset($_SESSION['form_admin_email_mail']) ? htmlspecialchars($_SESSION['form_admin_email_mail']) : ''; ?>" required>
                                <small class="form-text">Diese Adresse erhält automatisch eine Kopie aller Gast-Formulare</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Absendername</label>
                                <input type="text" name="sender_name" class="form-control" value="<?php echo isset($_SESSION['form_sender_name']) ? htmlspecialchars($_SESSION['form_sender_name']) : 'Menüwahl System'; ?>" required>
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
