<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Log;
use App\Helpers\Upload;
use App\Middleware\Auth;
use App\Models\Document;

/** Gestion des documents (administrateur). La consultation passe par FileController. */
final class DocumentController extends Controller
{
    public function store(): void
    {
        $truck = $this->truckOr404($this->postInt('truck_id'));
        $back = ['id' => $truck['id']];
        $category = $this->post('category', 20);
        $name = $this->post('name', 150);
        $expiry = valid_date($_POST['expiry_date'] ?? null);

        $errors = $this->validate($category, $expiry);
        $file = Upload::collect('file')[0] ?? null;
        if (!$file) {
            $errors[] = 'Sélectionnez un fichier (PDF ou image).';
        }
        $saved = null;
        if (!$errors) {
            try {
                $saved = Upload::saveDocument($file, 'documents');
            } catch (\RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }
        if ($errors) {
            $this->fail($errors, 'truck/show', $back);
        }
        $id = \App\Helpers\DB::insert('truck_documents', [
            'truck_id'      => (int)$truck['id'],
            'name'          => $name !== '' ? $name : label('doc_category', $category),
            'category'      => $category,
            'file_path'     => $saved['path'],
            'original_name' => $saved['original'],
            'mime_type'     => $saved['mime'],
            'file_size'     => $saved['size'],
            'expiry_date'   => $expiry,
            'comment'       => $this->postText('comment', 1000) ?: null,
            'uploaded_by'   => Auth::id(),
        ]);
        Log::add('document.add', 'document', $id, $truck['plate'] . ' – ' . label('doc_category', $category));
        \App\Helpers\Session::clearOld();
        flash('success', 'Document ajouté.');
        redirect('truck/show', $back + ['tab' => 'documents']);
    }

    public function replace(): void
    {
        $doc = $this->docOr404($this->postInt('id'));
        $back = ['id' => $doc['truck_id'], 'tab' => 'documents'];
        $expiry = valid_date($_POST['expiry_date'] ?? null);
        $errors = $this->validate($doc['category'], $expiry);
        $file = Upload::collect('file')[0] ?? null;
        if (!$file) {
            $errors[] = 'Sélectionnez le nouveau fichier.';
        }
        $saved = null;
        if (!$errors) {
            try {
                $saved = Upload::saveDocument($file, 'documents');
            } catch (\RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }
        if ($errors) {
            $this->fail($errors, 'truck/show', $back);
        }
        $old = $doc['file_path'];
        \App\Helpers\DB::update('truck_documents', (int)$doc['id'], [
            'file_path'     => $saved['path'],
            'original_name' => $saved['original'],
            'mime_type'     => $saved['mime'],
            'file_size'     => $saved['size'],
            'expiry_date'   => $expiry,
            'comment'       => $this->postText('comment', 1000) ?: $doc['comment'],
            'uploaded_by'   => Auth::id(),
            'replaced_at'   => date('Y-m-d H:i:s'),
        ]);
        Upload::delete($old);
        // Une nouvelle échéance = nouvelle série d'alertes (clés de notification différentes)
        Log::add('document.replace', 'document', (int)$doc['id'], $doc['plate'] . ' – ' . $doc['name']);
        flash('success', 'Document remplacé.');
        redirect('truck/show', $back);
    }

    public function delete(): void
    {
        $doc = $this->docOr404($this->postInt('id'));
        \App\Helpers\DB::run('DELETE FROM truck_documents WHERE id = ?', [$doc['id']]);
        Upload::delete($doc['file_path']);
        Log::add('document.delete', 'document', (int)$doc['id'], $doc['plate'] . ' – ' . $doc['name']);
        flash('success', 'Document supprimé.');
        redirect('truck/show', ['id' => $doc['truck_id'], 'tab' => 'documents']);
    }

    private function docOr404(?int $id): array
    {
        $d = $id ? Document::find($id) : null;
        return $d ?? $this->notFound('Ce document');
    }

    private function validate(string $category, ?string $expiry): array
    {
        $e = [];
        if (!isset(labels('doc_category')[$category])) {
            $e[] = 'Choisissez une catégorie de document.';
        }
        if (($_POST['expiry_date'] ?? '') !== '' && $expiry === null) {
            $e[] = 'Date d\'expiration invalide.';
        }
        if (in_array($category, labels('doc_required'), true) && !$expiry && $category !== 'registration') {
            $e[] = 'Indiquez la date d\'expiration (nécessaire pour les alertes).';
        }
        return $e;
    }
}
