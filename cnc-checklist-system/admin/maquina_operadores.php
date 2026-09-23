<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auditoria.php';

$user = require_role(ROLES_CADASTRO);

$maquinaId = (int)($_GET['maquina_id'] ?? 0);
$stmtM = db()->prepare('SELECT * FROM maquinas WHERE id = ?');
$stmtM->execute([$maquinaId]);
$maquina = $stmtM->fetch();

if (!$maquina) {
    flash_set('erro', 'Máquina não encontrada.');
    redirect(base_url('admin/maquinas.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        flash_set('erro', 'Requisição inválida.');
        redirect(base_url('admin/maquina_operadores.php?maquina_id=' . $maquinaId));
    }

    $acao = $_POST['acao'] ?? '';
    if ($acao === 'vincular') {
        $operadorId = (int)($_POST['user_id'] ?? 0);
        $turno = $_POST['turno'] ?? '';
        $turno = in_array($turno, ['manha', 'tarde', 'noite'], true) ? $turno : null;

        $stmtDup = db()->prepare('SELECT id FROM maquina_operadores WHERE maquina_id = ? AND user_id = ?');
        $stmtDup->execute([$maquinaId, $operadorId]);
        if ($operadorId && !$stmtDup->fetch()) {
            db()->prepare('INSERT INTO maquina_operadores (maquina_id, user_id, turno) VALUES (?, ?, ?)')
                ->execute([$maquinaId, $operadorId, $turno]);
            $stmtOp = db()->prepare('SELECT nome FROM usuarios WHERE id = ?');
            $stmtOp->execute([$operadorId]);
            $nomeOperador = $stmtOp->fetchColumn() ?: "ID $operadorId";
            log_auditoria('vincular_operador', 'maquina', $maquinaId, "Vinculou \"$nomeOperador\" à máquina \"{$maquina['codigo']} — {$maquina['nome']}\"");
            flash_set('sucesso', 'Operador vinculado com sucesso.');
        } else {
            flash_set('erro', 'Selecione um operador válido (ele já pode estar vinculado a essa máquina).');
        }
    } elseif ($acao === 'desvincular') {
        $vinculoId = (int)($_POST['vinculo_id'] ?? 0);
        $stmtV = db()->prepare('SELECT u.nome FROM maquina_operadores mo JOIN usuarios u ON u.id = mo.user_id WHERE mo.id = ? AND mo.maquina_id = ?');
        $stmtV->execute([$vinculoId, $maquinaId]);
        $nomeOperador = $stmtV->fetchColumn() ?: "vínculo $vinculoId";
        db()->prepare('DELETE FROM maquina_operadores WHERE id = ? AND maquina_id = ?')->execute([$vinculoId, $maquinaId]);
        log_auditoria('desvincular_operador', 'maquina', $maquinaId, "Desvinculou \"$nomeOperador\" da máquina \"{$maquina['codigo']} — {$maquina['nome']}\"");
        flash_set('sucesso', 'Operador desvinculado.');
    }
    redirect(base_url('admin/maquina_operadores.php?maquina_id=' . $maquinaId));
}

$stmtV = db()->prepare("
    SELECT mo.id, mo.turno, u.id AS user_id, u.nome, u.matricula
    FROM maquina_operadores mo
    JOIN usuarios u ON u.id = mo.user_id
    WHERE mo.maquina_id = ?
    ORDER BY u.nome
");
$stmtV->execute([$maquinaId]);
$vinculados = $stmtV->fetchAll();

$stmtDisp = db()->prepare("
    SELECT id, nome, matricula FROM usuarios
    WHERE role = 'operador' AND ativo = 1
    AND id NOT IN (SELECT user_id FROM maquina_operadores WHERE maquina_id = ?)
    ORDER BY nome
");
$stmtDisp->execute([$maquinaId]);
$operadoresDisponiveis = $stmtDisp->fetchAll();

$pageTitle = 'Operadores da Máquina';
require __DIR__ . '/../includes/header.php';
?>
<h1>Operadores — <?= e($maquina['codigo']) ?> — <?= e($maquina['nome']) ?></h1>
<p class="hint">Isso é só um registro informativo (quem costuma operar essa máquina) — não é mais
obrigatório para o operador preencher o checklist. Qualquer operador pode selecionar
qualquer máquina ativa na hora de fazer o checklist, dada a alta rotatividade da equipe.</p>

<form method="post" class="form-card">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="acao" value="vincular">
  <label>Operador
    <select name="user_id" required>
      <option value="">Selecione...</option>
      <?php foreach ($operadoresDisponiveis as $o): ?>
        <option value="<?= $o['id'] ?>"><?= e($o['nome']) ?> (<?= e($o['matricula']) ?>)</option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Turno responsável
    <select name="turno">
      <option value="">Todos os turnos</option>
      <option value="manha">Manhã</option>
      <option value="tarde">Tarde</option>
      <option value="noite">Noite</option>
    </select>
  </label>
  <button type="submit" class="btn btn-primary">Vincular operador</button>
</form>

<div class="table-wrap">
<table class="table">
  <thead><tr><th>Operador</th><th>Matrícula</th><th>Turno</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($vinculados as $v): ?>
    <tr>
      <td><?= e($v['nome']) ?></td>
      <td><?= e($v['matricula']) ?></td>
      <td><?= $v['turno'] ? e(turno_label($v['turno'])) : 'Todos' ?></td>
      <td>
        <form method="post" class="inline-form" data-confirm="Desvincular este operador da máquina?">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="acao" value="desvincular">
          <input type="hidden" name="vinculo_id" value="<?= (int)$v['id'] ?>">
          <button type="submit" class="link-button">Desvincular</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$vinculados): ?><tr><td colspan="4">Nenhum operador vinculado.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<a href="<?= base_url('admin/maquinas.php') ?>" class="btn btn-secondary">Voltar</a>
<?php require __DIR__ . '/../includes/footer.php'; ?>
