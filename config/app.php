<?php
/**
 * Configuration générale — ZT Continental Trans
 * Les valeurs peuvent être surchargées par config/app.local.php (non versionné).
 */
$config = [
    'name'      => 'ZT Continental Trans',
    'short'     => 'ZT Continental',
    // URL publique SANS slash final, ex. https://votre-domaine.com
    // Laisser vide pour une détection automatique.
    'base_url'  => '',
    'timezone'  => 'Europe/Paris',
    'debug'     => false,

    // Sessions (en secondes)
    'session_idle_timeout'     => 60 * 60 * 8,   // 8 h d'inactivité
    'session_absolute_timeout' => 60 * 60 * 14,  // 14 h maximum

    // Mots de passe
    'password_min_length' => 10,

    // Connexion : tentatives max par fenêtre
    'login_max_attempts' => 5,
    'login_window_min'   => 15,

    // Fichiers
    'max_photo_bytes'    => 12 * 1024 * 1024,  // 12 Mo par photo (avant recompression)
    'max_document_bytes' => 10 * 1024 * 1024,  // 10 Mo par document
    'max_photos_per_check'    => 10,
    'max_photos_per_incident' => 5,
    'photo_max_dimension'     => 1920,         // px (recompression JPEG côté serveur)

    // Alertes d'expiration (jours avant échéance)
    'expiry_alert_days' => [30, 15, 7],
    // Alertes de fin de contrat de location (jours avant la date de fin)
    'rental_alert_days' => [60, 30, 15, 7],
    // Alerte entretien (jours avant la prochaine date d'entretien)
    'service_alert_days' => 15,

    // Pagination
    'per_page' => 20,

    // Heure à partir de laquelle un contrôle manquant est signalé
    'morning_check_deadline' => '10:00',
    'evening_check_deadline' => '19:00',
];

if (is_file(__DIR__ . '/app.local.php')) {
    $config = array_replace($config, require __DIR__ . '/app.local.php');
}
return $config;
