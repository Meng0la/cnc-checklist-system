<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auditoria.php';

$user = require_role(ROLES_CADASTRO);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {
    flash_set('erro', 'Requisição inválida.');
    redirect(base_url('admin/itens.php'));
}

$acao = $_POST['acao'] ?? '';

try {
    if ($acao === 'criar') {
        $descricao = trim($_POST['descricao'] ?? '');
        $categoria = ($_POST['categoria'] ?? '') === 'ferramenta' ? 'ferramenta' : 'limpeza';
        $qtdPadrao = $categoria === 'ferramenta' && ($_POST['qtd_padrao'] ?? '') !== '' ? (int)$_POST['qtd_padrao'] : null;
        $ordem = (int)($_POST['ordem'] ?? 0);
        if ($descricao === '') {
            flash_set('erro', 'Informe a descrição do item.');
            redirect(base_url('admin/itens.php'));
        }
        db()->prepare('INSERT INTO checklist_itens (descricao, categoria, qtd_padrao, ordem, ativo) VALUES (?, ?, ?, ?, 1)')->execute([$descricao, $categoria, $qtdPadrao, $ordem]);
        $novoId = (int)db()->lastInsertId();
        log_auditoria('criar', 'item_checklist', $novoId, "Criou o item de checklist \"$descricao\"");
        flash_set('sucesso', 'Item adicionado com sucesso.');
    } elseif ($acao === 'editar') {
        $id = (int)($_POST['id'] ?? 0);
        $descricao = trim($_POST['descricao'] ?? '');
        $categoria = ($_POST['categoria'] ?? '') === 'ferramenta' ? 'ferramenta' : 'limpeza';
        $qtdPadrao = $categoria === 'ferramenta' && ($_POST['qtd_padrao'] ?? '') !== '' ? (int)$_POST['qtd_padrao'] : null;
        $ordem = (int)($_POST['ordem'] ?? 0);
        if (!$id || $descricao === '') {
            flash_set('erro', 'Informe a descrição do item.');
            redirect(base_url('admin/itens.php'));
        }
        db()->prepare('UPDATE checklist_itens SET descricao = ?, categoria = ?, qtd_padrao = ?, ordem = ? WHERE id = ?')->execute([$descricao, $categoria, $qtdPadrao, $ordem, $id]);
        log_auditoria('editar', 'item_checklist', $id, "Editou o item de checklist \"$descricao\"");
        flash_set('sucesso', 'Item atualizado com sucesso.');
    } elseif ($acao === 'toggle_ativo') {
        $id = (int)($_POST['id'] ?? 0);
        $stmtI = db()->prepare('SELECT descricao FROM checklist_itens WHERE id = ?');
        $stmtI->execute([$id]);
        $descricaoAlvo = $stmtI->fetchColumn() ?: "ID $id";
        db()->prepare('UPDATE checklist_itens SET ativo = NOT ativo WHERE id = ?')->execute([$id]);
        log_auditoria('toggle_ativo', 'item_checklist', $id, "Alterou o status do item \"$descricaoAlvo\"");
        flash_set('sucesso', 'Status do item atualizado.');
    } else {
        flash_set('erro', 'Ação inválida.');
    }
} catch (Exception $e) {
    flash_set('erro', 'Erro ao processar a solicitação.');
}

redirect(base_url('admin/itens.php'));
