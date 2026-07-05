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
    echo json_encode([]);
    exit;
}

try {
    $dateColumn = getGorjetaDateColumn($pdo);

    $stmt = $pdo->prepare("
        SELECT g.*, e.name AS funcionario_nome, t.nome_turno AS turno_nome
        FROM gorjetas g
        JOIN employees e ON g.funcionario_id = e.id
        LEFT JOIN turnos t ON g.turno_id = t.id
        WHERE g.client_id = ?
        ORDER BY g.{$dateColumn} DESC, g.created_at DESC
    ");
    $stmt->execute([$_SESSION['client_id']]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows);
} catch (Exception $e) {
    error_log('get_gorjetas error: '.$e->getMessage());
    echo json_encode([]);
}