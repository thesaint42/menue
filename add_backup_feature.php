<?php
/**
 * Temporary script to add backup_create feature to Projektadmin
 */

require_once 'db.php';
require_once 'script/auth.php';

$prefix = $config['database']['prefix'];

// Add backup_create to Projektadmin (ID 2)
$stmt = $pdo->prepare("INSERT IGNORE INTO {$prefix}role_menu_access (role_id, menu_key, visible) VALUES (?, ?, 1)");
$stmt->execute([2, 'backup_create']);
echo "✅ backup_create Feature zu Projektadmin hinzugefügt!\n";

// Verify
$stmt = $pdo->prepare("SELECT menu_key FROM {$prefix}role_menu_access WHERE role_id = 2 ORDER BY menu_key");
$stmt->execute();
$results = $stmt->fetchAll();
echo "\nProjektadmin Features in DB:\n";
foreach ($results as $row) {
    echo "  - " . $row['menu_key'] . "\n";
}
?>
