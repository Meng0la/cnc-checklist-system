<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = require_role(ROLES_CADASTRO);
$isAdmin = $user['role'] === ROLE_ADMIN;

$editId = isset($_GET['editar']) ? (int)$_GET['editar'] : 0;
$editUser = null;
if ($editId) {
    $stmt = db()->prepare('SELECT * FROM usuarios WHERE id = ?');
    $stmt->execute([$editId]);
    $editUser = $stmt->fetch();

    if ($editUser && !pode_gerenciar_role($editUser['role'], $isAdmin)) {
        flash_set('erro', 'Supervisor de Produção só pode gerenciar usuários com perfil Operador.');
        redirect(base_url('admin/usuarios.php'));
    }
}

$rolesDisponiveis = $isAdmin ? ROLES_TODAS : [ROLE_OPERADOR];

$filtroBusca = trim($_GET['busca'] ?? '');
$filtroRole = $_GET['role'] ?? '';
$filtroSetor = trim($_GET['setor'] ?? '');
$filtroStatus = $_GET['status'] ?? '';

$where = [];
$params = [];

if ($filtroBusca !== '') {
    $where[] = '(nome LIKE ? OR matricula LIKE ?)';
    $params[] = '%' . $filtroBusca . '%';
    $params[] = '%' . $filtroBusca . '%';
}
if (in_array($filtroRole, ROLES_TODAS, true)) {
    $where[] = 'role = ?';
    $params[] = $filtroRole;
}
if ($filtroSetor !== '') {
    $where[] = 'setor = ?';
    $params[] = $filtroSetor;
}
if ($filtroStatus === 'ativo') {
    $where[] = 'ativo = 1';
} elseif ($filtroStatus === 'inativo') {
    $where[] = 'ativo = 0';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = db()->prepare("SELECT id, matricula, nome, email, role, setor, ativo, primeiro_login, ultimo_login FROM usuarios $whereSql ORDER BY nome");
$stmt->execute($params);
$usuarios = $stmt->fetchAll();

$setoresDisponiveis = db()->query("SELECT DISTINCT setor FROM usuarios WHERE setor IS NOT NULL AND setor <> '' ORDER BY setor")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Usuários';
require __DIR__ . '/../includes/header.php';
?>
<h1>Usuários</h1>

<h2><?= $editUser ? 'Editar Usuário' : 'Novo Usuário' ?></h2>
<form method="post" action="<?= base_url('admin/usuarios_salvar.php') ?>" class="form-card">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="acao" value="<?= $editUser ? 'editar' : 'criar' ?>">
  <?php if ($editUser): ?><input type="hidden" name="id" value="<?= (int)$editUser['id'] ?>"><?php endif; ?>

  <label>Matrícula
    <input type="text" name="matricula" required value="<?= e($editUser['matricula'] ?? '') ?>" <?= $editUser ? 'readonly' : '' ?>>
  </label>
  <label>Nome completo
    <input type="text" name="nome" required value="<?= e($editUser['nome'] ?? '') ?>">
  </label>
  <label>E-mail
    <input type="email" name="email" value="<?= e($editUser['email'] ?? '') ?>" placeholder="opcional">
    <small class="hint">Só é usado para receber alertas de não conformidade (perfis de gestão). Deixe em branco se não quiser notificações.</small>
  </label>
  <label>Perfil de acesso
    <select name="role" required>
      <?php foreach ($rolesDisponiveis as $r): ?>
        <option value="<?= $r ?>" <?= ($editUser['role'] ?? '') === $r ? 'selected' : '' ?>><?= e(role_label($r)) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if (!$isAdmin): ?><small class="hint">Supervisor de Produção só pode criar ou editar contas com perfil Operador.</small><?php endif; ?>
  </label>
  <?php if (!$editUser): ?>
    <p class="hint">O usuário será criado com a senha padrão <strong><?= e(DEFAULT_PASSWORD) ?></strong> e deverá trocá-la no primeiro acesso.</p>
  <?php endif; ?>
  <button type="submit" class="btn btn-primary"><?= $editUser ? 'Salvar alterações' : 'Criar usuário' ?></button>
  <?php if ($editUser): ?><a href="<?= base_url('admin/usuarios.php') ?>" class="btn btn-secondary">Cancelar</a><?php endif; ?>
</form>

<h2>Usuários cadastrados</h2>

<form method="get" class="filter-bar">
  <label>Buscar
    <input type="text" name="busca" placeholder="Nome ou matrícula" value="<?= e($filtroBusca) ?>">
  </label>
  <label>Perfil
    <select name="role">
      <option value="">Todos</option>
      <?php foreach (ROLES_TODAS as $r): ?>
        <option value="<?= $r ?>" <?= $filtroRole === $r ? 'selected' : '' ?>><?= e(role_label($r)) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Setor
    <select name="setor">
      <option value="">Todos</option>
      <?php foreach ($setoresDisponiveis as $s): ?>
        <option value="<?= e($s) ?>" <?= $filtroSetor === $s ? 'selected' : '' ?>><?= e($s) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Status
    <select name="status">
      <option value="">Todos</option>
      <option value="ativo" <?= $filtroStatus === 'ativo' ? 'selected' : '' ?>>Ativo</option>
      <option value="inativo" <?= $filtroStatus === 'inativo' ? 'selected' : '' ?>>Inativo</option>
    </select>
  </label>
  <button type="submit" class="btn btn-secondary btn-sm">Filtrar</button>
  <?php if ($filtroBusca !== '' || $filtroRole !== '' || $filtroSetor !== '' || $filtroStatus !== ''): ?>
    <a href="<?= base_url('admin/usuarios.php') ?>" class="btn btn-secondary btn-sm">Limpar</a>
  <?php endif; ?>
</form>
<p class="hint"><?= count($usuarios) ?> usuário(s) encontrado(s).</p>

<div class="table-wrap">
<table class="table">
  <thead><tr><th>Matrícula</th><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Setor</th><th>Status</th><th>Último login</th><th>Ações</th></tr></thead>
  <tbody>
  <?php foreach ($usuarios as $u): ?>
    <tr>
      <td><?= e($u['matricula']) ?></td>
      <td><?= e($u['nome']) ?></td>
      <td><?= e($u['email'] ?? '') ?: '—' ?></td>
      <td><?= e(role_label($u['role'])) ?></td>
      <td><?= e($u['setor'] ?? '') ?: '—' ?></td>
      <td>
        <?= $u['ativo'] ? '<span class="badge badge-ok">Ativo</span>' : '<span class="badge badge-pendente">Inativo</span>' ?>
        <?php if ($u['primeiro_login']): ?><br><small>Aguardando 1º login</small><?php endif; ?>
      </td>
      <td><?= $u['ultimo_login'] ? e(format_datahora_br($u['ultimo_login'])) : '—' ?></td>
      <td class="actions">
        <?php if (!pode_gerenciar_role($u['role'], $isAdmin)): ?>
          <span class="hint">Somente administradores</span>
        <?php else: ?>
        <a href="<?= base_url('admin/usuarios.php?editar=' . $u['id']) ?>">Editar</a>
        <form method="post" action="<?= base_url('admin/usuarios_salvar.php') ?>" class="inline-form" data-confirm="Redefinir a senha deste usuário para a senha padrão?">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="acao" value="resetar_senha">
          <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
          <button type="submit" class="link-button">Resetar senha</button>
        </form>
        <?php if ((int)$u['id'] !== (int)$user['id']): ?>
        <form method="post" action="<?= base_url('admin/usuarios_salvar.php') ?>" class="inline-form" data-confirm="Confirma <?= $u['ativo'] ? 'desativar' : 'ativar' ?> este usuário?">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="acao" value="toggle_ativo">
          <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
          <button type="submit" class="link-button"><?= $u['ativo'] ? 'Desativar' : 'Ativar' ?></button>
        </form>
        <?php endif; ?>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
