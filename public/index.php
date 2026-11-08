<?php
declare(strict_types=1);

/**
 * Point d'entrée unique (front controller).
 * Toutes les routes sont déclarées ici : contrôleur, action, rôles autorisés.
 * Les rôles sont vérifiés côté serveur AVANT l'exécution de l'action.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Controllers as C;
use App\Helpers\Csrf;
use App\Helpers\View;
use App\Middleware\Auth;

const ADMIN = ['admin'];
const STAFF = ['admin', 'manager'];
const DRIVER = ['driver'];
const ALL = ['admin', 'manager', 'driver'];

// 'METHODE route' => [Contrôleur, action, rôles (null = public)]
$routes = [
    // Authentification
    'GET auth/login'      => [C\AuthController::class, 'loginForm', null],
    'POST auth/login'     => [C\AuthController::class, 'login', null],
    'POST auth/logout'    => [C\AuthController::class, 'logout', ALL],
    'GET auth/password'   => [C\AuthController::class, 'passwordForm', ALL],
    'POST auth/password'  => [C\AuthController::class, 'password', ALL],

    // Tableau de bord et accueil conducteur
    'GET dashboard/index' => [C\DashboardController::class, 'index', STAFF],
    'GET driver/home'     => [C\DriverController::class, 'home', DRIVER],
    'GET driver/truck'    => [C\DriverController::class, 'truck', DRIVER],

    // Camions
    'GET truck/index'     => [C\TruckController::class, 'index', STAFF],
    'GET truck/show'      => [C\TruckController::class, 'show', STAFF],
    'GET truck/form'      => [C\TruckController::class, 'form', ADMIN],
    'POST truck/save'     => [C\TruckController::class, 'save', ADMIN],
    'POST truck/delete'   => [C\TruckController::class, 'delete', ADMIN],
    'POST truck/assign'   => [C\TruckController::class, 'assign', STAFF],
    'POST truck/status'   => [C\TruckController::class, 'status', STAFF],
    'POST truck/renew'    => [C\TruckController::class, 'renew', STAFF],
    'POST truck/maintenance' => [C\TruckController::class, 'maintenanceSave', STAFF],

    // Tournées et rippeurs (envoi WhatsApp)
    'GET dispatch/index'    => [C\DispatchController::class, 'index', STAFF],
    'GET dispatch/form'     => [C\DispatchController::class, 'form', STAFF],
    'POST dispatch/save'    => [C\DispatchController::class, 'save', STAFF],
    'GET dispatch/show'     => [C\DispatchController::class, 'show', STAFF],
    'POST dispatch/validate' => [C\DispatchController::class, 'validate', STAFF],
    'POST dispatch/resend'  => [C\DispatchController::class, 'resend', STAFF],
    'POST dispatch/mark'    => [C\DispatchController::class, 'mark', STAFF],
    'POST dispatch/delete'  => [C\DispatchController::class, 'delete', STAFF],
    'GET helper/index'      => [C\HelperController::class, 'index', STAFF],
    'GET helper/form'       => [C\HelperController::class, 'form', STAFF],
    'POST helper/save'      => [C\HelperController::class, 'save', STAFF],
    'POST helper/toggle'    => [C\HelperController::class, 'toggle', STAFF],
    'POST helper/delete'    => [C\HelperController::class, 'delete', STAFF],

    // Utilisateurs (administrateur)
    'GET user/index'      => [C\UserController::class, 'index', ADMIN],
    'GET user/form'       => [C\UserController::class, 'form', ADMIN],
    'POST user/save'      => [C\UserController::class, 'save', ADMIN],
    'POST user/toggle'    => [C\UserController::class, 'toggle', ADMIN],

    // Contrôles quotidiens
    'GET check/form'      => [C\CheckController::class, 'form', DRIVER],
    'POST check/store'    => [C\CheckController::class, 'store', DRIVER],
    'GET check/show'      => [C\CheckController::class, 'show', ALL],
    'GET check/index'     => [C\CheckController::class, 'index', STAFF],
    'GET check/compare'   => [C\CheckController::class, 'compare', STAFF],

    // Signalements
    'GET incident/form'   => [C\IncidentController::class, 'form', DRIVER],
    'POST incident/store' => [C\IncidentController::class, 'store', DRIVER],
    'GET incident/mine'   => [C\IncidentController::class, 'mine', DRIVER],
    'GET incident/index'  => [C\IncidentController::class, 'index', STAFF],
    'GET incident/show'   => [C\IncidentController::class, 'show', ALL],
    'POST incident/comment' => [C\IncidentController::class, 'comment', STAFF],

    // Documents (gestion : administrateur ; consultation : voir file/document)
    'POST document/store'   => [C\DocumentController::class, 'store', ADMIN],
    'POST document/replace' => [C\DocumentController::class, 'replace', ADMIN],
    'POST document/delete'  => [C\DocumentController::class, 'delete', ADMIN],

    // Fichiers protégés
    'GET file/photo'      => [C\FileController::class, 'photo', ALL],
    'GET file/incident'   => [C\FileController::class, 'incident', ALL],
    'GET file/truck'      => [C\FileController::class, 'truck', ALL],
    'GET file/document'   => [C\FileController::class, 'document', ALL],
    'GET file/sheet'      => [C\FileController::class, 'sheet', null],   // lien secret envoyé par WhatsApp
    'GET file/zip'        => [C\FileController::class, 'checkZip', STAFF],

    // Historiques et exports
    'GET history/index'   => [C\HistoryController::class, 'index', STAFF],
    'GET history/export'  => [C\HistoryController::class, 'export', STAFF],

    // Administration
    'GET admin/logs'          => [C\AdminController::class, 'logs', ADMIN],
    'GET admin/notifications' => [C\AdminController::class, 'notifications', STAFF],
];

$method = $_SERVER['REQUEST_METHOD'] === 'POST' ? 'POST' : 'GET';
$route  = is_string($_GET['r'] ?? null) ? trim($_GET['r'], '/') : '';

if ($route === '') {
    // Accueil : redirection selon la connexion / le rôle
    Auth::user() ? redirect(Auth::home()) : redirect('auth/login');
}

$entry = $routes[$method . ' ' . $route] ?? null;
if ($entry === null) {
    View::error(404, 'Page introuvable', 'La page demandée n\'existe pas.');
}
[$class, $action, $roles] = $entry;

try {
    if ($method === 'POST') {
        Csrf::verify();
    }
    if ($roles !== null) {
        Auth::require($roles);
    }
    (new $class())->$action();
} catch (Throwable $e) {
    error_log($e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    View::error(500, 'Erreur inattendue', config('debug') ? $e->getMessage() : 'Une erreur est survenue. Réessayez ou contactez l\'administrateur.');
}
