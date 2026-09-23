<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = require_role(ROLES_GESTAO);

$dataIni = $_GET['data_ini'] ?? date('Y-m-d', strtotime('-29 days'));
$dataFim = $_GET['data_fim'] ?? date('Y-m-d');
if (!DateTime::createFromFormat('Y-m-d', $dataIni)) { $dataIni = date('Y-m-d', strtotime('-29 days')); }
if (!DateTime::createFromFormat('Y-m-d', $dataFim)) { $dataFim = date('Y-m-d'); }

$maquinaId = (int)($_GET['maquina_id'] ?? 0);

$contradicoes = get_contradicoes($maquinaId ?: null, $dataIni, $dataFim);
$maquinas = db()->query('SELECT id, codigo, nome FROM maquinas ORDER BY codigo')->fetchAll();

$pageTitle = 'Contradições de Handoff';
require __DIR__ . '/../includes/header.php';
?>
<h1>Contradições de Handoff entre Turnos</h1>
<p class="hint">
  Casos em que o checklist de <strong>Fim de Turno</strong> de uma máquina foi marcado como
  Conforme, mas o próximo checklist de <strong>Início de Turno</strong> da mesma máquina —
  feito pelo operador seguinte — veio Não Conforme. Pode indicar uso não autorizado entre
  turnos, uma pane que surgiu depois do "tudo ok", ou um checklist de fim de turno feito
  sem verificar de verdade.
</p>

<form method="get" class="filter-bar">
  <label>De <input type="date" name="data_ini" value="<?= e($dataIni) ?>"></label>
  <label>Até <input type="date" name="data_fim" value="<?= e($dataFim) ?>"></label>
  <label>Máquina
    <select name="maquina_id">
      <option value="">Todas</option>
      <?php foreach ($maquinas as $m): ?>
        <option value="<?= $m['id'] ?>" <?= $maquinaId == $m['id'] ? 'selected' : '' ?>><?= e($m['codigo']) ?> — <?= e($m['nome']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <button type="submit" class="btn btn-secondary btn-sm">Filtrar</button>
</form>

<?php if (!$contradicoes): ?>
  <div class="card"><p class="hint">Nenhuma contradição encontrada no período selecionado.</p></div>
<?php else: ?>
  <p class="hint"><?= count($contradicoes) ?> contradição(ões) encontrada(s).</p>
  <div class="checklist-itens">
    <?php foreach ($contradicoes as $c): ?>
      <div class="checklist-item item-nao-conforme">
        <div class="checklist-item-desc">
          <?= e($c['maquina_codigo']) ?> — <?= e($c['maquina_nome']) ?>
          <span class="badge badge-pendente"><?= (int)$c['qtd_nao_conforme'] ?> item(ns) não conforme(s)</span>
        </div>
        <div class="grid-2col">
          <div>
            <p class="hint">Fim de turno anterior — marcado Conforme</p>
            <p><strong><?= e($c['fim_operador_nome']) ?></strong> (matrícula <?= e($c['fim_matricula']) ?>)</p>
            <p><?= e(format_datahora_br($c['fim_created_at'])) ?></p>
            <a href="<?= base_url('checklist/detalhe.php?id=' . $c['fim_id']) ?>" class="btn btn-secondary btn-sm">Ver checklist</a>
          </div>
          <div>
            <p class="hint">Início de turno seguinte — Não Conforme</p>
            <p><strong><?= e($c['inicio_operador_nome']) ?></strong> (matrícula <?= e($c['inicio_matricula']) ?>)</p>
            <p><?= e(format_datahora_br($c['inicio_created_at'])) ?></p>
            <a href="<?= base_url('checklist/detalhe.php?id=' . $c['inicio_id']) ?>" class="btn btn-secondary btn-sm">Ver checklist</a>
          </div>
        </div>
        <p class="hint">Tempo entre os dois checklists: <?= e(formatar_duracao($c['fim_created_at'], $c['inicio_created_at'])) ?></p>

        <?php if ($c['detalhes_nc']): ?>
          <div class="checklist-item-obs-view">
            <strong>O que foi encontrado não conforme:</strong>
            <ul class="list-disc list-inside mt-1">
              <?php foreach (explode('||', $c['detalhes_nc']) as $detalhe): ?>
                <li><?= e($detalhe) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
