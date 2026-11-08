<?php $isMorning = $type === 'morning'; ?>
<div class="breadcrumb"><a href="<?= e(url('driver/home')) ?>">← Accueil</a></div>
<div class="page-head"><h1><?= e($title) ?></h1></div>
<?php if ($safety): ?><div class="danger-banner"><div>VÉHICULE À NE PAS UTILISER AVANT VÉRIFICATION<small>Un problème de sécurité est ouvert sur ce camion.</small></div></div><?php endif; ?>
<div class="card truck-line"><span class="plate"><?= e($truck['plate']) ?></span><span><?= e($truck['brand'] . ' ' . $truck['model']) ?></span><span class="meta">Dernier kilométrage : <?= e(fkm((int)$truck['mileage'])) ?></span></div>

<form class="card" method="post" action="<?= e(url('check/store', ['type' => $type])) ?>" enctype="multipart/form-data">
  <?= csrf_field() ?><input type="hidden" name="type" value="<?= e($type) ?>">
  <div class="field"><span class="label">Photos du camion *</span>
    <div class="photo-picker" data-photo-picker data-max="<?= (int)$maxPhotos ?>" data-max-bytes="<?= (int)config('max_photo_bytes') ?>">
      <div class="buttons">
        <button type="button" class="btn btn-blue" data-open="camera">Prendre une photo</button>
        <button type="button" class="btn btn-light" data-open="gallery">Choisir une image</button>
      </div>
      <input type="file" class="hidden" data-input="camera" accept="image/*" capture="environment">
      <input type="file" class="hidden" data-input="gallery" accept="image/jpeg,image/png,image/webp" multiple>
      <input type="file" class="hidden" name="photos[]" accept="image/*" multiple>
      <div class="photo-previews"></div>
      <div class="photo-count muted"></div>
      <div class="photo-error alert alert-error hidden" style="margin-top:8px"></div>
    </div>
    <span class="hint">Avant, côtés, arrière, intérieur de la caisse. La date et l'heure sont enregistrées automatiquement.</span>
  </div>
  <div class="form-grid" style="margin-top:16px">
    <div class="field"><label for="mileage">Kilométrage (facultatif)</label><input id="mileage" name="mileage" type="number" inputmode="numeric" min="<?= (int)$truck['mileage'] ?>" placeholder="<?= (int)$truck['mileage'] ?>" value="<?= old('mileage') ?>"></div>
    <div class="field"><label for="comment">Commentaire (facultatif)</label><textarea id="comment" name="comment" maxlength="1500" placeholder="Rayure, voyant allumé, propreté…"><?= old('comment') ?></textarea></div>
    <label class="check"><input type="checkbox" name="has_anomaly" value="1" <?= !empty($_SESSION['_old']['has_anomaly']) ? 'checked' : '' ?>> Je constate une anomalie (le responsable sera prévenu par e-mail)</label>
  </div>
  <div class="form-actions"><button class="btn btn-block" type="submit" data-loading="Envoi des photos…">Confirmer le contrôle</button></div>
</form>
