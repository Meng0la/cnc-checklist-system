<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = require_role(ROLES_GESTAO);
$isCadastro = in_array($user['role'], ROLES_CADASTRO, true);

$editId = isset($_GET['editar']) ? (int)$_GET['editar'] : 0;
$editMaquina = null;
if ($editId) {
    if (!$isCadastro) {
        flash_set('erro', 'Somente administrador ou supervisor podem editar máquinas.');
        redirect(base_url('admin/maquinas.php'));
    }
    $stmt = db()->prepare('SELECT * FROM maquinas WHERE id = ?');
    $stmt->execute([$editId]);
    $editMaquina = $stmt->fetch();
}

$filtroBusca = trim($_GET['busca'] ?? '');
$filtroTipo = trim($_GET['tipo'] ?? '');
$filtroFabricante = trim($_GET['fabricante'] ?? '');
$filtroCriticidade = $_GET['criticidade'] ?? '';
$filtroStatusOp = $_GET['status_operacional'] ?? '';
$filtroStatus = $_GET['status'] ?? '';

$where = [];
$params = [];

if ($filtroBusca !== '') {
    $where[] = '(codigo LIKE ? OR nome LIKE ? OR numero_patrimonio LIKE ?)';
    $params[] = '%' . $filtroBusca . '%';
    $params[] = '%' . $filtroBusca . '%';
    $params[] = '%' . $filtroBusca . '%';
}
if ($filtroTipo !== '') {
    $where[] = 'tipo = ?';
    $params[] = $filtroTipo;
}
if ($filtroFabricante !== '') {
    $where[] = 'fabricante = ?';
    $params[] = $filtroFabricante;
}
if (in_array($filtroCriticidade, ['baixa', 'media', 'alta', 'critica'], true)) {
    $where[] = 'criticidade = ?';
    $params[] = $filtroCriticidade;
}
if (in_array($filtroStatusOp, ['operando', 'manutencao', 'parada'], true)) {
    $where[] = 'status_operacional = ?';
    $params[] = $filtroStatusOp;
}
if ($filtroStatus === 'ativa') {
    $where[] = 'ativo = 1';
} elseif ($filtroStatus === 'inativa') {
    $where[] = 'ativo = 0';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = db()->prepare("SELECT * FROM maquinas $whereSql ORDER BY codigo");
$stmt->execute($params);
$maquinas = $stmt->fetchAll();

$tiposDisponiveis = db()->query("SELECT DISTINCT tipo FROM maquinas WHERE tipo IS NOT NULL AND tipo <> '' ORDER BY tipo")->fetchAll(PDO::FETCH_COLUMN);
$fabricantesDisponiveis = db()->query("SELECT DISTINCT fabricante FROM maquinas WHERE fabricante IS NOT NULL AND fabricante <> '' ORDER BY fabricante")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Máquinas';
require __DIR__ . '/../includes/header.php';
?>
<h1>Máquinas (CNCs)</h1>

<?php if ($isCadastro): ?>
<h2><?= $editMaquina ? 'Editar Máquina' : 'Nova Máquina' ?></h2>
<form method="post" action="<?= base_url('admin/maquinas_salvar.php') ?>" class="form-card">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="acao" value="<?= $editMaquina ? 'editar' : 'criar' ?>">
  <?php if ($editMaquina): ?><input type="hidden" name="id" value="<?= (int)$editMaquina['id'] ?>"><?php endif; ?>
  <label>Código (número usado no chão de fábrica, ex.: "FCNC 08")
    <input type="text" name="codigo" required value="<?= e($editMaquina['codigo'] ?? '') ?>">
  </label>
  <label>Nome / Identificação
    <input type="text" name="nome" required value="<?= e($editMaquina['nome'] ?? '') ?>">
  </label>
  <label>Nº de patrimônio (uso da manutenção)
    <input type="text" name="numero_patrimonio" value="<?= e($editMaquina['numero_patrimonio'] ?? '') ?>">
  </label>
  <label>Tipo
    <input type="text" name="tipo" value="<?= e($editMaquina['tipo'] ?? '') ?>" placeholder="Ex.: Torno CNC, Centro de Usinagem 3 Eixos">
  </label>
  <label>Modelo
    <input type="text" name="modelo" value="<?= e($editMaquina['modelo'] ?? '') ?>">
  </label>
  <label>Fabricante
    <input type="text" name="fabricante" value="<?= e($editMaquina['fabricante'] ?? '') ?>">
  </label>
  <label>Peça produzida
    <input type="text" name="peca_produzida" value="<?= e($editMaquina['peca_produzida'] ?? '') ?>">
  </label>
  <label>Setor / Área
    <input type="text" name="setor" value="<?= e($editMaquina['setor'] ?? '') ?>">
  </label>
  <label>Criticidade
    <select name="criticidade">
      <option value="">—</option>
      <?php foreach (['baixa' => 'Baixa', 'media' => 'Média', 'alta' => 'Alta', 'critica' => 'Crítica'] as $val => $label): ?>
        <option value="<?= $val ?>" <?= ($editMaquina['criticidade'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Horas de operação
    <input type="number" step="0.1" min="0" name="horas_operacao" value="<?= e((string)($editMaquina['horas_operacao'] ?? '0')) ?>">
  </label>
  <label>Próxima revisão
    <input type="date" name="proxima_revisao" value="<?= e($editMaquina['proxima_revisao'] ?? '') ?>">
  </label>
  <label>Status operacional
    <select name="status_operacional">
      <?php foreach (['operando' => 'Operando', 'manutencao' => 'Em Manutenção', 'parada' => 'Parada'] as $val => $label): ?>
        <option value="<?= $val ?>" <?= ($editMaquina['status_operacional'] ?? 'operando') === $val ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <button type="submit" class="btn btn-primary"><?= $editMaquina ? 'Salvar alterações' : 'Cadastrar máquina' ?></button>
  <?php if ($editMaquina): ?><a href="<?= base_url('admin/maquinas.php') ?>" class="btn btn-secondary">Cancelar</a><?php endif; ?>
</form>
<?php endif; ?>

<h2>Máquinas cadastradas</h2>

<form method="get" class="filter-bar">
  <label>Buscar
    <input type="text" name="busca" placeholder="Código, nome ou patrimônio" value="<?= e($filtroBusca) ?>">
  </label>
  <label>Tipo
    <select name="tipo">
      <option value="">Todos</option>
      <?php foreach ($tiposDisponiveis as $t): ?>
        <option value="<?= e($t) ?>" <?= $filtroTipo === $t ? 'selected' : '' ?>><?= e($t) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Fabricante
    <select name="fabricante">
      <option value="">Todos</option>
      <?php foreach ($fabricantesDisponiveis as $f): ?>
        <option value="<?= e($f) ?>" <?= $filtroFabricante === $f ? 'selected' : '' ?>><?= e($f) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Criticidade
    <select name="criticidade">
      <option value="">Todas</option>
      <?php foreach (['baixa' => 'Baixa', 'media' => 'Média', 'alta' => 'Alta', 'critica' => 'Crítica'] as $val => $label): ?>
        <option value="<?= $val ?>" <?= $filtroCriticidade === $val ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Status operacional
    <select name="status_operacional">
      <option value="">Todos</option>
      <?php foreach (['operando' => 'Operando', 'manutencao' => 'Em Manutenção', 'parada' => 'Parada'] as $val => $label): ?>
        <option value="<?= $val ?>" <?= $filtroStatusOp === $val ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Cadastro
    <select name="status">
      <option value="">Todos</option>
      <option value="ativa" <?= $filtroStatus === 'ativa' ? 'selected' : '' ?>>Ativa</option>
      <option value="inativa" <?= $filtroStatus === 'inativa' ? 'selected' : '' ?>>Inativa</option>
    </select>
  </label>
  <button type="submit" class="btn btn-secondary btn-sm">Filtrar</button>
  <?php if ($filtroBusca !== '' || $filtroTipo !== '' || $filtroFabricante !== '' || $filtroCriticidade !== '' || $filtroStatusOp !== '' || $filtroStatus !== ''): ?>
    <a href="<?= base_url('admin/maquinas.php') ?>" class="btn btn-secondary btn-sm">Limpar</a>
  <?php endif; ?>
</form>
<p class="hint"><?= count($maquinas) ?> máquina(s) encontrada(s).</p>

<div class="table-wrap">
<table class="table">
  <thead><tr><th>Código</th><th>Nome</th><th>Nº patrimônio</th><th>Tipo</th><th>Fabricante</th><th>Criticidade</th><th>Horas op.</th><th>Status</th><th>Cadastro</th><th>Ações</th></tr></thead>
  <tbody>
  <?php
    $criticidadeLabels = ['baixa' => 'Baixa', 'media' => 'Média', 'alta' => 'Alta', 'critica' => 'Crítica'];
    $statusOpLabels = ['operando' => 'Operando', 'manutencao' => 'Em Manutenção', 'parada' => 'Parada'];
  ?>
  <?php foreach ($maquinas as $m): ?>
    <tr>
      <td><?= e($m['codigo']) ?></td>
      <td><?= e($m['nome']) ?></td>
      <td><?= e($m['numero_patrimonio'] ?? '') ?></td>
      <td><?= e($m['tipo'] ?? '') ?></td>
      <td><?= e($m['fabricante'] ?? '') ?></td>
      <td>
        <?php if ($m['criticidade']): ?>
          <span class="badge <?= in_array($m['criticidade'], ['alta', 'critica'], true) ? 'badge-pendente' : 'badge-ok' ?>"><?= e($criticidadeLabels[$m['criticidade']] ?? $m['criticidade']) ?></span>
        <?php endif; ?>
      </td>
      <td><?= e(number_format((float)$m['horas_operacao'], 1, ',', '.')) ?> h</td>
      <td><?= $m['status_operacional'] === 'operando' ? '<span class="badge badge-ok">Operando</span>' : '<span class="badge badge-pendente">' . e($statusOpLabels[$m['status_operacional']] ?? $m['status_operacional']) . '</span>' ?></td>
      <td><?= $m['ativo'] ? '<span class="badge badge-ok">Ativa</span>' : '<span class="badge badge-pendente">Inativa</span>' ?></td>
      <td class="actions">
        <?php if ($isCadastro): ?>
        <a href="<?= base_url('admin/maquinas.php?editar=' . $m['id']) ?>">Editar</a>
        <a href="<?= base_url('admin/maquina_operadores.php?maquina_id=' . $m['id']) ?>">Operadores</a>
        <form method="post" action="<?= base_url('admin/maquinas_salvar.php') ?>" class="inline-form" data-confirm="Confirma alterar o status desta máquina?">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="acao" value="toggle_ativo">
          <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
          <button type="submit" class="link-button"><?= $m['ativo'] ? 'Desativar' : 'Ativar' ?></button>
        </form>
        <?php else: ?>
          <span class="hint">Somente leitura</span>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
