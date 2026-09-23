<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = require_role(ROLES_CADASTRO);

$editId = isset($_GET['editar']) ? (int)$_GET['editar'] : 0;
$editItem = null;
if ($editId) {
    $stmt = db()->prepare('SELECT * FROM checklist_itens WHERE id = ?');
    $stmt->execute([$editId]);
    $editItem = $stmt->fetch();
}

$itens = db()->query('SELECT * FROM checklist_itens ORDER BY ordem, id')->fetchAll();

$pageTitle = 'Itens do Checklist';
require __DIR__ . '/../includes/header.php';
?>
<h1>Itens do Checklist</h1>

<h2><?= $editItem ? 'Editar Item' : 'Novo Item' ?></h2>
<form method="post" action="<?= base_url('admin/itens_salvar.php') ?>" class="form-card">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="acao" value="<?= $editItem ? 'editar' : 'criar' ?>">
  <?php if ($editItem): ?><input type="hidden" name="id" value="<?= (int)$editItem['id'] ?>"><?php endif; ?>
  <label>Descrição do item
    <textarea name="descricao" required><?= e($editItem['descricao'] ?? '') ?></textarea>
  </label>
  <label>Categoria
    <select name="categoria" id="campo-categoria">
      <option value="limpeza" <?= ($editItem['categoria'] ?? 'limpeza') === 'limpeza' ? 'selected' : '' ?>>Limpeza</option>
      <option value="ferramenta" <?= ($editItem['categoria'] ?? '') === 'ferramenta' ? 'selected' : '' ?>>Ferramenta</option>
    </select>
  </label>
  <label id="campo-qtd-padrao-wrap">Quantidade padrão (só para ferramenta)
    <input type="number" name="qtd_padrao" id="campo-qtd-padrao" min="1" value="<?= e((string)($editItem['qtd_padrao'] ?? '')) ?>">
  </label>
  <label>Ordem de exibição
    <input type="number" name="ordem" value="<?= e((string)($editItem['ordem'] ?? (count($itens) + 1))) ?>">
  </label>
  <button type="submit" class="btn btn-primary"><?= $editItem ? 'Salvar alterações' : 'Adicionar item' ?></button>
  <?php if ($editItem): ?><a href="<?= base_url('admin/itens.php') ?>" class="btn btn-secondary">Cancelar</a><?php endif; ?>
</form>
<script>
(function () {
  var categoria = document.getElementById('campo-categoria');
  var qtdWrap = document.getElementById('campo-qtd-padrao-wrap');
  function atualizar() {
    qtdWrap.style.display = categoria.value === 'ferramenta' ? '' : 'none';
  }
  categoria.addEventListener('change', atualizar);
  atualizar();
})();
</script>

<h2>Limpeza</h2>
<div class="table-wrap">
<table class="table">
  <thead><tr><th>Ordem</th><th>Descrição</th><th>Status</th><th>Ações</th></tr></thead>
  <tbody>
  <?php foreach ($itens as $i): if ($i['categoria'] !== 'limpeza') continue; ?>
    <tr>
      <td><?= (int)$i['ordem'] ?></td>
      <td><?= e($i['descricao']) ?></td>
      <td><?= $i['ativo'] ? '<span class="badge badge-ok">Ativo</span>' : '<span class="badge badge-pendente">Inativo</span>' ?></td>
      <td class="actions">
        <a href="<?= base_url('admin/itens.php?editar=' . $i['id']) ?>">Editar</a>
        <form method="post" action="<?= base_url('admin/itens_salvar.php') ?>" class="inline-form" data-confirm="Confirma alterar o status deste item?">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="acao" value="toggle_ativo">
          <input type="hidden" name="id" value="<?= (int)$i['id'] ?>">
          <button type="submit" class="link-button"><?= $i['ativo'] ? 'Desativar' : 'Ativar' ?></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<h2>Ferramentas</h2>
<div class="table-wrap">
<table class="table">
  <thead><tr><th>Ordem</th><th>Descrição</th><th>Qtde padrão</th><th>Status</th><th>Ações</th></tr></thead>
  <tbody>
  <?php foreach ($itens as $i): if ($i['categoria'] !== 'ferramenta') continue; ?>
    <tr>
      <td><?= (int)$i['ordem'] ?></td>
      <td><?= e($i['descricao']) ?></td>
      <td><?= $i['qtd_padrao'] !== null ? (int)$i['qtd_padrao'] : '—' ?></td>
      <td><?= $i['ativo'] ? '<span class="badge badge-ok">Ativo</span>' : '<span class="badge badge-pendente">Inativo</span>' ?></td>
      <td class="actions">
        <a href="<?= base_url('admin/itens.php?editar=' . $i['id']) ?>">Editar</a>
        <form method="post" action="<?= base_url('admin/itens_salvar.php') ?>" class="inline-form" data-confirm="Confirma alterar o status deste item?">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="acao" value="toggle_ativo">
          <input type="hidden" name="id" value="<?= (int)$i['id'] ?>">
          <button type="submit" class="link-button"><?= $i['ativo'] ? 'Desativar' : 'Ativar' ?></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
