<?php
/** Libellés français des valeurs énumérées en base. */
return [
    'roles' => [
        'admin'   => 'Administrateur',
        'manager' => 'Responsable de flotte',
        'driver'  => 'Conducteur',
    ],
    'truck_status' => [
        'available'   => 'Disponible',
        'in_service'  => 'En circulation',
        'maintenance' => 'En maintenance',
        'immobilized' => 'Immobilisé',
    ],
    'ownership' => [
        'owned'  => 'Propriété',
        'rented' => 'Location',
    ],
    'check_type' => [
        'morning' => 'Matin',
        'evening' => 'Soir',
    ],
    'incident_category' => [
        'engine'     => 'Problème moteur',
        'tires'      => 'Pneus',
        'brakes'     => 'Freinage',
        'lights'     => 'Éclairage',
        'bodywork'   => 'Carrosserie',
        'electrical' => 'Problème électrique',
        'documents'  => 'Documents administratifs',
        'other'      => 'Autre',
    ],
    'dispatch_status' => [
        'draft'     => 'Brouillon',
        'validated' => 'Validé, à envoyer',
        'sent'      => 'Envoyé',
        'partial'   => 'Envoi partiel',
        'failed'    => 'Échec d\'envoi',
    ],
    'tour_kind' => [
        'tour' => 'Tournée',
        'run'  => 'Course',
    ],
    'priority' => [
        'low'      => 'Faible',
        'medium'   => 'Moyenne',
        'high'     => 'Élevée',
        'critical' => 'Critique',
    ],
    'incident_status' => [
        'new'         => 'Nouveau',
        'in_progress' => 'En cours de traitement',
        'pending'     => 'En attente',
        'resolved'    => 'Résolu',
    ],
    'doc_category' => [
        'registration'   => 'Carte grise',
        'insurance'      => 'Assurance',
        'inspection'     => 'Contrôle technique',
        'transport_auth' => 'Autorisation de transport',
        'rental_contract' => 'Contrat de location',
        'maintenance'    => 'Document d\'entretien',
        'other'          => 'Autre document',
    ],
    // Catégories dont les dates d'expiration déclenchent des alertes
    'doc_required' => ['registration', 'insurance', 'inspection'],
];
