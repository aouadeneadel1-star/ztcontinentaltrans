<?php
declare(strict_types=1);

namespace App\Helpers;

/**
 * Réception sécurisée des fichiers :
 *  - vérification du vrai type MIME (finfo), jamais l'extension envoyée
 *  - limites de taille et de formats
 *  - nom de fichier aléatoire, stockage hors du dossier public
 *  - photos recompressées en JPEG via GD (supprime EXIF/GPS et contenu piégé)
 */
final class Upload
{
    public const IMAGE_MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    public const DOC_MIMES   = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /** Normalise $_FILES['x'] (simple ou multiple) en liste de fichiers valides reçus. */
    public static function collect(string $field): array
    {
        if (empty($_FILES[$field])) {
            return [];
        }
        $f = $_FILES[$field];
        $out = [];
        if (is_array($f['name'])) {
            foreach ($f['name'] as $i => $name) {
                $out[] = ['name' => $name, 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
            }
        } else {
            $out[] = $f;
        }
        return array_values(array_filter($out, static fn($x) => $x['error'] !== UPLOAD_ERR_NO_FILE && $x['name'] !== ''));
    }

    private static function errorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Le fichier est trop volumineux.',
            UPLOAD_ERR_PARTIAL => 'Le fichier n\'a été envoyé que partiellement. Réessayez.',
            default => 'Erreur lors de l\'envoi du fichier.',
        };
    }

    /**
     * Enregistre une photo (recompressée en JPEG).
     * @return array{path:string,mime:string,size:int,original:string}
     * @throws \RuntimeException message affichable à l'utilisateur
     */
    public static function savePhoto(array $file, string $subdir): array
    {
        self::basicChecks($file, (int)config('max_photo_bytes'));
        $mime = self::detectMime($file['tmp_name']);
        if (!isset(self::IMAGE_MIMES[$mime])) {
            throw new \RuntimeException('Format non autorisé pour « ' . self::safeName($file['name']) . ' » (JPEG, PNG ou WebP uniquement).');
        }
        if (!function_exists('imagecreatefromstring')) {
            throw new \RuntimeException('L\'extension GD de PHP est requise pour traiter les photos.');
        }
        $data = @file_get_contents($file['tmp_name']);
        $img  = $data !== false ? @imagecreatefromstring($data) : false;
        if (!$img) {
            throw new \RuntimeException('« ' . self::safeName($file['name']) . ' » n\'est pas une image valide.');
        }
        // Orientation EXIF (photos de smartphone)
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($file['tmp_name']);
            $o = (int)($exif['Orientation'] ?? 1);
            $angle = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
            if ($angle !== 0) {
                $rot = imagerotate($img, $angle, 0);
                if ($rot) {
                    $img = $rot;
                }
            }
        }
        // Redimensionnement
        $max = (int)config('photo_max_dimension', 1920);
        $w = imagesx($img);
        $h = imagesy($img);
        if (max($w, $h) > $max) {
            $ratio = $max / max($w, $h);
            $nw = max(1, (int)round($w * $ratio));
            $nh = max(1, (int)round($h * $ratio));
            $dst = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            $img = $dst;
        } else {
            // Fond blanc pour les PNG/WebP transparents
            $flat = imagecreatetruecolor($w, $h);
            imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
            imagecopy($flat, $img, 0, 0, 0, 0, $w, $h);
            $img = $flat;
        }
        $rel = self::targetPath($subdir, 'jpg');
        $abs = STORAGE_PATH . '/' . $rel;
        if (!imagejpeg($img, $abs, 85)) {
            throw new \RuntimeException('Impossible d\'enregistrer la photo.');
        }
        @chmod($abs, 0640);
        return ['path' => $rel, 'mime' => 'image/jpeg', 'size' => (int)filesize($abs), 'original' => self::safeName($file['name'])];
    }

    /**
     * Enregistre un document (PDF ou image) sans le modifier.
     * @return array{path:string,mime:string,size:int,original:string}
     */
    public static function saveDocument(array $file, string $subdir): array
    {
        self::basicChecks($file, (int)config('max_document_bytes'));
        $mime = self::detectMime($file['tmp_name']);
        if (!isset(self::DOC_MIMES[$mime])) {
            throw new \RuntimeException('Format non autorisé (PDF, JPEG, PNG ou WebP uniquement).');
        }
        if ($mime === 'application/pdf') {
            $head = (string)file_get_contents($file['tmp_name'], false, null, 0, 5);
            if ($head !== '%PDF-') {
                throw new \RuntimeException('Ce fichier n\'est pas un PDF valide.');
            }
        } elseif (@getimagesize($file['tmp_name']) === false) {
            throw new \RuntimeException('Ce fichier n\'est pas une image valide.');
        }
        $rel = self::targetPath($subdir, self::DOC_MIMES[$mime]);
        $abs = STORAGE_PATH . '/' . $rel;
        if (!move_uploaded_file($file['tmp_name'], $abs)) {
            throw new \RuntimeException('Impossible d\'enregistrer le document.');
        }
        @chmod($abs, 0640);
        return ['path' => $rel, 'mime' => $mime, 'size' => (int)filesize($abs), 'original' => self::safeName($file['name'])];
    }

    public static function delete(?string $relPath): void
    {
        if (!$relPath) {
            return;
        }
        $abs = self::absolute($relPath);
        if ($abs && is_file($abs)) {
            @unlink($abs);
        }
    }

    /** Chemin absolu sûr (jamais en dehors de /storage). */
    public static function absolute(string $relPath): ?string
    {
        if (str_contains($relPath, '..') || str_contains($relPath, "\0")) {
            return null;
        }
        $abs  = STORAGE_PATH . '/' . ltrim($relPath, '/');
        $real = realpath($abs);
        $root = realpath(STORAGE_PATH);
        if ($real === false || $root === false || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
            return null;
        }
        return $real;
    }

    private static function basicChecks(array $file, int $maxBytes): void
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException(self::errorMessage((int)$file['error']));
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new \RuntimeException('Fichier invalide.');
        }
        if ($file['size'] > $maxBytes || $file['size'] <= 0) {
            throw new \RuntimeException('« ' . self::safeName($file['name']) . ' » dépasse la taille maximale de ' . fsize($maxBytes) . '.');
        }
    }

    private static function detectMime(string $tmp): string
    {
        $f = new \finfo(FILEINFO_MIME_TYPE);
        return (string)$f->file($tmp);
    }

    private static function targetPath(string $subdir, string $ext): string
    {
        $dir = STORAGE_PATH . '/' . $subdir . '/' . date('Y/m');
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException('Dossier de stockage inaccessible.');
        }
        return $subdir . '/' . date('Y/m') . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
    }

    private static function safeName(string $name): string
    {
        return mb_substr(preg_replace('/[^\p{L}\p{N}\.\-_ ()]/u', '_', basename($name)) ?? 'fichier', 0, 150);
    }
}
