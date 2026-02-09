-- Event Menue Order System (EMOS) Database Backup
-- Generated: 2026-02-08 23:27:33
-- Database Prefix: menu_

SET FOREIGN_KEY_CHECKS=0;

-- =====================
-- Table: menu_dishes
-- =====================

DROP TABLE IF EXISTS `menu_dishes`;

CREATE TABLE `menu_dishes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `category_id` int NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text,
  `sort_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `menu_dishes_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `menu_projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `menu_dishes_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `menu_menu_categories` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================
-- Table: menu_family_members
-- =====================

DROP TABLE IF EXISTS `menu_family_members`;

CREATE TABLE `menu_family_members` (
  `id` int NOT NULL AUTO_INCREMENT,
  `guest_id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `member_type` enum('adult','child') DEFAULT 'adult',
  `child_age` int DEFAULT NULL,
  `highchair_needed` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `guest_id` (`guest_id`),
  CONSTRAINT `menu_family_members_ibfk_1` FOREIGN KEY (`guest_id`) REFERENCES `menu_guests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================
-- Table: menu_guests
-- =====================

DROP TABLE IF EXISTS `menu_guests`;

CREATE TABLE `menu_guests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `firstname` varchar(100) NOT NULL,
  `lastname` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `guest_type` enum('individual','family') DEFAULT 'individual',
  `family_size` int DEFAULT '1',
  `order_status` enum('pending','confirmed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_guest` (`project_id`,`email`),
  CONSTRAINT `menu_guests_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `menu_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================
-- Table: menu_logs
-- =====================

DROP TABLE IF EXISTS `menu_logs`;

CREATE TABLE `menu_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `details` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `menu_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `menu_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `menu_logs` (`id`,`user_id`,`action`,`details`,`ip_address`,`created_at`) VALUES (1,1,'project_created','Projekt: Konfirmation Amira, PIN: 057329','88.67.48.187','2026-02-08 20:40:35');
INSERT INTO `menu_logs` (`id`,`user_id`,`action`,`details`,`ip_address`,`created_at`) VALUES (2,1,'project_updated','Projekt ID: 1','88.67.48.187','2026-02-08 20:42:40');
INSERT INTO `menu_logs` (`id`,`user_id`,`action`,`details`,`ip_address`,`created_at`) VALUES (3,1,'project_updated','Projekt ID: 1','88.67.48.187','2026-02-08 20:45:35');
INSERT INTO `menu_logs` (`id`,`user_id`,`action`,`details`,`ip_address`,`created_at`) VALUES (4,1,'project_updated','Projekt ID: 1','88.67.48.187','2026-02-08 20:46:35');
INSERT INTO `menu_logs` (`id`,`user_id`,`action`,`details`,`ip_address`,`created_at`) VALUES (5,1,'project_created','Projekt: Test, PIN: 534417','88.67.48.187','2026-02-08 20:49:38');
INSERT INTO `menu_logs` (`id`,`user_id`,`action`,`details`,`ip_address`,`created_at`) VALUES (6,1,'project_created','Projekt: Konfirmation Amira, PIN: 458832','88.67.48.187','2026-02-08 20:55:21');
INSERT INTO `menu_logs` (`id`,`user_id`,`action`,`details`,`ip_address`,`created_at`) VALUES (7,1,'project_deactivated','Projekt ID: 3','88.67.48.187','2026-02-08 20:55:41');
INSERT INTO `menu_logs` (`id`,`user_id`,`action`,`details`,`ip_address`,`created_at`) VALUES (8,1,'project_deactivated','Projekt ID: 3','88.67.48.187','2026-02-08 20:58:31');
INSERT INTO `menu_logs` (`id`,`user_id`,`action`,`details`,`ip_address`,`created_at`) VALUES (9,1,'project_deactivated','Projekt ID: 3','88.67.48.187','2026-02-08 21:04:14');
INSERT INTO `menu_logs` (`id`,`user_id`,`action`,`details`,`ip_address`,`created_at`) VALUES (10,1,'project_deactivated','Projekt ID: 3','88.67.48.187','2026-02-08 21:29:37');
INSERT INTO `menu_logs` (`id`,`user_id`,`action`,`details`,`ip_address`,`created_at`) VALUES (11,1,'project_deactivated','Projekt ID: 3','88.67.48.187','2026-02-08 21:29:40');
INSERT INTO `menu_logs` (`id`,`user_id`,`action`,`details`,`ip_address`,`created_at`) VALUES (12,1,'project_deactivated','Projekt ID: 3','88.67.48.187','2026-02-08 22:16:41');

-- =====================
-- Table: menu_mail_logs
-- =====================

DROP TABLE IF EXISTS `menu_mail_logs`;

CREATE TABLE `menu_mail_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sender` varchar(255) DEFAULT NULL,
  `recipient` varchar(255) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('success','failed') DEFAULT 'success',
  `error_message` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `menu_mail_logs` (`id`,`sender`,`recipient`,`subject`,`sent_at`,`status`,`error_message`) VALUES (1,'familie@schneider-ret.de','olaf@schneider-ret.de','Menüwahl System - SMTP Testmail','2026-02-08 16:03:03','success',NULL);

-- =====================
-- Table: menu_menu_categories
-- =====================

DROP TABLE IF EXISTS `menu_menu_categories`;

CREATE TABLE `menu_menu_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `sort_order` int DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `menu_menu_categories` (`id`,`name`,`sort_order`) VALUES (1,'Vorspeise',1);
INSERT INTO `menu_menu_categories` (`id`,`name`,`sort_order`) VALUES (2,'Hauptspeise',2);
INSERT INTO `menu_menu_categories` (`id`,`name`,`sort_order`) VALUES (3,'Beilage',3);
INSERT INTO `menu_menu_categories` (`id`,`name`,`sort_order`) VALUES (4,'Salat',4);
INSERT INTO `menu_menu_categories` (`id`,`name`,`sort_order`) VALUES (5,'Nachspeise',5);

-- =====================
-- Table: menu_migrations
-- =====================

DROP TABLE IF EXISTS `menu_migrations`;

CREATE TABLE `menu_migrations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `executed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `migration` (`migration`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `menu_migrations` (`id`,`migration`,`executed_at`) VALUES (1,'add_family_members_table','2026-02-02 23:45:57');
INSERT INTO `menu_migrations` (`id`,`migration`,`executed_at`) VALUES (2,'add_access_pin_to_projects','2026-02-02 23:46:01');
INSERT INTO `menu_migrations` (`id`,`migration`,`executed_at`) VALUES (3,'remove_age_group_from_guests','2026-02-02 23:46:06');

-- =====================
-- Table: menu_orders
-- =====================

DROP TABLE IF EXISTS `menu_orders`;

CREATE TABLE `menu_orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `guest_id` int NOT NULL,
  `dish_id` int NOT NULL,
  `quantity` int DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_order` (`guest_id`,`dish_id`),
  KEY `dish_id` (`dish_id`),
  CONSTRAINT `menu_orders_ibfk_1` FOREIGN KEY (`guest_id`) REFERENCES `menu_guests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `menu_orders_ibfk_2` FOREIGN KEY (`dish_id`) REFERENCES `menu_dishes` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================
-- Table: menu_password_resets
-- =====================

DROP TABLE IF EXISTS `menu_password_resets`;

CREATE TABLE `menu_password_resets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(150) NOT NULL,
  `token` varchar(100) NOT NULL,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `token` (`token`),
  KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================
-- Table: menu_projects
-- =====================

DROP TABLE IF EXISTS `menu_projects`;

CREATE TABLE `menu_projects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `description` text,
  `location` varchar(255) DEFAULT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `contact_phone` varchar(50) DEFAULT NULL,
  `contact_email` varchar(150) DEFAULT NULL,
  `max_guests` int DEFAULT '100',
  `admin_email` varchar(150) NOT NULL,
  `access_pin` varchar(10) NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_pin` (`access_pin`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `menu_projects_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `menu_users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `menu_projects` (`id`,`name`,`description`,`location`,`contact_person`,`contact_phone`,`contact_email`,`max_guests`,`admin_email`,`access_pin`,`is_active`,`created_by`,`created_at`) VALUES (1,'Konfirmation Amira','Wir laden unsere Gäste herzlich ein, im Anschluss an die Konfirmation gemeinsam mit uns im Restaurant „Zum lustigen Steirer“ in Heilbronn zu feiern.

In gemütlicher Atmosphäre genießen wir traditionelle österreichische und steirische Spezialitäten. Für diesen besonderen Anlass haben wir ein gemeinsames Menü ausgewählt, das vorab bestellt wird, damit ein reibungsloser Ablauf gewährleistet ist und wir den Tag entspannt miteinander verbringen können.

Wir freuen uns sehr darauf, diesen festlichen Tag gemeinsam mit euch bei gutem Essen und in angenehmer Runde ausklingen zu lassen.','Zum lustigen Steirer | Heilbronn','Olaf Schneider',+491778217559,'familie@schneider-ret.de',22,'familie@schneider-ret.de',057329,1,1,'2026-02-08 20:40:35');
INSERT INTO `menu_projects` (`id`,`name`,`description`,`location`,`contact_person`,`contact_phone`,`contact_email`,`max_guests`,`admin_email`,`access_pin`,`is_active`,`created_by`,`created_at`) VALUES (2,'Test','Test','Test','Test','','',100,'familie@schneider-ret.de',534417,1,1,'2026-02-08 20:49:38');
INSERT INTO `menu_projects` (`id`,`name`,`description`,`location`,`contact_person`,`contact_phone`,`contact_email`,`max_guests`,`admin_email`,`access_pin`,`is_active`,`created_by`,`created_at`) VALUES (3,'Konfirmation Amira','Wir laden unsere Gäste herzlich ein, im Anschluss an die Konfirmation gemeinsam mit uns im Restaurant „Zum lustigen Steirer“ in Heilbronn zu feiern.

In gemütlicher Atmosphäre genießen wir traditionelle österreichische und steirische Spezialitäten. Für diesen besonderen Anlass haben wir ein gemeinsames Menü ausgewählt, das vorab bestellt wird, damit ein reibungsloser Ablauf gewährleistet ist und wir den Tag entspannt miteinander verbringen können.

Wir freuen uns sehr darauf, diesen festlichen Tag gemeinsam mit euch bei gutem Essen und in angenehmer Runde ausklingen zu lassen.','Zum lustigen Steirer | Heilbronn','Olaf Schneider',+491778217559,'familie@schneider-ret.de',22,'familie@schneider-ret.de',458832,0,1,'2026-02-08 20:55:21');

-- =====================
-- Table: menu_roles
-- =====================

DROP TABLE IF EXISTS `menu_roles`;

CREATE TABLE `menu_roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `menu_roles` (`id`,`name`,`description`) VALUES (1,'Admin','Vollzugriff auf alle Funktionen');
INSERT INTO `menu_roles` (`id`,`name`,`description`) VALUES (2,'Editor','Kann Projekte und Menüs verwalten');

-- =====================
-- Table: menu_smtp_config
-- =====================

DROP TABLE IF EXISTS `menu_smtp_config`;

CREATE TABLE `menu_smtp_config` (
  `id` int NOT NULL,
  `smtp_host` varchar(255) NOT NULL,
  `smtp_port` int NOT NULL,
  `smtp_user` varchar(255) NOT NULL,
  `smtp_pass` varchar(255) NOT NULL,
  `smtp_secure` varchar(10) DEFAULT 'tls',
  `sender_email` varchar(255) NOT NULL,
  `sender_name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `menu_smtp_config_chk_1` CHECK ((`id` = 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `menu_smtp_config` (`id`,`smtp_host`,`smtp_port`,`smtp_user`,`smtp_pass`,`smtp_secure`,`sender_email`,`sender_name`) VALUES (1,'wp1038982.mailout.server-he.de',587,'wp1038982-medea','Medea001!','tls','familie@schneider-ret.de','Event Menue Order System (EMOS)');

-- =====================
-- Table: menu_users
-- =====================

DROP TABLE IF EXISTS `menu_users`;

CREATE TABLE `menu_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `firstname` varchar(100) NOT NULL,
  `lastname` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` int DEFAULT '1',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `menu_users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `menu_roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `menu_users` (`id`,`firstname`,`lastname`,`email`,`password_hash`,`role_id`,`is_active`,`created_at`) VALUES (1,'Olaf','Schneider','olaf@schneider-ret.de','$2y$10$YQjwuLTK0bu4Iyk859yXeOevB9AgAPiIkJDECHCdaxNUvZsuzbts.',1,1,'2026-02-08 15:50:21');

SET FOREIGN_KEY_CHECKS=1;
