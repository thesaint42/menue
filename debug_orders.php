<?php
/**
 * Debug Script für Bestellungen und Familienmitglieder
 * Aufruf: debug_orders.php?project_id=3
 */

require_once 'db.php';

$project_id = $_GET['project_id'] ?? 3;
$prefix = $config['database']['prefix'] ?? 'menu_';

echo "<h1>Debug: Projekt $project_id</h1>";
echo "<style>table { border-collapse: collapse; margin: 20px 0; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; } th { background: #f0f0f0; }</style>";

// 1. Gäste anzeigen
echo "<h2>1. Gäste (guests)</h2>";
$stmt = $pdo->prepare("SELECT * FROM {$prefix}guests WHERE project_id = ?");
$stmt->execute([$project_id]);
$guests = $stmt->fetchAll();

echo "<table><tr><th>ID</th><th>Name</th><th>Email</th><th>Type</th><th>Family Size</th></tr>";
foreach ($guests as $g) {
    echo "<tr>";
    echo "<td>{$g['id']}</td>";
    echo "<td>{$g['firstname']} {$g['lastname']}</td>";
    echo "<td>{$g['email']}</td>";
    echo "<td>{$g['guest_type']}</td>";
    echo "<td>{$g['family_size']}</td>";
    echo "</tr>";
}
echo "</table>";

// 2. Order Sessions anzeigen
echo "<h2>2. Bestellsessions (order_sessions)</h2>";
$stmt = $pdo->prepare("SELECT * FROM {$prefix}order_sessions WHERE project_id = ?");
$stmt->execute([$project_id]);
$order_sessions = $stmt->fetchAll();

echo "<table><tr><th>ID</th><th>Order ID</th><th>Email</th><th>Created</th></tr>";
foreach ($order_sessions as $os) {
    echo "<tr>";
    echo "<td>{$os['id']}</td>";
    echo "<td>{$os['order_id']}</td>";
    echo "<td>{$os['email']}</td>";
    echo "<td>{$os['created_at']}</td>";
    echo "</tr>";
}
echo "</table>";

// 3. Familienmitglieder anzeigen
echo "<h2>3. Familienmitglieder (family_members)</h2>";
foreach ($guests as $g) {
    if ($g['guest_type'] === 'family') {
        echo "<h3>Familie: {$g['firstname']} {$g['lastname']} (Guest ID: {$g['id']})</h3>";
        $stmt = $pdo->prepare("SELECT * FROM {$prefix}family_members WHERE guest_id = ?");
        $stmt->execute([$g['id']]);
        $members = $stmt->fetchAll();
        
        if (empty($members)) {
            echo "<p style='color: red;'>⚠️ KEINE Familienmitglieder gefunden!</p>";
        } else {
            echo "<table><tr><th>ID</th><th>Name</th><th>Type</th><th>Age</th><th>Hochstuhl</th></tr>";
            foreach ($members as $m) {
                echo "<tr>";
                echo "<td>{$m['id']}</td>";
                echo "<td>{$m['name']}</td>";
                echo "<td>{$m['member_type']}</td>";
                echo "<td>{$m['child_age']}</td>";
                echo "<td>" . ($m['highchair_needed'] ? '🪑' : '–') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }
}

// 4. Orders pro Bestellung anzeigen
echo "<h2>4. Bestellungen (orders) - gruppiert nach order_id</h2>";
foreach ($order_sessions as $os) {
    echo "<h3>Bestellung: {$os['order_id']}</h3>";
    $stmt = $pdo->prepare("
        SELECT o.*, d.name as dish_name, mc.name as category_name
        FROM {$prefix}orders o
        JOIN {$prefix}dishes d ON o.dish_id = d.id
        JOIN {$prefix}menu_categories mc ON d.category_id = mc.id
        WHERE o.order_id = ?
        ORDER BY o.person_id, mc.sort_order
    ");
    $stmt->execute([$os['order_id']]);
    $orders = $stmt->fetchAll();
    
    if (empty($orders)) {
        echo "<p style='color: orange;'>⚠️ Keine Gerichte bestellt</p>";
    } else {
        echo "<table><tr><th>Person ID</th><th>Kategorie</th><th>Gericht</th></tr>";
        $current_person = null;
        foreach ($orders as $o) {
            if ($current_person !== $o['person_id']) {
                $current_person = $o['person_id'];
                echo "<tr style='background: #f9f9f9; font-weight: bold;'>";
                echo "<td colspan='3'>👤 Person {$o['person_id']}</td>";
                echo "</tr>";
            }
            echo "<tr>";
            echo "<td>{$o['person_id']}</td>";
            echo "<td>{$o['category_name']}</td>";
            echo "<td>{$o['dish_name']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}

echo "<hr><p><em>Generated: " . date('Y-m-d H:i:s') . "</em></p>";
