<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\DB;
use App\Helpers\Log;
use App\Helpers\Pager;
use App\Helpers\Upload;
use App\Middleware\Auth;
use App\Models\Assignment;
use App\Models\Document;
use App\Models\Maintenance;
use App\Models\Truck;
use App\Models\User;

final class TruckController extends Controller
{
    public function index(): void
    {
        $f = $this->filters(['q', 'status', 'ownership']);
        $pager = new Pager(Truck::count($f));
        $this->view('trucks/index', [
            'title'  => 'Camions',
            'trucks' => Truck::search($f, $pager->limitSql()),
            'f'      => $f,
            'pager'  => $pager,
        ]);
    }

    public function show(): void
    {
        $truck = $this->truckOr404($this->getInt('id'));
        $id = (int)$truck['id'];
        $this->view('trucks/show', [
            'title'       => $truck['plate'],
            'truck'       => $truck,
            'documents'   => Document::forTruck($id),
            'required'    => Truck::requiredDocs($id),
            'maintenance' => Maintenance::forTruck($id),
            'assignments' => Assignment::historyForTruck($id),
            'incidents'   => DB::all("SELECT i.id, i.category, i.priority, i.status, i.created_at, i.safety_risk FROM incidents i WHERE i.truck_id = ? ORDER BY i.created_at DESC LIMIT 8", [$id]),
            'checks'      => DB::all("SELECT c.id, c.check_type, c.check_date, c.checked_at, c.has_anomaly, CONCAT(u.first_name,' ',u.last_name) AS driver_name FROM daily_checks c JOIN users u ON u.id=c.driver_id WHERE c.truck_id = ? ORDER BY c.checked_at DESC LIMIT 8", [$id]),
            'drivers'     => User::drivers(),
            'safety'      => Truck::hasSafetyRisk($id),
            'renewals'    => Truck::renewals($id),
        ]);
    }

    public function form(): void
    {
        $id = $this->getInt('id');
        $truck = $id ? $this->truckOr404($id) : null;
        $this->view('trucks/form', ['title' => $truck ? 'Modifier ' . $truck['plate'] : 'Nouveau camion', 'truck' => $truck]);
    }

    public function save(): void
    {
        $id = $this->postInt('id');
        $existing = $id ? $this->truckOr404($id) : null;

        $plate = strtoupper($this->post('plate', 20));
        $data = [
            'plate'               => $plate,
            'brand'               => $this->post('brand', 80),
            'model'               => $this->post('model', 80),
            'year'                => int_or_null($_POST['year'] ?? null),
            'vehicle_type'        => $this->post('vehicle_type', 60) ?: null,
            'volume_m3'           => ($_POST['volume_m3'] ?? '') !== '' ? (float)str_replace(',', '.', (string)$_POST['volume_m3']) : null,
            'mileage'             => int_or_null($_POST['mileage'] ?? null) ?? 0,
            'last_service_date'   => valid_date($_POST['last_service_date'] ?? null),
            'next_service_date'   => valid_date($_POST['next_service_date'] ?? null),
            'insurance_info'      => $this->post('insurance_info', 190) ?: null,
            'registration_number' => $this->post('registration_number', 60) ?: null,
            'status'              => $this->post('status', 20),
            'notes'               => $this->postText('notes', 2000) ?: null,
            'ownership'           => $this->post('ownership', 10),
        ];
        $rented = $data['ownership'] === 'rented';
        $data['rental_company']      = $rented ? ($this->post('rental_company', 150) ?: null) : null;
        $data['rental_contract_no']  = $rented ? ($this->post('rental_contract_no', 80) ?: null) : null;
        $data['rental_start_date']   = $rented ? valid_date($_POST['rental_start_date'] ?? null) : null;
        $data['rental_end_date']     = $rented ? valid_date($_POST['rental_end_date'] ?? null) : null;
        $data['rental_monthly_cost'] = $rented && ($_POST['rental_monthly_cost'] ?? '') !== '' ? round((float)str_replace(',', '.', (string)$_POST['rental_monthly_cost']), 2) : null;

        $errors = [];
        if ($plate === '' || !preg_match('/^[A-Z0-9][A-Z0-9 \-]{3,18}$/', $plate)) {
            $errors[] = 'Immatriculation invalide (ex. AB-123-CD).';
        } elseif (Truck::platesTaken($plate, $id)) {
            $errors[] = 'Cette immatriculation existe déjà.';
        }
        if ($data['brand'] === '' || $data['model'] === '') {
            $errors[] = 'La marque et le modèle sont obligatoires.';
        }
        if ($data['year'] !== null && ($data['year'] < 1980 || $data['year'] > (int)date('Y') + 1)) {
            $errors[] = 'Année de mise en circulation invalide.';
        }
        if ($data['volume_m3'] !== null && ($data['volume_m3'] <= 0 || $data['volume_m3'] > 200)) {
            $errors[] = 'Volume invalide.';
        }
        if (!isset(labels('truck_status')[$data['status']])) {
            $errors[] = 'Statut invalide.';
        }
        if ($data['next_service_date'] && $data['last_service_date'] && $data['next_service_date'] < $data['last_service_date']) {
            $errors[] = 'La prochaine date d\'entretien doit être postérieure au dernier entretien.';
        }

        if (!isset(labels('ownership')[$data['ownership']])) {
            $errors[] = 'Mode de détention invalide.';
        } elseif ($rented) {
            if (!$data['rental_company']) {
                $errors[] = 'Indiquez le loueur (société de location).';
            }
            if (!$data['rental_end_date']) {
                $errors[] = 'La date de fin de location est obligatoire (elle déclenche les alertes).';
            } elseif ($data['rental_start_date'] && $data['rental_end_date'] < $data['rental_start_date']) {
                $errors[] = 'La date de fin de location doit être postérieure à la date de début.';
            }
            if ($data['rental_monthly_cost'] !== null && ($data['rental_monthly_cost'] < 0 || $data['rental_monthly_cost'] > 100000)) {
                $errors[] = 'Loyer mensuel invalide.';
            }
        }

        $newPhoto = null;
        $file = Upload::collect('photo')[0] ?? null;
        if (!$errors && $file) {
            try {
                $newPhoto = Upload::savePhoto($file, 'truck_photos');
            } catch (\RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }
        if ($errors) {
            $this->fail($errors, 'truck/form', $id ? ['id' => $id] : []);
        }

        if ($newPhoto) {
            $data['photo_path'] = $newPhoto['path'];
        }
        if ($existing) {
            Truck::update((int)$id, $data);
            if ($newPhoto) {
                Upload::delete($existing['photo_path']);
            }
            Log::add('truck.update', 'truck', (int)$id, $plate);
            flash('success', 'Le camion ' . $plate . ' a été mis à jour.');
        } else {
            $id = Truck::create($data);
            Log::add('truck.create', 'truck', $id, $plate);
            flash('success', 'Le camion ' . $plate . ' a été ajouté.');
        }
        \App\Helpers\Session::clearOld();
        redirect('truck/show', ['id' => $id]);
    }

    public function delete(): void
    {
        $truck = $this->truckOr404($this->postInt('id'));
        Truck::delete((int)$truck['id']);
        Log::add('truck.delete', 'truck', (int)$truck['id'], $truck['plate']);
        flash('success', 'Le camion ' . $truck['plate'] . ' et tout son historique ont été supprimés.');
        redirect('truck/index');
    }

    /** Affecter / retirer un conducteur (admin et responsable). */
    public function assign(): void
    {
        $truck = $this->truckOr404($this->postInt('truck_id'));
        $driverId = $this->postInt('driver_id');
        $back = ['id' => $truck['id']];

        if (!$driverId) {
            Assignment::unassignTruck((int)$truck['id']);
            Log::add('truck.unassign', 'truck', (int)$truck['id'], $truck['plate']);
            flash('success', 'Le camion ' . $truck['plate'] . ' n\'a plus de conducteur affecté.');
            redirect('truck/show', $back);
        }
        $driver = User::find($driverId);
        if (!$driver || $driver['role'] !== 'driver' || !(int)$driver['is_active']) {
            $this->fail(['Conducteur invalide.'], 'truck/show', $back);
        }
        Assignment::assign((int)$truck['id'], $driverId, Auth::id());
        Log::add('truck.assign', 'truck', (int)$truck['id'], $truck['plate'] . ' → ' . $driver['first_name'] . ' ' . $driver['last_name']);
        flash('success', $driver['first_name'] . ' ' . $driver['last_name'] . ' est maintenant affecté au camion ' . $truck['plate'] . '.');
        redirect('truck/show', $back);
    }

    /** Changement rapide du statut du véhicule. */
    public function status(): void
    {
        $truck = $this->truckOr404($this->postInt('id'));
        $status = $this->post('status', 20);
        if (!isset(labels('truck_status')[$status])) {
            $this->fail(['Statut invalide.'], 'truck/show', ['id' => $truck['id']]);
        }
        Truck::setStatus((int)$truck['id'], $status);
        Log::add('truck.status', 'truck', (int)$truck['id'], $truck['plate'] . ' : ' . $truck['status'] . ' → ' . $status);
        flash('success', 'Statut du camion mis à jour : ' . label('truck_status', $status) . '.');
        redirect('truck/show', ['id' => $truck['id']]);
    }

    /** Renouvellement du contrat de location : nouvelle date de fin + historique + notification. */
    public function renew(): void
    {
        $truck = $this->truckOr404($this->postInt('truck_id'));
        $back = ['id' => $truck['id']];
        if ($truck['ownership'] !== 'rented') {
            $this->fail(['Ce camion n\'est pas en location.'], 'truck/show', $back);
        }
        $newEnd = valid_date($_POST['new_end_date'] ?? null);
        $note = $this->post('note', 500) ?: null;
        $errors = [];
        if (!$newEnd) {
            $errors[] = 'Indiquez la nouvelle date de fin de contrat.';
        } elseif ($newEnd <= date('Y-m-d') || ($truck['rental_end_date'] && $newEnd <= $truck['rental_end_date'])) {
            $errors[] = 'La nouvelle date de fin doit être postérieure à la date actuelle de fin de contrat.';
        }
        if ($errors) {
            $this->fail($errors, 'truck/show', $back);
        }
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $rid = DB::insert('rental_renewals', [
                'truck_id' => (int)$truck['id'], 'old_end_date' => $truck['rental_end_date'], 'new_end_date' => $newEnd,
                'note' => $note, 'renewed_by' => Auth::id(),
            ]);
            DB::update('trucks', (int)$truck['id'], ['rental_end_date' => $newEnd]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('Renouvellement non enregistré : ' . $e->getMessage());
            $this->fail(['Une erreur est survenue, le renouvellement n\'a pas été enregistré.'], 'truck/show', $back);
        }
        Log::add('rental.renew', 'truck', (int)$truck['id'], $truck['plate'] . ' : ' . ($truck['rental_end_date'] ?? '—') . ' → ' . $newEnd);
        $user = Auth::user();
        \App\Helpers\Notifier::toManagers('rental_renewed', 'rental_renewed:' . $rid,
            'Contrat de location renouvelé – ' . $truck['plate'], 'Contrat de location renouvelé',
            ['Camion' => $truck['plate'], 'Loueur' => (string)($truck['rental_company'] ?? '—'), 'N° de contrat' => (string)($truck['rental_contract_no'] ?? '—'),
             'Ancienne fin' => fdate($truck['rental_end_date']), 'Nouvelle fin' => fdate($newEnd), 'Par' => $user['first_name'] . ' ' . $user['last_name'], 'Note' => (string)($note ?? '')],
            absolute_url('truck/show', $back), 'green');
        flash('success', 'Contrat renouvelé jusqu\'au ' . fdate($newEnd) . '.');
        redirect('truck/show', $back);
    }

    public function maintenanceSave(): void
    {
        $truck = $this->truckOr404($this->postInt('truck_id'));
        $d = [
            'truck_id'          => (int)$truck['id'],
            'service_date'      => valid_date($_POST['service_date'] ?? null),
            'service_type'      => $this->post('service_type', 120),
            'description'       => $this->postText('description', 2000) ?: null,
            'mileage'           => int_or_null($_POST['mileage'] ?? null),
            'cost'              => ($_POST['cost'] ?? '') !== '' ? round((float)str_replace(',', '.', (string)$_POST['cost']), 2) : null,
            'garage'            => $this->post('garage', 150) ?: null,
            'next_service_date' => valid_date($_POST['next_service_date'] ?? null),
            'created_by'        => Auth::id(),
        ];
        $errors = [];
        if (!$d['service_date'] || $d['service_date'] > date('Y-m-d')) {
            $errors[] = 'La date d\'entretien est obligatoire et ne peut pas être dans le futur.';
        }
        if ($d['service_type'] === '') {
            $errors[] = 'Indiquez le type d\'entretien.';
        }
        if ($d['cost'] !== null && ($d['cost'] < 0 || $d['cost'] > 1000000)) {
            $errors[] = 'Coût invalide.';
        }
        if ($d['next_service_date'] && $d['service_date'] && $d['next_service_date'] <= $d['service_date']) {
            $errors[] = 'La prochaine date d\'entretien doit être postérieure à celle de l\'entretien.';
        }
        if ($errors) {
            $this->fail($errors, 'truck/show', ['id' => $truck['id']]);
        }
        $id = Maintenance::add($d);
        Log::add('maintenance.add', 'truck', (int)$truck['id'], $truck['plate'] . ' : ' . $d['service_type']);
        flash('success', 'Entretien enregistré.');
        redirect('truck/show', ['id' => $truck['id']]);
    }
}
