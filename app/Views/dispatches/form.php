<?php
$x = $d ?? [];
$v = static fn(string $k, $def = '') => old($k, $x[$k] ?? $def);
$sel = static fn(string $k, $val) => (string)old($k, (string)($x[$k] ?? '')) === (string)$val ? 'selected' : '';
$oldTours = $_SESSION['_old']['tours'] ?? null;
$rows = [];
if (is_array($oldTours)) {
    foreach ($oldTours as $r) { $rows[] = ['id' => $r['id'] ?? '', 'kind' => $r['kind'] ?? 'tour', 'client' => $r['client'] ?? '', 'city' => $r['city'] ?? '', 'original_name' => '']; }
} else {
    $rows = $tours;
}
if (!$rows) { $rows = [['id' => '', 'kind' => 'tour', 'client' => '', 'city' => '', 'original_name' => '']]; }
$row = static function (array $r, string $i): string {
    ob_start(); ?>
    <div class="tour-row" data-tour-row>
      <input type="hidden" name="tours[<?= $i ?>][id]" value="<?= e($r['id'] ?? '') ?>">
      <div class="form-grid cols-3">
        <div class="field"><label>Type</label><select name="tours[<?= $i ?>][kind]"><?php foreach (labels('tour_kind') as $k => $l): ?><option value="<?= e($k) ?>" <?= ($r['kind'] ?? 'tour') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Client *</label><input name="tours[<?= $i ?>][client]" maxlength="120" placeholder="IKEA, Leroy Merlin…" value="<?= e($r['client'] ?? '') ?>"></div>
        <div class="field"><label>Ville *</label><input name="tours[<?= $i ?>][city]" maxlength="120" value="<?= e($r['city'] ?? '') ?>"></div>
      </div>
      <div class="field"><label>Feuille de tournée (PDF ou photo)</label><input type="file" name="tour_file[<?= $i ?>]" accept="application/pdf,image/jpeg,image/png,image/webp">
        <?php if (!empty($r['original_name'])): ?><span class="hint">Actuel : <?= e($r['original_name']) ?>. Choisir un fichier pour le remplacer.</span><?php endif; ?></div>
      <button type="button" class="btn btn-light btn-sm" data-tour-remove>Retirer</button>
    </div>
    <?php return (string)ob_get_clean();
};
?>
<div class="page-head"><h1><?= e($title) ?></h1></div>
<form class="card" method="post" action="<?= e(url('dispatch/save')) ?>" enctype="multipart/form-data" data-tours-form data-max="<?= (int)$max ?>">
  <?= csrf_field() ?>
  <?php if ($d): ?><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><?php endif; ?>
  <div class="form-grid cols-2">
    <div class="field"><label for="work_date">Date *</label><input id="work_date" type="date" name="work_date" required value="<?= $v('work_date', date('Y-m-d', strtotime('+1 day'))) ?>"></div>
    <div class="field"><label for="driver_id">Conducteur *</label><select id="driver_id" name="driver_id" required data-driver-select><option value="">— Choisir —</option>
      <?php foreach ($drivers as $u): ?><option value="<?= (int)$u['id'] ?>" data-truck="<?= (int)$u['truck_id'] ?>" <?= $sel('driver_id', $u['id']) ?>><?= e($u['last_name'] . ' ' . $u['first_name']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="truck_id">Camion *</label><select id="truck_id" name="truck_id" required data-truck-select><option value="">— Choisir —</option>
      <?php foreach ($trucks as $t): ?><option value="<?= (int)$t['id'] ?>" <?= $sel('truck_id', $t['id']) ?>><?= e($t['plate'] . ' – ' . $t['brand'] . ' ' . $t['model']) ?></option><?php endforeach; ?></select>
      <span class="hint">Le camion habituel du conducteur est proposé automatiquement.</span></div>
    <div></div>
    <div class="field"><label for="helper1_id">Rippeur 1</label><select id="helper1_id" name="helper1_id"><option value="">— Aucun —</option>
      <?php foreach ($helpers as $h): ?><option value="<?= (int)$h['id'] ?>" <?= $sel('helper1_id', $h['id']) ?>><?= e($h['last_name'] . ' ' . $h['first_name']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="helper2_id">Rippeur 2</label><select id="helper2_id" name="helper2_id"><option value="">— Aucun —</option>
      <?php foreach ($helpers as $h): ?><option value="<?= (int)$h['id'] ?>" <?= $sel('helper2_id', $h['id']) ?>><?= e($h['last_name'] . ' ' . $h['first_name']) ?></option><?php endforeach; ?></select>
      <?php if (!$helpers): ?><span class="hint"><a href="<?= e(url('helper/form')) ?>">Ajouter un rippeur</a></span><?php endif; ?></div>
  </div>

  <h2 class="section-title">Missions du jour</h2>
  <div data-tours>
    <?php foreach ($rows as $i => $r): echo $row($r, (string)$i); endforeach; ?>
  </div>
  <template data-tour-template><?= $row(['id' => '', 'kind' => 'tour', 'client' => '', 'city' => ''], '__i__') ?></template>
  <button type="button" class="btn btn-light" data-tour-add>+ Ajouter une tournée ou une course</button>

  <div class="field"><label for="note">Remarque (facultatif)</label><textarea id="note" name="note" rows="2" maxlength="500"><?= e(old('note', $x['note'] ?? '')) ?></textarea></div>
  <div class="form-actions"><button class="btn" type="submit">Enregistrer et voir l'aperçu</button><a class="btn btn-light" href="<?= e(url('dispatch/index')) ?>">Annuler</a></div>
</form>
