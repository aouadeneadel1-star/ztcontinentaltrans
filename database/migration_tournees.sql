-- ============================================================
--  MIGRATION : planning des tournées + rippeurs + envoi WhatsApp
--  À importer UNE SEULE FOIS sur une base déjà installée.
-- ============================================================
SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- Rippeurs (équipiers) : nom, prénom et numéro WhatsApp uniquement
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `helpers` (
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
CREATE TABLE IF NOT EXISTS `dispatches` (
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

CREATE TABLE IF NOT EXISTS `dispatch_tours` (
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

CREATE TABLE IF NOT EXISTS `dispatch_messages` (
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

