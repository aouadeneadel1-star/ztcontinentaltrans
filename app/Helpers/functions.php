<?php
declare(strict_types=1);

use App\Helpers\Csrf;
use App\Helpers\Session;

/** Valeur de configuration (notation simple). */
function config(string $key, mixed $default = null): mixed
{
    return $GLOBALS['zt_config'][$key] ?? $default;
}

/** Échappement HTML (protection XSS) — à utiliser pour toute sortie. */
function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

/** URL de base de l'application (sans slash final). */
function base_url(): string
{
    $configured = (string)config('base_url', '');
    if ($configured !== '') {
        return rtrim($configured, '/');
    }
    $scheme = is_https() ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    return $scheme . '://' . $host . $dir;
}

/** Chemin relatif (sans domaine) vers la racine publique. */
function base_path(): string
{
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    return $dir;
}

/** URL d'une route : url('truck/show', ['id' => 3]) */
function url(string $route = '', array $params = []): string
{
    $query = $route !== '' ? ['r' => $route] + $params : $params;
    $query = array_filter($query, static fn($v) => $v !== null && $v !== '');
    return base_path() . '/index.php' . ($query ? '?' . http_build_query($query) : '');
}

/** URL absolue d'une route (e-mails). */
function absolute_url(string $route, array $params = []): string
{
    $query = ['r' => $route] + $params;
    return base_url() . '/index.php?' . http_build_query($query);
}

function asset(string $path): string
{
    $file = ROOT_PATH . '/public/assets/' . ltrim($path, '/');
    $v = is_file($file) ? filemtime($file) : 1;
    return base_path() . '/assets/' . ltrim($path, '/') . '?v=' . $v;
}

function redirect(string $route, array $params = []): never
{
    header('Location: ' . url($route, $params));
    exit;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

function flash(string $type, string $message): void
{
    Session::flash($type, $message);
}

/** Ancienne valeur d'un champ de formulaire (après erreur de validation). */
function old(string $key, mixed $default = ''): string
{
    $old = $_SESSION['_old'] ?? [];
    return e($old[$key] ?? $default);
}

/** Libellé français d'une valeur énumérée. */
function label(string $group, ?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    return $GLOBALS['zt_labels'][$group][$value] ?? $value;
}

function labels(string $group): array
{
    return $GLOBALS['zt_labels'][$group] ?? [];
}

/** Date au format français jj/mm/aaaa. */
function fdate(?string $date): string
{
    if (!$date || str_starts_with($date, '0000')) {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date('d/m/Y', $ts) : '—';
}

function fdatetime(?string $date): string
{
    if (!$date || str_starts_with($date, '0000')) {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date('d/m/Y à H:i', $ts) : '—';
}

function ftime(?string $date): string
{
    $ts = $date ? strtotime($date) : false;
    return $ts ? date('H:i', $ts) : '—';
}

function fkm(?int $km): string
{
    return $km === null ? '—' : number_format($km, 0, ',', ' ') . ' km';
}

function fsize(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1, ',', ' ') . ' Mo';
    }
    return max(1, (int)round($bytes / 1024)) . ' Ko';
}

function today_fr(): string
{
    $jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    $mois  = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    return $jours[(int)date('w')] . ' ' . date('j') . ' ' . $mois[(int)date('n') - 1] . ' ' . date('Y');
}

/** Pastille de statut : classe CSS selon la valeur. */
function badge(string $text, string $tone = 'neutral'): string
{
    return '<span class="badge badge-' . e($tone) . '">' . e($text) . '</span>';
}

function truck_status_badge(string $status): string
{
    $tone = ['available' => 'green', 'in_service' => 'blue', 'maintenance' => 'orange', 'immobilized' => 'red'][$status] ?? 'neutral';
    return badge(label('truck_status', $status), $tone);
}

function priority_badge(string $p): string
{
    $tone = ['low' => 'neutral', 'medium' => 'blue', 'high' => 'orange', 'critical' => 'red'][$p] ?? 'neutral';
    return badge(label('priority', $p), $tone);
}

function incident_status_badge(string $s): string
{
    $tone = ['new' => 'red', 'in_progress' => 'blue', 'pending' => 'orange', 'resolved' => 'green'][$s] ?? 'neutral';
    return badge(label('incident_status', $s), $tone);
}

/** Pastille d'expiration selon le nombre de jours restants. */
function expiry_badge(?string $date): string
{
    if (!$date) {
        return badge('Sans échéance', 'neutral');
    }
    $days = (int)floor((strtotime($date) - strtotime(date('Y-m-d'))) / 86400);
    if ($days < 0) {
        return badge('Expiré depuis ' . abs($days) . ' j', 'red');
    }
    if ($days <= 30) {
        return badge('Expire dans ' . $days . ' j', 'orange');
    }
    return badge('Valide', 'green');
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

/** Valide une date AAAA-MM-JJ, retourne la chaîne ou null. */
function valid_date(?string $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    $d = DateTime::createFromFormat('Y-m-d', $value);
    return ($d && $d->format('Y-m-d') === $value) ? $value : null;
}

/** Entier positif ou null. */
function int_or_null(mixed $value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }
    $v = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    return $v === false ? null : (int)$v;
}

/** Texte nettoyé (trim + longueur max). */
function clean(?string $value, int $max = 255): string
{
    $value = trim((string)$value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    return mb_substr($value, 0, $max);
}

/** Icône SVG inline : icon('truck'). */
function icon(string $name, string $class = ''): string
{
    return \App\Helpers\Icons::svg($name, $class);
}

/** Date longue en français : lundi 9 novembre 2026 */
function fdate_long(?string $date): string
{
    $ts = $date ? strtotime($date) : false;
    if (!$ts) {
        return '—';
    }
    $jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    $mois  = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    return $jours[(int)date('w', $ts)] . ' ' . date('j', $ts) . ' ' . $mois[(int)date('n', $ts) - 1] . ' ' . date('Y', $ts);
}
