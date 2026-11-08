<div class="breadcrumb"><a href="<?= e(url($staff ? 'incident/index' : 'incident/mine')) ?>">← Signalements</a></div>
<div class="page-head"><h1>Signalement #<?= (int)$incident['id'] ?></h1><div><?= priority_badge($incident['priority']) ?> <?= incident_status_badge($incident['status']) ?></div></div>
<?php if ($incident['safety_risk'] && $incident['status'] !== 'resolved'): ?>
  <div class="danger-banner"><div>CE VÉHICULE NE DOIT PAS ÊTRE UTILISÉ AVANT VÉRIFICATION<small>Risque pour la sécurité signalé sur le camion <?= e($incident['plate']) ?>.</small></div></div>
<?php endif; ?>
<div class="grid grid-2">
  <section class="card"><h2>Détails</h2><dl class="kv">
    <div><dt>Camion</dt><dd><?php if ($staff): ?><a href="<?= e(url('truck/show', ['id' => $incident['truck_id']])) ?>"><span class="plate"><?= e($incident['plate']) ?></span></a><?php else: ?><span class="plate"><?= e($incident['plate']) ?></span><?php endif; ?> <span class="small muted"><?= e($incident['brand'] . ' ' . $incident['model']) ?></span></dd></div>
    <?php if ($staff && $truck): ?><div><dt>Statut du camion</dt><dd><?= truck_status_badge($truck['status']) ?></dd></div>
      <div><dt>Kilométrage</dt><dd><?= e(fkm((int)$truck['mileage'])) ?></dd></div><?php endif; ?>
    <div><dt>Conducteur</dt><dd><?= e($incident['driver_name']) ?></dd></div>
    <div><dt>Type</dt><dd><?= e(label('incident_category', $incident['category'])) ?></dd></div>
    <div><dt>Signalé le</dt><dd><?= e(fdatetime($incident['created_at'])) ?></dd></div>
    <?php if ($incident['resolved_at']): ?><div><dt>Résolu le</dt><dd><?= e(fdatetime($incident['resolved_at'])) ?></dd></div><?php endif; ?>
  </dl><h3 style="margin-top:14px">Description</h3><p class="desc"><?= e($incident['description']) ?></p></section>
  <section class="card"><h2>Photos (<?= count($photos) ?>)</h2><div class="photo-grid">
    <?php foreach ($photos as $p): ?><div class="photo-item"><a href="<?= e(url('file/incident', ['id' => $p['id']])) ?>" target="_blank" rel="noopener" style="border:0;aspect-ratio:auto;height:100%"><img loading="lazy" src="<?= e(url('file/incident', ['id' => $p['id']])) ?>" alt="Photo du problème"></a>
      <?php if ($staff): ?><a class="photo-dl" href="<?= e(url('file/incident', ['id' => $p['id'], 'dl' => 1])) ?>" aria-label="Télécharger"><?= icon('download') ?></a><?php endif; ?></div><?php endforeach; ?></div>
    <?php if (!$photos): ?><p class="muted">Aucune photo jointe.</p><?php endif; ?></section>
</div>
<section class="card" style="margin-top:16px"><h2>Suivi</h2>
  <ul class="timeline">
    <?php foreach ($comments as $c): ?><li><div class="when"><?= e(fdatetime($c['created_at'])) ?> · <?= e($c['author'] ?: 'Utilisateur supprimé') ?>
      <?php if ($c['new_status']): ?> · <?= e(label('incident_status', $c['old_status'])) ?> → <strong><?= e(label('incident_status', $c['new_status'])) ?></strong><?php endif; ?></div>
      <div class="comment-text"><?= e($c['comment']) ?></div></li><?php endforeach; ?>
    <li><div class="when"><?= e(fdatetime($incident['created_at'])) ?> · <?= e($incident['driver_name']) ?></div><div>Signalement créé.</div></li>
  </ul>
  <?php if ($staff): ?>
  <form method="post" action="<?= e(url('incident/comment')) ?>" style="margin-top:12px"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$incident['id'] ?>">
    <div class="form-grid cols-2">
      <div class="field"><label for="status">Statut</label><select id="status" name="status"><?php foreach (labels('incident_status') as $k => $l): ?><option value="<?= e($k) ?>" <?= $incident['status'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="field span-2"><label for="comment">Commentaire</label><textarea id="comment" name="comment" maxlength="2000" style="min-height:80px"><?= old('comment') ?></textarea></div>
    </div><div class="form-actions"><button class="btn" type="submit">Enregistrer</button></div></form>
  <?php endif; ?>
</section>
