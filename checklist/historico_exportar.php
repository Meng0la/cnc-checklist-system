<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = require_login();
$isGestao = in_array($user['role'], ROLES_GESTAO, true);

$filtroMaquina = isset($_GET['maquina_id']) ? (int)$_GET['maquina_id'] : 0;
$filtroStatus = $_GET['status'] ?? '';
$filtroDataIni = $_GET['data_ini'] ?? '';
$filtroDataFim = $_GET['data_fim'] ?? '';
$filtroTurno = $_GET['turno'] ?? '';
$filtroOperador = trim($_GET['operador'] ?? '');

$where = [];
$params = [];

if (!$isGestao) {
    $where[] = 'c.user_id = ?';
    $params[] = $user['id'];
}
if ($filtroMaquina) {
    $where[] = 'c.maquina_id = ?';
    $params[] = $filtroMaquina;
}
if (in_array($filtroStatus, ['conforme', 'nao_conforme'], true)) {
    $where[] = 'c.status_geral = ?';
    $params[] = $filtroStatus;
}
if ($filtroDataIni !== '') {
    $where[] = 'c.data_checklist >= ?';
    $params[] = $filtroDataIni;
}
if ($filtroDataFim !== '') {
    $where[] = 'c.data_checklist <= ?';
    $params[] = $filtroDataFim;
}
if (in_array($filtroTurno, ['manha', 'tarde', 'noite'], true)) {
    $where[] = 'c.turno = ?';
    $params[] = $filtroTurno;
}
if ($isGestao && $filtroOperador !== '') {
    $where[] = '(u.nome LIKE ? OR u.matricula LIKE ?)';
    $params[] = '%' . $filtroOperador . '%';
    $params[] = '%' . $filtroOperador . '%';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = db()->prepare("
    SELECT c.id, c.data_checklist, c.tipo_checklist, c.turno, c.status_geral, c.visto_por, c.visto_em,
           m.codigo AS maquina_codigo, m.nome AS maquina_nome,
           u.nome AS operador_nome, u.matricula,
           v.nome AS visto_por_nome,
           GROUP_CONCAT(CASE WHEN r.status = 'nao_conforme' THEN CONCAT(i.descricao, ': ', r.observacao) END SEPARATOR ' | ') AS detalhes_nc
    FROM checklists c
    JOIN maquinas m ON m.id = c.maquina_id
    JOIN usuarios u ON u.id = c.user_id
    LEFT JOIN usuarios v ON v.id = c.visto_por
    LEFT JOIN checklist_respostas r ON r.checklist_id = c.id
    LEFT JOIN checklist_itens i ON i.id = r.item_id
    $whereSql
    GROUP BY c.id, c.data_checklist, c.tipo_checklist, c.turno, c.status_geral, c.visto_por, c.visto_em, m.codigo, m.nome, u.nome, u.matricula, v.nome
    ORDER BY c.data_checklist DESC, c.id DESC
    LIMIT 5000
");
$stmt->execute($params);

$nomeArquivo = 'checklists_' . date('Y-m-d_His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');
fputcsv($out, ['Data', 'Máquina', 'Tipo', 'Turno', 'Operador', 'Matrícula', 'Status', 'Confirmação (Visto)', 'Detalhes das não conformidades'], ';');

while ($row = $stmt->fetch()) {
    $confirmacao = '—';
    if ($row['status_geral'] === 'nao_conforme') {
        $confirmacao = $row['visto_por']
            ? 'Visto por ' . $row['visto_por_nome'] . ' em ' . format_datahora_br($row['visto_em'])
            : 'Não confirmado';
    }
    fputcsv($out, [
        format_data_br($row['data_checklist']),
        $row['maquina_codigo'] . ' — ' . $row['maquina_nome'],
        tipo_checklist_label($row['tipo_checklist']),
        turno_label($row['turno']),
        $row['operador_nome'],
        $row['matricula'],
        $row['status_geral'] === 'conforme' ? 'Conforme' : 'Não Conforme',
        $confirmacao,
        $row['detalhes_nc'] ?? '',
    ], ';');
}
fclose($out);
exit;
