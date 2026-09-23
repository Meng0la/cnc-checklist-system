<?php
/**
 * AeroCheck - verifica não conformidades sem confirmação ("Visto") há tempo demais e
 * dispara um e-mail de cobrança para a gestão. Roda via linha de comando (CLI), agendado
 * no Agendador de Tarefas do Windows — veja o passo a passo no README.md.
 *
 * Uso manual (para testar): php.exe verificar_alertas.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notificacoes.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Este script só pode ser executado via linha de comando (CLI).');
}

$stmt = db()->prepare("
    SELECT id FROM checklists
    WHERE status_geral = 'nao_conforme'
      AND visto_por IS NULL
      AND escalonamento_enviado = 0
      AND created_at <= DATE_SUB(NOW(), INTERVAL ? MINUTE)
");
$stmt->execute([ALERTA_ESCALACAO_MINUTOS]);
$pendentes = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

if (!$pendentes) {
    echo "[" . date('Y-m-d H:i:s') . "] 0 checklist(s) pendente(s) de confirmação.\n";
    exit;
}

// Um e-mail só juntando todas as pendências deste disparo (em vez de um por checklist).
$enviado = notificar_escalonamento_lote($pendentes);

if ($enviado) {
    $placeholders = implode(',', array_fill(0, count($pendentes), '?'));
    db()->prepare("UPDATE checklists SET escalonamento_enviado = 1 WHERE id IN ($placeholders)")->execute($pendentes);
    echo "[" . date('Y-m-d H:i:s') . "] " . count($pendentes) . " checklist(s) incluído(s) em 1 e-mail de alerta.\n";
} else {
    echo "[" . date('Y-m-d H:i:s') . "] " . count($pendentes) . " checklist(s) pendente(s), mas o e-mail não foi enviado (sem destinatário cadastrado ou falha no SMTP — veja o log de erros do PHP). Vai tentar de novo na próxima execução.\n";
}
