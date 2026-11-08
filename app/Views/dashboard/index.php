<?php
use App\Middleware\Auth;
$today = date('Y-m-d');
// « À surveiller » : véhicules à traiter + documents à renouveler, dans une seule liste
$watch = [];
foreach ($needs as $n) {
    $why = [];
    if ($n['safety_incidents']) $why[] = 'risque sécurité ouvert';
    if ($n['urgent_incidents']) $why[] = $n['urgent_incidents'] . ' signalement(s) urgent(s)';
    if ($n['next_service_date'] && $n['next_service_date'] <= date('Y-m-d', strtotime("+$serviceDays days")))
        $why[] = $n['next_service_date'] < $today ? 'entretien en retard (' . fdate($n['next_service_date']) . ')' : 'entretien le ' . fdate($n['next_service_date']);
    if (!$why) $why[] = mb_strtolower(label('truck_status', $n['status']));
    $watch[] = ['plate' => $n['plate'], 'url' => url('truck/show', ['id' => $n['id']]), 'text' => ucfirst(implode(', ', $why)), 'tone' => ($n['safety_incidents'] || $n['status'] === 'immobilized') ? 'red' : 'orange'];
}
foreach ($expiring as $d) {
    $watch[] = ['plate' => $d['plate'], 'url' => url('truck/show', ['id' => $d['truck_id'], 'tab' => 'documents']),
        'text' => label('doc_category', $d['category']) . ($d['days_left'] < 0 ? ' expiré depuis ' . abs((int)$d['days_left']) . ' j' : ' : expire dans ' . (int)$d['days_left'] . ' j'),
        'tone' => $d['days_left'] < 0 ? 'red' : 'orange'];
}
foreach ($rentals as $r) {
    $watch[] = ['plate' => $r['plate'], 'url' => url('truck/show', ['id' => $r['id']]),
        'text' => 'Location' . ($r['rental_company'] ? ' ' . $r['rental_company'] : '') . ($r['days_left'] < 0 ? ' : contrat terminé depuis ' . abs((int)$r['days_left']) . ' j' : ' : fin du contrat dans ' . (int)$r['days_left'] . ' j'),
        'tone' => $r['days_left'] <= 7 ? 'red' : 'orange'];
}
?>
<div class="page-head">
  <div><h1>Tableau de bord</h1><span class="muted"><?= e(ucfirst(today_fr())) ?></span></div>
  <form class="actions" method="get" action="<?= e(url('truck/index')) ?>" role="search">
    <input type="search" name="q" placeholder="Immatriculation" aria-label="Rechercher par immatriculation" style="width:190px">
    <button class="btn btn-light" type="submit">Rechercher</button>
  </form>
</div>

<?php foreach (['morning' => 'du matin', 'evening' => 'du soir'] as $k => $txt): if ($missing[$k]): ?>
  <div class="alert alert-warning"><strong>Contrôle <?= $txt ?> manquant :</strong>
    <?= e(implode(', ', array_map(static fn($m) => $m['driver_name'] . ' (' . $m['plate'] . ')', $missing[$k]))) ?></div>
<?php endif; endforeach; ?>

<div class="figures f5">
  <a class="figure" href="<?= e(url('truck/index')) ?>"><div class="label">Camions</div><div class="value"><?= $stats['total'] ?></div></a>
  <a class="figure" href="<?= e(url('truck/index', ['status' => 'available'])) ?>"><div class="label">Disponibles</div><div class="value"><?= $stats['available'] ?></div></a>
  <a class="figure" href="<?= e(url('truck/index', ['status' => 'in_service'])) ?>"><div class="label">En circulation</div><div class="value"><?= $stats['in_service'] ?></div></a>
  <a class="figure<?= $stats['maintenance'] ? ' warn' : '' ?>" href="<?= e(url('truck/index', ['status' => 'maintenance'])) ?>"><div class="label">En maintenance</div><div class="value"><?= $stats['maintenance'] ?></div></a>
  <a class="figure" href="<?= e(url('user/index', ['role' => 'driver'])) ?>"><div class="label">Conducteurs</div><div class="value"><?= $stats['drivers'] ?></div></a>
</div>

<div class="figures f3">
  <a class="figure" href="<?= e(url('check/index', ['type' => 'morning', 'from' => $today, 'to' => $today])) ?>"><div class="label">Contrôles du matin</div><div class="value"><?= $stats['morning'] ?> <small>sur <?= $stats['expected'] ?></small></div></a>
  <a class="figure" href="<?= e(url('check/index', ['type' => 'evening', 'from' => $today, 'to' => $today])) ?>"><div class="label">Contrôles du soir</div><div class="value"><?= $stats['evening'] ?> <small>sur <?= $stats['expected'] ?></small></div></a>
  <a class="figure<?= $stats['new_inc'] ? ' alert' : '' ?>" href="<?= e(url('incident/index', ['status' => 'new'])) ?>"><div class="label">Signalements non traités</div><div class="value"><?= $stats['new_inc'] ?></div></a>
</div>

<div class="grid grid-2">
  <section class="card flush">
    <h2>Derniers signalements</h2>
    <ul class="rows<?= $incidents ? '' : ' empty-row' ?>">
      <?php foreach ($incidents as $i): ?>
        <li><div class="main-text"><a href="<?= e(url('incident/show', ['id' => $i['id']])) ?>"><strong><?= e($i['plate']) ?></strong> · <?= e(label('incident_category', $i['category'])) ?></a><br>
          <span class="muted small"><?= e($i['driver_name']) ?> · <?= e(fdatetime($i['created_at'])) ?></span></div>
          <div class="side"><?= $i['priority'] === 'critical' || $i['priority'] === 'high' ? priority_badge($i['priority']) . '<br>' : '' ?><?= incident_status_badge($i['status']) ?></div></li>
      <?php endforeach; if (!$incidents): ?><li>Aucun signalement.</li><?php endif; ?>
    </ul>
    <?php if ($incidents): ?><div style="padding:10px 16px;border-top:1px solid #eef0f4"><a href="<?= e(url('incident/index')) ?>">Tous les signalements</a></div><?php endif; ?>
  </section>

  <section class="card flush">
    <h2>À surveiller</h2>
    <ul class="rows<?= $watch ? '' : ' empty-row' ?>">
      <?php foreach ($watch as $w): ?>
        <li><div class="main-text"><a href="<?= e($w['url']) ?>"><strong><?= e($w['plate']) ?></strong></a><br><span class="muted small"><?= e($w['text']) ?></span></div>
          <div class="side"><?= badge($w['tone'] === 'red' ? 'Urgent' : 'À prévoir', $w['tone']) ?></div></li>
      <?php endforeach; if (!$watch): ?><li>Rien à signaler.</li><?php endif; ?>
    </ul>
  </section>
</div>

<section class="card flush" style="margin-top:16px">
  <h2>Derniers contrôles</h2>
  <ul class="rows<?= $checks ? '' : ' empty-row' ?>">
    <?php foreach ($checks as $c): ?>
      <li><div class="main-text"><a href="<?= e(url('check/show', ['id' => $c['id']])) ?>"><strong><?= e($c['plate']) ?></strong> · <?= e($c['driver_name']) ?></a><br>
        <span class="muted small"><?= e(fdatetime($c['checked_at'])) ?> · <?= (int)$c['photo_count'] ?> photo(s)</span></div>
        <div class="side"><?= e(label('check_type', $c['check_type'])) ?><br><?= $c['has_anomaly'] ? badge('Anomalie', 'orange') : badge('RAS', 'green') ?></div></li>
    <?php endforeach; if (!$checks): ?><li>Aucun contrôle enregistré.</li><?php endif; ?>
  </ul>
</section>
