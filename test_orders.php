<?php
require_once 'db.php';
require_once 'script/auth.php';

checkLogin();

$prefix = $config['database']['prefix'] ?? 'menu_';
$project_id = isset($_GET['project']) ? (int)$_GET['project'] : 1;

echo "Project ID: $project_id\n";
echo "Prefix: $prefix\n\n";

// Check if tables exist
$stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
$stmt->execute(["{$prefix}order_sessions"]);
$has_sessions = $stmt->fetchColumn() > 0;
echo "order_sessions exists: " . ($has_sessions ? "YES" : "NO") . "\n";

$stmt->execute(["{$prefix}order_people"]);
$has_people = $stmt->fetchColumn() > 0;
echo "order_people exists: " . ($has_people ? "YES" : "NO") . "\n";

$stmt->execute(["{$prefix}orders"]);
$has_orders = $stmt->fetchColumn() > 0;
echo "orders exists: " . ($has_orders ? "YES" : "NO") . "\n\n";

// Count records
if ($has_sessions) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$prefix}order_sessions WHERE project_id = ?");
    $stmt->execute([$project_id]);
    $count = $stmt->fetchColumn();
    echo "order_sessions für project $project_id: $count\n";
    
    if ($count > 0) {
        $stmt = $pdo->prepare("SELECT order_id, created_at, email FROM {$prefix}order_sessions WHERE project_id = ? LIMIT 3");
        $stmt->execute([$project_id]);
        $rows = $stmt->fetchAll();
        foreach ($rows as $row) {
            echo "  - Order: {$row['order_id']}, Email: {$row['email']}\n";
        }
    }
}

if ($has_people) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$prefix}order_people");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    echo "\norder_people total: $count\n";
}

if ($has_orders) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$prefix}orders");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    echo "orders total: $count\n";
}
?>
