<?php
declare(strict_types=1);

namespace App\Models;

use App\Helpers\DB;

final class Maintenance
{
    public static function forTruck(int $truckId): array
    {
        return DB::all(
            "SELECT m.*, CONCAT(u.first_name,' ',u.last_name) AS author FROM maintenance_records m
             LEFT JOIN users u ON u.id = m.created_by WHERE m.truck_id = ? ORDER BY m.service_date DESC, m.id DESC",
            [$truckId]
        );
    }

    /** Enregistre l'entretien et met à jour la fiche du camion (dernier / prochain entretien, kilométrage). */
    public static function add(array $d): int
    {
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $id = DB::insert('maintenance_records', $d);
            $upd = [];
            $truck = Truck::find((int)$d['truck_id']);
            if ($truck && (!$truck['last_service_date'] || $d['service_date'] >= $truck['last_service_date'])) {
                $upd['last_service_date'] = $d['service_date'];
                $upd['next_service_date'] = $d['next_service_date'];
            }
            if ($truck && !empty($d['mileage']) && (int)$d['mileage'] > (int)$truck['mileage']) {
                $upd['mileage'] = (int)$d['mileage'];
            }
            if ($upd) {
                DB::update('trucks', (int)$d['truck_id'], $upd);
            }
            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private static function where(array $f): array
    {
        $w = [];
        $p = [];
        if (!empty($f['truck_id'])) {
            $w[] = 'm.truck_id = ?';
            $p[] = (int)$f['truck_id'];
        }
        if (!empty($f['from'])) {
            $w[] = 'm.service_date >= ?';
            $p[] = $f['from'];
        }
        if (!empty($f['to'])) {
            $w[] = 'm.service_date <= ?';
            $p[] = $f['to'];
        }
        return [$w ? ' WHERE ' . implode(' AND ', $w) : '', $p];
    }

    public static function count(array $f): int
    {
        [$where, $p] = self::where($f);
        return (int)DB::value('SELECT COUNT(*) FROM maintenance_records m' . $where, $p);
    }

    public static function search(array $f, string $limitSql = ''): array
    {
        [$where, $p] = self::where($f);
        return DB::all(
            'SELECT m.*, t.plate FROM maintenance_records m JOIN trucks t ON t.id = m.truck_id' . $where . ' ORDER BY m.service_date DESC, m.id DESC' . $limitSql,
            $p
        );
    }
}
