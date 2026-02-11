<?php
$host = "wp243.webpack.hosteurope.de";
$db = "db1038982-medea";
$user = "db1038982-medea";
$pass = "jetrEh-1dyqza-cahhof";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Finde die neueste Order Session für diesen Gast
    $stmt = $pdo->prepare("
        SELECT os.email, COUNT(*) as order_count
        FROM menu_order_sessions os
        WHERE os.project_id = 3
        GROUP BY os.email
    ");
    $stmt->execute();
    $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "=== Correcting Guest Names ===\n\n";
    
    foreach ($emails as $row) {
        $email = $row['email'];
        echo "Processing email: $email ({$row['order_count']} orders)\n";
        
        // Finde den Gast
        $stmt = $pdo->prepare("SELECT * FROM menu_guests WHERE email = ? AND project_id = 3");
        $stmt->execute([$email]);
        $guest = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$guest) {
            echo "  - No guest found\n\n";
            continue;
        }
        
        echo "  - Current: firstname='{$guest['firstname']}' lastname='{$guest['lastname']}'\n";
        
        // Finde die neueste Order Session
        $stmt = $pdo->prepare("
            SELECT os.id, os.order_id, os.email, os.created_at
            FROM menu_order_sessions os
            WHERE os.email = ? AND os.project_id = 3
            ORDER BY os.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $latest = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($latest) {
            echo "  - Latest order: {$latest['order_id']} (created: {$latest['created_at']})\n";
            
            // Finde Personen in dieser Order
            $stmt = $pdo->prepare("
                SELECT DISTINCT o.person_id
                FROM menu_orders o
                WHERE o.order_id = ?
                ORDER BY o.person_id
            ");
            $stmt->execute([$latest['order_id']]);
            $persons = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "  - Persons in this order: " . count($persons) . "\n";
            foreach ($persons as $p) {
                echo "    - Person {$p['person_id']}\n";
            }
        }
        
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
?>
