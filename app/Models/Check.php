<?php
declare(strict_types=1);

namespace App\Models;

use App\Helpers\DB;

final class Check
{
    public static function find(int $id): ?array
    {
        return DB::one(
            "SELECT c.*, t.plate, CONCAT(u.first_name,' ',u.last_name) AS driver_name
             FROM daily_checks c JOIN trucks t ON t.id = c.truck_id JOIN users u ON u.id = c.driver_id WHERE c.id = ?",
            [$id]
        );
    }

    public static function photos(int $checkId): array
    {
        return DB::all('SELECT * FROM truck_photos WHERE check_id = ? ORDER BY id', [$checkId]);
    }

    public static function forDriverToday(int $driverId, string $type): ?array
    {
        return DB::one('SELECT * FROM daily_checks WHERE driver_id = ? AND check_type = ? AND check_date = ?', [$driverId, $type, date('Y-m-d')]);
    }

    public static function recentForDriver(int $driverId, int $limit = 6): array
    {
        return DB::all(
            'SELECT c.*, t.plate, (SELECT COUNT(*) FROM truck_photos p WHERE p.check_id = c.id) AS photo_count
             FROM daily_checks c JOIN trucks t ON t.id = c.truck_id
             WHERE c.driver_id = ? ORDER BY c.checked_at DESC LIMIT ' . (int)$limit,
            [$driverId]
        );
    }

    private static function where(array $f): array
    {
        $w = [];
        $p = [];
        foreach (['truck_id' => 'c.truck_id', 'driver_id' => 'c.driver_id'] as $k => $col) {
            if (!empty($f[$k])) {
                $w[] = $col . ' = ?';
                $p[] = (int)$f[$k];
            }
        }
        if (!empty($f['type']) && isset(labels('check_type')[$f['type']])) {
            $w[] = 'c.check_type = ?';
            $p[] = $f['type'];
        }
        if (!empty($f['from'])) {
            $w[] = 'c.check_date >= ?';
            $p[] = $f['from'];
        }
        if (!empty($f['to'])) {
            $w[] = 'c.check_date <= ?';
            $p[] = $f['to'];
        }
        if (!empty($f['anomaly'])) {
            $w[] = 'c.has_anomaly = 1';
        }
        if (($f['q'] ?? '') !== '') {
            $w[] = 't.plate LIKE ?';
            $p[] = '%' . addcslashes($f['q'], '%_\\') . '%';
        }
        return [$w ? ' WHERE ' . implode(' AND ', $w) : '', $p];
    }

    public static function count(array $f): int
    {
        [$where, $p] = self::where($f);
        return (int)DB::value('SELECT COUNT(*) FROM daily_checks c JOIN trucks t ON t.id = c.truck_id' . $where, $p);
    }

    public static function search(array $f, string $limitSql = ''): array
    {
        [$where, $p] = self::where($f);
        return DB::all(
            "SELECT c.*, t.plate, CONCAT(u.first_name,' ',u.last_name) AS driver_name,
                    (SELECT COUNT(*) FROM truck_photos ph WHERE ph.check_id = c.id) AS photo_count,
                    (SELECT ph2.id FROM truck_photos ph2 WHERE ph2.check_id = c.id ORDER BY ph2.id LIMIT 1) AS first_photo_id
             FROM daily_checks c
             JOIN trucks t ON t.id = c.truck_id
             JOIN users u ON u.id = c.driver_id" . $where . ' ORDER BY c.checked_at DESC, c.id DESC' . $limitSql,
            $p
        );
    }

    /** Contrôles d'une journée pour un camion (comparaison matin / soir). */
    public static function forTruckDay(int $truckId, string $date): array
    {
        return DB::all(
            "SELECT c.*, CONCAT(u.first_name,' ',u.last_name) AS driver_name
             FROM daily_checks c JOIN users u ON u.id = c.driver_id
             WHERE c.truck_id = ? AND c.check_date = ? ORDER BY c.checked_at",
            [$truckId, $date]
        );
    }

    /** Conducteurs affectés qui n'ont pas fait un contrôle donné aujourd'hui. */
    public static function missingToday(string $type): array
    {
        return DB::all(
            "SELECT u.id AS driver_id, CONCAT(u.first_name,' ',u.last_name) AS driver_name, t.id AS truck_id, t.plate
             FROM truck_assignments a
             JOIN users u ON u.id = a.driver_id AND u.is_active = 1
             JOIN trucks t ON t.id = a.truck_id
             WHERE a.end_date IS NULL
               AND NOT EXISTS (SELECT 1 FROM daily_checks c WHERE c.driver_id = u.id AND c.check_type = ? AND c.check_date = CURDATE())
             ORDER BY u.last_name",
            [$type]
        );
    }
}
