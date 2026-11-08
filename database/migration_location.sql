-- ============================================================
--  MIGRATION : camions en location (à importer UNE SEULE FOIS
--  si la base existe déjà ; inutile pour une nouvelle installation)
-- ============================================================
SET NAMES utf8mb4;

ALTER TABLE `trucks`
  ADD COLUMN `ownership` ENUM('owned','rented') NOT NULL DEFAULT 'owned' COMMENT 'Propriété ou location' AFTER `photo_path`,
  ADD COLUMN `rental_company` VARCHAR(150) NULL AFTER `ownership`,
  ADD COLUMN `rental_contract_no` VARCHAR(80) NULL AFTER `rental_company`,
  ADD COLUMN `rental_start_date` DATE NULL AFTER `rental_contract_no`,
  ADD COLUMN `rental_end_date` DATE NULL AFTER `rental_start_date`,
  ADD COLUMN `rental_monthly_cost` DECIMAL(10,2) NULL AFTER `rental_end_date`,
  ADD KEY `idx_trucks_rental_end` (`ownership`,`rental_end_date`);

ALTER TABLE `truck_documents`
  MODIFY `category` ENUM('registration','insurance','inspection','transport_auth','rental_contract','maintenance','other') NOT NULL DEFAULT 'other';

CREATE TABLE IF NOT EXISTS `rental_renewals` (
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
