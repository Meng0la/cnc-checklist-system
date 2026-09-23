<?php
require_once __DIR__ . '/../config/database.php';

function log_auditoria(string $acao, string $entidade, ?int $entidadeId, string $descricao): void
{
    $userId = $_SESSION['user_id'] ?? null;
    db()->prepare('INSERT INTO auditoria (user_id, acao, entidade, entidade_id, descricao, created_at) VALUES (?, ?, ?, ?, ?, NOW())')
        ->execute([$userId, $acao, $entidade, $entidadeId, $descricao]);
}
