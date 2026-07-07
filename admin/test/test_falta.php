<?php
// =====================================================================
// PÁGINA DE TESTE — Marcar Falta para Funcionário
// Uso exclusivo em ambiente de desenvolvimento/testes
// =====================================================================
session_start();
require_once '../config/db_connection.php';
date_default_timezone_set('Europe/Lisbon');

if (empty($_SESSION['user_id']) || empty($_SESSION['client_id'])) {
    header('Location: login.php');
    exit;
}

$clientId = (int)$_SESSION['client_id'];
$message   = null;
$msgType   = 'success';

// ── Garantir que as tabelas existem ──────────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS presencas (
        id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        funcionario_id INT NOT NULL,
        client_id      INT NOT NULL,
        status         ENUM('presente','falta','atraso') NOT NULL DEFAULT 'falta',
        data_registro  DATE NOT NULL,
        created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_presenca_emp_data (funcionario_id, data_registro)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Adicionar client_id se não existir
    $colCheck = $pdo->query("SHOW COLUMNS FROM presencas LIKE 'client_id'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE presencas ADD COLUMN client_id INT NOT NULL DEFAULT 0 AFTER funcionario_id");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS justificativas_presenca (
        id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        client_id       INT NOT NULL,
        employee_id     INT NOT NULL,
        data_ocorrencia DATE NOT NULL,
        motivo          TEXT NOT NULL,
        anexo_path      VARCHAR(255) NULL,
        status          ENUM('pendente','aprovada','rejeitada') NOT NULL DEFAULT 'pendente',
        created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_jus_client_status   (client_id, status),
        KEY idx_jus_employee_data   (employee_id, data_ocorrencia)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Adicionar colunas opcionais individualmente (ignora erro se já existem)
    $jus_migrations = [
        "ALTER TABLE justificativas_presenca ADD COLUMN tipo ENUM('falta','atraso') NOT NULL DEFAULT 'falta' AFTER data_ocorrencia",
        "ALTER TABLE justificativas_presenca ADD COLUMN admin_observacao TEXT NULL AFTER status",
        "ALTER TABLE justificativas_presenca ADD COLUMN decidido_por INT NULL AFTER admin_observacao",
        "ALTER TABLE justificativas_presenca ADD COLUMN decidido_em DATETIME NULL AFTER decidido_por",
    ];
    foreach ($jus_migrations as $sql) {
        try { $pdo->exec($sql); } catch (Throwable $ignored) { /* coluna já existe */ }
    }

    // registros_ponto — garantir colunas tipo_dia e falta_tipo
    $hasTipoDia   = (bool)$pdo->query("SHOW COLUMNS FROM registros_ponto LIKE 'tipo_dia'"  )->fetch();
    $hasFaltaTipo = (bool)$pdo->query("SHOW COLUMNS FROM registros_ponto LIKE 'falta_tipo'")->fetch();
    if (!$hasTipoDia)   $pdo->exec("ALTER TABLE registros_ponto ADD COLUMN tipo_dia   VARCHAR(30) NULL AFTER status");
    if (!$hasFaltaTipo) $pdo->exec("ALTER TABLE registros_ponto ADD COLUMN falta_tipo VARCHAR(20) NULL AFTER tipo_dia");

} catch (Throwable $e) {
    error_log('test_falta migration: ' . $e->getMessage());
}

// ── Processar POST ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action      = $_POST['action'] ?? '';
    $employeeId  = (int)($_POST['employee_id']    ?? 0);
    $dataFalta   = trim($_POST['data_falta'] ?? date('Y-m-d'));

    // Validações básicas
    if ($employeeId <= 0 || $dataFalta === '' || strtotime($dataFalta) === false) {
        $message = 'Seleccione um funcionário e uma data válida.';
        $msgType = 'error';

    } elseif ($action === 'marcar_falta') {
        // Confirmar que o funcionário pertence ao cliente
        $stmtEmp = $pdo->prepare("SELECT id, name FROM employees WHERE id = ? AND client_id = ? LIMIT 1");
        $stmtEmp->execute([$employeeId, $clientId]);
        $emp = $stmtEmp->fetch(PDO::FETCH_ASSOC);

        if (!$emp) {
            $message = 'Funcionário não encontrado.';
            $msgType = 'error';
        } else {
            try {
                $pdo->beginTransaction();

                // 1) presencas — INSERT OR UPDATE
                $stmtP = $pdo->prepare("
                    INSERT INTO presencas (funcionario_id, client_id, status, data_registro)
                    VALUES (?, ?, 'falta', ?)
                    ON DUPLICATE KEY UPDATE status = 'falta', client_id = ?, updated_at = NOW()
                ");
                $stmtP->execute([$employeeId, $clientId, $dataFalta, $clientId]);

                // 2) registros_ponto — se já existe registo no dia, atualiza; senão insere
                $stmtRp = $pdo->prepare("
                    SELECT id FROM registros_ponto
                    WHERE funcionario_id = ? AND DATE(data_registro) = ?
                    LIMIT 1
                ");
                $stmtRp->execute([$employeeId, $dataFalta]);
                $rpRow = $stmtRp->fetch(PDO::FETCH_ASSOC);

                if ($rpRow) {
                    $stmtUpRp = $pdo->prepare("
                        UPDATE registros_ponto
                        SET status = 'falta', tipo_dia = 'falta', falta_tipo = 'injustificada',
                            hora_entrada = NULL, hora_saida = NULL,
                            status_confirmacao = 'pendente'
                        WHERE id = ?
                    ");
                    $stmtUpRp->execute([$rpRow['id']]);
                } else {
                    // Determinar a coluna de data correcta
                    $hasDataReg = (bool)$pdo->query("SHOW COLUMNS FROM registros_ponto LIKE 'data_registro'")->fetch();
                    $dataCol    = $hasDataReg ? 'data_registro' : 'data';

                    $stmtInRp = $pdo->prepare("
                        INSERT INTO registros_ponto
                            (funcionario_id, $dataCol, status, tipo_dia, falta_tipo, status_confirmacao)
                        VALUES (?, ?, 'falta', 'falta', 'injustificada', 'pendente')
                    ");
                    $stmtInRp->execute([$employeeId, $dataFalta]);
                }

                $pdo->commit();

                $message = "✅ Falta registada para <strong>" . htmlspecialchars($emp['name']) . "</strong> no dia <strong>" . htmlspecialchars($dataFalta) . "</strong>. O funcionário já pode submeter a justificação no portal.";
                $msgType = 'success';

            } catch (Throwable $e) {
                $pdo->rollBack();
                $message = 'Erro ao registar falta: ' . htmlspecialchars($e->getMessage());
                $msgType = 'error';
            }
        }

    } elseif ($action === 'remover_falta') {
        // Remover a falta (para recomeçar o teste)
        try {
            $stmtDelP = $pdo->prepare("DELETE FROM presencas WHERE funcionario_id = ? AND data_registro = ? AND client_id = ?");
            $stmtDelP->execute([$employeeId, $dataFalta, $clientId]);

            $stmtDelRp = $pdo->prepare("DELETE FROM registros_ponto WHERE funcionario_id = ? AND DATE(data_registro) = ?");
            $stmtDelRp->execute([$employeeId, $dataFalta]);

            $stmtDelJ = $pdo->prepare("DELETE FROM justificativas_presenca WHERE employee_id = ? AND data_ocorrencia = ? AND client_id = ?");
            $stmtDelJ->execute([$employeeId, $dataFalta, $clientId]);

            $message = "🗑️ Falta e justificativas removidas com sucesso (dados de teste limpos).";
            $msgType = 'info';
        } catch (Throwable $e) {
            $message = 'Erro ao remover: ' . htmlspecialchars($e->getMessage());
            $msgType = 'error';
        }
    }
}

// ── Carregar dados para a view ────────────────────────────────────────

// Lista de funcionários activos
$stmtEmps = $pdo->prepare("SELECT id, name, position FROM employees WHERE client_id = ? AND status = 'active' ORDER BY name");
$stmtEmps->execute([$clientId]);
$employees = $stmtEmps->fetchAll(PDO::FETCH_ASSOC);

// Faltas registadas nos últimos 30 dias
$stmtFaltas = $pdo->prepare("
    SELECT p.id, p.funcionario_id, e.name AS nome, p.data_registro, p.status,
           j.id AS jus_id, j.motivo, j.tipo AS jus_tipo, j.status AS jus_status,
           j.created_at AS jus_criada
    FROM presencas p
    JOIN employees e ON e.id = p.funcionario_id
    LEFT JOIN justificativas_presenca j
           ON j.employee_id = p.funcionario_id
          AND j.data_ocorrencia = p.data_registro
          AND j.client_id = p.client_id
    WHERE p.client_id = ?
      AND p.status = 'falta'
      AND p.data_registro >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY p.data_registro DESC, e.name
");
$stmtFaltas->execute([$clientId]);
$faltas = $stmtFaltas->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Teste — Marcar Falta | RHNeto Pro</title>
<link rel="stylesheet" href="../assets/css/login.css">
<style>
  :root {
    --color-primary: #4f46e5;
    --color-danger:  #ef4444;
    --color-success: #22c55e;
    --color-info:    #3b82f6;
    --color-warn:    #f59e0b;
    --color-bg:      #f8fafc;
    --color-card:    #ffffff;
    --color-border:  #e2e8f0;
    --color-text:    #1e293b;
    --color-muted:   #64748b;
    --radius:        10px;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', sans-serif; background: var(--color-bg); color: var(--color-text); min-height: 100vh; padding: 24px 16px; }
  a { color: var(--color-primary); text-decoration: none; }
  a:hover { text-decoration: underline; }

  .page-header { max-width: 900px; margin: 0 auto 24px; display: flex; align-items: center; gap: 14px; }
  .page-header .badge-test { background: #fef3c7; color: #92400e; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 99px; border: 1px solid #fbbf24; text-transform: uppercase; letter-spacing:.5px; }
  .page-header h1 { font-size: 22px; font-weight: 700; }
  .page-header p  { font-size: 13px; color: var(--color-muted); margin-top: 2px; }
  .back-link { margin-left: auto; font-size: 13px; }

  .card { background: var(--color-card); border: 1px solid var(--color-border); border-radius: var(--radius); padding: 24px; max-width: 900px; margin: 0 auto 24px; box-shadow: 0 1px 4px rgba(0,0,0,.05); }
  .card h2 { font-size: 16px; font-weight: 600; margin-bottom: 18px; padding-bottom: 10px; border-bottom: 1px solid var(--color-border); }

  .form-row { display: flex; gap: 14px; flex-wrap: wrap; align-items: flex-end; }
  .form-group { display: flex; flex-direction: column; gap: 5px; flex: 1; min-width: 180px; }
  .form-group label { font-size: 12px; font-weight: 600; color: var(--color-muted); text-transform: uppercase; letter-spacing: .4px; }
  .form-group select,
  .form-group input[type=date] { padding: 9px 12px; border: 1px solid var(--color-border); border-radius: 7px; font-size: 14px; width: 100%; background: #fff; color: var(--color-text); }
  .form-group select:focus,
  .form-group input:focus { outline: none; border-color: var(--color-primary); box-shadow: 0 0 0 3px rgba(79,70,229,.12); }

  .btn { padding: 9px 20px; border: none; border-radius: 7px; font-size: 14px; font-weight: 600; cursor: pointer; transition: opacity .15s; }
  .btn:hover { opacity: .87; }
  .btn-primary { background: var(--color-primary); color: #fff; }
  .btn-danger  { background: var(--color-danger);  color: #fff; }

  .alert { padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; line-height: 1.5; }
  .alert-success { background: #f0fdf4; border: 1px solid #86efac; color: #166534; }
  .alert-error   { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; }
  .alert-info    { background: #eff6ff; border: 1px solid #93c5fd; color: #1e40af; }

  .table-wrap { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  th { background: #f1f5f9; text-align: left; padding: 10px 12px; font-weight: 600; color: var(--color-muted); text-transform: uppercase; font-size: 11px; letter-spacing:.4px; border-bottom: 1px solid var(--color-border); }
  td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
  tr:last-child td { border-bottom: none; }
  tr:hover td { background: #fafbfc; }

  .badge { display: inline-block; padding: 2px 10px; border-radius: 99px; font-size: 11px; font-weight: 600; }
  .badge-falta    { background: #fee2e2; color: #991b1b; }
  .badge-pendente { background: #fef3c7; color: #92400e; }
  .badge-aprovada { background: #dcfce7; color: #166534; }
  .badge-rejeitada{ background: #f3e8ff; color: #6b21a8; }
  .badge-none     { background: #f1f5f9; color: #64748b; }

  .mini-form { display: inline-flex; gap: 6px; }
  .mini-form input { display: none; }

  .info-box { background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 14px 16px; font-size: 13px; color: #78350f; line-height: 1.7; margin-bottom: 20px; }
  .info-box strong { font-weight: 700; }
  .info-box ol { margin: 8px 0 0 18px; }

  .empty-state { text-align: center; padding: 30px 0; color: var(--color-muted); font-size: 13px; }
</style>
</head>
<body>

<div class="page-header">
  <div>
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;">
      <span class="badge-test">⚙ Ambiente de Teste</span>
    </div>
    <h1>Marcar Falta para Funcionário</h1>
    <p>Use esta página para registar faltas de teste e verificar o fluxo de justificações.</p>
  </div>
  <a href="dashboard.php" class="back-link">← Voltar ao Dashboard</a>
</div>

<div class="card">
  <div class="info-box">
    <strong>Como funciona o fluxo de teste:</strong>
    <ol>
      <li>Seleccione um funcionário e a data da falta e clique em <strong>Marcar Falta</strong>.</li>
      <li>Aceda ao portal do funcionário (<a href="../employee/" target="_blank">employee/</a>), faça login com o PIN do funcionário.</li>
      <li>No portal, envie uma justificação de falta para a data registada.</li>
      <li>Volte ao Dashboard do admin e aprove ou rejeite a justificação.</li>
      <li>Use o botão <strong>Remover Falta</strong> na tabela abaixo para limpar os dados de teste.</li>
    </ol>
  </div>

  <?php if ($message): ?>
  <div class="alert alert-<?= $msgType ?>"><?= $message ?></div>
  <?php endif; ?>

  <h2>Registar nova falta</h2>

  <?php if (empty($employees)): ?>
    <p style="color:var(--color-muted);font-size:14px;">Não existem funcionários activos neste cliente. <a href="dashboard.php">Adicione funcionários</a> primeiro.</p>
  <?php else: ?>
  <form method="POST">
    <input type="hidden" name="action" value="marcar_falta">
    <div class="form-row">
      <div class="form-group">
        <label for="employee_id">Funcionário</label>
        <select name="employee_id" id="employee_id" required>
          <option value="">— Seleccione —</option>
          <?php foreach ($employees as $e): ?>
          <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['name']) ?><?= $e['position'] ? ' — ' . htmlspecialchars($e['position']) : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="max-width:180px;">
        <label for="data_falta">Data da falta</label>
        <input type="date" name="data_falta" id="data_falta" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
      </div>
      <div class="form-group" style="flex:0;">
        <label>&nbsp;</label>
        <button type="submit" class="btn btn-primary">Marcar Falta</button>
      </div>
    </div>
  </form>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Faltas registadas nos últimos 30 dias</h2>
  <?php if (empty($faltas)): ?>
    <div class="empty-state">Nenhuma falta registada nos últimos 30 dias.</div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Funcionário</th>
          <th>Data</th>
          <th>Falta</th>
          <th>Justificação</th>
          <th>Estado Just.</th>
          <th>Acções</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($faltas as $row): ?>
        <tr>
          <td><strong><?= htmlspecialchars($row['nome']) ?></strong></td>
          <td><?= htmlspecialchars($row['data_registro']) ?></td>
          <td><span class="badge badge-falta">Falta</span></td>
          <td>
            <?php if ($row['jus_id']): ?>
              <span style="font-size:12px;color:var(--color-text);"><?= htmlspecialchars(mb_substr($row['motivo'], 0, 50)) . (mb_strlen($row['motivo']) > 50 ? '…' : '') ?></span>
              <br><span style="font-size:11px;color:var(--color-muted);">Enviada em <?= htmlspecialchars(substr($row['jus_criada'], 0, 16)) ?></span>
            <?php else: ?>
              <span style="color:var(--color-muted);font-size:12px;">Sem justificação enviada</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($row['jus_id']): ?>
              <span class="badge badge-<?= htmlspecialchars($row['jus_status']) ?>"><?= ucfirst(htmlspecialchars($row['jus_status'])) ?></span>
            <?php else: ?>
              <span class="badge badge-none">—</span>
            <?php endif; ?>
          </td>
          <td>
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
              <?php if ($row['jus_id'] && $row['jus_status'] === 'pendente'): ?>
              <form method="POST" action="../api/employees/review_justificativa.php">
                <input type="hidden" name="justificativa_id" value="<?= (int)$row['jus_id'] ?>">
                <input type="hidden" name="decision" value="aprovar">
                <input type="hidden" name="return_url" value="test_falta.php">
                <button type="submit" class="btn btn-primary" style="font-size:12px;padding:5px 12px;">✔ Aprovar</button>
              </form>
              <form method="POST" action="../api/employees/review_justificativa.php">
                <input type="hidden" name="justificativa_id" value="<?= (int)$row['jus_id'] ?>">
                <input type="hidden" name="decision" value="rejeitar">
                <input type="hidden" name="return_url" value="test_falta.php">
                <button type="submit" class="btn" style="font-size:12px;padding:5px 12px;background:#f59e0b;color:#fff;">✖ Rejeitar</button>
              </form>
              <?php endif; ?>
              <form method="POST" onsubmit="return confirm('Remover esta falta e todas as justificativas associadas?')">
                <input type="hidden" name="action" value="remover_falta">
                <input type="hidden" name="employee_id" value="<?= (int)$row['funcionario_id'] ?>">
                <input type="hidden" name="data_falta"  value="<?= htmlspecialchars($row['data_registro']) ?>">
                <button type="submit" class="btn btn-danger" style="font-size:12px;padding:5px 12px;">🗑 Remover</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

</body>
</html>
