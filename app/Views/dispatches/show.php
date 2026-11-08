<?php $tone = ['draft' => 'neutral', 'validated' => 'orange', 'sent' => 'green', 'partial' => 'orange', 'failed' => 'red'][$d['status']] ?? 'neutral';
$draft = $d['status'] === 'draft'; ?>
<div class="page-head"><h1>Planning du <?= e(fdate_long($d['work_date'])) ?></h1><div class="actions"><?= badge(label('dispatch_status', $d['status']), $tone) ?></div></div>

<section class="card">
  <ul class="list">
    <li><span class="muted">Conducteur</span><strong><?= e($d['driver_name']) ?></strong></li>
    <li><span class="muted">Rippeurs</span><strong><?= e(implode(', ', array_filter([$d['helper1_name'], $d['helper2_name']])) ?: '—') ?></strong></li>
    <li><span class="muted">Camion</span><span><span class="plate"><?= e($d['plate']) ?></span> <?= e($d['brand'] . ' ' . $d['model']) ?></span></li>
    <?php if ($d['note']): ?><li><span class="muted">Remarque</span><span><?= e($d['note']) ?></span></li><?php endif; ?>
  </ul>
</section>

<section class="card"><h2>Missions</h2>
  <ul class="list">
    <?php foreach ($tours as $i => $t): ?>
      <li><span><strong><?= e(label('tour_kind', $t['kind']) . ' ' . ($i + 1)) ?></strong> · <?= e($t['client'] . ' – ' . $t['city']) ?></span>
        <span class="muted small"><?= $t['file_path'] ? e($t['original_name']) : 'Sans feuille' ?></span></li>
    <?php endforeach; ?>
  </ul>
</section>

<?php if ($draft): ?>
  <section class="card"><h2>Aperçu des messages</h2>
    <?php foreach ($preview as $m): ?>
      <div class="wa-preview"><div class="wa-to"><?= e($m['name']) ?> · <?= $m['phone'] ? e(\App\Helpers\Phone::pretty($m['phone'])) : badge('Numéro invalide', 'red') ?></div><pre><?= e($m['body']) ?></pre></div>
    <?php endforeach; ?>
    <form method="post" action="<?= e(url('dispatch/validate')) ?>" class="form-actions"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
      <button class="btn" type="submit"><?= $auto ? 'Valider et envoyer par WhatsApp' : 'Valider' ?></button>
      <a class="btn btn-light" href="<?= e(url('dispatch/form', ['id' => $d['id']])) ?>">Modifier</a>
    </form>
    <?php if (!$auto): ?><p class="hint">L'envoi automatique n'est pas encore configuré (voir README) : après validation, un bouton WhatsApp sera proposé pour chaque personne.</p><?php endif; ?>
  </section>
<?php else: ?>
  <section class="card"><h2>Envois</h2>
    <ul class="list">
      <?php foreach ($messages as $m): ?>
        <li><span><strong><?= e($m['recipient_name']) ?></strong> <span class="muted small"><?= e(\App\Helpers\Phone::pretty($m['phone'])) ?></span>
          <?php if ($m['error_message']): ?><br><span class="small" style="color:var(--red-700)"><?= e($m['error_message']) ?></span><?php endif; ?></span>
          <span class="nowrap">
            <?= badge(['sent' => 'Envoyé', 'failed' => 'Échec', 'pending' => 'À envoyer'][$m['status']], ['sent' => 'green', 'failed' => 'red', 'pending' => 'orange'][$m['status']]) ?>
            <?php if ($m['status'] !== 'sent' && !$auto && $m['link']): ?>
              <a class="btn btn-sm" target="_blank" rel="noopener" href="<?= e($m['link']) ?>">Ouvrir WhatsApp</a>
              <form class="inline" method="post" action="<?= e(url('dispatch/mark')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn-light btn-sm" type="submit">C'est envoyé</button></form>
            <?php endif; ?>
          </span></li>
      <?php endforeach; ?>
    </ul>
    <?php if ($auto && $d['status'] !== 'sent'): ?>
      <form method="post" action="<?= e(url('dispatch/resend')) ?>" class="form-actions"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><button class="btn" type="submit">Renvoyer les messages en échec</button></form>
    <?php endif; ?>
  </section>
<?php endif; ?>

<form method="post" action="<?= e(url('dispatch/delete')) ?>" data-confirm="Supprimer ce planning et ses fichiers ?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><button class="btn btn-danger" type="submit">Supprimer ce planning</button></form>
