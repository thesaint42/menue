<?php
/**
 * migrate.php - Datenbankmigrationen für bestehende Installationen
 * Führt strukturelle Änderungen durch ohne Daten zu löschen
 */

@session_start();

// Error Reporting für Debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db.php';
require_once 'script/auth.php';
// Lade phone helper robust (falls auf dem Server noch nicht vorhanden)
$phone_helper = __DIR__ . '/script/phone.php';
if (file_exists($phone_helper)) {
    require_once $phone_helper;
} else {
    function normalize_phone_e164($rawNumber, $defaultCountry = 'DE') {
        $s = trim((string)$rawNumber);
        if ($s === '') return '';
        $clean = preg_replace('/[\s\.\-\(\)\\\/]+/', '', $s);
        if (strpos($clean, '00') === 0) { $clean = '+' . substr($clean, 2); }
        if (preg_match('/^\+\d{7,15}$/', $clean)) return $clean;
        $digits = preg_replace('/\D+/', '', $clean);
        if ($digits && strlen($digits) >= 7 && strlen($digits) <= 15) return '+' . $digits;
        return $clean;
    }
    function is_valid_e164($number) {
        return is_string($number) && preg_match('/^\+\d{7,15}$/', $number);
    }
}

// Nur Admins erlauben
if (!isset($_SESSION['user_id'])) {
    die("Sie müssen angemeldet sein, um Migrationen durchzuführen.");
}

checkLogin();
checkAdmin();

$prefix = $config['database']['prefix'] ?? 'menu_';
$message = "";
$messageType = "info";

// Liste der verfügbaren Migrationen
$migrations = [
    'add_family_members_table' => [
        'name' => 'Familienmitglieder-Tabelle hinzufügen',
        'description' => 'Erstellt die family_members Tabelle für detaillierte Gast-Informationen',
        'version' => '2.1.0',
        'up' => function($pdo, $prefix) {
            try {
                // Prüfe ob Tabelle schon existiert
                $result = $pdo->query("SHOW TABLES LIKE '{$prefix}family_members'");
                if ($result && $result->rowCount() > 0) {
                    return true; // Tabelle existiert bereits
                }
                
                $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}family_members` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `guest_id` INT NOT NULL,
                    `name` VARCHAR(100) NOT NULL,
                    `member_type` ENUM('adult', 'child') DEFAULT 'adult',
                    `child_age` INT,
                    `highchair_needed` TINYINT(1) DEFAULT 0,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (`guest_id`) REFERENCES `{$prefix}guests`(`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                return true;
            } catch (Exception $e) {
                throw new Exception("Fehler beim Erstellen family_members Tabelle: " . $e->getMessage());
            }
        }
    ],
    'normalize_phones_e164' => [
        'name' => 'Telefonnummern nach E.164 normalisieren',
        'description' => 'Versucht bestehende Telefonnummern in projects.contact_phone und guests.phone in E.164 zu konvertieren (Backup empfohlen).',
        'version' => '2.3.0',
        'up' => function($pdo, $prefix) {
            try {
                // Erstelle Log-Verzeichnis falls nötig
                $logDir = __DIR__ . '/storage/logs';
                if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
                $logFile = $logDir . '/phone_migration_failures.txt';

                $failures = [];
                $updated = 0;

                // Projekte: contact_phone
                $stmt = $pdo->query("SELECT id, contact_phone FROM `{$prefix}projects`");
                $projects = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
                foreach ($projects as $p) {
                    $orig = $p['contact_phone'] ?? '';
                    if (trim($orig) === '') continue;
                    $normalized = normalize_phone_e164($orig, 'DE');
                    if ($normalized === false) {
                        $failures[] = "projects:{$p['id']}:$orig";
                        continue;
                    }
                    if ($normalized !== $orig) {
                        $u = $pdo->prepare("UPDATE `{$prefix}projects` SET contact_phone = ? WHERE id = ?");
                        $u->execute([$normalized, $p['id']]);
                        $updated++;
                    }
                }

                // Gäste: phone
                $stmt = $pdo->query("SELECT id, phone FROM `{$prefix}guests`");
                $guests = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
                foreach ($guests as $g) {
                    $orig = $g['phone'] ?? '';
                    if (trim($orig) === '') continue;
                    $normalized = normalize_phone_e164($orig, 'DE');
                    if ($normalized === false) {
                        $failures[] = "guests:{$g['id']}:$orig";
                        continue;
                    }
                    if ($normalized !== $orig) {
                        $u = $pdo->prepare("UPDATE `{$prefix}guests` SET phone = ? WHERE id = ?");
                        $u->execute([$normalized, $g['id']]);
                        $updated++;
                    }
                }

                // Schreibe Failures ins Log wenn vorhanden
                if (!empty($failures)) {
                    $text = "Phone migration failures - " . date('c') . "\n" . implode("\n", $failures) . "\n\n";
                    @file_put_contents($logFile, $text, FILE_APPEND | LOCK_EX);
                }

                // Schreibe Summary ins error_log
                @error_log("Phone migration: updated={$updated}, failures=" . count($failures));

                return true;
            } catch (Exception $e) {
                throw new Exception("Fehler bei Phone-Normalisierung: " . $e->getMessage());
            }
        }
    ],
    'add_access_pin_to_projects' => [
        'name' => 'Zugangs-PIN zu Projekten hinzufügen',
        'description' => 'Fügt access_pin Spalte zu projects Tabelle für PIN-basierte Projektaufrufe hinzu',
        'version' => '2.2.0',
        'up' => function($pdo, $prefix) {
            try {
                // Prüfe ob Spalte existiert
                $result = $pdo->query("SHOW COLUMNS FROM `{$prefix}projects`");
                $columns = $result->fetchAll(PDO::FETCH_COLUMN, 0);
                
                if (in_array('access_pin', $columns)) {
                    return true; // Spalte existiert bereits
                }
                
                $pdo->exec("ALTER TABLE `{$prefix}projects` ADD COLUMN `access_pin` VARCHAR(10) NOT NULL UNIQUE");
                
                // Generiere PINs für alle existierenden Projekte
                $stmt = $pdo->query("SELECT id FROM `{$prefix}projects` WHERE access_pin IS NULL OR access_pin = ''");
                if ($stmt) {
                    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($projects as $project) {
                        $pin = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                        // Stelle sicher dass PIN einzigartig ist
                        $attempts = 0;
                        while ($attempts < 100) {
                            $check = $pdo->query("SELECT id FROM `{$prefix}projects` WHERE access_pin = '" . addslashes($pin) . "'");
                            if ($check->rowCount() === 0) {
                                break;
                            }
                            $pin = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                            $attempts++;
                        }
                        
                        $update_stmt = $pdo->prepare("UPDATE `{$prefix}projects` SET access_pin = ? WHERE id = ?");
                        $update_stmt->execute([$pin, $project['id']]);
                    }
                }
                return true;
            } catch (Exception $e) {
                throw new Exception("Fehler bei access_pin Migration: " . $e->getMessage());
            }
        }
    ],
    'remove_age_group_from_guests' => [
        'name' => 'age_group Spalte entfernen',
        'description' => 'Entfernt die age_group und child_age Spalten aus der guests Tabelle',
        'version' => '2.1.0',
        'depends_on' => ['add_family_members_table'],
        'up' => function($pdo, $prefix) {
            try {
                // Prüfe ob Spalten existieren
                $result = $pdo->query("SHOW COLUMNS FROM `{$prefix}guests`");
                $columns = $result->fetchAll(PDO::FETCH_COLUMN, 0);
                
                if (in_array('age_group', $columns)) {
                    $pdo->exec("ALTER TABLE `{$prefix}guests` DROP COLUMN `age_group`");
                }
                if (in_array('child_age', $columns)) {
                    $pdo->exec("ALTER TABLE `{$prefix}guests` DROP COLUMN `child_age`");
                }
                return true;
            } catch (Exception $e) {
                throw new Exception("Fehler bei age_group Migration: " . $e->getMessage());
            }
        }
    ],
    'add_show_prices_to_projects' => [
        'name' => 'Preisanzeige-Flag zu Projekten hinzufügen',
        'description' => 'Fügt show_prices Spalte zu projects Tabelle hinzu (0=verborgen, 1=sichtbar)',
        'version' => '3.0.0',
        'up' => function($pdo, $prefix) {
            try {
                $result = $pdo->query("SHOW COLUMNS FROM `{$prefix}projects`");
                $columns = $result->fetchAll(PDO::FETCH_COLUMN, 0);
                if (!in_array('show_prices', $columns)) {
                    $pdo->exec("ALTER TABLE `{$prefix}projects` ADD COLUMN `show_prices` TINYINT(1) DEFAULT 0 AFTER `is_active`");
                }
                return true;
            } catch (Exception $e) {
                throw new Exception("Fehler bei show_prices Migration: " . $e->getMessage());
            }
        }
    ],
    'add_price_to_dishes' => [
        'name' => 'Preisfeld zu Gerichten hinzufügen',
        'description' => 'Fügt price Spalte zu dishes Tabelle hinzu (Brutto, 2 Dezimalstellen)',
        'version' => '3.0.0',
        'up' => function($pdo, $prefix) {
            // Prüfe ob Spalte bereits existiert
            try {
                $pdo->query("SELECT price FROM `{$prefix}dishes` LIMIT 1");
                // Spalte existiert bereits - kein Fehler
                return true;
            } catch (Exception $e) {
                // Spalte existiert nicht - füge sie hinzu
                try {
                    $stmt = $pdo->prepare("ALTER TABLE `{$prefix}dishes` ADD COLUMN `price` DECIMAL(8,2) DEFAULT NULL AFTER `description`");
                    $stmt->execute();
                    return true;
                } catch (Exception $e2) {
                    throw new Exception("Fehler beim Hinzufügen der price-Spalte: " . $e2->getMessage());
                }
            }
        }
    ],
    'create_order_sessions_table' => [
        'name' => 'Order-Sessions Tabelle erstellen',
        'description' => 'Erstellt order_sessions Tabelle für eindeutige Bestellvorgänge mit order_id',
        'version' => '3.0.0',
        'up' => function($pdo, $prefix) {
            // Prüfe ob Tabelle bereits existiert
            try {
                $pdo->query("SELECT 1 FROM `{$prefix}order_sessions` LIMIT 1");
                // Tabelle existiert bereits
                return true;
            } catch (Exception $e) {
                // Tabelle existiert nicht - erstelle sie
                $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}order_sessions` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `order_id` CHAR(36) NOT NULL,
                    `project_id` INT NOT NULL,
                    `email` VARCHAR(150) NOT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY `unique_order_id` (`order_id`),
                    FOREIGN KEY (`project_id`) REFERENCES `{$prefix}projects`(`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                try {
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute();
                    return true;
                } catch (Exception $e2) {
                    throw new Exception("Fehler beim Erstellen der order_sessions-Tabelle: " . $e2->getMessage());
                }
            }
        }
    ],
    'migrate_orders_to_person_based' => [
        'name' => 'Orders auf personenspezifische Struktur migrieren',
        'description' => 'Strukturiert orders Tabelle um: order_id, person_id, category_id statt guest_id+quantity',
        'version' => '3.0.0',
        'depends_on' => ['create_order_sessions_table'],
        'up' => function($pdo, $prefix) {
            // Prüfe aktuelle Struktur
            try {
                $pdo->query("SELECT order_id FROM `{$prefix}orders` LIMIT 1");
                // Neue Struktur existiert bereits
                return true;
            } catch (Exception $e) {
                // Alte Struktur - migriere sie
                try {
                    // Backup der alten orders Tabelle erstellen
                    $pdo->query("CREATE TABLE IF NOT EXISTS `{$prefix}orders_backup_v2` LIKE `{$prefix}orders`");
                    $pdo->query("INSERT IGNORE INTO `{$prefix}orders_backup_v2` SELECT * FROM `{$prefix}orders`");
                    
                    // Alte Tabelle löschen
                    $pdo->query("DROP TABLE `{$prefix}orders`");
                    
                    // Neue Struktur erstellen
                    $pdo->query("CREATE TABLE `{$prefix}orders` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `order_id` CHAR(36) NOT NULL,
                        `person_id` INT NOT NULL,
                        `dish_id` INT NOT NULL,
                        `category_id` INT NOT NULL,
                        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (`dish_id`) REFERENCES `{$prefix}dishes`(`id`) ON DELETE RESTRICT,
                        UNIQUE KEY `unique_order` (`order_id`, `person_id`, `category_id`),
                        INDEX `idx_order_id` (`order_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                    
                    return true;
                } catch (Exception $e2) {
                    throw new Exception("Fehler bei orders Migration: " . $e2->getMessage());
                }
            }
        }
    ]
];

// Migration-Tracking Tabelle erstellen falls nicht vorhanden
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}migrations` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `migration` VARCHAR(255) NOT NULL UNIQUE,
        `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    $message = "Fehler beim Erstellen der Migrations-Tabelle: " . $e->getMessage();
    $messageType = "danger";
}

// Get bereits ausgeführte Migrationen
$executed_migrations = [];
try {
    $stmt = $pdo->query("SELECT migration FROM `{$prefix}migrations`");
    if ($stmt) {
        $executed_migrations = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    }
} catch (Exception $e) {
    // Tabelle existiert noch nicht - keine Migrationen ausgeführt
    $executed_migrations = [];
}

// Reset Migrationen bei Bedarf
if (isset($_POST['reset_migrations'])) {
    try {
        $pdo->exec("DROP TABLE IF EXISTS `{$prefix}migrations`");
        $pdo->exec("CREATE TABLE `{$prefix}migrations` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `migration` VARCHAR(255) NOT NULL UNIQUE,
            `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $executed_migrations = [];
        $message = "✓ Migrations-Historie gelöscht. Sie können Migrationen jetzt neu durchführen.";
        $messageType = "success";
    } catch (Exception $e) {
        $message = "Fehler beim Reset: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Migration durchführen wenn angefordert
if (isset($_POST['run_migration']) && isset($_POST['migration_key'])) {
    $migration_key = $_POST['migration_key'];
    
    if (!isset($migrations[$migration_key])) {
        $message = "❌ Migration nicht gefunden!";
        $messageType = "danger";
    } elseif (in_array($migration_key, $executed_migrations)) {
        $message = "⚠️ Diese Migration wurde bereits ausgeführt.";
        $messageType = "warning";
    } else {
        $transaction_active = false;
        
        try {
            $migration = $migrations[$migration_key];
            
            // Überprüfe Abhängigkeiten
            if (isset($migration['depends_on'])) {
                $missing_deps = array_diff($migration['depends_on'], $executed_migrations);
                if (!empty($missing_deps)) {
                    $message = "❌ Diese Migration hat noch ausstehende Abhängigkeiten: " . implode(', ', $missing_deps);
                    $messageType = "danger";
                } else {
                    // Führe Migration aus mit Transaction
                    if ($pdo) {
                        $pdo->beginTransaction();
                        $transaction_active = true;
                        
                        // Speichere Migrations-Eintrag ERST nach erfolgreicher Migration
                        call_user_func($migration['up'], $pdo, $prefix);
                        
                        // Nur wenn Migration erfolgreich war, speichere den Eintrag
                        $stmt = $pdo->prepare("INSERT INTO `{$prefix}migrations` (migration) VALUES (?)");
                        $stmt->execute([$migration_key]);
                        
                        $pdo->commit();
                        $transaction_active = false;
                        
                        $message = "✅ Migration erfolgreich ausgeführt!";
                        $messageType = "success";
                        $executed_migrations[] = $migration_key;
                    }
                }
            } else {
                // Keine Abhängigkeiten - führe aus
                if ($pdo) {
                    $pdo->beginTransaction();
                    $transaction_active = true;
                    
                    // Führe die Migration aus
                    call_user_func($migration['up'], $pdo, $prefix);
                    
                    // Nur wenn Migration erfolgreich war, speichere den Eintrag
                    $stmt = $pdo->prepare("INSERT INTO `{$prefix}migrations` (migration) VALUES (?)");
                    $stmt->execute([$migration_key]);
                    
                    $pdo->commit();
                    $transaction_active = false;
                    
                    $message = "✅ Migration erfolgreich ausgeführt!";
                    $messageType = "success";
                    $executed_migrations[] = $migration_key;
                }
            }
        } catch (Exception $e) {
            // Nur rollback wenn Transaction noch aktiv ist
            if ($transaction_active && $pdo) {
                try {
                    $pdo->rollBack();
                } catch (Exception $rollback_error) {
                    // Rollback selbst fehlgeschlagen, aber wir loggen es
                    @error_log("Rollback Error: " . $rollback_error->getMessage());
                }
            }
            
            $message = "❌ Fehler bei Migration: " . htmlspecialchars($e->getMessage());
            $messageType = "danger";
            
            // Logge auch in Datei für Debugging
            @error_log("Migration Error [$migration_key]: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datenbankmigrationen - Event Menue Order System (EMOS)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include 'nav/top_nav.php'; ?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <h2 class="mb-4">Datenbankmigrationen</h2>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow">
                <div class="card-body p-4">
                    <p class="text-muted mb-4">Hier können Sie strukturelle Datenbankänderungen durchführen, ohne bestehende Daten zu löschen.</p>

                    <!-- Debug Info -->
                    <div class="alert alert-secondary mb-4">
                        <strong>📋 Status:</strong> 
                        <br>Anzahl ausgeführter Migrationen: <strong><?php echo count($executed_migrations); ?></strong>
                        <?php if (!empty($executed_migrations)): ?>
                            <br>Ausgeführte Migrationen: <code><?php echo implode(', ', $executed_migrations); ?></code>
                        <?php endif; ?>
                    </div>

                    <?php foreach ($migrations as $key => $migration): ?>
                        <div class="card bg-dark border-secondary mb-3">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h5 class="card-title mb-1"><?php echo htmlspecialchars($migration['name']); ?></h5>
                                        <p class="text-muted small mb-0"><?php echo htmlspecialchars($migration['description']); ?></p>
                                        <small class="text-secondary">Version: <?php echo $migration['version']; ?></small>
                                        
                                        <?php if (isset($migration['depends_on'])): ?>
                                            <br><small class="text-warning">
                                                Abhängig von: <?php echo implode(', ', array_map(fn($d) => $migrations[$d]['name'] ?? $d, $migration['depends_on'])); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-auto">
                                        <?php if (in_array($key, $executed_migrations)): ?>
                                            <span class="badge bg-success">✓ Ausgeführt</span>
                                        <?php else: ?>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="migration_key" value="<?php echo $key; ?>">
                                                <button type="submit" name="run_migration" class="btn btn-sm btn-primary" onclick="return confirm('Diese Migration wirklich durchführen?')">
                                                    Ausführen
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="alert alert-info mt-4">
                        <strong>Hinweis:</strong> Migrationen können nicht rückgängig gemacht werden. Machen Sie einen Backup Ihrer Datenbank, bevor Sie Migrationen durchführen.
                    </div>

                    <hr class="my-4">

                    <div class="alert alert-danger">
                        <strong>⚠️ Probleme mit Migrationen?</strong>
                        <br>Falls Migrationen als "Ausgeführt" angezeigt werden, obwohl Sie fehlgeschlagen sind, können Sie die Historie hier zurücksetzen:
                        <br><br>
                        <form method="post" style="display: inline;">
                            <button type="submit" name="reset_migrations" class="btn btn-danger btn-sm" onclick="return confirm('ACHTUNG: Dies setzt die Migrations-Historie zurück! Sind Sie sicher?')">
                                🔄 Migrations-Historie zurücksetzen
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
