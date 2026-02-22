<?php
/**
 * schema.php - SQL-Schema für das Event Menue Order System (EMOS)
 * Ersetzt {PREFIX} durch den Tabellenpräfix
 */

function getMenuSelectionSchema($prefix) {
    return [
        // 1. ROLLEN
        "CREATE TABLE IF NOT EXISTS `{$prefix}roles` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(50) NOT NULL UNIQUE,
            `description` TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // 1b. ROLLEN-FEATURES (Flexible Berechtigungen pro Rolle)
        "CREATE TABLE IF NOT EXISTS `{$prefix}role_features` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `role_id` INT NOT NULL,
            `feature_name` VARCHAR(50) NOT NULL,
            `enabled` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_role_feature` (`role_id`, `feature_name`),
            FOREIGN KEY (`role_id`) REFERENCES `{$prefix}roles`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // 1c. ROLLEN-MENÜ-ZUGRIFF (Burger-Menü Sichtrechte pro Rolle)
        "CREATE TABLE IF NOT EXISTS `{$prefix}role_menu_access` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `role_id` INT NOT NULL,
            `menu_key` VARCHAR(100) NOT NULL,
            `visible` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_role_menu` (`role_id`, `menu_key`),
            FOREIGN KEY (`role_id`) REFERENCES `{$prefix}roles`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // 2. BENUTZER (Admins)
        "CREATE TABLE IF NOT EXISTS `{$prefix}users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `firstname` VARCHAR(100) NOT NULL,
            `lastname` VARCHAR(100) NOT NULL,
            `email` VARCHAR(150) NOT NULL UNIQUE,
            `password_hash` VARCHAR(255) NOT NULL,
            `role_id` INT DEFAULT 1,
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`role_id`) REFERENCES `{$prefix}roles`(`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // 2b. USER PROJECT MAPPING (Projektverwaltung users to projects)
        "CREATE TABLE IF NOT EXISTS `{$prefix}user_projects` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `project_id` INT NOT NULL,
            `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_user_project` (`user_id`, `project_id`),
            FOREIGN KEY (`user_id`) REFERENCES `{$prefix}users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`project_id`) REFERENCES `{$prefix}projects`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // 3. PASSWORT RESET
        "CREATE TABLE IF NOT EXISTS `{$prefix}password_resets` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `email` VARCHAR(150) NOT NULL,
            `token` VARCHAR(100) NOT NULL,
            `expires_at` DATETIME NOT NULL,
            INDEX (`token`),
            INDEX (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // 4. PROJEKTE
        "CREATE TABLE IF NOT EXISTS `{$prefix}projects` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(200) NOT NULL,
            `description` TEXT,
            `location` VARCHAR(255),
            `contact_person` VARCHAR(150),
            `contact_phone` VARCHAR(50),
            `contact_email` VARCHAR(150),
            `max_guests` INT DEFAULT 100,
            `admin_email` VARCHAR(150) NOT NULL,
            `access_pin` VARCHAR(10) NOT NULL,
            `is_active` TINYINT(1) DEFAULT 1,
            `show_prices` TINYINT(1) DEFAULT 0,
            `created_by` INT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_pin` (`access_pin`),
            FOREIGN KEY (`created_by`) REFERENCES `{$prefix}users`(`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // 5. MENÜ KATEGORIEN
        "CREATE TABLE IF NOT EXISTS `{$prefix}menu_categories` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `project_id` INT NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `sort_order` INT DEFAULT 0,
            UNIQUE KEY `unique_name_project` (`project_id`, `name`),
            FOREIGN KEY (`project_id`) REFERENCES `{$prefix}projects`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // 6. MENÜ GERICHTE
        "CREATE TABLE IF NOT EXISTS `{$prefix}dishes` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `project_id` INT NOT NULL,
            `category_id` INT NOT NULL,
            `name` VARCHAR(200) NOT NULL,
            `description` TEXT,
            `price` DECIMAL(8,2) DEFAULT NULL,
            `sort_order` INT DEFAULT 0,
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`project_id`) REFERENCES `{$prefix}projects`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`category_id`) REFERENCES `{$prefix}menu_categories`(`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // 7b. ORDER SESSIONS (Bestellvorgänge)
        "CREATE TABLE IF NOT EXISTS `{$prefix}order_sessions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `order_id` CHAR(36) NOT NULL,
            `project_id` INT NOT NULL,
            `email` VARCHAR(150) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_order_id` (`order_id`),
            FOREIGN KEY (`project_id`) REFERENCES `{$prefix}projects`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // 7. GÄSTE
        "CREATE TABLE IF NOT EXISTS `{$prefix}guests` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `project_id` INT NOT NULL,
            `firstname` VARCHAR(100) NOT NULL,
            `lastname` VARCHAR(100) NOT NULL,
            `email` VARCHAR(150) NOT NULL,
            `phone` VARCHAR(50),
            `guest_type` ENUM('individual', 'family') DEFAULT 'individual',
            `family_size` INT DEFAULT 1,
            `person_type` ENUM('adult', 'child') DEFAULT 'adult',
            `child_age` INT,
            `highchair_needed` TINYINT(1) DEFAULT 0,
            `order_status` ENUM('pending', 'confirmed', 'cancelled') DEFAULT 'pending',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`project_id`) REFERENCES `{$prefix}projects`(`id`) ON DELETE CASCADE,
            UNIQUE KEY `unique_guest` (`project_id`, `email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // 7a. FAMILIENMITGLIEDER
        "CREATE TABLE IF NOT EXISTS `{$prefix}family_members` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `guest_id` INT NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `member_type` ENUM('adult', 'child') DEFAULT 'adult',
            `child_age` INT,
            `highchair_needed` TINYINT(1) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`guest_id`) REFERENCES `{$prefix}guests`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // 8. BESTELLUNGEN (Personen-Menüauswahl, pro Gang)
        "CREATE TABLE IF NOT EXISTS `{$prefix}orders` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `order_id` CHAR(36) NOT NULL,
            `person_id` INT NOT NULL,
            `dish_id` INT NOT NULL,
            `category_id` INT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`order_id`) REFERENCES `{$prefix}order_sessions`(`order_id`) ON DELETE CASCADE,
            FOREIGN KEY (`dish_id`) REFERENCES `{$prefix}dishes`(`id`) ON DELETE RESTRICT,
            UNIQUE KEY `unique_order` (`order_id`, `person_id`, `category_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // 9. SMTP KONFIGURATION
        "CREATE TABLE IF NOT EXISTS `{$prefix}smtp_config` (
            `id` INT PRIMARY KEY CHECK (id = 1),
            `smtp_host` VARCHAR(255) NOT NULL,
            `smtp_port` INT NOT NULL,
            `smtp_user` VARCHAR(255) NOT NULL,
            `smtp_pass` VARCHAR(255) NOT NULL,
            `smtp_secure` VARCHAR(10) DEFAULT 'tls',
            `sender_email` VARCHAR(255) NOT NULL,
            `sender_name` VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // 10. MAIL LOGS
        "CREATE TABLE IF NOT EXISTS `{$prefix}mail_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `sender` VARCHAR(255),
            `recipient` VARCHAR(255),
            `subject` VARCHAR(255),
            `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `status` ENUM('success', 'failed') DEFAULT 'success',
            `error_message` TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // 11. AUDIT LOG
        "CREATE TABLE IF NOT EXISTS `{$prefix}logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT,
            `action` VARCHAR(255) NOT NULL,
            `details` TEXT,
            `ip_address` VARCHAR(45),
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `{$prefix}users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
}

// INSERT Statements für die Initialisierung
function getMenuSelectionInitData($prefix) {
    return [
        // System-Rollen (ID 1-3 sind reserviert)
        "INSERT IGNORE INTO `{$prefix}roles` (`id`, `name`, `description`) VALUES 
         (1, 'Systemadmin', 'Systemrolle mit vollzugriff auf alle Funktionen'),
         (2, 'Projektadmin', 'Systemrolle - kann Projekte und Menüs verwalten'),
         (3, 'Reporter', 'Systemrolle - kann nur Berichte einsehen')",

        // Rollen-Features: Projektadmin hat project_admin Feature
        "INSERT IGNORE INTO `{$prefix}role_features` (`role_id`, `feature_name`, `enabled`) VALUES 
         (1, 'project_admin', 1),
         (2, 'project_admin', 1),
         (3, 'project_admin', 0)",

        // Menu Access: Projektadmin (ID 2) hat Standard-Features, Reporter (ID 3) nur Reporting
        "INSERT IGNORE INTO `{$prefix}role_menu_access` (`role_id`, `menu_key`, `visible`) VALUES 
         (2, 'dashboard', 1),
         (2, 'menu_categories_read', 1),
         (2, 'menu_categories_write', 1),
         (2, 'projects_read', 1),
         (2, 'projects_write', 1),
         (2, 'menus_read', 1),
         (2, 'menus_write', 1),
         (2, 'guests_read', 1),
         (2, 'guests_write', 1),
         (2, 'orders_read', 1),
         (2, 'orders_write', 1),
         (2, 'reporting', 1),
         (3, 'dashboard', 1),
         (3, 'projects_read', 1),
         (3, 'menus_read', 1),
         (3, 'guests_read', 1),
         (3, 'orders_read', 1),
         (3, 'reporting', 1)"

        // Hinweis: Menükategorien werden nicht mehr initial eingefügt, da sie projektspezifisch sind.
        // Kategorien werden beim Anlegen eines Projekts erstellt oder vom Admin in menu_categories.php angelegt.
    ];
}
