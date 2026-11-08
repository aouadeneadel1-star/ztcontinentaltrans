# ZT Continental Trans – Gestion de flotte

Application web (PHP 8.2+, MySQL/MariaDB, HTML5/CSS3/JavaScript vanilla, PDO, PHPMailer) pour gérer camions, conducteurs, contrôles photo matin/soir, documents et signalements. Charte graphique : bleu marine `#0B2A5E` et rouge `#E30613` (logo ZT).

## Prérequis
PHP ≥ 8.2 avec extensions `pdo_mysql`, `gd`, `fileinfo`, `mbstring`, `openssl` (+ `exif` et `zip` recommandées) ; MySQL 5.7+/MariaDB 10.3+ ; hébergement PHP classique (Apache, `mod_rewrite` non requis).

## Installation sur o2switch (cPanel) — configuration déjà préparée
Valeurs préremplies : base **yuwe7714_camion**, utilisateur MySQL **yuwe7714_camion**, SMTP **carambole.o2switch.net** (SSL, port 465), compte **admin@ztcontinentaltrans.com**. Il ne reste à saisir que les deux mots de passe.

1. **Base** : dans cPanel > phpMyAdmin, sélectionnez `yuwe7714_camion` > *Importer* : `database/schema.sql`, puis `database/seed.sql` (compte administrateur), puis éventuellement `database/demo.sql` (données de test, à éviter en production). Vérifiez que l'utilisateur `yuwe7714_camion` a bien tous les privilèges sur cette base.
2. **Mots de passe** : créez `config/database.local.php` et `config/mail.local.php` sur le serveur (ils ne sont pas dans le dépôt) :
   ```php
   <?php return ['password' => 'MOT_DE_PASSE_MYSQL'];        // config/database.local.php
   <?php return ['password' => 'MOT_DE_PASSE_EMAIL'];        // config/mail.local.php
   ```
   Dans `config/app.local.php`, indiquez aussi `<?php return ['base_url' => 'https://votre-domaine.com'];` (liens des e-mails).
3. **Fichiers** : envoyez le contenu du dossier du projet (Gestionnaire de fichiers cPanel ou FTP), idéalement hors de `public_html`, puis faites pointer le domaine / sous-domaine sur le dossier `public/` (cPanel > Domaines). Si le projet est placé dans `public_html`, le `.htaccess` racine redirige vers `public/` et bloque le reste.
4. **Droits** : `storage/` doit être inscriptible (750 ou 755).
5. **PHP** : cPanel > *Sélectionner la version de PHP* : 8.2 ou plus, extensions `pdo_mysql`, `gd`, `fileinfo`, `mbstring`, `exif`, `zip` cochées.
6. **HTTPS** : activez le certificat SSL (AutoSSL) — nécessaire pour la caméra du téléphone et l'installation comme application.
7. **Connexion** : `admin@ztcontinentaltrans.com` / `ChangeMe!2026` — changement obligatoire à la première connexion.
8. **Cron** (cPanel > Tâches Cron) : voir plus bas.

### Installation sur un autre serveur
Créez la base et l'utilisateur, importez les mêmes fichiers SQL, puis adaptez `config/database.local.php` (`host`, `name`, `user`, `password`) et `config/mail.local.php`. Test local : `php -S 127.0.0.1:8080 -t public`.

## Camions en location
Dans la fiche d'un camion : *Mode de détention* = Location, avec loueur, n° de contrat, dates de début/fin et loyer. Le bouton « Renouveler le contrat » enregistre la nouvelle date de fin, l'historique des renouvellements et envoie un e-mail de confirmation. Des alertes e-mail de fin de contrat partent automatiquement à 60, 30, 15 et 7 jours, puis une fois le contrat terminé (`cron/daily.php`, réglage `rental_alert_days` dans `config/app.php`). Les contrats à échéance proche apparaissent aussi dans « À surveiller » du tableau de bord. La catégorie de document « Contrat de location » permet de joindre le contrat.

**Base déjà installée ?** Importez une seule fois `database/migration_location.sql` dans `yuwe7714_camion` (phpMyAdmin > Importer). Une nouvelle installation n'en a pas besoin : `schema.sql` contient déjà ces champs.

## E-mails (PHPMailer)
La configuration SMTP o2switch est déjà dans `config/mail.php` ; il suffit de renseigner le mot de passe dans `config/mail.local.php` (pour couper les envois : `'enabled' => false`). Notifications envoyées aux administrateurs/responsables : nouveau signalement, fin de contrat de location, renouvellement de contrat, problème critique, anomalie de contrôle, documents expirant (30/15/7 jours puis expirés), entretien à prévoir, contrôles manquants. Chaque événement n'est envoyé qu'une fois (clé unique en base). Historique : menu « Notifications e-mail ».

## Tournées et envoi WhatsApp

Menu **Tournées** : on choisit la date, le conducteur, le camion (celui du conducteur est proposé), jusqu'à 2 rippeurs, puis une ou plusieurs **tournées / courses** (client et ville saisis à la main, feuille de tournée en PDF ou photo). L'enregistrement affiche l'aperçu exact des messages ; le bouton **Valider** envoie ensuite automatiquement un message WhatsApp à chaque personne (avec qui elle travaille, le camion, les missions et le lien de la feuille). Un message en échec peut être renvoyé seul.
Les rippeurs (menu **Rippeurs**) n'ont pas de compte : nom, prénom et numéro WhatsApp uniquement.

**Base déjà installée** : importer une seule fois `database/migration_tournees.sql` dans `yuwe7714_camion`.

**Activer l'envoi automatique** (API WhatsApp Business Cloud de Meta) :
1. Créer une application sur developers.facebook.com, ajouter WhatsApp et enregistrer le numéro de l'entreprise ; générer un jeton d'accès permanent.
2. Créer un modèle de message (catégorie *Utilitaire*, langue *Français*) nommé `planning_tournee` avec ce texte et 6 variables :
   `Bonjour {{1}}, votre planning du {{2}} : camion {{3}}. Équipe : {{4}}. Missions : {{5}}. Détails : {{6}}`
   Il doit être approuvé par Meta (un message modèle est obligatoire pour écrire en premier à un contact).
3. Créer `config/whatsapp.local.php` :
   `<?php return ['enabled' => true, 'token' => '...', 'phone_number_id' => '...'];`
Sans cette configuration, tout fonctionne en mode manuel : après validation, un bouton « Ouvrir WhatsApp » prépare le message pour chaque personne.
Les feuilles de tournée sont accessibles par un lien secret non devinable (sans connexion), uniquement après validation.

## Tâches planifiées (cron)
```
0 7  * * *  php /chemin/projet/cron/daily.php                      # alertes + sauvegarde BDD (14 jours conservés)
15 10 * * * php /chemin/projet/cron/daily.php --checks=morning     # rappel contrôles du matin manquants
15 19 * * * php /chemin/projet/cron/daily.php --checks=evening     # rappel contrôles du soir manquants
```
La sauvegarde utilise `mysqldump` (fichiers dans `storage/backups/`).

## Rôles
- **Administrateur** : tout (camions, utilisateurs, documents, journal d'activité…).
- **Responsable de flotte** : consultation camions/documents, affectations, entretiens, contrôles/photos, traitement des signalements, historiques/exports.
- **Conducteur** : uniquement son camion, ses documents, ses contrôles et ses signalements.

## Sécurité (résumé)
`password_hash/verify`, limitation des tentatives de connexion, sessions strictes (régénération, expiration), CSRF sur tous les POST, échappement XSS systématique, PDO préparé, rôles vérifiés côté serveur dans le routeur, fichiers hors du dossier public servis par un contrôleur qui vérifie l'autorisation, validation du vrai type MIME, tailles limitées, noms aléatoires, photos recompressées (EXIF/GPS supprimés), journal d'activité, export CSV protégé contre l'injection de formules.

## Fonctionnalités notables
Photos : bouton « Prendre une photo » (appareil du smartphone) ou « Choisir une image », aperçus avant envoi. Comparaison matin/soir, téléchargement individuel ou ZIP. Exports CSV et PDF des historiques. PWA installable (manifest + service worker limité aux fichiers statiques : aucune donnée privée n'est mise en cache).

## Structure
`public/` (index.php, assets) · `app/` (Controllers, Models, Views, Middleware, Helpers, Lib/PHPMailer) · `config/` · `storage/` · `database/` · `cron/`

PHPMailer 6.9.3 est fourni dans `app/Lib/PHPMailer` (licence LGPL 2.1).
