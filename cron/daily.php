<?php
declare(strict_types=1);

/**
 * Tâche quotidienne (CLI uniquement) :
 *  - alertes d'expiration des documents (assurance, contrôle technique, etc.)
 *  - alertes d'entretien à venir
 *  - rappel des contrôles quotidiens manquants (option --checks)
 *  - sauvegarde de la base (mysqldump) dans storage/backups, conservation 14 jours
 *
 * Exemple crontab (tous les jours à 7h00) :
 *   0 7 * * * php /chemin/vers/projet/cron/daily.php
 * Rappel contrôles manquants (10h15 et 19h15) :
 *   15 10 * * * php /chemin/vers/projet/cron/daily.php --checks=morning
 *   15 19 * * * php /chemin/vers/projet/cron/daily.php --checks=evening
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI uniquement.');
}
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\DB;
use App\Helpers\Notifier;
use App\Models\Check;
use App\Models\Document;
use App\Models\Truck;

$opts = getopt('', ['checks::', 'no-backup']);

// --- Rappel des contrôles manquants ---
if (!empty($opts['checks'])) {
    $type = $opts['checks'] === 'evening' ? 'evening' : 'morning';
    $missing = Check::missingToday($type);
    if ($missing) {
        $list = implode("\n", array_map(static fn($m) => $m['driver_name'] . ' – ' . $m['plate'], $missing));
        Notifier::toManagers('check_missing', 'check_missing:' . $type . ':' . date('Y-m-d'),
            'Contrôle ' . ($type === 'morning' ? 'du matin' : 'du soir') . ' manquant (' . count($missing) . ')',
            'Contrôles manquants aujourd\'hui', ['Contrôle' => label('check_type', $type), 'Date' => fdate(date('Y-m-d')), 'Conducteurs' => $list],
            absolute_url('check/index', ['type' => $type, 'from' => date('Y-m-d'), 'to' => date('Y-m-d')]), 'orange');
    }
    echo count($missing) . " contrôle(s) manquant(s).\n";
    exit(0);
}

// --- Documents : alertes aux seuils configurés (30/15/7 jours) et à l'expiration ---
$thresholds = array_map('intval', (array)config('expiry_alert_days', [30, 15, 7]));
rsort($thresholds);
$sent = 0;
foreach (Document::expiring(max($thresholds)) as $d) {
    $days = (int)$d['days_left'];
    if ($days < 0) {
        $bucket = 'expired';
    } else {
        $bucket = null;
        foreach ($thresholds as $t) {       // plus petit seuil atteint
            if ($days <= $t) {
                $bucket = (string)$t;
            }
        }
        if ($bucket === null) {
            continue;
        }
    }
    // Clé unique : même document + même échéance + même palier = un seul envoi
    $key = 'doc_expiry:' . $d['id'] . ':' . $d['expiry_date'] . ':' . $bucket;
    $subject = ($days < 0 ? 'Document expiré' : 'Document bientôt expiré') . ' – ' . $d['plate'] . ' – ' . label('doc_category', $d['category']);
    Notifier::toManagers('doc_expiry', $key, $subject, $days < 0 ? 'Document expiré' : 'Document arrivant à expiration',
        ['Camion' => $d['plate'], 'Document' => $d['name'] . ' (' . label('doc_category', $d['category']) . ')', 'Échéance' => fdate($d['expiry_date']),
         'Situation' => $days < 0 ? 'Expiré depuis ' . abs($days) . ' jour(s)' : 'Expire dans ' . $days . ' jour(s)'],
        absolute_url('truck/show', ['id' => $d['truck_id'], 'tab' => 'documents']), $days < 0 || $days <= 7 ? 'red' : 'orange');
    $sent++;
}
echo "Documents : $sent alerte(s) traitée(s).\n";

// --- Locations : fin de contrat (paliers 60/30/15/7 jours, puis contrat terminé) ---
$rentalThresholds = array_map('intval', (array)config('rental_alert_days', [60, 30, 15, 7]));
rsort($rentalThresholds);
$rc = 0;
foreach (Truck::rentalsEnding(max($rentalThresholds)) as $r) {
    $left = (int)$r['days_left'];
    if ($left < 0) {
        $bucket = 'ended';
    } else {
        $bucket = null;
        foreach ($rentalThresholds as $t) {
            if ($left <= $t) {
                $bucket = (string)$t;
            }
        }
        if ($bucket === null) {
            continue;
        }
    }
    // Un renouvellement change la date de fin : nouvelle clé, donc nouvelle série d'alertes
    Notifier::toManagers('rental_end', 'rental_end:' . $r['id'] . ':' . $r['rental_end_date'] . ':' . $bucket,
        ($left < 0 ? 'Contrat de location terminé' : 'Fin de contrat de location à prévoir') . ' – ' . $r['plate'],
        $left < 0 ? 'Contrat de location terminé' : 'Contrat de location bientôt terminé',
        ['Camion' => $r['plate'], 'Loueur' => (string)($r['rental_company'] ?? '—'), 'N° de contrat' => (string)($r['rental_contract_no'] ?? '—'), 'Fin de contrat' => fdate($r['rental_end_date']),
         'Situation' => $left < 0 ? 'Terminé depuis ' . abs($left) . ' jour(s) : renouveler ou restituer le véhicule' : 'Fin dans ' . $left . ' jour(s) : renouveler ou restituer le véhicule'],
        absolute_url('truck/show', ['id' => $r['id']]), $left <= 7 ? 'red' : 'orange');
    $rc++;
}
echo "Locations : $rc alerte(s) traitée(s).\n";

// --- Entretiens à venir ---
$days = (int)config('service_alert_days', 15);
$rows = DB::all('SELECT id, plate, next_service_date, DATEDIFF(next_service_date, CURDATE()) AS d FROM trucks WHERE next_service_date IS NOT NULL AND next_service_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)', [$days]);
foreach ($rows as $t) {
    Notifier::toManagers('service_due', 'service_due:' . $t['id'] . ':' . $t['next_service_date'],
        ((int)$t['d'] < 0 ? 'Entretien en retard' : 'Entretien à prévoir') . ' – ' . $t['plate'],
        (int)$t['d'] < 0 ? 'Entretien en retard' : 'Entretien à prévoir',
        ['Camion' => $t['plate'], 'Date prévue' => fdate($t['next_service_date'])],
        absolute_url('truck/show', ['id' => $t['id'], 'tab' => 'maintenance']), (int)$t['d'] < 0 ? 'red' : 'orange');
}
echo count($rows) . " entretien(s) à signaler.\n";

// --- Sauvegarde de la base ---
if (!isset($opts['no-backup'])) {
    $c = require ROOT_PATH . '/config/database.php';
    $dir = STORAGE_PATH . '/backups';
    @mkdir($dir, 0750, true);
    $file = $dir . '/sauvegarde-' . date('Y-m-d') . '.sql';
    $cnf = tempnam(sys_get_temp_dir(), 'ztcnf');
    file_put_contents($cnf, "[client]\nuser=\"{$c['user']}\"\npassword=\"" . addcslashes($c['password'], '"\\') . "\"\nhost=\"{$c['host']}\"\nport={$c['port']}\n");
    chmod($cnf, 0600);
    $cmd = sprintf('mysqldump --defaults-extra-file=%s --single-transaction --routines %s > %s 2>/dev/null', escapeshellarg($cnf), escapeshellarg($c['name']), escapeshellarg($file));
    exec($cmd, $o, $rc);
    @unlink($cnf);
    if ($rc === 0 && filesize($file) > 0) {
        echo "Sauvegarde : $file\n";
        foreach (glob($dir . '/sauvegarde-*.sql') ?: [] as $old) {
            if (filemtime($old) < time() - 14 * 86400) {
                @unlink($old);
            }
        }
    } else {
        @unlink($file);
        fwrite(STDERR, "Sauvegarde impossible (mysqldump indisponible ?).\n");
    }
}
