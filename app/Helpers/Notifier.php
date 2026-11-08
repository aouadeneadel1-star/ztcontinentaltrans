<?php
declare(strict_types=1);

namespace App\Helpers;

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Notifications e-mail (PHPMailer).
 * Anti-doublon : chaque envoi a une clé d'événement unique (table notifications, UNIQUE event_key).
 */
final class Notifier
{
    private static bool $loaded = false;

    private static function load(): void
    {
        if (!self::$loaded) {
            $dir = APP_PATH . '/Lib/PHPMailer/';
            require_once $dir . 'Exception.php';
            require_once $dir . 'PHPMailer.php';
            require_once $dir . 'SMTP.php';
            self::$loaded = true;
        }
    }

    /** Destinataires : administrateurs et responsables actifs. */
    public static function managers(): array
    {
        return DB::all("SELECT id, first_name, last_name, email FROM users WHERE role IN ('admin','manager') AND is_active = 1");
    }

    /**
     * Envoie (une seule fois) un message à chaque responsable.
     * @param string $eventKey identifiant unique de l'événement, ex. "incident_new:12"
     */
    public static function toManagers(string $type, string $eventKey, string $subject, string $title, array $rows, ?string $linkUrl = null, string $tone = 'blue'): void
    {
        foreach (self::managers() as $m) {
            self::send($type, $eventKey . ':u' . $m['id'], (int)$m['id'], $m['email'], $subject, $title, $rows, $linkUrl, $tone);
        }
    }

    public static function send(string $type, string $eventKey, ?int $userId, string $to, string $subject, string $title, array $rows, ?string $linkUrl, string $tone = 'blue'): bool
    {
        $html = self::template($title, $rows, $linkUrl, $tone);
        // Réservation de la clé : si elle existe déjà, l'événement a déjà été notifié.
        $st = DB::run(
            'INSERT IGNORE INTO notifications (type, event_key, user_id, recipient_email, subject, body, status) VALUES (?,?,?,?,?,?,?)',
            [$type, $eventKey, $userId, $to, mb_substr($subject, 0, 255), $html, 'pending']
        );
        if ($st->rowCount() === 0) {
            return false;
        }
        $id = (int)DB::pdo()->lastInsertId();

        $cfg = require ROOT_PATH . '/config/mail.php';
        if (empty($cfg['enabled'])) {
            DB::update('notifications', $id, ['status' => 'skipped', 'error_message' => 'Envoi e-mail désactivé (config/mail.php)']);
            return false;
        }
        try {
            self::load();
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $cfg['host'];
            $mail->Port       = (int)$cfg['port'];
            $mail->SMTPAuth   = $cfg['username'] !== '';
            $mail->Username   = $cfg['username'];
            $mail->Password   = $cfg['password'];
            $mail->Timeout    = (int)($cfg['timeout'] ?? 8);
            $mail->CharSet    = 'UTF-8';
            if ($cfg['encryption'] === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($cfg['encryption'] === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }
            $mail->setFrom($cfg['from_email'], $cfg['from_name']);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html;
            $mail->AltBody = trim(strip_tags(str_replace(['</tr>', '<br>', '</p>'], "\n", $html)));
            $mail->send();
            DB::update('notifications', $id, ['status' => 'sent', 'sent_at' => date('Y-m-d H:i:s')]);
            return true;
        } catch (MailException|\Throwable $e) {
            DB::update('notifications', $id, ['status' => 'failed', 'error_message' => mb_substr($e->getMessage(), 0, 500)]);
            error_log('Mail non envoyé (' . $eventKey . ') : ' . $e->getMessage());
            return false;
        }
    }

    /** Gabarit HTML sobre aux couleurs ZT. */
    private static function template(string $title, array $rows, ?string $linkUrl, string $tone): string
    {
        $color = ['red' => '#E30613', 'orange' => '#E8820C', 'blue' => '#0B2A5E', 'green' => '#1E8E3E'][$tone] ?? '#0B2A5E';
        $h  = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:auto;border:1px solid #dfe3ea;border-radius:8px;overflow:hidden">';
        $h .= '<div style="background:#0B2A5E;color:#fff;padding:14px 18px;font-weight:bold">' . e(config('name')) . '</div>';
        $h .= '<div style="border-top:4px solid ' . $color . ';padding:18px">';
        $h .= '<h2 style="margin:0 0 14px;color:' . $color . ';font-size:18px">' . e($title) . '</h2>';
        $h .= '<table style="width:100%;border-collapse:collapse;font-size:14px">';
        foreach ($rows as $k => $v) {
            $h .= '<tr><td style="padding:6px 8px;color:#5b6678;width:38%;vertical-align:top">' . e($k) . '</td><td style="padding:6px 8px;color:#10203f;font-weight:bold">' . nl2br(e($v)) . '</td></tr>';
        }
        $h .= '</table>';
        if ($linkUrl) {
            $h .= '<p style="margin:20px 0 0"><a href="' . e($linkUrl) . '" style="background:#0B2A5E;color:#fff;text-decoration:none;padding:10px 18px;border-radius:6px;display:inline-block">Ouvrir dans l\'application</a></p>';
        }
        $h .= '</div></div>';
        return $h;
    }
}
