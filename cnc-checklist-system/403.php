<?php
if (!defined('APP_NAME')) {
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/includes/auth.php';
}
$pageTitle = 'Acesso Negado';
require __DIR__ . '/includes/header.php';
?>
<div class="page-narrow">
  <h1>Acesso negado</h1>
  <p>Você não tem permissão para acessar esta página.</p>
  <a href="<?= base_url('dashboard.php') ?>" class="btn btn-primary">Voltar ao painel</a>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
