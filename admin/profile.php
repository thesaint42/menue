<?php
/**
 * admin/profile.php - Admin Profil
 */

require_once '../db.php';
require_once '../script/auth.php';

checkLogin();

$prefix = $config['database']['prefix'] ?? 'menu_';
$user_id = $_SESSION['user_id'];
$message = "";
$messageType = "info";

// Profil aktualisieren
if (isset($_POST['update_profile'])) {
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $current_password = $_POST['current_password'];

    // Passwort prüfen
    $stmt = $pdo->prepare("SELECT password_hash FROM {$prefix}users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch();

    if (!verifyPassword($current_password, $user_data['password_hash'])) {
        $message = "Aktuelles Passwort ist nicht korrekt.";
        $messageType = "danger";
    } else {
        // Basisinfos aktualisieren
        $stmt = $pdo->prepare("UPDATE {$prefix}users SET firstname = ?, lastname = ? WHERE id = ?");
        $stmt->execute([$firstname, $lastname, $user_id]);

        // Neues Passwort
        if (!empty($_POST['new_password'])) {
            if ($_POST['new_password'] !== $_POST['new_password_confirm']) {
                $message = "Neue Passwörter stimmen nicht überein.";
                $messageType = "danger";
            } elseif (strlen($_POST['new_password']) < 8) {
                $message = "Passwort muss mindestens 8 Zeichen lang sein.";
                $messageType = "danger";
            } else {
                $pw_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE {$prefix}users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$pw_hash, $user_id]);
                $message = "✓ Profil und Passwort aktualisiert.";
                $messageType = "success";
            }
        } else {
            $message = "✓ Profil aktualisiert.";
            $messageType = "success";
        }

        // Session aktualisieren
        $_SESSION['user_name'] = $firstname . ' ' . $lastname;
        $_SESSION['firstname'] = $firstname;
        $_SESSION['lastname'] = $lastname;
    }
}

// Benutzer laden
$stmt = $pdo->prepare("SELECT * FROM {$prefix}users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

require_once '../script/auth.php';
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>Mein Profil - Menüwahl</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include '../nav/top_nav.php'; ?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card border-0 shadow">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0">Mein Profil</h5>
                </div>
                <div class="card-body p-4">
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Vorname</label>
                            <input type="text" name="firstname" class="form-control" value="<?php echo htmlspecialchars($user['firstname']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nachname</label>
                            <input type="text" name="lastname" class="form-control" value="<?php echo htmlspecialchars($user['lastname']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">E-Mail</label>
                            <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                        </div>

                        <hr>

                        <h6>Passwort ändern</h6>
                        <div class="mb-3">
                            <label class="form-label">Aktuelles Passwort *</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Neues Passwort (optional)</label>
                            <input type="password" name="new_password" class="form-control" minlength="8">
                            <small class="text-muted">Mindestens 8 Zeichen</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Passwort wiederholen</label>
                            <input type="password" name="new_password_confirm" class="form-control" minlength="8">
                        </div>

                        <button type="submit" name="update_profile" class="btn btn-primary w-100 fw-bold">Speichern</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
