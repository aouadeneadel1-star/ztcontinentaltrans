<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\DB;
use App\Helpers\Log;
use App\Helpers\Upload;
use App\Middleware\Auth;
use App\Models\Check;
use App\Models\Document;
use App\Models\Truck;

/**
 * Seul point d'accès aux fichiers (stockés hors du dossier public).
 * Chaque requête vérifie la connexion ET l'autorisation sur la ressource.
 */
final class FileController extends Controller
{
    /** Photo de contrôle : personnel, ou conducteur auteur. */
    public function photo(): void
    {
        $p = DB::one('SELECT * FROM truck_photos WHERE id = ?', [$this->getInt('id') ?? 0]);
        if (!$p) {
            $this->notFound('Cette photo');
        }
        if (Auth::is('driver') && (int)$p['driver_id'] !== Auth::id()) {
            $this->forbidden();
        }
        $this->stream($p['file_path'], $p['mime_type'], 'controle-' . $p['check_type'] . '-' . date('Ymd-Hi', strtotime($p['taken_at'])) . '-' . $p['id'] . '.jpg');
    }

    /** Photo de signalement : personnel, ou conducteur auteur. */
    public function incident(): void
    {
        $p = DB::one('SELECT p.*, i.driver_id FROM incident_photos p JOIN incidents i ON i.id = p.incident_id WHERE p.id = ?', [$this->getInt('id') ?? 0]);
        if (!$p) {
            $this->notFound('Cette photo');
        }
        if (Auth::is('driver') && (int)$p['driver_id'] !== Auth::id()) {
            $this->forbidden();
        }
        $this->stream($p['file_path'], $p['mime_type'], 'signalement-' . $p['incident_id'] . '-' . $p['id'] . '.jpg');
    }

    /** Photo du camion (fiche) : personnel, ou conducteur affecté à ce camion. */
    public function truck(): void
    {
        $t = $this->authorizedTruck($this->getInt('id'));
        if (!$t['photo_path']) {
            $this->notFound('Cette photo');
        }
        $this->stream($t['photo_path'], 'image/jpeg', 'camion-' . $t['id'] . '.jpg');
    }

    /** Document : personnel, ou conducteur du camion concerné (affectation en cours). */
    public function document(): void
    {
        $d = Document::find($this->getInt('id') ?? 0);
        if (!$d) {
            $this->notFound('Ce document');
        }
        if (Auth::is('driver')) {
            $truck = Truck::forDriver((int)Auth::id());
            if (!$truck || (int)$truck['id'] !== (int)$d['truck_id']) {
                Log::add('file.denied', 'document', (int)$d['id']);
                $this->forbidden();
            }
        }
        $ext = pathinfo($d['file_path'], PATHINFO_EXTENSION);
        $base = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $d['plate'] . '_' . $d['name']);
        $this->stream($d['file_path'], $d['mime_type'], $base . '.' . $ext);
    }

    /** Feuille de tournée : accessible uniquement avec le lien secret (jeton de 32 caractères). */
    public function sheet(): void
    {
        $t = is_string($_GET['t'] ?? null) && preg_match('/^[a-f0-9]{32}$/', $_GET['t'])
            ? DB::one("SELECT t.*, d.status FROM dispatch_tours t JOIN dispatches d ON d.id = t.dispatch_id WHERE t.token = ? AND d.status <> 'draft'", [$_GET['t']])
            : null;
        if (!$t || !$t['file_path']) {
            $this->notFound('Cette feuille de tournée');
        }
        $this->stream($t['file_path'], (string)$t['mime_type'], 'tournee-' . $t['client'] . '-' . $t['city'] . '.' . pathinfo($t['file_path'], PATHINFO_EXTENSION));
    }

    /** Toutes les photos d'un contrôle en ZIP (personnel). */
    public function checkZip(): void
    {
        $check = Check::find($this->getInt('id') ?? 0);
        if (!$check) {
            $this->notFound('Ce contrôle');
        }
        if (!class_exists(\ZipArchive::class)) {
            \App\Helpers\View::error(501, "ZIP indisponible", "L'extension PHP zip n'est pas activée sur ce serveur.");
        }
        $photos = Check::photos((int)$check['id']);
        $tmp = tempnam(sys_get_temp_dir(), 'zt');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        foreach ($photos as $i => $p) {
            $abs = Upload::absolute($p['file_path']);
            if ($abs) {
                $zip->addFile($abs, sprintf('%s_%s_%s_%02d.jpg', $check['plate'], $check['check_date'], $check['check_type'], $i + 1));
            }
        }
        $zip->close();
        session_write_close();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="photos_' . preg_replace('/[^A-Za-z0-9\-]/', '', $check['plate']) . '_' . $check['check_date'] . '_' . $check['check_type'] . '.zip"');
        header('Content-Length: ' . filesize($tmp));
        header('X-Content-Type-Options: nosniff');
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    private function stream(string $relPath, string $mime, string $downloadName): void
    {
        $abs = Upload::absolute($relPath);
        if (!$abs) {
            $this->notFound('Ce fichier');
        }
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        if (!in_array($mime, $allowed, true)) {
            $mime = 'application/octet-stream';
        }
        $download = !empty($_GET['dl']);
        $safe = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $downloadName);
        session_write_close();
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($abs));
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $safe . '"');
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
        header('Cache-Control: private, max-age=3600');
        readfile($abs);
        exit;
    }
}
