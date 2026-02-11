<?php
$host = "wp243.webpack.hosteurope.de";
$db = "db1038982-medea";
$user = "db1038982-medea";
$pass = "jetrEh-1dyqza-cahhof";

$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);

echo "=== Order Sessions (newest 5) ===\n";
$stmt = $pdo->query("SELECT id, order_id, email FROM menu_order_sessions WHERE project_id = 3 ORDER BY created_at DESC LIMIT 5");
foreach ($stmt->fetchAll() as $row) {
    echo "ID: {$row['id']}, Order: {$row['order_id']}, Email: {$row['email']}\n";
}

echo "\n=== Guests ===\n";
$stmt = $pdo->query("SELECT id, firstname, lastname, email FROM menu_guests WHERE project_id = 3");
foreach ($stmt->fetchAll() as $row) {
    echo "ID: {$row['id']}, Name: {$row['firstname']} {$row['lastname']}, Email: {$row['email']}\n";
}

echo "\n=== Family Members (newest 10) ===\n";
$stmt = $pdo->query("SELECT id, guest_id, name FROM menu_family_members ORDER BY id DESC LIMIT 10");
foreach ($stmt->fetchAll() as $row) {
    echo "ID: {$row['id']}, Guest_ID: {$row['guest_id']}, Name: {$row['name']}\n";
}

echo "\n=== Person Count per Order_ID ===\n";
$stmt = $pdo->query("SELECT order_id, COUNT(DISTINCT person_id) as persons FROM menu_orders GROUP BY order_id ORDER BY order_id DESC LIMIT 8");
foreach ($stmt->fetchAll() as $row) {
    echo "Order: {$row['order_id']}, Persons: {$row['persons']}\n";
}
?>
