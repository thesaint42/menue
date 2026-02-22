<?php
/**
 * One-time script to add default features to Projektadmin role (ID 2)
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
    // Features für Projektadmin (ID 2) setzen
    $projektadmin_features = [
        'dashboard',
        'menu_categories_read',
        'menu_categories_write',
        'projects_read',
        'projects_write',
        'menus_read',
        'menus_write',
        'guests_read',
        'guests_write',
        'orders_read',
        'orders_write',
        'reporting'
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO {$prefix}role_menu_access (role_id, menu_key, visible) VALUES (?, ?, 1)");
    
    $added = 0;
    foreach ($projektadmin_features as $feature) {
        $stmt->execute([2, $feature]);
        if ($stmt->rowCount() > 0) {
            $added++;
        }
    }

    echo "✅ Projektadmin-Features erfolgreich aktualisiert!\n";
    echo "📊 {$added} neue Features hinzugefügt.\n";
    echo "\n";
    echo "🔍 Aktuelle Features der Projektadmin-Rolle:\n";
    
    $stmt = $pdo->prepare("SELECT menu_key FROM {$prefix}role_menu_access WHERE role_id = 2 AND visible = 1 ORDER BY menu_key");
    $stmt->execute();
    $current_features = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($current_features as $feature) {
        echo "  ✓ {$feature}\n";
    }
    
    echo "\n";
    echo "💡 Dieses Script kann jetzt gelöscht werden: update_projektadmin_features.php\n";
    
} catch (Exception $e) {
    echo "❌ Fehler: " . $e->getMessage() . "\n";
    exit(1);
}
