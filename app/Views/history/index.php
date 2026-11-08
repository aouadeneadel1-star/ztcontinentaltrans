<?php
$has = static fn(string $k) => in_array($k, ['truck_id','driver_id','type_check','status'], true);
$q = array_filter(['type' => $type] + $f, static fn($v) => $v !== '' && $v !== null);
?>
<div class="page-head"><h1>Historique et exports</h1>
  <div class="actions"><a class="btn btn-light" href="<?= e(url('history/export', $q + ['format' => 'csv'])) ?>">Export CSV</a>
    <a class="btn btn-light" href="<?= e(url('history/export', $q + ['format' => 'pdf'])) ?>">Export PDF</a></div></div>
<div class="tabs"><?php foreach ($types as $k => $l): ?><a class="tab<?= $k === $type ? ' active' : '' ?>" href="<?= e(url('history/index', ['type' => $k])) ?>"><?= e($l) ?></a><?php endforeach; ?></div>
<form class="card filters" method="get" action="<?= e(url('history/index')) ?>"><input type="hidden" name="type" value="<?= e($type) ?>">
  <div class="field"><label for="truck_id">Véhicule</label><select id="truck_id" name="truck_id"><option value="">Tous</option><?php foreach ($trucks as $t): ?><option value="<?= (int)$t['id'] ?>" <?= (string)($f['truck_id'] ?? '') === (string)$t['id'] ? 'selected' : '' ?>><?= e($t['plate']) ?></option><?php endforeach; ?></select></div>
  <?php if (array_key_exists('driver_id', $f)): ?><div class="field"><label for="driver_id">Conducteur</label><select id="driver_id" name="driver_id"><option value="">Tous</option><?php foreach ($drivers as $d): ?><option value="<?= (int)$d['id'] ?>" <?= (string)$f['driver_id'] === (string)$d['id'] ? 'selected' : '' ?>><?= e($d['first_name'] . ' ' . $d['last_name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
  <?php if (array_key_exists('type_check', $f)): ?><div class="field"><label for="type_check">Type de contrôle</label><select id="type_check" name="type_check"><option value="">Matin et soir</option><?php foreach (labels('check_type') as $k => $l): ?><option value="<?= e($k) ?>" <?= $f['type_check'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div><?php endif; ?>
  <?php if (array_key_exists('status', $f)): ?><div class="field"><label for="status">Statut du signalement</label><select id="status" name="status"><option value="">Tous</option><option value="open" <?= $f['status'] === 'open' ? 'selected' : '' ?>>Non résolus</option><?php foreach (labels('incident_status') as $k => $l): ?><option value="<?= e($k) ?>" <?= $f['status'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div><?php endif; ?>
  <div class="field"><label for="from">Du</label><input id="from" type="date" name="from" value="<?= e($f['from']) ?>"></div>
  <div class="field"><label for="to">Au</label><input id="to" type="date" name="to" value="<?= e($f['to']) ?>"></div>
  <div class="actions"><button class="btn" type="submit">Filtrer</button><a class="btn btn-light" href="<?= e(url('history/index', ['type' => $type])) ?>">Réinitialiser</a></div>
</form>
<div class="card flush"><div class="table-wrap"><table>
  <thead><tr><?php foreach ($ds['headers'] as $h): ?><th><?= e($h) ?></th><?php endforeach; ?><th></th></tr></thead><tbody>
  <?php foreach ($ds['rows'] as $r): ?><tr><?php foreach ($r['cells'] as $c): ?><td><?= e(mb_strimwidth((string)$c, 0, 120, '…')) ?></td><?php endforeach; ?><td><?php if ($r['link']): ?><a href="<?= e($r['link']) ?>">Voir</a><?php endif; ?></td></tr>
  <?php endforeach; if (!$ds['rows']): ?><tr><td class="empty" colspan="<?= count($ds['headers']) + 1 ?>">Aucun résultat.</td></tr><?php endif; ?></tbody></table></div><?= $pager->render() ?></div>
