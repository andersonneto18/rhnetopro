<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../../config/db_connection.php';

if (empty($_SESSION['employee_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'JSON inválido']);
    exit;
}

$tipo           = ($input['tipo'] ?? '') === 'saida' ? 'saida' : 'entrada';
$observacao     = trim((string)($input['observacao'] ?? ''));
$funcionario_id = (int)$_SESSION['employee_id'];
$hora_atual     = date('H:i:s');

try {
    // Verificar estado e client_id do funcionário numa só query
    $stmtEmp = $pdo->prepare("SELECT status, client_id FROM employees WHERE id = ? LIMIT 1");
    $stmtEmp->execute([$funcionario_id]);
    $emp = $stmtEmp->fetch(PDO::FETCH_ASSOC);

    if (!$emp) {
        echo json_encode(['success' => false, 'message' => 'Funcionário não encontrado']);
        exit;
    }

    $empStatus = mb_strtolower(trim((string)($emp['status'] ?? '')));
    if (in_array($empStatus, ['ferias', 'férias'], true)) {
        echo json_encode(['success' => false, 'message' => 'Funcionário em férias não pode marcar presença.']);
        exit;
    }

    $stmtTurno = $pdo->prepare(
        "SELECT id FROM turnos WHERE funcionario_id = ? AND LOWER(COALESCE(status, '')) IN ('ativo', 'active') ORDER BY id DESC LIMIT 1"
    );
    $stmtTurno->execute([$funcionario_id]);
    if (!$stmtTurno->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode(['success' => false, 'message' => 'Funcionário sem turno ativo não pode marcar presença.']);
        exit;
    }

    $client_id = (int)($emp['client_id'] ?? 0);

    // Período aberto = linha com hora_entrada preenchida e hora_saida a NULL
    $stmtOpen = $pdo->prepare(
        "SELECT id FROM registros_ponto
         WHERE funcionario_id = ? AND data_registro = CURDATE()
           AND hora_entrada IS NOT NULL AND hora_saida IS NULL
         ORDER BY id DESC LIMIT 1"
    );
    $stmtOpen->execute([$funcionario_id]);
    $periodoAberto = $stmtOpen->fetch(PDO::FETCH_ASSOC);

    if ($tipo === 'entrada') {
        if ($periodoAberto) {
            echo json_encode(['success' => false, 'message' => 'Já está em serviço. Registe a saída ou pausa primeiro.']);
            exit;
        }
        // Novo período: entrada inicial ou regresso após pausa
        $ins = $pdo->prepare(
            "INSERT INTO registros_ponto (funcionario_id, client_id, data_registro, hora_entrada, observacao)
             VALUES (?, ?, CURDATE(), ?, ?)"
        );
        $ok = $ins->execute([$funcionario_id, $client_id, $hora_atual, $observacao ?: null]);
    } else {
        // saida / pausa — fecha o período aberto
        if (!$periodoAberto) {
            echo json_encode(['success' => false, 'message' => 'Registre a entrada primeiro.']);
            exit;
        }
        $upd = $pdo->prepare(
            "UPDATE registros_ponto SET hora_saida = ?, observacao = ? WHERE id = ?"
        );
        $ok = $upd->execute([$hora_atual, $observacao ?: null, $periodoAberto['id']]);
    }

    if ($ok) {
        echo json_encode(['success' => true, 'message' => 'Ponto registrado', 'tipo' => $tipo, 'hora' => substr($hora_atual, 0, 5)]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Falha ao gravar']);
    }

} catch (Exception $e) {
    error_log('registrar_ponto_session error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro no servidor']);
}
