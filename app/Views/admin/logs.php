<div class="page-head"><h1>Journal d'activité</h1></div>
<form class="card filters" method="get" action="<?= e(url('admin/logs')) ?>">
  <div class="field"><label for="action">Action (préfixe)</label><input id="action" name="action" value="<?= e($f['action']) ?>" placeholder="auth, truck, document…"></div>
  <div class="field"><label for="user_id">Utilisateur</label><select id="user_id" name="user_id"><option value="">Tous</option><?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>" <?= (string)$f['user_id'] === (string)$u['id'] ? 'selected' : '' ?>><?= e($u['first_name'] . ' ' . $u['last_name']) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="from">Du</label><input id="from" type="date" name="from" value="<?= e($f['from']) ?>"></div>
  <div class="field"><label for="to">Au</label><input id="to" type="date" name="to" value="<?= e($f['to']) ?>"></div>
  <div class="actions"><button class="btn" type="submit">Filtrer</button><a class="btn btn-light" href="<?= e(url('admin/logs')) ?>">Réinitialiser</a></div>
</form>
<div class="card flush"><div class="table-wrap"><table>
  <thead><tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Détails</th><th>IP</th></tr></thead><tbody>
  <?php foreach ($logs as $l): ?><tr><td class="nowrap"><?= e(fdatetime($l['created_at'])) ?></td><td><?= e($l['user_name'] ?: '—') ?></td><td><code><?= e($l['action']) ?></code></td><td><?= e($l['details']) ?></td><td class="nowrap"><?= e($l['ip_address']) ?></td></tr>
  <?php endforeach; if (!$logs): ?><tr><td class="empty" colspan="5">Aucune entrée.</td></tr><?php endif; ?></tbody></table></div><?= $pager->render() ?></div>
