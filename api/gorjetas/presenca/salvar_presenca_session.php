<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors',1);
error_reporting(E_ALL);

require_once '../../config/db_connection.php'; // deve expor $pdo (PDO)
require_once '../../includes/activity_logger.php';

if (empty($_SESSION['employee_id'])) {
    echo json_encode(['success'=>false,'message'=>'Não autenticado','code'=>'not_authenticated']);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success'=>false,'message'=>'JSON inválido','error'=>json_last_error_msg(),'raw'=>$raw]);
    exit;
}

$status = isset($input['status']) && $input['status'] === 'presente' ? 'presente' : 'falta';
$funcionario_id = (int)$_SESSION['employee_id'];

try {
    // verifica funcionário
    $c = $pdo->prepare("SELECT id, name, client_id FROM employees WHERE id = ? LIMIT 1");
    $c->execute([$funcionario_id]);
    $emp = $c->fetch(PDO::FETCH_ASSOC);
    if (!$emp) {
        echo json_encode(['success'=>false,'message'=>'Funcionário não encontrado','funcionario_id'=>$funcionario_id]);
        exit;
    }
    $empStatus = mb_strtolower(trim((string)($emp['status'] ?? '')));
    if (in_array($empStatus, ['ferias', 'férias'], true)) {
        echo json_encode(['success'=>false,'message'=>'Funcionário em férias não pode marcar presença.','code'=>'employee_on_vacation']);
        exit;
    }

    $stmtTurnoAtivo = $pdo->prepare("SELECT id FROM turnos WHERE funcionario_id = ? AND LOWER(COALESCE(status, '')) IN ('ativo', 'active') ORDER BY id DESC LIMIT 1");
    $stmtTurnoAtivo->execute([$funcionario_id]);
    if (!$stmtTurnoAtivo->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode(['success'=>false,'message'=>'Funcionário sem turno ativo não pode marcar presença.','code'=>'employee_without_shift']);
        exit;
    }

    $clientId = (int)($emp['client_id'] ?? 0);

    // procura registo hoje
    $stmt = $pdo->prepare("SELECT id FROM presencas WHERE funcionario_id = ? AND DATE(data_registro) = CURDATE()");
    $stmt->execute([$funcionario_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $upd = $pdo->prepare("UPDATE presencas SET status = ?, data_registro = NOW() WHERE id = ?");
        $ok = $upd->execute([$status, $row['id']]);
    } else {
        $ins = $pdo->prepare("INSERT INTO presencas (funcionario_id, client_id, status, data_registro) VALUES (?, ?, ?, NOW())");
        $ok = $ins->execute([$funcionario_id, $clientId, $status]);
    }

    if (!empty($ok)) {
        if ($clientId > 0) {
            $statusLabel = $status === 'presente' ? 'Presente' : 'Falta';
            $activityType = $status === 'presente' ? 'success' : 'warning';
            logActivity(
                $pdo,
                $clientId,
                $emp['name'] . ' marcou ' . $statusLabel . ' pelo portal do colaborador',
                $activityType,
                $statusLabel,
                $funcionario_id
            );
        }
        echo json_encode(['success'=>true,'message'=>'Presença gravada','status'=>$status,'employee'=>$emp]);
    } else {
        echo json_encode(['success'=>false,'message'=>'Falha ao gravar','error'=>$pdo->errorInfo()]);
    }
} catch (Exception $e) {
    error_log('salvar_presenca_session error: '.$e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Erro no servidor','exception'=>$e->getMessage()]);
}
?>