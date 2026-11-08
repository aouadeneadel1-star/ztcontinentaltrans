<div class="page-head"><h1>Photos et contrôles</h1>
  <div class="actions"><a class="btn btn-light" href="<?= e(url('history/index', array_filter(['type' => 'checks', 'truck_id' => $f['truck_id'], 'driver_id' => $f['driver_id'], 'from' => $f['from'], 'to' => $f['to'], 'type_check' => $f['type']]))) ?>">Vue tableau / export</a></div></div>
<form class="card filters" method="get" action="<?= e(url('check/index')) ?>">
  <div class="field"><label for="q">Immatriculation</label><input id="q" type="search" name="q" value="<?= e($f['q']) ?>"></div>
  <div class="field"><label for="truck_id">Camion</label><select id="truck_id" name="truck_id"><option value="">Tous</option><?php foreach ($trucks as $t): ?><option value="<?= (int)$t['id'] ?>" <?= (string)$f['truck_id'] === (string)$t['id'] ? 'selected' : '' ?>><?= e($t['plate']) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="driver_id">Conducteur</label><select id="driver_id" name="driver_id"><option value="">Tous</option><?php foreach ($drivers as $d): ?><option value="<?= (int)$d['id'] ?>" <?= (string)$f['driver_id'] === (string)$d['id'] ? 'selected' : '' ?>><?= e($d['first_name'] . ' ' . $d['last_name']) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="type">Contrôle</label><select id="type" name="type"><option value="">Matin et soir</option><?php foreach (labels('check_type') as $k => $l): ?><option value="<?= e($k) ?>" <?= $f['type'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="from">Du</label><input id="from" type="date" name="from" value="<?= e($f['from']) ?>"></div>
  <div class="field"><label for="to">Au</label><input id="to" type="date" name="to" value="<?= e($f['to']) ?>"></div>
  <div class="field"><label class="check" style="margin-top:22px"><input type="checkbox" name="anomaly" value="1" <?= $f['anomaly'] ? 'checked' : '' ?>> Anomalies seulement</label></div>
  <div class="actions"><button class="btn" type="submit">Filtrer</button><a class="btn btn-light" href="<?= e(url('check/index')) ?>">Réinitialiser</a></div>
</form>
<div class="check-cards">
<?php foreach ($checks as $c): ?>
  <a class="check-card" href="<?= e(url('check/show', ['id' => $c['id']])) ?>">
    <div class="thumb"><?php if ($c['first_photo_id']): ?><img loading="lazy" src="<?= e(url('file/photo', ['id' => $c['first_photo_id']])) ?>" alt=""><?php else: ?><?= icon('image') ?><?php endif; ?></div>
    <div class="body"><div><span class="plate"><?= e($c['plate']) ?></span> <?= badge(label('check_type', $c['check_type']), $c['check_type'] === 'morning' ? 'orange' : 'blue') ?> <?= $c['has_anomaly'] ? badge('Anomalie', 'red') : '' ?></div>
      <div><?= e($c['driver_name']) ?></div><div class="muted small"><?= e(fdatetime($c['checked_at'])) ?> · <?= (int)$c['photo_count'] ?> photo(s)</div></div></a>
<?php endforeach; ?>
</div>
<?php if (!$checks): ?><div class="card empty-state"><p>Aucun contrôle ne correspond à ces filtres.</p></div><?php endif; ?>
<div class="card flush" style="margin-top:16px"><?= $pager->render() ?: '<div class="pager"><span class="pager-info">' . count($checks) . ' résultat(s)</span></div>' ?></div>
