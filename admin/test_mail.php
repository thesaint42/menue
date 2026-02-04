<?php
require_once '../db.php';
require_once '../script/auth.php';

checkLogin();
checkAdmin();

$prefix = $config['database']['prefix'] ?? 'menu_';

$projects = $pdo->query("SELECT id, name FROM {$prefix}projects ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <title>SMTP Test - Menüwahl</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include '../nav/top_nav.php'; ?>

<div class="container py-4">
    <h2>SMTP Test / Einladung senden</h2>

    <div class="card mt-3 p-3 bg-dark border-secondary text-light">
        <div class="mb-3">
            <label class="form-label">Empfänger E-Mail</label>
            <input id="recipient" class="form-control" placeholder="email@example.com">
        </div>

        <div class="mb-3">
            <label class="form-label">Projekt (optional, für Einladung)</label>
            <select id="project_id" class="form-select">
                <option value="">-- Kein Projekt --</option>
                <?php foreach ($projects as $p): ?>
                    <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3 form-check">
            <input class="form-check-input" type="checkbox" id="sendInvite">
            <label class="form-check-label" for="sendInvite">Als Einladung senden (mit PIN/Link)</label>
        </div>

        <div>
            <button id="sendBtn" class="btn btn-primary">Test senden</button>
            <div id="status" class="mt-3"></div>
        </div>
    </div>
</div>

<script>
document.getElementById('sendBtn').addEventListener('click', function(){
    const recipient = document.getElementById('recipient').value.trim();
    const project_id = document.getElementById('project_id').value;
    const invite = document.getElementById('sendInvite').checked;

    if (!recipient) {
        document.getElementById('status').innerHTML = '<div class="alert alert-danger">Bitte Empfänger eingeben.</div>';
        return;
    }

    const data = new FormData();
    data.append('recipient', recipient);
    if (project_id) data.append('project_id', project_id);
    data.append('mode', invite ? 'invite' : 'test');

    document.getElementById('sendBtn').disabled = true;
    document.getElementById('status').innerHTML = '<div class="alert alert-info">Sende...</div>';

    fetch('send_test_mail.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(j => {
            if (j.status) {
                document.getElementById('status').innerHTML = '<div class="alert alert-success">Erfolg: ' + (j.message || 'Mail gesendet') + '</div>';
            } else {
                document.getElementById('status').innerHTML = '<div class="alert alert-danger">Fehler: ' + (j.error || 'Unbekannt') + '</div>';
            }
        })
        .catch(e => {
            document.getElementById('status').innerHTML = '<div class="alert alert-danger">Netzwerkfehler</div>';
        })
        .finally(() => { document.getElementById('sendBtn').disabled = false; });
});
</script>
</body>
</html>
