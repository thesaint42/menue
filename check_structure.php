<?php
$yaml = file_get_contents('script/config.yaml');
preg_match('/host:\s*"([^"]+)"/', $yaml, $h);
preg_match('/db_name:\s*"([^"]+)"/', $yaml, $d);
preg_match('/user:\s*"([^"]+)"/', $yaml, $u);
preg_match('/pass:\s*"([^"]+)"/', $yaml, $p);
preg_match('/prefix:\s*"([^"]+)"/', $yaml, $pr);

$dsn = "mysql:host={$h[1]};dbname={$d[1]};charset=utf8mb4";
$pdo = new PDO($dsn, $u[1], $p[1]);
$prefix = $pr[1];

foreach(['menu_categories', 'users', 'roles'] as $table) {
    echo "=== $table ===\n";
    try {
        $cols = $pdo->query("DESCRIBE `{$prefix}{$table}`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $col) {
            echo "  {$col['Field']} ({$col['Type']})\n";
        }
    } catch (Exception $e) {
        echo "NOT FOUND\n";
    }
    echo "\n";
}
?>
