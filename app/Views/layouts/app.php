<?php
use App\Middleware\Auth;
$user = Auth::user();
$route = $_GET['r'] ?? '';
$isStaff = Auth::isStaff();
$active = static fn(string ...$prefixes): string => array_reduce($prefixes, static fn($c, $p) => $c || str_starts_with($route, $p), false) ? ' active' : '';

if ($isStaff) {
    $nav = [
        ['', [
            ['dashboard/index', 'Tableau de bord', 'dashboard', ['dashboard/']],
        ]],
        ['Flotte', [
            ['dispatch/index', 'Tournées', 'route', ['dispatch/']],
            ['helper/index', 'Rippeurs', 'users', ['helper/']],
            ['truck/index', 'Camions', 'truck', ['truck/']],
            ['check/index', 'Photos et contrôles', 'camera', ['check/']],
            ['incident/index', 'Signalements', 'alert', ['incident/']],
            ['history/index', 'Historique et exports', 'history', ['history/']],
        ]],
    ];
    $admin = [];
    if (Auth::is('admin')) {
        $admin[] = ['user/index', 'Utilisateurs', 'users', ['user/']];
    }
    $admin[] = ['admin/notifications', 'Notifications e-mail', 'mail', ['admin/notifications']];
    if (Auth::is('admin')) {
        $admin[] = ['admin/logs', 'Journal d\'activité', 'list', ['admin/logs']];
    }
    $nav[] = ['Administration', $admin];
} else {
    $nav = [['Mon espace', [
        ['driver/home', 'Accueil', 'home', ['driver/home']],
        ['driver/truck', 'Mon camion et documents', 'truck', ['driver/truck']],
        ['check/form&type=morning', 'Contrôle du matin', 'sun', []],
        ['check/form&type=evening', 'Contrôle du soir', 'moon', []],
        ['incident/form', 'Signaler un problème', 'alert', ['incident/form']],
        ['incident/mine', 'Mes signalements', 'list', ['incident/mine']],
    ]]];
}
$flashes = \App\Helpers\Session::pullFlash();
$navUrl = static function (string $r): string {
    if (str_contains($r, '&')) {
        parse_str(str_replace('&', '&', substr($r, strpos($r, '&') + 1)), $q);
        return url(substr($r, 0, strpos($r, '&')), $q);
    }
    return url($r);
};
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?> – <?= e(config('name')) ?></title>
<meta name="theme-color" content="#ffffff">
<meta name="robots" content="noindex, nofollow">
<link rel="manifest" href="<?= e(base_path()) ?>/manifest.webmanifest">
<link rel="icon" type="image/png" href="<?= e(asset('images/icon-192.png')) ?>">
<link rel="apple-touch-icon" href="<?= e(asset('images/icon-192.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<div class="app">
  <header class="topbar">
    <button class="icon-btn" type="button" data-nav-toggle aria-label="Ouvrir le menu"><?= icon('menu') ?></button>
    <a class="brand" href="<?= e(url(Auth::home())) ?>"><img src="<?= e(asset('images/logo.png')) ?>" alt=""><span><?= e(config('name')) ?></span></a>
  </header>
  <div class="backdrop" data-nav-toggle></div>

  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <img src="<?= e(asset('images/logo.png')) ?>" alt="Logo <?= e(config('name')) ?>">
      <div><strong>ZT Continental Trans</strong></div>
    </div>
    <nav aria-label="Navigation principale">
      <?php foreach ($nav as [$group, $items]): ?>
        <?php if ($group !== ''): ?><div class="nav-title"><?= e($group) ?></div><?php endif; ?>
        <?php foreach ($items as [$r, $text, $ic, $prefixes]): ?>
          <a class="nav-link<?= $active(...$prefixes) ?>" href="<?= e($navUrl($r)) ?>"><?= icon($ic) ?> <?= e($text) ?></a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-user">
      <strong><?= e($user['first_name'] . ' ' . $user['last_name']) ?></strong>
      <span class="role"><?= e(label('roles', $user['role'])) ?></span>
      <div class="links">
        <a class="btn btn-light btn-sm" href="<?= e(url('auth/password')) ?>">Mot de passe</a>
        <form method="post" action="<?= e(url('auth/logout')) ?>" class="inline" style="flex:1;display:flex"><?= csrf_field() ?>
          <button class="btn btn-light btn-sm" style="flex:1" type="submit">Quitter</button>
        </form>
      </div>
    </div>
  </aside>

  <main class="main" id="content">
    <?php foreach ($flashes as $f): ?>
      <div class="alert alert-<?= e($f['type']) ?>" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>"><?= nl2br(e($f['message'])) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
  </main>

  <nav class="bottomnav" aria-label="Navigation mobile">
    <?php if ($isStaff): ?>
      <a href="<?= e(url('dashboard/index')) ?>" class="<?= trim($active('dashboard/')) ?>"><?= icon('dashboard') ?>Accueil</a>
      <a href="<?= e(url('dispatch/index')) ?>" class="<?= trim($active('dispatch/')) ?>"><?= icon('route') ?>Tournées</a>
      <a href="<?= e(url('truck/index')) ?>" class="<?= trim($active('truck/')) ?>"><?= icon('truck') ?>Camions</a>
      <a href="<?= e(url('incident/index')) ?>" class="<?= trim($active('incident/')) ?>"><?= icon('alert') ?>Problèmes</a>
      <button type="button" data-nav-toggle><?= icon('menu') ?>Menu</button>
    <?php else: ?>
      <a href="<?= e(url('driver/home')) ?>" class="<?= trim($active('driver/home')) ?>"><?= icon('home') ?>Accueil</a>
      <a href="<?= e(url('check/form', ['type' => 'morning'])) ?>"><?= icon('sun') ?>Matin</a>
      <a href="<?= e(url('check/form', ['type' => 'evening'])) ?>"><?= icon('moon') ?>Soir</a>
      <a href="<?= e(url('driver/truck')) ?>" class="<?= trim($active('driver/truck')) ?>"><?= icon('file') ?>Documents</a>
      <a href="<?= e(url('incident/form')) ?>" class="<?= trim($active('incident/form')) ?>"><?= icon('alert') ?>Problème</a>
    <?php endif; ?>
  </nav>
</div>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
