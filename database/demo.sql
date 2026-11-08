-- ============================================================
--  DONNÉES DE DÉMONSTRATION (facultatif, pour tester l'application)
--  Ne pas importer en production.
--  Mot de passe de tous les comptes de démo : Demo!Pass2026
--  À exécuter APRÈS schema.sql et seed.sql
-- ============================================================
SET NAMES utf8mb4;

INSERT INTO `users` (`role`,`first_name`,`last_name`,`email`,`phone`,`password_hash`,`must_change_password`) VALUES
('manager','Sophie','Martin','responsable@demo.ztcontinentaltrans.com','0601020304','$2y$10$YNHikRiBZnqt2sFqrYajWuBlShIJ418zza8v.ZJjcpPZtg95X.hDa',0),
('driver','Karim','Benali','karim@demo.ztcontinentaltrans.com','0611223344','$2y$10$YNHikRiBZnqt2sFqrYajWuBlShIJ418zza8v.ZJjcpPZtg95X.hDa',0),
('driver','Julien','Moreau','julien@demo.ztcontinentaltrans.com','0655667788','$2y$10$YNHikRiBZnqt2sFqrYajWuBlShIJ418zza8v.ZJjcpPZtg95X.hDa',0),
('driver','Paul','Durand','paul@demo.ztcontinentaltrans.com','0677889900','$2y$10$YNHikRiBZnqt2sFqrYajWuBlShIJ418zza8v.ZJjcpPZtg95X.hDa',0);

INSERT INTO `trucks` (`plate`,`brand`,`model`,`year`,`vehicle_type`,`volume_m3`,`mileage`,`last_service_date`,`next_service_date`,`insurance_info`,`registration_number`,`status`) VALUES
('AB-123-CD','Renault','Master L3H2',2021,'Fourgon',16.0,84500,DATE_SUB(CURDATE(), INTERVAL 120 DAY),DATE_ADD(CURDATE(), INTERVAL 10 DAY),'AXA – contrat 4587123','2021-AB-123456','available'),
('EF-456-GH','Mercedes','Sprinter 316',2022,'Fourgon',20.0,52300,DATE_SUB(CURDATE(), INTERVAL 60 DAY),DATE_ADD(CURDATE(), INTERVAL 90 DAY),'Allianz – contrat 998877','2022-EF-654321','available'),
('IJ-789-KL','Iveco','Daily 35S14',2020,'Fourgon',12.0,121000,DATE_SUB(CURDATE(), INTERVAL 200 DAY),DATE_SUB(CURDATE(), INTERVAL 5 DAY),'MMA – contrat 112233','2020-IJ-789012','maintenance');

-- Affectations en cours
INSERT INTO `truck_assignments` (`truck_id`,`driver_id`,`assigned_by`,`start_date`) VALUES
(1,(SELECT id FROM users WHERE email='karim@demo.ztcontinentaltrans.com'),1,NOW()),
(2,(SELECT id FROM users WHERE email='julien@demo.ztcontinentaltrans.com'),1,NOW());

-- Entretien passé
INSERT INTO `maintenance_records` (`truck_id`,`service_date`,`service_type`,`description`,`mileage`,`cost`,`garage`,`next_service_date`,`created_by`) VALUES
(1,DATE_SUB(CURDATE(), INTERVAL 120 DAY),'Révision complète','Vidange, filtres, plaquettes avant',79800,640.00,'Garage Central',DATE_ADD(CURDATE(), INTERVAL 10 DAY),1);

-- Un signalement de démonstration
INSERT INTO `incidents` (`truck_id`,`driver_id`,`category`,`description`,`priority`,`status`,`safety_risk`)
VALUES (3,(SELECT id FROM users WHERE email='paul@demo.ztcontinentaltrans.com'),'brakes','Bruit anormal au freinage, pédale molle.','high','in_progress',1);

-- Camion en location (fin de contrat dans 12 jours)
UPDATE `trucks` SET `ownership`='rented', `rental_company`='Location Express Pro', `rental_contract_no`='LEP-2025-0418',
  `rental_start_date`=DATE_SUB(CURDATE(), INTERVAL 11 MONTH), `rental_end_date`=DATE_ADD(CURDATE(), INTERVAL 12 DAY), `rental_monthly_cost`=1290.00
WHERE `plate`='EF-456-GH';
