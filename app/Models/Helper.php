<?php
declare(strict_types=1);

namespace App\Models;

use App\Helpers\DB;

/** Rippeur : uniquement nom, prénom et numéro WhatsApp. */
final class Helper
{
    public static function find(int $id): ?array
    {
        return DB::one('SELECT * FROM helpers WHERE id = ?', [$id]);
    }

    public static function all(bool $onlyActive = false): array
    {
        return DB::all('SELECT * FROM helpers' . ($onlyActive ? ' WHERE is_active = 1' : '') . ' ORDER BY last_name, first_name');
    }

    public static function create(array $d): int
    {
        return DB::insert('helpers', $d);
    }

    public static function update(int $id, array $d): void
    {
        DB::update('helpers', $id, $d);
    }

    public static function usage(int $id): int
    {
        return (int)DB::value('SELECT COUNT(*) FROM dispatches WHERE helper1_id = ? OR helper2_id = ?', [$id, $id]);
    }

    public static function delete(int $id): void
    {
        DB::run('DELETE FROM helpers WHERE id = ?', [$id]);
    }
}
