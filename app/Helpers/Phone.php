<?php
declare(strict_types=1);

namespace App\Helpers;

final class Phone
{
    /** Numéro international en chiffres (ex. 33612345678) ou null si invalide. */
    public static function normalize(?string $raw, ?string $country = null): ?string
    {
        $raw = trim((string)$raw);
        if ($raw === '') {
            return null;
        }
        $country = $country ?? (string)(require ROOT_PATH . '/config/whatsapp.php')['default_country'];
        $plus = str_starts_with($raw, '+');
        $d = preg_replace('/\D+/', '', $raw) ?? '';
        if (!$plus && str_starts_with($d, '00')) {
            $d = substr($d, 2);
        } elseif (!$plus && str_starts_with($d, '0')) {
            $d = $country . substr($d, 1);
        }
        return preg_match('/^[1-9][0-9]{8,14}$/', $d) ? $d : null;
    }

    /** Affichage lisible : +33 6 12 34 56 78 */
    public static function pretty(?string $digits): string
    {
        if (!$digits) {
            return '—';
        }
        if (str_starts_with($digits, '33') && strlen($digits) === 11) {
            return '+33 ' . substr($digits, 2, 1) . ' ' . implode(' ', str_split(substr($digits, 3), 2));
        }
        return '+' . $digits;
    }
}
