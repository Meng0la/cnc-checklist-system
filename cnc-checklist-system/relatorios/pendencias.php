<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = require_role(ROLES_GESTAO);

$data = $_GET['data'] ?? date('Y-m-d');
if (!DateTime::createFromFormat('Y-m-d', $data)) {
    $data = date('Y-m-d');
}

$pendencias = get_pendencias($data);

$pageTitle = 'Pendências';
require __DIR__ . '/../includes/header.php';
?>
<h1>Checklists Pendentes</h1>
<p class="hint">Máquinas ativas que ainda não tiveram checklist registrado por nenhum operador na data selecionada.</p>
<form method="get" class="filter-bar">
  <label>Data <input type="date" name="data" value="<?= e($data) ?>"></label>
  <button type="submit" class="btn btn-secondary btn-sm">Filtrar</button>
</form>

<div class="table-wrap">
<table class="table">
  <thead><tr><th>Máquina</th><th>Tipo pendente</th></tr></thead>
  <tbody>
  <?php foreach ($pendencias as $p): ?>
    <tr>
      <td><?= e($p['codigo']) ?> — <?= e($p['maquina_nome']) ?></td>
      <td><span class="badge badge-pendente"><?= e(tipo_checklist_label($p['tipo_checklist'])) ?></span></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$pendencias): ?><tr><td colspan="2">Nenhuma pendência para a data selecionada.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
