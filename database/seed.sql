-- ============================================================
--  Données initiales : compte administrateur uniquement
--  Mot de passe provisoire : ChangeMe!2026
--  (changement obligatoire à la première connexion)
--  À exécuter APRÈS schema.sql
-- ============================================================
SET NAMES utf8mb4;

INSERT INTO `users`
  (`role`, `first_name`, `last_name`, `email`, `phone`, `password_hash`, `must_change_password`, `is_active`)
VALUES
  ('admin', 'Administrateur', 'ZT Continental Trans', 'admin@ztcontinentaltrans.com', NULL,
   '$2y$10$h/Q7LgPWQ2nsePo9I2HTDuSzBTslGZk.HcS.x.fkCeaoNLeti.uva', 1, 1);
