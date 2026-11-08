<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\DB;
use App\Helpers\Log;
use App\Helpers\Pager;
use App\Helpers\SimplePdf;
use App\Models\Assignment;
use App\Models\Check;
use App\Models\Incident;
use App\Models\Maintenance;
use App\Models\Truck;
use App\Models\User;

/** Historiques filtrables + export CSV / PDF (personnel). */
final class HistoryController extends Controller
{
    private const TYPES = [
        'checks'      => 'Contrôles quotidiens',
        'incidents'   => 'Incidents',
        'maintenance' => 'Entretiens',
        'documents'   => 'Documents',
        'assignments' => 'Affectations',
    ];

    private function type(): string
    {
        $t = (string)($_GET['type'] ?? 'checks');
        return isset(self::TYPES[$t]) ? $t : 'checks';
    }

    private function filterKeys(string $type): array
    {
        return match ($type) {
            'checks'      => ['truck_id', 'driver_id', 'from', 'to', 'type_check'],
            'incidents'   => ['truck_id', 'driver_id', 'from', 'to', 'status'],
            'maintenance' => ['truck_id', 'from', 'to'],
            'documents'   => ['truck_id', 'from', 'to'],
            'assignments' => ['truck_id', 'driver_id', 'from', 'to'],
        };
    }

    public function index(): void
    {
        $type = $this->type();
        $f = $this->filters($this->filterKeys($type));
        $total = $this->total($type, $f);
        $pager = new Pager($total, 25);
        $ds = $this->dataset($type, $f, $pager->limitSql());
        $this->view('history/index', [
            'title'  => 'Historique',
            'type'   => $type,
            'types'  => self::TYPES,
            'f'      => $f,
            'ds'     => $ds,
            'pager'  => $pager,
            'trucks' => Truck::options(),
            'drivers' => User::drivers(false),
        ]);
    }

    public function export(): void
    {
        $type = $this->type();
        $f = $this->filters($this->filterKeys($type));
        $format = ($_GET['format'] ?? 'csv') === 'pdf' ? 'pdf' : 'csv';
        $ds = $this->dataset($type, $f, ' LIMIT 5000');
        Log::add('history.export', null, null, $type . ' ' . $format . ' (' . count($ds['rows']) . ' lignes)');
        $name = 'zt-' . $type . '-' . date('Ymd-His');

        if ($format === 'pdf') {
            $pdf = new SimplePdf('Historique – ' . self::TYPES[$type], $this->describeFilters($f) . ' · ' . count($ds['rows']) . ' ligne(s)');
            $pdf->table($ds['headers'], $ds['widths'], array_map(static fn($r) => $r['cells'], $ds['rows']));
            $pdf->output($name . '.pdf');
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '.csv"');
        header('X-Content-Type-Options: nosniff');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 pour Excel
        fputcsv($out, $ds['headers'], ';');
        foreach ($ds['rows'] as $r) {
            // Neutralise l'injection de formules (=, +, -, @) à l'ouverture dans Excel
            fputcsv($out, array_map(static fn($c) => preg_match('/^[=+\-@\t\r]/', (string)$c) ? "'" . $c : (string)$c, $r['cells']), ';');
        }
        fclose($out);
        exit;
    }

    private function describeFilters(array $f): string
    {
        $parts = [];
        if (!empty($f['truck_id']) && ($t = Truck::find((int)$f['truck_id']))) {
            $parts[] = 'Camion ' . $t['plate'];
        }
        if (!empty($f['driver_id']) && ($u = User::find((int)$f['driver_id']))) {
            $parts[] = 'Conducteur ' . $u['first_name'] . ' ' . $u['last_name'];
        }
        if (!empty($f['type_check'])) {
            $parts[] = 'Contrôle ' . mb_strtolower(label('check_type', $f['type_check']));
        }
        if (!empty($f['status'])) {
            $parts[] = 'Statut ' . mb_strtolower($f['status'] === 'open' ? 'ouverts' : label('incident_status', $f['status']));
        }
        if (!empty($f['from'])) {
            $parts[] = 'du ' . fdate($f['from']);
        }
        if (!empty($f['to'])) {
            $parts[] = 'au ' . fdate($f['to']);
        }
        return $parts ? implode(' · ', $parts) : 'Aucun filtre';
    }

    private function total(string $type, array $f): int
    {
        return match ($type) {
            'checks'      => Check::count($this->checkFilters($f)),
            'incidents'   => Incident::count($f),
            'maintenance' => Maintenance::count($f),
            'assignments' => Assignment::countAll($f),
            'documents'   => (int)DB::value('SELECT COUNT(*) FROM truck_documents d' . $this->docWhere($f)[0], $this->docWhere($f)[1]),
        };
    }

    private function checkFilters(array $f): array
    {
        $f['type'] = $f['type_check'] ?? '';
        return $f;
    }

    private function docWhere(array $f): array
    {
        $w = [];
        $p = [];
        if (!empty($f['truck_id'])) {
            $w[] = 'd.truck_id = ?';
            $p[] = (int)$f['truck_id'];
        }
        if (!empty($f['from'])) {
            $w[] = 'DATE(COALESCE(d.replaced_at, d.created_at)) >= ?';
            $p[] = $f['from'];
        }
        if (!empty($f['to'])) {
            $w[] = 'DATE(COALESCE(d.replaced_at, d.created_at)) <= ?';
            $p[] = $f['to'];
        }
        return [$w ? ' WHERE ' . implode(' AND ', $w) : '', $p];
    }

    /**
     * Jeu de données commun à l'affichage et aux exports.
     * @return array{headers:array,widths:array,rows:array<int,array{cells:array,link:?string}>}
     */
    private function dataset(string $type, array $f, string $limit): array
    {
        $rows = [];
        switch ($type) {
            case 'checks':
                foreach (Check::search($this->checkFilters($f), $limit) as $c) {
                    $rows[] = ['link' => url('check/show', ['id' => $c['id']]), 'cells' => [
                        fdatetime($c['checked_at']), $c['plate'], $c['driver_name'], label('check_type', $c['check_type']),
                        $c['mileage'] !== null ? fkm((int)$c['mileage']) : '—', (string)$c['photo_count'],
                        $c['has_anomaly'] ? 'Anomalie' : 'RAS', (string)($c['comment'] ?? ''),
                    ]];
                }
                return ['headers' => ['Date et heure', 'Camion', 'Conducteur', 'Contrôle', 'Km', 'Photos', 'Résultat', 'Commentaire'], 'widths' => [1.5, 1, 1.4, 0.8, 0.9, 0.6, 0.8, 3], 'rows' => $rows];

            case 'incidents':
                foreach (Incident::search($f, $limit) as $i) {
                    $rows[] = ['link' => url('incident/show', ['id' => $i['id']]), 'cells' => [
                        '#' . $i['id'], fdatetime($i['created_at']), $i['plate'], $i['driver_name'], label('incident_category', $i['category']),
                        label('priority', $i['priority']), label('incident_status', $i['status']), $i['safety_risk'] ? 'Oui' : 'Non', $i['description'],
                    ]];
                }
                return ['headers' => ['N°', 'Date', 'Camion', 'Conducteur', 'Type', 'Priorité', 'Statut', 'Sécurité', 'Description'], 'widths' => [0.5, 1.4, 1, 1.3, 1.2, 0.8, 1.3, 0.7, 3], 'rows' => $rows];

            case 'maintenance':
                foreach (Maintenance::search($f, $limit) as $m) {
                    $rows[] = ['link' => url('truck/show', ['id' => $m['truck_id'], 'tab' => 'maintenance']), 'cells' => [
                        fdate($m['service_date']), $m['plate'], $m['service_type'], $m['garage'] ?? '—',
                        $m['mileage'] !== null ? fkm((int)$m['mileage']) : '—',
                        $m['cost'] !== null ? number_format((float)$m['cost'], 2, ',', ' ') . ' €' : '—',
                        fdate($m['next_service_date']), (string)($m['description'] ?? ''),
                    ]];
                }
                return ['headers' => ['Date', 'Camion', 'Type', 'Garage', 'Km', 'Coût', 'Prochain', 'Détails'], 'widths' => [0.9, 1, 1.5, 1.4, 0.9, 0.9, 0.9, 3], 'rows' => $rows];

            case 'assignments':
                foreach (Assignment::historyAll($f, $limit) as $a) {
                    $rows[] = ['link' => url('truck/show', ['id' => $a['truck_id']]), 'cells' => [
                        $a['plate'], $a['driver_name'], fdatetime($a['start_date']), $a['end_date'] ? fdatetime($a['end_date']) : 'En cours', (string)($a['by_name'] ?? '—'),
                    ]];
                }
                return ['headers' => ['Camion', 'Conducteur', 'Début', 'Fin', 'Affecté par'], 'widths' => [1, 1.6, 1.5, 1.5, 1.6], 'rows' => $rows];

            case 'documents':
                [$where, $p] = $this->docWhere($f);
                $list = DB::all(
                    "SELECT d.*, t.plate, CONCAT(u.first_name,' ',u.last_name) AS uploader
                     FROM truck_documents d JOIN trucks t ON t.id = d.truck_id LEFT JOIN users u ON u.id = d.uploaded_by" . $where .
                    ' ORDER BY COALESCE(d.replaced_at, d.created_at) DESC' . $limit,
                    $p
                );
                foreach ($list as $d) {
                    $rows[] = ['link' => url('truck/show', ['id' => $d['truck_id'], 'tab' => 'documents']), 'cells' => [
                        fdatetime($d['replaced_at'] ?: $d['created_at']), $d['replaced_at'] ? 'Renouvelé' : 'Ajouté', $d['plate'],
                        label('doc_category', $d['category']), $d['name'], fdate($d['expiry_date']), (string)($d['uploader'] ?? '—'),
                    ]];
                }
                return ['headers' => ['Date', 'Action', 'Camion', 'Catégorie', 'Nom', 'Expiration', 'Par'], 'widths' => [1.5, 0.9, 1, 1.5, 2, 1, 1.4], 'rows' => $rows];
        }
        return ['headers' => [], 'widths' => [], 'rows' => []];
    }
}
