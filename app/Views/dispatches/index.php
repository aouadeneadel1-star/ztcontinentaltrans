<div class="page-head"><h1>Tournées</h1><div class="actions"><a class="btn" href="<?= e(url('dispatch/form')) ?>">Nouveau planning</a></div></div>
<form class="card filters" method="get" action="<?= e(url('dispatch/index')) ?>">
  <div class="field"><label for="date">Date</label><input id="date" type="date" name="date" value="<?= e($f['date']) ?>"></div>
  <div class="field"><label for="status">Statut</label><select id="status" name="status"><option value="">Tous</option><?php foreach (labels('dispatch_status') as $k => $l): ?><option value="<?= e($k) ?>" <?= $f['status'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div class="actions"><button class="btn" type="submit">Filtrer</button><a class="btn btn-light" href="<?= e(url('dispatch/index')) ?>">Réinitialiser</a></div>
</form>
<div class="card flush"><div class="table-wrap"><table>
  <thead><tr><th>Date</th><th>Équipe</th><th>Camion</th><th>Missions</th><th>Statut</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): $tone = ['draft' => 'neutral', 'validated' => 'orange', 'sent' => 'green', 'partial' => 'orange', 'failed' => 'red'][$r['status']] ?? 'neutral'; ?>
    <tr>
      <td class="nowrap"><strong><?= e(fdate($r['work_date'])) ?></strong></td>
      <td><?= e($r['driver_name']) ?><br><span class="muted small"><?= e(implode(', ', array_filter([$r['helper1_name'], $r['helper2_name']])) ?: 'Sans rippeur') ?></span></td>
      <td><span class="plate"><?= e($r['plate']) ?></span></td>
      <td><?php foreach ($r['tours'] as $t): ?><?= e($t['client'] . ' – ' . $t['city']) ?><br><?php endforeach; ?></td>
      <td><?= badge(label('dispatch_status', $r['status']), $tone) ?></td>
      <td class="nowrap"><a class="btn btn-light btn-sm" href="<?= e(url('dispatch/show', ['id' => $r['id']])) ?>">Ouvrir</a></td>
    </tr>
  <?php endforeach; if (!$rows): ?><tr><td class="empty" colspan="6">Aucun planning.</td></tr><?php endif; ?>
  </tbody></table></div><?= $pager->render() ?></div>
