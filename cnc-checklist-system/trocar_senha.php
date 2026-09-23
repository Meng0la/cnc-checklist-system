<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

$user = require_login();
$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $erro = 'Sessão expirada. Recarregue a página.';
    } else {
        $atual = (string)($_POST['senha_atual'] ?? '');
        $nova = (string)($_POST['nova_senha'] ?? '');
        $confirma = (string)($_POST['confirma_senha'] ?? '');

        $stmt = db()->prepare('SELECT senha_hash FROM usuarios WHERE id = ?');
        $stmt->execute([$user['id']]);
        $hashAtual = $stmt->fetchColumn();

        if (!password_verify($atual, (string)$hashAtual)) {
            $erro = 'Senha atual incorreta.';
        } elseif (strlen($nova) < 8) {
            $erro = 'A nova senha deve ter no mínimo 8 caracteres.';
        } elseif ($nova !== $confirma) {
            $erro = 'A confirmação não confere com a nova senha.';
        } elseif ($nova === DEFAULT_PASSWORD) {
            $erro = 'Escolha uma senha diferente da senha padrão.';
        } else {
            $novoHash = password_hash($nova, PASSWORD_DEFAULT);
            db()->prepare('UPDATE usuarios SET senha_hash = ?, primeiro_login = 0 WHERE id = ?')
                ->execute([$novoHash, $user['id']]);
            flash_set('sucesso', 'Senha alterada com sucesso.');
            redirect(base_url('dashboard.php'));
        }
    }
}

$pageTitle = 'Trocar Senha';
require __DIR__ . '/includes/header.php';
?>
<div class="page-narrow">
  <h1>Trocar Senha</h1>
  <?php if ((int)$user['primeiro_login'] === 1): ?>
    <div class="alert alert-aviso">Este é o seu primeiro acesso. Por segurança, defina uma nova senha antes de continuar.</div>
  <?php endif; ?>
  <?php if ($erro): ?><div class="alert alert-erro"><?= e($erro) ?></div><?php endif; ?>
  <form method="post" class="form-card">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <label>Senha atual
      <input type="password" name="senha_atual" required autofocus>
    </label>
    <label>Nova senha
      <input type="password" name="nova_senha" required minlength="8">
    </label>
    <label>Confirmar nova senha
      <input type="password" name="confirma_senha" required minlength="8">
    </label>
    <button type="submit" class="btn btn-primary">Salvar nova senha</button>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
