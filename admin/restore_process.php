<?php
/**
 * admin/restore_process.php - Backup-Wiederherstellung Backend
 */

require_once '../db.php';
require_once '../script/auth.php';

header('Content-Type: application/json; charset=utf-8');

checkLogin();
checkAdmin();

$prefix = $config['database']['prefix'] ?? 'menu_';
$backup_dir = __DIR__ . '/../storage/backups';

// Action: restore backup
if ($_REQUEST['action'] === 'restore') {
    $backup_file = $_POST['backup_file'] ?? '';
    $restore_type = $_POST['restore_type'] ?? '';
    
    // Sanitize inputs
    $backup_file = basename($backup_file);
    $restore_type = in_array($restore_type, ['database', 'files']) ? $restore_type : '';
    
    if (!$backup_file || !$restore_type) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        exit;
    }
    
    $backup_path = $backup_dir . '/' . $backup_file;
    
    if (!file_exists($backup_path)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Backup file not found']);
        exit;
    }
    
    try {
        if ($restore_type === 'database') {
            restoreDatabase($backup_path);
        } else {
            restoreFiles($backup_path);
        }
        
        echo json_encode(['success' => true, 'message' => 'Restore completed']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

/**
 * Restore database from SQL dump
 */
function restoreDatabase($sql_file) {
    global $pdo, $prefix;
    
    if (!file_exists($sql_file)) {
        throw new Exception('SQL file not found');
    }
    
    // Read SQL file
    $sql = file_get_contents($sql_file);
    
    if ($sql === false) {
        throw new Exception('Cannot read SQL file');
    }
    
    // Split by statements (basic parser)
    $statements = explode(';\n', $sql);
    $executed = 0;
    
    try {
        // Disable foreign key checks
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            
            // Skip empty statements and comments
            if (empty($statement) || strpos($statement, '--') === 0) {
                continue;
            }
            
            // Skip comments in the middle of statements
            $lines = explode("\n", $statement);
            $cleanLines = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line) && strpos($line, '--') !== 0) {
                    $cleanLines[] = $line;
                }
            }
            $statement = implode(' ', $cleanLines);
            
            if (empty($statement)) {
                continue;
            }
            
            try {
                $pdo->exec($statement);
                $executed++;
            } catch (PDOException $e) {
                // Log error but continue
                error_log("SQL restore error: " . $e->getMessage() . " | Statement: " . substr($statement, 0, 100));
            }
        }
        
        // Re-enable foreign key checks
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        
        // Log restore action
        error_log("Database restore completed: $executed statements executed from " . basename($sql_file));
        
    } catch (Exception $e) {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        throw $e;
    }
}

/**
 * Restore files from ZIP archive
 */
function restoreFiles($zip_file) {
    if (!file_exists($zip_file)) {
        throw new Exception('ZIP file not found');
    }
    
    // Check if ZIP extension is available
    if (!extension_loaded('zip')) {
        throw new Exception('ZIP extension not available');
    }
    
    $zip = new ZipArchive();
    $res = $zip->open($zip_file);
    
    if ($res !== true) {
        throw new Exception('Cannot open ZIP file: ' . $res);
    }
    
    try {
        $base_path = __DIR__ . '/..';
        
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) continue;
            
            $name = $stat['name'];
            
            // Security: only extract allowed directories
            if (!isAllowedPath($name)) {
                continue;
            }
            
            $full_path = $base_path . '/' . $name;
            
            if (substr($name, -1) === '/') {
                // Directory
                @mkdir($full_path, 0755, true);
            } else {
                // File
                $dir = dirname($full_path);
                @mkdir($dir, 0755, true);
                
                $content = $zip->getFromIndex($i);
                if ($content === false) {
                    throw new Exception("Cannot extract file: $name");
                }
                
                if (file_put_contents($full_path, $content) === false) {
                    throw new Exception("Cannot write file: $full_path");
                }
            }
        }
        
        $zip->close();
        error_log("Files restore completed from " . basename($zip_file));
        
    } catch (Exception $e) {
        $zip->close();
        throw $e;
    }
}

/**
 * Check if path is allowed for extraction
 */
function isAllowedPath($path) {
    $allowed = ['admin/', 'script/', 'assets/', 'views/', 'nav/'];
    
    foreach ($allowed as $dir) {
        if (strpos($path, $dir) === 0) {
            return true;
        }
    }
    
    return false;
}

// Default response
http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Invalid action']);
?>
