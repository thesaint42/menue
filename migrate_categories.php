<?php
/**
 * migrate_categories.php - Migration: Füge project_id zu menu_categories hinzu
 */

require_once 'db.php';

$prefix = $config['database']['prefix'] ?? 'menu_';

try {
    echo "🔧 Starte Migration: Kategorien projektspezifisch machen...\n\n";
    
    $pdo->beginTransaction();
    
    // 1. Prüfe, ob project_id bereits existiert
    $stmt = $pdo->query("SHOW COLUMNS FROM {$prefix}menu_categories");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('project_id', $columns)) {
        echo "✅ Schritt 1: Füge project_id Spalte hinzu...\n";
        $pdo->exec("ALTER TABLE {$prefix}menu_categories ADD COLUMN project_id INT NOT NULL DEFAULT 4 AFTER id");
        echo "   ✓ Spalte hinzugefügt und auf Projekt 4 gesetzt\n";
    } else {
        echo "ℹ️  Schritt 1: project_id Spalte existiert bereits\n";
    }
    
    // 2. Stelle sicher, dass alle Kategorien Projekt 4 zugeordnet sind
    echo "✅ Schritt 2: Ordne alle Kategorien Projekt 4 zu...\n";
    $pdo->exec("UPDATE {$prefix}menu_categories SET project_id = 4 WHERE project_id != 4 OR project_id IS NULL");
    $count = $pdo->query("SELECT COUNT(*) FROM {$prefix}menu_categories WHERE project_id = 4")->fetchColumn();
    echo "   ✓ $count Kategorien zugeordnet\n";
    
    // 3. Prüfe Foreign Key Constraint
    echo "✅ Schritt 3: Prüfe Foreign Key...\n";
    $constraints = $pdo->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = '{$prefix}menu_categories' AND COLUMN_NAME = 'project_id'")->fetchAll();
    
    if (empty($constraints)) {
        echo "   ✓ Füge Foreign Key hinzu...\n";
        try {
            $pdo->exec("ALTER TABLE {$prefix}menu_categories ADD CONSTRAINT fk_categories_project FOREIGN KEY (project_id) REFERENCES {$prefix}projects(id) ON DELETE CASCADE");
            echo "   ✓ Foreign Key erstellt\n";
        } catch (Exception $e) {
            echo "   ⚠️  Foreign Key konnte nicht erstellt werden: " . $e->getMessage() . "\n";
        }
    } else {
        echo "   ✓ Foreign Key existiert bereits\n";
    }
    
    $pdo->commit();
    
    echo "\n✅ Migration erfolgreich abgeschlossen!\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ Fehler: " . $e->getMessage() . "\n";
    exit(1);
}
?>
