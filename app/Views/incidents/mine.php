<div class="page-head"><h1>Mes signalements</h1><div class="actions"><a class="btn btn-solid-red" href="<?= e(url('incident/form')) ?>">Signaler un problème</a></div></div>
<div class="card flush"><?php $showDriver = false; require __DIR__ . '/_table.php'; ?><?= $pager->render() ?></div>
