<?php $admin = \App\Middleware\Auth::is('admin'); ?>
<div class="page-head"><h1>Camions</h1>
  <div class="actions"><?php if ($admin): ?><a class="btn" href="<?= e(url('truck/form')) ?>">Ajouter un camion</a><?php endif; ?></div></div>
<form class="card filters" method="get" action="<?= e(url('truck/index')) ?>">
  <div class="field grow"><label for="q">Immatriculation, marque, modèle</label><input id="q" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Ex. AB-123-CD"></div>
  <div class="field"><label for="status">Statut</label><select id="status" name="status"><option value="">Tous</option>
    <?php foreach (labels('truck_status') as $k => $v): ?><option value="<?= e($k) ?>" <?= $f['status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="ownership">Détention</label><select id="ownership" name="ownership"><option value="">Toutes</option>
    <?php foreach (labels('ownership') as $k => $v): ?><option value="<?= e($k) ?>" <?= $f['ownership'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
  <div class="actions"><button class="btn" type="submit">Filtrer</button><a class="btn btn-light" href="<?= e(url('truck/index')) ?>">Réinitialiser</a></div>
</form>
<div class="card flush"><div class="table-wrap"><table>
  <thead><tr><th>Immatriculation</th><th>Véhicule</th><th>Volume</th><th>Kilométrage</th><th>Conducteur</th><th>Prochain entretien</th><th>Statut</th></tr></thead>
  <tbody>
  <?php foreach ($trucks as $t): ?>
    <tr>
      <td><a href="<?= e(url('truck/show', ['id' => $t['id']])) ?>"><span class="plate"><?= e($t['plate']) ?></span></a></td>
      <td><?= e($t['brand'] . ' ' . $t['model']) ?><br><span class="muted small"><?= e($t['vehicle_type'] ?: '') ?><?= $t['year'] ? ' · ' . (int)$t['year'] : '' ?></span>
        <?php if ($t['ownership'] === 'rented'): ?><br><span class="small">Location<?= $t['rental_end_date'] ? ' jusqu\'au ' . e(fdate($t['rental_end_date'])) : '' ?> <?= $t['rental_end_date'] ? expiry_badge($t['rental_end_date']) : '' ?></span><?php endif; ?></td>
      <td><?= $t['volume_m3'] ? e(rtrim(rtrim(number_format((float)$t['volume_m3'], 1, ',', ''), '0'), ',')) . ' m³' : '—' ?></td>
      <td class="nowrap"><?= e(fkm((int)$t['mileage'])) ?></td>
      <td><?= e($t['driver_name'] ?: '—') ?></td>
      <td class="nowrap"><?= e(fdate($t['next_service_date'])) ?></td>
      <td><?= truck_status_badge($t['status']) ?></td>
    </tr>
  <?php endforeach; if (!$trucks): ?><tr><td class="empty" colspan="7">Aucun camion trouvé.</td></tr><?php endif; ?>
  </tbody></table></div><?= $pager->render() ?></div>
