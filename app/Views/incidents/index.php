<div class="page-head"><h1>Signalements</h1>
  <div class="actions"><a class="btn btn-light" href="<?= e(url('history/export', array_filter(['type' => 'incidents', 'format' => 'csv', 'truck_id' => $f['truck_id'], 'driver_id' => $f['driver_id'], 'status' => $f['status'], 'from' => $f['from'], 'to' => $f['to']]))) ?>">CSV</a></div></div>
<form class="card filters" method="get" action="<?= e(url('incident/index')) ?>">
  <div class="field"><label for="q">Recherche</label><input id="q" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Immatriculation, texte…"></div>
  <div class="field"><label for="status">Statut</label><select id="status" name="status"><option value="">Tous</option><option value="open" <?= $f['status'] === 'open' ? 'selected' : '' ?>>Non résolus</option>
    <?php foreach (labels('incident_status') as $k => $l): ?><option value="<?= e($k) ?>" <?= $f['status'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="priority">Priorité</label><select id="priority" name="priority"><option value="">Toutes</option><?php foreach (labels('priority') as $k => $l): ?><option value="<?= e($k) ?>" <?= $f['priority'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="truck_id">Camion</label><select id="truck_id" name="truck_id"><option value="">Tous</option><?php foreach ($trucks as $t): ?><option value="<?= (int)$t['id'] ?>" <?= (string)$f['truck_id'] === (string)$t['id'] ? 'selected' : '' ?>><?= e($t['plate']) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="driver_id">Conducteur</label><select id="driver_id" name="driver_id"><option value="">Tous</option><?php foreach ($drivers as $d): ?><option value="<?= (int)$d['id'] ?>" <?= (string)$f['driver_id'] === (string)$d['id'] ? 'selected' : '' ?>><?= e($d['first_name'] . ' ' . $d['last_name']) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="from">Du</label><input id="from" type="date" name="from" value="<?= e($f['from']) ?>"></div>
  <div class="field"><label for="to">Au</label><input id="to" type="date" name="to" value="<?= e($f['to']) ?>"></div>
  <div class="actions"><button class="btn" type="submit">Filtrer</button><a class="btn btn-light" href="<?= e(url('incident/index')) ?>">Réinitialiser</a></div>
</form>
<div class="card flush"><?php $showDriver = true; require __DIR__ . '/_table.php'; ?><?= $pager->render() ?></div>
