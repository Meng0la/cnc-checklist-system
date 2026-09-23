<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

$motivo = $_GET['motivo'] ?? '';

logout_user();
session_start();
if ($motivo === 'inatividade') {
    flash_set('aviso', 'Você foi desconectado automaticamente por inatividade.');
}
redirect(base_url('login.php'));
