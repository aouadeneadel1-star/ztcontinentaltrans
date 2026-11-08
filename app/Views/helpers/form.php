<?php $x = $h ?? []; $v = static fn(string $k) => old($k, $x[$k] ?? ''); ?>
<div class="page-head"><h1><?= e($title) ?></h1></div>
<form class="card" method="post" action="<?= e(url('helper/save')) ?>" autocomplete="off">
  <?= csrf_field() ?>
  <?php if ($h): ?><input type="hidden" name="id" value="<?= (int)$h['id'] ?>"><?php endif; ?>
  <div class="form-grid cols-2">
    <div class="field"><label for="last_name">Nom *</label><input id="last_name" name="last_name" required maxlength="80" value="<?= $v('last_name') ?>"></div>
    <div class="field"><label for="first_name">Prénom *</label><input id="first_name" name="first_name" required maxlength="80" value="<?= $v('first_name') ?>"></div>
    <div class="field span-2"><label for="whatsapp">Numéro WhatsApp *</label><input id="whatsapp" type="tel" name="whatsapp" required maxlength="30" inputmode="tel" value="<?= e(old('whatsapp', isset($x['whatsapp']) ? '+' . $x['whatsapp'] : '')) ?>">
      <span class="hint">Ex. 06 12 34 56 78 ou +33 6 12 34 56 78 (indicatif du pays pour un numéro étranger).</span></div>
    <?php if ($h): ?><label class="check"><input type="checkbox" name="is_active" value="1" <?= $h['is_active'] ? 'checked' : '' ?>> Actif</label><?php endif; ?>
  </div>
  <div class="form-actions"><button class="btn" type="submit">Enregistrer</button><a class="btn btn-light" href="<?= e(url('helper/index')) ?>">Annuler</a></div>
</form>
