<?php
declare(strict_types=1);

namespace App\Models;

use App\Helpers\DB;
use App\Helpers\Phone;
use App\Helpers\Upload;

final class Dispatch
{
    private const BASE = "SELECT d.*, t.plate, t.brand, t.model,
            CONCAT(u.first_name, ' ', u.last_name) AS driver_name, u.first_name AS driver_first, u.phone AS driver_phone,
            CONCAT(h1.first_name, ' ', h1.last_name) AS helper1_name, h1.first_name AS helper1_first, h1.whatsapp AS helper1_phone,
            CONCAT(h2.first_name, ' ', h2.last_name) AS helper2_name, h2.first_name AS helper2_first, h2.whatsapp AS helper2_phone
        FROM dispatches d
        JOIN trucks t ON t.id = d.truck_id
        JOIN users u ON u.id = d.driver_id
        LEFT JOIN helpers h1 ON h1.id = d.helper1_id
        LEFT JOIN helpers h2 ON h2.id = d.helper2_id";

    public static function find(int $id): ?array
    {
        return DB::one(self::BASE . ' WHERE d.id = ?', [$id]);
    }

    private static function where(array $f): array
    {
        $w = [];
        $p = [];
        if (($f['date'] ?? '') !== '') {
            $w[] = 'd.work_date = ?';
            $p[] = $f['date'];
        }
        if (($f['status'] ?? '') !== '' && isset(labels('dispatch_status')[$f['status']])) {
            $w[] = 'd.status = ?';
            $p[] = $f['status'];
        }
        return [$w ? ' WHERE ' . implode(' AND ', $w) : '', $p];
    }

    public static function count(array $f): int
    {
        [$where, $p] = self::where($f);
        return (int)DB::value('SELECT COUNT(*) FROM dispatches d' . $where, $p);
    }

    public static function search(array $f, string $limitSql = ''): array
    {
        [$where, $p] = self::where($f);
        $rows = DB::all(self::BASE . $where . ' ORDER BY d.work_date DESC, d.id DESC' . $limitSql, $p);
        foreach ($rows as &$r) {
            $r['tours'] = self::tours((int)$r['id']);
        }
        return $rows;
    }

    public static function tours(int $id): array
    {
        return DB::all('SELECT * FROM dispatch_tours WHERE dispatch_id = ? ORDER BY position, id', [$id]);
    }

    public static function messages(int $id): array
    {
        return DB::all('SELECT * FROM dispatch_messages WHERE dispatch_id = ? ORDER BY recipient_type = \'helper\', id', [$id]);
    }

    /** Tournées à venir (à partir d'aujourd'hui) d'un conducteur, déjà validées. */
    public static function upcomingForDriver(int $driverId): array
    {
        $rows = DB::all(self::BASE . " WHERE d.driver_id = ? AND d.work_date >= CURDATE() AND d.status <> 'draft'
            ORDER BY d.work_date, d.id LIMIT 8", [$driverId]);
        foreach ($rows as &$r) {
            $r['tours'] = self::tours((int)$r['id']);
        }
        return $rows;
    }

    /** Autres planning du même jour utilisant les mêmes personnes ou le même camion. */
    public static function conflicts(string $date, int $truckId, int $driverId, array $helperIds, ?int $exceptId): array
    {
        $sql = 'SELECT id, truck_id, driver_id, helper1_id, helper2_id FROM dispatches WHERE work_date = ? AND id <> ?';
        $out = [];
        foreach (DB::all($sql, [$date, (int)$exceptId]) as $o) {
            if ((int)$o['truck_id'] === $truckId) {
                $out[] = 'Le camion est déjà prévu dans un autre planning ce jour-là.';
            }
            if ((int)$o['driver_id'] === $driverId) {
                $out[] = 'Le conducteur a déjà un autre planning ce jour-là.';
            }
            if (array_intersect($helperIds, array_filter([(int)$o['helper1_id'], (int)$o['helper2_id']]))) {
                $out[] = 'Un rippeur a déjà un autre planning ce jour-là.';
            }
        }
        return array_values(array_unique($out));
    }

    public static function delete(int $id): void
    {
        foreach (self::tours($id) as $t) {
            Upload::delete($t['file_path']);
        }
        DB::run('DELETE FROM dispatches WHERE id = ?', [$id]);
    }

    public static function sheetUrl(array $tour): string
    {
        return absolute_url('file/sheet', ['t' => $tour['token'], 'dl' => 1]);
    }

    /** Équipe vue par un destinataire (texte court). */
    private static function team(array $d, string $who): string
    {
        $drv = $d['driver_name'];
        $hs = array_values(array_filter([$d['helper1_name'], $d['helper2_name']]));
        if ($who === 'driver') {
            return $hs ? 'rippeur' . (count($hs) > 1 ? 's' : '') . ' ' . implode(' et ', $hs) : 'aucun rippeur';
        }
        $others = array_values(array_filter($hs, static fn($n) => $n !== $who));
        return 'conducteur ' . $drv . ($others ? ', avec ' . implode(' et ', $others) : '');
    }

    /**
     * Messages à envoyer (un par personne) : texte complet + variables du modèle.
     * @return array<int,array{type:string,name:string,phone:?string,body:string,params:array}>
     */
    public static function buildMessages(array $d, array $tours): array
    {
        $date = fdate_long($d['work_date']);
        $truck = $d['plate'] . ' (' . trim($d['brand'] . ' ' . $d['model']) . ')';
        $missions = [];
        $lines = [];
        $links = [];
        foreach ($tours as $i => $t) {
            $label = label('tour_kind', $t['kind']) . ' ' . ($i + 1) . ' : ' . $t['client'] . ' – ' . $t['city'];
            $missions[] = $label;
            $lines[] = '• ' . $label;
            if ($t['file_path']) {
                $links[] = ($i + 1) . ' (' . $t['client'] . ') : ' . self::sheetUrl($t);
            }
        }
        $people = [['driver', $d['driver_name'], $d['driver_first'], Phone::normalize($d['driver_phone'])]];
        foreach ([1, 2] as $n) {
            if ($d["helper{$n}_name"]) {
                $people[] = ['helper', $d["helper{$n}_name"], $d["helper{$n}_first"], Phone::normalize($d["helper{$n}_phone"])];
            }
        }
        $out = [];
        foreach ($people as [$type, $name, $first, $phone]) {
            $team = self::team($d, $type === 'driver' ? 'driver' : $name);
            $body = "Bonjour $first,\nVotre planning du $date :\n\n"
                . 'Camion : ' . $truck . "\n"
                . 'Équipe : ' . $team . "\n\n"
                . "Missions :\n" . implode("\n", $lines);
            if ($links) {
                $body .= "\n\nTélécharger la feuille :\n" . implode("\n", array_map(static fn($l) => '• ' . $l, $links));
            }
            if (!empty($d['note'])) {
                $body .= "\n\nRemarque : " . preg_replace('/\s*\n\s*/', ' ', $d['note']);
            }
            $body .= "\n\nZT Continental Trans";
            $detail = $links ? 'Télécharger ' . implode(' ; ', $links) : 'aucune feuille jointe';
            if (!empty($d['note'])) {
                $detail .= ' — Remarque : ' . preg_replace('/\s+/', ' ', $d['note']);
            }
            $out[] = [
                'type' => $type, 'name' => $name, 'phone' => $phone, 'body' => $body,
                'params' => [$first, $date, $truck, $team, implode(' | ', $missions), $detail],
            ];
        }
        return $out;
    }
}
