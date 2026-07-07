<?php
session_start();
require_once '../../config/db_connection.php';
date_default_timezone_set('Europe/Lisbon');
require_once '../../includes/activity_logger.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$employeeId = isset($_SESSION['employee_id']) ? (int) $_SESSION['employee_id'] : 0;
$clientId = isset($_SESSION['client_id']) ? (int) $_SESSION['client_id'] : 0;

if ($employeeId <= 0 || $clientId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Sessão expirada. Faça login novamente.']);
    exit;
}

$valor = isset($_POST['valor']) ? str_replace(',', '.', (string) $_POST['valor']) : '';
$turno = trim((string) ($_POST['turno'] ?? ''));
$formaPagamento = trim((string) ($_POST['forma_pagamento'] ?? ''));
$origem = trim((string) ($_POST['origem'] ?? ''));
$observacoes = trim((string) ($_POST['observacoes'] ?? ''));

// Data da gorjeta: valida e limita até hoje e 90 dias atrás
$dataGorjetaRaw = trim((string)($_POST['data_gorjeta'] ?? ''));
$todayDate = date('Y-m-d');
$minDate   = date('Y-m-d', strtotime('-90 days'));
if ($dataGorjetaRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataGorjetaRaw)
    && $dataGorjetaRaw <= $todayDate && $dataGorjetaRaw >= $minDate) {
    $gorjetaDate = $dataGorjetaRaw;
} else {
    $gorjetaDate = $todayDate;
}
$isToday = ($gorjetaDate === $todayDate);

function _gorjetaParseDiasSemana(string $s): array
{
    $map = [
        'dom' => 0, 'sun' => 0, 'domingo' => 0,
        'seg' => 1, 'mon' => 1, 'segunda' => 1,
        'ter' => 2, 'tue' => 2, 'terca' => 2, 'terça' => 2,
        'qua' => 3, 'wed' => 3, 'quarta' => 3,
        'qui' => 4, 'thu' => 4, 'quinta' => 4,
        'sex' => 5, 'fri' => 5, 'sexta' => 5,
        'sab' => 6, 'sat' => 6, 'sabado' => 6, 'sábado' => 6,
    ];
    $out = [];
    foreach (preg_split('/[,;\s\/|]+/', mb_strtolower($s)) as $p) {
        $p = trim(preg_replace('/[-_].*/', '', $p));
        if (isset($map[$p])) $out[] = $map[$p];
        elseif (is_numeric($p) && $p >= 0 && $p <= 6) $out[] = (int)$p;
    }
    return array_unique($out);
}

function _funcionarioEstaEmTurno(PDO $pdo, int $employeeId, string $dateIso): bool
{
    $stmt = $pdo->prepare(
        "SELECT dias_semana, data_inicio, data_fim FROM turnos
         WHERE funcionario_id = ? AND LOWER(COALESCE(status, '')) IN ('ativo', 'active')"
    );
    $stmt->execute([$employeeId]);
    $weekday = (int)date('w', strtotime($dateIso));

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $turno) {
        $diasRaw = trim((string)($turno['dias_semana'] ?? ''));
        $dias = $diasRaw !== '' ? _gorjetaParseDiasSemana($diasRaw) : [];
        $diaCorreto = empty($dias) || in_array($weekday, $dias, true);

        $inicioVigencia = trim((string)($turno['data_inicio'] ?? ''));
        $fimVigencia = trim((string)($turno['data_fim'] ?? ''));
        $dentroVigencia = ($inicioVigencia === '' || $inicioVigencia === '0000-00-00' || $inicioVigencia <= $dateIso)
            && ($fimVigencia === '' || $fimVigencia === '0000-00-00' || $fimVigencia >= $dateIso);

        if ($diaCorreto && $dentroVigencia) {
            return true;
        }
    }
    return false;
}

if ($valor === '' || !is_numeric($valor)) {
    echo json_encode(['success' => false, 'message' => 'Informe um valor válido']);
    exit;
}

$valorFloat = (float) $valor;
if ($valorFloat <= 0) {
    echo json_encode(['success' => false, 'message' => 'O valor deve ser maior que zero']);
    exit;
}

if ($turno === '') {
    echo json_encode(['success' => false, 'message' => 'Selecione o turno']);
    exit;
}

if ($formaPagamento === '') {
    echo json_encode(['success' => false, 'message' => 'Selecione a forma de pagamento']);
    exit;
}

try {
    $stmtEmployeeStatus = $pdo->prepare("SELECT status FROM employees WHERE id = ? AND client_id = ? LIMIT 1");
    $stmtEmployeeStatus->execute([$employeeId, $clientId]);
    $employeeStatusRow = $stmtEmployeeStatus->fetch(PDO::FETCH_ASSOC);
    $employeeStatus = mb_strtolower(trim((string)($employeeStatusRow['status'] ?? '')));
    if (in_array($employeeStatus, ['ferias', 'férias'], true)) {
        echo json_encode(['success' => false, 'message' => 'Funcionário em férias não pode registrar gorjetas.']);
        exit;
    }

    if (!_funcionarioEstaEmTurno($pdo, $employeeId, $gorjetaDate)) {
        echo json_encode(['success' => false, 'message' => 'Funcionário fora de turno não pode registrar gorjetas.']);
        exit;
    }

    // Verificação de presença apenas para registo do dia atual
    if ($isToday) {
        $stmtPresencaHoje = $pdo->prepare("SELECT status FROM presencas WHERE funcionario_id = ? AND DATE(data_registro) = CURDATE() ORDER BY id DESC LIMIT 1");
        $stmtPresencaHoje->execute([$employeeId]);
        $presencaHoje = $stmtPresencaHoje->fetch(PDO::FETCH_ASSOC);
        $presencaStatus = mb_strtolower(trim((string)($presencaHoje['status'] ?? '')));
        if (!in_array($presencaStatus, ['presente', 'atrasado'], true)) {
            echo json_encode(['success' => false, 'message' => 'Só é possível registrar gorjeta quando o funcionário estiver presente.']);
            exit;
        }
    }

    $columns = [];
    $columnStmt = $pdo->query("SHOW COLUMNS FROM gorjetas");
    $columns = $columnStmt ? $columnStmt->fetchAll(PDO::FETCH_COLUMN) : [];
    $columns = array_map('strtolower', $columns);

    // Resolver turno_id a partir do turno_tipo enviado pelo formulário
    $resolvedTurnoId = null;
    try {
        $stmtTurnoId = $pdo->prepare("SELECT id FROM turnos WHERE LOWER(turno_tipo) = LOWER(?) AND funcionario_id = ? LIMIT 1");
        $stmtTurnoId->execute([$turno, $employeeId]);
        $turnoRow = $stmtTurnoId->fetch(PDO::FETCH_ASSOC);
        $resolvedTurnoId = $turnoRow ? (int)$turnoRow['id'] : null;
    } catch (Exception $e) {
        // tabela ou coluna não existe — continua com NULL
    }

    // Protecção contra duplicados: mesma gorjeta nos últimos 30 segundos
    if (in_array('created_at', $columns, true)) {
        $stmtDup = $pdo->prepare(
            "SELECT id FROM gorjetas
             WHERE funcionario_id = ? AND client_id = ? AND valor = ? AND forma_pagamento = ?
             AND created_at >= DATE_SUB(NOW(), INTERVAL 30 SECOND) LIMIT 1"
        );
        $stmtDup->execute([$employeeId, $clientId, $valorFloat, $formaPagamento]);
        if ($stmtDup->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Gorjeta duplicada. Aguarde 30 segundos antes de registar novamente.']);
            exit;
        }
    }

    $fieldNames = ['funcionario_id', 'client_id', 'valor'];
    $placeholders = ['?', '?', '?'];
    $values = [$employeeId, $clientId, $valorFloat];

    $nowDateTime = date('Y-m-d H:i:s');

    if (in_array('data_registro', $columns, true)) {
        $fieldNames[] = 'data_registro';
        $placeholders[] = '?';
        $values[] = $gorjetaDate;
    } elseif (in_array('data', $columns, true)) {
        $fieldNames[] = 'data';
        $placeholders[] = '?';
        $values[] = $gorjetaDate;
    }

    if (in_array('turno', $columns, true)) {
        $fieldNames[] = 'turno';
        $placeholders[] = '?';
        $values[] = $turno;
    }

    if (in_array('turno_id', $columns, true)) {
        $fieldNames[] = 'turno_id';
        $placeholders[] = '?';
        $values[] = $resolvedTurnoId;
    }

    if (in_array('forma_pagamento', $columns, true)) {
        $fieldNames[] = 'forma_pagamento';
        $placeholders[] = '?';
        $values[] = $formaPagamento;
    }

    if (in_array('origem', $columns, true)) {
        $fieldNames[] = 'origem';
        $placeholders[] = '?';
        $values[] = $origem;
    }

    if (in_array('observacoes', $columns, true)) {
        $fieldNames[] = 'observacoes';
        $placeholders[] = '?';
        $values[] = $observacoes;
    } elseif (in_array('observacao', $columns, true)) {
        $fieldNames[] = 'observacao';
        $placeholders[] = '?';
        $values[] = $observacoes;
    }

    if (in_array('status', $columns, true)) {
        $fieldNames[] = 'status';
        $placeholders[] = '?';
        $values[] = 'pendente';
    }

    if (in_array('created_at', $columns, true)) {
        $fieldNames[] = 'created_at';
        $placeholders[] = '?';
        $values[] = $nowDateTime;
    }

    if (in_array('updated_at', $columns, true)) {
        $fieldNames[] = 'updated_at';
        $placeholders[] = '?';
        $values[] = $nowDateTime;
    }

    $sql = 'INSERT INTO gorjetas (' . implode(', ', $fieldNames) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);

    $gorjetaId = (int) $pdo->lastInsertId();

    $titulo = sprintf('Gorjeta registrada: €%s por %s', number_format($valorFloat, 2, ',', '.'), $_SESSION['employee_name'] ?? 'Funcionário');
    logActivity(
        $pdo,
        $clientId,
        $titulo,
        'info',
        'Gorjeta',
        $employeeId
    );

    echo json_encode(['success' => true, 'id' => $gorjetaId]);
} catch (Exception $e) {
    error_log('add_gorjeta_employee erro: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro no servidor']);
}
