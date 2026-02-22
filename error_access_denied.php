<?php
/**
 * error_access_denied.php - Fehlerseite für Zugriff verweigert (403 Forbidden)
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/nav/top_nav.php';

http_response_code(403);

$reason = $_GET['reason'] ?? 'Sie haben keine Berechtigung, auf diese Seite zuzugreifen.';
$feature = $_GET['feature'] ?? null;

$is_admin_dir = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false);
$root = $is_admin_dir ? '../' : './';
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zugriff verweigert (403) - EMOS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #1a1a1a;
            color: #dee2e6;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .error-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .error-card {
            background-color: #212529;
            border: 2px solid #dc3545;
            border-radius: 8px;
            padding: 3rem 2rem;
            max-width: 600px;
            width: 100%;
            text-align: center;
        }
        .error-code {
            font-size: 4rem;
            font-weight: 700;
            color: #dc3545;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }
        .error-title {
            font-size: 1.75rem;
            font-weight: 600;
            color: #fff;
            margin-bottom: 1.5rem;
        }
        .error-reason {
            font-size: 1.1rem;
            color: #adb5bd;
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }
        .error-info {
            background-color: #1a1a1a;
            border-left: 4px solid #ffc107;
            padding: 1rem;
            margin: 1.5rem 0;
            text-align: left;
            border-radius: 4px;
        }
        .error-info strong {
            color: #ffc107;
            display: block;
            margin-bottom: 0.5rem;
        }
        .error-info p {
            margin: 0;
            font-size: 0.95rem;
        }
        .error-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        .error-actions a, .error-actions button {
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        .error-actions .btn-home {
            background-color: #0d6efd;
            color: white;
        }
        .error-actions .btn-home:hover {
            background-color: #0b5ed7;
        }
        .error-actions .btn-back {
            background-color: #6c757d;
            color: white;
        }
        .error-actions .btn-back:hover {
            background-color: #5c636a;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-card">
            <div class="error-code">⛔ 403</div>
            <div class="error-title">Zugriff verweigert</div>
            
            <div class="error-reason">
                <?php echo htmlspecialchars($reason); ?>
            </div>
            
            <?php if ($feature): ?>
            <div class="error-info">
                <strong>📋 Erforderliche Berechtigung:</strong>
                <p><?php echo htmlspecialchars($feature); ?></p>
            </div>
            <?php endif; ?>
            
            <div class="error-info">
                <strong>💡 Was kann ich tun?</strong>
                <p>Kontaktieren Sie Ihren Systemadministrator und fordern Sie die erforderlichen Berechtigungen an. Teilen Sie mit, welche Funktion Sie benötigen.</p>
            </div>
            
            <div class="error-actions">
                <a href="<?php echo $root; ?>index.php" class="btn-home">🏠 Zur Startseite</a>
                <button onclick="history.back();" class="btn-back">← Zurück</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
