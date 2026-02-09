<?php
/**
 * setup_restore.php - Backup-Upload & Restore für frisch installierte Systeme
 * Diese Seite ermöglicht das Hochladen und Wiederherstellen von Backups auf neuen Installationen
 */

session_start();

// Create storage directories
@mkdir(__DIR__ . '/storage', 0755, true);
@mkdir(__DIR__ . '/storage/backups', 0755, true);
@mkdir(__DIR__ . '/storage/logs', 0755, true);
@mkdir(__DIR__ . '/storage/pdfs', 0755, true);
@mkdir(__DIR__ . '/storage/tmp', 0755, true);

$message = '';
$messageType = 'info';
$success = false;

// Handle backup upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    if (!isset($_FILES['backup_file'])) {
        $message = '❌ Keine Datei hochgeladen';
        $messageType = 'danger';
    } else {
        $file = $_FILES['backup_file'];
        
        // Validate file
        $allowed_extensions = ['sql', 'zip'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $message = '❌ Upload-Fehler: ' . $file['error'];
            $messageType = 'danger';
        } elseif (!in_array($file_ext, $allowed_extensions)) {
            $message = '❌ Nur .sql und .zip Dateien erlaubt';
            $messageType = 'danger';
        } elseif ($file['size'] > 500 * 1024 * 1024) { // 500MB limit
            $message = '❌ Datei ist zu groß (max 500MB)';
            $messageType = 'danger';
        } else {
            $backup_dir = __DIR__ . '/storage/backups';
            $new_name = 'backup_' . time() . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '', basename($file['name']));
            $destination = $backup_dir . '/' . $new_name;
            
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $message = '✅ Backup erfolgreich hochgeladen: ' . $new_name;
                $messageType = 'success';
                $_SESSION['uploaded_backup'] = $new_name;
            } else {
                $message = '❌ Fehler beim Speichern der Datei';
                $messageType = 'danger';
            }
        }
    }
}

// Handle ZIP file restore
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restore_zip') {
    $backup_file = basename($_POST['backup_file'] ?? ''); // Sanitize
    $backup_path = __DIR__ . '/storage/backups/' . $backup_file;
    
    if (!file_exists($backup_path)) {
        $message = '❌ Backup-Datei nicht gefunden';
        $messageType = 'danger';
    } elseif (substr($backup_file, -4) !== '.zip') {
        $message = '❌ Nur ZIP-Dateien können wiederhergestellt werden';
        $messageType = 'danger';
    } else {
        try {
            if (!extension_loaded('zip')) {
                throw new Exception('ZIP extension nicht verfügbar');
            }
            
            $zip = new ZipArchive();
            $open_result = $zip->open($backup_path);
            if ($open_result !== true) {
                throw new Exception('ZIP-Datei kann nicht geöffnet werden (Fehler: ' . $open_result . ')');
            }
            
            $extracted = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat === false) continue;
                
                $name = $stat['name'];
                
                // Only extract allowed directories
                $allowed = ['admin/', 'script/', 'assets/', 'views/', 'nav/', 'img/'];
                $is_allowed = false;
                foreach ($allowed as $dir) {
                    if (strpos($name, $dir) === 0) {
                        $is_allowed = true;
                        break;
                    }
                }
                
                if (!$is_allowed) {
                    continue;
                }
                
                $full_path = __DIR__ . '/' . $name;
                
                if (substr($name, -1) === '/') {
                    // Directory
                    @mkdir($full_path, 0755, true);
                } else {
                    // File
                    $dir = dirname($full_path);
                    @mkdir($dir, 0755, true);
                    
                    $content = $zip->getFromIndex($i);
                    if ($content !== false) {
                        if (file_put_contents($full_path, $content) !== false) {
                            $extracted++;
                        }
                    }
                }
            }
            
            $zip->close();
            $message = "✅ Wiederherstellung erfolgreich! $extracted Dateien wiederhergestellt.";
            $messageType = 'success';
            $success = true;
        } catch (Exception $e) {
            $message = '❌ Fehler bei Wiederherstellung: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// List available backups
$backup_dir = __DIR__ . '/storage/backups';
$backups = [];
if (is_dir($backup_dir)) {
    $files = scandir($backup_dir, SCANDIR_SORT_DESCENDING);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && 
            (substr($file, -4) === '.sql' || substr($file, -4) === '.zip')) {
            $size = @filesize($backup_dir . '/' . $file);
            $backups[] = [
                'name' => $file,
                'size' => $size !== false ? $size : 0,
                'date' => @filemtime($backup_dir . '/' . $file),
                'type' => substr($file, -4) === '.sql' ? 'SQL' : 'ZIP'
            ];
        }
    }
}

function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, 2) . ' ' . $units[$pow];
}
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>Backup Upload & Restore - EMOS Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; }
        .card { border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <!-- HEADER -->
            <div class="text-center mb-5">
                <h1 class="text-white mb-2">🍽️ EMOS System Setup</h1>
                <p class="text-muted h6">Event Menue Order System - Backup Upload & Restore</p>
                <hr class="text-secondary">
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show mb-4" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="card border-success bg-dark mb-4">
                    <div class="card-body">
                        <h5 class="text-success">✅ Wiederherstellung abgeschlossen!</h5>
                        <p class="mb-2">Die Dateien wurden erfolgreich wiederhergestellt.</p>
                        <p class="mb-0"><strong>Nächste Schritte:</strong></p>
                        <ol class="mb-0 mt-2 small">
                            <li>Konfigurieren Sie <strong>db.php</strong> mit Ihren Datenbankeinstellungen</li>
                            <li>Rufen Sie <strong><a href="migrate.php" class="text-info">migrate.php</a></strong> auf</li>
                            <li>Starten Sie mit <strong><a href="index.php" class="text-info">index.php</a></strong></li>
                        </ol>
                    </div>
                </div>
            <?php endif; ?>

            <!-- UPLOAD SECTION -->
            <div class="card border-0 shadow mb-4">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0">📤 Backup hochladen</h5>
                </div>
                <div class="card-body p-4">
                    <p class="text-muted mb-4">Laden Sie ein Backup (.zip oder .sql) hoch, um dieses auf dem System wiederherzustellen.</p>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Backup-Datei</label>
                            <input type="file" name="backup_file" class="form-control" accept=".zip,.sql" required>
                            <div class="form-text">
                                📌 Maximale Größe: 500MB | Format: .zip (Dateien+Code) oder .sql (Datenbank)
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 fw-bold">
                            📤 Backup hochladen
                        </button>
                    </form>
                </div>
            </div>

            <!-- BACKUPS LIST -->
            <?php if (!empty($backups)): ?>
                <div class="card border-0 shadow mb-4">
                    <div class="card-header bg-warning text-dark py-3">
                        <h5 class="mb-0">📥 Verfügbare Backups zum Wiederherstellen</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Dateiname</th>
                                        <th>Typ</th>
                                        <th>Größe</th>
                                        <th>Erstellt</th>
                                        <th style="width: 200px;">Aktion</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($backups as $backup): ?>
                                        <tr>
                                            <td>
                                                <small class="text-truncate" title="<?php echo htmlspecialchars($backup['name']); ?>">
                                                    <?php echo htmlspecialchars($backup['name']); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php if ($backup['type'] === 'ZIP'): ?>
                                                    <span class="badge bg-secondary">📁 ZIP</span>
                                                <?php else: ?>
                                                    <span class="badge bg-info">📊 SQL</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo formatBytes($backup['size']); ?></td>
                                            <td>
                                                <small>
                                                    <?php echo $backup['date'] ? date('d.m.Y H:i', $backup['date']) : '—'; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php if ($backup['type'] === 'ZIP'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="restore_zip">
                                                        <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($backup['name']); ?>">
                                                        <button type="submit" class="btn btn-sm btn-warning" 
                                                                onclick="return confirm('⚠️ Dateien wiederherstellen? Bestehende Dateien werden überschrieben.')">
                                                            📥 Wiederherstellen
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <small class="text-muted">SQL-Import über phpMyAdmin</small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- INFO & INSTRUCTIONS -->
            <div class="card border-0 shadow bg-dark border-info">
                <div class="card-header bg-info text-dark py-3">
                    <h5 class="mb-0">ℹ️ Informationen & Anleitung</h5>
                </div>
                <div class="card-body p-4 text-muted small">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <h6 class="text-info mb-2">📊 Backup-Typen</h6>
                            <ul class="mb-0">
                                <li><strong>ZIP-Backup:</strong> Enthält Admin, Script, Assets, Nav, Views</li>
                                <li><strong>SQL-Backup:</strong> Nur Datenbank-Tabellen & Daten</li>
                                <li><strong>Full-Backup:</strong> Alle Komponenten (ZIP + SQL getrennt)</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-info mb-2">🔄 Setup-Schritte</h6>
                            <ol class="mb-0">
                                <li>Laden Sie ZIP-Backup hoch</li>
                                <li>Klicken Sie "Wiederherstellen"</li>
                                <li>Konfigurieren Sie db.php</li>
                                <li>Führen Sie migrate.php aus</li>
                                <li>Starten Sie mit index.php</li>
                            </ol>
                        </div>
                    </div>
                    <hr class="my-3">
                    <p class="mb-0">
                        💡 <strong>Hinweis:</strong> Diese Seite ermöglicht die schnelle Wiederherstellung auf neuen Systemen. 
                        Nach der Installation verwenden Sie bitte <strong>admin/restore.php</strong> für Restore-Operationen.
                    </p>
                </div>
            </div>

            <!-- FOOTER -->
            <div class="text-center mt-5 text-muted">
                <small>EMOS v3.0 | Setup-Assistent | <?php echo date('Y'); ?></small>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
