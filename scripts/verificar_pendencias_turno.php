<?php
/**
 * AeroCheck - verifica se alguma máquina ativa não teve o checklist de Início ou Fim de um
 * turno específico registrado por ninguém, e dispara um e-mail de alerta para a gestão.
 * Roda via linha de comando (CLI), 4x por dia (uma vez pra cada combinação abaixo) —
 * agendado no Agendador de Tarefas do Windows, veja o passo a passo no README.md.
 *
 * Uso: php.exe verificar_pendencias_turno.php <turno> <tipo>
 *   <turno> = manha | noite
 *   <tipo>  = inicio | fim
 *
 * Sábados e domingos são opcionais no sistema — o script não alerta nesses dias.
 *
 * O turno da noite cruza a meia-noite (ex.: 16h30 às 02h30). Por isso, a checagem de Fim da
 * noite é pensada para rodar já de madrugada (dia seguinte ao início do turno) — nesse
 * caso, o dia de referência (pra saber se era dia útil) é o dia ANTERIOR ao dia em que o
 * script roda, mas a data buscada no banco é a de HOJE, porque é assim que o checklist
 * de Fim é salvo quando o operador confirma depois da meia-noite (CURDATE() no momento do
 * envio).
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notificacoes.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Este script só pode ser executado via linha de comando (CLI).');
}

$turno = $argv[1] ?? '';
$tipo = $argv[2] ?? '';

if (!in_array($turno, ['manha', 'noite'], true) || !in_array($tipo, ['inicio', 'fim'], true)) {
    fwrite(STDERR, "Uso: php.exe verificar_pendencias_turno.php <manha|noite> <inicio|fim>\n");
    exit(1);
}

$dataConsulta = date('Y-m-d');
$dataReferencia = ($turno === 'noite' && $tipo === 'fim') ? date('Y-m-d', strtotime('-1 day')) : $dataConsulta;

$diaSemana = (int)date('N', strtotime($dataReferencia)); // 1 = segunda ... 7 = domingo
if ($diaSemana >= 6) {
    echo "[" . date('Y-m-d H:i:s') . "] Fim de semana (" . $dataReferencia . ") — checklist opcional, sem alerta.\n";
    exit;
}

$pendentes = get_pendencias_turno($dataConsulta, $turno, $tipo);

if (!$pendentes) {
    echo "[" . date('Y-m-d H:i:s') . "] Turno " . turno_label($turno) . " / " . tipo_checklist_label($tipo) . " ($dataReferencia): sem pendências.\n";
    exit;
}

$enviado = notificar_pendencias_turno($turno, $tipo, $pendentes, $dataReferencia);

if ($enviado) {
    echo "[" . date('Y-m-d H:i:s') . "] Turno " . turno_label($turno) . " / " . tipo_checklist_label($tipo) . " ($dataReferencia): " . count($pendentes) . " máquina(s) pendente(s) — e-mail de alerta enviado.\n";
} else {
    echo "[" . date('Y-m-d H:i:s') . "] Turno " . turno_label($turno) . " / " . tipo_checklist_label($tipo) . " ($dataReferencia): " . count($pendentes) . " máquina(s) pendente(s), mas o e-mail não foi enviado (sem destinatário cadastrado ou falha no SMTP — veja o log de erros do PHP).\n";
}
