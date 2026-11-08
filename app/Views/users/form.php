<?php $x = $u ?? []; $v = static fn(string $k) => old($k, $x[$k] ?? ''); $min = (int)config('password_min_length'); ?>
<div class="page-head"><h1><?= e($title) ?></h1></div>
<form class="card" method="post" action="<?= e(url('user/save')) ?>" autocomplete="off">
  <?= csrf_field() ?>
  <?php if ($u): ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><?php endif; ?>
  <div class="form-grid cols-2">
    <div class="field"><label for="first_name">Prénom *</label><input id="first_name" name="first_name" required maxlength="80" value="<?= $v('first_name') ?>"></div>
    <div class="field"><label for="last_name">Nom *</label><input id="last_name" name="last_name" required maxlength="80" value="<?= $v('last_name') ?>"></div>
    <div class="field"><label for="email">Adresse e-mail *</label><input id="email" type="email" name="email" required maxlength="190" value="<?= $v('email') ?>"></div>
    <div class="field"><label for="phone">Téléphone</label><input id="phone" type="tel" name="phone" maxlength="30" value="<?= $v('phone') ?>"></div>
    <div class="field"><label for="role">Rôle *</label><select id="role" name="role"><?php $cr = old('role', $x['role'] ?? 'driver'); foreach (labels('roles') as $k => $l): ?><option value="<?= e($k) ?>" <?= $cr === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="truck_id">Camion affecté (conducteurs)</label><select id="truck_id" name="truck_id"><option value="">— Aucun —</option>
      <?php foreach ($trucks as $t): ?><option value="<?= (int)$t['id'] ?>" <?= (int)($currentTruck['id'] ?? 0) === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['plate'] . ' – ' . $t['brand'] . ' ' . $t['model']) ?><?= $t['driver_name'] && (int)$t['driver_id'] !== (int)($x['id'] ?? 0) ? ' (déjà affecté : ' . e($t['driver_name']) . ')' : '' ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="password"><?= $u ? 'Nouveau mot de passe' : 'Mot de passe *' ?></label><input id="password" type="password" name="password" <?= $u ? '' : 'required' ?> minlength="<?= $min ?>" autocomplete="new-password">
      <span class="hint"><?= $u ? 'Laisser vide pour ne pas le changer. ' : '' ?><?= $min ?> caractères minimum, lettre et chiffre.</span></div>
    <div class="field"><label for="password_confirm">Confirmer le mot de passe</label><input id="password_confirm" type="password" name="password_confirm" autocomplete="new-password"></div>
    <label class="check"><input type="checkbox" name="must_change_password" value="1" <?= !$u || !empty($_SESSION['_old']['must_change_password']) ? 'checked' : '' ?>> Imposer le changement du mot de passe à la prochaine connexion</label>
    <label class="check"><input type="checkbox" name="is_active" value="1" <?= (!$u || $u['is_active']) ? 'checked' : '' ?>> Compte actif</label>
  </div>
  <div class="form-actions"><button class="btn" type="submit">Enregistrer</button><a class="btn btn-light" href="<?= e(url('user/index')) ?>">Annuler</a></div>
</form>
