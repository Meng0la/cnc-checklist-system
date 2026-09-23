<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect(base_url('dashboard.php'));
}

$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $erro = 'Sessão expirada. Tente novamente.';
    } else {
        $matricula = trim($_POST['matricula'] ?? '');
        $senha = (string)($_POST['senha'] ?? '');

        if ($matricula === '' || $senha === '') {
            $erro = 'Informe matrícula e senha.';
        } else {
            $stmt = db()->prepare('SELECT * FROM usuarios WHERE matricula = ? LIMIT 1');
            $stmt->execute([$matricula]);
            $user = $stmt->fetch();

            if (!$user) {
                $erro = 'Matrícula ou senha inválida.';
            } elseif (!$user['ativo']) {
                $erro = 'Usuário inativo. Procure o supervisor ou administrador.';
            } elseif ($user['bloqueado_ate'] && strtotime($user['bloqueado_ate']) > time()) {
                $erro = 'Usuário bloqueado temporariamente por excesso de tentativas. Tente novamente mais tarde ou procure o supervisor.';
            } elseif (!password_verify($senha, $user['senha_hash'])) {
                $tentativas = (int)$user['tentativas_falhas'] + 1;
                $bloqueadoAte = null;
                if ($tentativas >= 5) {
                    $bloqueadoAte = date('Y-m-d H:i:s', time() + 900);
                    $tentativas = 0;
                }
                $upd = db()->prepare('UPDATE usuarios SET tentativas_falhas = ?, bloqueado_ate = ? WHERE id = ?');
                $upd->execute([$tentativas, $bloqueadoAte, $user['id']]);
                $erro = $bloqueadoAte ? 'Muitas tentativas incorretas. Usuário bloqueado por 15 minutos.' : 'Matrícula ou senha inválida.';
            } else {
                db()->prepare('UPDATE usuarios SET tentativas_falhas = 0, bloqueado_ate = NULL, ultimo_login = NOW() WHERE id = ?')
                    ->execute([$user['id']]);
                login_user($user);
                redirect(base_url((int)$user['primeiro_login'] === 1 ? 'trocar_senha.php' : 'dashboard.php'));
            }
        }
    }
}

$pageTitle = 'Login';
require __DIR__ . '/includes/header.php';
?>
<div class="login-wrap">
  <div class="login-card">
    <img src="<?= base_url('assets/img/logo.png') ?>" alt="<?= APP_NAME ?>" class="login-logo">
    <h1>Entrar</h1>
    <?php if ($erro): ?><div class="alert alert-erro"><?= e($erro) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <label>Matrícula
        <input type="text" name="matricula" required autofocus inputmode="numeric">
      </label>
      <label>Senha
        <input type="password" name="senha" required>
      </label>
      <button type="submit" class="btn btn-primary btn-block">Entrar</button>
    </form>
    <p class="login-hint">Esqueceu a senha? Procure seu supervisor ou administrador.</p>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
