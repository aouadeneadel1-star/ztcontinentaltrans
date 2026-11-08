<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\DB;
use App\Helpers\Pager;

/** Journal d'activité et historique des notifications (administrateur / responsable). */
final class AdminController extends Controller
{
    public function logs(): void
    {
        $f = $this->filters(['action', 'user_id', 'from', 'to']);
        $w = [];
        $p = [];
        if ($f['action'] !== '') {
            $w[] = 'l.action LIKE ?';
            $p[] = addcslashes($f['action'], '%_\\') . '%';
        }
        if ($f['user_id'] !== '') {
            $w[] = 'l.user_id = ?';
            $p[] = (int)$f['user_id'];
        }
        if ($f['from'] !== '') {
            $w[] = 'DATE(l.created_at) >= ?';
            $p[] = $f['from'];
        }
        if ($f['to'] !== '') {
            $w[] = 'DATE(l.created_at) <= ?';
            $p[] = $f['to'];
        }
        $where = $w ? ' WHERE ' . implode(' AND ', $w) : '';
        $pager = new Pager((int)DB::value('SELECT COUNT(*) FROM activity_logs l' . $where, $p), 30);
        $this->view('admin/logs', [
            'title' => 'Journal d\'activité',
            'logs'  => DB::all("SELECT l.*, CONCAT(u.first_name,' ',u.last_name) AS user_name FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id" . $where . ' ORDER BY l.id DESC' . $pager->limitSql(), $p),
            'f'     => $f,
            'pager' => $pager,
            'users' => DB::all("SELECT id, first_name, last_name FROM users ORDER BY last_name"),
        ]);
    }

    public function notifications(): void
    {
        $status = $this->filters(['status'])['status'];
        $where = in_array($status, ['pending', 'sent', 'failed', 'skipped'], true) ? ' WHERE status = ?' : '';
        $p = $where ? [$status] : [];
        $pager = new Pager((int)DB::value('SELECT COUNT(*) FROM notifications' . $where, $p), 25);
        $this->view('admin/notifications', [
            'title' => 'Notifications e-mail',
            'items' => DB::all('SELECT id, type, recipient_email, subject, status, error_message, created_at, sent_at FROM notifications' . $where . ' ORDER BY id DESC' . $pager->limitSql(), $p),
            'status' => $status,
            'pager' => $pager,
            'mailEnabled' => !empty((require ROOT_PATH . '/config/mail.php')['enabled']),
        ]);
    }
}
