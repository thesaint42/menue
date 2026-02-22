<?php
/**
 * One-time script to configure Reporter role (ID 3) as system role
 * Run this once on production after deployment
 */

require_once 'db.php';
require_once 'script/auth.php';

// Prüfe ob Benutzer eingeloggt ist
if (!isset($_SESSION['user_id'])) {
    die('Bitte erst einloggen!');
}

// Nur Admin (role_id = 1) darf dieses Script ausführen
if ($_SESSION['role_id'] !== 1) {
    die('Nur Systemadmin darf dieses Script ausführen!');
}

$prefix = $config['database']['prefix'];

try {
    // Prüfe ob Reporter-Rolle existiert
    $stmt = $pdo->prepare("SELECT id, name FROM {$prefix}roles WHERE id = 3");
    $stmt->execute();
    $reporter = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reporter) {
        echo "❌ Reporter-Rolle (ID 3) nicht gefunden!\n";
        echo "Bitte erst die Reporter-Rolle manuell anlegen.\n";
        exit(1);
    }
    
    echo "✅ Reporter-Rolle gefunden: {$reporter['name']}\n\n";
    
    // Setze Beschreibung
    $stmt = $pdo->prepare("UPDATE {$prefix}roles SET description = 'Systemrolle - kann nur Berichte einsehen' WHERE id = 3");
    $stmt->execute();
    echo "✅ Beschreibung aktualisiert\n";
    
    // Setze Reporting-Feature
    $stmt = $pdo->prepare("INSERT IGNORE INTO {$prefix}role_menu_access (role_id, menu_key, visible) VALUES (3, 'reporting', 1)");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        echo "✅ Reporting-Feature hinzugefügt\n";
    } else {
        echo "ℹ️  Reporting-Feature bereits vorhanden\n";
    }
    
    // Entferne project_admin Feature falls vorhanden
    $stmt = $pdo->prepare("DELETE FROM {$prefix}role_features WHERE role_id = 3 AND feature_name = 'project_admin'");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        echo "✅ project_admin Feature entfernt\n";
    }
    
    // Füge project_admin = 0 Eintrag hinzu
    $stmt = $pdo->prepare("INSERT IGNORE INTO {$prefix}role_features (role_id, feature_name, enabled) VALUES (3, 'project_admin', 0)");
    $stmt->execute();
    
    echo "\n";
    echo "🎉 Reporter-Rolle erfolgreich als Systemrolle konfiguriert!\n";
    echo "\n";
    echo "🔍 Aktuelle Features der Reporter-Rolle:\n";
    
    $stmt = $pdo->prepare("SELECT menu_key FROM {$prefix}role_menu_access WHERE role_id = 3 AND visible = 1 ORDER BY menu_key");
    $stmt->execute();
    $current_features = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($current_features)) {
        echo "  ℹ️  Keine Features aktiv\n";
    } else {
        foreach ($current_features as $feature) {
            echo "  ✓ {$feature}\n";
        }
    }
    
    echo "\n";
    echo "💡 Dieses Script kann jetzt gelöscht werden: update_reporter_role.php\n";
    
} catch (Exception $e) {
    echo "❌ Fehler: " . $e->getMessage() . "\n";
    exit(1);
}
