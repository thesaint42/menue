<?php
/**
 * Einmaliges Script: Fügt menus_read Feature für Reporting User hinzu
 * Nach Ausführung kann diese Datei gelöscht werden
 */

require_once 'db.php';

$prefix = $config['database']['prefix'] ?? 'menu_';

try {
    // Prüfe ob menus_read für Role 3 bereits existiert
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$prefix}role_menu_access` WHERE role_id = 3 AND menu_key = 'menus_read'");
    $stmt->execute();
    
    if ($stmt->fetchColumn() === 0) {
        $ins = $pdo->prepare("INSERT INTO `{$prefix}role_menu_access` (role_id, menu_key, visible) VALUES (3, 'menus_read', 1)");
        $ins->execute();
        echo "✅ menus_read Feature für Reporting User erfolgreich hinzugefügt!<br>";
        echo "Sie können jetzt diese Datei löschen.";
    } else {
        echo "ℹ️ menus_read Feature existiert bereits für Reporting User.";
    }
} catch (Exception $e) {
    echo "❌ Fehler: " . htmlspecialchars($e->getMessage());
    exit(1);
}
