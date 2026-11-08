<div class="page-head"><h1>Mon camion</h1></div>
<?php if ($safety): ?>
  <div class="danger-banner"><div>VÉHICULE À NE PAS UTILISER AVANT VÉRIFICATION<small>Un problème de sécurité est ouvert sur ce camion.</small></div></div>
<?php endif; ?>
<div class="card">
  <div class="truck-head">
    <div class="truck-photo"><?php if ($truck['photo_path']): ?><img src="<?= e(url('file/truck', ['id' => $truck['id']])) ?>" alt="Photo du camion"><?php else: ?><?= icon('truck') ?><?php endif; ?></div>
    <dl class="kv">
      <div><dt>Immatriculation</dt><dd><span class="plate"><?= e($truck['plate']) ?></span></dd></div>
      <div><dt>Véhicule</dt><dd><?= e($truck['brand'] . ' ' . $truck['model']) ?></dd></div>
      <div><dt>Type / volume</dt><dd><?= e($truck['vehicle_type'] ?: '—') ?> <?= $truck['volume_m3'] ? '· ' . e(rtrim(rtrim(number_format((float)$truck['volume_m3'], 1, ',', ''), '0'), ',')) . ' m³' : '' ?></dd></div>
      <div><dt>Année</dt><dd><?= e($truck['year'] ?: '—') ?></dd></div>
      <div><dt>Kilométrage</dt><dd><?= e(fkm((int)$truck['mileage'])) ?></dd></div>
      <div><dt>Prochain entretien</dt><dd><?= e(fdate($truck['next_service_date'])) ?></dd></div>
      <div><dt>Statut</dt><dd><?= truck_status_badge($truck['status']) ?></dd></div>
    </dl>
  </div>
</div>
<div class="card"><h2>Documents</h2>
  <?php foreach ($documents as $d): ?>
    <div class="doc-row">
      <div class="info"><strong><?= e($d['name']) ?></strong><br><span class="muted small"><?= e(label('doc_category', $d['category'])) ?> · <?= e(fsize((int)$d['file_size'])) ?></span>
        <?php if ($d['expiry_date']): ?><br><?= expiry_badge($d['expiry_date']) ?> <span class="small muted">jusqu'au <?= e(fdate($d['expiry_date'])) ?></span><?php endif; ?></div>
      <div class="btns">
        <a class="btn btn-light btn-sm" target="_blank" rel="noopener" href="<?= e(url('file/document', ['id' => $d['id']])) ?>">Consulter</a>
        <a class="btn btn-light btn-sm" href="<?= e(url('file/document', ['id' => $d['id'], 'dl' => 1])) ?>">Télécharger</a>
      </div>
    </div>
  <?php endforeach; if (!$documents): ?><div class="empty-state"><p>Aucun document pour ce camion.</p></div><?php endif; ?>
</div>
