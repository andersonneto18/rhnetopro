<?php
session_start();
require_once '../../config/db_connection.php';
date_default_timezone_set('Europe/Lisbon');
require_once '../../includes/activity_logger.php';
header('Content-Type: application/json; charset=utf-8');

if (!function_exists('getGorjetaDateColumn')) {
    function getGorjetaDateColumn(PDO $pdo): string
    {
        static $column = null;

        if ($column !== null) {
            return $column;
        }

        try {
            $checkRegistro = $pdo->query("SHOW COLUMNS FROM gorjetas LIKE 'data_registro'");
            if ($checkRegistro && $checkRegistro->fetch()) {
                $column = 'data_registro';
                return $column;
            }

            $checkData = $pdo->query("SHOW COLUMNS FROM gorjetas LIKE 'data'");
            if ($checkData && $checkData->fetch()) {
                $column = 'data';
                return $column;
            }
        } catch (Exception $e) {
            // Ignora, usa padrão abaixo
        }

        $column = 'data_registro';
        return $column;
    }
}

if (!isset($_SESSION['client_id'])) {
    echo json_encode(['success'=>false,'message'=>'Não autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$required = ['funcionario_id','data_registro','valor'];
foreach ($required as $f) {
    if (empty($input[$f]) && $input[$f] !== '0') {
        echo json_encode(['success'=>false,'message'=>"Campo obrigatório ausente: $f"]);
        exit;
    }
}

// verificar funcionário pertence ao client
$stmt = $pdo->prepare("SELECT id, name, status FROM employees WHERE id = ? AND client_id = ?");
$stmt->execute([(int)$input['funcionario_id'], $_SESSION['client_id']]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$employee) {
    echo json_encode(['success'=>false,'message'=>'Funcionário inválido']);
    exit;
}

$employeeStatus = mb_strtolower(trim((string)($employee['status'] ?? '')));
if (in_array($employeeStatus, ['ferias', 'férias'], true)) {
    echo json_encode(['success'=>false,'message'=>'Funcionário em férias não pode registrar gorjetas']);
    exit;
}

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

if (!_funcionarioEstaEmTurno($pdo, (int)$input['funcionario_id'], (string)$input['data_registro'])) {
    echo json_encode(['success'=>false,'message'=>'Funcionário fora de turno não pode registrar gorjetas']);
    exit;
}

try {
    $dateColumn = getGorjetaDateColumn($pdo);
    // Salvar o turno (texto) na coluna turno_id como texto temporariamente
    // ou criar uma migração para adicionar coluna 'turno' TEXT
    $turnoTexto = $input['turno'] ?? '';
    
    $stmt = $pdo->prepare("
        INSERT INTO gorjetas (funcionario_id, {$dateColumn}, valor, forma_pagamento, origem, status, client_id)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $ok = $stmt->execute([
        (int)$input['funcionario_id'],
        $input['data_registro'],
        (float)$input['valor'],
        $input['forma_pagamento'] ?? 'Dinheiro',
        $input['origem'] ?? '',
        $input['status'] ?? 'pago',
        $_SESSION['client_id']
    ]);
    
    // Se sucesso, tentar adicionar o turno em um campo separado se existir
    if ($ok && !empty($turnoTexto)) {
        $gorjetaId = $pdo->lastInsertId();
        // Verificar se coluna turno existe
        try {
            $stmt2 = $pdo->prepare("UPDATE gorjetas SET turno = ? WHERE id = ?");
            $stmt2->execute([$turnoTexto, $gorjetaId]);
        } catch (Exception $e2) {
            // Coluna turno não existe, ignorar
        }
    }
    
    if ($ok) {
        $valorFormatado = number_format((float)$input['valor'], 2, ',', '.');
        $title = sprintf('Gorjeta de €%s atribuída a %s', $valorFormatado, $employee['name']);
        $statusLabel = $input['status'] ?? 'Gorjeta';
        $statusLabel = trim($statusLabel) === '' ? 'Gorjeta' : ucfirst($statusLabel);
        logActivity(
            $pdo,
            (int)$_SESSION['client_id'],
            $title,
            'success',
            $statusLabel,
            (int)$input['funcionario_id']
        );
    }

    echo json_encode(['success'=>(bool)$ok, 'id'=>$pdo->lastInsertId()]);
} catch (Exception $e) {
    error_log('create_gorjeta error: '.$e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Erro no servidor.']);
}