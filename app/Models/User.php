<?php
declare(strict_types=1);

namespace App\Models;

use App\Helpers\DB;

final class User
{
    public static function find(int $id): ?array
    {
        return DB::one('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public static function findByEmail(string $email): ?array
    {
        return DB::one('SELECT * FROM users WHERE email = ?', [mb_strtolower($email)]);
    }

    public static function emailTaken(string $email, ?int $exceptId = null): bool
    {
        return (bool)DB::value('SELECT 1 FROM users WHERE email = ? AND id <> ?', [mb_strtolower($email), $exceptId ?? 0]);
    }

    private static function where(array $f): array
    {
        $w = [];
        $p = [];
        if (($f['q'] ?? '') !== '') {
            $w[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR CONCAT(u.first_name,' ',u.last_name) LIKE ?)";
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            array_push($p, $like, $like, $like, $like);
        }
        if (($f['role'] ?? '') !== '' && isset(labels('roles')[$f['role']])) {
            $w[] = 'u.role = ?';
            $p[] = $f['role'];
        }
        return [$w ? ' WHERE ' . implode(' AND ', $w) : '', $p];
    }

    public static function count(array $f): int
    {
        [$where, $p] = self::where($f);
        return (int)DB::value('SELECT COUNT(*) FROM users u' . $where, $p);
    }

    public static function search(array $f, string $limitSql = ''): array
    {
        [$where, $p] = self::where($f);
        return DB::all(
            "SELECT u.*, t.plate AS truck_plate, t.id AS truck_id
             FROM users u
             LEFT JOIN truck_assignments a ON a.driver_id = u.id AND a.end_date IS NULL
             LEFT JOIN trucks t ON t.id = a.truck_id" . $where . ' ORDER BY u.role, u.last_name, u.first_name' . $limitSql,
            $p
        );
    }

    /** Conducteurs actifs (listes déroulantes). */
    public static function drivers(bool $onlyActive = true): array
    {
        return DB::all(
            "SELECT u.id, u.first_name, u.last_name, t.plate AS truck_plate, t.id AS truck_id
             FROM users u
             LEFT JOIN truck_assignments a ON a.driver_id = u.id AND a.end_date IS NULL
             LEFT JOIN trucks t ON t.id = a.truck_id
             WHERE u.role = 'driver'" . ($onlyActive ? ' AND u.is_active = 1' : '') . ' ORDER BY u.last_name, u.first_name'
        );
    }

    public static function create(array $d): int
    {
        return DB::insert('users', $d);
    }

    public static function update(int $id, array $d): void
    {
        DB::update('users', $id, $d);
    }

    public static function countActiveAdmins(?int $exceptId = null): int
    {
        return (int)DB::value("SELECT COUNT(*) FROM users WHERE role='admin' AND is_active=1 AND id <> ?", [$exceptId ?? 0]);
    }
}
