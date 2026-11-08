<?php
/**
 * Envoi WhatsApp des tournées (API WhatsApp Business Cloud de Meta).
 * Mettez vos identifiants dans config/whatsapp.local.php (non versionné).
 *
 * Tant que 'enabled' est false (ou sans jeton), l'envoi automatique est désactivé :
 * les messages sont préparés et l'administrateur peut les envoyer en un clic
 * via un lien WhatsApp (wa.me).
 *
 * mode 'template' : message modèle approuvé par Meta (obligatoire pour écrire en
 *                   premier à un contact : c'est le cas ici). Voir README.
 * mode 'text'     : texte libre (ne fonctionne que si le contact vous a écrit
 *                   dans les dernières 24 h).
 */
$wa = [
    'enabled'         => false,
    'mode'            => 'template',
    'token'           => '',            // jeton d'accès permanent
    'phone_number_id' => '',            // identifiant du numéro WhatsApp Business
    'api_base'        => 'https://graph.facebook.com',
    'api_version'     => 'v21.0',
    'template_name'   => 'planning_tournee',
    'template_lang'   => 'fr',
    'default_country' => '33',          // indicatif ajouté aux numéros commençant par 0
    'timeout'         => 15,
];
if (is_file(__DIR__ . '/whatsapp.local.php')) {
    $wa = array_replace($wa, require __DIR__ . '/whatsapp.local.php');
}
return $wa;
