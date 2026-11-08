<div class="page-head"><h1>Rippeurs</h1><div class="actions"><a class="btn" href="<?= e(url('helper/form')) ?>">Nouveau rippeur</a></div></div>
<p class="muted">Un rippeur n'a pas de compte : on enregistre seulement son nom, son prénom et son numéro WhatsApp.</p>
<div class="card flush"><div class="table-wrap"><table>
  <thead><tr><th>Nom</th><th>WhatsApp</th><th>Statut</th><th></th></tr></thead><tbody>
  <?php foreach ($helpers as $h): ?>
    <tr>
      <td><strong><?= e($h['last_name'] . ' ' . $h['first_name']) ?></strong></td>
      <td class="nowrap"><?= e(\App\Helpers\Phone::pretty($h['whatsapp'])) ?></td>
      <td><?= $h['is_active'] ? badge('Actif', 'green') : badge('Désactivé', 'neutral') ?></td>
      <td class="nowrap">
        <a class="btn btn-light btn-sm" href="<?= e(url('helper/form', ['id' => $h['id']])) ?>">Modifier</a>
        <form class="inline" method="post" action="<?= e(url('helper/toggle')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$h['id'] ?>"><button class="btn btn-light btn-sm" type="submit"><?= $h['is_active'] ? 'Désactiver' : 'Réactiver' ?></button></form>
        <form class="inline" method="post" action="<?= e(url('helper/delete')) ?>" data-confirm="Supprimer ce rippeur ?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$h['id'] ?>"><button class="btn btn-danger btn-sm" type="submit">Supprimer</button></form>
      </td>
    </tr>
  <?php endforeach; if (!$helpers): ?><tr><td class="empty" colspan="4">Aucun rippeur pour le moment.</td></tr><?php endif; ?>
  </tbody></table></div></div>
