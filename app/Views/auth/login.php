<img class="logo" src="<?= e(asset('images/logo.png')) ?>" alt="ZT Continental Trans">
<h1>Connexion</h1>

<form method="post" action="<?= e(url('auth/login')) ?>" autocomplete="on">
  <?= csrf_field() ?>
  <div class="field" style="margin-bottom:14px">
    <label for="email">Adresse e-mail</label>
    <input id="email" name="email" type="email" required autofocus autocomplete="username" value="<?= old('email') ?>">
  </div>
  <div class="field" style="margin-bottom:18px">
    <label for="password">Mot de passe</label>
    <input id="password" name="password" type="password" required autocomplete="current-password">
  </div>
  <button class="btn btn-block" type="submit" data-loading="Connexion…">Se connecter</button>
</form>
<?php \App\Helpers\Session::clearOld(); ?>
