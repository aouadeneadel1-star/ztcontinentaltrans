<?php
/**
 * Configuration PHPMailer (SMTP).
 * Copiez en config/mail.local.php pour vos identifiants réels.
 * Tant que 'enabled' est false, les notifications sont enregistrées
 * (table notifications, statut « skipped ») mais aucun e-mail n'est envoyé.
 */
$mail = [
    'enabled'    => true,
    'host'       => 'carambole.o2switch.net',
    'port'       => 465,
    'encryption' => 'ssl',          // SMTP o2switch : SSL, port 465
    'username'   => 'admin@ztcontinentaltrans.com',
    'password'   => '',   // ← à renseigner : mot de passe du compte e-mail admin@ztcontinentaltrans.com
    'from_email' => 'admin@ztcontinentaltrans.com',
    'from_name'  => 'ZT Continental Trans',
    'timeout'    => 8,
];

if (is_file(__DIR__ . '/mail.local.php')) {
    $mail = array_replace($mail, require __DIR__ . '/mail.local.php');
}
return $mail;
