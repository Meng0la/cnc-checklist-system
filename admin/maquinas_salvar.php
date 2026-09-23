<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auditoria.php';

$user = require_role(ROLES_CADASTRO);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {
    flash_set('erro', 'Requisição inválida.');
    redirect(base_url('admin/maquinas.php'));
}

$acao = $_POST['acao'] ?? '';

$criticidadesValidas = ['baixa', 'media', 'alta', 'critica'];
$statusValidos = ['operando', 'manutencao', 'parada'];

try {
    if ($acao === 'criar' || $acao === 'editar') {
        $codigo = trim($_POST['codigo'] ?? '');
        $nome = trim($_POST['nome'] ?? '');
        $tipo = trim($_POST['tipo'] ?? '');
        $modelo = trim($_POST['modelo'] ?? '');
        $fabricante = trim($_POST['fabricante'] ?? '');
        $numeroPatrimonio = trim($_POST['numero_patrimonio'] ?? '');
        $pecaProduzida = trim($_POST['peca_produzida'] ?? '');
        $setor = trim($_POST['setor'] ?? '');
        $criticidade = $_POST['criticidade'] ?? '';
        $criticidade = in_array($criticidade, $criticidadesValidas, true) ? $criticidade : null;
        $horasOperacao = (float)str_replace(',', '.', $_POST['horas_operacao'] ?? '0');
        $proximaRevisao = trim($_POST['proxima_revisao'] ?? '');
        $proximaRevisao = $proximaRevisao !== '' ? $proximaRevisao : null;
        $statusOperacional = $_POST['status_operacional'] ?? 'operando';
        $statusOperacional = in_array($statusOperacional, $statusValidos, true) ? $statusOperacional : 'operando';

        if ($codigo === '' || $nome === '') {
            flash_set('erro', 'Preencha código e nome da máquina.');
            redirect(base_url('admin/maquinas.php'));
        }

        if ($acao === 'criar') {
            db()->prepare('INSERT INTO maquinas (codigo, nome, tipo, modelo, fabricante, numero_patrimonio, peca_produzida, setor, criticidade, horas_operacao, proxima_revisao, status_operacional, ativo, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())')
                ->execute([$codigo, $nome, $tipo ?: null, $modelo ?: null, $fabricante ?: null, $numeroPatrimonio ?: null, $pecaProduzida ?: null, $setor ?: null, $criticidade, $horasOperacao, $proximaRevisao, $statusOperacional]);
            $novoId = (int)db()->lastInsertId();
            log_auditoria('criar', 'maquina', $novoId, "Cadastrou a máquina \"$codigo — $nome\"");
            flash_set('sucesso', 'Máquina cadastrada com sucesso.');
        } else {
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) {
                flash_set('erro', 'Máquina inválida.');
                redirect(base_url('admin/maquinas.php'));
            }
            db()->prepare('UPDATE maquinas SET codigo = ?, nome = ?, tipo = ?, modelo = ?, fabricante = ?, numero_patrimonio = ?, peca_produzida = ?, setor = ?, criticidade = ?, horas_operacao = ?, proxima_revisao = ?, status_operacional = ? WHERE id = ?')
                ->execute([$codigo, $nome, $tipo ?: null, $modelo ?: null, $fabricante ?: null, $numeroPatrimonio ?: null, $pecaProduzida ?: null, $setor ?: null, $criticidade, $horasOperacao, $proximaRevisao, $statusOperacional, $id]);
            log_auditoria('editar', 'maquina', $id, "Editou a máquina \"$codigo — $nome\"");
            flash_set('sucesso', 'Máquina atualizada com sucesso.');
        }
    } elseif ($acao === 'toggle_ativo') {
        $id = (int)($_POST['id'] ?? 0);
        $stmtM = db()->prepare('SELECT codigo, nome FROM maquinas WHERE id = ?');
        $stmtM->execute([$id]);
        $alvo = $stmtM->fetch();
        db()->prepare('UPDATE maquinas SET ativo = NOT ativo WHERE id = ?')->execute([$id]);
        log_auditoria('toggle_ativo', 'maquina', $id, 'Alterou o status da máquina "' . ($alvo ? $alvo['codigo'] . ' — ' . $alvo['nome'] : "ID $id") . '"');
        flash_set('sucesso', 'Status da máquina atualizado.');
    } else {
        flash_set('erro', 'Ação inválida.');
    }
} catch (PDOException $e) {
    flash_set('erro', 'Já existe uma máquina com esse código, ou dados inválidos.');
} catch (Exception $e) {
    flash_set('erro', 'Erro ao processar a solicitação.');
}

redirect(base_url('admin/maquinas.php'));
