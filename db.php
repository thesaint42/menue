<?php
/**
 * db.php - Zentrale Datenbankverbindung und Initialisierung
 * Konfiguration wird aus script/config.yaml geladen
 */

// Session starten für Login-Prüfung
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fehlerberichterstattung (im Live-Betrieb auf 0 setzen)
error_reporting(E_ALL);
ini_set('display_errors', 1);

$config = null;
$pdo = null;

// 1. Prüfen, ob Konfiguration existiert
$configPath = __DIR__ . '/script/config.yaml';

if (file_exists($configPath)) {
    // Einfaches Parsing der YAML-Struktur
    $content = file_get_contents($configPath);
    
    // Wenn config.yaml leer ist, zur Installation umleiten
    if (empty(trim($content))) {
        if (basename($_SERVER['PHP_SELF']) !== 'install.php') {
            $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
            $base = ($base === '/') ? '' : $base;
            header("Location: {$base}/install.php?error=config_empty");
            exit;
        }
    }
    
    $config = ['database' => [], 'mail' => [], 'system' => []];
    
    $lines = explode("\n", $content);
    $currentSection = null;
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Leere Zeilen und Kommentare ignorieren
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        
        // Sektion erkennen
        if (preg_match('/^(\w+):$/', $line, $matches)) {
            $currentSection = $matches[1];
            continue;
        }
        
        // Key-Value Paare parsen
        if (strpos($line, ':') !== false && $currentSection) {
            list($key, $value) = explode(':', $line, 2);
            $key = trim($key);
            $value = stripslashes(trim($value, " \"\n\r"));
            
            if ($key && $value) {
                $config[$currentSection][$key] = $value;
            }
        }
    }

    // 2. Datenbankverbindung aufbauen (Nur wenn Keys existieren)
    if (isset($config['database']['host'], $config['database']['db_name'], $config['database']['user'])) {
        try {
            $dsn = "mysql:host=" . $config['database']['host'] . 
                   ";dbname=" . $config['database']['db_name'] . 
                   ";charset=utf8mb4";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];

            $pdo = new PDO($dsn, $config['database']['user'], $config['database']['pass'] ?? '', $options);

        } catch (\PDOException $e) {
            // Während der Installation unterdrücken wir den Fehler
                if (basename($_SERVER['PHP_SELF']) !== 'install.php') {
                    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
                    $base = ($base === '/') ? '' : $base;
                    header("Location: {$base}/install.php?error=db_connection_failed");
                    exit;
                }
        }
    }
} else {
    // config.yaml existiert nicht - Installation erforderlich
    if (basename($_SERVER['PHP_SELF']) !== 'install.php') {
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        $base = ($base === '/') ? '' : $base;
        header("Location: {$base}/install.php?error=not_installed");
        exit;
    }
}

/**
 * Prüft, ob ein Benutzer eingeloggt ist
 */
function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: /admin/login.php");
        exit;
    }
}

/**
 * Prüft, ob der Benutzer Admin-Rechte hat
 */
function checkAdmin() {
    if (!isset($_SESSION['role_id']) || !in_array($_SESSION['role_id'], [1])) {
        die("Zugriff verweigert.");
    }
}

/**
 * Log-Eintrag erstellen
 */
function logAction($pdo, $prefix, $action, $details = '') {
    $user_id = $_SESSION['user_id'] ?? null;
    $stmt = $pdo->prepare("INSERT INTO {$prefix}logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $action, $details, $_SERVER['REMOTE_ADDR']]);
}
