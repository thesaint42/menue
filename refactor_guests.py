#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Refaktoriert admin/guests.php um hierarchische Bestellungsstruktur zu zeigen
"""

with open('admin/guests.php', 'r', encoding='utf-8') as f:
    content = f.read()

# Alte Query ersetzen - gruppiert nach order_id statt nach guest
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

new_query = """// Gäste laden - gruppiert nach Bestellung mit Personen-Details
// 1. Alle Bestellungen dieses Projekts laden
$stmt = $pdo->prepare("""SELECT DISTINCT os.order_id, os.created_at
                       FROM {$prefix}order_sessions os
                       WHERE os.project_id = ?
                       ORDER BY os.created_at DESC"""");
$stmt->execute([$project_id]);
$orders = $stmt->fetchAll();

// 2. Für jede Bestellung: Personen und deren Menüauswahlen laden
$orders_with_people = [];
foreach ($orders as $order_row) {
    $order_id = $order_row['order_id'];
    
    // Anzahl der Menüauswahlen (Gerichte) dieser Bestellung
    $stmt = $pdo->prepare("""SELECT COUNT(*) as dish_count
                            FROM {$prefix}orders
                            WHERE order_id = ?"""");
    $stmt->execute([$order_id]);
    $dish_count = (int)$stmt->fetchColumn();
    
    // Alle Personen dieser Bestellung laden
    $stmt = $pdo->prepare("""SELECT DISTINCT og.*, og.person_index
                            FROM {$prefix}order_guest_data og
                            WHERE og.order_id = ?
                            ORDER BY og.person_index"""");
    $stmt->execute([$order_id]);
    $people = $stmt->fetchAll();
    
    // Fallback auf legacy data wenn nötig
    if (empty($people) && $has_order_people) {
        $stmt = $pdo->prepare("""SELECT * FROM {$prefix}order_people
                                WHERE order_id = ?
                                ORDER BY person_index"""");
        $stmt->execute([$order_id]);
        $people = $stmt->fetchAll();
    }
    
    $orders_with_people[] = [
        'order_id' => $order_id,
        'created_at' => $order_row['created_at'],
        'dish_count' => $dish_count,
        'people' => $people
    ];
}"""

if old_query in content:
    content = content.replace(old_query, new_query)
    print("✅ SQL-Query aktualisiert")
else:
    print("⚠️  Alte Query nicht gefunden")

# Alte Tabelle ersetzen durch hierarchische Struktur
old_table = """                <tbody>
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

new_table = """                <tbody>
                    <?php if (empty($orders_with_people)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">Noch keine Gäste angemeldet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($orders_with_people as $order_data): ?>
                            <!-- Bestellungs-Hauptzeile -->
                            <tr class="table-active fw-bold bg-secondary bg-opacity-25">
                                <td colspan="2">
                                    📦 Bestellung #<?php echo htmlspecialchars($order_data['order_id']); ?>
                                    <small class="text-muted ms-2"><?php echo date('d.m.Y H:i', strtotime($order_data['created_at'])); ?></small>
                                </td>
                                <td colspan="5"></td>
                                <td class="text-center fw-bold"><?php echo $order_data['dish_count']; ?> Gericht<?php echo $order_data['dish_count'] !== 1 ? 'e' : ''; ?></td>
                                <td>
                                    <form method="post" onsubmit="return confirm('Bestellung und alle Personen wirklich löschen?');" style="display: inline;">
                                        <input type="hidden" name="delete_order_id" value="<?php echo htmlspecialchars($order_data['order_id']); ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Löschen</button>
                                    </form>
                                </td>
                            </tr>
                            
                            <!-- Personen dieser Bestellung (eingerückt) -->
                            <?php foreach ($order_data['people'] as $person): ?>
                                <tr>
                                    <td></td>
                                    <td><small class="text-muted">└─ Person <?php echo ($person['person_index'] + 1); ?></small></td>
                                    <td>
                                        <span class="ms-3"><?php echo htmlspecialchars($person['name'] ?? 'N/A'); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($person['email'] ?? '–'); ?></td>
                                    <td><?php echo htmlspecialchars($person['phone'] ?? '–'); ?></td>
                                    <td>
                                        <?php 
                                            $type = strtolower($person['person_type'] ?? 'adult');
                                            echo $type === 'child' ? 'Kind' : 'Erwachsen';
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                            if (strtolower($person['person_type'] ?? 'adult') === 'child') {
                                                echo htmlspecialchars($person['child_age'] ?? '–') . ' J.';
                                            } else {
                                                echo '–';
                                            }
                                        ?>
                                    </td>
                                    <td class="text-center">1</td>
                                    <td></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>"""

if old_table in content:
    content = content.replace(old_table, new_table)
    print("✅ Tabellen-Struktur aktualisiert")
else:
    print("⚠️  Alte Tabelle nicht gefunden")

with open('admin/guests.php', 'w', encoding='utf-8') as f:
    f.write(content)

print("✅ admin/guests.php refaktoriert")
