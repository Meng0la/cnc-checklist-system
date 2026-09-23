<?php
/** @var string|null $pageTitle */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? e($pageTitle) . ' – ' : '' ?><?= APP_NAME ?></title>
<link rel="icon" href="<?= base_url('assets/img/icon.png') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>
<?php if (is_logged_in()): $u = current_user(); ?>
<div class="app-shell">
  <div class="sidebar-overlay" id="sidebar-overlay"></div>
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <a href="<?= base_url('dashboard.php') ?>">
        <img src="<?= base_url('assets/img/logo.png') ?>" alt="<?= APP_NAME ?>" class="sidebar-logo">
      </a>
    </div>
    <nav class="sidebar-nav">
      <a href="<?= base_url('dashboard.php') ?>" class="nav-link<?= nav_active('dashboard.php') ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        <span>Painel</span>
      </a>
      <?php if ($u['role'] === ROLE_OPERADOR): ?>
      <a href="<?= base_url('checklist/novo.php') ?>" class="nav-link<?= nav_active('checklist/novo.php') ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
        <span>Novo Checklist</span>
      </a>
      <?php endif; ?>
      <a href="<?= base_url('checklist/historico.php') ?>" class="nav-link<?= nav_active('checklist/historico.php') ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 16 14"/></svg>
        <span>Histórico</span>
      </a>
      <?php if (in_array($u['role'], ROLES_GESTAO, true)): ?>
      <a href="<?= base_url('relatorios/pendencias.php') ?>" class="nav-link<?= nav_active('relatorios/pendencias.php') ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <span>Pendências</span>
      </a>
      <a href="<?= base_url('relatorios/conformidade.php') ?>" class="nav-link<?= nav_active('relatorios/conformidade.php') ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
        <span>Conformidade</span>
      </a>
      <a href="<?= base_url('relatorios/contradicoes.php') ?>" class="nav-link<?= nav_active('relatorios/contradicoes.php') ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h7l-1 8 10-12h-7l1-8z"/></svg>
        <span>Contradições</span>
      </a>
      <a href="<?= base_url('admin/maquinas.php') ?>" class="nav-link<?= nav_active('admin/maquinas.php') ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><line x1="9" y1="1" x2="9" y2="4"/><line x1="15" y1="1" x2="15" y2="4"/><line x1="9" y1="20" x2="9" y2="23"/><line x1="15" y1="20" x2="15" y2="23"/><line x1="1" y1="9" x2="4" y2="9"/><line x1="1" y1="15" x2="4" y2="15"/><line x1="20" y1="9" x2="23" y2="9"/><line x1="20" y1="15" x2="23" y2="15"/></svg>
        <span>Máquinas</span>
      </a>
      <?php endif; ?>
      <?php if (in_array($u['role'], ROLES_CADASTRO, true)): ?>
      <div class="sidebar-section-label">Cadastros</div>
      <a href="<?= base_url('admin/usuarios.php') ?>" class="nav-link<?= nav_active('admin/usuarios.php') ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <span>Usuários</span>
      </a>
      <a href="<?= base_url('admin/itens.php') ?>" class="nav-link<?= nav_active('admin/itens.php') ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        <span>Itens do Checklist</span>
      </a>
      <a href="<?= base_url('admin/auditoria.php') ?>" class="nav-link<?= nav_active('admin/auditoria.php') ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        <span>Auditoria</span>
      </a>
      <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="user-avatar"><?= e(mb_strtoupper(mb_substr($u['nome'], 0, 1))) ?></div>
        <div class="user-meta">
          <span class="user-name"><?= e($u['nome']) ?></span>
          <span class="user-role"><?= e(role_label($u['role'])) ?></span>
        </div>
      </div>
      <div class="sidebar-actions">
        <a href="<?= base_url('trocar_senha.php') ?>">Trocar senha</a>
        <a href="<?= base_url('logout.php') ?>">Sair</a>
      </div>
    </div>
  </aside>
  <div class="main-col">
    <header class="topbar">
      <button type="button" class="nav-toggle" id="nav-toggle" aria-label="Abrir menu">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <span class="topbar-title"><?= isset($pageTitle) ? e($pageTitle) : APP_NAME ?></span>
    </header>
    <main class="content">
<?php foreach (flash_get_all() as $f): ?>
  <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
<?php endforeach; ?>
<?php else: ?>
<main class="content content-guest">
<?php foreach (flash_get_all() as $f): ?>
  <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
<?php endforeach; ?>
<?php endif; ?>
