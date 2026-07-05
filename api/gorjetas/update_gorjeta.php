<?php
session_start();
require_once '../../config/db_connection.php';
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
if (!$input || !isset($input['id'])) {
    echo json_encode(['success'=>false,'message'=>'Dados inválidos ou ID ausente']);
    exit;
}

$required = ['funcionario_id','data_registro','valor'];
foreach ($required as $f) {
    if (!isset($input[$f]) || $input[$f] === '') {
        echo json_encode(['success'=>false,'message'=>"Campo obrigatório ausente: $f"]);
        exit;
    }
}

// verificar se a gorjeta pertence ao cliente
$stmt = $pdo->prepare("SELECT id FROM gorjetas WHERE id = ? AND client_id = ?");
$stmt->execute([(int)$input['id'], $_SESSION['client_id']]);
if (!$stmt->fetch()) {
    echo json_encode(['success'=>false,'message'=>'Gorjeta não encontrada ou não pertence ao seu cliente']);
    exit;
}

// verificar funcionário pertence ao client
$stmt = $pdo->prepare("SELECT id FROM employees WHERE id = ? AND client_id = ?");
$stmt->execute([(int)$input['funcionario_id'], $_SESSION['client_id']]);
if (!$stmt->fetch()) {
    echo json_encode(['success'=>false,'message'=>'Funcionário inválido']);
    exit;
}

try {
    $turnoTexto = $input['turno'] ?? '';
    
    $dateColumn = getGorjetaDateColumn($pdo);

    $stmt = $pdo->prepare("
        UPDATE gorjetas 
        SET funcionario_id = ?, {$dateColumn} = ?, valor = ?, 
            forma_pagamento = ?, origem = ?, status = ?
        WHERE id = ? AND client_id = ?
    ");
    $ok = $stmt->execute([
        (int)$input['funcionario_id'],
        $input['data_registro'],
        (float)$input['valor'],
        $input['forma_pagamento'] ?? 'Dinheiro',
        $input['origem'] ?? '',
        $input['status'] ?? 'pendente',
        (int)$input['id'],
        $_SESSION['client_id']
    ]);
    
    // Tentar atualizar turno se a coluna existir
    if ($ok && !empty($turnoTexto)) {
        try {
            $stmt2 = $pdo->prepare("UPDATE gorjetas SET turno = ? WHERE id = ?");
            $stmt2->execute([$turnoTexto, (int)$input['id']]);
        } catch (Exception $e2) {
            // Coluna turno não existe, ignorar
        }
    }
    
    echo json_encode(['success'=>(bool)$ok]);
} catch (Exception $e) {
    error_log('update_gorjeta error: '.$e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Erro no servidor: ' . $e->getMessage()]);
}