<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/Mailer.php';

function emails_gestao(): array
{
    $stmt = db()->prepare("
        SELECT email FROM usuarios
        WHERE ativo = 1 AND role IN (?, ?, ?, ?) AND email IS NOT NULL AND email <> ''
    ");
    $stmt->execute([ROLE_ADMIN, ROLE_SUPERVISOR, ROLE_GERENTE, ROLE_MANUTENCAO]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function link_checklist(int $checklistId): string
{
    return rtrim(APP_URL_PUBLICA, '/') . base_url('checklist/detalhe.php?id=' . $checklistId);
}

/**
 * Dispara na hora, ao salvar um checklist Não Conforme (chamado em checklist/salvar.php).
 */
function notificar_nao_conformidade(int $checklistId): void
{
    $stmt = db()->prepare("
        SELECT c.id, c.data_checklist, c.tipo_checklist, c.turno,
               m.codigo AS maquina_codigo, m.nome AS maquina_nome,
               u.nome AS operador_nome, u.matricula
        FROM checklists c
        JOIN maquinas m ON m.id = c.maquina_id
        JOIN usuarios u ON u.id = c.user_id
        WHERE c.id = ?
    ");
    $stmt->execute([$checklistId]);
    $c = $stmt->fetch();
    if (!$c) {
        return;
    }

    $destinatarios = emails_gestao();
    if (!$destinatarios) {
        return;
    }

    $stmtItens = db()->prepare("
        SELECT i.descricao, r.observacao
        FROM checklist_respostas r
        JOIN checklist_itens i ON i.id = r.item_id
        WHERE r.checklist_id = ? AND r.status = 'nao_conforme'
    ");
    $stmtItens->execute([$checklistId]);
    $itens = $stmtItens->fetchAll();

    $itensHtml = '';
    foreach ($itens as $it) {
        $itensHtml .= '<li><strong>' . e($it['descricao']) . ':</strong> ' . nl2br(e($it['observacao'])) . '</li>';
    }

    $corpo = '
        <div style="font-family: Arial, sans-serif; font-size: 14px; color: #1c2130;">
            <h2 style="color:#c8372f; margin-bottom: 4px;">Não conformidade registrada — ' . e($c['maquina_codigo']) . '</h2>
            <p><strong>Máquina:</strong> ' . e($c['maquina_codigo']) . ' — ' . e($c['maquina_nome']) . '</p>
            <p><strong>Operador:</strong> ' . e($c['operador_nome']) . ' (matrícula ' . e($c['matricula']) . ')</p>
            <p><strong>Tipo:</strong> ' . e(tipo_checklist_label($c['tipo_checklist'])) . ' &mdash; <strong>Turno:</strong> ' . e(turno_label($c['turno'])) . '</p>
            <p><strong>Data:</strong> ' . e(format_data_br($c['data_checklist'])) . '</p>
            <p><strong>Itens não conformes:</strong></p>
            <ul>' . $itensHtml . '</ul>
            <p><a href="' . e(link_checklist($checklistId)) . '">Ver checklist completo no AeroCheck</a></p>
            <p style="color:#6b7484; font-size:12px;">Este link só abre a partir da rede local da fábrica (ou VPN até lá).</p>
        </div>
    ';

    Mailer::enviar($destinatarios, '[AeroCheck] Não conformidade — ' . $c['maquina_codigo'], $corpo);
}

/**
 * Disparado pelo scripts/verificar_alertas.php quando uma ou mais não conformidades ficam
 * tempo demais sem ninguém marcar "Visto". Manda UM e-mail só, com todas juntas — em vez
 * de um e-mail por checklist, o que ficaria inconveniente se várias vencerem o prazo ao
 * mesmo tempo (ex.: troca de turno).
 */
function notificar_escalonamento_lote(array $checklistIds): bool
{
    if (!$checklistIds) {
        return false;
    }

    $placeholders = implode(',', array_fill(0, count($checklistIds), '?'));
    $stmt = db()->prepare("
        SELECT c.id, c.created_at, c.tipo_checklist, c.turno,
               m.codigo AS maquina_codigo, m.nome AS maquina_nome,
               u.nome AS operador_nome
        FROM checklists c
        JOIN maquinas m ON m.id = c.maquina_id
        JOIN usuarios u ON u.id = c.user_id
        WHERE c.id IN ($placeholders)
        ORDER BY c.created_at ASC
    ");
    $stmt->execute($checklistIds);
    $itens = $stmt->fetchAll();
    if (!$itens) {
        return false;
    }

    $destinatarios = emails_gestao();
    if (!$destinatarios) {
        return false;
    }

    $linhas = '';
    foreach ($itens as $c) {
        $minutos = (int)round((time() - strtotime($c['created_at'])) / 60);
        $linhas .= '
            <tr>
                <td style="padding:6px 10px; border-bottom:1px solid #e4e8ef;">' . e($c['maquina_codigo']) . ' — ' . e($c['maquina_nome']) . '</td>
                <td style="padding:6px 10px; border-bottom:1px solid #e4e8ef;">' . e($c['operador_nome']) . '</td>
                <td style="padding:6px 10px; border-bottom:1px solid #e4e8ef;">' . e(tipo_checklist_label($c['tipo_checklist'])) . ' / ' . e(turno_label($c['turno'])) . '</td>
                <td style="padding:6px 10px; border-bottom:1px solid #e4e8ef;">' . $minutos . ' min</td>
                <td style="padding:6px 10px; border-bottom:1px solid #e4e8ef;"><a href="' . e(link_checklist((int)$c['id'])) . '">Ver</a></td>
            </tr>
        ';
    }

    $total = count($itens);
    $corpo = '
        <div style="font-family: Arial, sans-serif; font-size: 14px; color: #1c2130;">
            <h2 style="color:#966600; margin-bottom: 4px;">' . $total . ' não conformidade(s) sem confirmação</h2>
            <p>Ninguém marcou "Visto" nestes checklists ainda:</p>
            <table style="border-collapse: collapse; width: 100%;">
                <thead>
                    <tr style="text-align:left; color:#6b7484; font-size:12px; text-transform:uppercase;">
                        <th style="padding:6px 10px;">Máquina</th>
                        <th style="padding:6px 10px;">Operador</th>
                        <th style="padding:6px 10px;">Tipo / Turno</th>
                        <th style="padding:6px 10px;">Em aberto</th>
                        <th style="padding:6px 10px;"></th>
                    </tr>
                </thead>
                <tbody>' . $linhas . '</tbody>
            </table>
        </div>
    ';

    $assunto = $total === 1
        ? '[AeroCheck] Alerta — 1 não conformidade sem confirmação'
        : "[AeroCheck] Alerta — $total não conformidades sem confirmação";

    return Mailer::enviar($destinatarios, $assunto, $corpo);
}

/**
 * Disparado pelo scripts/verificar_pendencias_turno.php quando uma ou mais máquinas ativas
 * não têm o checklist de Início ou Fim registrado por ninguém, num turno específico, numa
 * data específica. Um e-mail por checagem (Início/Fim x Manhã/Noite), não por máquina.
 */
function notificar_pendencias_turno(string $turno, string $tipo, array $maquinas, string $dataReferencia): bool
{
    if (!$maquinas) {
        return false;
    }

    $destinatarios = emails_gestao();
    if (!$destinatarios) {
        return false;
    }

    $linhas = '';
    foreach ($maquinas as $m) {
        $linhas .= '<tr><td style="padding:6px 10px; border-bottom:1px solid #e4e8ef;">' . e($m['codigo']) . ' — ' . e($m['maquina_nome']) . '</td></tr>';
    }

    $tituloTipo = tipo_checklist_label($tipo);
    $tituloTurno = turno_label($turno);
    $total = count($maquinas);

    $corpo = '
        <div style="font-family: Arial, sans-serif; font-size: 14px; color: #1c2130;">
            <h2 style="color:#c8372f; margin-bottom: 4px;">Checklist de ' . e($tituloTipo) . ' pendente — Turno ' . e($tituloTurno) . '</h2>
            <p><strong>Data:</strong> ' . e(format_data_br($dataReferencia)) . '</p>
            <p>Nenhum operador registrou o checklist de <strong>' . e($tituloTipo) . '</strong> nestas máquinas, no turno <strong>' . e($tituloTurno) . '</strong>:</p>
            <table style="border-collapse: collapse; width: 100%;">
                <thead>
                    <tr style="text-align:left; color:#6b7484; font-size:12px; text-transform:uppercase;">
                        <th style="padding:6px 10px;">Máquina</th>
                    </tr>
                </thead>
                <tbody>' . $linhas . '</tbody>
            </table>
            <p style="color:#6b7484; font-size:12px;">Alerta automático do AeroCheck — checklist local de segurança/planejamento do turno, não é abertura de chamado de manutenção.</p>
        </div>
    ';

    $assunto = $total === 1
        ? "[AeroCheck] 1 máquina sem checklist de $tituloTipo — Turno $tituloTurno"
        : "[AeroCheck] $total máquinas sem checklist de $tituloTipo — Turno $tituloTurno";

    return Mailer::enviar($destinatarios, $assunto, $corpo);
}

function marcar_visto(int $checklistId, int $userId): void
{
    db()->prepare("UPDATE checklists SET visto_por = ?, visto_em = NOW() WHERE id = ? AND status_geral = 'nao_conforme' AND visto_por IS NULL")
        ->execute([$userId, $checklistId]);
}
