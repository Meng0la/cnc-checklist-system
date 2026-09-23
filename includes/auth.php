<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

const ROLE_OPERADOR = 'operador';
const ROLE_ADMIN = 'administrador';
const ROLE_SUPERVISOR = 'supervisor_producao';
const ROLE_GERENTE = 'gerente';
const ROLE_MANUTENCAO = 'manutencao';

const ROLES_GESTAO = [ROLE_ADMIN, ROLE_SUPERVISOR, ROLE_GERENTE, ROLE_MANUTENCAO];
const ROLES_CADASTRO = [ROLE_ADMIN, ROLE_SUPERVISOR];
const ROLES_TODAS = [ROLE_OPERADOR, ROLE_ADMIN, ROLE_SUPERVISOR, ROLE_GERENTE, ROLE_MANUTENCAO];

// Supervisor só pode criar/gerenciar contas Operador — qualquer outro perfil (inclusive
// outro Supervisor, Gerente ou Manutenção) fica restrito ao Administrador.
function pode_gerenciar_role(string $role, bool $isAdmin): bool
{
    return $isAdmin || $role === ROLE_OPERADOR;
}

function role_label(string $role): string
{
    $labels = [
        ROLE_OPERADOR => 'Operador',
        ROLE_ADMIN => 'Administrador',
        ROLE_SUPERVISOR => 'Supervisor de Produção',
        ROLE_GERENTE => 'Gerente',
        ROLE_MANUTENCAO => 'Manutenção',
    ];
    return $labels[$role] ?? $role;
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }
    static $user = null;
    if ($user === null) {
        $stmt = db()->prepare('SELECT id, matricula, nome, role, ativo, primeiro_login FROM usuarios WHERE id = ? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $found = $stmt->fetch() ?: null;
        if (!$found || !$found['ativo']) {
            logout_user();
            return null;
        }
        $user = $found;
    }
    return $user;
}

function require_login(): array
{
    if (!is_logged_in()) {
        redirect(base_url('login.php'));
    }

    if (isset($_SESSION['ultima_atividade']) && (time() - $_SESSION['ultima_atividade']) > SESSAO_IDLE_SEGUNDOS) {
        logout_user();
        session_start();
        flash_set('aviso', 'Sua sessão expirou após ' . (int)(SESSAO_IDLE_SEGUNDOS / 60) . ' minutos de inatividade. Faça login novamente.');
        redirect(base_url('login.php'));
    }
    $_SESSION['ultima_atividade'] = time();

    $user = current_user();
    if (!$user) {
        redirect(base_url('login.php'));
    }
    if ((int)$user['primeiro_login'] === 1 && !ends_with($_SERVER['SCRIPT_NAME'], 'trocar_senha.php')) {
        redirect(base_url('trocar_senha.php'));
    }
    return $user;
}

function require_role(array $roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        require BASE_PATH . '/403.php';
        exit;
    }
    return $user;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['ultima_atividade'] = time();
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
