<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\DB;
use App\Models\Check;
use App\Models\Document;
use App\Models\Incident;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $status = array_column(DB::all('SELECT status, COUNT(*) AS n FROM trucks GROUP BY status'), 'n', 'status');
        $stats = [
            'total'       => array_sum($status),
            'available'   => (int)($status['available'] ?? 0),
            'in_service'  => (int)($status['in_service'] ?? 0),
            'maintenance' => (int)($status['maintenance'] ?? 0),
            'immobilized' => (int)($status['immobilized'] ?? 0),
            'drivers'     => (int)DB::value("SELECT COUNT(*) FROM users WHERE role='driver' AND is_active=1"),
            'expected'    => (int)DB::value('SELECT COUNT(*) FROM truck_assignments a JOIN users u ON u.id=a.driver_id AND u.is_active=1 WHERE a.end_date IS NULL'),
            'morning'     => (int)DB::value("SELECT COUNT(*) FROM daily_checks WHERE check_date = CURDATE() AND check_type='morning'"),
            'evening'     => (int)DB::value("SELECT COUNT(*) FROM daily_checks WHERE check_date = CURDATE() AND check_type='evening'"),
            'new_inc'     => (int)DB::value("SELECT COUNT(*) FROM incidents WHERE status='new'"),
            'open_inc'    => (int)DB::value("SELECT COUNT(*) FROM incidents WHERE status<>'resolved'"),
        ];

        $recentIncidents = Incident::search([], ' LIMIT 6');
        // Pour le tableau de bord on veut les plus récents, pas triés par priorité
        $recentIncidents = DB::all(
            "SELECT i.id, i.category, i.priority, i.status, i.safety_risk, i.created_at, t.plate, CONCAT(u.first_name,' ',u.last_name) AS driver_name
             FROM incidents i JOIN trucks t ON t.id=i.truck_id JOIN users u ON u.id=i.driver_id ORDER BY i.created_at DESC, i.id DESC LIMIT 6"
        );
        $recentChecks = Check::search([], ' LIMIT 6');

        // Véhicules nécessitant une intervention : statut, incident important ouvert ou entretien dû
        $serviceDays = (int)config('service_alert_days', 15);
        $needs = DB::all(
            "SELECT t.id, t.plate, t.brand, t.model, t.status, t.next_service_date,
                    (SELECT COUNT(*) FROM incidents i WHERE i.truck_id=t.id AND i.status<>'resolved' AND i.priority IN ('high','critical')) AS urgent_incidents,
                    (SELECT COUNT(*) FROM incidents i WHERE i.truck_id=t.id AND i.status<>'resolved' AND i.safety_risk=1) AS safety_incidents
             FROM trucks t
             WHERE t.status IN ('maintenance','immobilized')
                OR (t.next_service_date IS NOT NULL AND t.next_service_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY))
                OR EXISTS (SELECT 1 FROM incidents i WHERE i.truck_id=t.id AND i.status<>'resolved' AND (i.priority IN ('high','critical') OR i.safety_risk=1))
             ORDER BY t.plate",
            [$serviceDays]
        );

        $now = date('H:i');
        $missing = [
            'morning' => $now >= config('morning_check_deadline', '10:00') ? Check::missingToday('morning') : [],
            'evening' => $now >= config('evening_check_deadline', '19:00') ? Check::missingToday('evening') : [],
        ];

        $this->view('dashboard/index', [
            'title'    => 'Tableau de bord',
            'stats'    => $stats,
            'incidents' => $recentIncidents,
            'checks'   => $recentChecks,
            'needs'    => $needs,
            'expiring' => Document::expiring(30),
            'rentals'  => \App\Models\Truck::rentalsEnding(30),
            'missing'  => $missing,
            'serviceDays' => $serviceDays,
        ]);
    }
}
