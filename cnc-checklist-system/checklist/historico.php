<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = require_login();
$isGestao = in_array($user['role'], ROLES_GESTAO, true);

$filtroMaquina = isset($_GET['maquina_id']) ? (int)$_GET['maquina_id'] : 0;
$filtroStatus = $_GET['status'] ?? '';
$filtroDataIni = $_GET['data_ini'] ?? '';
$filtroDataFim = $_GET['data_fim'] ?? '';
$filtroTurno = $_GET['turno'] ?? '';
$filtroOperador = trim($_GET['operador'] ?? '');

$where = [];
$params = [];

if (!$isGestao) {
    $where[] = 'c.user_id = ?';
    $params[] = $user['id'];
}
if ($filtroMaquina) {
    $where[] = 'c.maquina_id = ?';
    $params[] = $filtroMaquina;
}
if (in_array($filtroStatus, ['conforme', 'nao_conforme'], true)) {
    $where[] = 'c.status_geral = ?';
    $params[] = $filtroStatus;
}
if ($filtroDataIni !== '') {
    $where[] = 'c.data_checklist >= ?';
    $params[] = $filtroDataIni;
}
if ($filtroDataFim !== '') {
    $where[] = 'c.data_checklist <= ?';
    $params[] = $filtroDataFim;
}
if (in_array($filtroTurno, ['manha', 'tarde', 'noite'], true)) {
    $where[] = 'c.turno = ?';
    $params[] = $filtroTurno;
}
if ($isGestao && $filtroOperador !== '') {
    $where[] = '(u.nome LIKE ? OR u.matricula LIKE ?)';
    $params[] = '%' . $filtroOperador . '%';
    $params[] = '%' . $filtroOperador . '%';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = db()->prepare("
    SELECT c.id, c.data_checklist, c.tipo_checklist, c.turno, c.status_geral, c.visto_por,
           m.codigo AS maquina_codigo, m.nome AS maquina_nome, m.numero_patrimonio, u.nome AS operador_nome
    FROM checklists c
    JOIN maquinas m ON m.id = c.maquina_id
    JOIN usuarios u ON u.id = c.user_id
    $whereSql
    ORDER BY c.data_checklist DESC, c.created_at DESC
    LIMIT 200
");
$stmt->execute($params);
$checklists = $stmt->fetchAll();

$maquinas = db()->query('SELECT id, codigo, nome FROM maquinas ORDER BY codigo')->fetchAll();

$pageTitle = 'Histórico de Checklists';
require __DIR__ . '/../includes/header.php';
?>
<h1>Histórico de Checklists</h1>

<form method="get" class="filter-bar">
  <?php if ($isGestao): ?>
  <label>Operador
    <input type="text" name="operador" placeholder="Nome ou matrícula" value="<?= e($filtroOperador) ?>">
  </label>
  <select name="maquina_id">
    <option value="">Todas as máquinas</option>
    <?php foreach ($maquinas as $m): ?>
      <option value="<?= $m['id'] ?>" <?= $filtroMaquina == $m['id'] ? 'selected' : '' ?>><?= e($m['codigo']) ?> — <?= e($m['nome']) ?></option>
    <?php endforeach; ?>
  </select>
  <?php endif; ?>
  <select name="turno">
    <option value="">Todos os turnos</option>
    <option value="manha" <?= $filtroTurno === 'manha' ? 'selected' : '' ?>>Manhã</option>
    <option value="tarde" <?= $filtroTurno === 'tarde' ? 'selected' : '' ?>>Tarde</option>
    <option value="noite" <?= $filtroTurno === 'noite' ? 'selected' : '' ?>>Noite</option>
  </select>
  <select name="status">
    <option value="">Todos os status</option>
    <option value="conforme" <?= $filtroStatus === 'conforme' ? 'selected' : '' ?>>Conforme</option>
    <option value="nao_conforme" <?= $filtroStatus === 'nao_conforme' ? 'selected' : '' ?>>Não Conforme</option>
  </select>
  <label>De <input type="date" name="data_ini" value="<?= e($filtroDataIni) ?>"></label>
  <label>Até <input type="date" name="data_fim" value="<?= e($filtroDataFim) ?>"></label>
  <button type="submit" class="btn btn-secondary btn-sm">Filtrar</button>
  <?php if ($filtroMaquina || $filtroStatus !== '' || $filtroDataIni !== '' || $filtroDataFim !== '' || $filtroTurno !== '' || $filtroOperador !== ''): ?>
    <a href="<?= base_url('checklist/historico.php') ?>" class="btn btn-secondary btn-sm">Limpar</a>
  <?php endif; ?>
  <a href="<?= base_url('checklist/historico_exportar.php?' . http_build_query($_GET)) ?>" class="btn btn-secondary btn-sm">Exportar CSV</a>
</form>
<p class="hint"><?= count($checklists) ?> checklist(s) encontrado(s)<?= count($checklists) >= 200 ? ' (mostrando os 200 mais recentes — refine o filtro para ver todos)' : '' ?>.</p>

<div class="table-wrap">
<table class="table">
  <thead><tr><th>Data</th><th>Máquina</th><?php if ($isGestao): ?><th>Operador</th><?php endif; ?><th>Tipo</th><th>Turno</th><th>Status</th><th>Confirmação</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($checklists as $c): ?>
    <tr>
      <td><?= e(format_data_br($c['data_checklist'])) ?></td>
      <td>
        <?= e($c['maquina_codigo']) ?> — <?= e($c['maquina_nome']) ?>
        <?php if ($c['numero_patrimonio']): ?><br><small class="hint">Patrimônio: <?= e($c['numero_patrimonio']) ?></small><?php endif; ?>
      </td>
      <?php if ($isGestao): ?><td><?= e($c['operador_nome']) ?></td><?php endif; ?>
      <td><?= e(tipo_checklist_label($c['tipo_checklist'])) ?></td>
      <td><?= e(turno_label($c['turno'])) ?></td>
      <td><?= $c['status_geral'] === 'conforme' ? '<span class="badge badge-ok">Conforme</span>' : '<span class="badge badge-pendente">Não Conforme</span>' ?></td>
      <td>
        <?php if ($c['status_geral'] !== 'nao_conforme'): ?>
          —
        <?php elseif ($c['visto_por']): ?>
          <span class="badge badge-ok">Visto</span>
        <?php else: ?>
          <span class="badge badge-pendente">Não confirmado</span>
        <?php endif; ?>
      </td>
      <td><a href="<?= base_url('checklist/detalhe.php?id=' . $c['id']) ?>">Ver</a></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$checklists): ?><tr><td colspan="8">Nenhum checklist encontrado.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
