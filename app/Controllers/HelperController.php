<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Log;
use App\Helpers\Phone;
use App\Helpers\Session;
use App\Models\Helper;

final class HelperController extends Controller
{
    public function index(): void
    {
        $this->view('helpers/index', ['title' => 'Rippeurs', 'helpers' => Helper::all()]);
    }

    public function form(): void
    {
        $id = $this->getInt('id');
        $h = $id ? Helper::find($id) : null;
        if ($id && !$h) {
            $this->notFound('Ce rippeur');
        }
        $this->view('helpers/form', ['title' => $h ? 'Modifier un rippeur' : 'Nouveau rippeur', 'h' => $h]);
    }

    public function save(): void
    {
        $id = $this->postInt('id');
        $existing = $id ? Helper::find($id) : null;
        if ($id && !$existing) {
            $this->notFound('Ce rippeur');
        }
        $phone = Phone::normalize($this->post('whatsapp', 30));
        $d = [
            'first_name' => $this->post('first_name', 80),
            'last_name'  => $this->post('last_name', 80),
            'whatsapp'   => $phone,
            'is_active'  => isset($_POST['is_active']) || !$existing ? 1 : 0,
        ];
        $errors = [];
        if ($d['first_name'] === '' || $d['last_name'] === '') {
            $errors[] = 'Le nom et le prénom sont obligatoires.';
        }
        if (!$phone) {
            $errors[] = 'Numéro WhatsApp invalide. Exemple : 06 12 34 56 78 ou +33 6 12 34 56 78.';
        }
        if ($errors) {
            $this->fail($errors, 'helper/form', $id ? ['id' => $id] : []);
        }
        if ($existing) {
            Helper::update((int)$id, $d);
            Log::add('helper.update', 'helper', (int)$id, $d['first_name'] . ' ' . $d['last_name']);
        } else {
            $id = Helper::create($d);
            Log::add('helper.create', 'helper', $id, $d['first_name'] . ' ' . $d['last_name']);
        }
        Session::clearOld();
        flash('success', 'Rippeur enregistré.');
        redirect('helper/index');
    }

    public function toggle(): void
    {
        $h = Helper::find($this->postInt('id') ?? 0) ?? $this->notFound('Ce rippeur');
        Helper::update((int)$h['id'], ['is_active' => (int)$h['is_active'] ? 0 : 1]);
        redirect('helper/index');
    }

    public function delete(): void
    {
        $h = Helper::find($this->postInt('id') ?? 0) ?? $this->notFound('Ce rippeur');
        if (Helper::usage((int)$h['id']) > 0) {
            $this->fail(['Ce rippeur apparaît dans des plannings : désactivez-le plutôt que de le supprimer.'], 'helper/index');
        }
        Helper::delete((int)$h['id']);
        Log::add('helper.delete', 'helper', (int)$h['id'], $h['first_name'] . ' ' . $h['last_name']);
        flash('success', 'Rippeur supprimé.');
        redirect('helper/index');
    }
}
