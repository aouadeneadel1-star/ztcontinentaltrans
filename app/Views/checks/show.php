<?php $staff = \App\Middleware\Auth::isStaff(); ?>
<div class="breadcrumb"><a href="<?= e(url($staff ? 'check/index' : 'driver/home')) ?>">← Retour</a></div>
<div class="page-head"><h1>Contrôle <?= e(mb_strtolower(label('check_type', $check['check_type']))) ?> – <span class="plate"><?= e($check['plate']) ?></span></h1>
  <?php if ($staff && $photos): ?><div class="actions"><a class="btn btn-light" href="<?= e(url('file/zip', ['id' => $check['id']])) ?>">Tout télécharger (ZIP)</a>
    <a class="btn btn-light" href="<?= e(url('check/compare', ['truck_id' => $check['truck_id'], 'date' => $check['check_date']])) ?>">Comparer la journée</a></div><?php endif; ?></div>
<div class="card"><dl class="kv">
  <div><dt>Conducteur</dt><dd><?= e($check['driver_name']) ?></dd></div>
  <div><dt>Date et heure</dt><dd><?= e(fdatetime($check['checked_at'])) ?></dd></div>
  <div><dt>Kilométrage</dt><dd><?= $check['mileage'] !== null ? e(fkm((int)$check['mileage'])) : 'Non renseigné' ?></dd></div>
  <div><dt>Résultat</dt><dd><?= $check['has_anomaly'] ? badge('Anomalie signalée', 'orange') : badge('RAS', 'green') ?></dd></div>
</dl><?php if ($check['comment']): ?><h3 style="margin-top:14px">Commentaire</h3><p class="desc"><?= e($check['comment']) ?></p><?php endif; ?></div>
<div class="card"><h2>Photos (<?= count($photos) ?>)</h2>
  <div class="photo-grid">
  <?php foreach ($photos as $p): ?>
    <div class="photo-item"><a href="<?= e(url('file/photo', ['id' => $p['id']])) ?>" target="_blank" rel="noopener" style="border:0;aspect-ratio:auto;height:100%"><img loading="lazy" src="<?= e(url('file/photo', ['id' => $p['id']])) ?>" alt="Photo du contrôle"></a>
      <?php if ($staff): ?><a class="photo-dl" href="<?= e(url('file/photo', ['id' => $p['id'], 'dl' => 1])) ?>" aria-label="Télécharger"><?= icon('download') ?></a><?php endif; ?></div>
  <?php endforeach; if (!$photos): ?><p class="muted">Aucune photo.</p><?php endif; ?>
  </div></div>
