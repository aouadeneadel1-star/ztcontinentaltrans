<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\DB;
use App\Helpers\Log;
use App\Helpers\Pager;
use App\Helpers\Phone;
use App\Helpers\Session;
use App\Helpers\Upload;
use App\Helpers\WhatsApp;
use App\Middleware\Auth;
use App\Models\Dispatch;
use App\Models\Helper;
use App\Models\Truck;
use App\Models\User;

/** Planning des tournées : préparation, validation et envoi WhatsApp. */
final class DispatchController extends Controller
{
    private const MAX_TOURS = 6;

    public function index(): void
    {
        $f = $this->filters(['date', 'status']);
        $pager = new Pager(Dispatch::count($f));
        $this->view('dispatches/index', [
            'title' => 'Tournées', 'rows' => Dispatch::search($f, $pager->limitSql()), 'f' => $f, 'pager' => $pager,
        ]);
    }

    public function form(): void
    {
        $id = $this->getInt('id');
        $d = $id ? Dispatch::find($id) : null;
        if ($id && !$d) {
            $this->notFound('Ce planning');
        }
        if ($d && $d['status'] !== 'draft') {
            flash('info', 'Ce planning a déjà été validé. Supprimez-le puis recréez-le pour le modifier.');
            redirect('dispatch/show', ['id' => $id]);
        }
        $this->view('dispatches/form', [
            'title'   => $d ? 'Modifier le planning' : 'Nouveau planning',
            'd'       => $d,
            'tours'   => $d ? Dispatch::tours((int)$d['id']) : [],
            'drivers' => User::drivers(),
            'helpers' => Helper::all(true),
            'trucks'  => Truck::search([]),
            'max'     => self::MAX_TOURS,
        ]);
    }

    public function save(): void
    {
        $id = $this->postInt('id');
        $existing = $id ? Dispatch::find($id) : null;
        if ($id && (!$existing || $existing['status'] !== 'draft')) {
            $this->notFound('Ce planning modifiable');
        }
        $back = fn() => $id ? ['id' => $id] : [];

        $date    = valid_date($this->post('work_date', 10));
        $truckId = $this->postInt('truck_id');
        $driverId = $this->postInt('driver_id');
        $h1 = $this->postInt('helper1_id');
        $h2 = $this->postInt('helper2_id');
        if ($h1 === null && $h2 !== null) {
            [$h1, $h2] = [$h2, null];
        }
        $note = $this->postText('note', 500);

        $errors = [];
        if (!$date) {
            $errors[] = 'La date est obligatoire.';
        }
        $truck = $truckId ? Truck::find($truckId) : null;
        if (!$truck) {
            $errors[] = 'Choisissez un camion.';
        }
        $driver = $driverId ? User::find($driverId) : null;
        if (!$driver || $driver['role'] !== 'driver' || !(int)$driver['is_active']) {
            $errors[] = 'Choisissez un conducteur actif.';
        } elseif (!Phone::normalize($driver['phone'])) {
            $errors[] = 'Le conducteur n\'a pas de numéro de téléphone valide : renseignez-le dans sa fiche utilisateur.';
        }
        if ($h1 && $h1 === $h2) {
            $errors[] = 'Les deux rippeurs doivent être différents.';
        }
        foreach (array_filter([$h1, $h2]) as $hid) {
            $h = Helper::find((int)$hid);
            if (!$h || !(int)$h['is_active']) {
                $errors[] = 'Rippeur introuvable ou désactivé.';
                break;
            }
        }

        // Tournées / courses
        $existingTours = $existing ? array_column(Dispatch::tours((int)$existing['id']), null, 'id') : [];
        $rows = is_array($_POST['tours'] ?? null) ? array_values($_POST['tours']) : [];
        $tours = [];
        foreach ($rows as $i => $r) {
            if (!is_array($r) || count($tours) >= self::MAX_TOURS) {
                continue;
            }
            $client = clean(is_string($r['client'] ?? null) ? $r['client'] : '', 120);
            $city   = clean(is_string($r['city'] ?? null) ? $r['city'] : '', 120);
            if ($client === '' && $city === '') {
                continue;
            }
            $kind = ($r['kind'] ?? '') === 'run' ? 'run' : 'tour';
            $prev = $existingTours[(int)($r['id'] ?? 0)] ?? null;
            $tours[] = ['idx' => $i, 'kind' => $kind, 'client' => $client, 'city' => $city, 'prev' => $prev];
            if ($client === '' || $city === '') {
                $errors[] = 'Chaque tournée ou course doit avoir un client et une ville.';
            }
        }
        if (!$tours) {
            $errors[] = 'Ajoutez au moins une tournée ou une course.';
        }

        // Fichiers (un par ligne, facultatif)
        $files = [];
        foreach ($tours as $k => $t) {
            $f = self::fileAt('tour_file', (int)$t['idx']);
            if ($f) {
                $files[$k] = $f;
            }
        }
        $saved = [];
        if (!$errors) {
            try {
                foreach ($files as $k => $f) {
                    $saved[$k] = Upload::saveDocument($f, 'dispatches');
                }
            } catch (\RuntimeException $e) {
                foreach ($saved as $s) {
                    Upload::delete($s['path']);
                }
                $errors[] = $e->getMessage();
            }
        }
        if ($errors) {
            $this->fail($errors, 'dispatch/form', $back());
        }

        $helperIds = array_values(array_filter([$h1, $h2]));
        $data = [
            'work_date' => $date, 'truck_id' => $truckId, 'driver_id' => $driverId,
            'helper1_id' => $h1, 'helper2_id' => $h2, 'note' => $note !== '' ? $note : null,
        ];
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            if ($existing) {
                DB::update('dispatches', (int)$id, $data);
            } else {
                $data['status'] = 'draft';
                $data['created_by'] = Auth::id();
                $id = DB::insert('dispatches', $data);
            }
            $keepIds = [];
            $toDelete = [];
            foreach ($tours as $k => $t) {
                $row = ['dispatch_id' => $id, 'position' => $k + 1, 'kind' => $t['kind'], 'client' => $t['client'], 'city' => $t['city']];
                $prev = $t['prev'];
                if (isset($saved[$k])) {
                    if ($prev && $prev['file_path']) {
                        $toDelete[] = $prev['file_path'];
                    }
                    $row += ['file_path' => $saved[$k]['path'], 'original_name' => $saved[$k]['original'], 'mime_type' => $saved[$k]['mime'], 'file_size' => $saved[$k]['size']];
                }
                if ($prev) {
                    DB::update('dispatch_tours', (int)$prev['id'], $row);
                    $keepIds[] = (int)$prev['id'];
                } else {
                    $row += ['token' => bin2hex(random_bytes(16))];
                    $keepIds[] = DB::insert('dispatch_tours', $row);
                }
            }
            foreach ($existingTours as $tid => $old) {
                if (!in_array((int)$tid, $keepIds, true)) {
                    DB::run('DELETE FROM dispatch_tours WHERE id = ?', [(int)$tid]);
                    if ($old['file_path']) {
                        $toDelete[] = $old['file_path'];
                    }
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            foreach ($saved as $s) {
                Upload::delete($s['path']);
            }
            throw $e;
        }
        foreach ($toDelete as $p) {
            Upload::delete($p);
        }
        Log::add($existing ? 'dispatch.update' : 'dispatch.create', 'dispatch', (int)$id, fdate($date) . ' – ' . $truck['plate']);
        foreach (Dispatch::conflicts($date, (int)$truckId, (int)$driverId, $helperIds, (int)$id) as $c) {
            flash('warning', $c);
        }
        Session::clearOld();
        flash('success', 'Planning enregistré. Vérifiez l\'aperçu puis validez l\'envoi.');
        redirect('dispatch/show', ['id' => $id]);
    }

    public function show(): void
    {
        $d = Dispatch::find($this->getInt('id') ?? 0) ?? $this->notFound('Ce planning');
        $tours = Dispatch::tours((int)$d['id']);
        $messages = Dispatch::messages((int)$d['id']);
        $preview = $d['status'] === 'draft' ? Dispatch::buildMessages($d, $tours) : [];
        foreach ($messages as &$m) {
            $m['link'] = $m['phone'] ? WhatsApp::link($m['phone'], $m['body']) : null;
        }
        foreach ($preview as &$m) {
            $m['link'] = null;
        }
        $this->view('dispatches/show', [
            'title' => 'Planning du ' . fdate($d['work_date']),
            'd' => $d, 'tours' => $tours, 'messages' => $messages, 'preview' => $preview,
            'auto' => WhatsApp::enabled(),
        ]);
    }

    /** Validation : crée les messages puis les envoie automatiquement. */
    public function validate(): void
    {
        $d = Dispatch::find($this->postInt('id') ?? 0) ?? $this->notFound('Ce planning');
        if ($d['status'] !== 'draft') {
            flash('info', 'Ce planning est déjà validé.');
            redirect('dispatch/show', ['id' => $d['id']]);
        }
        $tours = Dispatch::tours((int)$d['id']);
        $built = Dispatch::buildMessages($d, $tours);
        foreach ($built as $m) {
            if (!$m['phone']) {
                $this->fail(['Numéro WhatsApp manquant ou invalide pour ' . $m['name'] . '.'], 'dispatch/show', ['id' => $d['id']]);
            }
        }
        DB::run('DELETE FROM dispatch_messages WHERE dispatch_id = ?', [$d['id']]);
        foreach ($built as $m) {
            DB::insert('dispatch_messages', [
                'dispatch_id' => $d['id'], 'recipient_type' => $m['type'], 'recipient_name' => $m['name'],
                'phone' => $m['phone'], 'body' => $m['body'], 'params' => json_encode($m['params'], JSON_UNESCAPED_UNICODE),
            ]);
        }
        DB::update('dispatches', (int)$d['id'], ['status' => 'validated', 'validated_by' => Auth::id(), 'validated_at' => date('Y-m-d H:i:s')]);
        Log::add('dispatch.validate', 'dispatch', (int)$d['id'], fdate($d['work_date']));
        $this->deliver((int)$d['id']);
    }

    /** Renvoie uniquement les messages non envoyés. */
    public function resend(): void
    {
        $d = Dispatch::find($this->postInt('id') ?? 0) ?? $this->notFound('Ce planning');
        if ($d['status'] === 'draft') {
            redirect('dispatch/show', ['id' => $d['id']]);
        }
        $this->deliver((int)$d['id']);
    }

    /** Envoi manuel via lien WhatsApp : l'administrateur confirme que c'est parti. */
    public function mark(): void
    {
        $m = DB::one('SELECT * FROM dispatch_messages WHERE id = ?', [$this->postInt('id') ?? 0]) ?? $this->notFound('Ce message');
        DB::update('dispatch_messages', (int)$m['id'], ['status' => 'sent', 'sent_at' => date('Y-m-d H:i:s'), 'error_message' => null]);
        $this->refreshStatus((int)$m['dispatch_id']);
        redirect('dispatch/show', ['id' => $m['dispatch_id']]);
    }

    public function delete(): void
    {
        $d = Dispatch::find($this->postInt('id') ?? 0) ?? $this->notFound('Ce planning');
        Dispatch::delete((int)$d['id']);
        Log::add('dispatch.delete', 'dispatch', (int)$d['id'], fdate($d['work_date']) . ' – ' . $d['plate']);
        flash('success', 'Planning supprimé.');
        redirect('dispatch/index');
    }

    private function deliver(int $id): void
    {
        if (!WhatsApp::enabled()) {
            flash('info', 'Envoi automatique non configuré : utilisez les boutons WhatsApp ci-dessous pour envoyer chaque message.');
            redirect('dispatch/show', ['id' => $id]);
        }
        foreach (Dispatch::messages($id) as $m) {
            if ($m['status'] === 'sent') {
                continue;
            }
            $params = json_decode((string)$m['params'], true) ?: [];
            $r = WhatsApp::send((string)$m['phone'], $m['body'], $params);
            DB::update('dispatch_messages', (int)$m['id'], [
                'status' => $r['ok'] ? 'sent' : 'failed', 'provider_id' => $r['id'], 'error_message' => $r['error'],
                'attempts' => (int)$m['attempts'] + 1, 'sent_at' => $r['ok'] ? date('Y-m-d H:i:s') : null,
            ]);
        }
        $status = $this->refreshStatus($id);
        flash($status === 'sent' ? 'success' : 'error', match ($status) {
            'sent' => 'Messages WhatsApp envoyés.',
            'partial' => 'Certains messages n\'ont pas pu être envoyés. Voir le détail ci-dessous.',
            default => 'L\'envoi a échoué. Voir le détail ci-dessous.',
        });
        redirect('dispatch/show', ['id' => $id]);
    }

    private function refreshStatus(int $id): string
    {
        $c = DB::one("SELECT COUNT(*) n, SUM(status='sent') s FROM dispatch_messages WHERE dispatch_id = ?", [$id]);
        $n = (int)$c['n'];
        $s = (int)$c['s'];
        $status = $n === 0 ? 'validated' : ($s === $n ? 'sent' : ($s > 0 ? 'partial' : (DB::value("SELECT COUNT(*) FROM dispatch_messages WHERE dispatch_id = ? AND status = 'failed'", [$id]) ? 'failed' : 'validated')));
        DB::update('dispatches', $id, ['status' => $status]);
        return $status;
    }

    /** Fichier reçu à l'indice $i de $_FILES[$field][...] (ou null). */
    private static function fileAt(string $field, int $i): ?array
    {
        $f = $_FILES[$field] ?? null;
        if (!$f || !is_array($f['name'] ?? null) || !isset($f['name'][$i]) || ($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || $f['name'][$i] === '') {
            return null;
        }
        return ['name' => $f['name'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
    }
}
