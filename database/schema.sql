-- ============================================================
--  ZT Continental Trans — Gestion de flotte
--  Schéma MySQL / MariaDB (InnoDB, utf8mb4)
--  À importer (phpMyAdmin > Importer) dans la base déjà sélectionnée, ex. yuwe7714_camion
-- ============================================================


SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `dispatch_messages`, `dispatch_tours`, `dispatches`, `helpers`, `rental_renewals`, `incident_comments`, `incident_photos`, `incidents`,
  `truck_photos`, `daily_checks`, `truck_documents`, `maintenance_records`,
  `truck_assignments`, `notifications`, `activity_logs`, `login_attempts`,
  `trucks`, `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- Utilisateurs et rôles
-- ------------------------------------------------------------
CREATE TABLE `users` (
  `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role`                 ENUM('admin','manager','driver') NOT NULL DEFAULT 'driver',
  `first_name`           VARCHAR(80)  NOT NULL,
  `last_name`            VARCHAR(80)  NOT NULL,
  `email`                VARCHAR(190) NOT NULL,
  `phone`                VARCHAR(30)  NULL,
  `password_hash`        VARCHAR(255) NOT NULL,
  `must_change_password` TINYINT(1)   NOT NULL DEFAULT 0,
  `is_active`            TINYINT(1)   NOT NULL DEFAULT 1,
  `last_login_at`        DATETIME     NULL,
  `created_at`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role_active` (`role`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Camions
-- ------------------------------------------------------------
CREATE TABLE `trucks` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `plate`               VARCHAR(20)  NOT NULL,
  `brand`               VARCHAR(80)  NOT NULL,
  `model`               VARCHAR(80)  NOT NULL,
  `year`                SMALLINT UNSIGNED NULL,
  `vehicle_type`        VARCHAR(60)  NULL,
  `volume_m3`           DECIMAL(5,1) NULL,
  `mileage`             INT UNSIGNED NOT NULL DEFAULT 0,
  `last_service_date`   DATE NULL,
  `next_service_date`   DATE NULL,
  `insurance_info`      VARCHAR(190) NULL COMMENT 'Assureur / n° de contrat',
  `registration_number` VARCHAR(60)  NULL COMMENT 'N° de carte grise',
  `photo_path`          VARCHAR(190) NULL,
  `ownership`           ENUM('owned','rented') NOT NULL DEFAULT 'owned' COMMENT 'Propriété ou location',
  `rental_company`      VARCHAR(150) NULL,
  `rental_contract_no`  VARCHAR(80)  NULL,
  `rental_start_date`   DATE NULL,
  `rental_end_date`     DATE NULL,
  `rental_monthly_cost` DECIMAL(10,2) NULL,
  `status`              ENUM('available','in_service','maintenance','immobilized') NOT NULL DEFAULT 'available',
  `notes`               TEXT NULL,
  `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_trucks_plate` (`plate`),
  KEY `idx_trucks_status` (`status`),
  KEY `idx_trucks_next_service` (`next_service_date`),
  KEY `idx_trucks_rental_end` (`ownership`,`rental_end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Affectations (end_date NULL = affectation en cours)
-- ------------------------------------------------------------
CREATE TABLE `truck_assignments` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `truck_id`    INT UNSIGNED NOT NULL,
  `driver_id`   INT UNSIGNED NOT NULL,
  `assigned_by` INT UNSIGNED NULL,
  `start_date`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `end_date`    DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_assign_truck_active`  (`truck_id`,`end_date`),
  KEY `idx_assign_driver_active` (`driver_id`,`end_date`),
  CONSTRAINT `fk_assign_truck`  FOREIGN KEY (`truck_id`)    REFERENCES `trucks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assign_driver` FOREIGN KEY (`driver_id`)   REFERENCES `users`  (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assign_by`     FOREIGN KEY (`assigned_by`) REFERENCES `users`  (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Contrôles quotidiens (matin / soir)
-- ------------------------------------------------------------
CREATE TABLE `daily_checks` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `truck_id`     INT UNSIGNED NOT NULL,
  `driver_id`    INT UNSIGNED NOT NULL,
  `check_type`   ENUM('morning','evening') NOT NULL,
  `check_date`   DATE NOT NULL,
  `checked_at`   DATETIME NOT NULL,
  `mileage`      INT UNSIGNED NULL,
  `comment`      TEXT NULL,
  `has_anomaly`  TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_check_driver_type_date` (`driver_id`,`check_type`,`check_date`),
  KEY `idx_checks_truck_date` (`truck_id`,`check_date`),
  KEY `idx_checks_date_type`  (`check_date`,`check_type`),
  KEY `idx_checks_anomaly`    (`has_anomaly`),
  CONSTRAINT `fk_checks_truck`  FOREIGN KEY (`truck_id`)  REFERENCES `trucks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_checks_driver` FOREIGN KEY (`driver_id`) REFERENCES `users`  (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `truck_photos` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `check_id`      INT UNSIGNED NOT NULL,
  `truck_id`      INT UNSIGNED NOT NULL,
  `driver_id`     INT UNSIGNED NOT NULL,
  `check_type`    ENUM('morning','evening') NOT NULL,
  `file_path`     VARCHAR(190) NOT NULL,
  `original_name` VARCHAR(190) NULL,
  `mime_type`     VARCHAR(60)  NOT NULL,
  `file_size`     INT UNSIGNED NOT NULL DEFAULT 0,
  `taken_at`      DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_photos_check` (`check_id`),
  KEY `idx_photos_truck_date` (`truck_id`,`taken_at`),
  KEY `idx_photos_driver` (`driver_id`),
  CONSTRAINT `fk_photos_check`  FOREIGN KEY (`check_id`)  REFERENCES `daily_checks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_photos_truck`  FOREIGN KEY (`truck_id`)  REFERENCES `trucks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_photos_driver` FOREIGN KEY (`driver_id`) REFERENCES `users`  (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Signalements
-- ------------------------------------------------------------
CREATE TABLE `incidents` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `truck_id`    INT UNSIGNED NOT NULL,
  `driver_id`   INT UNSIGNED NOT NULL,
  `category`    ENUM('engine','tires','brakes','lights','bodywork','electrical','documents','other') NOT NULL,
  `description` TEXT NOT NULL,
  `priority`    ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `status`      ENUM('new','in_progress','pending','resolved') NOT NULL DEFAULT 'new',
  `safety_risk` TINYINT(1) NOT NULL DEFAULT 0,
  `resolved_at` DATETIME NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_incidents_truck`    (`truck_id`),
  KEY `idx_incidents_driver`   (`driver_id`),
  KEY `idx_incidents_status`   (`status`,`priority`),
  KEY `idx_incidents_created`  (`created_at`),
  CONSTRAINT `fk_inc_truck`  FOREIGN KEY (`truck_id`)  REFERENCES `trucks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inc_driver` FOREIGN KEY (`driver_id`) REFERENCES `users`  (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `incident_photos` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `incident_id`   INT UNSIGNED NOT NULL,
  `file_path`     VARCHAR(190) NOT NULL,
  `original_name` VARCHAR(190) NULL,
  `mime_type`     VARCHAR(60)  NOT NULL,
  `file_size`     INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_incphotos_incident` (`incident_id`),
  CONSTRAINT `fk_incphotos_incident` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Commentaires des responsables sur un signalement (avec changement de statut éventuel)
CREATE TABLE `incident_comments` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `incident_id` INT UNSIGNED NOT NULL,
  `user_id`     INT UNSIGNED NULL,
  `comment`     TEXT NOT NULL,
  `old_status`  VARCHAR(20) NULL,
  `new_status`  VARCHAR(20) NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_inccom_incident` (`incident_id`),
  CONSTRAINT `fk_inccom_incident` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inccom_user`     FOREIGN KEY (`user_id`)     REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Documents administratifs
-- ------------------------------------------------------------
CREATE TABLE `truck_documents` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `truck_id`      INT UNSIGNED NOT NULL,
  `name`          VARCHAR(150) NOT NULL,
  `category`      ENUM('registration','insurance','inspection','transport_auth','rental_contract','maintenance','other') NOT NULL DEFAULT 'other',
  `file_path`     VARCHAR(190) NOT NULL,
  `original_name` VARCHAR(190) NULL,
  `mime_type`     VARCHAR(60)  NOT NULL,
  `file_size`     INT UNSIGNED NOT NULL DEFAULT 0,
  `expiry_date`   DATE NULL,
  `comment`       TEXT NULL,
  `uploaded_by`   INT UNSIGNED NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `replaced_at`   DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_docs_truck`  (`truck_id`,`category`),
  KEY `idx_docs_expiry` (`expiry_date`),
  CONSTRAINT `fk_docs_truck` FOREIGN KEY (`truck_id`)    REFERENCES `trucks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_docs_user`  FOREIGN KEY (`uploaded_by`) REFERENCES `users`  (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Renouvellements de contrats de location
-- ------------------------------------------------------------
CREATE TABLE `rental_renewals` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `truck_id`      INT UNSIGNED NOT NULL,
  `old_end_date`  DATE NULL,
  `new_end_date`  DATE NOT NULL,
  `note`          VARCHAR(500) NULL,
  `renewed_by`    INT UNSIGNED NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_renew_truck` (`truck_id`,`created_at`),
  CONSTRAINT `fk_renew_truck` FOREIGN KEY (`truck_id`)   REFERENCES `trucks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_renew_user`  FOREIGN KEY (`renewed_by`) REFERENCES `users`  (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Rippeurs (équipiers) : nom, prénom et numéro WhatsApp uniquement
-- ------------------------------------------------------------
CREATE TABLE `helpers` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `first_name` VARCHAR(80)  NOT NULL,
  `last_name`  VARCHAR(80)  NOT NULL,
  `whatsapp`   VARCHAR(20)  NOT NULL COMMENT 'Numéro international, chiffres uniquement (ex. 33612345678)',
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_helpers_name` (`last_name`,`first_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Planning des tournées envoyées aux équipes
-- ------------------------------------------------------------
CREATE TABLE `dispatches` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `work_date`    DATE NOT NULL,
  `truck_id`     INT UNSIGNED NOT NULL,
  `driver_id`    INT UNSIGNED NOT NULL,
  `helper1_id`   INT UNSIGNED NULL,
  `helper2_id`   INT UNSIGNED NULL,
  `note`         VARCHAR(500) NULL,
  `status`       ENUM('draft','validated','sent','partial','failed') NOT NULL DEFAULT 'draft',
  `created_by`   INT UNSIGNED NULL,
  `validated_by` INT UNSIGNED NULL,
  `validated_at` DATETIME NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_disp_date` (`work_date`),
  KEY `idx_disp_driver` (`driver_id`,`work_date`),
  KEY `idx_disp_status` (`status`),
  CONSTRAINT `fk_disp_truck`   FOREIGN KEY (`truck_id`)   REFERENCES `trucks`  (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_disp_driver`  FOREIGN KEY (`driver_id`)  REFERENCES `users`   (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_disp_h1`      FOREIGN KEY (`helper1_id`) REFERENCES `helpers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_disp_h2`      FOREIGN KEY (`helper2_id`) REFERENCES `helpers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_disp_creator` FOREIGN KEY (`created_by`) REFERENCES `users`  (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `dispatch_tours` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dispatch_id`   INT UNSIGNED NOT NULL,
  `position`      TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `kind`          ENUM('tour','run') NOT NULL DEFAULT 'tour' COMMENT 'tour = tournée, run = course',
  `client`        VARCHAR(120) NOT NULL,
  `city`          VARCHAR(120) NOT NULL,
  `file_path`     VARCHAR(190) NULL,
  `original_name` VARCHAR(190) NULL,
  `mime_type`     VARCHAR(60)  NULL,
  `file_size`     INT UNSIGNED NOT NULL DEFAULT 0,
  `token`         CHAR(32) NOT NULL COMMENT 'Lien secret de consultation de la feuille de tournée',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tours_token` (`token`),
  KEY `idx_tours_dispatch` (`dispatch_id`,`position`),
  CONSTRAINT `fk_tours_dispatch` FOREIGN KEY (`dispatch_id`) REFERENCES `dispatches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `dispatch_messages` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dispatch_id`    INT UNSIGNED NOT NULL,
  `recipient_type` ENUM('driver','helper') NOT NULL,
  `recipient_name` VARCHAR(170) NOT NULL,
  `phone`          VARCHAR(30) NULL,
  `body`           TEXT NOT NULL,
  `params`         TEXT NULL COMMENT 'Variables du modèle WhatsApp (JSON)',
  `status`         ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `provider_id`    VARCHAR(120) NULL,
  `error_message`  VARCHAR(500) NULL,
  `attempts`       TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `sent_at`        DATETIME NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dmsg_dispatch` (`dispatch_id`),
  CONSTRAINT `fk_dmsg_dispatch` FOREIGN KEY (`dispatch_id`) REFERENCES `dispatches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Entretiens
-- ------------------------------------------------------------
CREATE TABLE `maintenance_records` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `truck_id`          INT UNSIGNED NOT NULL,
  `service_date`      DATE NOT NULL,
  `service_type`      VARCHAR(120) NOT NULL,
  `description`       TEXT NULL,
  `mileage`           INT UNSIGNED NULL,
  `cost`              DECIMAL(10,2) NULL,
  `garage`            VARCHAR(150) NULL,
  `next_service_date` DATE NULL,
  `created_by`        INT UNSIGNED NULL,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_maint_truck_date` (`truck_id`,`service_date`),
  CONSTRAINT `fk_maint_truck` FOREIGN KEY (`truck_id`)   REFERENCES `trucks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_maint_user`  FOREIGN KEY (`created_by`) REFERENCES `users`  (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Notifications e-mail (event_key UNIQUE = jamais deux fois le même envoi)
-- ------------------------------------------------------------
CREATE TABLE `notifications` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type`            VARCHAR(40)  NOT NULL,
  `event_key`       VARCHAR(190) NOT NULL,
  `user_id`         INT UNSIGNED NULL,
  `recipient_email` VARCHAR(190) NOT NULL,
  `subject`         VARCHAR(255) NOT NULL,
  `body`            MEDIUMTEXT NULL,
  `status`          ENUM('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending',
  `error_message`   VARCHAR(500) NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at`         DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_notif_event` (`event_key`),
  KEY `idx_notif_type_created` (`type`,`created_at`),
  KEY `idx_notif_status` (`status`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Journal des actions importantes
-- ------------------------------------------------------------
CREATE TABLE `activity_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NULL,
  `action`      VARCHAR(60)  NOT NULL,
  `entity_type` VARCHAR(40)  NULL,
  `entity_id`   INT UNSIGNED NULL,
  `details`     VARCHAR(500) NULL,
  `ip_address`  VARCHAR(45)  NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_logs_user`    (`user_id`),
  KEY `idx_logs_action`  (`action`),
  KEY `idx_logs_entity`  (`entity_type`,`entity_id`),
  KEY `idx_logs_created` (`created_at`),
  CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Limitation des tentatives de connexion
-- ------------------------------------------------------------
CREATE TABLE `login_attempts` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`      VARCHAR(190) NOT NULL,
  `ip_address` VARCHAR(45)  NOT NULL,
  `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_attempts_email_time` (`email`,`attempted_at`),
  KEY `idx_attempts_ip_time`    (`ip_address`,`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
