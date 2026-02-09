-- Project Backup (only project-specific rows)
-- Generated: 2026-02-08 23:33:24
SET FOREIGN_KEY_CHECKS=0;

-- Table: menu_projects
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

-- Table: menu_dishes
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

-- Table: menu_guests
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

-- Table: menu_family_members
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

-- Table: menu_orders
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

SET FOREIGN_KEY_CHECKS=1;
