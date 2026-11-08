<div class="breadcrumb"><a href="<?= e(url('driver/home')) ?>">← Accueil</a></div>
<div class="page-head"><h1>Signaler un problème</h1></div>
<form class="card" method="post" action="<?= e(url('incident/store')) ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="alert alert-info" style="margin-bottom:16px">Camion concerné : <strong><?= e($truck['plate']) ?></strong> (<?= e($truck['brand'] . ' ' . $truck['model']) ?>) · Conducteur : <strong><?= e(\App\Middleware\Auth::user()['first_name'] . ' ' . \App\Middleware\Auth::user()['last_name']) ?></strong><br></div>
  <div class="form-grid cols-2">
    <div class="field"><label for="category">Type de problème *</label><select id="category" name="category" required><option value="">— Choisir —</option>
      <?php $oc = old('category'); foreach (labels('incident_category') as $k => $l): ?><option value="<?= e($k) ?>" <?= $oc === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="priority">Niveau de priorité *</label><select id="priority" name="priority" required>
      <?php $op = old('priority', 'medium'); foreach (labels('priority') as $k => $l): ?><option value="<?= e($k) ?>" <?= $op === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="field span-2"><label for="description">Description *</label><textarea id="description" name="description" required minlength="5" maxlength="3000" placeholder="Que se passe-t-il ? Depuis quand ?"><?= old('description') ?></textarea></div>
    <label class="check span-2"><input type="checkbox" name="safety_risk" value="1" <?= !empty($_SESSION['_old']['safety_risk']) ? 'checked' : '' ?>> <span><strong>Risque pour la sécurité</strong> (freins, direction, pneus…) : le véhicule ne doit pas être utilisé avant vérification.</span></label>
    <div class="field span-2"><span class="label">Photo du problème (facultatif)</span>
      <div class="photo-picker" data-photo-picker data-max="<?= (int)$maxPhotos ?>" data-max-bytes="<?= (int)config('max_photo_bytes') ?>">
        <div class="buttons"><button type="button" class="btn btn-blue" data-open="camera">Prendre une photo</button><button type="button" class="btn btn-light" data-open="gallery">Choisir une image</button></div>
        <input type="file" class="hidden" data-input="camera" accept="image/*" capture="environment">
        <input type="file" class="hidden" data-input="gallery" accept="image/jpeg,image/png,image/webp" multiple>
        <input type="file" class="hidden" name="photos[]" accept="image/*" multiple>
        <div class="photo-previews"></div><div class="photo-count muted"></div><div class="photo-error alert alert-error hidden" style="margin-top:8px"></div>
      </div></div>
  </div>
  <div class="form-actions"><button class="btn btn-solid-red" type="submit" data-loading="Envoi…">Envoyer le signalement</button><a class="btn btn-light" href="<?= e(url('driver/home')) ?>">Annuler</a></div>
</form>
