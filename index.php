<?php
/**
 * index.php v3.0 - Personenspezifische Menüauswahl mit order-id
 */

require_once 'db.php';
require_once 'script/auth.php';
require_once 'script/phone.php';
require_once 'script/order_system.php';

$prefix = $config['database']['prefix'] ?? 'menu_';
$message = "";
$messageType = "info";
$order_success = false;
$order_id_display = null;

// Schritt 1: PIN-Eingabe, wenn keine PIN vorhanden
$pin_input = isset($_GET['pin']) ? trim($_GET['pin']) : null;
$project_id = null;
$project = null;

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
}

// Schritt 2: Startseite - Neue Bestellung oder Bearbeiten
$action = $_GET['action'] ?? null;
$edit_order_id = $_GET['order_id'] ?? null;
$existing_order = null;

if (!$project && !$pin_input) {
    // Keine PIN -> zeige PIN-Eingabeseite
    include 'views/pin_entry.php';
    exit;
}

if ($project && !$action && !$edit_order_id) {
    // PIN gültig, aber kein Aktionswahl -> zeige Startseite
    include 'views/order_start.php';
    exit;
}

// Schritt 3: Bestellung laden (falls edit_order_id)
if ($edit_order_id) {
    $existing_order = load_order_by_id($pdo, $prefix, $edit_order_id);
    if (!$existing_order) {
        $message = "❌ Bestellung nicht gefunden. Bitte prüfen Sie die Order-ID.";
        $messageType = "danger";
        include 'views/order_start.php';
        exit;
    }
    // Projekt-Kontext aus Bestellung setzen
    if ($existing_order['project_id'] != $project_id) {
        $stmt = $pdo->prepare("SELECT * FROM {$prefix}projects WHERE id = ?");
        $stmt->execute([$existing_order['project_id']]);
        $project = $stmt->fetch();
        $project_id = $project['id'];
    }
}

// Schritt 4: Menükategorien und Gerichte laden
$stmt = $pdo->prepare("
    SELECT mc.id as category_id, mc.name as category_name, mc.sort_order,
           d.id as dish_id, d.name as dish_name, d.description, d.price, d.sort_order as dish_sort
    FROM {$prefix}menu_categories mc
    LEFT JOIN {$prefix}dishes d ON mc.id = d.category_id AND d.project_id = ?
    WHERE d.project_id = ? OR mc.id IN (
        SELECT DISTINCT category_id FROM {$prefix}dishes WHERE project_id = ?
    )
    ORDER BY mc.sort_order, d.sort_order
");
$stmt->execute([$project_id, $project_id, $project_id]);
$menu_items = $stmt->fetchAll();

$categories = [];
foreach ($menu_items as $item) {
    if (!isset($categories[$item['category_id']])) {
        $categories[$item['category_id']] = [
            'id' => $item['category_id'],
            'name' => $item['category_name'],
            'dishes' => []
        ];
    }
    if ($item['dish_id']) {
        $categories[$item['category_id']]['dishes'][] = [
            'id' => $item['dish_id'],
            'name' => $item['dish_name'],
            'description' => $item['description'],
            'price' => $item['price']
        ];
    }
}

// Schritt 5: Formular-Submit verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    // Basis-Daten validieren
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname = trim($_POST['lastname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone_raw = trim($_POST['phone_full'] ?? '');
    $guest_type = $_POST['guest_type'] ?? 'individual';
    
    if (empty($firstname)) $errors[] = "Vorname ist erforderlich.";
    if (empty($lastname)) $errors[] = "Nachname ist erforderlich.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Gültige E-Mail ist erforderlich.";
    
    // Telefon validieren (optional)
    $phone_parsed = null;
    if ($phone_raw) {
        $phone_parsed = parsePhone($phone_raw);
        if (!$phone_parsed['valid']) {
            $errors[] = "Telefonnummer ist ungültig.";
        }
    }
    
    // Personen-Array aufbauen
    $persons = [[
        'type' => 'guest',
        'name' => $firstname . ' ' . $lastname,
        'age_group' => null
    ]];
    
    if ($guest_type === 'family') {
        $member_count = intval($_POST['member_count'] ?? 0);
        for ($i = 1; $i <= $member_count; $i++) {
            $member_name = trim($_POST["member_{$i}_name"] ?? '');
            $member_type = $_POST["member_{$i}_type"] ?? 'adult';
            $member_age = $_POST["member_{$i}_age"] ?? null;
            
            if (empty($member_name)) {
                $errors[] = "Name von Person #{$i} ist erforderlich.";
                continue;
            }
            
            if ($member_type === 'child') {
                if (!$member_age || $member_age > 12) {
                    $errors[] = "Alter von {$member_name} muss zwischen 1 und 12 Jahren liegen.";
                }
            }
            
            $persons[] = [
                'type' => $member_type,
                'name' => $member_name,
                'age_group' => $member_age
            ];
        }
    }
    
    // Bestellungen validieren (jede Person muss mind. 1 Gericht haben)
    $orders_array = [];
    foreach ($persons as $idx => $person) {
        foreach ($categories as $cat) {
            $dish_key = "person_{$idx}_cat_{$cat['id']}";
            $dish_id = intval($_POST[$dish_key] ?? 0);
            
            if ($dish_id > 0) {
                $orders_array[] = [
                    'person_index' => $idx,
                    'category_id' => $cat['id'],
                    'dish_id' => $dish_id
                ];
            }
        }
    }
    
    if (empty($orders_array)) {
        $errors[] = "Bitte wählen Sie mindestens ein Gericht aus.";
    }
    
    // Speichern, wenn keine Fehler
    if (empty($errors)) {
        $order_data = [
            'order_id' => $edit_order_id ?? null,
            'project_id' => $project_id,
            'email' => $email,
            'guest' => [
                'firstname' => $firstname,
                'lastname' => $lastname,
                'phone' => $phone_parsed ? $phone_parsed['e164'] : null,
                'phone_raw' => $phone_raw,
                'guest_type' => $guest_type
            ],
            'persons' => $persons,
            'orders' => $orders_array
        ];
        
        $result = save_order($pdo, $prefix, $order_data);
        
        if ($result['success']) {
            $order_success = true;
            $order_id_display = $result['order_id'];
            $message = $result['message'];
            $messageType = "success";
            
            // TODO: Bestätigungs-Mail senden
            
        } else {
            $message = "❌ " . $result['message'];
            $messageType = "danger";
        }
    } else {
        $message = "❌ " . implode("<br>", $errors);
        $messageType = "danger";
    }
}

// Formulardaten vorbefüllen (bei Fehler oder Edit)
$form_data = [
    'firstname' => $_POST['firstname'] ?? ($existing_order['guest']['firstname'] ?? ''),
    'lastname' => $_POST['lastname'] ?? ($existing_order['guest']['lastname'] ?? ''),
    'email' => $_POST['email'] ?? ($existing_order['email'] ?? ''),
    'phone_raw' => $_POST['phone_raw'] ?? ($existing_order['guest']['phone_raw'] ?? ''),
    'guest_type' => $_POST['guest_type'] ?? ($existing_order['guest']['guest_type'] ?? 'individual'),
    'members' => []
];

if ($existing_order && isset($existing_order['persons'])) {
    foreach ($existing_order['persons'] as $idx => $person) {
        if ($idx === 0) continue; // Skip Hauptperson
        $form_data['members'][] = [
            'name' => $person['name'],
            'type' => $person['type'],
            'age_group' => $person['age_group']
        ];
    }
} elseif (isset($_POST['member_count'])) {
    $member_count = intval($_POST['member_count']);
    for ($i = 1; $i <= $member_count; $i++) {
        $form_data['members'][] = [
            'name' => $_POST["member_{$i}_name"] ?? '',
            'type' => $_POST["member_{$i}_type"] ?? 'adult',
            'age_group' => $_POST["member_{$i}_age"] ?? null
        ];
    }
}

// Bestell-Auswahl vorbefüllen
$form_data['orders'] = [];
if ($existing_order && isset($existing_order['orders'])) {
    foreach ($existing_order['orders'] as $order) {
        $form_data['orders'][$order['person_index']][$order['category_id']] = $order['dish_id'];
    }
} elseif (isset($_POST)) {
    foreach ($_POST as $key => $value) {
        if (preg_match('/^person_(\d+)_cat_(\d+)$/', $key, $matches)) {
            $person_idx = intval($matches[1]);
            $cat_id = intval($matches[2]);
            $form_data['orders'][$person_idx][$cat_id] = intval($value);
        }
    }
}

?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($project['name']); ?> - EMOS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@24.5.0/build/css/intlTelInput.css">
</head>
<body>
<?php include 'nav/top_nav.php'; ?>

<div class="container py-3 py-md-4" style="max-width: 800px;">
    
    <?php if ($order_success): ?>
        <!-- Erfolgsseite -->
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-success">
                    <div class="card-body text-center p-5">
                        <div class="display-1 mb-4">✅</div>
                        <h2 class="card-title mb-3">Bestellung erfolgreich!</h2>
                        <p class="lead mb-4"><?php echo htmlspecialchars($message); ?></p>
                        
                        <div class="alert alert-info">
                            <h5>📋 Ihre Order-ID:</h5>
                            <p class="font-monospace fs-4 mb-2"><strong><?php echo htmlspecialchars($order_id_display); ?></strong></p>
                            <p class="small text-muted mb-0">Bitte notieren Sie diese ID. Sie benötigen sie, um Ihre Bestellung später zu bearbeiten.</p>
                        </div>
                        
                        <hr class="my-4">
                        
                        <div class="d-grid gap-3">
                            <a href="index.php?pin=<?php echo htmlspecialchars($project['access_pin']); ?>&action=edit&order_id=<?php echo htmlspecialchars($order_id_display); ?>" 
                               class="btn btn-outline-primary btn-lg">
                                📝 Bestellung bearbeiten
                            </a>
                            <a href="index.php?pin=<?php echo htmlspecialchars($project['access_pin']); ?>&action=new" 
                               class="btn btn-primary btn-lg">
                                ➕ Neue Bestellung erstellen
                            </a>
                            <a href="index.php" class="btn btn-outline-secondary">
                                🏠 Zur Startseite
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    
    <?php else: ?>
        <!-- Bestellformular -->
        <div class="row mb-4">
            <div class="col">
                <h1><?php echo htmlspecialchars($project['name']); ?></h1>
                <p class="text-muted"><?php echo $edit_order_id ? '✏️ Bestellung bearbeiten' : '🍽️ Neue Bestellung aufgeben'; ?></p>
            </div>
        </div>
        
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <form method="post" id="orderForm">
            
            <!-- Gast-Daten -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">👤 Ihre Daten</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label">Vorname *</label>
                            <input type="text" name="firstname" class="form-control form-control-lg" required
                                   value="<?php echo htmlspecialchars($form_data['firstname']); ?>">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label">Nachname *</label>
                            <input type="text" name="lastname" class="form-control form-control-lg" required
                                   value="<?php echo htmlspecialchars($form_data['lastname']); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label d-block">E-Mail *</label>
                            <input type="email" name="email" class="form-control form-control-lg" required
                                   value="<?php echo htmlspecialchars($form_data['email']); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label d-block">Telefon (optional)</label>
                            <input type="tel" id="phone_visible" class="form-control form-control-lg"
                                   value="<?php echo htmlspecialchars($form_data['phone_raw']); ?>">
                            <input type="hidden" id="phone_full" name="phone_full">
                            <div id="phone-error" class="invalid-feedback d-none">Ungültige Telefonnummer</div>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="d-grid gap-2">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="guest_type" id="type_individual" 
                                   value="individual" <?php echo ($form_data['guest_type'] === 'individual') ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="type_individual">Nur für mich</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="guest_type" id="type_family" 
                                   value="family" <?php echo ($form_data['guest_type'] === 'family') ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="type_family">Für mich und weitere Personen</label>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Familienmitglieder -->
            <div class="card mb-4 <?php echo ($form_data['guest_type'] === 'family') ? '' : 'd-none'; ?>" id="familySection">
                <div class="card-header">
                    <h5 class="mb-3">👥 Weitere Personen</h5>
                    <div class="d-grid">
                        <button type="button" class="btn btn-outline-primary" id="addMemberBtn">+ Person hinzufügen</button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="membersContainer">
                        <?php foreach ($form_data['members'] as $idx => $member): ?>
                        <div class="member-row border-bottom pb-3 mb-3">
                            <div class="row g-2">
                                <div class="col-12">
                                    <input type="text" name="member_<?php echo $idx + 1; ?>_name" class="form-control form-control-lg" 
                                           placeholder="Name" value="<?php echo htmlspecialchars($member['name']); ?>" required>
                                </div>
                                <div class="col-8">
                                    <select name="member_<?php echo $idx + 1; ?>_type" class="form-select form-select-lg member-type-select">
                                        <option value="adult" <?php echo ($member['type'] === 'adult') ? 'selected' : ''; ?>>Erwachsener</option>
                                        <option value="child" <?php echo ($member['type'] === 'child') ? 'selected' : ''; ?>>Kind (≤12)</option>
                                    </select>
                                </div>
                                <div class="col-4 member-age-col <?php echo ($member['type'] === 'child') ? '' : 'd-none'; ?>">
                                    <input type="number" name="member_<?php echo $idx + 1; ?>_age" class="form-control form-control-lg member-age-input" 
                                           placeholder="Alter" min="1" max="12" value="<?php echo $member['age_group'] ?? ''; ?>">
                                </div>
                                <div class="col-12 mt-2">
                                    <button type="button" class="btn btn-outline-danger w-100 remove-member-btn">Entfernen</button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" id="member_count" name="member_count" value="<?php echo count($form_data['members']); ?>">
                </div>
            </div>
            
            <!-- Menüauswahl pro Person -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">🍽️ Menüauswahl</h5>
                    <small class="text-muted">Wählen Sie für jede Person ein Gericht pro Gang</small>
                </div>
                <div class="card-body" id="menuSelection">
                    <!-- Dynamisch gefüllt via JS oder Server -->
                    <div class="person-menu-section mb-4" data-person-idx="0">
                        <h6 class="border-bottom pb-2">
                            <span class="person-name-display"><?php echo htmlspecialchars($form_data['firstname'] . ' ' . $form_data['lastname']); ?></span>
                            <small class="text-muted">(Hauptperson)</small>
                        </h6>
                        <?php foreach ($categories as $cat): ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold"><?php echo htmlspecialchars($cat['name']); ?></label>
                            <select name="person_0_cat_<?php echo $cat['id']; ?>" class="form-select form-select-lg">
                                <option value="">-- Bitte wählen --</option>
                                <?php foreach ($cat['dishes'] as $dish): ?>
                                <option value="<?php echo $dish['id']; ?>" 
                                        <?php echo (isset($form_data['orders'][0][$cat['id']]) && $form_data['orders'][0][$cat['id']] == $dish['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dish['name']); ?>
                                    <?php if ($project['show_prices'] && $dish['price']): ?>
                                        (<?php echo number_format($dish['price'], 2, ',', '.'); ?> €)
                                    <?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <?php echo $edit_order_id ? '💾 Änderungen speichern' : '✅ Bestellung absenden'; ?>
                </button>
                <a href="index.php?pin=<?php echo htmlspecialchars($project['access_pin']); ?>" class="btn btn-outline-secondary">
                    ↩️ Abbrechen
                </a>
            </div>
            
        </form>
    <?php endif; ?>
    
</div>

<?php include 'nav/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@24.5.0/build/js/intlTelInput.min.js"></script>
<script>
// Telefon-Validierung
(function(){
    var phoneVisible = document.getElementById('phone_visible');
    var phoneFull = document.getElementById('phone_full');
    var phoneError = document.getElementById('phone-error');
    if (!phoneVisible || !phoneFull) return;
    
    var iti = window.intlTelInput(phoneVisible, {
        initialCountry: 'de',
        preferredCountries: ['de','at','ch'],
        separateDialCode: true,
        utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@24.5.0/build/js/utils.js',
        dropdownContainer: document.body
    });
    
    document.getElementById('orderForm').addEventListener('submit', function(e){
        if (phoneVisible.value.trim()) {
            if (iti.isValidNumber()) {
                phoneFull.value = iti.getNumber();
                phoneError.classList.add('d-none');
            } else {
                e.preventDefault();
                phoneError.classList.remove('d-none');
                phoneVisible.focus();
            }
        }
    });
})();

// Gast-Typ Toggle
document.querySelectorAll('[name="guest_type"]').forEach(function(radio){
    radio.addEventListener('change', function(){
        var familySection = document.getElementById('familySection');
        if (this.value === 'family') {
            familySection.classList.remove('d-none');
        } else {
            familySection.classList.add('d-none');
        }
        updateMenuSections();
    });
});

// Mitglieder hinzufügen/entfernen
var memberCounter = <?php echo count($form_data['members']); ?>;
document.getElementById('addMemberBtn').addEventListener('click', function(){
    memberCounter++;
    var html = `
        <div class="member-row border-bottom pb-3 mb-3">
            <div class="row g-2">
                <div class="col-12">
                    <input type="text" name="member_${memberCounter}_name" class="form-control form-control-lg member-name-input" placeholder="Name" required>
                </div>
                <div class="col-8">
                    <select name="member_${memberCounter}_type" class="form-select form-select-lg member-type-select">
                        <option value="adult">Erwachsener</option>
                        <option value="child">Kind (≤12)</option>
                    </select>
                </div>
                <div class="col-4 member-age-col d-none">
                    <input type="number" name="member_${memberCounter}_age" class="form-control form-control-lg member-age-input" placeholder="Alter" min="1" max="12">
                </div>
                <div class="col-12 mt-2">
                    <button type="button" class="btn btn-outline-danger w-100 remove-member-btn">Entfernen</button>
                </div>
            </div>
        </div>
    `;
    document.getElementById('membersContainer').insertAdjacentHTML('beforeend', html);
    document.getElementById('member_count').value = memberCounter;
    attachMemberListeners();
    updateMenuSections();
});

function attachMemberListeners() {
    document.querySelectorAll('.member-type-select').forEach(function(select){
        select.removeEventListener('change', handleMemberTypeChange);
        select.addEventListener('change', handleMemberTypeChange);
    });
    document.querySelectorAll('.remove-member-btn').forEach(function(btn){
        btn.removeEventListener('click', handleRemoveMember);
        btn.addEventListener('click', handleRemoveMember);
    });
    document.querySelectorAll('.member-name-input').forEach(function(input){
        input.removeEventListener('input', updateMenuSections);
        input.addEventListener('input', updateMenuSections);
    });
}

function handleMemberTypeChange(e) {
    var row = e.target.closest('.member-row');
    var ageCol = row.querySelector('.member-age-col');
    if (e.target.value === 'child') {
        ageCol.classList.remove('d-none');
        ageCol.querySelector('.member-age-input').required = true;
    } else {
        ageCol.classList.add('d-none');
        ageCol.querySelector('.member-age-input').required = false;
    }
    updateMenuSections();
}

function handleRemoveMember(e) {
    e.target.closest('.member-row').remove();
    memberCounter--;
    document.getElementById('member_count').value = document.querySelectorAll('.member-row').length;
    updateMenuSections();
}

function updateMenuSections() {
    var guestType = document.querySelector('[name="guest_type"]:checked').value;
    var menuSelection = document.getElementById('menuSelection');
    
    // Hauptperson immer vorhanden
    var sections = [{
        idx: 0,
        name: (document.querySelector('[name="firstname"]').value + ' ' + document.querySelector('[name="lastname"]').value).trim() || 'Hauptperson',
        type: 'guest'
    }];
    
    // Familie: alle Mitglieder hinzufügen
    if (guestType === 'family') {
        document.querySelectorAll('.member-row').forEach(function(row, idx){
            var nameInput = row.querySelector('[name^="member_"][name$="_name"]');
            var typeSelect = row.querySelector('[name^="member_"][name$="_type"]');
            sections.push({
                idx: idx + 1,
                name: nameInput.value || 'Person ' + (idx + 1),
                type: typeSelect.value
            });
        });
    }
    
    // Menu sections neu generieren
    var categoriesData = <?php echo json_encode(array_values($categories)); ?>;
    var showPrices = <?php echo $project['show_prices'] ? 'true' : 'false'; ?>;
    var formOrders = <?php echo json_encode($form_data['orders']); ?>;
    
    menuSelection.innerHTML = '';
    sections.forEach(function(person){
        var sectionHtml = `
            <div class="person-menu-section mb-4" data-person-idx="${person.idx}">
                <h6 class="border-bottom pb-2">
                    <span class="person-name-display">${escapeHtml(person.name)}</span>
                    ${person.idx === 0 ? '<small class="text-muted">(Hauptperson)</small>' : ''}
                    ${person.type === 'child' ? '<small class="badge bg-info">Kind</small>' : ''}
                </h6>
        `;
        
        categoriesData.forEach(function(cat){
            sectionHtml += `<div class="mb-3">
                <label class="form-label fw-bold">${escapeHtml(cat.name)}</label>
                <select name="person_${person.idx}_cat_${cat.id}" class="form-select form-select-lg">
                    <option value="">-- Bitte wählen --</option>`;
            
            cat.dishes.forEach(function(dish){
                var selected = (formOrders[person.idx] && formOrders[person.idx][cat.id] == dish.id) ? 'selected' : '';
                var priceText = (showPrices && dish.price) ? ` (${parseFloat(dish.price).toFixed(2).replace('.', ',')} €)` : '';
                sectionHtml += `<option value="${dish.id}" ${selected}>${escapeHtml(dish.name)}${priceText}</option>`;
            });
            
            sectionHtml += `</select></div>`;
        });
        
        sectionHtml += `</div>`;
        menuSelection.insertAdjacentHTML('beforeend', sectionHtml);
    });
}

function escapeHtml(text) {
    var map = {'&': '&amp;','<': '&lt;','>': '&gt;','"': '&quot;',"'": '&#039;'};
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

// Init
attachMemberListeners();
</script>
</body>
</html>
