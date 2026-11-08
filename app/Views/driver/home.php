<?php use App\Middleware\Auth; $u = Auth::user(); $dis = $truck ? '' : ' disabled'; ?>
<div class="hello">
  <h1>Bonjour <?= e($u['first_name']) ?></h1>
  <span class="muted"><?= e(ucfirst(today_fr())) ?></span>
</div>

<?php if (!$truck): ?>
  <div class="alert alert-warning">Aucun camion ne vous est affecté. Contactez votre responsable.</div>
<?php else: ?>
  <?php if ($safety): ?>
    <div class="danger-banner">Véhicule à ne pas utiliser avant vérification<small>Un problème de sécurité est en cours de traitement sur ce camion.</small></div>
  <?php endif; ?>
  <div class="card truck-line">
    <span class="plate"><?= e($truck['plate']) ?></span>
    <span><?= e($truck['brand'] . ' ' . $truck['model']) ?></span>
    <span class="meta"><?= e(fkm((int)$truck['mileage'])) ?></span>
  </div>
<?php endif; ?>

<div class="actions-grid">
  <a class="action-tile<?= $morning ? ' done' : '' ?><?= $dis ?>" href="<?= e($morning ? url('check/show', ['id' => $morning['id']]) : url('check/form', ['type' => 'morning'])) ?>">
    Contrôle du matin<span class="state"><?= $morning ? 'Fait à ' . e(ftime($morning['checked_at'])) : 'À faire' ?></span></a>
  <a class="action-tile<?= $evening ? ' done' : '' ?><?= $dis ?>" href="<?= e($evening ? url('check/show', ['id' => $evening['id']]) : url('check/form', ['type' => 'evening'])) ?>">
    Contrôle du soir<span class="state"><?= $evening ? 'Fait à ' . e(ftime($evening['checked_at'])) : 'À faire' ?></span></a>
  <a class="action-tile<?= $dis ?>" href="<?= e(url('driver/truck')) ?>">Documents du camion</a>
  <a class="action-tile problem<?= $dis ?>" href="<?= e(url('incident/form')) ?>">Signaler un problème</a>
</div>

<?php if (!empty($dispatches)): ?>
<section class="card"><h2>Mes tournées</h2>
  <ul class="list">
    <?php foreach ($dispatches as $x): ?>
      <li><span><strong><?= e(fdate_long($x['work_date'])) ?></strong><br>
        <span class="muted small">Camion <?= e($x['plate']) ?><?= ($x['helper1_name'] || $x['helper2_name']) ? ' · avec ' . e(implode(' et ', array_filter([$x['helper1_name'], $x['helper2_name']]))) : '' ?></span><br>
        <?php foreach ($x['tours'] as $t): ?><?= e($t['client'] . ' – ' . $t['city']) ?><?= $t['file_path'] ? ' · <a class="btn btn-sm" href="' . e(url('file/sheet', ['t' => $t['token'], 'dl' => 1])) . '">Télécharger</a>' : '' ?><br><?php endforeach; ?></span></li>
    <?php endforeach; ?>
  </ul></section>
<?php endif; ?>

<?php foreach ($expiring as $d): ?>
  <div class="alert alert-warning"><?= e(label('doc_category', $d['category'])) ?> : <?= $d['days_left'] < 0 ? 'expiré' : 'expire dans ' . (int)$d['days_left'] . ' jours' ?> (<?= e(fdate($d['expiry_date'])) ?>)</div>
<?php endforeach; ?>

<?php if ($incidents): ?>
<section class="card"><h2>Signalements en cours</h2>
  <ul class="list">
    <?php foreach ($incidents as $i): ?>
      <li><a href="<?= e(url('incident/show', ['id' => $i['id']])) ?>"><?= e(label('incident_category', $i['category'])) ?></a> <?= incident_status_badge($i['status']) ?></li>
    <?php endforeach; ?>
  </ul></section>
<?php endif; ?>

<?php if ($checks): ?>
<section class="card"><h2>Derniers contrôles</h2>
  <ul class="list">
    <?php foreach ($checks as $c): ?>
      <li><a href="<?= e(url('check/show', ['id' => $c['id']])) ?>"><?= e(fdate($c['check_date'])) ?> · <?= e(label('check_type', $c['check_type'])) ?></a>
        <?= $c['has_anomaly'] ? badge('Anomalie', 'orange') : '<span class="muted small">' . (int)$c['photo_count'] . ' photo(s)</span>' ?></li>
    <?php endforeach; ?>
  </ul></section>
<?php endif; ?>
