<?php
// tools/sim_delete.php
// Zeigt eine Übersicht der inaktiven Projekte und betroffenen Datensätze
require __DIR__ . '/../db.php';

$prefix = $config['database']['prefix'] ?? 'menu_';

function q($pdo, $sql, $params = []){
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$opts = [];
foreach ($argv as $a) {
    if (strpos($a, '--id=') === 0) $opts['id'] = (int)substr($a,5);
}

if (empty($opts['id'])) {
    echo "Inaktive Projekte und betroffene Datensätze:\n\n";
    $projects = q($pdo, "SELECT id, name, created_at FROM {$prefix}projects WHERE is_active = 0 ORDER BY created_at DESC");
    if (empty($projects)) {
        echo "Keine inaktiven Projekte gefunden.\n";
        exit(0);
    }
    foreach ($projects as $p) {
        $id = $p['id'];
        $guestCount = q($pdo, "SELECT COUNT(*) as c FROM {$prefix}guests WHERE project_id = ?", [$id])[0]['c'];
        $orderCount = q($pdo, "SELECT COUNT(o.id) as c FROM {$prefix}orders o JOIN {$prefix}guests g ON o.guest_id = g.id WHERE g.project_id = ?", [$id])[0]['c'];
        $dishCount = q($pdo, "SELECT COUNT(*) as c FROM {$prefix}dishes WHERE project_id = ?", [$id])[0]['c'];
        echo "ID: {$id} | {$p['name']} | Gäste: {$guestCount} | Bestellungen: {$orderCount} | Gerichte: {$dishCount}\n";
    }
    echo "\nUm Details für ein Projekt zu zeigen: php tools/sim_delete.php --id=ID\n";
    exit(0);
}

$id = (int)$opts['id'];
$proj = q($pdo, "SELECT * FROM {$prefix}projects WHERE id = ?", [$id]);
if (empty($proj)) { echo "Projekt nicht gefunden (ID: $id)\n"; exit(1); }
$proj = $proj[0];

echo "Projekt: {$proj['id']} - {$proj['name']} (aktiv: " . ($proj['is_active'] ? 'ja' : 'nein') . ")\n\n";

$guestRows = q($pdo, "SELECT id, firstname, lastname, email FROM {$prefix}guests WHERE project_id = ? ORDER BY created_at DESC", [$id]);
echo "Gäste (" . count($guestRows) . "):\n";
foreach ($guestRows as $g) {
    $orderCount = q($pdo, "SELECT COUNT(*) as c FROM {$prefix}orders WHERE guest_id = ?", [$g['id']])[0]['c'];
    echo " - {$g['id']}: {$g['firstname']} {$g['lastname']} ({$g['email']}) - Bestellungen: {$orderCount}\n";
}

$dishRows = q($pdo, "SELECT id, name FROM {$prefix}dishes WHERE project_id = ? ORDER BY id", [$id]);
echo "\nGerichte (" . count($dishRows) . "):\n";
foreach ($dishRows as $d) {
    echo " - {$d['id']}: {$d['name']}\n";
}

$orderTotal = q($pdo, "SELECT COUNT(o.id) as c FROM {$prefix}orders o JOIN {$prefix}guests g ON o.guest_id = g.id WHERE g.project_id = ?", [$id])[0]['c'];
echo "\nGesamt Bestellungen: {$orderTotal}\n";

echo "\nHinweis: Dies ist eine Sicherheitsabfrage. Kein Löschvorgang wurde ausgeführt.\n";

exit(0);

