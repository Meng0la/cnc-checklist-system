<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

function e_local(?string $v): string
{
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

$stmt = db()->query("SELECT COUNT(*) FROM usuarios WHERE role = 'administrador'");
$jaExisteAdmin = (int)$stmt->fetchColumn() > 0;

$mensagem = null;
$erro = null;

if (!$jaExisteAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $matricula = trim($_POST['matricula'] ?? '00001');
    $nome = trim($_POST['nome'] ?? 'Administrador');

    if ($matricula === '' || $nome === '') {
        $erro = 'Preencha matrícula e nome.';
    } else {
        $hash = password_hash(DEFAULT_PASSWORD, PASSWORD_DEFAULT);
        db()->prepare('INSERT INTO usuarios (matricula, nome, senha_hash, role, ativo, primeiro_login, created_at) VALUES (?, ?, ?, ?, 1, 1, NOW())')
            ->execute([$matricula, $nome, $hash, 'administrador']);
        $jaExisteAdmin = true;
        $mensagem = 'Administrador criado com sucesso! Matrícula: ' . e_local($matricula) . ' — Senha padrão: ' . e_local(DEFAULT_PASSWORD);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Instalação — <?= APP_NAME ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <img src="assets/img/logo.png" alt="<?= APP_NAME ?>" class="login-logo">
    <h1>Instalação do <?= APP_NAME ?></h1>
    <?php if ($jaExisteAdmin): ?>
      <?php if ($mensagem): ?><div class="alert alert-sucesso"><?= $mensagem ?></div><?php endif; ?>
      <p>Já existe um administrador cadastrado no sistema.</p>
      <p><strong>Por segurança, apague ou renomeie o arquivo install.php agora.</strong></p>
      <a href="login.php" class="btn btn-primary btn-block">Ir para o login</a>
    <?php else: ?>
      <?php if ($erro): ?><div class="alert alert-erro"><?= e_local($erro) ?></div><?php endif; ?>
      <p>Nenhum administrador encontrado. Crie o primeiro usuário administrador do sistema:</p>
      <form method="post">
        <label>Matrícula <input type="text" name="matricula" value="00001" required></label>
        <label>Nome completo <input type="text" name="nome" value="Administrador" required></label>
        <button type="submit" class="btn btn-primary btn-block">Criar administrador</button>
      </form>
      <p class="login-hint">Senha padrão: <strong><?= e_local(DEFAULT_PASSWORD) ?></strong> (troca obrigatória no primeiro login)</p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
