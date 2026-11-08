<div class="page-head"><h1>Utilisateurs</h1><div class="actions"><a class="btn" href="<?= e(url('user/form')) ?>">Nouvel utilisateur</a></div></div>
<form class="card filters" method="get" action="<?= e(url('user/index')) ?>">
  <div class="field grow"><label for="q">Nom ou e-mail</label><input id="q" type="search" name="q" value="<?= e($f['q']) ?>"></div>
  <div class="field"><label for="role">Rôle</label><select id="role" name="role"><option value="">Tous</option><?php foreach (labels('roles') as $k => $v): ?><option value="<?= e($k) ?>" <?= $f['role'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
  <div class="actions"><button class="btn" type="submit">Filtrer</button><a class="btn btn-light" href="<?= e(url('user/index')) ?>">Réinitialiser</a></div>
</form>
<div class="card flush"><div class="table-wrap"><table>
  <thead><tr><th>Nom</th><th>Rôle</th><th>E-mail / Téléphone</th><th>Camion</th><th>Dernière connexion</th><th>Statut</th><th></th></tr></thead><tbody>
  <?php foreach ($users as $u): ?>
    <tr>
      <td><strong><?= e($u['first_name'] . ' ' . $u['last_name']) ?></strong></td>
      <td><?= e(label('roles', $u['role'])) ?></td>
      <td><?= e($u['email']) ?><br><span class="muted small"><?= e($u['phone'] ?: '') ?></span></td>
      <td><?= $u['truck_plate'] ? '<a href="' . e(url('truck/show', ['id' => $u['truck_id']])) . '"><span class="plate">' . e($u['truck_plate']) . '</span></a>' : '—' ?></td>
      <td class="nowrap"><?= e(fdatetime($u['last_login_at'])) ?></td>
      <td><?= $u['is_active'] ? badge('Actif', 'green') : badge('Désactivé', 'neutral') ?></td>
      <td class="nowrap"><a class="btn btn-light btn-sm" href="<?= e(url('user/form', ['id' => $u['id']])) ?>">Modifier</a>
        <?php if ((int)$u['id'] !== \App\Middleware\Auth::id()): ?>
        <form class="inline" method="post" action="<?= e(url('user/toggle')) ?>" data-confirm="<?= $u['is_active'] ? 'Désactiver ce compte ?' : 'Réactiver ce compte ?' ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="btn btn-light btn-sm" type="submit"><?= $u['is_active'] ? 'Désactiver' : 'Réactiver' ?></button></form><?php endif; ?></td>
    </tr>
  <?php endforeach; if (!$users): ?><tr><td class="empty" colspan="7">Aucun utilisateur.</td></tr><?php endif; ?>
  </tbody></table></div><?= $pager->render() ?></div>
