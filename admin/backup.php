<?php
/**
 * admin/backup.php - Backup-Management
 */

require_once '../db.php';
require_once '../script/auth.php';

checkLogin();

$prefix = $config['database']['prefix'] ?? 'menu_';
$user_role_id = $_SESSION['role_id'] ?? null;

// Access-Check: Backup-Berechtigung erforderlich (entweder Erstellen oder Importieren)
requireMenuAccess($pdo, ['backup_create', 'backup_import'], 'read', $prefix);

// Rolle bestimmen für Projektfilterung
$is_admin = ($user_role_id === 1);
$is_project_admin = hasMenuAccess($pdo, 'projects_write', $prefix);
$is_reporting_user = hasMenuAccess($pdo, 'projects_read', $prefix);

// Für Projekt Admin oder Reporting User: Nur die eigenen Projekte abrufen
$user_projects = [];
if (($is_project_admin || $is_reporting_user) && !$is_admin) {
    $user_projects = getUserProjects($pdo, $prefix);
}
$message = "";
$messageType = "info";

// Backup-Verzeichnis
$backup_dir = __DIR__ . '/../storage/backups';
if (!is_dir($backup_dir)) {
    @mkdir($backup_dir, 0755, true);
}

// Nur für direkte POST-Anfragen (alte Schnittstelle) - wird nicht mehr verwendet
// Das neue System nutzt AJAX via backup_process.php

// Backup löschen
if (isset($_GET['delete'])) {
    $file = basename($_GET['delete']);
    $file_path = $backup_dir . '/' . $file;
    
    // Für Projekt Admin: Nur eigene Projekt-Backups löschen
    if (!$is_admin && $is_project_admin) {
        if (preg_match('/^project_backup_(\d+)_/', $file, $matches)) {
            $project_id = (int)$matches[1];
            $allowed_project_ids = array_column($user_projects, 'id');
            if (!in_array($project_id, $allowed_project_ids)) {
                $message = "Zugriff verweigert: Sie können nur Backups Ihrer eigenen Projekte löschen.";
                $messageType = "danger";
                $file_path = null; // Verhindere Löschen
            }
        } else {
            $message = "Zugriff verweigert: Sie können nur Projekt-Backups löschen.";
            $messageType = "danger";
            $file_path = null;
        }
    }
    
    if ($file_path && file_exists($file_path) && strpos($file, '..') === false) {
        if (unlink($file_path)) {
            $message = "Backup gelöscht: $file";
            $messageType = "success";
            logAction($pdo, $prefix, 'backup_deleted', "Datei: $file");
        } else {
            $message = "Fehler beim Löschen des Backups";
            $messageType = "danger";
        }
    }
}

// Backup herunterladen
if (isset($_GET['download'])) {
    $file = basename($_GET['download']);
    $file_path = $backup_dir . '/' . $file;
    
    // Für Projekt Admin und Reporting User: Nur eigene Projekt-Backups herunterladen
    if (!$is_admin && ($is_project_admin || $is_reporting_user)) {
        if (preg_match('/^project_backup_(\d+)_/', $file, $matches)) {
            $project_id = (int)$matches[1];
            $allowed_project_ids = array_column($user_projects, 'id');
            if (!in_array($project_id, $allowed_project_ids)) {
                die("Zugriff verweigert: Sie können nur Backups Ihrer eigenen Projekte herunterladen.");
            }
        } else {
            die("Zugriff verweigert: Sie können nur Projekt-Backups herunterladen.");
        }
    }
    
    if (file_exists($file_path) && strpos($file, '..') === false) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($file_path));
        header('Pragma: public');
        header('Cache-Control: max-age=0');
        
        readfile($file_path);
        logAction($pdo, $prefix, 'backup_downloaded', "Datei: $file");
        exit;
    } else {
        $message = "Backup-Datei nicht gefunden";
        $messageType = "danger";
    }
}

// Backup-Dateien auflisten
$backup_files = [];
if (is_dir($backup_dir)) {
    $files = scandir($backup_dir, SCANDIR_SORT_DESCENDING);
    
    // Für Projekt Admin und Reporting User: Nur die eigenen Projekt-Backups anzeigen
    $allowed_project_ids = [];
    if (!$is_admin && ($is_project_admin || $is_reporting_user) && !empty($user_projects)) {
        $allowed_project_ids = array_column($user_projects, 'id');
    }
    
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && (strpos($file, 'backup_') !== false)) {
            // Für Projekt Admin und Reporting User: Nur Projekt-spezifische Backups anzeigen
            if (!$is_admin && ($is_project_admin || $is_reporting_user)) {
                // Nur project_backup_X_* anzeigen und nur wenn X in erlaubten Projekten
                if (preg_match('/^project_backup_(\d+)_/', $file, $matches)) {
                    $project_id = (int)$matches[1];
                    if (!in_array($project_id, $allowed_project_ids)) {
                        continue; // Skip backup von nicht-erlaubtem Projekt
                    }
                } else {
                    continue; // Skip non-project backups (db_backup, files_backup, etc.)
                }
            }
            
            $backup_files[] = [
                'name' => $file,
                'size' => filesize($backup_dir . '/' . $file),
                'date' => filemtime($backup_dir . '/' . $file),
                'type' => strpos($file, 'db_backup') !== false ? 'Datenbank' : (strpos($file, 'project_backup') !== false ? 'Projekt Backup' : 'Dateien')
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

function addDirToZip(&$zip, $dir, $base_path) {
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $file_path = $dir . '/' . $file;
        $zip_path = $base_path . '/' . $file;
        
        if (is_file($file_path)) {
            $zip->addFile($file_path, $zip_path);
        } elseif (is_dir($file_path)) {
            $zip->addEmptyDir($zip_path);
            addDirToZip($zip, $file_path, $zip_path);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup-Verwaltung - Event Menue Order System (EMOS)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include '../nav/top_nav.php'; ?>

<div class="container py-4">
    <div class="mb-5">
        <div class="text-center mb-4">
            <h1 class="mb-1">🔒 Backup-Verwaltung</h1>
            <p class="text-muted">Sichern und Wiederherstellen Ihrer Daten</p>
        </div>

        <!-- HAUPT-BUTTONS NEBENEINANDER -->
        <div class="row g-3 g-md-4 mb-4">
            <?php if ($is_admin): ?>
                <div class="col-12 col-md-6">
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-success btn-lg fw-bold py-3" data-bs-toggle="collapse" data-bs-target="#backupCreateSection" aria-expanded="false">
                            📦 Neues Backup erstellen
                        </button>
                    </div>
                </div>
            <?php endif; ?>
            <div class="col-12 col-md-<?php echo $is_admin ? '6' : '12'; ?>">
                <div class="d-grid gap-2">
                    <a href="restore.php" class="btn btn-warning btn-lg fw-bold py-3">
                        📥 Backup wiederherstellen
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- BACKUP ERSTELLEN (Collapsible) - NUR FÜR ADMIN -->
    <?php if ($is_admin): ?>
    <div class="collapse mb-4" id="backupCreateSection">
        <div class="card border-0 shadow">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="mb-0">📦 Neues Backup erstellen</h5>
            </div>
            <div class="card-body p-4">
                <div id="backupForm">
                    <form id="createBackupForm" onsubmit="startBackup(event)">
                        <div class="row g-3 g-md-4">
                            <div class="col-12 col-lg-4">
                                <label class="form-label fw-bold">Backup-Typ *</label>
                                <select id="backupType" name="backup_type" class="form-select" required>
                                    <option value="full" selected>✓ Vollständig (Datenbank + Dateien)</option>
                                    <option value="database">📊 Nur Datenbank</option>
                                    <option value="files">📁 Nur Dateien</option>
                                </select>
                                <small class="text-muted d-block mt-2">
                                    💡 Vollständig empfohlen für vollständige Wiederherstellung
                                </small>
                            </div>
                            <div class="col-12 col-lg-8">
                                <label class="form-label fw-bold d-block">Aktion</label>
                                <button type="submit" class="btn btn-success btn-lg fw-bold w-100">
                                    🔒 Jetzt Backup erstellen
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- PROGRESS ANZEIGE -->
                <div id="backupProgress" style="display: none;" class="mt-4">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0" id="currentStep">Backup wird vorbereitet...</h6>
                            <div>
                                <small class="text-success" id="progressPercent">0%</small>
                                <small class="text-muted ms-3" id="progressEta"></small>
                            </div>
                        </div>
                        <div class="progress" style="height: 25px;">
                            <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>

                    <!-- DETAIL INFORMATIONEN -->
                    <div class="alert alert-info mt-3 p-3 small">
                        <strong>📋 Backup Details:</strong>
                        <div id="detailsList" style="margin-top: 10px; line-height: 1.8;">
                            <div class="text-muted">Warte auf Update...</div>
                        </div>
                    </div>

                    <!-- STATUS MESSAGE -->
                    <div id="statusMessage" class="alert alert-secondary mt-3 p-3 small" style="display: none;">
                        <span id="statusText"></span>
                    </div>
                </div>

                <div class="alert alert-info mt-4 mb-0">
                    <strong>⏱️ Hinweis:</strong> Große Datenbanken benötigen möglicherweise eine Weile. Bitte nicht die Seite schließen während der Erstellung.
                </div>
            </div>
        </div>
    </div>
    <?php elseif ($is_project_admin): ?>
        <div class="alert alert-info mb-4">
            <strong>ℹ️ Hinweis:</strong> Projekt-Backups können Sie direkt in den Projekt-Bearbeitungsmodi (Gäste, Gerichte, etc.) erstellen.
        </div>
    <?php endif; ?>
</div>

    <!-- BACKUP LISTE -->
    <div class="card border-0 shadow mt-4">
        <div class="card-header bg-info text-white py-3">
            <h5 class="mb-0">💾 Existierende Backups</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($backup_files)): ?>
                <div class="p-4 text-center text-muted">
                    Noch keine Backups vorhanden. Erstellen Sie Ihr erstes Backup!
                </div>
            <?php else: ?>
                <!-- Massenbearbeitung Buttons -->
                <div class="p-3 bg-light border-bottom d-flex gap-2" id="bulkActions" style="display: none;">
                    <button class="btn btn-sm btn-info" onclick="bulkDownload()">
                        ⬇️ Ausgewählte herunterladen
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="bulkDelete()">
                        🗑️ Ausgewählte löschen
                    </button>
                    <button class="btn btn-sm btn-secondary" onclick="clearSelection()">
                        Abbrechen
                    </button>
                    <small class="ms-auto align-self-center text-muted">
                        <span id="selectionCount">0</span> ausgewählt
                    </small>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 40px;">
                                    <input type="checkbox" class="form-check-input" id="selectAll" onchange="toggleSelectAll()">
                                </th>
                                <th>Dateiname</th>
                                <th>Typ</th>
                                <th>Größe</th>
                                <th>Erstellt am</th>
                                <th style="width: 240px;">Aktion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backup_files as $file): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input backup-checkbox" data-filename="<?php echo htmlspecialchars($file['name']); ?>" onchange="updateBulkActions()">
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($file['name']); ?></strong></td>
                                    <td>
                                        <?php if ($file['type'] === 'Datenbank'): ?>
                                            <span class="badge bg-secondary">📊 <?php echo $file['type']; ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-info">📁 <?php echo $file['type']; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo formatBytes($file['size']); ?></td>
                                    <td><small><?php echo date('d.m.Y H:i:s', $file['date']); ?></small></td>
                                    <td>
                                        <a href="backup.php?download=<?php echo urlencode($file['name']); ?>" class="btn btn-sm btn-outline-info" download>⬇️</a>
                                        <a href="backup.php?delete=<?php echo urlencode($file['name']); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Wirklich löschen?')">🗑️</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="p-4 text-muted small border-top">
                    <strong>Gesamt:</strong> <?php 
                    $total_size = array_sum(array_column($backup_files, 'size'));
                    echo count($backup_files) . ' Backup' . (count($backup_files) !== 1 ? 's' : '') . ' (' . formatBytes($total_size) . ')';
                    ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- INFO -->
    <div class="alert alert-warning mt-4">
        <h6>📋 Backup-Empfehlungen:</h6>
        <ul class="mb-0">
            <li>Erstellen Sie regelmäßig Backups (mindestens wöchentlich)</li>
            <li>Laden Sie Backups regelmäßig herunter und speichern Sie sie extern</li>
            <li>Testen Sie gelegentlich die Wiederherstellung eines Backups</li>
            <li>Alte Backups löschen um Speicher zu sparen</li>
        </ul>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let backupProgressInterval = null;
let backupStartTime = null;
let backupStartTimestamp = null;
let autoReloadScheduled = false;

function startBackup(e) {
    e.preventDefault();
    
    const backupType = document.getElementById('backupType').value;
    
    // Verstecke Formular, zeige Progress
    document.getElementById('backupForm').style.display = 'none';
    document.getElementById('backupProgress').style.display = 'block';
    
    backupStartTime = Date.now();
    backupStartTimestamp = Math.floor(Date.now() / 1000);
    
    // Starte das Backup
    fetch('backup_process.php?action=execute', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'backup_type=' + encodeURIComponent(backupType)
    })
    .then(response => {
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.json();
    })
    .then(data => {
        if (data.error) {
            showBackupError(data.error);
            return;
        }
        updateBackupUI(data);
        
        // Poll für Status Updates wenn noch nicht fertig
        if (data.status === 'processing' || data.status === 'starting') {
            if (backupProgressInterval) clearInterval(backupProgressInterval);
            backupProgressInterval = setInterval(checkBackupStatus, 800);
        }
    })
    .catch(error => {
        console.error('Fehler:', error);
        showBackupError('Fehler beim Start des Backups: ' + error.message);
    });
}

function checkBackupStatus() {
    fetch('backup_process.php?action=status')
        .then(response => {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.json();
        })
        .then(data => {
            if (data.error) {
                clearInterval(backupProgressInterval);
                showBackupError(data.error);
                return;
            }
            
            updateBackupUI(data);
            
            // Stoppe wenn fertig
            if (data.status === 'completed' || data.status === 'error') {
                if (backupProgressInterval) {
                    clearInterval(backupProgressInterval);
                }
                
                if (data.status === 'completed') {
                    // Refresh Seite nach 4 Sekunden für neue Backup-Liste
                    setTimeout(() => {
                        fetch('backup_process.php?action=cleanup').catch(() => {});
                        location.reload();
                    }, 4000);
                }
            }
        })
        .catch(error => {
            console.error('Status-Fehler:', error);
            if (backupProgressInterval) clearInterval(backupProgressInterval);
            showBackupError('Fehler beim Abrufen des Status: ' + error.message);
        });
}

function updateBackupUI(data) {
    const progressBar = document.getElementById('progressBar');
    const progressPercent = document.getElementById('progressPercent');
    const currentStep = document.getElementById('currentStep');
    const detailsList = document.getElementById('detailsList');
    const progressEta = document.getElementById('progressEta');
    const statusMessage = document.getElementById('statusMessage');
    const statusText = document.getElementById('statusText');
    
    // Update Progress Bar
    progressBar.style.width = data.progress + '%';
    progressBar.setAttribute('aria-valuenow', data.progress);
    progressPercent.textContent = data.progress + '%';
    
    // Update Current Step
    currentStep.textContent = (data.current_step || 'Verarbeitung läuft...');
    
    // Update Details
    if (data.details && data.details.length > 0) {
        detailsList.innerHTML = data.details.map(d => '<div>• ' + d + '</div>').join('');
    }
    
    // Update ETA
    if (data.eta !== undefined && data.eta > 0) {
        const minutes = Math.floor(data.eta / 60);
        const seconds = data.eta % 60;
        let etaText = '';
        if (minutes > 0) etaText += minutes + 'm ';
        etaText += seconds + 's';
        progressEta.textContent = 'ETA: ' + etaText;
    } else {
        // Berechne verstrichene Zeit basierend auf lokaler Uhr
        if (backupStartTimestamp) {
            const currentTime = Math.floor(Date.now() / 1000);
            const elapsed = currentTime - backupStartTimestamp;
            const minutes = Math.floor(elapsed / 60);
            const seconds = elapsed % 60;
            let durationText = '';
            if (minutes > 0) durationText += minutes + 'm ';
            durationText += seconds + 's';
            progressEta.textContent = 'Zeit: ' + durationText;
        }
    }
    
    // Bei Fertigstellung: Zeige finale Zeit
    if (data.status === 'completed' && data.duration) {
        const minutes = Math.floor(data.duration / 60);
        const seconds = data.duration % 60;
        let durationText = '';
        if (minutes > 0) durationText += minutes + 'm ';
        durationText += seconds + 's';
        progressEta.textContent = 'Zeit: ' + durationText;
    }
    
    // Update Status Message
    if (data.status === 'completed') {
        statusMessage.className = 'alert alert-success mt-3 p-3';
        statusMessage.style.display = 'block';
        statusText.innerHTML = `
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <strong>✅ Backup erfolgreich erstellt!</strong>
                    <div class="small text-muted mt-2">${data.message}</div>
                </div>
                <button class="btn btn-success ms-3" onclick="resetBackupForm()">
                    🔄 Zurück
                </button>
            </div>
        `;
        
        // Change Button
        progressBar.classList.add('bg-success');
        progressBar.classList.remove('progress-bar-striped', 'progress-bar-animated');
        
        // Auto-reload nach 4 Sekunden wenn nicht geklickt
        autoReloadScheduled = true;
        setTimeout(() => {
            if (autoReloadScheduled) {
                fetch('backup_process.php?action=cleanup').catch(() => {});
                location.reload();
            }
        }, 4000);
    } else if (data.status === 'error') {
        statusMessage.className = 'alert alert-danger mt-3 p-3';
        statusMessage.style.display = 'block';
        statusText.innerHTML = `
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <strong>❌ Backup-Fehler</strong>
                    <div class="small text-muted mt-2">${data.message}</div>
                </div>
                <button class="btn btn-warning ms-3" onclick="resetBackupForm()">
                    🔄 Zurück
                </button>
            </div>
        `;
        
        progressBar.classList.add('bg-danger');
        progressBar.classList.remove('progress-bar-animated');
    }
}

function showBackupError(message) {
    const statusMessage = document.getElementById('statusMessage');
    statusMessage.className = 'alert alert-danger mt-3 p-3';
    statusMessage.style.display = 'block';
    document.getElementById('statusText').innerHTML = `
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <strong>❌ Fehler</strong>
                <div class="small text-muted mt-2">${message}</div>
            </div>
            <button class="btn btn-warning ms-3" onclick="resetBackupForm()">
                🔄 Zurück
            </button>
        </div>
    `;
    
    const progressBar = document.getElementById('progressBar');
    progressBar.classList.add('bg-danger');
    progressBar.classList.remove('progress-bar-animated');
}

function resetBackupForm() {
    // Verhindere Auto-Reload
    autoReloadScheduled = false;
    
    // Reset UI
    document.getElementById('backupForm').style.display = 'block';
    document.getElementById('backupProgress').style.display = 'none';
    
    // Reset Progress Bar
    const progressBar = document.getElementById('progressBar');
    progressBar.style.width = '0%';
    progressBar.setAttribute('aria-valuenow', '0');
    progressBar.classList.remove('bg-success', 'bg-danger');
    progressBar.classList.add('progress-bar-striped', 'progress-bar-animated');
    
    // Reset Prozent
    document.getElementById('progressPercent').textContent = '0%';
    
    // Reset Status
    document.getElementById('currentStep').textContent = 'Backup wird vorbereitet...';
    document.getElementById('detailsList').innerHTML = '<div class="text-muted">Warte auf Start...</div>';
    document.getElementById('progressEta').textContent = '';
    document.getElementById('statusMessage').style.display = 'none';
    
    // Reset Zeitvariablen
    backupStartTime = null;
    backupStartTimestamp = null;
}
// Massenbearbeitung: Select All
function toggleSelectAll() {
    const selectAllCheckbox = document.getElementById('selectAll');
    const backupCheckboxes = document.querySelectorAll('tbody .backup-checkbox');
    
    console.log('✅ toggleSelectAll() - checked:', selectAllCheckbox.checked, 'count:', backupCheckboxes.length);
    
    backupCheckboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
    
    updateBulkActions();
}

// Massenbearbeitung: Update Buttons
function updateBulkActions() {
    const bulkActionsDiv = document.getElementById('bulkActions');
    const selectionCountSpan = document.getElementById('selectionCount');
    const selectAllCheckbox = document.getElementById('selectAll');
    
    if (!bulkActionsDiv || !selectionCountSpan || !selectAllCheckbox) {
        console.warn('⚠️  Missing DOM elements');
        return;
    }
    
    // Zähle nur Checkboxen in tbody (nicht checked, alle!)
    const allCheckboxes = document.querySelectorAll('tbody .backup-checkbox');
    const checkedCheckboxes = document.querySelectorAll('tbody .backup-checkbox:checked');
    
    console.log('📊 updateBulkActions() - total:', allCheckboxes.length, 'checked:', checkedCheckboxes.length);
    
    // Update Selection Count
    selectionCountSpan.textContent = checkedCheckboxes.length;
    
    // Show/Hide Bulk Actions
    if (checkedCheckboxes.length > 0) {
        bulkActionsDiv.style.display = 'flex';
    } else {
        bulkActionsDiv.style.display = 'none';
    }
    
    // Update Select All Checkbox
    if (allCheckboxes.length > 0) {
        const allChecked = checkedCheckboxes.length === allCheckboxes.length;
        const someChecked = checkedCheckboxes.length > 0 && checkedCheckboxes.length < allCheckboxes.length;
        
        selectAllCheckbox.checked = allChecked;
        selectAllCheckbox.indeterminate = someChecked;
        
        console.log('  selectAll.checked =', selectAllCheckbox.checked, ', indeterminate =', selectAllCheckbox.indeterminate);
    }
}

// Massenbearbeitung: Alle Download
function bulkDownload() {
    const selectedCheckboxes = document.querySelectorAll('tbody .backup-checkbox:checked');
    
    if (selectedCheckboxes.length === 0) {
        alert('Keine Backups ausgewählt!');
        return;
    }
    
    // Downloade eins nach dem anderen mit Delay
    selectedCheckboxes.forEach((checkbox, index) => {
        const filename = checkbox.getAttribute('data-filename');
        setTimeout(() => {
            const link = document.createElement('a');
            link.href = `backup.php?download=${encodeURIComponent(filename)}`;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }, index * 500); // 500ms Delay zwischen Downloads
    });
}

// Massenbearbeitung: Alle Löschen
function bulkDelete() {
    const selectedCheckboxes = document.querySelectorAll('tbody .backup-checkbox:checked');
    
    if (selectedCheckboxes.length === 0) {
        alert('Keine Backups ausgewählt!');
        return;
    }
    
    if (!confirm(`Wirklich ${selectedCheckboxes.length} Backup(s) löschen? Dies kann nicht rückgängig gemacht werden.`)) {
        return;
    }
    
    const filesToDelete = [];
    selectedCheckboxes.forEach(checkbox => {
        filesToDelete.push(checkbox.getAttribute('data-filename'));
    });
    
    // Lösche nacheinander
    let deleted = 0;
    filesToDelete.forEach((filename, index) => {
        setTimeout(() => {
            fetch(`backup.php?delete=${encodeURIComponent(filename)}`)
                .then(() => {
                    deleted++;
                    if (deleted === filesToDelete.length) {
                        // Alle gelöscht - neuladen
                        alert(`✅ ${deleted} Backup(s) erfolgreich gelöscht!`);
                        location.reload();
                    }
                })
                .catch(err => {
                    console.error('Fehler beim Löschen:', err);
                });
        }, index * 300);
    });
}

// Massenbearbeitung: Abbrechen
function clearSelection() {
    document.querySelectorAll('tbody .backup-checkbox').forEach(checkbox => {
        checkbox.checked = false;
    });
    document.getElementById('selectAll').checked = false;
    updateBulkActions();
}
</script>
