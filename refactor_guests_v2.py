#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Refaktoriert admin/guests.php für hierarchische Bestellungsdarstellung
"""
import re

with open('admin/guests.php', 'r', encoding='utf-8') as f:
    content = f.read()

# ============================================================================
# 1. DELETE-LOGIK ANPASSEN (für Person und ganze Bestellung)
# ============================================================================

old_delete_logic = """// Gast löschen (inkl. Bestellung)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_guest_id'])) {
    $delete_guest_id = (int)$_POST['delete_guest_id'];
    $delete_order_id = trim($_POST['delete_order_id'] ?? '');

    if ($delete_guest_id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM {$prefix}guests WHERE id = ? AND project_id = ? LIMIT 1");
        $stmt->execute([$delete_guest_id, $project_id]);
        $guest_row = $stmt->fetch();

        if ($delete_order_id !== '') {
            if ($has_order_people) {
                $stmt = $pdo->prepare("DELETE FROM {$prefix}order_people WHERE order_id = ?");
                $stmt->execute([$delete_order_id]);
            }
            if ($has_order_guest_data) {
                $stmt = $pdo->prepare("DELETE FROM {$prefix}order_guest_data WHERE order_id = ?");
                $stmt->execute([$delete_order_id]);
            }
            $stmt = $pdo->prepare("DELETE FROM {$prefix}orders WHERE order_id = ?");
            $stmt->execute([$delete_order_id]);
            $stmt = $pdo->prepare("DELETE FROM {$prefix}order_sessions WHERE order_id = ?");
            $stmt->execute([$delete_order_id]);
        } elseif ($guest_row) {
            // Legacy: alle Bestellungen dieses Gasts löschen
            $stmt = $pdo->prepare("SELECT order_id FROM {$prefix}order_sessions WHERE project_id = ? AND email = ?");
            $stmt->execute([$project_id, $guest_row['email']]);
            $order_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($order_ids as $oid) {
                if ($has_order_people) {
                    $stmt = $pdo->prepare("DELETE FROM {$prefix}order_people WHERE order_id = ?");
                    $stmt->execute([$oid]);
                }
                if ($has_order_guest_data) {
                    $stmt = $pdo->prepare("DELETE FROM {$prefix}order_guest_data WHERE order_id = ?");
                    $stmt->execute([$oid]);
                }
                $stmt = $pdo->prepare("DELETE FROM {$prefix}orders WHERE order_id = ?");
                $stmt->execute([$oid]);
                $stmt = $pdo->prepare("DELETE FROM {$prefix}order_sessions WHERE order_id = ?");
                $stmt->execute([$oid]);
            }
        }

        $stmt = $pdo->prepare("DELETE FROM {$prefix}family_members WHERE guest_id = ?");
        $stmt->execute([$delete_guest_id]);
        $stmt = $pdo->prepare("DELETE FROM {$prefix}guests WHERE id = ?");
        $stmt->execute([$delete_guest_id]);
    }
}"""

new_delete_logic = """// Bestellung oder Einzelperson löschen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Szenario 1: Ganze Bestellung löschen
    if (isset($_POST['delete_order_id'])) {
        $delete_order_id = trim($_POST['delete_order_id'] ?? '');
        if ($delete_order_id !== '') {
            if ($has_order_people) {
                $stmt = $pdo->prepare("DELETE FROM {$prefix}order_people WHERE order_id = ?");
                $stmt->execute([$delete_order_id]);
            }
            if ($has_order_guest_data) {
                $stmt = $pdo->prepare("DELETE FROM {$prefix}order_guest_data WHERE order_id = ?");
                $stmt->execute([$delete_order_id]);
            }
            $stmt = $pdo->prepare("DELETE FROM {$prefix}orders WHERE order_id = ?");
            $stmt->execute([$delete_order_id]);
            $stmt = $pdo->prepare("DELETE FROM {$prefix}order_sessions WHERE order_id = ?");
            $stmt->execute([$delete_order_id]);
        }
    }
    
    // Szenario 2: Einzelne Person aus Bestellung löschen
    if (isset($_POST['delete_person_order_id']) && isset($_POST['delete_person_index'])) {
        $person_order_id = trim($_POST['delete_person_order_id'] ?? '');
        $person_index = (int)$_POST['delete_person_index'];
        
        if ($person_order_id !== '' && $person_index >= 0) {
            // Lösche alle Menüauswahlen dieser Person
            $stmt = $pdo->prepare("DELETE FROM {$prefix}orders WHERE order_id = ? AND person_index = ?");
            $stmt->execute([$person_order_id, $person_index]);
            
            // Lösche Person aus order_guest_data
            if ($has_order_guest_data) {
                $stmt = $pdo->prepare("DELETE FROM {$prefix}order_guest_data WHERE order_id = ? AND person_index = ?");
                $stmt->execute([$person_order_id, $person_index]);
            }
            
            // Lösche Person aus order_people (Legacy)
            if ($has_order_people) {
                $stmt = $pdo->prepare("DELETE FROM {$prefix}order_people WHERE order_id = ? AND person_index = ?");
                $stmt->execute([$person_order_id, $person_index]);
            }
        }
    }
}"""

content = content.replace(old_delete_logic, new_delete_logic)

# ============================================================================
# 2. SQL-QUERY ANPASSEN (gruppiert nach Bestellung)
# ============================================================================

old_query = """// Gäste laden (Orders via order_sessions -> orders)
$order_id_select = $has_guest_order_id ? 'g.order_id' : 'NULL';
$stmt = $pdo->prepare("SELECT g.*, p.name as project_name,
                       COALESCE({$order_id_select}, MAX(os.order_id)) as order_id_display,
                       COUNT(DISTINCT os.order_id) as order_count
                       FROM {$prefix}guests g
                       JOIN {$prefix}projects p ON p.id = g.project_id
                       LEFT JOIN {$prefix}order_sessions os ON g.email = os.email AND os.project_id = g.project_id
                       LEFT JOIN {$prefix}orders o ON os.order_id = o.order_id
                       WHERE g.project_id = ? GROUP BY g.id ORDER BY g.created_at DESC");
$stmt->execute([$project_id]);
$guests = $stmt->fetchAll();"""

new_query = """// Bestellungen laden (gruppiert nach order_id)
$stmt = $pdo->prepare("SELECT DISTINCT os.order_id, os.created_at, os.email
                       FROM {$prefix}order_sessions os
                       WHERE os.project_id = ?
                       ORDER BY os.created_at DESC");
$stmt->execute([$project_id]);
$orders_list = $stmt->fetchAll();

// Strukturiere Bestellungen mit Personen
$orders_with_people = [];
foreach ($orders_list as $order_row) {
    $order_id = $order_row['order_id'];
    
    // Zähle Menüauswahlen (Gerichte) dieser Bestellung
    $stmt = $pdo->prepare("SELECT COUNT(*) as dish_count FROM {$prefix}orders WHERE order_id = ?");
    $stmt->execute([$order_id]);
    $dish_count = (int)$stmt->fetchColumn();
    
    // Lade alle Personen dieser Bestellung
    $stmt = $pdo->prepare("SELECT og.* FROM {$prefix}order_guest_data og WHERE og.order_id = ? ORDER BY og.person_index");
    $stmt->execute([$order_id]);
    $people = $stmt->fetchAll();
    
    // Fallback auf legacy Daten
    if (empty($people) && $has_order_people) {
        $stmt = $pdo->prepare("SELECT * FROM {$prefix}order_people WHERE order_id = ? ORDER BY person_index");
        $stmt->execute([$order_id]);
        $people = $stmt->fetchAll();
    }
    
    $orders_with_people[] = [
        'order_id' => $order_id,
        'created_at' => $order_row['created_at'],
        'email' => $order_row['email'],
        'dish_count' => $dish_count,
        'people' => $people
    ];
}"""

content = content.replace(old_query, new_query)

# ============================================================================
# 3. TABLE-KOPFZEILE ANPASSEN
# ============================================================================

old_table_header = """                <thead class="table-dark">
                    <tr>
                        <th>Projekt</th>
                        <th>Bestell-Nr.</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Tel.</th>
                        <th>Typ</th>
                        <th>Alter</th>
                        <th>Bestellungen</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>"""

new_table_header = """                <thead class="table-dark">
                    <tr>
                        <th colspan="2">Bestell-Nr. / Person</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Typ</th>
                        <th>Alter</th>
                        <th class="text-center">Gericht</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>"""

content = content.replace(old_table_header, new_table_header)

# ============================================================================
# 4. TABLE-BODY ANPASSEN (hierarchische Struktur)
# ============================================================================

old_table_body = """                <tbody>
                    <?php if (empty($guests)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">Noch keine Gäste angemeldet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($guests as $g): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($g['project_name'] ?? '–'); ?></td>
                                <td><?php echo htmlspecialchars($g['order_id_display'] ?? '–'); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($g['firstname'] . ' ' . $g['lastname']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($g['email']); ?></td>
                                <td><?php echo htmlspecialchars($g['phone'] ?? '–'); ?></td>
                                <td>
                                    <?php echo $g['guest_type'] === 'family' ? 'Familie' : 'Einzelperson'; ?>
                                    <?php if ($g['guest_type'] === 'family'): ?>
                                        <small class="text-muted">(<?php echo $g['family_size']; ?> Pers.)</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                        $ageGroup = $g['person_type'] ?? 'adult';
                                        $childAge = $g['child_age'] ?? null;
                                        echo $ageGroup === 'child' && $childAge ? 'Kind ' . $childAge . 'J.' : 'Erwachsen';
                                    ?>
                                </td>
                                <td><?php echo $g['order_count']; ?></td>
                                <td>
                                    <form method="post" onsubmit="return confirm('Gast und zugehörige Bestellung wirklich löschen?');">
                                        <input type="hidden" name="delete_guest_id" value="<?php echo (int)$g['id']; ?>">
                                        <input type="hidden" name="delete_order_id" value="<?php echo htmlspecialchars($g['order_id_display'] ?? ''); ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Löschen</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>"""

new_table_body = """                <tbody>
                    <?php if (empty($orders_with_people)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">Noch keine Bestellungen vorhanden.</td></tr>
                    <?php else: ?>
                        <?php foreach ($orders_with_people as $order_data): ?>
                            <!-- BESTELLUNGS-HAUPTZEILE -->
                            <tr class="order-header" style="background-color: #3d3d3d; font-weight: bold;">
                                <td colspan="2">
                                    📦 Bestellung #<?php echo htmlspecialchars($order_data['order_id']); ?>
                                    <small class="text-muted ms-2"><?php echo date('d.m.Y H:i', strtotime($order_data['created_at'])); ?></small>
                                </td>
                                <td colspan="4"></td>
                                <td class="text-center fw-bold" style="background-color: #4d4d4d;">
                                    <?php echo $order_data['dish_count']; ?> Gericht<?php echo $order_data['dish_count'] !== 1 ? 'e' : ''; ?>
                                </td>
                                <td>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Bestellung und alle Personen/Gerichte wirklich löschen?');">
                                        <input type="hidden" name="delete_order_id" value="<?php echo htmlspecialchars($order_data['order_id']); ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Alles löschen</button>
                                    </form>
                                </td>
                            </tr>
                            
                            <!-- PERSONEN-UNTERZEILEN -->
                            <?php foreach ($order_data['people'] as $person): ?>
                                <tr class="order-person" style="padding-left: 2em;">
                                    <td style="width: 1%; color: #888;">└─</td>
                                    <td style="padding-left: 1em;">
                                        Person <?php echo ($person['person_index'] + 1); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($person['name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($person['email'] ?? '–'); ?></td>
                                    <td>
                                        <?php 
                                            $type = strtolower($person['person_type'] ?? 'adult');
                                            echo $type === 'child' ? 'Kind' : 'Erwachsen';
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                            if (strtolower($person['person_type'] ?? 'adult') === 'child') {
                                                echo htmlspecialchars($person['child_age'] ?? '–');
                                            } else {
                                                echo '–';
                                            }
                                        ?>
                                    </td>
                                    <td class="text-center">1</td>
                                    <td>
                                        <form method="post" style="display: inline;" onsubmit="return confirm('Person und zugehörige Gerichte löschen?');">
                                            <input type="hidden" name="delete_person_order_id" value="<?php echo htmlspecialchars($order_data['order_id']); ?>">
                                            <input type="hidden" name="delete_person_index" value="<?php echo (int)$person['person_index']; ?>">
                                            <button type="submit" class="btn btn-sm btn-warning">Löschen</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>"""

content = content.replace(old_table_body, new_table_body)

# Speichere die Datei
with open('admin/guests.php', 'w', encoding='utf-8') as f:
    f.write(content)

print("✅ admin/guests.php erfolgreich refaktoriert!")
print("   - SQL-Query neu strukturiert (gruppiert nach Bestellung)")
print("   - Hierarchische Tabellendarstellung implementiert")
print("   - Delete-Logik für Einzelpersonen und ganze Bestellungen")
print("   - Styling hinzugefügt")
