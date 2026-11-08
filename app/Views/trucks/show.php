<?php
use App\Middleware\Auth;
$admin = Auth::is('admin');
$tid = (int)$truck['id'];
$defaultTab = in_array($_GET['tab'] ?? '', ['info', 'documents', 'maintenance', 'history'], true) ? $_GET['tab'] : 'info';
?>
<div class="breadcrumb"><a href="<?= e(url('truck/index')) ?>">← Camions</a></div>
<div class="page-head">
  <h1><span class="plate"><?= e($truck['plate']) ?></span> <?= e($truck['brand'] . ' ' . $truck['model']) ?></h1>
  <div class="actions">
    <a class="btn btn-light" href="<?= e(url('check/compare', ['truck_id' => $tid])) ?>">Comparer matin/soir</a>
    <?php if ($admin): ?>
      <a class="btn btn-light" href="<?= e(url('truck/form', ['id' => $tid])) ?>">Modifier</a>
      <form class="inline" method="post" action="<?= e(url('truck/delete')) ?>" data-confirm="Supprimer définitivement ce camion, ses photos, documents et historique ?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $tid ?>">
        <button class="btn btn-danger" type="submit">Supprimer</button></form>
    <?php endif; ?>
  </div>
</div>
<?php if ($safety): ?><div class="danger-banner"><div>CE VÉHICULE NE DOIT PAS ÊTRE UTILISÉ AVANT VÉRIFICATION<small>Un signalement lié à la sécurité est ouvert.</small></div></div><?php endif; ?>

<div class="tabs" role="tablist">
  <button class="tab" data-tab="info" type="button">Fiche</button>
  <button class="tab" data-tab="documents" type="button">Documents (<?= count($documents) ?>)</button>
  <button class="tab" data-tab="maintenance" type="button">Entretiens (<?= count($maintenance) ?>)</button>
  <button class="tab" data-tab="history" type="button">Historique</button>
</div>
<span data-default-tab="<?= e($defaultTab) ?>" hidden></span>

<div class="tab-panel" data-panel="info">
  <div class="card"><div class="truck-head">
    <div class="truck-photo"><?php if ($truck['photo_path']): ?><img src="<?= e(url('file/truck', ['id' => $tid])) ?>" alt="Photo du camion"><?php else: ?><?= icon('truck') ?><?php endif; ?></div>
    <dl class="kv">
      <div><dt>Statut</dt><dd><?= truck_status_badge($truck['status']) ?></dd></div>
      <div><dt>Détention</dt><dd><?= e(label('ownership', $truck['ownership'])) ?><?= $truck['ownership'] === 'rented' ? ' · ' . e($truck['rental_company']) : '' ?></dd></div>
      <div><dt>Type</dt><dd><?= e($truck['vehicle_type'] ?: '—') ?></dd></div>
      <div><dt>Volume</dt><dd><?= $truck['volume_m3'] ? e(rtrim(rtrim(number_format((float)$truck['volume_m3'], 1, ',', ''), '0'), ',')) . ' m³' : '—' ?></dd></div>
      <div><dt>Année</dt><dd><?= e($truck['year'] ?: '—') ?></dd></div>
      <div><dt>Kilométrage</dt><dd><?= e(fkm((int)$truck['mileage'])) ?></dd></div>
      <div><dt>Dernier entretien</dt><dd><?= e(fdate($truck['last_service_date'])) ?></dd></div>
      <div><dt>Prochain entretien</dt><dd><?= e(fdate($truck['next_service_date'])) ?> <?= $truck['next_service_date'] ? expiry_badge($truck['next_service_date']) : '' ?></dd></div>
      <div><dt>Assurance</dt><dd><?= e($truck['insurance_info'] ?: '—') ?></dd></div>
      <div><dt>Carte grise n°</dt><dd><?= e($truck['registration_number'] ?: '—') ?></dd></div>
    </dl></div>
    <?php if ($truck['notes']): ?><p class="desc muted" style="margin-top:12px"><?= e($truck['notes']) ?></p><?php endif; ?>
  </div>

  <?php if ($truck['ownership'] === 'rented'): ?>
  <section class="card"><h2>Contrat de location</h2>
    <div class="grid grid-2">
      <dl class="kv">
        <div><dt>Loueur</dt><dd><?= e($truck['rental_company'] ?: '—') ?></dd></div>
        <div><dt>N° de contrat</dt><dd><?= e($truck['rental_contract_no'] ?: '—') ?></dd></div>
        <div><dt>Début</dt><dd><?= e(fdate($truck['rental_start_date'])) ?></dd></div>
        <div><dt>Fin</dt><dd><?= e(fdate($truck['rental_end_date'])) ?> <?= $truck['rental_end_date'] ? expiry_badge($truck['rental_end_date']) : '' ?></dd></div>
        <div><dt>Loyer mensuel</dt><dd><?= $truck['rental_monthly_cost'] !== null ? e(number_format((float)$truck['rental_monthly_cost'], 2, ',', ' ')) . ' €' : '—' ?></dd></div>
      </dl>
      <div>
        <h3>Renouveler le contrat</h3>
        <form method="post" action="<?= e(url('truck/renew')) ?>"><?= csrf_field() ?><input type="hidden" name="truck_id" value="<?= $tid ?>">
          <div class="form-grid">
            <div class="field"><label for="new_end_date">Nouvelle date de fin *</label><input id="new_end_date" type="date" name="new_end_date" required min="<?= e(date('Y-m-d', strtotime(max($truck['rental_end_date'] ?: 'today', date('Y-m-d')) . ' +1 day'))) ?>"></div>
            <div class="field"><label for="renew_note">Note</label><input id="renew_note" name="note" maxlength="500" placeholder="Ex. avenant n°2, nouveau loyer…"></div>
          </div>
          <div class="form-actions"><button class="btn" type="submit">Enregistrer le renouvellement</button></div>
        </form>
      </div>
    </div>
    <?php if ($renewals): ?>
      <h3 style="margin-top:16px">Renouvellements précédents</h3>
      <ul class="list">
        <?php foreach ($renewals as $r): ?><li><span><?= e(fdate($r['old_end_date'])) ?> → <strong><?= e(fdate($r['new_end_date'])) ?></strong><?= $r['note'] ? ' · ' . e($r['note']) : '' ?></span>
          <span class="muted small"><?= e(fdate($r['created_at'])) ?><?= $r['by_name'] ? ' · ' . e($r['by_name']) : '' ?></span></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <div class="grid grid-2">
    <section class="card"><h2>Conducteur affecté</h2>
      <p><?= $truck['driver_name'] ? '<strong>' . e($truck['driver_name']) . '</strong> <span class="muted small">depuis le ' . e(fdate($truck['assigned_since'])) . '</span>' : '<span class="muted">Aucun conducteur affecté.</span>' ?></p>
      <form method="post" action="<?= e(url('truck/assign')) ?>" class="filters"><?= csrf_field() ?><input type="hidden" name="truck_id" value="<?= $tid ?>">
        <div class="field"><label for="driver_id">Affecter à</label><select id="driver_id" name="driver_id"><option value="">— Aucun conducteur —</option>
          <?php foreach ($drivers as $d): ?><option value="<?= (int)$d['id'] ?>" <?= (int)($truck['driver_id'] ?? 0) === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['first_name'] . ' ' . $d['last_name']) ?><?= $d['truck_plate'] ? ' (' . e($d['truck_plate']) . ')' : '' ?></option><?php endforeach; ?></select></div>
        <div class="actions"><button class="btn" type="submit">Valider</button></div></form>
      <h3 style="margin-top:16px">Changer le statut</h3>
      <form method="post" action="<?= e(url('truck/status')) ?>" class="filters"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $tid ?>">
        <div class="field"><select name="status" aria-label="Statut"><?php foreach (labels('truck_status') as $k => $l): ?><option value="<?= e($k) ?>" <?= $truck['status'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div class="actions"><button class="btn btn-light" type="submit">Mettre à jour</button></div></form>
    </section>
    <section class="card"><h2>Documents obligatoires</h2>
      <ul class="list">
        <?php foreach ($required as $cat => $doc): ?>
          <li><span><?= e(label('doc_category', $cat)) ?></span>
            <?php if (!$doc): ?><?= badge('Manquant', 'red') ?><?php elseif ($cat === 'registration' && !$doc['expiry_date']): ?><?= badge('Présent', 'green') ?><?php else: ?><?= expiry_badge($doc['expiry_date']) ?> <span class="small muted"><?= e(fdate($doc['expiry_date'])) ?></span><?php endif; ?></li>
        <?php endforeach; ?>
      </ul></section>
  </div>

  <div class="grid grid-2" style="margin-top:16px">
    <section class="card flush"><h2>Derniers contrôles</h2><div class="table-wrap"><table><tbody>
      <?php foreach ($checks as $c): ?><tr><td><a href="<?= e(url('check/show', ['id' => $c['id']])) ?>"><?= e(fdatetime($c['checked_at'])) ?></a></td><td><?= e($c['driver_name']) ?></td><td><?= badge(label('check_type', $c['check_type']), $c['has_anomaly'] ? 'orange' : 'green') ?></td></tr>
      <?php endforeach; if (!$checks): ?><tr><td class="empty">Aucun contrôle.</td></tr><?php endif; ?></tbody></table></div></section>
    <section class="card flush"><h2>Derniers signalements</h2><div class="table-wrap"><table><tbody>
      <?php foreach ($incidents as $i): ?><tr><td><a href="<?= e(url('incident/show', ['id' => $i['id']])) ?>">#<?= (int)$i['id'] ?> <?= e(fdate($i['created_at'])) ?></a></td><td><?= e(label('incident_category', $i['category'])) ?></td><td><?= priority_badge($i['priority']) ?></td><td><?= incident_status_badge($i['status']) ?></td></tr>
      <?php endforeach; if (!$incidents): ?><tr><td class="empty">Aucun signalement.</td></tr><?php endif; ?></tbody></table></div></section>
  </div>
</div>

<div class="tab-panel" data-panel="documents">
  <div class="card">
    <div class="card-head"><h2>Documents</h2></div>
    <?php foreach ($documents as $d): ?>
      <div class="doc-row">
        <div class="info"><strong><?= e($d['name']) ?></strong> <?= badge(label('doc_category', $d['category']), 'neutral') ?><br>
          <span class="muted small">Ajouté le <?= e(fdate($d['created_at'])) ?><?= $d['replaced_at'] ? ' · remplacé le ' . e(fdate($d['replaced_at'])) : '' ?> · <?= e(fsize((int)$d['file_size'])) ?><?= $d['uploader'] ? ' · ' . e($d['uploader']) : '' ?></span>
          <?php if ($d['expiry_date']): ?><br><?= expiry_badge($d['expiry_date']) ?> <span class="small muted">échéance <?= e(fdate($d['expiry_date'])) ?></span><?php endif; ?>
          <?php if ($d['comment']): ?><br><span class="small desc"><?= e($d['comment']) ?></span><?php endif; ?></div>
        <div class="btns">
          <a class="btn btn-light btn-sm" target="_blank" rel="noopener" href="<?= e(url('file/document', ['id' => $d['id']])) ?>">Consulter</a>
          <a class="btn btn-light btn-sm" href="<?= e(url('file/document', ['id' => $d['id'], 'dl' => 1])) ?>">Télécharger</a>
          <?php if ($admin): ?>
            <form class="inline" method="post" action="<?= e(url('document/delete')) ?>" data-confirm="Supprimer ce document ?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><button class="btn btn-danger btn-sm" type="submit"></button></form>
          <?php endif; ?>
        </div>
        <?php if ($admin): ?>
          <details class="box" style="flex-basis:100%"><summary>Remplacer ce document</summary><div class="inner">
            <form method="post" action="<?= e(url('document/replace')) ?>" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
              <div class="form-grid cols-2">
                <div class="field"><label>Nouveau fichier *</label><input type="file" name="file" required accept="application/pdf,image/jpeg,image/png,image/webp"></div>
                <div class="field"><label>Nouvelle date d'expiration</label><input type="date" name="expiry_date" value="<?= e($d['expiry_date']) ?>"></div>
                <div class="field span-2"><label>Commentaire</label><input name="comment" maxlength="1000" value="<?= e($d['comment']) ?>"></div>
              </div><div class="form-actions"><button class="btn btn-sm" type="submit">Remplacer</button></div></form></div></details>
        <?php endif; ?>
      </div>
    <?php endforeach; if (!$documents): ?><div class="empty-state"><p>Aucun document.</p></div><?php endif; ?>
  </div>
  <?php if ($admin): ?>
  <div class="card"><h2>Ajouter un document</h2>
    <form method="post" action="<?= e(url('document/store')) ?>" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="truck_id" value="<?= $tid ?>">
      <div class="form-grid cols-2">
        <div class="field"><label for="doc_cat">Catégorie *</label><select id="doc_cat" name="category" required><?php foreach (labels('doc_category') as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="doc_name">Nom du document</label><input id="doc_name" name="name" maxlength="150" placeholder="Par défaut : la catégorie"></div>
        <div class="field"><label for="doc_file">Fichier (PDF ou image) *</label><input id="doc_file" type="file" name="file" required accept="application/pdf,image/jpeg,image/png,image/webp"><span class="hint"><?= e(fsize((int)config('max_document_bytes'))) ?> maximum.</span></div>
        <div class="field"><label for="doc_exp">Date d'expiration</label><input id="doc_exp" type="date" name="expiry_date"><span class="hint">Obligatoire pour l'assurance et le contrôle technique (alertes automatiques).</span></div>
        <div class="field span-2"><label for="doc_com">Commentaire</label><input id="doc_com" name="comment" maxlength="1000"></div>
      </div><div class="form-actions"><button class="btn" type="submit" data-loading="Envoi…">Ajouter</button></div></form>
  </div>
  <?php endif; ?>
</div>

<div class="tab-panel" data-panel="maintenance">
  <div class="card flush"><h2>Historique des entretiens</h2><div class="table-wrap"><table>
    <thead><tr><th>Date</th><th>Type</th><th>Garage</th><th>Km</th><th>Coût</th><th>Détails</th></tr></thead><tbody>
    <?php foreach ($maintenance as $m): ?><tr><td class="nowrap"><?= e(fdate($m['service_date'])) ?></td><td><?= e($m['service_type']) ?></td><td><?= e($m['garage'] ?: '—') ?></td>
      <td class="nowrap"><?= $m['mileage'] !== null ? e(fkm((int)$m['mileage'])) : '—' ?></td><td class="nowrap"><?= $m['cost'] !== null ? e(number_format((float)$m['cost'], 2, ',', ' ')) . ' €' : '—' ?></td><td class="desc"><?= e($m['description']) ?></td></tr>
    <?php endforeach; if (!$maintenance): ?><tr><td class="empty" colspan="6">Aucun entretien enregistré.</td></tr><?php endif; ?></tbody></table></div></div>
  <div class="card"><h2>Enregistrer un entretien</h2>
    <form method="post" action="<?= e(url('truck/maintenance')) ?>"><?= csrf_field() ?><input type="hidden" name="truck_id" value="<?= $tid ?>">
      <div class="form-grid cols-3">
        <div class="field"><label>Date *</label><input type="date" name="service_date" required max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>"></div>
        <div class="field"><label>Type d'entretien *</label><input name="service_type" required maxlength="120" placeholder="Révision, pneus, freins…"></div>
        <div class="field"><label>Garage</label><input name="garage" maxlength="150"></div>
        <div class="field"><label>Kilométrage</label><input type="number" name="mileage" min="0"></div>
        <div class="field"><label>Coût (€)</label><input name="cost" inputmode="decimal"></div>
        <div class="field"><label>Prochain entretien</label><input type="date" name="next_service_date"></div>
        <div class="field span-2"><label>Détails</label><textarea name="description" maxlength="2000" style="min-height:70px"></textarea></div>
      </div><div class="form-actions"><button class="btn" type="submit">Enregistrer</button></div></form></div>
</div>

<div class="tab-panel" data-panel="history">
  <div class="card flush"><h2>Historique des affectations</h2><div class="table-wrap"><table>
    <thead><tr><th>Conducteur</th><th>Début</th><th>Fin</th><th>Affecté par</th></tr></thead><tbody>
    <?php foreach ($assignments as $a): ?><tr><td><?= e($a['driver_name']) ?></td><td class="nowrap"><?= e(fdatetime($a['start_date'])) ?></td><td class="nowrap"><?= $a['end_date'] ? e(fdatetime($a['end_date'])) : badge('En cours', 'green') ?></td><td><?= e($a['by_name'] ?: '—') ?></td></tr>
    <?php endforeach; if (!$assignments): ?><tr><td class="empty" colspan="4">Aucune affectation.</td></tr><?php endif; ?></tbody></table></div></div>
  <p><a class="btn btn-light" href="<?= e(url('history/index', ['type' => 'checks', 'truck_id' => $tid])) ?>">Historique complet de ce camion</a></p>
</div>
