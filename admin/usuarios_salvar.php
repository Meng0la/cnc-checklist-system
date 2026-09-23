<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auditoria.php';

$user = require_role(ROLES_CADASTRO);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {
    flash_set('erro', 'Requisição inválida.');
    redirect(base_url('admin/usuarios.php'));
}

$acao = $_POST['acao'] ?? '';
$isAdmin = $user['role'] === ROLE_ADMIN;

function usuario_role_atual(int $id): ?string
{
    $stmt = db()->prepare('SELECT role FROM usuarios WHERE id = ?');
    $stmt->execute([$id]);
    $role = $stmt->fetchColumn();
    return $role !== false ? $role : null;
}

function usuario_info(int $id): ?array
{
    $stmt = db()->prepare('SELECT nome, matricula FROM usuarios WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

try {
    if ($acao === 'criar') {
        $matricula = trim($_POST['matricula'] ?? '');
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? '';

        if ($matricula === '' || $nome === '' || !in_array($role, ROLES_TODAS, true)) {
            flash_set('erro', 'Preencha todos os campos corretamente.');
            redirect(base_url('admin/usuarios.php'));
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash_set('erro', 'E-mail inválido.');
            redirect(base_url('admin/usuarios.php'));
        }

        if (!pode_gerenciar_role($role, $isAdmin)) {
            flash_set('erro', 'Supervisor de Produção só pode criar usuários com perfil Operador.');
            redirect(base_url('admin/usuarios.php'));
        }

        $stmtDup = db()->prepare('SELECT id FROM usuarios WHERE matricula = ?');
        $stmtDup->execute([$matricula]);
        if ($stmtDup->fetch()) {
            flash_set('erro', 'Já existe um usuário com essa matrícula.');
            redirect(base_url('admin/usuarios.php'));
        }

        $hash = password_hash(DEFAULT_PASSWORD, PASSWORD_DEFAULT);
        db()->prepare('INSERT INTO usuarios (matricula, nome, email, senha_hash, role, ativo, primeiro_login, created_at) VALUES (?, ?, ?, ?, ?, 1, 1, NOW())')
            ->execute([$matricula, $nome, $email ?: null, $hash, $role]);
        $novoId = (int)db()->lastInsertId();
        log_auditoria('criar', 'usuario', $novoId, "Criou o usuário \"$nome\" (matrícula $matricula, perfil " . role_label($role) . ')');
        flash_set('sucesso', 'Usuário criado com sucesso. Senha padrão: ' . DEFAULT_PASSWORD);
    } elseif ($acao === 'editar') {
        $id = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? '';

        if (!$id || $nome === '' || !in_array($role, ROLES_TODAS, true)) {
            flash_set('erro', 'Preencha todos os campos corretamente.');
            redirect(base_url('admin/usuarios.php'));
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash_set('erro', 'E-mail inválido.');
            redirect(base_url('admin/usuarios.php'));
        }

        $roleAtual = usuario_role_atual($id);
        if (!pode_gerenciar_role($roleAtual ?? '', $isAdmin) || !pode_gerenciar_role($role, $isAdmin)) {
            flash_set('erro', 'Supervisor de Produção só pode gerenciar usuários com perfil Operador.');
            redirect(base_url('admin/usuarios.php'));
        }

        db()->prepare('UPDATE usuarios SET nome = ?, email = ?, role = ? WHERE id = ?')->execute([$nome, $email ?: null, $role, $id]);
        $rotulo = $roleAtual !== $role ? (role_label($roleAtual ?? '') . ' → ' . role_label($role)) : role_label($role);
        log_auditoria('editar', 'usuario', $id, "Editou o usuário \"$nome\" (perfil: $rotulo)");
        flash_set('sucesso', 'Usuário atualizado com sucesso.');
    } elseif ($acao === 'resetar_senha') {
        $id = (int)($_POST['id'] ?? 0);

        if (!pode_gerenciar_role(usuario_role_atual($id) ?? '', $isAdmin)) {
            flash_set('erro', 'Supervisor de Produção só pode resetar senha de usuários com perfil Operador.');
            redirect(base_url('admin/usuarios.php'));
        }

        $alvo = usuario_info($id);
        $hash = password_hash(DEFAULT_PASSWORD, PASSWORD_DEFAULT);
        db()->prepare('UPDATE usuarios SET senha_hash = ?, primeiro_login = 1, tentativas_falhas = 0, bloqueado_ate = NULL WHERE id = ?')
            ->execute([$hash, $id]);
        log_auditoria('resetar_senha', 'usuario', $id, 'Resetou a senha de "' . ($alvo['nome'] ?? "ID $id") . '" para o padrão');
        flash_set('sucesso', 'Senha redefinida para o padrão (' . DEFAULT_PASSWORD . '). O usuário deverá trocá-la no próximo login.');
    } elseif ($acao === 'toggle_ativo') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$user['id']) {
            flash_set('erro', 'Você não pode desativar seu próprio usuário.');
            redirect(base_url('admin/usuarios.php'));
        }

        if (!pode_gerenciar_role(usuario_role_atual($id) ?? '', $isAdmin)) {
            flash_set('erro', 'Supervisor de Produção só pode ativar/desativar usuários com perfil Operador.');
            redirect(base_url('admin/usuarios.php'));
        }

        $alvo = usuario_info($id);
        db()->prepare('UPDATE usuarios SET ativo = NOT ativo WHERE id = ?')->execute([$id]);
        log_auditoria('toggle_ativo', 'usuario', $id, 'Alterou o status de "' . ($alvo['nome'] ?? "ID $id") . '"');
        flash_set('sucesso', 'Status do usuário atualizado.');
    } else {
        flash_set('erro', 'Ação inválida.');
    }
} catch (Exception $e) {
    flash_set('erro', 'Erro ao processar a solicitação.');
}

redirect(base_url('admin/usuarios.php'));
