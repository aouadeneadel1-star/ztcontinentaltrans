<?php $t = $truck ?? []; $v = static fn(string $k) => old($k, $t[$k] ?? ''); ?>
<div class="page-head"><h1><?= e($title) ?></h1></div>
<form class="card" method="post" action="<?= e(url('truck/save')) ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <?php if ($truck): ?><input type="hidden" name="id" value="<?= (int)$truck['id'] ?>"><?php endif; ?>
  <div class="form-grid cols-3">
    <div class="field"><label for="plate">Immatriculation *</label><input id="plate" name="plate" required maxlength="20" style="text-transform:uppercase" value="<?= $v('plate') ?>"></div>
    <div class="field"><label for="brand">Marque *</label><input id="brand" name="brand" required maxlength="80" value="<?= $v('brand') ?>"></div>
    <div class="field"><label for="model">Modèle *</label><input id="model" name="model" required maxlength="80" value="<?= $v('model') ?>"></div>
    <div class="field"><label for="year">Année de mise en circulation</label><input id="year" name="year" type="number" min="1980" max="<?= (int)date('Y') + 1 ?>" value="<?= $v('year') ?>"></div>
    <div class="field"><label for="vehicle_type">Type de véhicule</label><input id="vehicle_type" name="vehicle_type" maxlength="60" list="types" placeholder="Fourgon, porteur…" value="<?= $v('vehicle_type') ?>">
      <datalist id="types"><option>Fourgon</option><option>Porteur</option><option>Camion plateau</option><option>Hayon élévateur</option></datalist></div>
    <div class="field"><label for="volume_m3">Volume (m³)</label><input id="volume_m3" name="volume_m3" inputmode="decimal" placeholder="9, 12, 16, 20…" value="<?= $v('volume_m3') ?>"></div>
    <div class="field"><label for="mileage">Kilométrage actuel</label><input id="mileage" name="mileage" type="number" min="0" value="<?= $v('mileage') ?>"></div>
    <div class="field"><label for="last_service_date">Dernier entretien</label><input id="last_service_date" name="last_service_date" type="date" value="<?= $v('last_service_date') ?>"></div>
    <div class="field"><label for="next_service_date">Prochain entretien</label><input id="next_service_date" name="next_service_date" type="date" value="<?= $v('next_service_date') ?>"></div>
    <div class="field"><label for="insurance_info">Assurance (assureur / contrat)</label><input id="insurance_info" name="insurance_info" maxlength="190" value="<?= $v('insurance_info') ?>"></div>
    <div class="field"><label for="registration_number">N° de carte grise</label><input id="registration_number" name="registration_number" maxlength="60" value="<?= $v('registration_number') ?>"></div>
    <div class="field"><label for="status">Statut</label><select id="status" name="status">
      <?php $cur = old('status', $t['status'] ?? 'available'); foreach (labels('truck_status') as $k => $l): ?><option value="<?= e($k) ?>" <?= $cur === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="ownership">Mode de détention</label><select id="ownership" name="ownership" data-rental-toggle>
      <?php $co = old('ownership', $t['ownership'] ?? 'owned'); foreach (labels('ownership') as $k => $l): ?><option value="<?= e($k) ?>" <?= $co === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <fieldset class="rental-fields span-2 cols-3" data-rental-fields>
      <legend>Contrat de location</legend>
      <div class="form-grid cols-3">
        <div class="field"><label for="rental_company">Loueur *</label><input id="rental_company" name="rental_company" maxlength="150" value="<?= $v('rental_company') ?>"></div>
        <div class="field"><label for="rental_contract_no">N° de contrat</label><input id="rental_contract_no" name="rental_contract_no" maxlength="80" value="<?= $v('rental_contract_no') ?>"></div>
        <div class="field"><label for="rental_monthly_cost">Loyer mensuel (€)</label><input id="rental_monthly_cost" name="rental_monthly_cost" inputmode="decimal" value="<?= $v('rental_monthly_cost') ?>"></div>
        <div class="field"><label for="rental_start_date">Début de location</label><input id="rental_start_date" name="rental_start_date" type="date" value="<?= $v('rental_start_date') ?>"></div>
        <div class="field"><label for="rental_end_date">Fin de location *</label><input id="rental_end_date" name="rental_end_date" type="date" value="<?= $v('rental_end_date') ?>"><span class="hint">Des alertes e-mail sont envoyées avant cette date.</span></div>
      </div>
    </fieldset>
    <div class="field span-2"><label for="photo">Photo du camion</label><input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp">
      <span class="hint">JPEG, PNG ou WebP – <?= e(fsize((int)config('max_photo_bytes'))) ?> maximum.<?= !empty($t['photo_path']) ? ' Une photo existe déjà : en choisir une nouvelle la remplace.' : '' ?></span></div>
    <div class="field span-2"><label for="notes">Notes</label><textarea id="notes" name="notes" maxlength="2000"><?= $v('notes') ?></textarea></div>
  </div>
  <div class="form-actions"><button class="btn" type="submit" data-loading="Enregistrement…">Enregistrer</button>
    <a class="btn btn-light" href="<?= e($truck ? url('truck/show', ['id' => $truck['id']]) : url('truck/index')) ?>">Annuler</a></div>
</form>
