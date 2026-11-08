<?php
declare(strict_types=1);

namespace App\Models;

use App\Helpers\DB;

final class Assignment
{
    /**
     * Affecte un camion à un conducteur.
     * Termine l'affectation en cours du camion ET celle du conducteur (1 camion ↔ 1 conducteur).
     */
    public static function assign(int $truckId, int $driverId, ?int $byId): void
    {
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $now = date('Y-m-d H:i:s');
            DB::run('UPDATE truck_assignments SET end_date = ? WHERE end_date IS NULL AND (truck_id = ? OR driver_id = ?)', [$now, $truckId, $driverId]);
            DB::insert('truck_assignments', ['truck_id' => $truckId, 'driver_id' => $driverId, 'assigned_by' => $byId, 'start_date' => $now]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function unassignTruck(int $truckId): void
    {
        DB::run('UPDATE truck_assignments SET end_date = ? WHERE end_date IS NULL AND truck_id = ?', [date('Y-m-d H:i:s'), $truckId]);
    }

    public static function unassignDriver(int $driverId): void
    {
        DB::run('UPDATE truck_assignments SET end_date = ? WHERE end_date IS NULL AND driver_id = ?', [date('Y-m-d H:i:s'), $driverId]);
    }

    /** Historique des affectations d'un camion. */
    public static function historyForTruck(int $truckId): array
    {
        return DB::all(
            "SELECT a.*, CONCAT(d.first_name,' ',d.last_name) AS driver_name, CONCAT(b.first_name,' ',b.last_name) AS by_name
             FROM truck_assignments a
             JOIN users d ON d.id = a.driver_id
             LEFT JOIN users b ON b.id = a.assigned_by
             WHERE a.truck_id = ? ORDER BY a.start_date DESC, a.id DESC",
            [$truckId]
        );
    }

    public static function historyAll(array $f, string $limitSql = ''): array
    {
        [$where, $p] = self::where($f);
        return DB::all(
            "SELECT a.*, t.plate, CONCAT(d.first_name,' ',d.last_name) AS driver_name, CONCAT(b.first_name,' ',b.last_name) AS by_name
             FROM truck_assignments a
             JOIN trucks t ON t.id = a.truck_id
             JOIN users d ON d.id = a.driver_id
             LEFT JOIN users b ON b.id = a.assigned_by" . $where . ' ORDER BY a.start_date DESC, a.id DESC' . $limitSql,
            $p
        );
    }

    public static function countAll(array $f): int
    {
        [$where, $p] = self::where($f);
        return (int)DB::value('SELECT COUNT(*) FROM truck_assignments a' . $where, $p);
    }

    private static function where(array $f): array
    {
        $w = [];
        $p = [];
        if (!empty($f['truck_id'])) {
            $w[] = 'a.truck_id = ?';
            $p[] = (int)$f['truck_id'];
        }
        if (!empty($f['driver_id'])) {
            $w[] = 'a.driver_id = ?';
            $p[] = (int)$f['driver_id'];
        }
        if (!empty($f['from'])) {
            $w[] = 'DATE(a.start_date) >= ?';
            $p[] = $f['from'];
        }
        if (!empty($f['to'])) {
            $w[] = 'DATE(a.start_date) <= ?';
            $p[] = $f['to'];
        }
        return [$w ? ' WHERE ' . implode(' AND ', $w) : '', $p];
    }
}
