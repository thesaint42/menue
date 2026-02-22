<?php
/**
 * Temporary script to add features to roles
 */

require_once 'db.php';
require_once 'script/auth.php';

$prefix = $config['database']['prefix'];

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

echo "✅ Projektadmin Features aktualisiert! {$added} neue Features hinzugefügt.\n";

// Auch Reporter (ID 3) Features setzen
$reporter_features = ['reporting'];
$stmt = $pdo->prepare("INSERT IGNORE INTO {$prefix}role_menu_access (role_id, menu_key, visible) VALUES (?, ?, 1)");
foreach ($reporter_features as $feature) {
    $stmt->execute([3, $feature]);
}
echo "✅ Reporter Features aktualisiert!\n";

// Verify
$stmt = $pdo->prepare("SELECT menu_key FROM {$prefix}role_menu_access WHERE role_id = 2 ORDER BY menu_key");
$stmt->execute();
$results = $stmt->fetchAll();
echo "\nProjektadmin Features in DB:\n";
foreach ($results as $row) {
    echo "  - " . $row['menu_key'] . "\n";
}
?>
