<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = require_role(ROLES_CADASTRO);

$filtroUsuario = (int)($_GET['user_id'] ?? 0);
$filtroEntidade = $_GET['entidade'] ?? '';
$filtroDataIni = $_GET['data_ini'] ?? '';
$filtroDataFim = $_GET['data_fim'] ?? '';

$entidadesValidas = ['usuario', 'maquina', 'item_checklist'];
$entidadeLabels = ['usuario' => 'Usuário', 'maquina' => 'Máquina', 'item_checklist' => 'Item do Checklist'];
$acaoLabels = [
    'criar' => 'Criação',
    'editar' => 'Edição',
    'resetar_senha' => 'Reset de senha',
    'toggle_ativo' => 'Ativar/Desativar',
    'vincular_operador' => 'Vínculo de operador',
    'desvincular_operador' => 'Remoção de vínculo',
];

$where = [];
$params = [];

if ($filtroUsuario) {
    $where[] = 'a.user_id = ?';
    $params[] = $filtroUsuario;
}
if (in_array($filtroEntidade, $entidadesValidas, true)) {
    $where[] = 'a.entidade = ?';
    $params[] = $filtroEntidade;
}
if ($filtroDataIni !== '') {
    $where[] = 'DATE(a.created_at) >= ?';
    $params[] = $filtroDataIni;
}
if ($filtroDataFim !== '') {
    $where[] = 'DATE(a.created_at) <= ?';
    $params[] = $filtroDataFim;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = db()->prepare("
    SELECT a.id, a.acao, a.entidade, a.entidade_id, a.descricao, a.created_at, u.nome AS autor_nome
    FROM auditoria a
    LEFT JOIN usuarios u ON u.id = a.user_id
    $whereSql
    ORDER BY a.created_at DESC
    LIMIT 300
");
$stmt->execute($params);
$registros = $stmt->fetchAll();

$usuariosDisponiveis = db()->query("
    SELECT DISTINCT u.id, u.nome FROM auditoria a JOIN usuarios u ON u.id = a.user_id ORDER BY u.nome
")->fetchAll();

$pageTitle = 'Auditoria';
require __DIR__ . '/../includes/header.php';
?>
<h1>Log de Auditoria</h1>
<p class="hint">Registro de criações, edições e alterações de status feitas no painel administrativo. Mostrando os 300 registros mais recentes.</p>

<form method="get" class="filter-bar">
  <label>Usuário
    <select name="user_id">
      <option value="">Todos</option>
      <?php foreach ($usuariosDisponiveis as $u): ?>
        <option value="<?= $u['id'] ?>" <?= $filtroUsuario == $u['id'] ? 'selected' : '' ?>><?= e($u['nome']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Área
    <select name="entidade">
      <option value="">Todas</option>
      <?php foreach ($entidadeLabels as $val => $label): ?>
        <option value="<?= $val ?>" <?= $filtroEntidade === $val ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>De <input type="date" name="data_ini" value="<?= e($filtroDataIni) ?>"></label>
  <label>Até <input type="date" name="data_fim" value="<?= e($filtroDataFim) ?>"></label>
  <button type="submit" class="btn btn-secondary btn-sm">Filtrar</button>
  <?php if ($filtroUsuario || $filtroEntidade !== '' || $filtroDataIni !== '' || $filtroDataFim !== ''): ?>
    <a href="<?= base_url('admin/auditoria.php') ?>" class="btn btn-secondary btn-sm">Limpar</a>
  <?php endif; ?>
</form>

<div class="table-wrap">
<table class="table">
  <thead><tr><th>Data/Hora</th><th>Usuário</th><th>Área</th><th>Ação</th><th>Descrição</th></tr></thead>
  <tbody>
  <?php foreach ($registros as $r): ?>
    <tr>
      <td><?= e(format_datahora_br($r['created_at'])) ?></td>
      <td><?= e($r['autor_nome'] ?? 'Sistema') ?></td>
      <td><?= e($entidadeLabels[$r['entidade']] ?? $r['entidade']) ?></td>
      <td><?= e($acaoLabels[$r['acao']] ?? $r['acao']) ?></td>
      <td class="whitespace-normal"><?= e($r['descricao']) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$registros): ?><tr><td colspan="5">Nenhum registro encontrado.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
