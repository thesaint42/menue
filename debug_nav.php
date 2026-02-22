<?php
/**
 * debug_nav.php - Debug-Seite zum Testen der Navigation und Features
 */

require_once __DIR__ . '/db.php';

echo "<h1>Navigation Debug</h1>";
echo "<pre>";

// User info
echo "=== SESSION ===\n";
echo "user_id: " . ($_SESSION['user_id'] ?? 'not set') . "\n";
echo "user_name: " . ($_SESSION['user_name'] ?? 'not set') . "\n";
echo "role_id: " . ($_SESSION['role_id'] ?? 'not set') . "\n";
echo "email: " . ($_SESSION['email'] ?? 'not set') . "\n";

echo "\n=== CONFIG ===\n";
echo "pdo: " . (isset($pdo) ? 'yes' : 'no') . "\n";
echo "prefix: " . ($prefix ?? 'not set') . "\n";
echo "config['database']['prefix']: " . ($config['database']['prefix'] ?? 'not set') . "\n";

// Test hasMenuAccess function
echo "\n=== hasMenuAccess TEST ===\n";
if (isset($pdo) && function_exists('hasMenuAccess')) {
    $features = [
        'dashboard',
        'menu_categories_read',
        'projects_read',
        'menus_read',
        'guests_read',
        'orders_read',
        'reporting',
        'users',
        'roles',
        'settings_mail',
        'update',
        'backup_create'
    ];
    
    foreach ($features as $feature) {
        $has_access = hasMenuAccess($pdo, $feature, $prefix);
        echo "$feature: " . ($has_access ? 'YES' : 'NO') . "\n";
    }
} else {
    echo "ERROR: pdo or hasMenuAccess not available!\n";
}

// Database check
echo "\n=== DATABASE CHECK ===\n";
if (isset($pdo)) {
    try {
        // Check tables exist
        $stmt = $pdo->query("SHOW TABLES LIKE 'menu_%'");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "Tables found: " . count($tables) . "\n";
        foreach ($tables as $table) {
            echo "  - $table\n";
        }
        
        // Check user's role
        if (isset($_SESSION['role_id'])) {
            $stmt = $pdo->prepare("SELECT * FROM {$prefix}roles WHERE id = ?");
            $stmt->execute([$_SESSION['role_id']]);
            $role = $stmt->fetch();
            echo "\nUser's Role:\n";
            echo "  ID: " . $role['id'] . "\n";
            echo "  Name: " . $role['name'] . "\n";
            
            // Check role_menu_access entries
            $stmt = $pdo->prepare("SELECT * FROM {$prefix}role_menu_access WHERE role_id = ?");
            $stmt->execute([$_SESSION['role_id']]);
            $menu_access = $stmt->fetchAll();
            echo "\nRole Menu Access Entries: " . count($menu_access) . "\n";
            foreach ($menu_access as $entry) {
                echo "  - " . $entry['menu_key'] . ": " . ($entry['visible'] ? 'visible' : 'hidden') . "\n";
            }
        }
    } catch (Exception $e) {
        echo "Database Error: " . $e->getMessage() . "\n";
    }
}

echo "</pre>";
?>
