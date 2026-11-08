<?php
declare(strict_types=1);

namespace App\Helpers;

/** Envoi via l'API WhatsApp Business Cloud (Meta). */
final class WhatsApp
{
    public static function config(): array
    {
        return require ROOT_PATH . '/config/whatsapp.php';
    }

    public static function enabled(): bool
    {
        $c = self::config();
        return !empty($c['enabled']) && $c['token'] !== '' && $c['phone_number_id'] !== '';
    }

    /** Lien WhatsApp prêt à envoyer (repli manuel). */
    public static function link(string $phone, string $body): string
    {
        return 'https://wa.me/' . $phone . '?text=' . rawurlencode($body);
    }

    /**
     * @param string[] $params variables du modèle (mode template)
     * @return array{ok:bool,id:?string,error:?string}
     */
    public static function send(string $phone, string $body, array $params): array
    {
        $c = self::config();
        if ($c['mode'] === 'text') {
            $payload = ['messaging_product' => 'whatsapp', 'to' => $phone, 'type' => 'text',
                'text' => ['preview_url' => true, 'body' => $body]];
        } else {
            $payload = ['messaging_product' => 'whatsapp', 'to' => $phone, 'type' => 'template',
                'template' => ['name' => $c['template_name'], 'language' => ['code' => $c['template_lang']],
                    'components' => [['type' => 'body', 'parameters' => array_map(
                        static fn($p) => ['type' => 'text', 'text' => (string)$p], array_values($params))]]]];
        }
        $url = rtrim($c['api_base'], '/') . '/' . $c['api_version'] . '/' . $c['phone_number_id'] . '/messages';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $c['token'], 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => (int)$c['timeout'],
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);
        $res = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($res === false) {
            return ['ok' => false, 'id' => null, 'error' => 'Connexion impossible : ' . mb_substr($err, 0, 200)];
        }
        $json = json_decode((string)$res, true);
        $id = $json['messages'][0]['id'] ?? null;
        if ($http >= 200 && $http < 300 && $id) {
            return ['ok' => true, 'id' => (string)$id, 'error' => null];
        }
        $msg = $json['error']['message'] ?? ('Réponse inattendue (HTTP ' . $http . ')');
        return ['ok' => false, 'id' => null, 'error' => mb_substr((string)$msg, 0, 450)];
    }
}
