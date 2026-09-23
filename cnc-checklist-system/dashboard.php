<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

$user = require_login();
$hoje = date('Y-m-d');

$meusChecklistsHoje = [];
$totalPendencias = 0;
$naoConformidades = [];

if ($user['role'] === ROLE_OPERADOR) {
    $stmt = db()->prepare("
        SELECT c.id, c.tipo_checklist, c.turno, c.status_geral, m.codigo, m.nome
        FROM checklists c
        JOIN maquinas m ON m.id = c.maquina_id
        WHERE c.user_id = ? AND c.data_checklist = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$user['id'], $hoje]);
    $meusChecklistsHoje = $stmt->fetchAll();
} else {
    $totalPendencias = count(get_pendencias($hoje));
    $contradicoes7dias = get_contradicoes(null, date('Y-m-d', strtotime('-6 days')), $hoje);
    $totalNaoConfirmadas = (int)db()->query("SELECT COUNT(*) FROM checklists WHERE status_geral = 'nao_conforme' AND visto_por IS NULL")->fetchColumn();

    $stmtNc = db()->query("
        SELECT c.id, c.data_checklist, c.tipo_checklist, c.turno, c.visto_por, m.codigo AS maquina_codigo, m.nome AS maquina_nome, u.nome AS operador_nome
        FROM checklists c
        JOIN maquinas m ON m.id = c.maquina_id
        JOIN usuarios u ON u.id = c.user_id
        WHERE c.status_geral = 'nao_conforme'
        ORDER BY c.created_at DESC
        LIMIT 10
    ");
    $naoConformidades = $stmtNc->fetchAll();
}

$pageTitle = 'Painel';
require __DIR__ . '/includes/header.php';
?>
<h1>Painel — <?= e(date('d/m/Y')) ?></h1>

<?php if ($user['role'] === ROLE_OPERADOR): ?>
  <div class="grid-cards">
    <div class="card card-highlight">
      <h3>Preencher checklist</h3>
      <p class="hint">Selecione a máquina que você está operando neste turno.</p>
      <a href="<?= base_url('checklist/novo.php') ?>" class="btn btn-primary btn-block">Novo Checklist</a>
    </div>
  </div>

  <h2>Meus checklists de hoje</h2>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>Máquina</th><th>Tipo</th><th>Turno</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($meusChecklistsHoje as $c): ?>
      <tr>
        <td><?= e($c['codigo']) ?> — <?= e($c['nome']) ?></td>
        <td><?= e(tipo_checklist_label($c['tipo_checklist'])) ?></td>
        <td><?= e(turno_label($c['turno'])) ?></td>
        <td><?= $c['status_geral'] === 'conforme' ? '<span class="badge badge-ok">Conforme</span>' : '<span class="badge badge-pendente">Não Conforme</span>' ?></td>
        <td><a href="<?= base_url('checklist/detalhe.php?id=' . $c['id']) ?>">Ver</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$meusChecklistsHoje): ?><tr><td colspan="5">Nenhum checklist preenchido hoje ainda.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
<?php else: ?>
  <?php if ($contradicoes7dias): ?>
    <div class="alert alert-erro">
      <strong>⚠ <?= count($contradicoes7dias) ?> contradição(ões) de handoff</strong> nos últimos 7 dias —
      o operador anterior marcou "Fim de Turno: Conforme", mas o próximo encontrou não conformidade
      logo em seguida na mesma máquina.
      <a href="<?= base_url('relatorios/contradicoes.php') ?>">Ver detalhes</a>
    </div>
  <?php endif; ?>

  <div class="grid-cards">
    <div class="card card-highlight">
      <h3>Checklists pendentes hoje</h3>
      <p class="big-number <?= $totalPendencias > 0 ? 'text-danger' : 'text-ok' ?>"><?= $totalPendencias ?></p>
      <a href="<?= base_url('relatorios/pendencias.php') ?>" class="btn btn-secondary btn-sm">Ver detalhes</a>
    </div>
    <div class="card card-highlight">
      <h3>Não conformidades sem confirmação</h3>
      <p class="big-number <?= $totalNaoConfirmadas > 0 ? 'text-danger' : 'text-ok' ?>"><?= $totalNaoConfirmadas ?></p>
      <a href="<?= base_url('checklist/historico.php?status=nao_conforme') ?>" class="btn btn-secondary btn-sm">Ver todas</a>
    </div>
  </div>

  <h2>Últimas não conformidades</h2>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>Data</th><th>Máquina</th><th>Operador</th><th>Tipo</th><th>Turno</th><th>Confirmação</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($naoConformidades as $nc): ?>
      <tr>
        <td><?= e(format_data_br($nc['data_checklist'])) ?></td>
        <td><?= e($nc['maquina_codigo']) ?> - <?= e($nc['maquina_nome']) ?></td>
        <td><?= e($nc['operador_nome']) ?></td>
        <td><?= e(tipo_checklist_label($nc['tipo_checklist'])) ?></td>
        <td><?= e(turno_label($nc['turno'])) ?></td>
        <td><?= $nc['visto_por'] ? '<span class="badge badge-ok">Visto</span>' : '<span class="badge badge-pendente">Não confirmado</span>' ?></td>
        <td><a href="<?= base_url('checklist/detalhe.php?id=' . $nc['id']) ?>">Ver</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$naoConformidades): ?><tr><td colspan="7">Nenhuma não conformidade registrada recentemente.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
