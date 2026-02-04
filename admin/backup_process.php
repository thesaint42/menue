<?php
/**
 * admin/backup_process.php - AJAX-Endpoint für Backup-Erstellung mit Progress-Tracking
 */

@session_start();

require_once '../db.php';
require_once '../script/auth.php';

checkLogin();
checkAdmin();

$action = $_GET['action'] ?? 'start';
$session_id = session_id();

// Backup-Verzeichnis
$backup_dir = __DIR__ . '/../storage/backups';
if (!is_dir($backup_dir)) {
    @mkdir($backup_dir, 0755, true);
}

// Status-Datei für Fortschritt (mit MD5 für Sicherheit)
$status_file = sys_get_temp_dir() . "/backup_status_" . md5($session_id) . ".json";

header('Content-Type: application/json; charset=utf-8');

if ($action === 'start') {
    $backup_type = $_POST['backup_type'] ?? 'full';
    $timestamp = date('Y-m-d_H-i-s');
    
    // Initialisiere Status
    $status = [
        'status' => 'starting',
        'progress' => 0,
        'message' => 'Backup wird vorbereitet...',
        'details' => [],
        'start_time' => time(),
        'current_step' => 'Initialisierung',
        'timestamp' => $timestamp,
        'backup_type' => $backup_type
    ];
    
    file_put_contents($status_file, json_encode($status));
    
    echo json_encode(['success' => true, 'message' => 'Backup-Prozess gestartet']);
    exit;
    
} elseif ($action === 'execute') {
    $backup_type = $_POST['backup_type'] ?? 'full';
    $timestamp = date('Y-m-d_H-i-s');
    
    $status = [
        'status' => 'processing',
        'progress' => 0,
        'message' => 'Backup wird erstellt...',
        'details' => [],
        'start_time' => time(),
        'current_step' => 'Datenbank wird gesichert',
        'timestamp' => $timestamp,
        'backup_type' => $backup_type,
        'files_created' => []
    ];
    
    $prefix = $config['database']['prefix'] ?? 'menu_';
    
    try {
        // === DATENBANKBACKUP ===
        if (in_array($backup_type, ['database', 'full'])) {
            $status['current_step'] = '💾 Datenbank wird als SQL exportiert...';
            $status['progress'] = 15;
            file_put_contents($status_file, json_encode($status));
            
            $db_file = $backup_dir . '/db_backup_' . $timestamp . '.sql';
            
            // Exportiere Datenbank mit PHP (kein mysqldump nötig)
            try {
                $sql_dump = exportDatabaseToSQL($pdo, $prefix);
                
                if (!empty($sql_dump) && strlen($sql_dump) > 100) {
                    @file_put_contents($db_file, $sql_dump);
                    
                    if (file_exists($db_file) && filesize($db_file) > 0) {
                        $db_size = formatBytes(filesize($db_file));
                        $status['details'][] = "✅ Datenbank exportiert: " . basename($db_file) . " ($db_size)";
                        $status['files_created'][] = 'db_backup_' . $timestamp . '.sql';
                        $status['progress'] = 50;
                    } else {
                        throw new Exception('Datenbankdatei konnte nicht geschrieben werden');
                    }
                } else {
                    throw new Exception('Datenbank-Export hat keine Daten zurückgegeben');
                }
            } catch (Exception $e) {
                throw new Exception('Datenbank-Backup fehlgeschlagen: ' . $e->getMessage());
            }
        }
        
        // === DATEIBACKUP ===
        if (in_array($backup_type, ['files', 'full'])) {
            $status['current_step'] = '📦 Dateien werden komprimiert...';
            file_put_contents($status_file, json_encode($status));
            
            $files_file = $backup_dir . '/files_backup_' . $timestamp . '.zip';
            
            $zip = new ZipArchive();
            if ($zip->open($files_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                $dirs_to_backup = [
                    ['path' => '../admin', 'name' => 'admin'],
                    ['path' => '../script', 'name' => 'script'],
                    ['path' => '../assets', 'name' => 'assets'],
                ];
                
                $file_count = 0;
                foreach ($dirs_to_backup as $item) {
                    $full_path = __DIR__ . '/' . $item['path'];
                    if (is_file($full_path)) {
                        $zip->addFile($full_path, $item['name']);
                        $file_count++;
                    } elseif (is_dir($full_path)) {
                        $file_count += addDirToZip($zip, $full_path, $item['name']);
                    }
                }
                
                $zip->close();
                
                if (file_exists($files_file) && filesize($files_file) > 0) {
                    $files_size = formatBytes(filesize($files_file));
                    $status['details'][] = "✅ Dateien komprimiert: " . basename($files_file) . " ($files_size, $file_count Dateien)";
                    $status['files_created'][] = 'files_backup_' . $timestamp . '.zip';
                    $status['progress'] = 90;
                } else {
                    throw new Exception('ZIP-Datei ist leer oder konnte nicht erstellt werden');
                }
            } else {
                throw new Exception('ZIP-Datei konnte nicht erstellt werden');
            }
        }
        
        // === FERTIGSTELLUNG ===
        $status['status'] = 'completed';
        $status['progress'] = 100;
        $status['message'] = '✅ Backup erfolgreich erstellt!';
        $status['current_step'] = 'Fertig';
        $status['end_time'] = time();
        $status['duration'] = $status['end_time'] - $status['start_time'];
        
        try {
            logAction($pdo, $prefix, 'backup_created', "Typ: $backup_type, Dateien: " . count($status['files_created']));
        } catch (Exception $e) {
            // Logging nicht kritisch für Backup-Erfolg
        }
        
    } catch (Exception $e) {
        $status['status'] = 'error';
        $status['message'] = '❌ Fehler: ' . $e->getMessage();
        $status['current_step'] = 'Fehler';
        $status['progress'] = 100;
    }
    
    file_put_contents($status_file, json_encode($status));
    echo json_encode($status);
    exit;
    
} elseif ($action === 'status') {
    if (file_exists($status_file)) {
        $status = json_decode(file_get_contents($status_file), true);
        
        if (!$status) {
            echo json_encode(['status' => 'error', 'message' => 'Status-Datei ungültig', 'progress' => 0]);
            exit;
        }
        
        // Berechne ETA
        if ($status['status'] === 'processing' && isset($status['progress']) && $status['progress'] > 0) {
            $elapsed = time() - $status['start_time'];
            $total_time = ($elapsed / $status['progress']) * 100;
            $remaining = $total_time - $elapsed;
            $status['eta'] = $remaining > 0 ? ceil($remaining) : 0;
        }
        
        echo json_encode($status);
    } else {
        echo json_encode(['status' => 'idle', 'message' => 'Kein aktives Backup', 'progress' => 0]);
    }
    exit;
    
} elseif ($action === 'cleanup') {
    // Status-Datei aufräumen
    if (file_exists($status_file)) {
        unlink($status_file);
    }
    echo json_encode(['success' => true]);
    exit;
}

// Fallback
echo json_encode(['error' => 'Unbekannte Action']);

function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, 2) . ' ' . $units[$pow];
}

function exportDatabaseToSQL($pdo, $prefix) {
    $sql = "-- Menüwahl Database Backup\n";
    $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Database Prefix: " . $prefix . "\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
    
    try {
        // Hole alle Tabellen die mit dem Prefix anfangen
        $tables_result = $pdo->query("SHOW TABLES LIKE '" . addslashes($prefix) . "%'");
        $tables = $tables_result->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            $sql .= "-- =====================\n";
            $sql .= "-- Table: $table\n";
            $sql .= "-- =====================\n\n";
            
            // Drop Table wenn existiert
            $sql .= "DROP TABLE IF EXISTS `$table`;\n\n";
            
            // Create Table Statement
            $create_result = $pdo->query("SHOW CREATE TABLE `$table`");
            $create_row = $create_result->fetch(PDO::FETCH_NUM);
            $sql .= $create_row[1] . ";\n\n";
            
            // Daten exportieren
            $data_result = $pdo->query("SELECT * FROM `$table`");
            $rows = $data_result->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($rows)) {
                // Hole Column-Informationen
                $cols_result = $pdo->query("SHOW COLUMNS FROM `$table`");
                $columns = $cols_result->fetchAll(PDO::FETCH_COLUMN);
                
                $col_names = '`' . implode('`,`', $columns) . '`';
                
                // Baue INSERT Statements
                foreach ($rows as $row) {
                    $values = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } elseif (is_numeric($value) && $value != '') {
                            $values[] = $value;
                        } else {
                            $values[] = "'" . addslashes($value) . "'";
                        }
                    }
                    $sql .= "INSERT INTO `$table` ($col_names) VALUES (" . implode(',', $values) . ");\n";
                }
                $sql .= "\n";
            }
        }
        
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        return $sql;
        
    } catch (Exception $e) {
        throw new Exception('Fehler beim Datenbankexport: ' . $e->getMessage());
    }
}

function addDirToZip(&$zip, $dir, $base_path) {
    $files = @scandir($dir);
    if (!$files) return 0;
    
    $count = 0;
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $file_path = $dir . '/' . $file;
        $zip_path = $base_path . '/' . $file;
        
        if (is_file($file_path)) {
            @$zip->addFile($file_path, $zip_path);
            $count++;
        } elseif (is_dir($file_path)) {
            @$zip->addEmptyDir($zip_path);
            $count += addDirToZip($zip, $file_path, $zip_path);
        }
    }
    return $count;
}
?>
