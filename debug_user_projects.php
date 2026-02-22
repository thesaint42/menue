<?php
/**
 * debug_user_projects.php - Debug User Projects
 */

require_once 'db.php';
require_once 'script/auth.php';

checkLogin();

$prefix = $config['database']['prefix'] ?? 'menu_';
$user_id = getUserId();
$role_id = getUserRole();

echo "<h1>Debug User Projects</h1>";
echo "<p>User ID: $user_id</p>";
echo "<p>Role ID: $role_id</p>";

// Prüfe user_projects Einträge
echo "<h2>user_projects Einträge:</h2>";
$stmt = $pdo->prepare("SELECT * FROM {$prefix}user_projects WHERE user_id = ?");
$stmt->execute([$user_id]);
$user_projects_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($user_projects_rows);
echo "</pre>";

// Prüfe getUserProjects()
echo "<h2>getUserProjects() Ergebnis:</h2>";
$projects = getUserProjects($pdo, $prefix);
echo "<pre>";
print_r($projects);
echo "</pre>";

// Prüfe role_menu_access
echo "<h2>role_menu_access Einträge für Role $role_id:</h2>";
$stmt = $pdo->prepare("SELECT * FROM {$prefix}role_menu_access WHERE role_id = ?");
$stmt->execute([$role_id]);
$menu_access_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($menu_access_rows);
echo "</pre>";

// Prüfe hasMenuAccess für projects_read
echo "<h2>hasMenuAccess Tests:</h2>";
echo "projects_read: " . (hasMenuAccess($pdo, 'projects_read', $prefix) ? 'JA' : 'NEIN') . "<br>";
echo "projects_write: " . (hasMenuAccess($pdo, 'projects_write', $prefix) ? 'JA' : 'NEIN') . "<br>";
echo "dashboard: " . (hasMenuAccess($pdo, 'dashboard', $prefix) ? 'JA' : 'NEIN') . "<br>";
echo "reporting: " . (hasMenuAccess($pdo, 'reporting', $prefix) ? 'JA' : 'NEIN') . "<br>";

// Alle aktiven Projekte
echo "<h2>Alle aktiven Projekte:</h2>";
$stmt = $pdo->query("SELECT * FROM {$prefix}projects WHERE is_active = 1 ORDER BY name");
$all_projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($all_projects);
echo "</pre>";
?>
