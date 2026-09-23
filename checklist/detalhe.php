<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notificacoes.php';

$user = require_login();
$id = (int)($_GET['id'] ?? 0);
$isGestao = in_array($user['role'], ROLES_GESTAO, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'marcar_visto') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        flash_set('erro', 'Requisição inválida.');
    } elseif (!$isGestao) {
        flash_set('erro', 'Você não tem permissão para confirmar esta não conformidade.');
    } else {
        marcar_visto($id, (int)$user['id']);
        flash_set('sucesso', 'Não conformidade marcada como vista.');
    }
    redirect(base_url('checklist/detalhe.php?id=' . $id));
}

$stmt = db()->prepare("
    SELECT c.*, m.codigo AS maquina_codigo, m.nome AS maquina_nome, m.numero_patrimonio,
           u.nome AS operador_nome, u.matricula, v.nome AS visto_por_nome
    FROM checklists c
    JOIN maquinas m ON m.id = c.maquina_id
    JOIN usuarios u ON u.id = c.user_id
    LEFT JOIN usuarios v ON v.id = c.visto_por
    WHERE c.id = ?
");
$stmt->execute([$id]);
$checklist = $stmt->fetch();

if (!$checklist) {
    flash_set('erro', 'Checklist não encontrado.');
    redirect(base_url('checklist/historico.php'));
}

if (!$isGestao && (int)$checklist['user_id'] !== (int)$user['id']) {
    http_response_code(403);
    require BASE_PATH . '/403.php';
    exit;
}

$stmtItens = db()->prepare("
    SELECT r.status, r.observacao, i.descricao, i.categoria, i.qtd_padrao
    FROM checklist_respostas r
    JOIN checklist_itens i ON i.id = r.item_id
    WHERE r.checklist_id = ?
    ORDER BY i.ordem, i.id
");
$stmtItens->execute([$id]);
$respostas = $stmtItens->fetchAll();
$respostasLimpeza = array_filter($respostas, fn($r) => $r['categoria'] === 'limpeza');
$respostasFerramenta = array_filter($respostas, fn($r) => $r['categoria'] === 'ferramenta');

$pageTitle = 'Detalhe do Checklist';
require __DIR__ . '/../includes/header.php';
?>
<h1>Checklist #<?= (int)$checklist['id'] ?></h1>
<div class="detail-header">
  <p><strong>Máquina:</strong> <?= e($checklist['maquina_codigo']) ?> — <?= e($checklist['maquina_nome']) ?>
    <?php if ($checklist['numero_patrimonio']): ?><br><small class="hint">Nº de patrimônio (manutenção): <?= e($checklist['numero_patrimonio']) ?></small><?php endif; ?>
  </p>
  <p><strong>Operador:</strong> <?= e($checklist['operador_nome']) ?> (Matrícula <?= e($checklist['matricula']) ?>)</p>
  <p><strong>Data:</strong> <?= e(format_data_br($checklist['data_checklist'])) ?> — <strong>Turno:</strong> <?= e(turno_label($checklist['turno'])) ?></p>
  <p><strong>Tipo:</strong> <?= e(tipo_checklist_label($checklist['tipo_checklist'])) ?></p>
  <p><strong>Status geral:</strong>
    <?= $checklist['status_geral'] === 'conforme' ? '<span class="badge badge-ok">Conforme</span>' : '<span class="badge badge-pendente">Não Conforme</span>' ?>
  </p>
  <?php if ($checklist['status_geral'] === 'nao_conforme'): ?>
    <p><strong>Confirmação:</strong>
      <?php if ($checklist['visto_por']): ?>
        <span class="badge badge-ok">Visto</span> por <?= e($checklist['visto_por_nome']) ?> em <?= e(format_datahora_br($checklist['visto_em'])) ?>
      <?php elseif ($isGestao): ?>
        <span class="badge badge-pendente">Não confirmado</span>
        <form method="post" class="inline-form">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="acao" value="marcar_visto">
          <button type="submit" class="btn btn-primary btn-sm">Marcar como visto</button>
        </form>
      <?php else: ?>
        <span class="badge badge-pendente">Não confirmado</span>
      <?php endif; ?>
    </p>
  <?php endif; ?>
</div>

<h2>Limpeza</h2>
<div class="checklist-itens checklist-itens-readonly">
  <?php foreach ($respostasLimpeza as $r): ?>
    <div class="checklist-item">
      <div class="checklist-item-desc"><?= e($r['descricao']) ?></div>
      <div><?= $r['status'] === 'conforme' ? '<span class="badge badge-ok">Conforme</span>' : '<span class="badge badge-pendente">Não Conforme</span>' ?></div>
      <?php if ($r['observacao']): ?><div class="checklist-item-obs-view"><?= nl2br(e($r['observacao'])) ?></div><?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<?php if ($respostasFerramenta): ?>
  <h2>Ferramentas</h2>
  <div class="checklist-itens checklist-itens-readonly">
    <?php foreach ($respostasFerramenta as $r): ?>
      <div class="checklist-item">
        <div class="checklist-item-desc">
          <?= e($r['descricao']) ?>
          <?php if ($r['qtd_padrao'] !== null): ?><span class="hint">(Qtde padrão: <?= (int)$r['qtd_padrao'] ?>)</span><?php endif; ?>
        </div>
        <div><?= $r['status'] === 'conforme' ? '<span class="badge badge-ok">Conforme</span>' : '<span class="badge badge-pendente">Não Conforme</span>' ?></div>
        <?php if ($r['observacao']): ?><div class="checklist-item-obs-view"><?= nl2br(e($r['observacao'])) ?></div><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<a href="<?= base_url('checklist/historico.php') ?>" class="btn btn-secondary">Voltar</a>
<?php require __DIR__ . '/../includes/footer.php'; ?>
