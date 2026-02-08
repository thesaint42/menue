<?php
/**
 * admin/login.php - Admin Login
 */

require_once '../db.php';
require_once '../script/auth.php';

// Wenn bereits eingeloggt, zum Admin Dashboard
if (isLoggedIn()) {
    header("Location: admin.php");
    exit;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $prefix = $config['database']['prefix'] ?? 'menu_';

    if (!$pdo) {
        $error = "Datenbankfehler. System nicht installiert?";
    } else {
        $stmt = $pdo->prepare("SELECT id, firstname, lastname, password_hash, role_id FROM {$prefix}users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && verifyPassword($password, $user['password_hash'])) {
            setLogin($user['id'], $email, $user['firstname'], $user['lastname'], $user['role_id']);
            header("Location: admin.php");
            exit;
        } else {
            $error = "Ungültige Anmeldedaten oder Account deaktiviert.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Event Menue Order System (EMOS)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Layout: ensure nav stays on top and content stacks below */
        body { min-height: 100vh; display: flex; flex-direction: column; }
        .login-form { max-width: 400px; }
    </style>
</head>
<body class="bg-body-tertiary">

<?php include __DIR__ . '/../nav/top_nav.php'; ?>

<div class="container" style="margin-top: 1rem;">
    <div class="row justify-content-center">
        <div class="col-lg-5">
            <div class="card shadow border-0 bg-dark">
                <div class="card-body p-5">
                    <h2 class="text-center mb-4 text-light">Administrator Login</h2>

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show border-0" role="alert">
                            <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="post">
                        <div class="mb-3">
                            <label for="email" class="form-label text-light">E-Mail Adresse</label>
                            <input type="email" name="email" id="email" class="form-control bg-dark border-secondary text-light" placeholder="admin@example.com" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label text-light">Passwort</label>
                            <input type="password" name="password" id="password" class="form-control bg-dark border-secondary text-light" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Anmelden</button>
                    </form>

                    <hr class="border-secondary my-4">
                    <p class="text-center text-secondary small">
                        Noch nicht installiert? <a href="../install.php" class="text-info">Zur Installation</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
