<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?> – <?= e(config('name')) ?></title>
<meta name="theme-color" content="#f6f7f9">
<meta name="robots" content="noindex, nofollow">
<link rel="manifest" href="<?= e(base_path()) ?>/manifest.webmanifest">
<link rel="icon" type="image/png" href="<?= e(asset('images/icon-192.png')) ?>">
<link rel="apple-touch-icon" href="<?= e(asset('images/icon-192.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<div class="auth-wrap">
  <div style="width:100%;max-width:410px">
    <div class="auth-card">
      <?php foreach (\App\Helpers\Session::pullFlash() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>"><?= nl2br(e($f['message'])) ?></div>
      <?php endforeach; ?>
      <?= $content ?>
    </div>
    <div class="auth-footer">© <?= date('Y') ?> <?= e(config('name')) ?></div>
  </div>
</div>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
