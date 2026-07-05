<?php
// Script para atualizar status de presenças para 'atrasado' ou 'falta' automaticamente
// Execute manualmente ou via cron para manter o status atualizado no banco

require_once '../config/db_connection.php';

date_default_timezone_set('Europe/Lisbon');
$data_hoje = date('Y-m-d');
$hora_atual = date('H:i');

// Busca todos os funcionários ativos do cliente
$stmtEmployees = $pdo->prepare("SELECT e.id, e.name, t.horario_inicio, t.status as turno_status, c.tolerancia_atraso_min
    FROM employees e
    INNER JOIN turnos t ON t.funcionario_id = e.id AND t.status = 'ativo'
    INNER JOIN estabelecimento_horarios c ON c.client_id = e.client_id
    WHERE e.status = 'active' AND e.client_id = ?");
$client_id = isset($_SESSION['client_id']) ? (int)$_SESSION['client_id'] : 0;
$stmtEmployees->execute([$client_id]);
$funcionarios = $stmtEmployees->fetchAll(PDO::FETCH_ASSOC);

foreach ($funcionarios as $func) {
    $employee_id = (int)$func['id'];
    $hora_entrada = substr($func['horario_inicio'], 0, 5);
    $tolerancia = (int)$func['tolerancia_atraso_min'];
    $entradaTimestamp = strtotime($data_hoje . ' ' . $hora_entrada);
    $toleranciaTimestamp = $entradaTimestamp + ($tolerancia * 60);
    $agoraTimestamp = strtotime($data_hoje . ' ' . $hora_atual);

    // Verifica se já existe registro de presença hoje
    $stmtPresenca = $pdo->prepare("SELECT id, status FROM presencas WHERE funcionario_id = ? AND DATE(data_registro) = ?");
    $stmtPresenca->execute([$employee_id, $data_hoje]);
    $presenca = $stmtPresenca->fetch(PDO::FETCH_ASSOC);

    if (!$presenca) {
        // Não há registro, decidir status
        if ($agoraTimestamp > $entradaTimestamp && $agoraTimestamp <= $toleranciaTimestamp) {
            // Está atrasado
            $stmtInsert = $pdo->prepare("INSERT INTO presencas (funcionario_id, status, data_registro) VALUES (?, 'atrasado', ?)");
            $stmtInsert->execute([$employee_id, $data_hoje]);
        } elseif ($agoraTimestamp > $toleranciaTimestamp) {
            // Já passou da tolerância, é falta
            $stmtInsert = $pdo->prepare("INSERT INTO presencas (funcionario_id, status, data_registro) VALUES (?, 'falta', ?)");
            $stmtInsert->execute([$employee_id, $data_hoje]);
        }
    } elseif ($presenca['status'] === 'atrasado' && $agoraTimestamp > $toleranciaTimestamp) {
        // Se já estava atrasado e passou da tolerância, vira falta
        $stmtUpdate = $pdo->prepare("UPDATE presencas SET status = 'falta' WHERE id = ?");
        $stmtUpdate->execute([$presenca['id']]);
    }
}

echo "Status de presenças atualizado com sucesso.";
