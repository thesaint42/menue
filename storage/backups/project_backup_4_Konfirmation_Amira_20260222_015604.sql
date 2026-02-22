-- Project Backup (only project-specific rows)
-- Generated: 2026-02-22 01:56:04
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
  `show_prices` tinyint(1) DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_pin` (`access_pin`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `menu_projects_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `menu_users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `menu_projects` (`id`,`name`,`description`,`location`,`contact_person`,`contact_phone`,`contact_email`,`max_guests`,`admin_email`,`access_pin`,`is_active`,`show_prices`,`created_by`,`created_at`) VALUES (4,'Konfirmation Amira','<p class=\"ql-align-center\">Liebe Familie, liebe Freunde,</p><p class=\"ql-align-center\"><strong>am </strong><strong style=\"color: rgb(0, 138, 0);\">26. April 2026</strong> ist es so weit: <em>Amira feiert ihre Konfirmation!</em></p><p class=\"ql-align-center\">Wir freuen uns sehr darauf, diesen besonderen Meilenstein gemeinsam mit euch zu verbringen.
Nach dem Gottesdienst möchten wir den Tag in gemütlicher Runde feiern und laden euch herzlich <strong>ab </strong><strong style=\"color: rgb(0, 138, 0);\">14:00 Uhr</strong><strong> </strong>in die Gaststätte \"Zum Lustigen Steirer\" in Heilbronn ein.</p><p class=\"ql-align-center\">Damit wir gemeinsam schlemmen können, bitten wir euch, vorab einen Blick in unsere digitale Speisekarte zu werfen. Bitte wählt über dieses Tool eure Lieblingsgerichte aus.</p><p class=\"ql-align-center\">Wir freuen uns auf ein unvergessliches Fest mit euch!<strong style=\"color: rgb(255, 153, 0);\" class=\"ql-size-small\"><span class=\"ql-cursor\">﻿﻿﻿﻿﻿﻿﻿﻿﻿﻿</span><span class=\"ql-cursor\">﻿﻿﻿﻿﻿﻿﻿﻿﻿</span></strong></p><p class=\"ql-align-center\"><strong style=\"color: rgb(255, 153, 0);\" class=\"ql-size-small\">Rückmeldefrist: ﻿﻿﻿﻿﻿</strong><span class=\"ql-size-small\">Bitte trefft eure Auswahl bis zum </span><strong style=\"color: rgb(255, 153, 0);\" class=\"ql-size-small\">31.03.2026</strong><span class=\"ql-size-small\">.</span></p>','Zum lustigen Steirer | Heilbronn','Olaf Schneider',+491778217559,'familie@schneider-ret.de',22,'familie@schneider-ret.de',785582,1,0,1,'2026-02-10 22:55:27');

-- Table: menu_dishes
DROP TABLE IF EXISTS `menu_dishes`;
CREATE TABLE `menu_dishes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `category_id` int NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text,
  `price` decimal(8,2) DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `menu_dishes_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `menu_projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `menu_dishes_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `menu_menu_categories` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `menu_dishes` (`id`,`project_id`,`category_id`,`name`,`description`,`price`,`sort_order`,`is_active`,`created_at`) VALUES (17,4,1,'Frittatensuppe','',5.20,1,1,'2026-02-10 23:00:58');
INSERT INTO `menu_dishes` (`id`,`project_id`,`category_id`,`name`,`description`,`price`,`sort_order`,`is_active`,`created_at`) VALUES (18,4,1,'Grießnockerlsuppe','',5.20,0,1,'2026-02-10 23:01:23');
INSERT INTO `menu_dishes` (`id`,`project_id`,`category_id`,`name`,`description`,`price`,`sort_order`,`is_active`,`created_at`) VALUES (19,4,2,'Paniertes Schnitzel (Schwein)','',21.90,2,1,'2026-02-10 23:02:15');
INSERT INTO `menu_dishes` (`id`,`project_id`,`category_id`,`name`,`description`,`price`,`sort_order`,`is_active`,`created_at`) VALUES (20,4,2,'Paniertes Schnitzel (Pute)','',21.90,3,1,'2026-02-10 23:03:10');
INSERT INTO `menu_dishes` (`id`,`project_id`,`category_id`,`name`,`description`,`price`,`sort_order`,`is_active`,`created_at`) VALUES (21,4,2,'Kinderschnitzel (Schwein)','',12.90,4,1,'2026-02-10 23:03:52');
INSERT INTO `menu_dishes` (`id`,`project_id`,`category_id`,`name`,`description`,`price`,`sort_order`,`is_active`,`created_at`) VALUES (22,4,2,'Kinderschnitzel (Pute)','',12.90,5,1,'2026-02-10 23:04:04');
INSERT INTO `menu_dishes` (`id`,`project_id`,`category_id`,`name`,`description`,`price`,`sort_order`,`is_active`,`created_at`) VALUES (23,4,2,'Rindsgulasch','',21.90,6,1,'2026-02-10 23:04:33');
INSERT INTO `menu_dishes` (`id`,`project_id`,`category_id`,`name`,`description`,`price`,`sort_order`,`is_active`,`created_at`) VALUES (24,4,3,'Pommes Frites','',5.20,7,1,'2026-02-10 23:05:10');
INSERT INTO `menu_dishes` (`id`,`project_id`,`category_id`,`name`,`description`,`price`,`sort_order`,`is_active`,`created_at`) VALUES (25,4,3,'Semmelknödel','',5.20,8,1,'2026-02-10 23:05:28');
INSERT INTO `menu_dishes` (`id`,`project_id`,`category_id`,`name`,`description`,`price`,`sort_order`,`is_active`,`created_at`) VALUES (26,4,3,'Geröstete Erdäpfel','',5.20,9,1,'2026-02-10 23:06:00');
INSERT INTO `menu_dishes` (`id`,`project_id`,`category_id`,`name`,`description`,`price`,`sort_order`,`is_active`,`created_at`) VALUES (27,4,3,'Spätzle','',5.20,10,1,'2026-02-10 23:06:19');
INSERT INTO `menu_dishes` (`id`,`project_id`,`category_id`,`name`,`description`,`price`,`sort_order`,`is_active`,`created_at`) VALUES (28,4,4,'Beilagensalat','',5.90,11,1,'2026-02-10 23:07:10');
INSERT INTO `menu_dishes` (`id`,`project_id`,`category_id`,`name`,`description`,`price`,`sort_order`,`is_active`,`created_at`) VALUES (29,4,4,'Krautsalat mit Kürbiskernöl','',4.90,12,1,'2026-02-10 23:07:30');
INSERT INTO `menu_dishes` (`id`,`project_id`,`category_id`,`name`,`description`,`price`,`sort_order`,`is_active`,`created_at`) VALUES (30,4,4,'Erdäpfelsalat','',4.90,13,1,'2026-02-10 23:07:59');
INSERT INTO `menu_dishes` (`id`,`project_id`,`category_id`,`name`,`description`,`price`,`sort_order`,`is_active`,`created_at`) VALUES (31,4,5,'Kaiserschmarrn','',3.50,14,1,'2026-02-10 23:09:32');

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
  `person_type` enum('adult','child') DEFAULT 'adult',
  `child_age` int DEFAULT NULL,
  `highchair_needed` tinyint(1) DEFAULT '0',
  `order_id` char(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_guest_order` (`project_id`,`email`,`order_id`),
  CONSTRAINT `menu_guests_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `menu_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `menu_guests` (`id`,`project_id`,`firstname`,`lastname`,`email`,`phone`,`guest_type`,`family_size`,`order_status`,`created_at`,`person_type`,`child_age`,`highchair_needed`,`order_id`) VALUES (44,4,'Mia','Musterfrau','olaf@schneider-ret.de',+491778217559,'family',2,'pending','2026-02-11 22:43:37','adult',NULL,0,'25970-81686');
INSERT INTO `menu_guests` (`id`,`project_id`,`firstname`,`lastname`,`email`,`phone`,`guest_type`,`family_size`,`order_status`,`created_at`,`person_type`,`child_age`,`highchair_needed`,`order_id`) VALUES (47,4,'Aaron','Schneider','olaf@schneider-ret.de',+491778217559,'individual',1,'pending','2026-02-21 18:17:44','child',2,0,'26925-37975');
INSERT INTO `menu_guests` (`id`,`project_id`,`firstname`,`lastname`,`email`,`phone`,`guest_type`,`family_size`,`order_status`,`created_at`,`person_type`,`child_age`,`highchair_needed`,`order_id`) VALUES (49,4,'Bestie','Besteller','olaf@schneider-ret.de',+491778217559,'family',2,'pending','2026-02-21 23:11:47','adult',NULL,0,'28064-70417');
INSERT INTO `menu_guests` (`id`,`project_id`,`firstname`,`lastname`,`email`,`phone`,`guest_type`,`family_size`,`order_status`,`created_at`,`person_type`,`child_age`,`highchair_needed`,`order_id`) VALUES (48,4,'Olaf','Schneider','olaf@schneider-ret.de',+491778217559,'family',6,'pending','2026-02-21 18:24:57','adult',NULL,0,'31074-18875');
INSERT INTO `menu_guests` (`id`,`project_id`,`firstname`,`lastname`,`email`,`phone`,`guest_type`,`family_size`,`order_status`,`created_at`,`person_type`,`child_age`,`highchair_needed`,`order_id`) VALUES (40,4,'Olaf','Schneider','olaf@schneider-ret.de',+491778217559,'family',4,'pending','2026-02-11 21:46:35','adult',NULL,0,'35575-88355');
INSERT INTO `menu_guests` (`id`,`project_id`,`firstname`,`lastname`,`email`,`phone`,`guest_type`,`family_size`,`order_status`,`created_at`,`person_type`,`child_age`,`highchair_needed`,`order_id`) VALUES (46,4,'Amira','Schneider','olaf@schneider-ret.de',+491778217559,'individual',1,'pending','2026-02-21 18:16:53','adult',NULL,0,'38933-47094');

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
  `order_id` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `guest_id` (`guest_id`),
  CONSTRAINT `menu_family_members_ibfk_1` FOREIGN KEY (`guest_id`) REFERENCES `menu_guests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=107 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `menu_family_members` (`id`,`guest_id`,`name`,`member_type`,`child_age`,`highchair_needed`,`created_at`,`order_id`) VALUES (85,44,'Miababy Musterfrau','child',2,1,'2026-02-11 22:43:37',NULL);
INSERT INTO `menu_family_members` (`id`,`guest_id`,`name`,`member_type`,`child_age`,`highchair_needed`,`created_at`,`order_id`) VALUES (98,48,'Claudia Schneider','adult',NULL,0,'2026-02-21 18:24:57',NULL);
INSERT INTO `menu_family_members` (`id`,`guest_id`,`name`,`member_type`,`child_age`,`highchair_needed`,`created_at`,`order_id`) VALUES (99,48,'Jeremias Schneider','adult',NULL,0,'2026-02-21 18:24:57',NULL);
INSERT INTO `menu_family_members` (`id`,`guest_id`,`name`,`member_type`,`child_age`,`highchair_needed`,`created_at`,`order_id`) VALUES (100,48,'Amira Schneider','adult',NULL,0,'2026-02-21 18:24:57',NULL);
INSERT INTO `menu_family_members` (`id`,`guest_id`,`name`,`member_type`,`child_age`,`highchair_needed`,`created_at`,`order_id`) VALUES (101,48,'Aaron Schneider','child',11,0,'2026-02-21 18:24:57',NULL);
INSERT INTO `menu_family_members` (`id`,`guest_id`,`name`,`member_type`,`child_age`,`highchair_needed`,`created_at`,`order_id`) VALUES (102,48,'Baby Schneider','child',2,1,'2026-02-21 18:24:57',NULL);
INSERT INTO `menu_family_members` (`id`,`guest_id`,`name`,`member_type`,`child_age`,`highchair_needed`,`created_at`,`order_id`) VALUES (103,40,'Aaron Schneider','child',11,0,'2026-02-21 19:10:17',NULL);
INSERT INTO `menu_family_members` (`id`,`guest_id`,`name`,`member_type`,`child_age`,`highchair_needed`,`created_at`,`order_id`) VALUES (104,40,'Baby Schneider','child',2,1,'2026-02-21 19:10:17',NULL);
INSERT INTO `menu_family_members` (`id`,`guest_id`,`name`,`member_type`,`child_age`,`highchair_needed`,`created_at`,`order_id`) VALUES (105,40,'Claudia Schneider','adult',NULL,0,'2026-02-21 19:10:17',NULL);
INSERT INTO `menu_family_members` (`id`,`guest_id`,`name`,`member_type`,`child_age`,`highchair_needed`,`created_at`,`order_id`) VALUES (106,49,'Schlechti Besteller','adult',NULL,0,'2026-02-21 23:11:47',NULL);

-- Table: menu_order_sessions
DROP TABLE IF EXISTS `menu_order_sessions`;
CREATE TABLE `menu_order_sessions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` char(36) NOT NULL,
  `project_id` int NOT NULL,
  `email` varchar(150) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_order_id` (`order_id`),
  KEY `project_id` (`project_id`),
  CONSTRAINT `menu_order_sessions_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `menu_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `menu_order_sessions` (`id`,`order_id`,`project_id`,`email`,`created_at`) VALUES (56,'25970-81686',4,'olaf@schneider-ret.de','2026-02-11 22:43:37');
INSERT INTO `menu_order_sessions` (`id`,`order_id`,`project_id`,`email`,`created_at`) VALUES (58,'38933-47094',4,'olaf@schneider-ret.de','2026-02-21 18:16:53');
INSERT INTO `menu_order_sessions` (`id`,`order_id`,`project_id`,`email`,`created_at`) VALUES (59,'26925-37975',4,'olaf@schneider-ret.de','2026-02-21 18:17:44');
INSERT INTO `menu_order_sessions` (`id`,`order_id`,`project_id`,`email`,`created_at`) VALUES (60,'31074-18875',4,'olaf@schneider-ret.de','2026-02-21 18:24:57');
INSERT INTO `menu_order_sessions` (`id`,`order_id`,`project_id`,`email`,`created_at`) VALUES (61,'28064-70417',4,'olaf@schneider-ret.de','2026-02-21 23:11:47');

-- Table: menu_order_guest_data
DROP TABLE IF EXISTS `menu_order_guest_data`;
CREATE TABLE `menu_order_guest_data` (
  `order_id` char(36) NOT NULL,
  `project_id` int NOT NULL,
  `email` varchar(150) NOT NULL,
  `firstname` varchar(100) NOT NULL,
  `lastname` varchar(100) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `phone_raw` varchar(50) DEFAULT NULL,
  `guest_type` enum('individual','family') DEFAULT 'individual',
  `person_type` enum('adult','child') DEFAULT 'adult',
  `child_age` int DEFAULT NULL,
  `highchair_needed` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`order_id`),
  KEY `project_id` (`project_id`),
  CONSTRAINT `menu_order_guest_data_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `menu_order_sessions` (`order_id`) ON DELETE CASCADE,
  CONSTRAINT `menu_order_guest_data_ibfk_2` FOREIGN KEY (`project_id`) REFERENCES `menu_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `menu_order_guest_data` (`order_id`,`project_id`,`email`,`firstname`,`lastname`,`phone`,`phone_raw`,`guest_type`,`person_type`,`child_age`,`highchair_needed`,`created_at`) VALUES ('25970-81686',4,'olaf@schneider-ret.de','Mia','Musterfrau',+491778217559,+491778217559,'family','adult',NULL,0,'2026-02-11 22:43:37');
INSERT INTO `menu_order_guest_data` (`order_id`,`project_id`,`email`,`firstname`,`lastname`,`phone`,`phone_raw`,`guest_type`,`person_type`,`child_age`,`highchair_needed`,`created_at`) VALUES ('26925-37975',4,'olaf@schneider-ret.de','Aaron','Schneider',+491778217559,+491778217559,'individual','child',2,0,'2026-02-21 18:17:44');
INSERT INTO `menu_order_guest_data` (`order_id`,`project_id`,`email`,`firstname`,`lastname`,`phone`,`phone_raw`,`guest_type`,`person_type`,`child_age`,`highchair_needed`,`created_at`) VALUES ('28064-70417',4,'olaf@schneider-ret.de','Bestie','Besteller',+491778217559,+491778217559,'family','adult',NULL,0,'2026-02-21 23:11:47');
INSERT INTO `menu_order_guest_data` (`order_id`,`project_id`,`email`,`firstname`,`lastname`,`phone`,`phone_raw`,`guest_type`,`person_type`,`child_age`,`highchair_needed`,`created_at`) VALUES ('31074-18875',4,'olaf@schneider-ret.de','Olaf','Schneider',+491778217559,+491778217559,'family','adult',NULL,0,'2026-02-21 18:24:57');
INSERT INTO `menu_order_guest_data` (`order_id`,`project_id`,`email`,`firstname`,`lastname`,`phone`,`phone_raw`,`guest_type`,`person_type`,`child_age`,`highchair_needed`,`created_at`) VALUES ('38933-47094',4,'olaf@schneider-ret.de','Amira','Schneider',+491778217559,+491778217559,'individual','adult',NULL,0,'2026-02-21 18:16:53');

-- Table: menu_order_people
DROP TABLE IF EXISTS `menu_order_people`;
CREATE TABLE `menu_order_people` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` char(36) NOT NULL,
  `person_index` int NOT NULL,
  `name` varchar(200) NOT NULL,
  `person_type` enum('adult','child') DEFAULT 'adult',
  `child_age` int DEFAULT NULL,
  `highchair_needed` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_order_person` (`order_id`,`person_index`),
  CONSTRAINT `menu_order_people_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `menu_order_sessions` (`order_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=143 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `menu_order_people` (`id`,`order_id`,`person_index`,`name`,`person_type`,`child_age`,`highchair_needed`,`created_at`) VALUES (110,'25970-81686',0,'Mia Musterfrau','adult',NULL,0,'2026-02-11 22:43:37');
INSERT INTO `menu_order_people` (`id`,`order_id`,`person_index`,`name`,`person_type`,`child_age`,`highchair_needed`,`created_at`) VALUES (111,'25970-81686',1,'Miababy Musterfrau','child',2,1,'2026-02-11 22:43:37');
INSERT INTO `menu_order_people` (`id`,`order_id`,`person_index`,`name`,`person_type`,`child_age`,`highchair_needed`,`created_at`) VALUES (129,'38933-47094',0,'Amira Schneider','adult',NULL,0,'2026-02-21 18:17:06');
INSERT INTO `menu_order_people` (`id`,`order_id`,`person_index`,`name`,`person_type`,`child_age`,`highchair_needed`,`created_at`) VALUES (130,'26925-37975',0,'Aaron Schneider','child',2,1,'2026-02-21 18:17:44');
INSERT INTO `menu_order_people` (`id`,`order_id`,`person_index`,`name`,`person_type`,`child_age`,`highchair_needed`,`created_at`) VALUES (131,'31074-18875',0,'Olaf Schneider','adult',NULL,0,'2026-02-21 18:24:57');
INSERT INTO `menu_order_people` (`id`,`order_id`,`person_index`,`name`,`person_type`,`child_age`,`highchair_needed`,`created_at`) VALUES (132,'31074-18875',1,'Claudia Schneider','adult',NULL,0,'2026-02-21 18:24:57');
INSERT INTO `menu_order_people` (`id`,`order_id`,`person_index`,`name`,`person_type`,`child_age`,`highchair_needed`,`created_at`) VALUES (133,'31074-18875',2,'Jeremias Schneider','adult',NULL,0,'2026-02-21 18:24:57');
INSERT INTO `menu_order_people` (`id`,`order_id`,`person_index`,`name`,`person_type`,`child_age`,`highchair_needed`,`created_at`) VALUES (134,'31074-18875',3,'Amira Schneider','adult',NULL,0,'2026-02-21 18:24:57');
INSERT INTO `menu_order_people` (`id`,`order_id`,`person_index`,`name`,`person_type`,`child_age`,`highchair_needed`,`created_at`) VALUES (135,'31074-18875',4,'Aaron Schneider','child',11,0,'2026-02-21 18:24:57');
INSERT INTO `menu_order_people` (`id`,`order_id`,`person_index`,`name`,`person_type`,`child_age`,`highchair_needed`,`created_at`) VALUES (136,'31074-18875',5,'Baby Schneider','child',2,1,'2026-02-21 18:24:57');
INSERT INTO `menu_order_people` (`id`,`order_id`,`person_index`,`name`,`person_type`,`child_age`,`highchair_needed`,`created_at`) VALUES (141,'28064-70417',0,'Bestie Besteller','adult',NULL,0,'2026-02-21 23:11:47');
INSERT INTO `menu_order_people` (`id`,`order_id`,`person_index`,`name`,`person_type`,`child_age`,`highchair_needed`,`created_at`) VALUES (142,'28064-70417',1,'Schlechti Besteller','adult',NULL,0,'2026-02-21 23:11:47');

-- Table: menu_orders
DROP TABLE IF EXISTS `menu_orders`;
CREATE TABLE `menu_orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` char(36) NOT NULL,
  `person_id` int NOT NULL,
  `dish_id` int NOT NULL,
  `category_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_order` (`order_id`,`person_id`,`category_id`),
  KEY `dish_id` (`dish_id`),
  KEY `idx_order_id` (`order_id`),
  CONSTRAINT `menu_orders_ibfk_1` FOREIGN KEY (`dish_id`) REFERENCES `menu_dishes` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=624 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (466,'25970-81686',0,17,1,'2026-02-11 22:43:37');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (467,'25970-81686',0,20,2,'2026-02-11 22:43:37');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (468,'25970-81686',0,26,3,'2026-02-11 22:43:37');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (469,'25970-81686',0,29,4,'2026-02-11 22:43:37');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (470,'25970-81686',0,31,5,'2026-02-11 22:43:37');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (471,'25970-81686',1,18,1,'2026-02-11 22:43:37');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (554,'38933-47094',0,18,1,'2026-02-21 18:17:06');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (555,'38933-47094',0,21,2,'2026-02-21 18:17:06');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (556,'38933-47094',0,26,3,'2026-02-21 18:17:06');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (557,'38933-47094',0,28,4,'2026-02-21 18:17:06');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (558,'38933-47094',0,31,5,'2026-02-21 18:17:06');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (559,'26925-37975',0,17,1,'2026-02-21 18:17:44');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (560,'26925-37975',0,20,2,'2026-02-21 18:17:44');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (561,'26925-37975',0,26,3,'2026-02-21 18:17:44');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (562,'26925-37975',0,29,4,'2026-02-21 18:17:44');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (563,'26925-37975',0,31,5,'2026-02-21 18:17:44');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (564,'31074-18875',0,17,1,'2026-02-21 18:24:57');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (565,'31074-18875',0,20,2,'2026-02-21 18:24:57');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (566,'31074-18875',0,25,3,'2026-02-21 18:24:57');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (567,'31074-18875',0,29,4,'2026-02-21 18:24:57');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (568,'31074-18875',0,31,5,'2026-02-21 18:24:57');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (569,'31074-18875',1,18,1,'2026-02-21 18:24:57');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (570,'31074-18875',1,20,2,'2026-02-21 18:24:57');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (571,'31074-18875',1,25,3,'2026-02-21 18:24:57');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (572,'31074-18875',1,29,4,'2026-02-21 18:24:57');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (573,'31074-18875',1,31,5,'2026-02-21 18:24:57');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (574,'31074-18875',2,17,1,'2026-02-21 18:24:57');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (575,'31074-18875',2,20,2,'2026-02-21 18:24:57');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (576,'31074-18875',2,25,3,'2026-02-21 18:24:57');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (577,'31074-18875',2,29,4,'2026-02-21 18:24:57');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (578,'31074-18875',2,31,5,'2026-02-21 18:24:57');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (579,'31074-18875',3,18,1,'2026-02-21 18:24:57');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (580,'31074-18875',3,19,2,'2026-02-21 18:24:57');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (581,'31074-18875',3,26,3,'2026-02-21 18:24:57');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (582,'31074-18875',3,29,4,'2026-02-21 18:24:57');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (583,'31074-18875',3,31,5,'2026-02-21 18:24:57');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (584,'31074-18875',4,17,1,'2026-02-21 18:24:57');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (585,'31074-18875',4,19,2,'2026-02-21 18:24:57');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (586,'31074-18875',4,25,3,'2026-02-21 18:24:57');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (587,'31074-18875',4,29,4,'2026-02-21 18:24:57');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (588,'31074-18875',4,31,5,'2026-02-21 18:24:57');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (589,'31074-18875',5,18,1,'2026-02-21 18:24:57');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (590,'31074-18875',5,20,2,'2026-02-21 18:24:57');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (591,'31074-18875',5,25,3,'2026-02-21 18:24:57');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (592,'31074-18875',5,29,4,'2026-02-21 18:24:57');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (593,'31074-18875',5,31,5,'2026-02-21 18:24:57');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (614,'28064-70417',0,18,1,'2026-02-21 23:11:47');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (615,'28064-70417',0,20,2,'2026-02-21 23:11:47');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (616,'28064-70417',0,27,3,'2026-02-21 23:11:47');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (617,'28064-70417',0,29,4,'2026-02-21 23:11:47');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (618,'28064-70417',0,31,5,'2026-02-21 23:11:47');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (619,'28064-70417',1,18,1,'2026-02-21 23:11:47');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (620,'28064-70417',1,20,2,'2026-02-21 23:11:47');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (621,'28064-70417',1,26,3,'2026-02-21 23:11:47');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (622,'28064-70417',1,29,4,'2026-02-21 23:11:47');
INSERT INTO `menu_orders` (`id`,`order_id`,`person_id`,`dish_id`,`category_id`,`created_at`) VALUES (623,'28064-70417',1,31,5,'2026-02-21 23:11:47');

SET FOREIGN_KEY_CHECKS=1;
