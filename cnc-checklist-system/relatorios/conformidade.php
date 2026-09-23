<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = require_role(ROLES_GESTAO);

$hoje = date('Y-m-d');
$dataIni = $_GET['data_ini'] ?? date('Y-m-d', strtotime('-29 days'));
$dataFim = $_GET['data_fim'] ?? $hoje;
if (!DateTime::createFromFormat('Y-m-d', $dataIni)) { $dataIni = date('Y-m-d', strtotime('-29 days')); }
if (!DateTime::createFromFormat('Y-m-d', $dataFim)) { $dataFim = $hoje; }
if ($dataIni > $dataFim) { [$dataIni, $dataFim] = [$dataFim, $dataIni]; }

$maquinaId = (int)($_GET['maquina_id'] ?? 0);

$whereBase = 'c.data_checklist BETWEEN ? AND ?';
$paramsBase = [$dataIni, $dataFim];
if ($maquinaId) {
    $whereBase .= ' AND c.maquina_id = ?';
    $paramsBase[] = $maquinaId;
}

// KPIs do período
$stmtKpi = db()->prepare("
    SELECT COUNT(*) AS total,
           SUM(status_geral = 'conforme') AS total_conforme,
           SUM(status_geral = 'nao_conforme') AS total_nao_conforme
    FROM checklists c
    WHERE $whereBase
");
$stmtKpi->execute($paramsBase);
$kpi = $stmtKpi->fetch();
$totalChecklists = (int)($kpi['total'] ?? 0);
$totalConforme = (int)($kpi['total_conforme'] ?? 0);
$totalNaoConforme = (int)($kpi['total_nao_conforme'] ?? 0);
$pctConforme = $totalChecklists > 0 ? round($totalConforme / $totalChecklists * 100, 1) : 0;
$pctNaoConforme = $totalChecklists > 0 ? round(100 - $pctConforme, 1) : 0;

// Série diária (para o gráfico de barras)
$stmtDia = db()->prepare("
    SELECT c.data_checklist,
           SUM(status_geral = 'conforme') AS conforme,
           SUM(status_geral = 'nao_conforme') AS nao_conforme
    FROM checklists c
    WHERE $whereBase
    GROUP BY c.data_checklist
    ORDER BY c.data_checklist
");
$stmtDia->execute($paramsBase);
$serieDiaria = $stmtDia->fetchAll();

$diasMap = [];
foreach ($serieDiaria as $d) {
    $diasMap[$d['data_checklist']] = ['conforme' => (int)$d['conforme'], 'nao_conforme' => (int)$d['nao_conforme']];
}
$dias = [];
$cursor = new DateTime($dataIni);
$fim = new DateTime($dataFim);
while ($cursor <= $fim) {
    $key = $cursor->format('Y-m-d');
    $dias[] = ['data' => $key, 'conforme' => $diasMap[$key]['conforme'] ?? 0, 'nao_conforme' => $diasMap[$key]['nao_conforme'] ?? 0];
    $cursor->modify('+1 day');
}
$maxDia = 1;
foreach ($dias as $d) {
    $maxDia = max($maxDia, $d['conforme'] + $d['nao_conforme']);
}

// Itens do checklist que mais reprovam
$stmtItens = db()->prepare("
    SELECT i.descricao,
           COUNT(*) AS total_respostas,
           SUM(r.status = 'nao_conforme') AS total_nc
    FROM checklist_respostas r
    JOIN checklist_itens i ON i.id = r.item_id
    JOIN checklists c ON c.id = r.checklist_id
    WHERE $whereBase
    GROUP BY i.id, i.descricao
    HAVING total_nc > 0
    ORDER BY total_nc DESC, total_respostas DESC
    LIMIT 8
");
$stmtItens->execute($paramsBase);
$itensRanking = $stmtItens->fetchAll();

// Máquinas com mais não conformidades
$stmtMaq = db()->prepare("
    SELECT m.id, m.codigo, m.nome,
           COUNT(*) AS total_checklists,
           SUM(c.status_geral = 'nao_conforme') AS total_nc
    FROM checklists c
    JOIN maquinas m ON m.id = c.maquina_id
    WHERE $whereBase
    GROUP BY m.id, m.codigo, m.nome
    HAVING total_nc > 0
    ORDER BY total_nc DESC, total_checklists DESC
    LIMIT 8
");
$stmtMaq->execute($paramsBase);
$maquinasRanking = $stmtMaq->fetchAll();

// Operadores com melhor taxa de conformidade (mínimo 3 checklists no período)
$operadoresRanking = get_ranking_operadores($dataIni, $dataFim, $maquinaId ?: null);

// Indicadores extras de gestão
$stmtOp = db()->prepare("SELECT COUNT(DISTINCT c.user_id) FROM checklists c WHERE $whereBase");
$stmtOp->execute($paramsBase);
$operadoresAtivos = (int)$stmtOp->fetchColumn();

$maquinasManutencao = (int)db()->query("SELECT COUNT(*) FROM maquinas WHERE ativo = 1 AND status_operacional = 'manutencao'")->fetchColumn();

$totalContradicoes = count(get_contradicoes($maquinaId ?: null, $dataIni, $dataFim));

$maquinas = db()->query('SELECT id, codigo, nome FROM maquinas ORDER BY codigo')->fetchAll();

$pageTitle = 'Conformidade';
require __DIR__ . '/../includes/header.php';
?>
<h1>Conformidade — Conforme x Não Conforme</h1>

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
  <a href="<?= base_url('checklist/historico_exportar.php?' . http_build_query(['data_ini' => $dataIni, 'data_fim' => $dataFim, 'maquina_id' => $maquinaId ?: ''])) ?>" class="btn btn-secondary btn-sm">Exportar CSV</a>
</form>

<div class="grid-cards">
  <div class="card card-highlight">
    <h3>Checklists no período</h3>
    <p class="big-number"><?= $totalChecklists ?></p>
  </div>
  <div class="card card-highlight">
    <h3>Conforme</h3>
    <p class="big-number text-ok"><?= $pctConforme ?>%</p>
    <p class="hint"><?= $totalConforme ?> checklist(s)</p>
  </div>
  <div class="card card-highlight">
    <h3>Não Conforme</h3>
    <p class="big-number text-danger"><?= $pctNaoConforme ?>%</p>
    <p class="hint"><?= $totalNaoConforme ?> checklist(s)</p>
  </div>
  <div class="card card-highlight">
    <h3>Operadores ativos</h3>
    <p class="big-number"><?= $operadoresAtivos ?></p>
    <p class="hint">fizeram checklist no período</p>
  </div>
  <div class="card card-highlight">
    <h3>Máquinas em manutenção</h3>
    <p class="big-number <?= $maquinasManutencao > 0 ? 'text-danger' : 'text-ok' ?>"><?= $maquinasManutencao ?></p>
    <a href="<?= base_url('admin/maquinas.php?status_operacional=manutencao') ?>" class="btn btn-secondary btn-sm">Ver máquinas</a>
  </div>
  <div class="card card-highlight">
    <h3>Contradições de handoff</h3>
    <p class="big-number <?= $totalContradicoes > 0 ? 'text-danger' : 'text-ok' ?>"><?= $totalContradicoes ?></p>
    <a href="<?= base_url('relatorios/contradicoes.php?data_ini=' . $dataIni . '&data_fim=' . $dataFim) ?>" class="btn btn-secondary btn-sm">Ver detalhes</a>
  </div>
</div>

<h2>Evolução diária</h2>
<?php if ($totalChecklists === 0): ?>
  <div class="card"><p class="hint">Nenhum checklist registrado no período selecionado.</p></div>
<?php else: ?>
<div class="chart-wrap">
  <div class="legend">
    <span><span class="legend-dot bg-ok"></span>Conforme</span>
    <span><span class="legend-dot bg-danger"></span>Não Conforme</span>
  </div>
  <div class="chart-bars">
    <?php foreach ($dias as $d):
      $totalDia = $d['conforme'] + $d['nao_conforme'];
      $alturaOk = $maxDia > 0 ? round($d['conforme'] / $maxDia * 150) : 0;
      $alturaNc = $maxDia > 0 ? round($d['nao_conforme'] / $maxDia * 150) : 0;
      $dt = DateTime::createFromFormat('Y-m-d', $d['data']);
    ?>
      <div class="chart-col" title="<?= e($dt->format('d/m/Y')) ?>: <?= $d['conforme'] ?> conforme, <?= $d['nao_conforme'] ?> não conforme">
        <div class="chart-bar" style="height:150px;">
          <?php if ($totalDia === 0): ?>
            <div class="chart-seg-empty" style="height:4px;"></div>
          <?php else: ?>
            <div class="chart-seg-nc" style="height:<?= $alturaNc ?>px;"></div>
            <div class="chart-seg-ok" style="height:<?= $alturaOk ?>px;"></div>
          <?php endif; ?>
        </div>
        <span class="chart-label"><?= e($dt->format('d/m')) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="grid-2col">
  <div>
    <h2>Itens que mais reprovam</h2>
    <?php if (!$itensRanking): ?>
      <div class="card"><p class="hint">Nenhuma não conformidade registrada no período.</p></div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Item</th><th>Não conforme</th><th>Taxa</th></tr></thead>
        <tbody>
        <?php foreach ($itensRanking as $it):
          $taxa = $it['total_respostas'] > 0 ? round($it['total_nc'] / $it['total_respostas'] * 100, 1) : 0;
        ?>
          <tr>
            <td class="whitespace-normal"><?= e($it['descricao']) ?></td>
            <td><?= (int)$it['total_nc'] ?> / <?= (int)$it['total_respostas'] ?></td>
            <td><span class="badge badge-pendente"><?= $taxa ?>%</span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <div>
    <h2>Máquinas com mais não conformidades</h2>
    <?php if (!$maquinasRanking): ?>
      <div class="card"><p class="hint">Nenhuma não conformidade registrada no período.</p></div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Máquina</th><th>Não conforme</th><th>Taxa</th></tr></thead>
        <tbody>
        <?php foreach ($maquinasRanking as $m):
          $taxa = $m['total_checklists'] > 0 ? round($m['total_nc'] / $m['total_checklists'] * 100, 1) : 0;
        ?>
          <tr>
            <td><?= e($m['codigo']) ?> — <?= e($m['nome']) ?></td>
            <td><?= (int)$m['total_nc'] ?> / <?= (int)$m['total_checklists'] ?></td>
            <td><span class="badge badge-pendente"><?= $taxa ?>%</span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<h2>Operadores com melhor desempenho</h2>
<p class="hint">Ranking por taxa de conformidade no período (mínimo de 3 checklists para entrar no ranking, pra não deixar 1 checklist isolado distorcer o resultado).</p>
<?php if (!$operadoresRanking): ?>
  <div class="card"><p class="hint">Nenhum operador com checklists suficientes no período para gerar o ranking.</p></div>
<?php else: ?>
<div class="table-wrap">
  <table class="table">
    <thead><tr><th>Operador</th><th>Matrícula</th><th>Checklists</th><th>Conforme</th><th>Taxa</th></tr></thead>
    <tbody>
    <?php foreach ($operadoresRanking as $op):
      $taxaOp = $op['total_checklists'] > 0 ? round($op['total_conforme'] / $op['total_checklists'] * 100, 1) : 0;
    ?>
      <tr>
        <td><?= e($op['nome']) ?></td>
        <td><?= e($op['matricula']) ?></td>
        <td><?= (int)$op['total_checklists'] ?></td>
        <td><?= (int)$op['total_conforme'] ?></td>
        <td><span class="badge badge-ok"><?= $taxaOp ?>%</span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
