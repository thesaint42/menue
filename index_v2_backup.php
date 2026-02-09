<?php
/**
 * index.php - Gast-Formular für Menüauswahl
 */

require_once 'db.php';
require_once 'script/auth.php';
require_once 'script/phone.php';

$prefix = $config['database']['prefix'] ?? 'menu_';
$message = "";
$messageType = "info";
$order_success = false;

// Projekt-ID aus URL-Parameter (pin) abrufen
$pin_input = isset($_GET['pin']) ? trim($_GET['pin']) : null;
$project_id = null;
$project = null;

// Wenn PIN eingegeben wurde, lade das Projekt
if ($pin_input) {
    $stmt = $pdo->prepare("SELECT * FROM {$prefix}projects WHERE access_pin = ? AND is_active = 1");
    $stmt->execute([$pin_input]);
    $project = $stmt->fetch();
    
    if ($project) {
        $project_id = $project['id'];
        $_SESSION['current_project'] = $project_id;
    } else {
        $message = "❌ Ungültige PIN. Bitte versuchen Sie es erneut.";
        $messageType = "danger";
    }
} else {
    // Kein PIN-Parameter - zeige PIN-Eingabe
    ?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PIN Eingabe - Event Menue Order System (EMOS)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@24.5.0/build/css/intlTelInput.css">
    <!-- intl-tel-input applied: v24.5.0; build at 2026-02-04 -->
</head>
<body>
<?php include 'nav/top_nav.php'; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-5">
            <div class="card border-0 shadow bg-dark text-light">
                <div class="card-body p-5 text-center">
                    <div class="mb-4" style="font-size: 4rem;">🍽️</div>
                    <h2 class="card-title mb-4">Event Menue Order System (EMOS)</h2>
                    
                        <p class="text-muted mb-4">Bitte geben Sie Ihre Zugangs-PIN ein:</p>

                        <!-- admin-login notice removed; access via menu -->
                    
                    <form method="get" action="index.php">
                        <div class="mb-3">
                            <input type="text" name="pin" class="form-control form-control-lg text-center fs-5 fw-bold" 
                                   placeholder="000000" maxlength="10" required autofocus 
                                   style="letter-spacing: 0.5em; font-family: monospace;">
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold">Zugang</button>
                    </form>
                </div>
            </div>
            
            <div class="alert alert-info text-center mt-4 small">
                💡 Tipp: Sie können auch den QR-Code mit einem Smartphone scannen
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@24.5.0/build/js/intlTelInput.min.js"></script>
<script>
    (function(){
        var phoneVisible = document.getElementById('phone_visible');
        var phoneFull = document.getElementById('phone_full');
        var phoneError = document.getElementById('phone-error');
        if (!phoneVisible || !phoneFull) return;
        try {
            var iti = window.intlTelInput(phoneVisible, {
                initialCountry: 'de',
                preferredCountries: ['de','at','ch'],
                separateDialCode: true,
                utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@24.5.0/build/js/utils.js',
                dropdownContainer: document.body,
                autoHideDialCode: false
            });

            var form = document.getElementById('orderForm');
            if (form) {
                form.addEventListener('submit', function(e){
                    if (phoneVisible.value.trim()) {
                        try {
                            if (iti.isValidNumber()) {
                                phoneFull.value = iti.getNumber();
                                phoneError.classList.add('d-none');
                            } else {
                                e.preventDefault();
                                phoneError.classList.remove('d-none');
                                phoneVisible.focus();
                                return false;
                            }
                        } catch(err) {
                            // allow server-side validation if JS fails
                        }
                    } else {
                        phoneFull.value = '';
                    }
                });
            }
        } catch(e) {}
    })();
</script>
    <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@24.5.0/build/js/intlTelInput.min.js"></script>
<script>
    (function(){
        var phoneVisible = document.getElementById('phone_visible');
        var phoneFull = document.getElementById('phone_full');
        var phoneError = document.getElementById('phone-error');
        if (!phoneVisible || !phoneFull) return;
        try {
            var iti = window.intlTelInput(phoneVisible, {
                initialCountry: 'de',
                preferredCountries: ['de','at','ch'],
                separateDialCode: true,
                utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@24.5.0/build/js/utils.js',
                dropdownContainer: document.body,
                autoHideDialCode: false
            });

            var form = document.getElementById('orderForm');
            if (form) {
                form.addEventListener('submit', function(e){
                    if (phoneVisible.value.trim()) {
                        try {
                            if (iti.isValidNumber()) {
                                phoneFull.value = iti.getNumber();
                                phoneError.classList.add('d-none');
                            } else {
                                e.preventDefault();
                                phoneError.classList.remove('d-none');
                                phoneVisible.focus();
                                return false;
                            }
                        } catch(err) {
                            // allow server-side validation if JS fails
                        }
                    } else {
                        phoneFull.value = '';
                    }
                });
            }
        } catch(e) {}
    })();
</script>
<?php include 'nav/footer.php'; ?>
</body>
</html>
    <?php
    exit;
}

// Wenn kein Projekt gefunden, zeige Fehlermeldung mit PIN-Eingabe
if (!$project_id || !$project) {
    ?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PIN Eingabe - Event Menue Order System (EMOS)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@24.5.0/build/css/intlTelInput.css">
    <!-- intl-tel-input applied: v24.5.0 -->
</head>
<body>
<?php include 'nav/top_nav.php'; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-5">
            <div class="card border-0 shadow bg-dark text-light">
                <div class="card-body p-5 text-center">
                    <div class="mb-4" style="font-size: 4rem;">🍽️</div>
                    <h2 class="card-title mb-4">Event Menue Order System (EMOS)</h2>
                    
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $messageType; ?> mb-4"><?php echo $message; ?></div>
                    <?php endif; ?>
                    
                    <p class="text-muted mb-4">Bitte geben Sie Ihre Zugangs-PIN ein:</p>
                    <!-- admin-login notice removed; access via menu -->
                    
                    <form method="get" action="index.php">
                        <div class="mb-3">
                            <input type="text" name="pin" class="form-control form-control-lg text-center fs-5 fw-bold" 
                                   placeholder="000000" maxlength="10" required autofocus 
                                   value="<?php echo htmlspecialchars($pin_input ?? ''); ?>"
                                   style="letter-spacing: 0.5em; font-family: monospace;">
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold">Zugang</button>
                    </form>
                </div>
            </div>
            
            <div class="alert alert-info text-center mt-4 small">
                💡 Tipp: Sie können auch den QR-Code mit einem Smartphone scannen
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@24.5.0/build/js/intlTelInput.min.js"></script>
<script>
(function(){
    var phoneVisible = document.getElementById('phone_visible');
    var phoneFull = document.getElementById('phone_full');
    var phoneError = document.getElementById('phone-error');
    if (!phoneVisible || !phoneFull) return;
    try {
        var iti = window.intlTelInput(phoneVisible, {
            initialCountry: 'de',
            preferredCountries: ['de','at','ch'],
            separateDialCode: true,
            utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@24.5.0/build/js/utils.js',
            dropdownContainer: document.body,
            autoHideDialCode: false
        });

        var form = document.getElementById('orderForm');
        if (form) {
            form.addEventListener('submit', function(e){
                if (phoneVisible.value.trim()) {
                    try {
                        if (iti.isValidNumber()) {
                            phoneFull.value = iti.getNumber();
                            phoneError.classList.add('d-none');
                        } else {
                            e.preventDefault();
                            phoneError.classList.remove('d-none');
                            phoneVisible.focus();
                            return false;
                        }
                    } catch(err) {
                        // allow server-side validation if JS fails
                    }
                } else {
                    phoneFull.value = '';
                }
            });
        }
    } catch(e) {}
})();
</script>
<?php include 'nav/footer.php'; ?>
<?php
    exit;
}
// Gäste-Statistik
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM {$prefix}guests WHERE project_id = ?");
$stmt->execute([$project_id]);
$guest_count = $stmt->fetch()['count'] ?? 0;

// Formularverarbeitung
if (isset($_POST['submit_order'])) {
    // Preserve submitted values
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname = trim($_POST['lastname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone_raw = trim($_POST['phone'] ?? '');
    $phone = normalize_phone_e164($phone_raw, 'DE');
    $guest_type = $_POST['guest_type'] ?? 'individual';
    $family_size = ($guest_type === 'family') ? (int)($_POST['family_size'] ?? 2) : 1;
    $submitted_members = $_POST['members'] ?? [];
    $submitted_orders = $_POST['orders'] ?? [];

    // Validierung
    if (empty($firstname) || empty($lastname) || empty($email)) {
        $message = "Bitte füllen Sie alle erforderlichen Felder aus.";
        $messageType = "danger";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Ungültige E-Mail Adresse.";
        $messageType = "danger";
    } elseif ($phone_raw !== '' && $phone === false) {
        $message = "Ungültige Telefonnummer. Bitte geben Sie die Nummer mit Ländervorwahl ein.";
        $messageType = "danger";
    } elseif ($guest_count >= $project['max_guests']) {
        $message = "Maximale Anzahl von Gästen erreicht.";
        $messageType = "danger";
    } else {
        // Validate family members: children must be <= 12 years
        $invalid_child_age = false;
        if ($guest_type === 'family' && !empty($submitted_members)) {
            foreach ($submitted_members as $m) {
                $type = $m['type'] ?? 'adult';
                if ($type === 'child') {
                    $age = isset($m['age']) ? (int)$m['age'] : 0;
                    if ($age > 12) {
                        $invalid_child_age = true;
                        break;
                    }
                }
            }
        }

        if ($invalid_child_age) {
            $message = "Fehler: Für ein als 'Kind' markiertes Familienmitglied wurde ein Alter > 12 Jahre angegeben. Bitte wählen Sie 'Erwachsener' oder korrigieren Sie das Alter.";
            $messageType = "danger";
        } else {
            try {
                $pdo->beginTransaction();

                // Guest: if exists (project+email) update, otherwise insert new
                $stmt = $pdo->prepare("SELECT id FROM {$prefix}guests WHERE project_id = ? AND email = ?");
                $stmt->execute([$project_id, $email]);
                $row = $stmt->fetch();
                if ($row && !empty($row['id'])) {
                    $guest_id = (int)$row['id'];
                    $stmt = $pdo->prepare("UPDATE {$prefix}guests SET firstname = ?, lastname = ?, phone = ?, guest_type = ?, family_size = ?, order_status = 'pending' WHERE id = ?");
                    $stmt->execute([$firstname, $lastname, $phone, $guest_type, $family_size, $guest_id]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO {$prefix}guests 
                        (project_id, firstname, lastname, email, phone, guest_type, family_size, order_status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
                    $stmt->execute([$project_id, $firstname, $lastname, $email, $phone, $guest_type, $family_size]);
                    $guest_id = (int)$pdo->lastInsertId();
                }

                // Familienmitglieder: clear and re-insert if family
                $stmt = $pdo->prepare("DELETE FROM {$prefix}family_members WHERE guest_id = ?");
                $stmt->execute([$guest_id]);
                if ($guest_type === 'family' && !empty($submitted_members)) {
                    foreach ($submitted_members as $idx => $member) {
                        $member_name = trim($member['name'] ?? '');
                        $member_type = $member['type'] ?? 'adult';
                        $child_age = ($member_type === 'child') ? (int)($member['age'] ?? 0) : null;
                        $highchair = ($member_type === 'child') ? ((int)($member['highchair'] ?? 0)) : 0;
                        if (!empty($member_name)) {
                            $stmt = $pdo->prepare("INSERT INTO {$prefix}family_members 
                                (guest_id, name, member_type, child_age, highchair_needed) 
                                VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$guest_id, $member_name, $member_type, $child_age, $highchair]);
                        }
                    }
                }

                // Bestellungen speichern
                foreach ($submitted_orders as $dish_id => $quantity) {
                    $q = (int)$quantity;
                    if ($q > 0) {
                        $stmt = $pdo->prepare("INSERT INTO {$prefix}orders (guest_id, dish_id, quantity) 
                                              VALUES (?, ?, ?) 
                                              ON DUPLICATE KEY UPDATE quantity = ?");
                        $stmt->execute([$guest_id, $dish_id, $q, $q]);
                    }
                }

                $pdo->commit();

                // Email versenden
                require_once 'script/mailer.php';
                $mail_result = sendOrderConfirmation($pdo, $prefix, $guest_id);

                $message = "✓ Ihre Bestellung wurde erfolgreich aufgegeben! Eine Bestätigungsemail wird in Kürze versendet.";
                $messageType = "success";
                $order_success = true;

            } catch (Exception $e) {
                $pdo->rollBack();
                $message = "Fehler beim Speichern: " . $e->getMessage();
                $messageType = "danger";
            }
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
    <title><?php echo htmlspecialchars($project['name']); ?> - Event Menue Order System (EMOS)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@24.5.0/build/css/intlTelInput.css">
</head>
<body>

<?php include 'nav/top_nav.php'; ?>

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
            <?php if ($order_success): ?>
                <div class="card border-0 shadow mb-4">
                    <div class="card-body p-4 text-center">
                        <h3 class="text-success">✓ Ihre Bestellung wurde erfolgreich aufgegeben!</h3>
                        <p class="mb-3">Eine Bestätigungsemail wird in Kürze versendet.</p>
                        <div class="d-flex justify-content-center gap-2">
                            <a href="index.php?pin=<?php echo urlencode($pin_input); ?>" class="btn btn-primary">Neue Bestellung auslösen</a>
                            <a href="index.php" class="btn btn-secondary">Zurück zur Startseite (PIN eingeben)</a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
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
                                <input type="text" name="firstname" class="form-control" required value="<?php echo htmlspecialchars($firstname ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nachname *</label>
                                <input type="text" name="lastname" class="form-control" required value="<?php echo htmlspecialchars($lastname ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">E-Mail *</label>
                                <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($email ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Telefon</label>
                                <input type="tel" id="phone_visible" name="phone_visible" class="form-control" value="<?php echo htmlspecialchars($phone_raw ?? ''); ?>">
                                <input type="hidden" name="phone" id="phone_full" value="<?php echo htmlspecialchars($phone ?? ''); ?>">
                                <div id="phone-error" class="text-danger small mt-1 d-none">Ungültige Telefonnummer. Bitte prüfen Sie die Eingabe (z.B. Ländervorwahl).</div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Art der Bestellung *</label>
                                <select name="guest_type" id="guestType" class="form-select" required onchange="updateFamilySize()">
                                    <option value="individual" <?php echo (($guest_type ?? '') !== 'family') ? 'selected' : ''; ?>>Einzelperson</option>
                                    <option value="family" <?php echo (($guest_type ?? '') === 'family') ? 'selected' : ''; ?>>Familie/Haushalt</option>
                                </select>
                            </div>
                            <div class="col-md-6" id="familySizeContainer" style="display: <?php echo (($guest_type ?? '') === 'family') ? 'block' : 'none'; ?>;">
                                <label class="form-label">Anzahl Personen *</label>
                                <input type="number" name="family_size" id="familySize" class="form-control" min="2" value="<?php echo intval($family_size ?? 2); ?>" onchange="updateFamilyForm()">
                            </div>
                        </div>

                        <!-- Familienmitglieder Details -->
                        <div id="familyMembersContainer" style="display: <?php echo (($guest_type ?? '') === 'family') ? 'block' : 'none'; ?>; margin-top: 20px;">
                            <hr class="my-4">
                            <h6 class="mb-3">Familienmitglieder Details</h6>
                            <div id="childInfo" class="alert alert-warning small d-none">Hinweis: Kinder gelten nur bis zum Alter von 12 Jahren. Bei älteren Personen bitte 'Erwachsener' wählen.</div>
                            <div id="membersForm">
                                <?php
                                // If form was submitted, render members server-side to preserve inputs
                                if (($guest_type ?? '') === 'family') {
                                    $cnt = max(1, intval($family_size ?? 2));
                                    for ($i = 0; $i < $cnt; $i++) {
                                        $m = $submitted_members[$i] ?? [];
                                        $mname = htmlspecialchars($m['name'] ?? '');
                                        $mtype = $m['type'] ?? 'adult';
                                        $mage = isset($m['age']) ? intval($m['age']) : '';
                                        $mhigh = !empty($m['highchair']) ? 'checked' : '';
                                        ?>
                                        <div class="card bg-dark border-secondary mb-3" style="padding: 15px;">
                                            <h6 class="mb-3">Person <?php echo ($i + 1); ?></h6>
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Name *</label>
                                                    <input type="text" name="members[<?php echo $i; ?>][name]" class="form-control" required value="<?php echo $mname; ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Typ *</label>
                                                    <select name="members[<?php echo $i; ?>][type]" class="form-select member-type" onchange="updateMemberFields(<?php echo $i; ?>)">
                                                        <option value="adult" <?php echo ($mtype !== 'child') ? 'selected' : ''; ?>>Erwachsener</option>
                                                        <option value="child" <?php echo ($mtype === 'child') ? 'selected' : ''; ?>>Kind</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6 member-age-<?php echo $i; ?>" style="display: <?php echo ($mtype === 'child') ? 'block' : 'none'; ?>;">
                                                    <label class="form-label">Alter (Jahre)</label>
                                                    <input type="number" name="members[<?php echo $i; ?>][age]" class="form-control" min="1" max="120" value="<?php echo ($mage === '') ? '' : intval($mage); ?>">
                                                </div>
                                                <div class="col-md-6 member-highchair-<?php echo $i; ?>" style="display: <?php echo ($mtype === 'child') ? 'block' : 'none'; ?>;">
                                                    <label class="form-label">
                                                        <input type="checkbox" name="members[<?php echo $i; ?>][highchair]" value="1" class="form-check-input" <?php echo $mhigh; ?>>
                                                        Hochstuhl benötigt
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <?php
                                    }
                                }
                                ?>
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
                                                <input type="number" name="orders[<?php echo $dish['id']; ?>]" class="qty-input" value="<?php echo intval($submitted_orders[$dish['id']] ?? 0); ?>" min="0" max="99">
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
            <?php endif; ?>

            <!-- Gäste-Statistik -->
            <div class="alert alert-secondary small">
                <strong><?php echo $guest_count; ?></strong> von <strong><?php echo $project['max_guests']; ?></strong> verfügbaren Plätzen belegt.
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<style>
    .navbar-collapse {
        position: fixed !important;
        top: 60px;
        right: 0;
        left: auto;
        background-color: #212529;
        border-left: 1px solid #495057;
        border-bottom: 1px solid #495057;
        z-index: 1000;
        width: auto;
        min-width: 250px;
        box-shadow: -2px 2px 8px rgba(0,0,0,0.3);
        visibility: hidden;
        opacity: 0;
        transition: opacity 0.3s ease-in-out, visibility 0.3s ease-in-out;
    }
    
    .navbar-collapse.show {
        visibility: visible;
        opacity: 1;
    }
    
    .navbar-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        z-index: 999;
        visibility: hidden;
        opacity: 0;
        transition: opacity 0.3s ease-in-out, visibility 0.3s ease-in-out;
    }
    
    .navbar-backdrop.show {
        visibility: visible;
        opacity: 1;
    }
</style>
<script>
function changeQty(btn, change) {
    const input = btn.parentElement.querySelector('.qty-input');
    let val = parseInt(input.value) || 0;
    val = Math.max(0, Math.min(99, val + change));
    input.value = val;
}

function updateFamilySize() {
    const type = document.getElementById('guestType').value;
    const container = document.getElementById('familySizeContainer');
    const familyMembers = document.getElementById('familyMembersContainer');
    
    if (type === 'family') {
        container.style.display = 'block';
        document.getElementById('familySize').required = true;
        familyMembers.style.display = 'block';
        updateFamilyForm();
    } else {
        container.style.display = 'none';
        document.getElementById('familySize').required = false;
        familyMembers.style.display = 'none';
        document.getElementById('membersForm').innerHTML = '';
    }
}

function updateFamilyForm() {
    const size = parseInt(document.getElementById('familySize').value) || 2;
    const container = document.getElementById('membersForm');
    let html = '';
    
    for (let i = 0; i < size; i++) {
        html += `
            <div class="card bg-dark border-secondary mb-3" style="padding: 15px;">
                <h6 class="mb-3">Person ${i + 1}</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Name *</label>
                        <input type="text" name="members[${i}][name]" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Typ *</label>
                        <select name="members[${i}][type]" class="form-select member-type" onchange="updateMemberFields(${i})">
                            <option value="adult">Erwachsener</option>
                            <option value="child">Kind</option>
                        </select>
                    </div>
                    <div class="col-md-6 member-age-${i}" style="display: none;">
                        <label class="form-label">Alter (Jahre)</label>
                        <input type="number" name="members[${i}][age]" class="form-control" min="1" max="12">
                    </div>
                    <div class="col-md-6 member-highchair-${i}" style="display: none;">
                        <label class="form-label">
                            <input type="checkbox" name="members[${i}][highchair]" value="1" class="form-check-input">
                            Hochstuhl benötigt
                        </label>
                    </div>
                </div>
            </div>
        `;
    }
    
    container.innerHTML = html;
    
    // Update alle Member-Felder
    for (let i = 0; i < size; i++) {
        updateMemberFields(i);
    }
}

function updateMemberFields(index) {
    const select = document.querySelector(`select[name="members[${index}][type]"]`);
    const ageField = document.querySelector(`.member-age-${index}`);
    const highchairField = document.querySelector(`.member-highchair-${index}`);
    
    if (select.value === 'child') {
        ageField.style.display = 'block';
        highchairField.style.display = 'block';
    } else {
        ageField.style.display = 'none';
        highchairField.style.display = 'none';
    }
    checkChildInfo();
}

function checkChildInfo() {
    const selects = document.querySelectorAll('.member-type');
    let anyChild = false;
    selects.forEach(s => { if (s.value === 'child') anyChild = true; });
    const info = document.getElementById('childInfo');
    if (!info) return;
    if (anyChild) info.classList.remove('d-none'); else info.classList.add('d-none');
}

// Validate on submit: child ages must be <= 12
document.addEventListener('DOMContentLoaded', function(){
    const form = document.getElementById('orderForm');
    if (!form) return;
    form.addEventListener('submit', function(e){
        const selects = document.querySelectorAll('.member-type');
        for (let i = 0; i < selects.length; i++) {
            if (selects[i].value === 'child') {
                const ageInput = document.querySelector(`input[name="members[${i}][age]"]`);
                if (ageInput) {
                    const age = parseInt(ageInput.value) || 0;
                    if (age > 12) {
                        e.preventDefault();
                        alert('Fehler: Für ein als Kind markiertes Familienmitglied wurde ein Alter > 12 Jahre angegeben. Bitte wählen Sie "Erwachsener" oder korrigieren Sie das Alter.');
                        ageInput.focus();
                        return false;
                    }
                }
            }
        }
    });
    // run once to show child info when server-rendered members exist
    if (typeof checkChildInfo === 'function') checkChildInfo();
});
</script>
<?php include 'nav/footer.php'; ?>
</body>
</html>
