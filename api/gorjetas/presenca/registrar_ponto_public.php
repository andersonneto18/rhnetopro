<?php
date_default_timezone_set('Europe/Lisbon');
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../config/db_connection.php'; // deve expor $pdo (PDO)

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success'=>false,'stage'=>'json','message'=>json_last_error_msg(),'raw'=>$raw]);
    exit;
}
if (empty($input['tipo']) || empty($input['funcionario_id'])) {
    echo json_encode(['success'=>false,'stage'=>'validation','message'=>'Parâmetros obrigatórios faltando','received'=>$input]);
    exit;
}

$tipo = $input['tipo'] === 'saida' ? 'saida' : 'entrada';
$funcionario_id = (int)$input['funcionario_id'];
$hora_atual = date('H:i:s');

try {
    if (!isset($pdo) || !$pdo) {
        throw new Exception('PDO $pdo não está definido. Verifique db_connection.php');
    }

    // verifica funcionário
    $cstmt = $pdo->prepare("SELECT id, client_id, name, status FROM employees WHERE id = ? LIMIT 1");
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

    // busca registro de hoje
    $stmt = $pdo->prepare("SELECT id, hora_entrada, hora_saida FROM registros_ponto WHERE funcionario_id = ? AND data_registro = CURDATE() LIMIT 1");
    $stmt->execute([$funcionario_id]);
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($tipo === 'entrada') {
        if (!$registro) {
            // inserir novo registro com hora entrada
            $ins = $pdo->prepare("INSERT INTO registros_ponto (funcionario_id, client_id, data_registro, hora_entrada) VALUES (?, ?, CURDATE(), ?)");
            $client_id = $emp['client_id'] ?? 0;
            $ok = $ins->execute([$funcionario_id, $client_id, $hora_atual]);
            $action = 'insercao';
        } else {
            if (empty($registro['hora_entrada'])) {
                $upd = $pdo->prepare("UPDATE registros_ponto SET hora_entrada = ? WHERE id = ?");
                $ok = $upd->execute([$hora_atual, $registro['id']]);
                $action = 'update_entrada';
            } else {
                echo json_encode(['success'=>false,'stage'=>'logic','message'=>'Entrada já registrada','hora_entrada'=>substr($registro['hora_entrada'],0,5)]);
                exit;
            }
        }
    } else { // saida
        if ($registro && empty($registro['hora_saida'])) {
            $upd = $pdo->prepare("UPDATE registros_ponto SET hora_saida = ? WHERE id = ?");
            $ok = $upd->execute([$hora_atual, $registro['id']]);
            $action = 'update_saida';
        } else if (!$registro) {
            echo json_encode(['success'=>false,'stage'=>'logic','message'=>'Registre a entrada primeiro']);
            exit;
        } else {
            echo json_encode(['success'=>false,'stage'=>'logic','message'=>'Saída já registrada','hora_saida'=>substr($registro['hora_saida'],0,5)]);
            exit;
        }
    }

    if (!empty($ok)) {
        echo json_encode([
            'success' => true,
            'message' => 'Ponto registrado',
            'tipo' => $tipo,
            'hora' => substr($hora_atual,0,5),
            'action' => $action,
            'employee' => ['id'=>$emp['id'],'name'=>$emp['name']]
        ]);
    } else {
        echo json_encode(['success'=>false,'stage'=>'db','message'=>'Falha ao gravar ponto','errorInfo'=>$pdo->errorInfo()]);
    }
} catch (Exception $e) {
    error_log('registrar_ponto_public debug error: '.$e->getMessage());
    echo json_encode(['success'=>false,'stage'=>'exception','message'=>$e->getMessage()]);
}