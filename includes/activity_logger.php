<?php
// Helper para registar atividades recentes em um único ponto
// Mantém o histórico consistente em todas as operações críticas do sistema.

declare(strict_types=1);

if (!function_exists('logActivity')) {
    /**
     * Regista uma entrada na tabela atividades_recentes.
     *
     * @param PDO         $pdo         Instância PDO já conectada.
     * @param int         $clientId    Identificador do cliente (tenant).
     * @param string      $title       Texto a ser exibido na timeline.
     * @param string      $type        Tipo da atividade (success, info, warning, danger, ...).
     * @param string|null $statusLabel Texto curto para o badge (ex.: Presente, Falta...).
     * @param int|null    $employeeId  Funcionário relacionado, se existir.
     */
    function logActivity(
        PDO $pdo,
        int $clientId,
        string $title,
        string $type = 'info',
        ?string $statusLabel = null,
        ?int $employeeId = null
    ): bool {
        if ($clientId <= 0 || trim($title) === '') {
            return false;
        }

        static $columnSupport = null;
        if ($columnSupport === null) {
            $columnSupport = ['status' => false, 'employee_id' => false];
            try {
                $columnSupport['status'] = (bool) $pdo->query("SHOW COLUMNS FROM atividades_recentes LIKE 'status'")->fetch();
                $columnSupport['employee_id'] = (bool) $pdo->query("SHOW COLUMNS FROM atividades_recentes LIKE 'employee_id'")->fetch();
            } catch (Throwable $e) {
                // Se não conseguir verificar, assume que colunas não existem
                error_log('logActivity column detect error: ' . $e->getMessage());
            }
        }

        $safeTitle = function_exists('mb_substr') ? mb_substr(trim($title), 0, 255) : substr(trim($title), 0, 255);
        $safeType = function_exists('mb_substr') ? mb_substr($type ?: 'info', 0, 32) : substr($type ?: 'info', 0, 32);
        $safeStatus = $statusLabel !== null
            ? ((function_exists('mb_substr') ? mb_substr(trim($statusLabel), 0, 64) : substr(trim($statusLabel), 0, 64)) ?: null)
            : null;
        $safeEmployee = ($employeeId && $employeeId > 0) ? $employeeId : null;

        $columns = ['title', 'type', 'timestamp', 'client_id'];
        $placeholders = '?, ?, NOW(), ?';
        $params = [$safeTitle, $safeType, $clientId];

        if ($columnSupport['status']) {
            $columns[] = 'status';
            $placeholders .= ', ?';
            $params[] = $safeStatus;
        }

        if ($columnSupport['employee_id']) {
            $columns[] = 'employee_id';
            $placeholders .= ', ?';
            $params[] = $safeEmployee;
        }

        $sql = sprintf(
            'INSERT INTO atividades_recentes (%s) VALUES (%s)',
            implode(', ', $columns),
            $placeholders
        );

        try {
            $stmt = $pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (Throwable $e) {
            error_log('logActivity error: ' . $e->getMessage());
            return false;
        }
    }
}
