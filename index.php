<?php
/**
 * index.php - Gast-Formular für Menüauswahl
 */

require_once 'db.php';
require_once 'script/auth.php';

$prefix = $config['database']['prefix'] ?? 'menu_';
$message = "";
$messageType = "info";

// Projekt-ID aus URL oder Session
$project_id = isset($_GET['project']) ? (int)$_GET['project'] : ($_SESSION['current_project'] ?? null);

if (!$project_id || !$pdo) {
    die("Projekt nicht gefunden oder System nicht initialisiert.");
}

// Projekt laden
$stmt = $pdo->prepare("SELECT * FROM {$prefix}projects WHERE id = ? AND is_active = 1");
$stmt->execute([$project_id]);
$project = $stmt->fetch();

if (!$project) {
    die("Projekt nicht gefunden.");
}

// Gäste-Statistik
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM {$prefix}guests WHERE project_id = ?");
$stmt->execute([$project_id]);
$guest_count = $stmt->fetch()['count'] ?? 0;

// Formularverarbeitung
if (isset($_POST['submit_order'])) {
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $guest_type = $_POST['guest_type']; // 'individual' oder 'family'
    $age_group = $_POST['age_group']; // 'adult' oder 'child'
    $child_age = ($age_group === 'child') ? (int)$_POST['child_age'] : null;
    $family_size = ($guest_type === 'family') ? (int)$_POST['family_size'] : 1;

    // Validierung
    if (empty($firstname) || empty($lastname) || empty($email)) {
        $message = "Bitte füllen Sie alle erforderlichen Felder aus.";
        $messageType = "danger";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Ungültige E-Mail Adresse.";
        $messageType = "danger";
    } elseif ($guest_count >= $project['max_guests']) {
        $message = "Maximale Anzahl von Gästen erreicht.";
        $messageType = "danger";
    } else {
        try {
            $pdo->beginTransaction();

            // Gast eintragen (oder Update falls bereits vorhanden)
            $stmt = $pdo->prepare("INSERT INTO {$prefix}guests 
                (project_id, firstname, lastname, email, phone, guest_type, age_group, child_age, family_size, order_status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending') 
                ON DUPLICATE KEY UPDATE 
                phone = ?, guest_type = ?, age_group = ?, child_age = ?, family_size = ?, order_status = 'pending'");
            
            $stmt->execute([
                $project_id, $firstname, $lastname, $email, $phone, $guest_type, $age_group, $child_age, $family_size,
                $phone, $guest_type, $age_group, $child_age, $family_size
            ]);

            $guest_id = $pdo->lastInsertId() ?: $pdo->prepare("SELECT id FROM {$prefix}guests WHERE project_id = ? AND email = ?")->execute([$project_id, $email]);
            
            // Gast-ID nochmal abrufen falls UPDATE
            if (!$guest_id || $guest_id == 0) {
                $stmt = $pdo->prepare("SELECT id FROM {$prefix}guests WHERE project_id = ? AND email = ?");
                $stmt->execute([$project_id, $email]);
                $guest_id = $stmt->fetch()['id'];
            }

            // Bestellungen speichern
            foreach ($_POST['orders'] as $dish_id => $quantity) {
                if ($quantity > 0) {
                    $stmt = $pdo->prepare("INSERT INTO {$prefix}orders (guest_id, dish_id, quantity) 
                                          VALUES (?, ?, ?) 
                                          ON DUPLICATE KEY UPDATE quantity = ?");
                    $stmt->execute([$guest_id, $dish_id, $quantity, $quantity]);
                }
            }

            $pdo->commit();

            // Email versenden
            require_once 'script/mailer.php';
            $mail_result = sendOrderConfirmation($pdo, $prefix, $guest_id);

            $message = "✓ Ihre Bestellung wurde erfolgreich aufgegeben! Eine Bestätigungsemail wird in Kürze versendet.";
            $messageType = "success";

        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Fehler beim Speichern: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Menü-Kategorien und Gerichte laden
$stmt = $pdo->prepare("SELECT * FROM {$prefix}menu_categories ORDER BY sort_order");
$stmt->execute();
$categories = $stmt->fetchAll();

$dishes_by_category = [];
foreach ($categories as $cat) {
    $stmt = $pdo->prepare("SELECT * FROM {$prefix}dishes WHERE project_id = ? AND category_id = ? AND is_active = 1 ORDER BY sort_order");
    $stmt->execute([$project_id, $cat['id']]);
    $dishes_by_category[$cat['id']] = [
        'category' => $cat,
        'dishes' => $stmt->fetchAll()
    ];
}
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($project['name']); ?> - Menüwahl</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-dark bg-dark border-bottom border-secondary mb-4">
    <div class="container-fluid px-4">
        <span class="navbar-brand fw-bold">
            🍽️ <?php echo htmlspecialchars($project['name']); ?>
        </span>
    </div>
</nav>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <!-- Projekt-Informationen -->
            <div class="card border-0 shadow mb-4">
                <div class="card-body p-4">
                    <h2 class="card-title mb-3">Willkommen!</h2>
                    <?php if ($project['description']): ?>
                        <p class="text-muted"><?php echo htmlspecialchars($project['description']); ?></p>
                    <?php endif; ?>
                    
                    <?php if ($project['location'] || $project['contact_person']): ?>
                        <hr>
                        <h5>Veranstaltungsdetails</h5>
                        <?php if ($project['location']): ?>
                            <p class="mb-1"><strong>Ort:</strong> <?php echo htmlspecialchars($project['location']); ?></p>
                        <?php endif; ?>
                        <?php if ($project['contact_person']): ?>
                            <p class="mb-1"><strong>Ansprechpartner:</strong> <?php echo htmlspecialchars($project['contact_person']); ?></p>
                        <?php endif; ?>
                        <?php if ($project['contact_phone']): ?>
                            <p class="mb-1"><strong>Telefon:</strong> <a href="tel:<?php echo urlencode($project['contact_phone']); ?>"><?php echo htmlspecialchars($project['contact_phone']); ?></a></p>
                        <?php endif; ?>
                        <?php if ($project['contact_email']): ?>
                            <p class="mb-1"><strong>Email:</strong> <a href="mailto:<?php echo urlencode($project['contact_email']); ?>"><?php echo htmlspecialchars($project['contact_email']); ?></a></p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Mitteilungen -->
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show border-0" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Bestellformular -->
            <form method="post" id="orderForm">
                <!-- PERSÖNLICHE DATEN -->
                <div class="card border-0 shadow mb-4">
                    <div class="card-header bg-primary text-white py-3">
                        <h5 class="mb-0">1. Ihre Daten</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Vorname *</label>
                                <input type="text" name="firstname" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nachname *</label>
                                <input type="text" name="lastname" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">E-Mail *</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Telefon</label>
                                <input type="tel" name="phone" class="form-control">
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Art der Bestellung *</label>
                                <select name="guest_type" id="guestType" class="form-select" required onchange="updateFamilySize()">
                                    <option value="individual">Einzelperson</option>
                                    <option value="family">Familie/Haushalt</option>
                                </select>
                            </div>
                            <div class="col-md-6" id="familySizeContainer" style="display: none;">
                                <label class="form-label">Anzahl Personen *</label>
                                <input type="number" name="family_size" id="familySize" class="form-control" min="2" value="2">
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Erwachsen oder Kind? *</label>
                                <select name="age_group" id="ageGroup" class="form-select" required onchange="updateChildAge()">
                                    <option value="adult">Erwachsener</option>
                                    <option value="child">Kind (unter 12 Jahren)</option>
                                </select>
                            </div>
                            <div class="col-md-6" id="childAgeContainer" style="display: none;">
                                <label class="form-label">Alter des Kindes (Jahre) *</label>
                                <input type="number" name="child_age" id="childAge" class="form-control" min="1" max="11">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MENÜAUSWAHL -->
                <div class="card border-0 shadow mb-4">
                    <div class="card-header bg-primary text-white py-3">
                        <h5 class="mb-0">2. Menüauswahl</h5>
                    </div>
                    <div class="card-body p-4">
                        <?php if (empty($dishes_by_category) || !array_filter($dishes_by_category, fn($cat) => !empty($cat['dishes']))): ?>
                            <div class="alert alert-info">Keine Menüs verfügbar.</div>
                        <?php else: ?>
                            <?php foreach ($dishes_by_category as $cat_id => $data): ?>
                                <?php if (empty($data['dishes'])) continue; ?>
                                
                                <div class="mb-4">
                                    <h5 class="text-info mb-3"><?php echo htmlspecialchars($data['category']['name']); ?></h5>
                                    
                                    <?php foreach ($data['dishes'] as $dish): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-3 p-3 bg-dark rounded">
                                            <div>
                                                <strong><?php echo htmlspecialchars($dish['name']); ?></strong>
                                                <?php if ($dish['description']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($dish['description']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="quantity-selector">
                                                <button type="button" class="qty-minus" onclick="changeQty(this, -1)">−</button>
                                                <input type="number" name="orders[<?php echo $dish['id']; ?>]" class="qty-input" value="0" min="0" max="99">
                                                <button type="button" class="qty-plus" onclick="changeQty(this, 1)">+</button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <hr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- SUBMIT -->
                <div class="d-grid gap-2 mb-4">
                    <button type="submit" name="submit_order" class="btn btn-success btn-lg fw-bold">Bestellung aufgeben</button>
                </div>
            </form>

            <!-- Gäste-Statistik -->
            <div class="alert alert-secondary small">
                <strong><?php echo $guest_count; ?></strong> von <strong><?php echo $project['max_guests']; ?></strong> verfügbaren Plätzen belegt.
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function changeQty(btn, change) {
    const input = btn.parentElement.querySelector('.qty-input');
    let val = parseInt(input.value) || 0;
    val = Math.max(0, Math.min(99, val + change));
    input.value = val;
}

function updateFamilySize() {
    const type = document.getElementById('guestType').value;
    document.getElementById('familySizeContainer').style.display = type === 'family' ? 'block' : 'none';
    if (type === 'family') {
        document.getElementById('familySize').required = true;
    }
}

function updateChildAge() {
    const age = document.getElementById('ageGroup').value;
    document.getElementById('childAgeContainer').style.display = age === 'child' ? 'block' : 'none';
    if (age === 'child') {
        document.getElementById('childAge').required = true;
    }
}
</script>
</body>
</html>
