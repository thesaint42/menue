<?php
/**
 * admin/restore.php - Backup-Wiederherstellung
 */

require_once '../db.php';
require_once '../script/auth.php';

checkLogin();
checkAdmin();

$prefix = $config['database']['prefix'] ?? 'menu_';
$message = "";
$messageType = "info";

// Backup-Verzeichnis
$backup_dir = __DIR__ . '/../storage/backups';

// Backup-Dateien auflisten
$backup_files = [];
if (is_dir($backup_dir)) {
    $files = scandir($backup_dir, SCANDIR_SORT_DESCENDING);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && (strpos($file, 'backup_') !== false || strpos($file, 'db_backup_') !== false)) {
            $backup_files[] = [
                'name' => $file,
                'size' => filesize($backup_dir . '/' . $file),
                'date' => filemtime($backup_dir . '/' . $file),
                'type' => strpos($file, '.sql') !== false ? 'SQL' : 'ZIP'
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
    <title>Backup-Wiederherstellung - Event Menue Order System (EMOS)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include '../nav/top_nav.php'; ?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Backup-Wiederherstellung</h2>
        <a href="backup.php" class="btn btn-outline-secondary">← Zurück zu Backups</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- WARNUNG -->
    <div class="alert alert-danger mb-4" role="alert">
        <h5 class="alert-heading">⚠️ Wichtige Warnung</h5>
        <p><strong>Eine Wiederherstellung wird die aktuelle Datenbank/Dateien ÜBERSCHREIBEN!</strong></p>
        <ul class="mb-0">
            <li>Machen Sie ein vollständiges Backup, bevor Sie wiederherstellen</li>
            <li>Dieser Vorgang kann nicht rückgängig gemacht werden</li>
            <li>Das System wird während der Wiederherstellung kurzzeitig nicht verfügbar sein</li>
        </ul>
    </div>

    <!-- WIEDERHERSTELLUNG -->
    <div class="card border-0 shadow mb-4">
        <div class="card-header bg-warning text-dark py-3">
            <h5 class="mb-0">📥 Backup wiederherstellen</h5>
        </div>
        <div class="card-body p-4">
            <?php if (empty($backup_files)): ?>
                <div class="alert alert-info">
                    Keine Backups gefunden. Bitte erstellen Sie zuerst ein Backup.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Dateiname</th>
                                <th>Typ</th>
                                <th>Größe</th>
                                <th>Erstellt am</th>
                                <th>Aktion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backup_files as $file): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($file['name']); ?></strong></td>
                                    <td>
                                        <?php if ($file['type'] === 'SQL'): ?>
                                            <span class="badge bg-info">📊 <?php echo $file['type']; ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">📁 <?php echo $file['type']; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo formatBytes($file['size']); ?></td>
                                    <td><small><?php echo date('d.m.Y H:i:s', $file['date']); ?></small></td>
                                    <td>
                                        <?php if ($file['type'] === 'SQL'): ?>
                                            <button class="btn btn-sm btn-warning" onclick="confirmRestore('<?php echo htmlspecialchars($file['name']); ?>', 'database')">
                                                📥 DB wiederherstellen
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-warning" onclick="confirmRestore('<?php echo htmlspecialchars($file['name']); ?>', 'files')">
                                                📥 Dateien wiederherstellen
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- HINWEISE -->
    <div class="alert alert-info">
        <h6>📋 Wiederherstellungs-Information:</h6>
        <ul class="mb-0 small">
            <li><strong>Datenbank (SQL):</strong> Stellt Tabellen und Daten wieder her</li>
            <li><strong>Dateien (ZIP):</strong> Ersetzt admin/, script/, assets/, views/, nav/ Verzeichnisse</li>
            <li><strong>Nach Wiederherstellung:</strong> Eventuell müssen Sie sich neu anmelden</li>
            <li><strong>Auf neuem System:</strong> Können Sie Backups über diese Seite oder manuell hochladen</li>
        </ul>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">⚠️ Bestätigung erforderlich</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="confirmText"></p>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="confirmCheck">
                    <label class="form-check-label" for="confirmCheck">
                        Ich verstehe, dass dies nicht rückgängig gemacht werden kann
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-danger" id="confirmButton" disabled onclick="executeRestore()">
                    Ja, wiederherstellen
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let restoreFile = '';
let restoreType = '';

function confirmRestore(filename, type) {
    restoreFile = filename;
    restoreType = type;
    
    const text = type === 'database' 
        ? `Datenbank aus <strong>${filename}</strong> wiederherstellen?`
        : `Dateien aus <strong>${filename}</strong> wiederherstellen?`;
    
    document.getElementById('confirmText').innerHTML = text;
    document.getElementById('confirmCheck').checked = false;
    document.getElementById('confirmButton').disabled = true;
    
    document.getElementById('confirmCheck').onchange = function() {
        document.getElementById('confirmButton').disabled = !this.checked;
    };
    
    const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
    modal.show();
}

function executeRestore() {
    fetch('restore_process.php?action=restore', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'backup_file=' + encodeURIComponent(restoreFile) + '&restore_type=' + encodeURIComponent(restoreType)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Wiederherstellung erfolgreich!');
            location.reload();
        } else {
            alert('❌ Fehler: ' + (data.error || 'Unbekannter Fehler'));
        }
    })
    .catch(error => {
        alert('❌ Fehler: ' + error.message);
    });
}
</script>

</body>
</html>
