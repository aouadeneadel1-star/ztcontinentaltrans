<?php
declare(strict_types=1);

namespace App\Helpers;

/** Journalisation des actions sensibles (table activity_logs). */
final class Log
{
    public static function add(string $action, ?string $entityType = null, ?int $entityId = null, ?string $details = null, ?int $userId = null): void
    {
        try {
            DB::insert('activity_logs', [
                'user_id'     => $userId ?? ($_SESSION['uid'] ?? null),
                'action'      => $action,
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'details'     => $details !== null ? mb_substr($details, 0, 500) : null,
                'ip_address'  => client_ip() ?: null,
            ]);
        } catch (\Throwable $e) {
            error_log('Journal impossible : ' . $e->getMessage());
        }
    }
}
