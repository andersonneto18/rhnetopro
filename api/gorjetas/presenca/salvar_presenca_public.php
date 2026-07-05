<?php

date_default_timezone_set('Europe/Lisbon');
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

// carregue a sua conexão PDO — ajuste o nome se for outro ficheiro
require_once '../../config/db_connection.php'; // deve expor $pdo (PDO)
require_once '../../includes/activity_logger.php';

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success'=>false,'stage'=>'json','message'=>json_last_error_msg(),'raw'=>$raw]);
    exit;
}
if (empty($input['id']) || empty($input['status'])) {
    echo json_encode(['success'=>false,'stage'=>'validation','message'=>'Parâmetros obrigatórios faltando','received'=>$input]);
    exit;
}

$funcionario_id = (int)$input['id'];
$status = $input['status'] === 'presente' ? 'presente' : 'falta';

try {
    // confirma conexão e existência da tabela
    if (!isset($pdo) || !$pdo) {
        throw new Exception('PDO $pdo não está definido. Verifique db_connection.php');
    }

    // debug: verificar funcionário existe
    $cstmt = $pdo->prepare("SELECT id, name, client_id, status FROM employees WHERE id = ? LIMIT 1");
    $cstmt->execute([$funcionario_id]);
    $emp = $cstmt->fetch(PDO::FETCH_ASSOC);
    if (!$emp) {
        echo json_encode(['success'=>false,'stage'=>'lookup','message'=>'Funcionário não encontrado','funcionario_id'=>$funcionario_id]);
        exit;
    }

    $empStatus = mb_strtolower(trim((string)($emp['status'] ?? 'active')));
    if (in_array($empStatus, ['inactive', 'inativo', 'ferias', 'férias'], true)) {
        echo json_encode(['success'=>false,'stage'=>'validation','message'=>'Funcionário inativo ou em férias não pode marcar presença']);
        exit;
    }

    $stmtTurnoAtivo = $pdo->prepare("SELECT id FROM turnos WHERE funcionario_id = ? AND LOWER(COALESCE(status, '')) IN ('ativo', 'active') ORDER BY id DESC LIMIT 1");
    $stmtTurnoAtivo->execute([$funcionario_id]);
    if (!$stmtTurnoAtivo->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode(['success'=>false,'stage'=>'validation','message'=>'Funcionário sem turno ativo não pode marcar presença']);
        exit;
    }

    // procura registo para hoje
    $stmt = $pdo->prepare("SELECT id FROM presencas WHERE funcionario_id = ? AND DATE(data_registro) = CURDATE()");
    $stmt->execute([$funcionario_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $upd = $pdo->prepare("UPDATE presencas SET status = ?, data_registro = NOW() WHERE id = ?");
        $ok = $upd->execute([$status, $row['id']]);
    } else {
        $client_id = $emp['client_id'] ?? 0;
        $ins = $pdo->prepare("INSERT INTO presencas (funcionario_id, client_id, status, data_registro) VALUES (?, ?, ?, NOW())");
        $ok = $ins->execute([$funcionario_id, $client_id, $status]);
    }

    if ($ok) {
        $clientId = (int)($emp['client_id'] ?? 0);
        if ($clientId > 0) {
            $statusLabel = $status === 'presente' ? 'Presente' : 'Falta';
            $activityType = $status === 'presente' ? 'success' : 'warning';
            logActivity(
                $pdo,
                $clientId,
                $emp['name'] . ' marcou ' . $statusLabel . ' (terminal público)',
                $activityType,
                $statusLabel,
                $funcionario_id
            );
        }

        echo json_encode(['success'=>true,'message'=>'Presença gravada','status'=>$status,'employee'=>$emp]);
    } else {
        echo json_encode(['success'=>false,'stage'=>'db','message'=>'Falha ao gravar','errorInfo'=>$pdo->errorInfo()]);
    }
} catch (Exception $e) {
    error_log('salvar_presenca_public debug error: '.$e->getMessage());
    echo json_encode(['success'=>false,'stage'=>'exception','message'=>$e->getMessage()]);
}