<?php
declare(strict_types=1);

namespace App\Models;

use App\Helpers\DB;

final class Incident
{
    private const BASE = "SELECT i.*, t.plate, t.brand, t.model, t.status AS truck_status,
            CONCAT(u.first_name,' ',u.last_name) AS driver_name,
            (SELECT COUNT(*) FROM incident_photos p WHERE p.incident_id = i.id) AS photo_count
        FROM incidents i JOIN trucks t ON t.id = i.truck_id JOIN users u ON u.id = i.driver_id";

    public static function find(int $id): ?array
    {
        return DB::one(self::BASE . ' WHERE i.id = ?', [$id]);
    }

    public static function photos(int $id): array
    {
        return DB::all('SELECT * FROM incident_photos WHERE incident_id = ? ORDER BY id', [$id]);
    }

    public static function comments(int $id): array
    {
        return DB::all(
            "SELECT c.*, CONCAT(u.first_name,' ',u.last_name) AS author
             FROM incident_comments c LEFT JOIN users u ON u.id = c.user_id
             WHERE c.incident_id = ? ORDER BY c.created_at, c.id",
            [$id]
        );
    }

    private static function where(array $f): array
    {
        $w = [];
        $p = [];
        foreach (['truck_id' => 'i.truck_id', 'driver_id' => 'i.driver_id'] as $k => $col) {
            if (!empty($f[$k])) {
                $w[] = $col . ' = ?';
                $p[] = (int)$f[$k];
            }
        }
        if (!empty($f['status'])) {
            if ($f['status'] === 'open') {
                $w[] = "i.status <> 'resolved'";
            } elseif (isset(labels('incident_status')[$f['status']])) {
                $w[] = 'i.status = ?';
                $p[] = $f['status'];
            }
        }
        if (!empty($f['priority']) && isset(labels('priority')[$f['priority']])) {
            $w[] = 'i.priority = ?';
            $p[] = $f['priority'];
        }
        if (!empty($f['from'])) {
            $w[] = 'DATE(i.created_at) >= ?';
            $p[] = $f['from'];
        }
        if (!empty($f['to'])) {
            $w[] = 'DATE(i.created_at) <= ?';
            $p[] = $f['to'];
        }
        if (($f['q'] ?? '') !== '') {
            $w[] = '(t.plate LIKE ? OR i.description LIKE ?)';
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            array_push($p, $like, $like);
        }
        return [$w ? ' WHERE ' . implode(' AND ', $w) : '', $p];
    }

    public static function count(array $f): int
    {
        [$where, $p] = self::where($f);
        return (int)DB::value('SELECT COUNT(*) FROM incidents i JOIN trucks t ON t.id = i.truck_id' . $where, $p);
    }

    public static function search(array $f, string $limitSql = ''): array
    {
        [$where, $p] = self::where($f);
        return DB::all(
            self::BASE . $where . " ORDER BY (i.status = 'resolved'), FIELD(i.priority,'critical','high','medium','low'), i.created_at DESC" . $limitSql,
            $p
        );
    }

    /** Un signalement est « à risque sécurité » si le conducteur l'a coché ou si la priorité est critique. */
    public static function isSafetyRisk(string $priority, bool $checked): bool
    {
        return $checked || $priority === 'critical';
    }
}
