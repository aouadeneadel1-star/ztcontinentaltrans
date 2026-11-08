<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Session;
use App\Helpers\View;
use App\Middleware\Auth;
use App\Models\Truck;

abstract class Controller
{
    protected function view(string $view, array $data = [], string $layout = 'app'): void
    {
        View::render($view, $data, $layout);
    }

    /** Champ texte POST nettoyé. */
    protected function post(string $key, int $max = 255): string
    {
        return clean(is_string($_POST[$key] ?? null) ? $_POST[$key] : '', $max);
    }

    /** Texte multi-lignes (conserve les retours à la ligne). */
    protected function postText(string $key, int $max = 2000): string
    {
        $v = is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
        $v = str_replace("\r\n", "\n", trim($v));
        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
        return mb_substr($v, 0, $max);
    }

    protected function getInt(string $key): ?int
    {
        return int_or_null($_GET[$key] ?? null);
    }

    protected function postInt(string $key): ?int
    {
        return int_or_null($_POST[$key] ?? null);
    }

    /** Filtres de liste lus dans l'URL (texte nettoyé). */
    protected function filters(array $keys): array
    {
        $f = [];
        foreach ($keys as $k) {
            $v = $_GET[$k] ?? '';
            $f[$k] = is_string($v) ? clean($v, 100) : '';
        }
        foreach (['from', 'to', 'date'] as $d) {
            if (isset($f[$d])) {
                $f[$d] = valid_date($f[$d]) ?? '';
            }
        }
        return $f;
    }

    /** Retour au formulaire avec les erreurs et les anciennes valeurs. */
    protected function fail(array $errors, string $route, array $params = []): never
    {
        Session::setOld($_POST);
        flash('error', implode("\n", $errors));
        redirect($route, $params);
    }

    protected function notFound(string $what = 'Cette page'): never
    {
        View::error(404, 'Introuvable', $what . ' n\'existe pas ou a été supprimé.');
    }

    protected function forbidden(): never
    {
        View::error(403, 'Accès refusé', 'Vous n\'avez pas l\'autorisation d\'accéder à cette ressource.');
    }

    /** Camion existant, sinon 404. */
    protected function truckOr404(?int $id): array
    {
        $t = $id ? Truck::find($id) : null;
        return $t ?? $this->notFound('Ce camion');
    }

    /** Camion autorisé pour l'utilisateur : tout pour le personnel, uniquement le sien pour un conducteur. */
    protected function authorizedTruck(?int $id): array
    {
        $t = $this->truckOr404($id);
        if (Auth::is('driver') && (int)($t['driver_id'] ?? 0) !== Auth::id()) {
            $this->forbidden();
        }
        return $t;
    }
}
