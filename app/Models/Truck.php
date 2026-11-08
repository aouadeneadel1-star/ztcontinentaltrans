<?php
declare(strict_types=1);

namespace App\Models;

use App\Helpers\DB;
use App\Helpers\Upload;

final class Truck
{
    private const BASE = "SELECT t.*, a.driver_id AS driver_id, a.start_date AS assigned_since,
            CONCAT(u.first_name, ' ', u.last_name) AS driver_name
        FROM trucks t
        LEFT JOIN truck_assignments a ON a.truck_id = t.id AND a.end_date IS NULL
        LEFT JOIN users u ON u.id = a.driver_id";

    public static function find(int $id): ?array
    {
        return DB::one(self::BASE . ' WHERE t.id = ?', [$id]);
    }

    /** Filtres : q (immatriculation/marque/modèle), status, driver */
    private static function where(array $f): array
    {
        $w = [];
        $p = [];
        if (($f['q'] ?? '') !== '') {
            $w[] = '(t.plate LIKE ? OR t.brand LIKE ? OR t.model LIKE ?)';
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            array_push($p, $like, $like, $like);
        }
        if (($f['status'] ?? '') !== '' && isset(labels('truck_status')[$f['status']])) {
            $w[] = 't.status = ?';
            $p[] = $f['status'];
        }
        if (($f['ownership'] ?? '') !== '' && isset(labels('ownership')[$f['ownership']])) {
            $w[] = 't.ownership = ?';
            $p[] = $f['ownership'];
        }
        return [$w ? ' WHERE ' . implode(' AND ', $w) : '', $p];
    }

    public static function count(array $f): int
    {
        [$where, $p] = self::where($f);
        return (int)DB::value('SELECT COUNT(*) FROM trucks t' . $where, $p);
    }

    public static function search(array $f, string $limitSql = ''): array
    {
        [$where, $p] = self::where($f);
        return DB::all(self::BASE . $where . ' ORDER BY t.plate' . $limitSql, $p);
    }

    /** Liste simple pour listes déroulantes. */
    public static function options(): array
    {
        return DB::all('SELECT id, plate, brand, model FROM trucks ORDER BY plate');
    }

    /** Camion actuellement affecté à un conducteur (ou null). */
    public static function forDriver(int $driverId): ?array
    {
        return DB::one(self::BASE . ' WHERE a.driver_id = ? LIMIT 1', [$driverId]);
    }

    public static function platesTaken(string $plate, ?int $exceptId = null): bool
    {
        return (bool)DB::value('SELECT 1 FROM trucks WHERE plate = ? AND id <> ?', [$plate, $exceptId ?? 0]);
    }

    public static function create(array $d): int
    {
        return DB::insert('trucks', $d);
    }

    public static function update(int $id, array $d): void
    {
        DB::update('trucks', $id, $d);
    }

    public static function setStatus(int $id, string $status): void
    {
        DB::update('trucks', $id, ['status' => $status]);
    }

    /** Suppression complète : fichiers (photos, incidents, documents) puis ligne (cascade SQL). */
    public static function delete(int $id): void
    {
        $paths = array_merge(
            array_column(DB::all('SELECT file_path FROM truck_photos WHERE truck_id = ?', [$id]), 'file_path'),
            array_column(DB::all('SELECT ip.file_path FROM incident_photos ip JOIN incidents i ON i.id = ip.incident_id WHERE i.truck_id = ?', [$id]), 'file_path'),
            array_column(DB::all('SELECT file_path FROM truck_documents WHERE truck_id = ?', [$id]), 'file_path')
        );
        $paths = array_merge($paths, array_column(DB::all('SELECT file_path FROM dispatch_tours WHERE file_path IS NOT NULL AND dispatch_id IN (SELECT id FROM dispatches WHERE truck_id = ?)', [$id]), 'file_path'));
        $t = DB::one('SELECT photo_path FROM trucks WHERE id = ?', [$id]);
        if ($t && $t['photo_path']) {
            $paths[] = $t['photo_path'];
        }
        DB::run('DELETE FROM trucks WHERE id = ?', [$id]);
        foreach ($paths as $p) {
            Upload::delete($p);
        }
    }

    /** Dernier document par catégorie obligatoire (carte grise, assurance, contrôle technique). */
    public static function requiredDocs(int $truckId): array
    {
        $out = [];
        foreach ($GLOBALS['zt_labels']['doc_required'] as $cat) {
            $out[$cat] = DB::one(
                'SELECT id, name, expiry_date FROM truck_documents WHERE truck_id = ? AND category = ?
                 ORDER BY (expiry_date IS NULL), expiry_date DESC, id DESC LIMIT 1',
                [$truckId, $cat]
            );
        }
        return $out;
    }

    /** Incidents ouverts (non résolus) pour un camion. */
    public static function openIncidentCount(int $truckId): int
    {
        return (int)DB::value("SELECT COUNT(*) FROM incidents WHERE truck_id = ? AND status <> 'resolved'", [$truckId]);
    }

    /** Camions en location dont le contrat se termine dans $days jours (ou est déjà terminé). */
    public static function rentalsEnding(int $days): array
    {
        return DB::all(
            "SELECT id, plate, rental_company, rental_contract_no, rental_end_date, DATEDIFF(rental_end_date, CURDATE()) AS days_left
             FROM trucks WHERE ownership = 'rented' AND rental_end_date IS NOT NULL
               AND rental_end_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY) ORDER BY rental_end_date",
            [$days]
        );
    }

    public static function renewals(int $truckId): array
    {
        return DB::all(
            "SELECT r.*, CONCAT(u.first_name,' ',u.last_name) AS by_name FROM rental_renewals r
             LEFT JOIN users u ON u.id = r.renewed_by WHERE r.truck_id = ? ORDER BY r.created_at DESC, r.id DESC",
            [$truckId]
        );
    }

    /** Un risque sécurité non résolu interdit l'usage du véhicule. */
    public static function hasSafetyRisk(int $truckId): bool
    {
        return (bool)DB::value("SELECT 1 FROM incidents WHERE truck_id = ? AND safety_risk = 1 AND status <> 'resolved' LIMIT 1", [$truckId]);
    }
}
