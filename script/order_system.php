<?php
/**
 * order_system.php - Backend-Logik für das neue personenspezifische Bestellsystem
 * Version 3.0 mit order-id, personenspezifischer Menüwahl und Preisunterstützung
 */

/**
 * Generiert eine eindeutige Order-ID im Format "##### - #####"
 */
function generate_order_id() {
    $part1 = str_pad(mt_rand(0, 99999), 5, '0', STR_PAD_LEFT);
    $part2 = str_pad(mt_rand(0, 99999), 5, '0', STR_PAD_LEFT);
    return $part1 . '-' . $part2;
}

/**
 * Lädt eine Bestellung anhand der order-id
 */
function load_order_by_id($pdo, $prefix, $order_id) {
    try {
        // Order Session laden
        $stmt = $pdo->prepare("SELECT * FROM {$prefix}order_sessions WHERE order_id = ?");
        $stmt->execute([$order_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            return null;
        }
        
        // Projekt laden
        $stmt = $pdo->prepare("SELECT * FROM {$prefix}projects WHERE id = ?");
        $stmt->execute([$session['project_id']]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Gast laden
        $stmt = $pdo->prepare("SELECT * FROM {$prefix}guests WHERE project_id = ? AND email = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$session['project_id'], $session['email']]);
        $guest = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Family Members laden
        $family_members = [];
        if ($guest) {
            $stmt = $pdo->prepare("SELECT * FROM {$prefix}family_members WHERE guest_id = ? ORDER BY id");
            $stmt->execute([$guest['id']]);
            $family_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Orders laden
        $stmt = $pdo->prepare("
            SELECT o.*, d.name as dish_name, d.price, mc.name as category_name
            FROM {$prefix}orders o
            JOIN {$prefix}dishes d ON o.dish_id = d.id
            JOIN {$prefix}menu_categories mc ON o.category_id = mc.id
            WHERE o.order_id = ?
            ORDER BY o.person_id, mc.sort_order
        ");
        $stmt->execute([$order_id]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'session' => $session,
            'project' => $project,
            'guest' => $guest,
            'family_members' => $family_members,
            'orders' => $orders
        ];
    } catch (Exception $e) {
        error_log("Error loading order: " . $e->getMessage());
        return null;
    }
}

/**
 * Erstellt oder aktualisiert eine Bestellung
 * 
 * @param PDO $pdo
 * @param string $prefix
 * @param array $data Bestelldaten mit Struktur:
 *   - order_id: optional, wenn vorhanden wird aktualisiert
 *   - project_id: Pflicht
 *   - email: Pflicht
 *   - firstname, lastname, phone: Gastdaten
 *   - guest_type: 'individual' oder 'family'
 *   - persons: Array mit Personendaten [['name' => '...', 'type' => 'adult'|'child', 'age' => null|int, 'highchair' => 0|1]]
 *   - orders: Array mit Bestellungen [['person_index' => 0, 'category_id' => 1, 'dish_id' => 5]]
 * @return array ['success' => bool, 'order_id' => string, 'message' => string]
 */
function save_order($pdo, $prefix, $data) {
    try {
        $pdo->beginTransaction();
        
        $order_id = $data['order_id'] ?? generate_order_id();
        $project_id = $data['project_id'];
        $email = $data['email'];
        $firstname = $data['firstname'] ?? ($data['guest']['firstname'] ?? '');
        $lastname = $data['lastname'] ?? ($data['guest']['lastname'] ?? '');
        $phone = $data['phone'] ?? ($data['guest']['phone'] ?? ($data['guest']['phone_raw'] ?? ''));
        
        // 1. Order Session erstellen/prüfen
        $stmt = $pdo->prepare("SELECT id FROM {$prefix}order_sessions WHERE order_id = ?");
        $stmt->execute([$order_id]);
        $session_exists = $stmt->fetch();
        
        if (!$session_exists) {
            $stmt = $pdo->prepare("INSERT INTO {$prefix}order_sessions (order_id, project_id, email) VALUES (?, ?, ?)");
            $stmt->execute([$order_id, $project_id, $email]);
        }
        
        // 2. Gast erstellen oder aktualisieren
        $stmt = $pdo->prepare("SELECT id FROM {$prefix}guests WHERE project_id = ? AND email = ?");
        $stmt->execute([$project_id, $email]);
        $existing_guest = $stmt->fetch();
        
        $guest_type = $data['guest_type'] ?? 'individual';
        $family_size = ($guest_type === 'family') ? count($data['persons'] ?? []) : 1;
        
        // Hauptperson Typ und Alter extrahieren
        $main_person = $data['persons'][0] ?? ['type' => 'adult', 'age_group' => null, 'highchair' => 0];
        $person_type = $main_person['type'] ?? 'adult';
        $child_age = ($person_type === 'child') ? ($main_person['age_group'] ?? $main_person['age'] ?? null) : null;
        $highchair_needed = ($person_type === 'child') ? ($main_person['highchair'] ?? 0) : 0;
        
        if ($existing_guest) {
            $guest_id = $existing_guest['id'];
            $stmt = $pdo->prepare("UPDATE {$prefix}guests SET firstname = ?, lastname = ?, phone = ?, guest_type = ?, family_size = ?, person_type = ?, child_age = ?, highchair_needed = ? WHERE id = ?");
            $stmt->execute([
                $firstname,
                $lastname,
                $phone,
                $guest_type,
                $family_size,
                $person_type,
                $child_age,
                $highchair_needed,
                $guest_id
            ]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO {$prefix}guests (project_id, firstname, lastname, email, phone, guest_type, family_size, person_type, child_age, highchair_needed) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $project_id,
                $firstname,
                $lastname,
                $email,
                $phone,
                $guest_type,
                $family_size,
                $person_type,
                $child_age,
                $highchair_needed
            ]);
            $guest_id = $pdo->lastInsertId();
        }
        
        // 3. Family Members löschen und neu anlegen
        $stmt = $pdo->prepare("DELETE FROM {$prefix}family_members WHERE guest_id = ?");
        $stmt->execute([$guest_id]);
        
        if ($guest_type === 'family' && !empty($data['persons'])) {
            $stmt = $pdo->prepare("INSERT INTO {$prefix}family_members (guest_id, name, member_type, child_age, highchair_needed) VALUES (?, ?, ?, ?, ?)");
            foreach ($data['persons'] as $idx => $person) {
                if ($idx === 0) {
                    continue; // Hauptperson wird nicht als Family Member gespeichert
                }
                $age = $person['age'] ?? $person['age_group'] ?? null;
                $stmt->execute([
                    $guest_id,
                    $person['name'],
                    $person['type'],
                    $age,
                    $person['highchair'] ?? 0
                ]);
            }
        }
        
        // 4. Alte Orders für diese order_id löschen
        $stmt = $pdo->prepare("DELETE FROM {$prefix}orders WHERE order_id = ?");
        $stmt->execute([$order_id]);
        
        // 5. Neue Orders einfügen
        if (!empty($data['orders'])) {
            $stmt = $pdo->prepare("INSERT INTO {$prefix}orders (order_id, person_id, dish_id, category_id) VALUES (?, ?, ?, ?)");
            foreach ($data['orders'] as $order) {
                // person_id ist der Index im persons-Array (oder 0 für Einzelgast)
                $person_id = $order['person_index'];
                $stmt->execute([
                    $order_id,
                    $person_id,
                    $order['dish_id'],
                    $order['category_id']
                ]);
            }
        }
        
        $pdo->commit();

        // Bestätigungs-Mail (v3.0) versenden
        $mail_message = 'Bestellung erfolgreich gespeichert. Eine Bestätigungsemail wird in Kürze an ' . htmlspecialchars($email) . ' versendet.';
        try {
            if (!empty($email) && isset($_SERVER['HTTP_HOST'])) {
                require_once __DIR__ . '/mailer.php';
                require_once __DIR__ . '/mailer_templates.php';

                // Projekt laden
                $stmt = $pdo->prepare("SELECT * FROM {$prefix}projects WHERE id = ?");
                $stmt->execute([$project_id]);
                $project = $stmt->fetch(PDO::FETCH_ASSOC);

                // Orders inkl. Namen laden
                $stmt = $pdo->prepare("
                    SELECT o.person_id, o.category_id, o.dish_id, d.name as dish_name, d.price, mc.name as category_name
                    FROM {$prefix}orders o
                    JOIN {$prefix}dishes d ON d.id = o.dish_id
                    JOIN {$prefix}menu_categories mc ON mc.id = o.category_id
                    WHERE o.order_id = ?
                    ORDER BY o.person_id, mc.sort_order
                ");
                $stmt->execute([$order_id]);
                $order_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $persons = [];
                foreach (($data['persons'] ?? []) as $idx => $person) {
                    $persons[] = [
                        'name' => $person['name'] ?? '',
                        'type' => $person['type'] ?? 'adult',
                        'age_group' => $person['age_group'] ?? ($person['age'] ?? null)
                    ];
                }

                $order_items = [];
                foreach ($order_rows as $row) {
                    $order_items[] = [
                        'person_index' => (int)$row['person_id'],
                        'category_id' => (int)$row['category_id'],
                        'dish_id' => (int)$row['dish_id'],
                        'dish_name' => $row['dish_name'],
                        'category_name' => $row['category_name'],
                        'price' => $row['price']
                    ];
                }

                $order_data = [
                    'order_id' => $order_id,
                    'guest' => [
                        'firstname' => $firstname,
                        'lastname' => $lastname
                    ],
                    'persons' => $persons,
                    'orders' => $order_items
                ];

                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $base = $scheme . '://' . $_SERVER['HTTP_HOST'];
                $base_path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
                $base_path = $base_path === '/' ? '' : $base_path;
                $edit_url = $base . $base_path . '/index.php?pin=' . urlencode($project['access_pin']) . '&action=edit&order_id=' . urlencode($order_id);

                $mail_result = sendOrderConfirmationV3($pdo, $prefix, $order_data, $project, $edit_url, $email);
                if (!$mail_result['status']) {
                    $mail_message = 'Bestellung gespeichert. Hinweis: Bestätigungs-E-Mail konnte nicht gesendet werden.';
                }
            }
        } catch (Exception $e) {
            $mail_message = 'Bestellung gespeichert. Hinweis: Bestätigungs-E-Mail konnte nicht gesendet werden.';
            error_log('Mail send error: ' . $e->getMessage());
        }

        return [
            'success' => true,
            'order_id' => $order_id,
            'message' => $mail_message
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error saving order: " . $e->getMessage());
        return [
            'success' => false,
            'order_id' => null,
            'message' => 'Fehler beim Speichern der Bestellung: ' . $e->getMessage()
        ];
    }
}

/**
 * Service-Report: Personen mit ihren Gerichten
 */
function generate_service_report($pdo, $prefix, $project_id) {
    $stmt = $pdo->prepare("
        SELECT 
            os.order_id,
            os.email,
            g.firstname,
            g.lastname,
            fm.name as person_name,
            fm.member_type,
            fm.child_age,
            mc.name as category,
            mc.sort_order,
            d.name as dish,
            d.price
        FROM {$prefix}order_sessions os
        JOIN {$prefix}guests g ON g.project_id = os.project_id AND g.email = os.email
        LEFT JOIN {$prefix}family_members fm ON fm.guest_id = g.id
        JOIN {$prefix}orders o ON o.order_id = os.order_id
        JOIN {$prefix}dishes d ON d.id = o.dish_id
        JOIN {$prefix}menu_categories mc ON mc.id = o.category_id
        WHERE os.project_id = ?
        ORDER BY g.lastname, g.firstname, fm.id, mc.sort_order
    ");
    $stmt->execute([$project_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Küchen-Report: Anzahl pro Gericht
 */
function generate_kitchen_report($pdo, $prefix, $project_id) {
    $stmt = $pdo->prepare("
        SELECT 
            mc.name as category,
            mc.sort_order,
            d.name as dish,
            COUNT(*) as quantity,
            GROUP_CONCAT(CONCAT(g.firstname, ' ', g.lastname) SEPARATOR ', ') as guests
        FROM {$prefix}order_sessions os
        JOIN {$prefix}orders o ON o.order_id = os.order_id
        JOIN {$prefix}dishes d ON d.id = o.dish_id
        JOIN {$prefix}menu_categories mc ON mc.id = o.category_id
        JOIN {$prefix}guests g ON g.project_id = os.project_id AND g.email = os.email
        WHERE os.project_id = ?
        GROUP BY d.id, mc.id
        ORDER BY mc.sort_order, d.sort_order
    ");
    $stmt->execute([$project_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Kosten-Report: Gesamtkosten pro Bestellung und Gesamt
 */
function generate_cost_report($pdo, $prefix, $project_id) {
    $stmt = $pdo->prepare("
        SELECT 
            os.order_id,
            g.firstname,
            g.lastname,
            g.email,
            SUM(d.price) as total_cost,
            COUNT(o.id) as dish_count
        FROM {$prefix}order_sessions os
        JOIN {$prefix}orders o ON o.order_id = os.order_id
        JOIN {$prefix}dishes d ON d.id = o.dish_id
        JOIN {$prefix}guests g ON g.project_id = os.project_id AND g.email = os.email
        WHERE os.project_id = ? AND d.price IS NOT NULL
        GROUP BY os.order_id
        ORDER BY g.lastname, g.firstname
    ");
    $stmt->execute([$project_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
