<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\DB;
use App\Helpers\Log;
use App\Helpers\Notifier;
use App\Helpers\Pager;
use App\Helpers\Upload;
use App\Middleware\Auth;
use App\Models\Incident;
use App\Models\Truck;
use App\Models\User;

final class IncidentController extends Controller
{
    /** Formulaire de signalement (conducteur). */
    public function form(): void
    {
        $truck = Truck::forDriver((int)Auth::id());
        if (!$truck) {
            flash('error', 'Aucun camion ne vous est affecté. Contactez votre responsable.');
            redirect('driver/home');
        }
        $this->view('incidents/form', [
            'title' => 'Signaler un problème',
            'truck' => $truck,
            'maxPhotos' => (int)config('max_photos_per_incident'),
        ]);
    }

    public function store(): void
    {
        $user = Auth::user();
        $truck = Truck::forDriver((int)$user['id']);
        if (!$truck) {
            $this->fail(['Aucun camion ne vous est affecté.'], 'driver/home');
        }
        $category = $this->post('category', 20);
        $priority = $this->post('priority', 10);
        $description = $this->postText('description', 3000);
        $safety = Incident::isSafetyRisk($priority, isset($_POST['safety_risk']));

        $errors = [];
        if (!isset(labels('incident_category')[$category])) {
            $errors[] = 'Choisissez le type de problème.';
        }
        if (!isset(labels('priority')[$priority])) {
            $errors[] = 'Choisissez un niveau de priorité.';
        }
        if (mb_strlen($description) < 5) {
            $errors[] = 'Décrivez le problème (au moins quelques mots).';
        }
        $files = Upload::collect('photos');
        $max = (int)config('max_photos_per_incident');
        if (count($files) > $max) {
            $errors[] = 'Maximum ' . $max . ' photos par signalement.';
        }
        $saved = [];
        if (!$errors) {
            try {
                foreach ($files as $file) {
                    $saved[] = Upload::savePhoto($file, 'incident_photos');
                }
            } catch (\RuntimeException $e) {
                foreach ($saved as $s) {
                    Upload::delete($s['path']);
                }
                $errors[] = $e->getMessage();
            }
        }
        if ($errors) {
            $this->fail($errors, 'incident/form');
        }

        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $id = DB::insert('incidents', [
                'truck_id'    => (int)$truck['id'],
                'driver_id'   => (int)$user['id'],
                'category'    => $category,
                'description' => $description,
                'priority'    => $priority,
                'status'      => 'new',
                'safety_risk' => $safety ? 1 : 0,
            ]);
            foreach ($saved as $s) {
                DB::insert('incident_photos', [
                    'incident_id'   => $id,
                    'file_path'     => $s['path'],
                    'original_name' => $s['original'],
                    'mime_type'     => $s['mime'],
                    'file_size'     => $s['size'],
                ]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            foreach ($saved as $s) {
                Upload::delete($s['path']);
            }
            error_log('Signalement non enregistré : ' . $e->getMessage());
            $this->fail(['Une erreur est survenue, le signalement n\'a pas été enregistré. Réessayez.'], 'incident/form');
        }

        Log::add('incident.create', 'incident', $id, $truck['plate'] . ' – ' . $category . ' – ' . $priority);

        $critical = $priority === 'critical';
        Notifier::toManagers(
            $critical ? 'incident_critical' : 'incident_new',
            ($critical ? 'incident_critical:' : 'incident_new:') . $id,
            ($critical ? 'Problème critique' : 'Nouveau signalement') . ' – ' . $truck['plate'] . ' – ' . label('incident_category', $category),
            $critical ? 'Problème critique déclaré' : 'Nouveau problème signalé',
            [
                'Conducteur'    => $user['first_name'] . ' ' . $user['last_name'],
                'Camion'        => $truck['plate'],
                'Type'          => label('incident_category', $category),
                'Description'   => $description,
                'Priorité'      => label('priority', $priority),
                'Risque sécurité' => $safety ? 'OUI – ne pas utiliser le véhicule avant vérification' : 'Non',
                'Date et heure' => fdatetime(date('Y-m-d H:i:s')),
            ],
            absolute_url('incident/show', ['id' => $id]),
            $critical || $safety ? 'red' : 'orange'
        );

        \App\Helpers\Session::clearOld();
        flash('success', 'Votre signalement a été envoyé au responsable.' . ($safety ? ' Ne pas utiliser le véhicule avant vérification.' : ''));
        redirect($safety ? 'incident/show' : 'incident/mine', $safety ? ['id' => $id] : []);
    }

    /** Historique des signalements du conducteur. */
    public function mine(): void
    {
        $f = ['driver_id' => Auth::id()];
        $pager = new Pager(Incident::count($f), 15);
        $this->view('incidents/mine', ['title' => 'Mes signalements', 'incidents' => Incident::search($f, $pager->limitSql()), 'pager' => $pager]);
    }

    /** Liste complète (personnel). */
    public function index(): void
    {
        $f = $this->filters(['truck_id', 'driver_id', 'status', 'priority', 'from', 'to', 'q']);
        $pager = new Pager(Incident::count($f), 15);
        $this->view('incidents/index', [
            'title'     => 'Signalements',
            'incidents' => Incident::search($f, $pager->limitSql()),
            'f'         => $f,
            'pager'     => $pager,
            'trucks'    => Truck::options(),
            'drivers'   => User::drivers(false),
        ]);
    }

    public function show(): void
    {
        $id = $this->getInt('id');
        $incident = $id ? Incident::find($id) : null;
        if (!$incident) {
            $this->notFound('Ce signalement');
        }
        if (Auth::is('driver') && (int)$incident['driver_id'] !== Auth::id()) {
            $this->forbidden();
        }
        $truck = Truck::find((int)$incident['truck_id']);
        $this->view('incidents/show', [
            'title'    => 'Signalement #' . $incident['id'],
            'incident' => $incident,
            'photos'   => Incident::photos((int)$incident['id']),
            'comments' => Incident::comments((int)$incident['id']),
            'truck'    => $truck,
            'staff'    => Auth::isStaff(),
        ]);
    }

    /** Commentaire et/ou changement de statut (personnel). */
    public function comment(): void
    {
        $id = $this->postInt('id');
        $incident = $id ? Incident::find($id) : null;
        if (!$incident) {
            $this->notFound('Ce signalement');
        }
        $comment = $this->postText('comment', 2000);
        $status = $this->post('status', 20);
        $errors = [];
        if (!isset(labels('incident_status')[$status])) {
            $errors[] = 'Statut invalide.';
        }
        $changed = $status !== $incident['status'];
        if ($comment === '' && !$changed) {
            $errors[] = 'Ajoutez un commentaire ou changez le statut.';
        }
        if ($errors) {
            $this->fail($errors, 'incident/show', ['id' => $id]);
        }
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            DB::insert('incident_comments', [
                'incident_id' => $id,
                'user_id'     => Auth::id(),
                'comment'     => $comment !== '' ? $comment : '(changement de statut)',
                'old_status'  => $changed ? $incident['status'] : null,
                'new_status'  => $changed ? $status : null,
            ]);
            if ($changed) {
                DB::update('incidents', (int)$id, [
                    'status'      => $status,
                    'resolved_at' => $status === 'resolved' ? date('Y-m-d H:i:s') : null,
                ]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('Commentaire non enregistré : ' . $e->getMessage());
            $this->fail(['Une erreur est survenue. Réessayez.'], 'incident/show', ['id' => $id]);
        }
        Log::add('incident.update', 'incident', (int)$id, $changed ? $incident['status'] . ' → ' . $status : 'commentaire');
        \App\Helpers\Session::clearOld();
        flash('success', $changed ? 'Statut mis à jour : ' . label('incident_status', $status) . '.' : 'Commentaire ajouté.');
        redirect('incident/show', ['id' => $id]);
    }
}
