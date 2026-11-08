<div class="breadcrumb"><a href="<?= e(url('truck/show', ['id' => $truck['id']])) ?>">← <?= e($truck['plate']) ?></a></div>
<div class="page-head"><h1>Comparaison matin / soir – <span class="plate"><?= e($truck['plate']) ?></span></h1></div>
<form class="card filters" data-keep method="get" action="<?= e(url('check/compare')) ?>"><input type="hidden" name="truck_id" value="<?= (int)$truck['id'] ?>">
  <div class="field"><label for="date">Journée</label><input id="date" type="date" name="date" value="<?= e($date) ?>"></div>
  <div class="actions"><button class="btn" type="submit">Afficher</button>
    <a class="btn btn-light" href="<?= e(url('check/compare', ['truck_id' => $truck['id'], 'date' => date('Y-m-d', strtotime($date . ' -1 day'))])) ?>">‹ Veille</a>
    <a class="btn btn-light" href="<?= e(url('check/compare', ['truck_id' => $truck['id'], 'date' => date('Y-m-d', strtotime($date . ' +1 day'))])) ?>">Lendemain ›</a></div></form>
<div class="compare">
<?php foreach (['morning' => $morning, 'evening' => $evening] as $type => $list): ?>
  <section class="card">
    <div class="col-head <?= $type ?>"><h2>Contrôle du <?= $type === 'morning' ? 'matin' : 'soir' ?></h2></div>
    <?php foreach ($list as $c): ?>
      <p class="small muted"><?= e($c['driver_name']) ?> · <?= e(fdatetime($c['checked_at'])) ?> · <?= $c['mileage'] !== null ? e(fkm((int)$c['mileage'])) : 'km non renseigné' ?> · <a href="<?= e(url('check/show', ['id' => $c['id']])) ?>">Détail</a> <?= $c['has_anomaly'] ? badge('Anomalie', 'red') : '' ?></p>
      <?php if ($c['comment']): ?><p class="desc"><?= e($c['comment']) ?></p><?php endif; ?>
      <div class="photo-grid" style="margin-bottom:12px">
        <?php foreach ($c['photos'] as $p): ?><div class="photo-item"><a href="<?= e(url('file/photo', ['id' => $p['id']])) ?>" target="_blank" rel="noopener" style="border:0;aspect-ratio:auto;height:100%"><img loading="lazy" src="<?= e(url('file/photo', ['id' => $p['id']])) ?>" alt="Photo"></a>
          <a class="photo-dl" href="<?= e(url('file/photo', ['id' => $p['id'], 'dl' => 1])) ?>" aria-label="Télécharger"><?= icon('download') ?></a></div><?php endforeach; ?></div>
    <?php endforeach; if (!$list): ?><div class="empty-state"><p>Aucun contrôle <?= $type === 'morning' ? 'du matin' : 'du soir' ?> ce jour.</p></div><?php endif; ?>
  </section>
<?php endforeach; ?>
</div>
