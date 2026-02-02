<?php
/**
 * schema.php - SQL-Schema für das Menüwahl-System
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
            `is_active` TINYINT(1) DEFAULT 1,
            `created_by` INT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`created_by`) REFERENCES `{$prefix}users`(`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // 5. MENÜ KATEGORIEN
        "CREATE TABLE IF NOT EXISTS `{$prefix}menu_categories` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `sort_order` INT DEFAULT 0,
            UNIQUE KEY `unique_name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // 6. MENÜ GERICHTE
        "CREATE TABLE IF NOT EXISTS `{$prefix}dishes` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `project_id` INT NOT NULL,
            `category_id` INT NOT NULL,
            `name` VARCHAR(200) NOT NULL,
            `description` TEXT,
            `sort_order` INT DEFAULT 0,
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`project_id`) REFERENCES `{$prefix}projects`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`category_id`) REFERENCES `{$prefix}menu_categories`(`id`) ON DELETE RESTRICT
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
            `age_group` ENUM('adult', 'child') DEFAULT 'adult',
            `child_age` INT,
            `family_size` INT DEFAULT 1,
            `order_status` ENUM('pending', 'confirmed', 'cancelled') DEFAULT 'pending',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`project_id`) REFERENCES `{$prefix}projects`(`id`) ON DELETE CASCADE,
            UNIQUE KEY `unique_guest` (`project_id`, `email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // 8. BESTELLUNGEN (Gast-Menüauswahl)
        "CREATE TABLE IF NOT EXISTS `{$prefix}orders` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `guest_id` INT NOT NULL,
            `dish_id` INT NOT NULL,
            `quantity` INT DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`guest_id`) REFERENCES `{$prefix}guests`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`dish_id`) REFERENCES `{$prefix}dishes`(`id`) ON DELETE RESTRICT,
            UNIQUE KEY `unique_order` (`guest_id`, `dish_id`)
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
        // Rollen
        "INSERT IGNORE INTO `{$prefix}roles` (`id`, `name`, `description`) VALUES 
         (1, 'Admin', 'Vollzugriff auf alle Funktionen'),
         (2, 'Editor', 'Kann Projekte und Menüs verwalten')",

        // Menu Kategorien
        "INSERT IGNORE INTO `{$prefix}menu_categories` (`name`, `sort_order`) VALUES
         ('Vorspeise', 1),
         ('Hauptspeise', 2),
         ('Beilage', 3),
         ('Salat', 4),
         ('Nachspeise', 5)"
    ];
}
