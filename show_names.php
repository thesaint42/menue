<?php
$host = "wp243.webpack.hosteurope.de";
$db = "db1038982-medea";
$user = "db1038982-medea";
$pass = "jetrEh-1dyqza-cahhof";

$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);

// Zeige die neueste Familie
$stmt = $pdo->prepare("
    SELECT g.id, g.firstname, g.lastname, fm.id as member_id, fm.name
    FROM menu_guests g
    LEFT JOIN menu_family_members fm ON g.id = fm.guest_id
    WHERE g.project_id = 3
    ORDER BY g.id, fm.id
");
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($data as $row) {
    if ($row['member_id']) {
        echo "Member: {$row['name']}\n";
    } else {
        echo "Guest: {$row['firstname']} {$row['lastname']}\n";
    }
}
?>
