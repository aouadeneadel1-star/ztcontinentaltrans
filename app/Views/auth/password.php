<?php $min = (int)config('password_min_length'); ?>
<?php if (!empty($forced)): ?>
  <div class="alert alert-warning">Pour votre sécurité, vous devez choisir un nouveau mot de passe avant de continuer.</div>
<?php endif; ?>
<div class="page-head"><h1>Changer le mot de passe</h1></div>
<div class="card" style="max-width:520px">
  <form method="post" action="<?= e(url('auth/password')) ?>">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="field"><label for="current_password">Mot de passe actuel</label>
        <input id="current_password" name="current_password" type="password" required autocomplete="current-password"></div>
      <div class="field"><label for="password">Nouveau mot de passe</label>
        <input id="password" name="password" type="password" required minlength="<?= $min ?>" autocomplete="new-password">
        <span class="hint"><?= $min ?> caractères minimum, avec au moins une lettre et un chiffre.</span></div>
      <div class="field"><label for="password_confirm">Confirmer le nouveau mot de passe</label>
        <input id="password_confirm" name="password_confirm" type="password" required minlength="<?= $min ?>" autocomplete="new-password"></div>
    </div>
    <div class="form-actions"><button class="btn" type="submit">Enregistrer</button></div>
  </form>
</div>
