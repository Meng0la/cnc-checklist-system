<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = require_role([ROLE_OPERADOR]);

$maquinaId = isset($_GET['maquina_id']) ? (int)$_GET['maquina_id'] : 0;

$maquinas = db()->query('SELECT id, codigo, nome FROM maquinas WHERE ativo = 1 ORDER BY codigo')->fetchAll();

if (!$maquinas) {
    flash_set('erro', 'Nenhuma máquina ativa cadastrada. Procure o supervisor.');
    redirect(base_url('dashboard.php'));
}

$itens = db()->query('SELECT id, descricao, categoria, qtd_padrao FROM checklist_itens WHERE ativo = 1 ORDER BY ordem, id')->fetchAll();

if (!$itens) {
    flash_set('erro', 'Nenhum item de checklist configurado. Procure o administrador.');
    redirect(base_url('dashboard.php'));
}

$itensLimpeza = array_filter($itens, fn($i) => $i['categoria'] === 'limpeza');
$itensFerramenta = array_filter($itens, fn($i) => $i['categoria'] === 'ferramenta');

$pageTitle = 'Novo Checklist';
require __DIR__ . '/../includes/header.php';
?>
<h1>Novo Checklist</h1>
<form method="post" action="<?= base_url('checklist/salvar.php') ?>" id="form-checklist" class="form-card">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

  <label>Máquina (CNC) — selecione a máquina que você está operando neste turno
    <select name="maquina_id" required>
      <option value="">Selecione...</option>
      <?php foreach ($maquinas as $m): ?>
        <option value="<?= $m['id'] ?>" <?= $m['id'] == $maquinaId ? 'selected' : '' ?>><?= e($m['codigo']) ?> — <?= e($m['nome']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>

  <label>Tipo de vistoria
    <select name="tipo_checklist" required>
      <option value="inicio">Início de Turno</option>
      <option value="fim">Fim de Turno</option>
    </select>
  </label>

  <label>Turno
    <select name="turno" required>
      <option value="manha">Manhã</option>
      <option value="tarde">Tarde</option>
      <option value="noite">Noite</option>
    </select>
  </label>

  <h2>Limpeza</h2>
  <p class="hint">Marque cada item como Conforme ou Não Conforme. Em caso de Não Conforme, descreva a ocorrência (mínimo 10 caracteres).</p>

  <div class="checklist-itens">
    <?php foreach ($itensLimpeza as $item): ?>
      <div class="checklist-item" data-item="<?= $item['id'] ?>">
        <div class="checklist-item-desc"><?= e($item['descricao']) ?></div>
        <div class="checklist-item-opcoes">
          <label class="radio-ok"><input type="radio" name="item_<?= $item['id'] ?>" value="conforme" required> Conforme</label>
          <label class="radio-nok"><input type="radio" name="item_<?= $item['id'] ?>" value="nao_conforme"> Não Conforme</label>
        </div>
        <div class="checklist-item-obs" style="display:none;">
          <textarea name="obs_<?= $item['id'] ?>" maxlength="1000" placeholder="Descreva o que foi encontrado..."></textarea>
          <small class="char-counter">0 caracteres</small>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($itensFerramenta): ?>
    <h2>Ferramentas</h2>
    <p class="hint">Confira o jogo de ferramentas da máquina. Marque Não Conforme se faltar ferramenta ou a quantidade estiver incorreta, e descreva o que falta.</p>

    <div class="checklist-itens">
      <?php foreach ($itensFerramenta as $item): ?>
        <div class="checklist-item" data-item="<?= $item['id'] ?>">
          <div class="checklist-item-desc">
            <?= e($item['descricao']) ?>
            <?php if ($item['qtd_padrao'] !== null): ?><span class="hint">(Qtde padrão: <?= (int)$item['qtd_padrao'] ?>)</span><?php endif; ?>
          </div>
          <div class="checklist-item-opcoes">
            <label class="radio-ok"><input type="radio" name="item_<?= $item['id'] ?>" value="conforme" required> Conforme</label>
            <label class="radio-nok"><input type="radio" name="item_<?= $item['id'] ?>" value="nao_conforme"> Não Conforme</label>
          </div>
          <div class="checklist-item-obs" style="display:none;">
            <textarea name="obs_<?= $item['id'] ?>" maxlength="1000" placeholder="Descreva o que foi encontrado..."></textarea>
            <small class="char-counter">0 caracteres</small>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <button type="submit" class="btn btn-primary btn-block">Enviar Checklist</button>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
