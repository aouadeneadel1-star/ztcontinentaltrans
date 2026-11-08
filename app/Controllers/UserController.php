<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Log;
use App\Helpers\Pager;
use App\Middleware\Auth;
use App\Models\Assignment;
use App\Models\Truck;
use App\Models\User;

final class UserController extends Controller
{
    public function index(): void
    {
        $f = $this->filters(['q', 'role']);
        $pager = new Pager(User::count($f));
        $this->view('users/index', ['title' => 'Utilisateurs', 'users' => User::search($f, $pager->limitSql()), 'f' => $f, 'pager' => $pager]);
    }

    public function form(): void
    {
        $id = $this->getInt('id');
        $user = $id ? User::find($id) : null;
        if ($id && !$user) {
            $this->notFound('Cet utilisateur');
        }
        $this->view('users/form', [
            'title'  => $user ? 'Modifier ' . $user['first_name'] . ' ' . $user['last_name'] : 'Nouvel utilisateur',
            'u'      => $user,
            'trucks' => Truck::search([]),
            'currentTruck' => $user ? Truck::forDriver((int)$user['id']) : null,
        ]);
    }

    public function save(): void
    {
        $id = $this->postInt('id');
        $existing = $id ? User::find($id) : null;
        if ($id && !$existing) {
            $this->notFound('Cet utilisateur');
        }
        $email = mb_strtolower($this->post('email', 190));
        $d = [
            'role'       => $this->post('role', 10),
            'first_name' => $this->post('first_name', 80),
            'last_name'  => $this->post('last_name', 80),
            'email'      => $email,
            'phone'      => $this->post('phone', 30) ?: null,
            'is_active'  => isset($_POST['is_active']) ? 1 : 0,
        ];
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['password_confirm'] ?? '');

        $errors = [];
        if (!isset(labels('roles')[$d['role']])) {
            $errors[] = 'Rôle invalide.';
        }
        if ($d['first_name'] === '' || $d['last_name'] === '') {
            $errors[] = 'Le prénom et le nom sont obligatoires.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Adresse e-mail invalide.';
        } elseif (User::emailTaken($email, $id)) {
            $errors[] = 'Cette adresse e-mail est déjà utilisée.';
        }
        if ($d['phone'] && !preg_match('/^[0-9 +().\-]{6,30}$/', $d['phone'])) {
            $errors[] = 'Numéro de téléphone invalide.';
        }
        if (!$existing || $password !== '') {
            $errors = array_merge($errors, AuthController::passwordErrors($password, $confirm));
        }
        // On ne peut pas retirer le dernier administrateur actif
        if ($existing && $existing['role'] === 'admin' && ($d['role'] !== 'admin' || !$d['is_active']) && User::countActiveAdmins((int)$id) === 0) {
            $errors[] = 'Impossible : il doit rester au moins un administrateur actif.';
        }
        if ($existing && (int)$existing['id'] === Auth::id() && !$d['is_active']) {
            $errors[] = 'Vous ne pouvez pas désactiver votre propre compte.';
        }
        if ($errors) {
            $this->fail($errors, 'user/form', $id ? ['id' => $id] : []);
        }

        if ($password !== '') {
            $d['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            $d['must_change_password'] = isset($_POST['must_change_password']) ? 1 : 0;
        }
        if ($existing) {
            User::update((int)$id, $d);
            Log::add('user.update', 'user', (int)$id, $email . ' (' . $d['role'] . ')');
            $msg = 'Utilisateur mis à jour.';
        } else {
            $d['must_change_password'] = isset($_POST['must_change_password']) ? 1 : 0;
            $id = User::create($d);
            Log::add('user.create', 'user', $id, $email . ' (' . $d['role'] . ')');
            $msg = 'Utilisateur créé.';
        }

        // Un compte désactivé ou changé de rôle perd son camion
        if ($d['role'] !== 'driver' || !$d['is_active']) {
            Assignment::unassignDriver((int)$id);
        } elseif ($d['role'] === 'driver') {
            $truckId = $this->postInt('truck_id');
            $current = Truck::forDriver((int)$id);
            if ($truckId && (!$current || (int)$current['id'] !== $truckId) && Truck::find($truckId)) {
                Assignment::assign($truckId, (int)$id, Auth::id());
                Log::add('truck.assign', 'truck', $truckId, 'Affectation depuis la fiche utilisateur #' . $id);
            } elseif (!$truckId && $current && isset($_POST['truck_id'])) {
                Assignment::unassignDriver((int)$id);
            }
        }
        \App\Helpers\Session::clearOld();
        flash('success', $msg);
        redirect('user/index');
    }

    public function toggle(): void
    {
        $id = $this->postInt('id');
        $u = $id ? User::find($id) : null;
        if (!$u) {
            $this->notFound('Cet utilisateur');
        }
        if ((int)$u['id'] === Auth::id()) {
            $this->fail(['Vous ne pouvez pas désactiver votre propre compte.'], 'user/index');
        }
        $newState = (int)$u['is_active'] ? 0 : 1;
        if (!$newState && $u['role'] === 'admin' && User::countActiveAdmins((int)$id) === 0) {
            $this->fail(['Impossible : il doit rester au moins un administrateur actif.'], 'user/index');
        }
        User::update((int)$id, ['is_active' => $newState]);
        if (!$newState) {
            Assignment::unassignDriver((int)$id);
        }
        Log::add($newState ? 'user.activate' : 'user.deactivate', 'user', (int)$id, $u['email']);
        flash('success', 'Compte ' . ($newState ? 'réactivé' : 'désactivé') . ' : ' . $u['first_name'] . ' ' . $u['last_name'] . '.');
        redirect('user/index');
    }
}
