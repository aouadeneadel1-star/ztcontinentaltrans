<div class="table-wrap"><table>
  <thead><tr><th class="hide-sm">N°</th><th>Date</th><th>Camion</th><?php if ($showDriver): ?><th class="hide-sm">Conducteur</th><?php endif; ?><th>Type</th><th>Priorité</th><th>Statut</th><th></th></tr></thead><tbody>
  <?php foreach ($incidents as $i): ?>
    <tr class="row-<?= e($i['priority']) ?>">
      <td class="hide-sm"><a href="<?= e(url('incident/show', ['id' => $i['id']])) ?>">#<?= (int)$i['id'] ?></a></td>
      <td class="nowrap"><a href="<?= e(url('incident/show', ['id' => $i['id']])) ?>"><?= e(fdatetime($i['created_at'])) ?></a></td>
      <td><span class="plate"><?= e($i['plate']) ?></span></td>
      <?php if ($showDriver): ?><td class="hide-sm"><?= e($i['driver_name']) ?></td><?php endif; ?>
      <td><?= e(label('incident_category', $i['category'])) ?><br><span class="muted small"><?= e(mb_strimwidth($i['description'], 0, 70, '…')) ?></span></td>
      <td><?= priority_badge($i['priority']) ?></td>
      <td><?= incident_status_badge($i['status']) ?></td>
      <td><?= $i['safety_risk'] && $i['status'] !== 'resolved' ? badge('Sécurité', 'red') : '' ?></td>
    </tr>
  <?php endforeach; if (!$incidents): ?><tr><td class="empty" colspan="8">Aucun signalement.</td></tr><?php endif; ?>
  </tbody></table></div>
