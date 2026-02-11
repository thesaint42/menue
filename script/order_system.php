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
        
        // Gastdaten aus Bestell-Snapshot laden (falls vorhanden)
        $guest = null;
        $family_members = [];
        $persons = [];

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $stmt->execute(["{$prefix}order_guest_data"]);
        $has_order_guest_data = $stmt->fetchColumn() > 0;

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $stmt->execute(["{$prefix}order_people"]);
        $has_order_people = $stmt->fetchColumn() > 0;

        if ($has_order_guest_data) {
            $stmt = $pdo->prepare("SELECT * FROM {$prefix}order_guest_data WHERE order_id = ? LIMIT 1");
            $stmt->execute([$order_id]);
            $guest = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($has_order_people) {
            $stmt = $pdo->prepare("SELECT * FROM {$prefix}order_people WHERE order_id = ? ORDER BY person_index ASC");
            $stmt->execute([$order_id]);
            $persons = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Fallback: Gast + Familienmitglieder laden (Legacy) - IMMER versuchen zu laden
        if (!$guest) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'order_id'");
            $stmt->execute(["{$prefix}guests"]);
            $has_guest_order_id = $stmt->fetchColumn() > 0;

            if ($has_guest_order_id) {
                $stmt = $pdo->prepare("SELECT * FROM {$prefix}guests WHERE project_id = ? AND order_id = ? LIMIT 1");
                $stmt->execute([$session['project_id'], $order_id]);
                $guest = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if (!$guest) {
                $stmt = $pdo->prepare("SELECT * FROM {$prefix}guests WHERE project_id = ? AND email = ? ORDER BY created_at DESC LIMIT 1");
                $stmt->execute([$session['project_id'], $session['email']]);
                $guest = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }

        // Family members laden - IMMER wenn guest vorhanden ist
        if ($guest && $guest['id']) {
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
            'persons_snapshot' => $persons,
            'orders' => $orders,
            'email' => $session['email'] ?? null,
            'project_id' => $session['project_id'] ?? null
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
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'order_id'");
        $stmt->execute(["{$prefix}guests"]);
        $has_guest_order_id = $stmt->fetchColumn() > 0;

        if (!$has_guest_order_id) {
            $pdo->exec("ALTER TABLE `{$prefix}guests` ADD COLUMN `order_id` CHAR(36) NULL");
        }

        // Unique Index anpassen (falls vorhanden)
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = 'unique_guest'");
            $stmt->execute(["{$prefix}guests"]);
            if ($stmt->fetchColumn() > 0) {
                $pdo->exec("ALTER TABLE `{$prefix}guests` DROP INDEX `unique_guest`");
            }
        } catch (Exception $e) {
            // Ignorieren
        }

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = 'unique_guest_order'");
            $stmt->execute(["{$prefix}guests"]);
            if ($stmt->fetchColumn() == 0) {
                $pdo->exec("ALTER TABLE `{$prefix}guests` ADD UNIQUE KEY `unique_guest_order` (`project_id`, `email`, `order_id`)");
            }
        } catch (Exception $e) {
            // Ignorieren
        }
        if ($has_guest_order_id) {
            $stmt = $pdo->prepare("SELECT id FROM {$prefix}guests WHERE project_id = ? AND order_id = ?");
            $stmt->execute([$project_id, $order_id]);
        } else {
            $stmt = $pdo->prepare("SELECT id FROM {$prefix}guests WHERE project_id = ? AND email = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$project_id, $email]);
        }
        $existing_guest = $stmt->fetch();
        
        $guest_type = $data['guest_type'] ?? 'individual';
        // Auto-korrigiere guest_type wenn mehr als 1 Person vorhanden ist
        if (count($data['persons'] ?? []) > 1) {
            $guest_type = 'family';
        }
        $family_size = ($guest_type === 'family') ? count($data['persons'] ?? []) : 1;
        
        // Hauptperson Typ und Alter extrahieren (für zukünftige Nutzung vorbereitet)
        $main_person = $data['persons'][0] ?? ['type' => 'adult', 'age_group' => null, 'highchair' => 0];
        $person_type = $main_person['type'] ?? 'adult';
        $age_value = ($person_type === 'child') ? ($main_person['age_group'] ?? $main_person['age'] ?? null) : null;
        // Konvertiere leere Strings zu NULL
        $child_age = ($age_value === '' || $age_value === null) ? null : intval($age_value);
        $highchair_needed = ($person_type === 'child') ? ($main_person['highchair'] ?? 0) : 0;
        
        // Prüfe ob neue Spalten existieren
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'person_type'");
        $stmt_check->execute(["{$prefix}guests"]);
        $has_person_type_column = $stmt_check->fetchColumn() > 0;
        
        if ($existing_guest) {
            $guest_id = $existing_guest['id'];
            if ($has_person_type_column) {
                $stmt = $pdo->prepare("UPDATE {$prefix}guests SET firstname = ?, lastname = ?, phone = ?, guest_type = ?, family_size = ?, person_type = ?, child_age = ?, highchair_needed = ?, order_id = ? WHERE id = ?");
                $stmt->execute([
                    $firstname,
                    $lastname,
                    $phone,
                    $guest_type,
                    $family_size,
                    $person_type,
                    $child_age,
                    $highchair_needed,
                    $order_id,
                    $guest_id
                ]);
            } else {
                $stmt = $pdo->prepare("UPDATE {$prefix}guests SET firstname = ?, lastname = ?, phone = ?, guest_type = ?, family_size = ?, order_id = ? WHERE id = ?");
                $stmt->execute([
                    $firstname,
                    $lastname,
                    $phone,
                    $guest_type,
                    $family_size,
                    $order_id,
                    $guest_id
                ]);
            }
        } else {
            if ($has_person_type_column) {
                $stmt = $pdo->prepare("INSERT INTO {$prefix}guests (project_id, firstname, lastname, email, phone, guest_type, family_size, person_type, child_age, highchair_needed, order_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
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
                    $highchair_needed,
                    $order_id
                ]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO {$prefix}guests (project_id, firstname, lastname, email, phone, guest_type, family_size, order_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $project_id,
                    $firstname,
                    $lastname,
                    $email,
                    $phone,
                    $guest_type,
                    $family_size,
                    $order_id
                ]);
            }
            $guest_id = $pdo->lastInsertId();
        }

        // 2b. Bestell-Snapshot Tabellen sicherstellen
        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}order_guest_data` (
            `order_id` CHAR(36) PRIMARY KEY,
            `project_id` INT NOT NULL,
            `email` VARCHAR(150) NOT NULL,
            `firstname` VARCHAR(100) NOT NULL,
            `lastname` VARCHAR(100) NOT NULL,
            `phone` VARCHAR(50),
            `phone_raw` VARCHAR(50),
            `guest_type` ENUM('individual', 'family') DEFAULT 'individual',
            `person_type` ENUM('adult', 'child') DEFAULT 'adult',
            `child_age` INT,
            `highchair_needed` TINYINT(1) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`order_id`) REFERENCES `{$prefix}order_sessions`(`order_id`) ON DELETE CASCADE,
            FOREIGN KEY (`project_id`) REFERENCES `{$prefix}projects`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}order_people` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `order_id` CHAR(36) NOT NULL,
            `person_index` INT NOT NULL,
            `name` VARCHAR(200) NOT NULL,
            `person_type` ENUM('adult', 'child') DEFAULT 'adult',
            `child_age` INT,
            `highchair_needed` TINYINT(1) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_order_person` (`order_id`, `person_index`),
            FOREIGN KEY (`order_id`) REFERENCES `{$prefix}order_sessions`(`order_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // 2c. Bestell-Snapshot speichern
        $stmt = $pdo->prepare("INSERT INTO {$prefix}order_guest_data
            (order_id, project_id, email, firstname, lastname, phone, phone_raw, guest_type, person_type, child_age, highchair_needed)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                project_id = VALUES(project_id),
                email = VALUES(email),
                firstname = VALUES(firstname),
                lastname = VALUES(lastname),
                phone = VALUES(phone),
                phone_raw = VALUES(phone_raw),
                guest_type = VALUES(guest_type),
                person_type = VALUES(person_type),
                child_age = VALUES(child_age),
                highchair_needed = VALUES(highchair_needed)");
        $stmt->execute([
            $order_id,
            $project_id,
            $email,
            $firstname,
            $lastname,
            $phone,
            $data['guest']['phone_raw'] ?? null,
            $guest_type,
            $person_type,
            $child_age,
            $highchair_needed
        ]);

        $stmt = $pdo->prepare("DELETE FROM {$prefix}order_people WHERE order_id = ?");
        $stmt->execute([$order_id]);

        $stmt = $pdo->prepare("INSERT INTO {$prefix}order_people (order_id, person_index, name, person_type, child_age, highchair_needed) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($data['persons'] as $idx => $person) {
            $person_name = $person['name'] ?? '';
            $stmt->execute([
                $order_id,
                $idx,
                $person_name,
                $person['type'] ?? 'adult',
                $person['age_group'] ?? ($person['age'] ?? null),
                $person['highchair_needed'] ?? 0
            ]);
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
                // Konvertiere leere Strings zu NULL
                $age = ($age === '' || $age === null) ? null : intval($age);
                $stmt->execute([
                    $guest_id,
                    $person['name'],
                    $person['type'],
                    $age,
                    $person['highchair_needed'] ?? 0
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
        $mail_message = 'Eine Bestätigungsemail wird in Kürze an ' . htmlspecialchars($email) . ' versendet.';
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
        error_log("SAVE_ORDER ERROR: " . $e->getMessage());
        error_log("SAVE_ORDER ERROR trace: " . $e->getTraceAsString());
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
