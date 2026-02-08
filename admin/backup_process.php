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
    // timestamp for filenames in format yyyymmdd_hhmmss
    $file_timestamp = date('Ymd_His');
    // timestamp for filenames in format yyyymmdd_hhmmss
    $file_timestamp = date('Ymd_His');
    
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
    // timestamp for filenames in format yyyymmdd_hhmmss
    $file_timestamp = date('Ymd_His');
    
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
        // === PROJEKTBACKUP ===
        if ($backup_type === 'project') {
            $status['current_step'] = '💾 Projekt-Datenbank wird exportiert...';
            $status['progress'] = 20;
            file_put_contents($status_file, json_encode($status));

            $project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
            if ($project_id <= 0) throw new Exception('Ungültige Projekt-ID');

            // Fetch project name for filename
            $proj_name = '';
            try {
                $q = $pdo->prepare("SELECT name FROM {$prefix}projects WHERE id = ?");
                $q->execute([$project_id]);
                $row = $q->fetch(PDO::FETCH_ASSOC);
                if ($row && isset($row['name'])) $proj_name = $row['name'];
            } catch (Exception $e) {
                // ignore, keep empty name
            }

            // sanitize project name for filename: allow a-zA-Z0-9 and replace others with underscore
            $proj_name_clean = preg_replace('/[^A-Za-z0-9\-_]/', '_', trim((string)$proj_name));
            if ($proj_name_clean === '') $proj_name_clean = 'project';
            // limit length
            $proj_name_clean = substr($proj_name_clean, 0, 50);

            $proj_file = $backup_dir . '/project_backup_' . $project_id . '_' . $proj_name_clean . '_' . $file_timestamp . '.sql';
            try {
                $sql_dump = exportProjectToSQL($pdo, $prefix, $project_id);
                if (!empty($sql_dump) && strlen($sql_dump) > 20) {
                    @file_put_contents($proj_file, $sql_dump);
                    if (file_exists($proj_file) && filesize($proj_file) > 0) {
                        $status['details'][] = "✅ Projekt-Export: " . basename($proj_file) . "";
                        $status['files_created'][] = basename($proj_file);
                        $status['progress'] = 100;
                    } else {
                        throw new Exception('Projekt-Backup konnte nicht geschrieben werden');
                    }
                } else {
                    throw new Exception('Projekt-Export lieferte keine Daten');
                }
            } catch (Exception $e) {
                throw new Exception('Projekt-Backup fehlgeschlagen: ' . $e->getMessage());
            }
        }

        // === DATENBANKBACKUP (vollständig) ===
        elseif (in_array($backup_type, ['database', 'full'])) {
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
    $sql = "-- Event Menue Order System (EMOS) Database Backup\n";
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

/**
 * Exportiert nur die projektrelevanten Tabellen/Zeilen als SQL
 */
function exportProjectToSQL($pdo, $prefix, $project_id) {
    $sql = "-- Project Backup (only project-specific rows)\n";
    $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    // Tabellen, die projektbezogene Daten enthalten
    $tables = ['projects', 'dishes', 'guests', 'family_members', 'orders'];

    foreach ($tables as $tshort) {
        $table = $prefix . $tshort;
        try {
            // Create Table
            $cr = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
            if ($cr && isset($cr[1])) {
                $sql .= "-- Table: $table\n";
                $sql .= "DROP TABLE IF EXISTS `$table`;\n";
                $sql .= $cr[1] . ";\n\n";
            }
        } catch (Exception $e) {
            // Table may not exist; skip
            continue;
        }

        // Daten exportieren (eingeschränkt)
        if ($tshort === 'projects') {
            $rows = $pdo->prepare("SELECT * FROM `$table` WHERE id = ?");
            $rows->execute([$project_id]);
            $data = $rows->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($tshort === 'dishes') {
            $rows = $pdo->prepare("SELECT * FROM `$table` WHERE project_id = ?");
            $rows->execute([$project_id]);
            $data = $rows->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($tshort === 'guests') {
            $rows = $pdo->prepare("SELECT * FROM `$table` WHERE project_id = ?");
            $rows->execute([$project_id]);
            $data = $rows->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($tshort === 'family_members') {
            // family_members for guests of this project
            $g = $pdo->prepare("SELECT id FROM `{$prefix}guests` WHERE project_id = ?");
            $g->execute([$project_id]);
            $guestIds = $g->fetchAll(PDO::FETCH_COLUMN);
            $data = [];
            if (!empty($guestIds)) {
                $in = implode(',', array_fill(0, count($guestIds), '?'));
                $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE guest_id IN ($in)");
                $stmt->execute($guestIds);
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } elseif ($tshort === 'orders') {
            $g = $pdo->prepare("SELECT id FROM `{$prefix}guests` WHERE project_id = ?");
            $g->execute([$project_id]);
            $guestIds = $g->fetchAll(PDO::FETCH_COLUMN);
            $data = [];
            if (!empty($guestIds)) {
                $in = implode(',', array_fill(0, count($guestIds), '?'));
                $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE guest_id IN ($in)");
                $stmt->execute($guestIds);
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } else {
            $data = [];
        }

        if (!empty($data)) {
            // Column names
            $cols = array_keys($data[0]);
            $col_names = '`' . implode('`,`', $cols) . '`';
            foreach ($data as $row) {
                $values = [];
                foreach ($cols as $c) {
                    $v = $row[$c];
                    if ($v === null) $values[] = 'NULL';
                    elseif (is_numeric($v) && $v !== '') $values[] = $v;
                    else $values[] = "'" . addslashes($v) . "'";
                }
                $sql .= "INSERT INTO `$table` ($col_names) VALUES (" . implode(',', $values) . ");\n";
            }
            $sql .= "\n";
        }
    }

    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return $sql;
}
?>
