<?php
/**
 * Connexion MySQL / MariaDB — base cPanel « yuwe7714_camion » (projet ZT Continental Trans).
 * Copiez ce fichier en config/database.local.php pour vos identifiants réels
 * (le fichier .local.php prend le dessus et n'est pas à versionner).
 */
$db = [
    'host'     => 'localhost',
    'port'     => 3306,
    'name'     => 'yuwe7714_camion',
    'user'     => 'yuwe7714_camion',
    'password' => '',   // ← à renseigner : mot de passe de l'utilisateur MySQL yuwe7714_camion
    'charset'  => 'utf8mb4',
];

if (is_file(__DIR__ . '/database.local.php')) {
    $db = array_replace($db, require __DIR__ . '/database.local.php');
}
return $db;
