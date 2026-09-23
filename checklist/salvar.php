<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notificacoes.php';

$user = require_role([ROLE_OPERADOR]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {
    flash_set('erro', 'Requisição inválida. Tente novamente.');
    redirect(base_url('checklist/novo.php'));
}

$maquinaId = (int)($_POST['maquina_id'] ?? 0);
$tipoChecklist = $_POST['tipo_checklist'] ?? '';
$turno = $_POST['turno'] ?? '';

if (!in_array($tipoChecklist, ['inicio', 'fim'], true) || !in_array($turno, ['manha', 'tarde', 'noite'], true)) {
    flash_set('erro', 'Dados inválidos. Preencha o formulário novamente.');
    redirect(base_url('checklist/novo.php'));
}

$stmtCheck = db()->prepare('SELECT id FROM maquinas WHERE id = ? AND ativo = 1');
$stmtCheck->execute([$maquinaId]);
if (!$stmtCheck->fetch()) {
    flash_set('erro', 'Máquina inválida ou inativa.');
    redirect(base_url('checklist/novo.php'));
}

$itens = db()->query('SELECT id, descricao FROM checklist_itens WHERE ativo = 1 ORDER BY ordem, id')->fetchAll();
if (!$itens) {
    flash_set('erro', 'Nenhum item de checklist configurado. Procure o administrador.');
    redirect(base_url('checklist/novo.php'));
}

$respostas = [];
$statusGeral = 'conforme';

foreach ($itens as $item) {
    $status = $_POST['item_' . $item['id']] ?? '';
    if (!in_array($status, ['conforme', 'nao_conforme'], true)) {
        flash_set('erro', 'Preencha todos os itens do checklist.');
        redirect(base_url('checklist/novo.php'));
    }
    $obs = trim($_POST['obs_' . $item['id']] ?? '');
    if ($status === 'nao_conforme') {
        $statusGeral = 'nao_conforme';
        if (mb_strlen($obs) < 10) {
            flash_set('erro', 'Descreva a não conformidade do item "' . $item['descricao'] . '" com no mínimo 10 caracteres.');
            redirect(base_url('checklist/novo.php'));
        }
    } else {
        $obs = null;
    }
    $respostas[] = ['item_id' => $item['id'], 'status' => $status, 'observacao' => $obs];
}

$pdo = db();
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('INSERT INTO checklists (maquina_id, user_id, tipo_checklist, turno, data_checklist, status_geral, created_at) VALUES (?, ?, ?, ?, CURDATE(), ?, NOW())');
    $stmt->execute([$maquinaId, $user['id'], $tipoChecklist, $turno, $statusGeral]);
    $checklistId = (int)$pdo->lastInsertId();

    $stmtResp = $pdo->prepare('INSERT INTO checklist_respostas (checklist_id, item_id, status, observacao) VALUES (?, ?, ?, ?)');
    foreach ($respostas as $r) {
        $stmtResp->execute([$checklistId, $r['item_id'], $r['status'], $r['observacao']]);
    }

    $pdo->commit();

    if ($statusGeral === 'nao_conforme') {
        try {
            notificar_nao_conformidade($checklistId);
        } catch (Throwable $e) {
            // Nunca deixa uma falha de e-mail impedir o checklist de ser salvo.
        }
    }

    flash_set('sucesso', 'Checklist enviado com sucesso.');
    redirect(base_url('checklist/detalhe.php?id=' . $checklistId));
} catch (Exception $e) {
    $pdo->rollBack();
    flash_set('erro', 'Erro ao salvar o checklist. Tente novamente.');
    redirect(base_url('checklist/novo.php'));
}
