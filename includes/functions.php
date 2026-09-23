<?php
require_once __DIR__ . '/../config/database.php';

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_get_all(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function ends_with(string $haystack, string $needle): bool
{
    $length = strlen($needle);
    return $length === 0 || substr($haystack, -$length) === $needle;
}

function nav_active(string $needle): string
{
    return strpos(str_replace('\\', '/', $_SERVER['SCRIPT_NAME']), $needle) !== false ? ' active' : '';
}

function format_data_br(string $data): string
{
    $dt = DateTime::createFromFormat('Y-m-d', $data);
    return $dt ? $dt->format('d/m/Y') : $data;
}

function format_datahora_br(string $datahora): string
{
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $datahora);
    return $dt ? $dt->format('d/m/Y H:i') : $datahora;
}

function turno_label(string $turno): string
{
    $labels = ['manha' => 'Manhã', 'tarde' => 'Tarde', 'noite' => 'Noite'];
    return $labels[$turno] ?? $turno;
}

function tipo_checklist_label(string $tipo): string
{
    return $tipo === 'inicio' ? 'Início de Turno' : 'Fim de Turno';
}

/**
 * Retorna, para a data informada, as máquinas ativas que ainda não têm nenhum checklist
 * de início e/ou fim de turno registrado (por qualquer operador). Não depende de vínculo
 * fixo operador-máquina — com a alta rotatividade de operadores, o que importa é se a
 * máquina foi checada naquele dia, não quem especificamente deveria ter checado.
 */
function get_pendencias(string $data): array
{
    $sql = "
        SELECT m.id AS maquina_id, m.codigo, m.nome AS maquina_nome, tipo.tipo_checklist
        FROM maquinas m
        CROSS JOIN (SELECT 'inicio' AS tipo_checklist UNION SELECT 'fim') tipo
        WHERE m.ativo = 1
          AND NOT EXISTS (
            SELECT 1 FROM checklists c
            WHERE c.maquina_id = m.id
              AND c.tipo_checklist = tipo.tipo_checklist
              AND c.data_checklist = ?
        )
        ORDER BY m.codigo, tipo.tipo_checklist
    ";
    $stmt = db()->prepare($sql);
    $stmt->execute([$data]);
    return $stmt->fetchAll();
}

/**
 * Igual a get_pendencias(), mas cobrando um turno e tipo específicos — usado pelo alerta
 * de e-mail (scripts/verificar_pendencias_turno.php), que confere Início e Fim de cada
 * turno em horários separados (ex.: cobra o Início pouco depois do turno começar, e o Fim
 * pouco depois do turno acabar).
 */
function get_pendencias_turno(string $data, string $turno, string $tipo): array
{
    $sql = "
        SELECT m.id AS maquina_id, m.codigo, m.nome AS maquina_nome
        FROM maquinas m
        WHERE m.ativo = 1
          AND NOT EXISTS (
            SELECT 1 FROM checklists c
            WHERE c.maquina_id = m.id
              AND c.tipo_checklist = ?
              AND c.turno = ?
              AND c.data_checklist = ?
        )
        ORDER BY m.codigo
    ";
    $stmt = db()->prepare($sql);
    $stmt->execute([$tipo, $turno, $data]);
    return $stmt->fetchAll();
}

/**
 * Cruzamento de dados entre turnos: encontra casos em que o checklist de FIM de turno de
 * uma máquina foi marcado como Conforme, mas o checklist de INÍCIO de turno seguinte —
 * feito pelo próximo operador que pegou a mesma máquina — veio Não Conforme. Isso é um
 * sinal de alerta: ou algo aconteceu com a máquina entre os dois turnos (uso não
 * autorizado, pane), ou o checklist de fim de turno anterior não foi feito corretamente.
 *
 * Implementado só com JOIN + NOT EXISTS (sem window function/CTE) de propósito — algumas
 * instalações de MariaDB/MySQL mais antigas em XAMPP não suportam LEAD()/OVER, e isso já
 * derrubou a página com erro 500 uma vez. "inicio" é o próximo checklist da mesma máquina
 * que não tem nenhum outro checklist entre ele e o "fim" (usa created_at, com id como
 * desempate para o caso raro de dois registros no mesmo segundo).
 */
function get_contradicoes(?int $maquinaId = null, ?string $dataIni = null, ?string $dataFim = null): array
{
    $where = [];
    $params = [];
    if ($maquinaId) {
        $where[] = 'fim.maquina_id = ?';
        $params[] = $maquinaId;
    }
    if ($dataIni) {
        $where[] = 'inicio.data_checklist >= ?';
        $params[] = $dataIni;
    }
    if ($dataFim) {
        $where[] = 'inicio.data_checklist <= ?';
        $params[] = $dataFim;
    }
    $whereSql = $where ? ('AND ' . implode(' AND ', $where)) : '';

    $sql = "
        SELECT
            fim.id AS fim_id, fim.data_checklist AS fim_data, fim.created_at AS fim_created_at,
            ufim.nome AS fim_operador_nome, ufim.matricula AS fim_matricula,
            inicio.id AS inicio_id, inicio.data_checklist AS inicio_data, inicio.created_at AS inicio_created_at,
            uinicio.nome AS inicio_operador_nome, uinicio.matricula AS inicio_matricula,
            m.id AS maquina_id, m.codigo AS maquina_codigo, m.nome AS maquina_nome,
            (SELECT COUNT(*) FROM checklist_respostas r WHERE r.checklist_id = inicio.id AND r.status = 'nao_conforme') AS qtd_nao_conforme,
            (SELECT GROUP_CONCAT(CONCAT(i.descricao, ': ', r.observacao) SEPARATOR '||')
             FROM checklist_respostas r JOIN checklist_itens i ON i.id = r.item_id
             WHERE r.checklist_id = inicio.id AND r.status = 'nao_conforme') AS detalhes_nc
        FROM checklists fim
        JOIN maquinas m ON m.id = fim.maquina_id
        JOIN usuarios ufim ON ufim.id = fim.user_id
        JOIN checklists inicio
            ON inicio.maquina_id = fim.maquina_id
           AND inicio.tipo_checklist = 'inicio' AND inicio.status_geral = 'nao_conforme'
           AND (inicio.created_at > fim.created_at OR (inicio.created_at = fim.created_at AND inicio.id > fim.id))
        JOIN usuarios uinicio ON uinicio.id = inicio.user_id
        WHERE fim.tipo_checklist = 'fim' AND fim.status_geral = 'conforme'
          AND NOT EXISTS (
              SELECT 1 FROM checklists x
              WHERE x.maquina_id = fim.maquina_id
                AND (x.created_at > fim.created_at OR (x.created_at = fim.created_at AND x.id > fim.id))
                AND (x.created_at < inicio.created_at OR (x.created_at = inicio.created_at AND x.id < inicio.id))
          )
          $whereSql
        ORDER BY inicio.created_at DESC
    ";
    try {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Ranking de operadores por taxa de conformidade no período (para reconhecer quem faz o
 * checklist certinho, não só apontar problema). Exige um mínimo de checklists no período
 * para não deixar 1 checklist 100% conforme distorcer o ranking.
 */
function get_ranking_operadores(?string $dataIni, ?string $dataFim, ?int $maquinaId = null, int $minimoChecklists = 3, int $limite = 8): array
{
    $where = ['c.data_checklist BETWEEN ? AND ?'];
    $params = [$dataIni, $dataFim];
    if ($maquinaId) {
        $where[] = 'c.maquina_id = ?';
        $params[] = $maquinaId;
    }
    $whereSql = implode(' AND ', $where);
    $params[] = $minimoChecklists;
    $limite = max(1, (int)$limite);

    $sql = "
        SELECT u.id, u.nome, u.matricula,
               COUNT(*) AS total_checklists,
               SUM(c.status_geral = 'conforme') AS total_conforme
        FROM checklists c
        JOIN usuarios u ON u.id = c.user_id
        WHERE $whereSql
        GROUP BY u.id, u.nome, u.matricula
        HAVING total_checklists >= ?
        ORDER BY (total_conforme / total_checklists) DESC, total_checklists DESC
        LIMIT $limite
    ";
    try {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Formata a diferença entre dois DATETIME (Y-m-d H:i:s) como "Xh Ymin" ou "X dia(s) e Yh".
 */
function formatar_duracao(string $inicio, string $fim): string
{
    $dtInicio = new DateTime($inicio);
    $dtFim = new DateTime($fim);
    $diff = $dtInicio->diff($dtFim);

    if ($diff->days > 0) {
        return $diff->days . ' dia(s) e ' . $diff->h . 'h';
    }
    if ($diff->h > 0) {
        return $diff->h . 'h ' . $diff->i . 'min';
    }
    return $diff->i . ' min';
}
