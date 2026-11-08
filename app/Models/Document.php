<?php
declare(strict_types=1);

namespace App\Models;

use App\Helpers\DB;

final class Document
{
    public static function find(int $id): ?array
    {
        return DB::one(
            "SELECT d.*, t.plate, CONCAT(u.first_name,' ',u.last_name) AS uploader
             FROM truck_documents d JOIN trucks t ON t.id = d.truck_id LEFT JOIN users u ON u.id = d.uploaded_by WHERE d.id = ?",
            [$id]
        );
    }

    public static function forTruck(int $truckId): array
    {
        return DB::all(
            "SELECT d.*, CONCAT(u.first_name,' ',u.last_name) AS uploader
             FROM truck_documents d LEFT JOIN users u ON u.id = d.uploaded_by
             WHERE d.truck_id = ? ORDER BY d.category, d.created_at DESC",
            [$truckId]
        );
    }

    /**
     * Documents expirés ou arrivant à échéance (<= $days jours).
     * Pour chaque camion + catégorie on ne garde que le document le plus récent en échéance
     * (un contrôle technique renouvelé ne doit pas déclencher l'alerte de l'ancien).
     */
    public static function expiring(int $days = 30): array
    {
        return DB::all(
            "SELECT d.id, d.truck_id, d.name, d.category, d.expiry_date, t.plate,
                    DATEDIFF(d.expiry_date, CURDATE()) AS days_left
             FROM truck_documents d
             JOIN trucks t ON t.id = d.truck_id
             WHERE d.expiry_date IS NOT NULL
               AND d.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
               AND NOT EXISTS (
                   SELECT 1 FROM truck_documents n
                   WHERE n.truck_id = d.truck_id AND n.category = d.category
                     AND n.expiry_date > d.expiry_date AND n.id <> d.id
               )
             ORDER BY d.expiry_date",
            [$days]
        );
    }
}
