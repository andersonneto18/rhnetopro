<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Use este script em CLI: php tools/smoke_test_full_flow.php\n");
    exit(1);
}

require_once __DIR__ . '/../config/db_connection.php';
date_default_timezone_set('Europe/Lisbon');

if (!isset($pdo) || !($pdo instanceof PDO)) {
    fwrite(STDERR, "Falha ao iniciar conexao PDO.\n");
    exit(1);
}

$opts = getopt('', [
    'client-id::',
    'year::',
    'month::',
    'scenario::',
    'employee-name::',
    'turno::',
    'gorjeta-pendente::',
    'gorjeta-paga::',
    'dry-run',
    'cleanup-old'
]);

$year = isset($opts['year']) ? (int)$opts['year'] : (int)date('Y');
$month = isset($opts['month']) ? (int)$opts['month'] : (int)date('n');
$scenario = strtolower(trim((string)($opts['scenario'] ?? 'presente')));
$employeeNameInput = trim((string)($opts['employee-name'] ?? ''));
$turnoLabel = trim((string)($opts['turno'] ?? 'Manha'));
$gorjetaPendenteValor = isset($opts['gorjeta-pendente']) ? (float)$opts['gorjeta-pendente'] : 45.50;
$gorjetaPagaValor = isset($opts['gorjeta-paga']) ? (float)$opts['gorjeta-paga'] : 120.00;
$dryRun = array_key_exists('dry-run', $opts);
$cleanupOld = array_key_exists('cleanup-old', $opts);

if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
    fwrite(STDERR, "Ano/mes invalido. Exemplo: --year=2026 --month=5\n");
    exit(1);
}

if (!in_array($scenario, ['presente', 'falta'], true)) {
    fwrite(STDERR, "Scenario invalido. Use --scenario=presente ou --scenario=falta\n");
    exit(1);
}

if ($turnoLabel === '') {
    $turnoLabel = 'Manha';
}

if ($gorjetaPendenteValor < 0 || $gorjetaPagaValor < 0) {
    fwrite(STDERR, "Valores de gorjeta invalidos. Use apenas numeros >= 0.\n");
    exit(1);
}

function out(string $line): void
{
    fwrite(STDOUT, $line . PHP_EOL);
}

function fail(string $line): void
{
    fwrite(STDERR, "ERRO: " . $line . PHP_EOL);
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function resolveClientId(PDO $pdo, ?int $cliClientId): int
{
    if ($cliClientId !== null && $cliClientId > 0) {
        return $cliClientId;
    }

    $stmt = $pdo->query('SELECT MAX(client_id) AS client_id FROM usuarios');
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    $clientId = (int)($row['client_id'] ?? 0);

    if ($clientId <= 0) {
        throw new RuntimeException('Nao foi possivel determinar client_id automaticamente. Use --client-id=N.');
    }

    return $clientId;
}

function cleanupOldQaData(PDO $pdo, int $clientId): int
{
    $stmt = $pdo->prepare("SELECT id FROM employees WHERE client_id = ? AND name LIKE 'QA_FT_%'");
    $stmt->execute([$clientId]);
    $employeeIds = array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id'));

    if (empty($employeeIds)) {
        return 0;
    }

    $in = implode(',', array_fill(0, count($employeeIds), '?'));

    $deleteSpecs = [
        ['table' => 'turnos', 'column' => 'funcionario_id'],
        ['table' => 'registros_ponto', 'column' => 'funcionario_id'],
        ['table' => 'presencas', 'column' => 'funcionario_id'],
        ['table' => 'gorjetas', 'column' => 'funcionario_id'],
        ['table' => 'folha_variaveis_mensais', 'column' => 'employee_id'],
        ['table' => 'folha_pagamento', 'column' => 'employee_id'],
        ['table' => 'employee_documents', 'column' => 'employee_id'],
    ];

    foreach ($deleteSpecs as $spec) {
        if (!tableExists($pdo, $spec['table'])) {
            continue;
        }
        $sql = "DELETE FROM {$spec['table']} WHERE {$spec['column']} IN ($in)";
        $del = $pdo->prepare($sql);
        $del->execute($employeeIds);
    }

    $delEmp = $pdo->prepare("DELETE FROM employees WHERE id IN ($in)");
    $delEmp->execute($employeeIds);

    return count($employeeIds);
}

try {
    $clientId = resolveClientId($pdo, isset($opts['client-id']) ? (int)$opts['client-id'] : null);

    $requiredTables = [
        'employees',
        'turnos',
        'presencas',
        'registros_ponto',
        'gorjetas',
        'folha_variaveis_mensais',
        'folha_pagamento',
    ];

    foreach ($requiredTables as $tbl) {
        if (!tableExists($pdo, $tbl)) {
            throw new RuntimeException("Tabela obrigatoria ausente: {$tbl}");
        }
    }

    $stamp = date('Ymd_His') . '_' . random_int(100, 999);
    $employeeName = $employeeNameInput !== ''
        ? $employeeNameInput . '_' . $stamp
        : 'QA_FT_' . $stamp;
    $employeeEmail = 'qa_ft_' . $stamp . '@example.local';
    $employeePhone = '93' . str_pad((string)random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT);
    $employeePin = (string)random_int(1000, 9999);
    $employeePinHash = password_hash($employeePin, PASSWORD_DEFAULT);
    $today = date('Y-m-d');

    out('Iniciando smoke test completo...');
    out("Client ID: {$clientId} | Periodo folha: {$year}-" . str_pad((string)$month, 2, '0', STR_PAD_LEFT));
    out("Scenario: {$scenario}");
    out('Plano de dados:');
    out("- Funcionario: {$employeeName}");
    out("- Turno: {$turnoLabel}");
    out("- Gorjeta pendente: " . number_format($gorjetaPendenteValor, 2, ',', '.'));
    out("- Gorjeta paga: " . number_format($gorjetaPagaValor, 2, ',', '.'));
    out($dryRun ? 'Modo: DRY-RUN (rollback no final)' : 'Modo: COMMIT (dados ficam gravados)');

    $pdo->beginTransaction();

    if ($cleanupOld) {
        $deleted = cleanupOldQaData($pdo, $clientId);
        out("Limpeza de dados QA antigos: {$deleted} funcionario(s) removido(s)");
    }

    $insEmp = $pdo->prepare(
        "INSERT INTO employees
            (name, position, department, email, phone, startDate, status, client_id, pin, pin_hash, salary_base, subsidio_alimentacao, bonus)
         VALUES
            (?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, ?, ?, ?)"
    );
    $insEmp->execute([
        $employeeName,
        'Operador QA',
        'QA',
        $employeeEmail,
        $employeePhone,
        $today,
        $clientId,
        $employeePin,
        $employeePinHash,
        1200.00,
        6.00,
        35.00,
    ]);
    $employeeId = (int)$pdo->lastInsertId();
    out("PASS: funcionario criado (ID {$employeeId})");

    $insTurno = $pdo->prepare(
        "INSERT INTO turnos
            (funcionario_id, turno_tipo, horario_inicio, horario_fim, dias_semana, escala, status, gorjetas_base)
         VALUES
            (?, ?, '09:00:00', '18:00:00', 'Segunda,Terça,Quarta,Quinta,Sexta', 'Fixa', 'ativo', 0.00)"
    );
    $insTurno->execute([$employeeId, $turnoLabel]);
    $turnoId = (int)$pdo->lastInsertId();
    out("PASS: turno criado (ID {$turnoId})");

    $presencaStatus = $scenario === 'falta' ? 'falta' : 'presente';
    $presencaObs = $scenario === 'falta' ? 'Registro automatico QA - falta' : 'Registro automatico QA - presente';
    $insPresenca = $pdo->prepare(
        "INSERT INTO presencas (funcionario_id, client_id, data_registro, status, obs)
         VALUES (?, ?, ?, ?, ?)"
    );
    $insPresenca->execute([$employeeId, $clientId, $today, $presencaStatus, $presencaObs]);
    out("PASS: presenca criada ({$presencaStatus})");

    if ($scenario === 'falta') {
        $insPonto = $pdo->prepare(
            "INSERT INTO registros_ponto
                (funcionario_id, client_id, data_registro, hora_entrada, hora_saida, obs, status_confirmacao, validado_admin, status, tipo_dia, falta_tipo)
             VALUES
                (?, ?, ?, NULL, NULL, 'Falta QA sem entrada/saida', 'confirmado', 1, 'presente', 'falta', 'injustificada')"
        );
        $insPonto->execute([$employeeId, $clientId, $today]);
        out('PASS: registro de ponto de falta criado (sem entrada/saida)');
    } else {
        $insPonto = $pdo->prepare(
            "INSERT INTO registros_ponto
                (funcionario_id, client_id, data_registro, hora_entrada, hora_saida, obs, status_confirmacao, validado_admin, status, tipo_dia)
             VALUES
                (?, ?, ?, '09:02:00', '18:11:00', 'Ponto QA completo', 'confirmado', 1, 'presente', 'normal')"
        );
        $insPonto->execute([$employeeId, $clientId, $today]);
        out('PASS: registro de ponto (entrada/saida) criado');
    }

    $insGorjetaPendente = $pdo->prepare(
        "INSERT INTO gorjetas
            (funcionario_id, client_id, valor, data, turno, forma_pagamento, origem, status, observacao)
         VALUES
            (?, ?, ?, ?, ?, 'Cartao', 'Mesa 12', 'pendente', 'Gorjeta pendente QA')"
    );
    $insGorjetaPendente->execute([$employeeId, $clientId, $gorjetaPendenteValor, $today, $turnoLabel]);
    $gorjetaPendenteId = (int)$pdo->lastInsertId();
    out("PASS: gorjeta pendente criada (ID {$gorjetaPendenteId})");

    $insGorjetaPaga = $pdo->prepare(
        "INSERT INTO gorjetas
            (funcionario_id, client_id, valor, data, turno, forma_pagamento, origem, status, observacao)
         VALUES
            (?, ?, ?, ?, ?, 'Dinheiro', 'Balcao', 'pago', 'Gorjeta paga QA')"
    );
    $insGorjetaPaga->execute([$employeeId, $clientId, $gorjetaPagaValor, $today, $turnoLabel]);
    $gorjetaPagaId = (int)$pdo->lastInsertId();
    out("PASS: gorjeta paga criada (ID {$gorjetaPagaId})");

    $upsertVars = $pdo->prepare(
        "INSERT INTO folha_variaveis_mensais
            (client_id, employee_id, fiscal_year, fiscal_month, horas_extra, faltas_dias, bonus, subsidios_extra, outros_descontos, gorjeta_manual, status, is_locked, updated_by)
         VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, 0.00, 30.00, 'ativo', 0, NULL)
         ON DUPLICATE KEY UPDATE
            horas_extra = VALUES(horas_extra),
                faltas_dias = VALUES(faltas_dias),
            bonus = VALUES(bonus),
            subsidios_extra = VALUES(subsidios_extra),
            gorjeta_manual = VALUES(gorjeta_manual),
            status = VALUES(status),
            is_locked = VALUES(is_locked),
            updated_at = NOW()"
    );
     $horasExtra = $scenario === 'falta' ? 0.00 : 25.00;
     $faltasDias = $scenario === 'falta' ? 1.00 : 0.00;
     $bonusMensal = $scenario === 'falta' ? 20.00 : 40.00;
     $subsidiosExtra = $scenario === 'falta' ? 0.00 : 15.00;
     $upsertVars->execute([$clientId, $employeeId, $year, $month, $horasExtra, $faltasDias, $bonusMensal, $subsidiosExtra]);
    out('PASS: variaveis mensais da folha configuradas');

    $selFolha = $pdo->prepare(
        "SELECT id FROM folha_pagamento
         WHERE client_id = ? AND employee_id = ? AND fiscal_year = ? AND fiscal_month = ?
         LIMIT 1"
    );
    $selFolha->execute([$clientId, $employeeId, $year, $month]);
    $folhaId = (int)($selFolha->fetchColumn() ?: 0);

    if ($folhaId <= 0) {
        $insFolha = $pdo->prepare(
            "INSERT INTO folha_pagamento
                (client_id, employee_id, fiscal_year, fiscal_month, salario_base, subsidio_alimentacao, horas_extra, bonus, gorjetas, salario_bruto, base_seguranca_social, seguranca_social, base_irs, irs, total_descontos, salario_liquido, custo_total_empresa, status_pagamento, status)
             VALUES
                (?, ?, ?, ?, 1200.00, 6.00, 25.00, 75.00, 150.00, 1456.00, 1456.00, 160.16, 1456.00, 218.40, 378.56, 1077.44, 1801.80, 'pendente', 'calculado')"
        );
        $insFolha->execute([$clientId, $employeeId, $year, $month]);
        $folhaId = (int)$pdo->lastInsertId();
        out("PASS: folha_pagamento criada (ID {$folhaId})");
    } else {
        out("PASS: folha_pagamento ja existente (ID {$folhaId})");
    }

    $updPago = $pdo->prepare(
        "UPDATE folha_pagamento
         SET status_pagamento = 'pago', data_pagamento = NOW(), updated_at = NOW()
         WHERE id = ?"
    );
    $updPago->execute([$folhaId]);

    $checkPago = $pdo->prepare("SELECT status_pagamento FROM folha_pagamento WHERE id = ? LIMIT 1");
    $checkPago->execute([$folhaId]);
    $statusPagamento = (string)$checkPago->fetchColumn();
    if (strtolower($statusPagamento) !== 'pago') {
        throw new RuntimeException('Falha ao marcar folha como paga no teste.');
    }
    out('PASS: folha marcada como paga');

    if ($dryRun) {
        $pdo->rollBack();
        out('DRY-RUN concluido com sucesso (rollback executado).');
    } else {
        $pdo->commit();
        out('Smoke test concluido com sucesso e dados gravados.');
        out('Resumo final:');
        out("- Funcionario: {$employeeName} (ID {$employeeId})");
        out("- Turno ID: {$turnoId}");
        out("- Gorjetas IDs: pendente={$gorjetaPendenteId}, paga={$gorjetaPagaId}");
        out("- Folha ID: {$folhaId} (status pago)");
    }

    out('Checklist validado: funcionario, turno, presenca, ponto, gorjetas, folha e pagamento.');
    exit(0);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fail($e->getMessage());
    exit(1);
}
