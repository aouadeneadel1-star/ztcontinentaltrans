<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\DB;
use App\Helpers\Log;
use App\Helpers\Notifier;
use App\Helpers\Pager;
use App\Helpers\Upload;
use App\Middleware\Auth;
use App\Models\Check;
use App\Models\Truck;
use App\Models\User;

final class CheckController extends Controller
{
    private function type(): string
    {
        $t = (string)($_GET['type'] ?? $_POST['type'] ?? '');
        return isset(labels('check_type')[$t]) ? $t : 'morning';
    }

    /** Formulaire de contrôle (conducteur) : camion = celui qui lui est affecté. */
    public function form(): void
    {
        $user = Auth::user();
        $type = $this->type();
        $truck = Truck::forDriver((int)$user['id']);
        if (!$truck) {
            flash('error', 'Aucun camion ne vous est affecté. Contactez votre responsable.');
            redirect('driver/home');
        }
        $done = Check::forDriverToday((int)$user['id'], $type);
        if ($done) {
            flash('info', 'Le contrôle du ' . mb_strtolower(label('check_type', $type)) . ' est déjà enregistré aujourd\'hui (' . ftime($done['checked_at']) . ').');
            redirect('check/show', ['id' => $done['id']]);
        }
        $this->view('checks/form', [
            'title'  => 'Contrôle du ' . mb_strtolower(label('check_type', $type)),
            'type'   => $type,
            'truck'  => $truck,
            'safety' => Truck::hasSafetyRisk((int)$truck['id']),
            'maxPhotos' => (int)config('max_photos_per_check'),
        ]);
    }

    public function store(): void
    {
        $user = Auth::user();
        $type = $this->type();
        $truck = Truck::forDriver((int)$user['id']);
        if (!$truck) {
            $this->fail(['Aucun camion ne vous est affecté.'], 'driver/home');
        }
        if (Check::forDriverToday((int)$user['id'], $type)) {
            flash('info', 'Ce contrôle a déjà été enregistré aujourd\'hui.');
            redirect('driver/home');
        }

        $mileage = int_or_null($_POST['mileage'] ?? null);
        $comment = $this->postText('comment', 1500);
        $anomaly = isset($_POST['has_anomaly']) ? 1 : 0;
        $errors = [];

        if (($_POST['mileage'] ?? '') !== '' && $mileage === null) {
            $errors[] = 'Kilométrage invalide.';
        } elseif ($mileage !== null && $mileage < (int)$truck['mileage']) {
            $errors[] = 'Le kilométrage saisi (' . fkm($mileage) . ') est inférieur au dernier kilométrage enregistré (' . fkm((int)$truck['mileage']) . ').';
        } elseif ($mileage !== null && $mileage - (int)$truck['mileage'] > 3000 && (int)$truck['mileage'] > 0) {
            $errors[] = 'Le kilométrage semble incorrect (écart supérieur à 3 000 km). Vérifiez votre saisie.';
        }
        if ($anomaly && mb_strlen($comment) < 5) {
            $errors[] = 'Décrivez l\'anomalie dans le commentaire.';
        }

        $files = Upload::collect('photos');
        $max = (int)config('max_photos_per_check');
        if (!$files) {
            $errors[] = 'Ajoutez au moins une photo du camion.';
        } elseif (count($files) > $max) {
            $errors[] = 'Maximum ' . $max . ' photos par contrôle.';
        }

        $saved = [];
        if (!$errors) {
            try {
                foreach ($files as $file) {
                    $saved[] = Upload::savePhoto($file, 'truck_photos') + ['file' => $file];
                }
            } catch (\RuntimeException $e) {
                foreach ($saved as $s) {
                    Upload::delete($s['path']);
                }
                $errors[] = $e->getMessage();
            }
        }
        if ($errors) {
            $this->fail($errors, 'check/form', ['type' => $type]);
        }

        $now = date('Y-m-d H:i:s');
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $checkId = DB::insert('daily_checks', [
                'truck_id'    => (int)$truck['id'],
                'driver_id'   => (int)$user['id'],
                'check_type'  => $type,
                'check_date'  => date('Y-m-d'),
                'checked_at'  => $now,
                'mileage'     => $mileage,
                'comment'     => $comment ?: null,
                'has_anomaly' => $anomaly,
            ]);
            foreach ($saved as $s) {
                DB::insert('truck_photos', [
                    'check_id'      => $checkId,
                    'truck_id'      => (int)$truck['id'],
                    'driver_id'     => (int)$user['id'],
                    'check_type'    => $type,
                    'file_path'     => $s['path'],
                    'original_name' => $s['original'],
                    'mime_type'     => $s['mime'],
                    'file_size'     => $s['size'],
                    'taken_at'      => $now,
                ]);
            }
            $upd = [];
            if ($mileage !== null && $mileage > (int)$truck['mileage']) {
                $upd['mileage'] = $mileage;
            }
            // Le matin le camion part en tournée, le soir il redevient disponible
            if ($type === 'morning' && $truck['status'] === 'available') {
                $upd['status'] = 'in_service';
            } elseif ($type === 'evening' && $truck['status'] === 'in_service') {
                $upd['status'] = 'available';
            }
            if ($upd) {
                DB::update('trucks', (int)$truck['id'], $upd);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            foreach ($saved as $s) {
                Upload::delete($s['path']);
            }
            error_log('Contrôle non enregistré : ' . $e->getMessage());
            $this->fail(['Une erreur est survenue, le contrôle n\'a pas été enregistré. Réessayez.'], 'check/form', ['type' => $type]);
        }

        Log::add('check.' . $type, 'check', $checkId, $truck['plate'] . ($anomaly ? ' (anomalie)' : ''));

        if ($anomaly) {
            Notifier::toManagers(
                'check_anomaly',
                'check_anomaly:' . $checkId,
                'Anomalie au contrôle du ' . mb_strtolower(label('check_type', $type)) . ' – ' . $truck['plate'],
                'Anomalie signalée lors d\'un contrôle quotidien',
                [
                    'Conducteur'  => $user['first_name'] . ' ' . $user['last_name'],
                    'Camion'      => $truck['plate'],
                    'Contrôle'    => label('check_type', $type),
                    'Commentaire' => $comment,
                    'Photos'      => (string)count($saved),
                    'Date et heure' => fdatetime($now),
                ],
                absolute_url('check/show', ['id' => $checkId]),
                'orange'
            );
        }
        \App\Helpers\Session::clearOld();
        flash('success', 'Contrôle du ' . mb_strtolower(label('check_type', $type)) . ' enregistré avec ' . count($saved) . ' photo' . (count($saved) > 1 ? 's' : '') . '.');
        redirect('driver/home');
    }

    /** Détail d'un contrôle : personnel, ou conducteur auteur uniquement. */
    public function show(): void
    {
        $check = $this->checkOr404($this->getInt('id'));
        $this->view('checks/show', [
            'title'  => 'Contrôle ' . mb_strtolower(label('check_type', $check['check_type'])) . ' – ' . $check['plate'],
            'check'  => $check,
            'photos' => Check::photos((int)$check['id']),
        ]);
    }

    private function checkOr404(?int $id): array
    {
        $check = $id ? Check::find($id) : null;
        if (!$check) {
            $this->notFound('Ce contrôle');
        }
        if (Auth::is('driver') && (int)$check['driver_id'] !== Auth::id()) {
            $this->forbidden();
        }
        return $check;
    }

    /** Galerie / liste des contrôles (personnel) avec filtres. */
    public function index(): void
    {
        $f = $this->filters(['truck_id', 'driver_id', 'type', 'from', 'to', 'q', 'anomaly']);
        $pager = new Pager(Check::count($f), 12);
        $this->view('checks/index', [
            'title'  => 'Photos et contrôles',
            'checks' => Check::search($f, $pager->limitSql()),
            'f'      => $f,
            'pager'  => $pager,
            'trucks' => Truck::options(),
            'drivers' => User::drivers(false),
        ]);
    }

    /** Comparaison des photos matin / soir d'un camion pour une journée. */
    public function compare(): void
    {
        $truck = $this->truckOr404($this->getInt('truck_id'));
        $date = valid_date($_GET['date'] ?? '') ?? date('Y-m-d');
        $checks = Check::forTruckDay((int)$truck['id'], $date);
        foreach ($checks as &$c) {
            $c['photos'] = Check::photos((int)$c['id']);
        }
        unset($c);
        $this->view('checks/compare', [
            'title'  => 'Comparaison – ' . $truck['plate'],
            'truck'  => $truck,
            'date'   => $date,
            'morning' => array_values(array_filter($checks, static fn($c) => $c['check_type'] === 'morning')),
            'evening' => array_values(array_filter($checks, static fn($c) => $c['check_type'] === 'evening')),
        ]);
    }
}
