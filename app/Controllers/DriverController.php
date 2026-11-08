<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\DB;
use App\Middleware\Auth;
use App\Models\Check;
use App\Models\Document;
use App\Models\Truck;

final class DriverController extends Controller
{
    public function home(): void
    {
        $user = Auth::user();
        $truck = Truck::forDriver((int)$user['id']);
        $data = [
            'title'    => 'Accueil',
            'truck'    => $truck,
            'morning'  => Check::forDriverToday((int)$user['id'], 'morning'),
            'evening'  => Check::forDriverToday((int)$user['id'], 'evening'),
            'checks'   => Check::recentForDriver((int)$user['id'], 4),
            'incidents' => DB::all(
                "SELECT i.*, t.plate FROM incidents i JOIN trucks t ON t.id = i.truck_id
                 WHERE i.driver_id = ? AND i.status <> 'resolved' ORDER BY i.created_at DESC LIMIT 5",
                [$user['id']]
            ),
            'safety'   => $truck ? Truck::hasSafetyRisk((int)$truck['id']) : false,
            'expiring' => [],
            'dispatches' => \App\Models\Dispatch::upcomingForDriver((int)$user['id']),
        ];
        if ($truck) {
            $data['expiring'] = array_values(array_filter(
                Document::expiring(30),
                static fn($d) => (int)$d['truck_id'] === (int)$truck['id']
            ));
        }
        $this->view('driver/home', $data);
    }

    /** « Mon camion » : informations et documents du camion affecté (jamais un autre). */
    public function truck(): void
    {
        $truck = Truck::forDriver((int)Auth::id());
        if (!$truck) {
            flash('info', 'Aucun camion ne vous est affecté pour le moment.');
            redirect('driver/home');
        }
        $this->view('driver/truck', [
            'title'     => 'Mon camion',
            'truck'     => $truck,
            'documents' => Document::forTruck((int)$truck['id']),
            'safety'    => Truck::hasSafetyRisk((int)$truck['id']),
        ]);
    }
}
