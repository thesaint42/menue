<?php
$host = "wp243.webpack.hosteurope.de";
$db = "db1038982-medea";
$user = "db1038982-medea";
$pass = "jetrEh-1dyqza-cahhof";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Guests for Project 3 ===\n";
    $stmt = $pdo->prepare("SELECT id, firstname, lastname, email FROM menu_guests WHERE project_id = 3");
    $stmt->execute();
    $guests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($guests as $g) {
        echo "ID: {$g['id']}, Name: '{$g['firstname']}' '{$g['lastname']}', Email: {$g['email']}\n";
    }
    
    echo "\n=== Family Members ===\n";
    $stmt = $pdo->prepare("SELECT id, guest_id, name FROM menu_family_members WHERE guest_id IN (SELECT id FROM menu_guests WHERE project_id = 3) ORDER BY guest_id, id");
    $stmt->execute();
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($members as $m) {
        echo "ID: {$m['id']}, Guest_ID: {$m['guest_id']}, Name: '{$m['name']}'\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
