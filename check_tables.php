<?php
$config = yaml_parse_file(__DIR__ . '/script/config.yaml');
$db = $config['database'];
$dsn = "mysql:host={$db['host']};dbname={$db['db_name']};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $db['user'], $db['pass']);
    $prefix = $db['prefix'];
    
    // Check family_members table
    echo "=== family_members ===\n";
    try {
        $tables = $pdo->query("SHOW TABLES LIKE '{$prefix}family_members'")->fetchAll();
        if (empty($tables)) {
            echo "NOT FOUND\n\n";
        } else {
            echo "EXISTS\n";
            $cols = $pdo->query("DESCRIBE `{$prefix}family_members`")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cols as $col) {
                echo "  {$col['Field']} ({$col['Type']})\n";
            }
            echo "\n";
        }
    } catch (Exception $e) {
        echo "Error checking table: " . $e->getMessage() . "\n\n";
    }
    
    // Check orders table
    echo "=== orders ===\n";
    $cols = $pdo->query("DESCRIBE `{$prefix}orders`")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $col) {
        echo "  {$col['Field']} ({$col['Type']})\n";
    }
    echo "\n";
    
    // Check guests table
    echo "=== guests ===\n";
    $cols = $pdo->query("DESCRIBE `{$prefix}guests`")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $col) {
        echo "  {$col['Field']} ({$col['Type']})\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
