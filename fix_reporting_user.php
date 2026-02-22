<?php
/**
 * fix_reporting_user.php - Fügt fehlende Features zum Reporting User hinzu
 */

require_once 'db.php';

$prefix = $config['database']['prefix'] ?? 'menu_';

try {
    echo "🔧 Füge Features zum Reporting User hinzu...\n\n";
    
    // Prüfe ob Reporting User (Role 3) existiert
    $stmt = $pdo->prepare("SELECT id, name FROM {$prefix}roles WHERE id = 3");
    $stmt->execute();
    $role = $stmt->fetch();
    
    if (!$role) {
        echo "❌ Fehler: Reporting User (Role 3) nicht gefunden!\n";
        exit(1);
    }
    
    echo "✓ Reporting User gefunden: {$role['name']}\n\n";
    
    // Füge dashboard Feature hinzu
    echo "Schritt 1: Dashboard Feature...\n";
    $stmt = $pdo->prepare("SELECT id FROM {$prefix}role_menu_access WHERE role_id = 3 AND menu_key = 'dashboard'");
    $stmt->execute();
    if ($stmt->rowCount() === 0) {
        $ins = $pdo->prepare("INSERT INTO {$prefix}role_menu_access (role_id, menu_key, visible) VALUES (3, 'dashboard', 1)");
        $ins->execute();
        echo "  ✓ Dashboard Feature hinzugefügt\n";
    } else {
        echo "  ℹ️  Dashboard Feature bereits vorhanden\n";
    }
    
    // Füge projects_read Feature hinzu
    echo "Schritt 2: projects_read Feature...\n";
    $stmt = $pdo->prepare("SELECT id FROM {$prefix}role_menu_access WHERE role_id = 3 AND menu_key = 'projects_read'");
    $stmt->execute();
    if ($stmt->rowCount() === 0) {
        $ins = $pdo->prepare("INSERT INTO {$prefix}role_menu_access (role_id, menu_key, visible) VALUES (3, 'projects_read', 1)");
        $ins->execute();
        echo "  ✓ projects_read Feature hinzugefügt\n";
    } else {
        echo "  ℹ️  projects_read Feature bereits vorhanden\n";
    }
    
    // Prüfe reporting Feature
    echo "Schritt 3: reporting Feature...\n";
    $stmt = $pdo->prepare("SELECT id FROM {$prefix}role_menu_access WHERE role_id = 3 AND menu_key = 'reporting'");
    $stmt->execute();
    if ($stmt->rowCount() === 0) {
        $ins = $pdo->prepare("INSERT INTO {$prefix}role_menu_access (role_id, menu_key, visible) VALUES (3, 'reporting', 1)");
        $ins->execute();
        echo "  ✓ reporting Feature hinzugefügt\n";
    } else {
        echo "  ℹ️  reporting Feature bereits vorhanden\n";
    }
    
    // Zeige alle Features des Reporting Users
    echo "\n📋 Alle Features des Reporting Users:\n";
    $stmt = $pdo->prepare("SELECT menu_key, visible FROM {$prefix}role_menu_access WHERE role_id = 3 ORDER BY menu_key");
    $stmt->execute();
    $features = $stmt->fetchAll();
    
    if (empty($features)) {
        echo "  ⚠️  Keine Features gefunden!\n";
    } else {
        foreach ($features as $f) {
            $status = $f['visible'] ? '✓' : '✗';
            echo "  $status {$f['menu_key']}\n";
        }
    }
    
    // Zeige zugewiesene Projekte für Reporting User
    echo "\n📊 Prüfe zugewiesene Projekte für Reporting User...\n";
    $stmt = $pdo->query("SELECT u.id, u.email, u.firstname, u.lastname FROM {$prefix}users u WHERE u.role_id = 3");
    $reporting_users = $stmt->fetchAll();
    
    if (empty($reporting_users)) {
        echo "  ℹ️  Keine Reporting User gefunden\n";
    } else {
        foreach ($reporting_users as $user) {
            echo "\n  User: {$user['firstname']} {$user['lastname']} ({$user['email']})\n";
            
            $stmt = $pdo->prepare("SELECT p.id, p.name FROM {$prefix}user_projects up 
                                   JOIN {$prefix}projects p ON up.project_id = p.id 
                                   WHERE up.user_id = ?");
            $stmt->execute([$user['id']]);
            $projects = $stmt->fetchAll();
            
            if (empty($projects)) {
                echo "    ⚠️  Keine Projekte zugewiesen!\n";
            } else {
                echo "    Zugewiesene Projekte:\n";
                foreach ($projects as $p) {
                    echo "      - [{$p['id']}] {$p['name']}\n";
                }
            }
        }
    }
    
    echo "\n✅ Fertig!\n";
    
} catch (Exception $e) {
    echo "❌ Fehler: " . $e->getMessage() . "\n";
    exit(1);
}
?>
