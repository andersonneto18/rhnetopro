<?php
session_start();
header('Content-Type: application/json');


require_once '../config/db_connection.php';

// Verifica autenticação
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['client_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit();
}

try {
    $employee_id = (int)$_SESSION['employee_id'];
    $client_id = (int)$_SESSION['client_id'];
    $data_hoje = date('Y-m-d');
    $hora_atual = date('H:i');

    // Funcionário em férias não pode marcar presença.
    $stmtEmpStatus = $pdo->prepare("SELECT status FROM employees WHERE id = ? AND client_id = ? LIMIT 1");
    $stmtEmpStatus->execute([$employee_id, $client_id]);
    $empStatusRow = $stmtEmpStatus->fetch(PDO::FETCH_ASSOC);
    $empStatus = mb_strtolower(trim((string)($empStatusRow['status'] ?? '')));
    if (in_array($empStatus, ['ferias', 'férias'], true)) {
        throw new Exception('Funcionário em férias não pode marcar presença.');
    }

    // Buscar turno do funcionário (ativo mais recente)
    $stmtTurno = $pdo->prepare("SELECT horario_inicio FROM turnos WHERE funcionario_id = ? AND LOWER(COALESCE(status, '')) IN ('ativo', 'active') ORDER BY id DESC LIMIT 1");
    $stmtTurno->execute([$employee_id]);
    $turno = $stmtTurno->fetch(PDO::FETCH_ASSOC);
    if (!$turno || empty($turno['horario_inicio'])) {
        throw new Exception('Turno do funcionário não encontrado.');
    }
    $hora_entrada = $turno['horario_inicio']; // formato HH:ii:ss ou HH:ii
    $hora_entrada = substr($hora_entrada, 0, 5); // garantir HH:ii

    // Buscar tolerância do restaurante
    $stmtTol = $pdo->prepare("SELECT tolerancia_atraso_min FROM estabelecimento_horarios WHERE client_id = ? LIMIT 1");
    $stmtTol->execute([$client_id]);
    $rowTol = $stmtTol->fetch(PDO::FETCH_ASSOC);
    $tolerancia = $rowTol ? (int)$rowTol['tolerancia_atraso_min'] : 0;

    // Calcular limites
    $entradaTimestamp = strtotime($data_hoje . ' ' . $hora_entrada);
    $toleranciaTimestamp = $entradaTimestamp + ($tolerancia * 60);
    $agoraTimestamp = strtotime($data_hoje . ' ' . $hora_atual);

    // Verificar se já existe registro de presença hoje
        $stmt = $pdo->prepare("
            SELECT id, status FROM presencas 
            WHERE funcionario_id = ? AND DATE(data_registro) = ?
        ");
    $stmt->execute([$employee_id, $data_hoje]);
    $existe = $stmt->fetch(PDO::FETCH_ASSOC);

        // Se já existe falta, impedir marcação
        if ($existe && isset($existe['status']) && $existe['status'] === 'falta') {
            echo json_encode([
                'success' => false,
                'message' => 'Você não pode mais marcar presença hoje, pois já foi registrado como falta.',
                'status' => 'falta'
            ]);
            exit();
        }

    // Determinar status
    if ($agoraTimestamp < $entradaTimestamp) {
        $status = 'nao_registrado';
        $message = 'Ainda não está no horário de entrada.';
    } elseif ($agoraTimestamp <= $toleranciaTimestamp) {
        $status = 'presente';
        $message = 'Presença registrada: Presente';
    } else {
        $status = 'falta';
        $message = 'Presença não pode ser registrada: Falta (fora do tempo de tolerância)';
    }

    if ($status === 'nao_registrado') {
        echo json_encode([
            'success' => false,
            'message' => $message,
            'status' => $status
        ]);
        exit();
    }

    if ($existe) {
        // Atualizar
        $stmtUpdate = $pdo->prepare("
            UPDATE presencas 
            SET status = ?
            WHERE id = ?
        ");
        $stmtUpdate->execute([$status, $existe['id']]);
    } else {
        // Inserir novo
        $stmtInsert = $pdo->prepare("
            INSERT INTO presencas (funcionario_id, status, data_registro)
            VALUES (?, ?, ?)
        ");
        $stmtInsert->execute([$employee_id, $status, $data_hoje]);
    }

    // Registrar atividade para o admin
    require_once '../includes/activity_logger.php';
    $stmtEmployee = $pdo->prepare("SELECT name FROM employees WHERE id = ?");
    $stmtEmployee->execute([$employee_id]);
    $employee = $stmtEmployee->fetch(PDO::FETCH_ASSOC);
    $employee_name = $employee['name'] ?? 'Funcionário';
    $titulo = "$employee_name marcou presença";
    $tipo = ($status === 'presente' ? 'success' : 'danger');
    $statusText = ucfirst($status);
    logActivity($pdo, $client_id, $titulo, $tipo, $statusText, $employee_id);

    echo json_encode([
        'success' => true,
        'message' => $message,
        'status' => $status
    ]);

} catch (PDOException $e) {
    error_log('salvar_presenca_session.php erro: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro no servidor. Tente novamente.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
