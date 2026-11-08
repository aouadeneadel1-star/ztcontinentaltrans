<div class="page-head"><h1>Notifications e-mail</h1></div>
<?php if (!$mailEnabled): ?><div class="alert alert-warning">L'envoi d'e-mails est <strong>désactivé</strong>. Renseignez <code>config/mail.local.php</code> (voir README) pour activer les notifications ; elles sont déjà historisées ci-dessous.</div><?php endif; ?>
<form class="card filters" method="get" action="<?= e(url('admin/notifications')) ?>">
  <div class="field"><label for="status">Statut</label><select id="status" name="status"><option value="">Tous</option>
    <?php foreach (['sent' => 'Envoyé', 'failed' => 'Échec', 'skipped' => 'Non envoyé (désactivé)', 'pending' => 'En attente'] as $k => $l): ?><option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div class="actions"><button class="btn" type="submit">Filtrer</button></div></form>
<div class="card flush"><div class="table-wrap"><table>
  <thead><tr><th>Date</th><th>Type</th><th>Destinataire</th><th>Objet</th><th>Statut</th></tr></thead><tbody>
  <?php $tones = ['sent' => 'green', 'failed' => 'red', 'skipped' => 'neutral', 'pending' => 'orange']; $txt = ['sent' => 'Envoyé', 'failed' => 'Échec', 'skipped' => 'Non envoyé', 'pending' => 'En attente'];
  foreach ($items as $n): ?><tr><td class="nowrap"><?= e(fdatetime($n['created_at'])) ?></td><td><?= e($n['type']) ?></td><td><?= e($n['recipient_email']) ?></td><td><?= e($n['subject']) ?></td>
    <td><?= badge($txt[$n['status']] ?? $n['status'], $tones[$n['status']] ?? 'neutral') ?><?= $n['error_message'] ? '<br><span class="small muted">' . e($n['error_message']) . '</span>' : '' ?></td></tr>
  <?php endforeach; if (!$items): ?><tr><td class="empty" colspan="5">Aucune notification.</td></tr><?php endif; ?></tbody></table></div><?= $pager->render() ?></div>
