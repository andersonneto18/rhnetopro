<?php
// Diretório real de admin/, para uso nas secções incluídas de admin/sections/
// (onde __DIR__ resolveria para admin/sections em vez de admin/)
$ADMIN_DIR = __DIR__;

// ✅ Inicia output buffering ANTES de tudo para capturar warnings/notices
ob_start();

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = (string)$_SESSION['csrf_token'];

date_default_timezone_set('Europe/Lisbon'); // Inicia a sessão para aceder às variáveis de sessão

// ✅ CORREÇÃO AUTOMÁTICA: Se client_id não está na sessão, busca do banco
if (isset($_SESSION['user_id']) && !isset($_SESSION['client_id'])) {
    require_once '../config/db_connection.php';
    try {
        $stmt = $pdo->prepare("SELECT client_id FROM usuarios WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && !empty($user['client_id'])) {
            $_SESSION['client_id'] = $user['client_id'];
            error_log("Dashboard: client_id {$user['client_id']} adicionado automaticamente à sessão para user_id {$_SESSION['user_id']}");
        } else {
            error_log("AVISO: Usuário {$_SESSION['user_id']} não tem client_id no banco de dados!");
        }
    } catch (Exception $e) {
        error_log("Erro ao buscar client_id: " . $e->getMessage());
    }
}

// Helper para enviar resposta JSON sem permitir que quaisquer avisos/echos quebrem o payload
function send_json($data)
{
    // Limpa buffers e descarta qualquer saída anterior (avisos/NOTICES)
    while (ob_get_level()) ob_end_clean();

    // ✅ Garante que não há output anterior
    if (headers_sent($file, $line)) {
        error_log("ERRO: Headers já enviados em $file:$line");
        die(json_encode(['success' => false, 'message' => 'Erro interno: headers já enviados']));
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function normalizeTurnoDayToken(string $value): string
{
    $token = mb_strtolower(trim($value));
    $token = strtr($token, [
        'á' => 'a',
        'à' => 'a',
        'â' => 'a',
        'ã' => 'a',
        'é' => 'e',
        'ê' => 'e',
        'í' => 'i',
        'ó' => 'o',
        'ô' => 'o',
        'õ' => 'o',
        'ú' => 'u',
        'ç' => 'c',
    ]);
    $token = str_replace('-feira', '', $token);

    $map = [
        'segunda' => 'seg',
        'seg' => 'seg',
        'terca' => 'ter',
        'ter' => 'ter',
        'quarta' => 'qua',
        'qua' => 'qua',
        'quinta' => 'qui',
        'qui' => 'qui',
        'sexta' => 'sex',
        'sex' => 'sex',
        'sabado' => 'sab',
        'sab' => 'sab',
        'domingo' => 'dom',
        'dom' => 'dom',
    ];

    return $map[$token] ?? $token;
}

function parseTurnoDays(string $diasSemana): array
{
    $parts = preg_split('/\s*,\s*/', $diasSemana) ?: [];
    $normalized = [];
    foreach ($parts as $part) {
        $day = normalizeTurnoDayToken((string)$part);
        if ($day === '') {
            continue;
        }
        $normalized[$day] = true;
    }
    return array_keys($normalized);
}

function buildTurnoTimeRanges(string $start, string $end): array
{
    $startMin = strtotime('1970-01-01 ' . $start);
    $endMin = strtotime('1970-01-01 ' . $end);
    if ($startMin === false || $endMin === false) {
        return [];
    }

    $startValue = (int)date('G', $startMin) * 60 + (int)date('i', $startMin);
    $endValue = (int)date('G', $endMin) * 60 + (int)date('i', $endMin);

    if ($startValue === $endValue) {
        return [[0, 1440]];
    }

    if ($startValue < $endValue) {
        return [[$startValue, $endValue]];
    }

    return [[$startValue, 1440], [0, $endValue]];
}

function turnoTimeOverlaps(string $aStart, string $aEnd, string $bStart, string $bEnd): bool
{
    $rangesA = buildTurnoTimeRanges($aStart, $aEnd);
    $rangesB = buildTurnoTimeRanges($bStart, $bEnd);

    foreach ($rangesA as $ra) {
        foreach ($rangesB as $rb) {
            if ($ra[0] < $rb[1] && $rb[0] < $ra[1]) {
                return true;
            }
        }
    }

    return false;
}

function normalizeTurnoDate(?string $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        return null;
    }

    return $date->format('Y-m-d');
}

function turnoDateRangesOverlap(?string $startA, ?string $endA, ?string $startB, ?string $endB): bool
{
    $normalizedStartA = $startA ?: '1000-01-01';
    $normalizedEndA = $endA ?: '9999-12-31';
    $normalizedStartB = $startB ?: '1000-01-01';
    $normalizedEndB = $endB ?: '9999-12-31';

    return $normalizedStartA <= $normalizedEndB && $normalizedStartB <= $normalizedEndA;
}

function formatTurnoDateRange(?string $start, ?string $end): string
{
    $start = normalizeTurnoDate($start);
    $end = normalizeTurnoDate($end);

    if (!$start && !$end) {
        return '';
    }

    $startLabel = $start ? date('d/m/Y', strtotime($start)) : '...';
    $endLabel = $end ? date('d/m/Y', strtotime($end)) : '...';

    return $startLabel . ' a ' . $endLabel;
}

function ensureTurnosDateColumns(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    if (!$pdo->query("SHOW COLUMNS FROM turnos LIKE 'data_inicio'")->fetch()) {
        $pdo->exec("ALTER TABLE turnos ADD COLUMN data_inicio DATE NULL AFTER dias_semana");
    }

    if (!$pdo->query("SHOW COLUMNS FROM turnos LIKE 'data_fim'")->fetch()) {
        $pdo->exec("ALTER TABLE turnos ADD COLUMN data_fim DATE NULL AFTER data_inicio");
    }

    $ensured = true;
}

function ensureTurnosPublicationTable(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS turnos_publicacoes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'publicado',
            published_by INT NULL,
            note VARCHAR(255) NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_turnos_publicacao_periodo (client_id, period_start, period_end),
            KEY idx_turnos_publicacao_client_status (client_id, status),
            KEY idx_turnos_publicacao_periodo (period_start, period_end)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $ensured = true;
}

function countActiveTurnosForPeriod(PDO $pdo, int $clientId, string $periodStart, string $periodEnd): int
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM turnos t
         INNER JOIN employees e ON e.id = t.funcionario_id
         WHERE e.client_id = ?
           AND LOWER(COALESCE(t.status, 'ativo')) IN ('ativo', 'active')
           AND COALESCE(t.data_inicio, '1000-01-01') <= ?
           AND COALESCE(t.data_fim, '9999-12-31') >= ?"
    );
    $stmt->execute([$clientId, $periodEnd, $periodStart]);
    return (int)$stmt->fetchColumn();
}

function getTurnosPublicationForPeriod(PDO $pdo, int $clientId, string $periodStart, string $periodEnd): ?array
{
    $stmt = $pdo->prepare(
        "SELECT id, status, updated_at, period_start, period_end, published_by
         FROM turnos_publicacoes
         WHERE client_id = ? AND period_start = ? AND period_end = ?
         LIMIT 1"
    );
    $stmt->execute([$clientId, $periodStart, $periodEnd]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function getUsuarioDisplayName(PDO $pdo, int $userId): string
{
    if ($userId <= 0) {
        return '';
    }

    try {
        $stmt = $pdo->prepare("SELECT nome_completo, nome_usuario, email FROM usuarios WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$row) {
            return 'Utilizador #' . $userId;
        }

        $nomeCompleto = trim((string)($row['nome_completo'] ?? ''));
        if ($nomeCompleto !== '') {
            return $nomeCompleto;
        }

        $nomeUsuario = trim((string)($row['nome_usuario'] ?? ''));
        if ($nomeUsuario !== '') {
            return $nomeUsuario;
        }

        $email = trim((string)($row['email'] ?? ''));
        if ($email !== '') {
            return $email;
        }
    } catch (Throwable $e) {
        error_log('getUsuarioDisplayName: ' . $e->getMessage());
    }

    return 'Utilizador #' . $userId;
}

function findClosedTurnosPublicationOverlap(PDO $pdo, int $clientId, ?string $periodStart, ?string $periodEnd): ?array
{
    $normalizedStart = $periodStart ?: '1000-01-01';
    $normalizedEnd = $periodEnd ?: '9999-12-31';

    $stmt = $pdo->prepare(
        "SELECT id, period_start, period_end, status
         FROM turnos_publicacoes
         WHERE client_id = ?
           AND LOWER(COALESCE(status, '')) = 'fechado'
           AND period_start <= ?
           AND period_end >= ?
         ORDER BY period_start ASC
         LIMIT 1"
    );
    $stmt->execute([$clientId, $normalizedEnd, $normalizedStart]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function findTurnoConflictForEmployee(PDO $pdo, int $clientId, int $employeeId, string $diasSemana, string $horarioInicio, string $horarioFim, ?string $dataInicio = null, ?string $dataFim = null, ?int $ignoreTurnoId = null): ?array
{
    $newDays = parseTurnoDays($diasSemana);
    if (empty($newDays)) {
        return null;
    }

    $sql = "SELECT t.id, t.turno_tipo, t.horario_inicio, t.horario_fim, t.dias_semana, t.data_inicio, t.data_fim
            FROM turnos t
            INNER JOIN employees e ON e.id = t.funcionario_id
            WHERE t.funcionario_id = ?
              AND e.client_id = ?
              AND LOWER(COALESCE(t.status, 'ativo')) <> 'inativo'";
    $params = [$employeeId, $clientId];

    if ($ignoreTurnoId !== null && $ignoreTurnoId > 0) {
        $sql .= ' AND t.id <> ?';
        $params[] = $ignoreTurnoId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as $row) {
        $existingDays = parseTurnoDays((string)($row['dias_semana'] ?? ''));
        if (empty(array_intersect($newDays, $existingDays))) {
            continue;
        }

        if (!turnoDateRangesOverlap(
            $dataInicio,
            $dataFim,
            normalizeTurnoDate((string)($row['data_inicio'] ?? '')),
            normalizeTurnoDate((string)($row['data_fim'] ?? ''))
        )) {
            continue;
        }

        if (turnoTimeOverlaps(
            $horarioInicio,
            $horarioFim,
            (string)($row['horario_inicio'] ?? ''),
            (string)($row['horario_fim'] ?? '')
        )) {
            return $row;
        }
    }

    return null;
}

// Inclui o arquivo de conexão com a base de dados
require_once '../config/db_connection.php'; // já deve criar $pdo
require_once '../includes/activity_logger.php';
require_once '../includes/payroll_calculator.php';

ensureTurnosDateColumns($pdo);
ensureTurnosPublicationTable($pdo);

if (!isset($pdo) && !isset($conn)) {
    die('Erro: nenhuma conexão ao BD encontrada em db_connection.php');
}

// Se já existe $conn (mysqli) mantém, senão cria um wrapper PDO para compatibilidade
if (!isset($conn) && isset($pdo)) {
    class PDOResultWrapper
    {
        private $stmt;
        public function __construct($stmt)
        {
            $this->stmt = $stmt;
        }
        public function fetch_assoc()
        {
            return $this->stmt->fetch(PDO::FETCH_ASSOC);
        }
        public function fetch_all_assoc()
        {
            return $this->stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        public function fetch()
        {
            return $this->fetch_assoc();
        }
        public function rowCount()
        {
            return $this->stmt->rowCount();
        }
    }

    class PDOWrapper
    {
        private $pdo;
        public function __construct($pdo)
        {
            $this->pdo = $pdo;
        }
        public function query($sql)
        {
            $stmt = $this->pdo->query($sql);
            if ($stmt === false) return null;
            return new PDOResultWrapper($stmt);
        }
        public function prepare($sql)
        {
            return $this->pdo->prepare($sql);
        }
    }

    $conn = new PDOWrapper($pdo);
}

try {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS turno_swap_requests (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            requester_employee_id INT NOT NULL,
            target_employee_id INT NOT NULL,
            requester_turno_id INT NOT NULL,
            target_turno_id INT NOT NULL,
            requested_date DATE NULL,
            reason TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pendente_colega',
            review_note TEXT NULL,
            reviewed_by INT NULL,
            reviewed_at DATETIME NULL,
            requested_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_turno_swap_client_status (client_id, status),
            KEY idx_turno_swap_requester (requester_employee_id),
            KEY idx_turno_swap_target (target_employee_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
} catch (Throwable $e) {
    error_log('Erro ao preparar tabela turno_swap_requests: ' . $e->getMessage());
}

// **** SALVAR / ATUALIZAR TURNO (POST) ****
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_turno') {
    // ✅ Limpa qualquer output anterior
    ob_clean();

    $csrfTokenPost = (string)($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], $csrfTokenPost)) {
        send_json(['success' => false, 'message' => 'Token CSRF inválido. Atualize a página e tente novamente.']);
    }

    try {
        $turno_id = !empty($_POST['turno_id']) ? (int)$_POST['turno_id'] : null;
        $funcionario_id = (int)$_POST['funcionario_id'];
        $turno_tipo = $_POST['turno_tipo'];
        $horario_inicio = $_POST['horario_inicio'];
        $horario_fim = $_POST['horario_fim'];
        $dias_semana = $_POST['dias_semana'];
        $data_inicio = normalizeTurnoDate($_POST['data_inicio'] ?? null);
        $data_fim = normalizeTurnoDate($_POST['data_fim'] ?? null);
        $escala = $_POST['escala'];
        $status = $_POST['status'];

        // Validação básica
        if (empty($funcionario_id) || empty($turno_tipo) || empty($horario_inicio) || empty($horario_fim)) {
            send_json(['success' => false, 'message' => 'Campos obrigatórios faltando']);
        }

        $hasDateStart = trim((string)($_POST['data_inicio'] ?? '')) !== '';
        $hasDateEnd = trim((string)($_POST['data_fim'] ?? '')) !== '';
        if ($hasDateStart xor $hasDateEnd) {
            send_json(['success' => false, 'message' => 'Preencha data de início e data de fim para definir a vigência da escala.']);
        }

        if (($hasDateStart && !$data_inicio) || ($hasDateEnd && !$data_fim)) {
            send_json(['success' => false, 'message' => 'Formato inválido na vigência da escala.']);
        }

        if ($data_inicio && $data_fim && $data_inicio > $data_fim) {
            send_json(['success' => false, 'message' => 'A data de início não pode ser maior que a data de fim.']);
        }

        $closedPublication = findClosedTurnosPublicationOverlap(
            $pdo,
            (int)$_SESSION['client_id'],
            $data_inicio,
            $data_fim
        );
        if ($closedPublication) {
            $closedRange = formatTurnoDateRange(
                (string)($closedPublication['period_start'] ?? ''),
                (string)($closedPublication['period_end'] ?? '')
            );
            send_json(['success' => false, 'message' => 'Este turno cruza um período fechado (' . $closedRange . '). Reabra o período antes de alterar a escala.']);
        }

        // Verificar se funcionário pertence ao cliente
        $stmtCheck = $pdo->prepare("SELECT id FROM employees WHERE id = ? AND client_id = ?");
        $stmtCheck->execute([$funcionario_id, $_SESSION['client_id']]);
        if (!$stmtCheck->fetch()) {
            send_json(['success' => false, 'message' => 'Funcionário inválido']);
        }

        $conflictingTurno = findTurnoConflictForEmployee(
            $pdo,
            (int)$_SESSION['client_id'],
            $funcionario_id,
            (string)$dias_semana,
            (string)$horario_inicio,
            (string)$horario_fim,
            $data_inicio,
            $data_fim,
            $turno_id ? (int)$turno_id : null
        );
        if ($conflictingTurno) {
            $conflictLabel = (string)($conflictingTurno['turno_tipo'] ?? 'Turno');
            $conflictStart = substr((string)($conflictingTurno['horario_inicio'] ?? ''), 0, 5);
            $conflictEnd = substr((string)($conflictingTurno['horario_fim'] ?? ''), 0, 5);
            send_json([
                'success' => false,
                'message' => 'Conflito de horário: o funcionário já possui turno sobreposto (' . $conflictLabel . ' ' . $conflictStart . '-' . $conflictEnd . ').'
            ]);
        }

        if ($turno_id) {
            // Garante que o turno a editar pertence ao cliente logado
            $stmtOwner = $pdo->prepare(
                "SELECT t.id
                 FROM turnos t
                 INNER JOIN employees e ON t.funcionario_id = e.id
                 WHERE t.id = ? AND e.client_id = ?
                 LIMIT 1"
            );
            $stmtOwner->execute([$turno_id, $_SESSION['client_id']]);
            if (!$stmtOwner->fetch()) {
                send_json(['success' => false, 'message' => 'Turno não encontrado ou sem permissão']);
            }

            // ATUALIZAR
            $stmt = $pdo->prepare(
                "UPDATE turnos SET
                    funcionario_id = ?,
                    turno_tipo = ?,
                    horario_inicio = ?,
                    horario_fim = ?,
                    dias_semana = ?,
                          data_inicio = ?,
                          data_fim = ?,
                    escala = ?,
                    status = ?
                 WHERE id = ?"
            );
            $stmt->execute([
                $funcionario_id,
                $turno_tipo,
                $horario_inicio,
                $horario_fim,
                $dias_semana,
                $data_inicio,
                $data_fim,
                $escala,
                $status,
                $turno_id
            ]);
            send_json(['success' => true, 'message' => 'Turno atualizado com sucesso']);
        } else {
            // CRIAR
            $stmt = $pdo->prepare(
                "INSERT INTO turnos (funcionario_id, turno_tipo, horario_inicio, horario_fim, dias_semana, data_inicio, data_fim, escala, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $funcionario_id,
                $turno_tipo,
                $horario_inicio,
                $horario_fim,
                $dias_semana,
                $data_inicio,
                $data_fim,
                $escala,
                $status
            ]);
            $newId = $pdo->lastInsertId();

            // Registrar atividade recente: Novo turno
            $activityRow = null;
            try {
                $empStmt = $pdo->prepare("SELECT name FROM employees WHERE id = ? LIMIT 1");
                $empStmt->execute([$funcionario_id]);
                $empRow = $empStmt->fetch(PDO::FETCH_ASSOC);
                $empName = $empRow['name'] ?? '';
                $clientId = (int)($_SESSION['client_id'] ?? 0);
                $titulo = "Novo turno: " . ($empName ? $empName . " - " : "") . $turno_tipo . " (" . $horario_inicio . " - " . $horario_fim . ")";

                logActivity($pdo, $clientId, $titulo, 'info', 'Turno', $funcionario_id);

                // Recupera a atividade inserida para retornar ao cliente (ajuda a atualizar UI sem reload)
                $activityId = $pdo->lastInsertId();
                if ($activityId) {
                    $hasEmpCol = false;
                    $hasStatusCol = false;
                    try {
                        $hasEmpCol = (bool)$pdo->query("SHOW COLUMNS FROM atividades_recentes LIKE 'employee_id'")->fetch();
                        $hasStatusCol = (bool)$pdo->query("SHOW COLUMNS FROM atividades_recentes LIKE 'status'")->fetch();
                    } catch (Exception $e) {
                    }

                    $fields = ['ar.title', 'ar.type', 'ar.timestamp'];
                    $join = '';
                    if ($hasStatusCol) $fields[] = 'ar.status';
                    if ($hasEmpCol) {
                        $fields[] = 'ar.employee_id';
                        $fields[] = 'e.name AS employee_name';
                        $fields[] = 'e.profile_picture AS employee_profile_picture';
                        $join = 'LEFT JOIN employees e ON ar.employee_id = e.id';
                    }

                    $stmtFetch = $pdo->prepare("SELECT " . implode(', ', $fields) . " FROM atividades_recentes ar $join WHERE ar.id = ? LIMIT 1");
                    $stmtFetch->execute([$activityId]);
                    $activityRow = $stmtFetch->fetch(PDO::FETCH_ASSOC);
                }
            } catch (Exception $e) {
                error_log('save_turno: erro ao registrar atividade - ' . $e->getMessage());
            }

            send_json(['success' => true, 'message' => 'Turno criado com sucesso', 'id' => $newId, 'activity' => $activityRow]);
        }
    } catch (Exception $e) {
        send_json(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
    }
    exit;
}

// **** PUBLICAR ESCALA DE TURNOS POR PERÍODO (POST) ****
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'publish_turnos_period') {
    ob_clean();

    $csrfTokenPost = (string)($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], $csrfTokenPost)) {
        send_json(['success' => false, 'message' => 'Token CSRF inválido. Atualize a página e tente novamente.']);
    }

    try {
        $clientId = (int)($_SESSION['client_id'] ?? 0);
        $publishedBy = (int)($_SESSION['user_id'] ?? 0);
        $periodStart = normalizeTurnoDate($_POST['period_start'] ?? null);
        $periodEnd = normalizeTurnoDate($_POST['period_end'] ?? null);
        $note = trim((string)($_POST['note'] ?? ''));

        if ($clientId <= 0) {
            send_json(['success' => false, 'message' => 'Sessão inválida para publicar escala.']);
        }

        if (!$periodStart || !$periodEnd) {
            send_json(['success' => false, 'message' => 'Selecione data de início e fim do período.']);
        }

        if ($periodStart > $periodEnd) {
            send_json(['success' => false, 'message' => 'A data inicial não pode ser maior que a final.']);
        }

        $existingPublication = getTurnosPublicationForPeriod($pdo, $clientId, $periodStart, $periodEnd);
        if ($existingPublication && mb_strtolower((string)($existingPublication['status'] ?? '')) === 'fechado') {
            send_json(['success' => false, 'message' => 'Este período está fechado. Reabra antes de publicar novamente.']);
        }

        $totalTurnosNoPeriodo = countActiveTurnosForPeriod($pdo, $clientId, $periodStart, $periodEnd);

        if ($totalTurnosNoPeriodo <= 0) {
            send_json(['success' => false, 'message' => 'Não há turnos ativos para este período.']);
        }

        $stmtUpsert = $pdo->prepare(
            "INSERT INTO turnos_publicacoes (client_id, period_start, period_end, status, published_by, note)
             VALUES (?, ?, ?, 'publicado', ?, ?)
             ON DUPLICATE KEY UPDATE
                status = 'publicado',
                published_by = VALUES(published_by),
                note = VALUES(note),
                updated_at = CURRENT_TIMESTAMP"
        );
        $stmtUpsert->execute([$clientId, $periodStart, $periodEnd, $publishedBy > 0 ? $publishedBy : null, $note !== '' ? $note : null]);

        try {
            $title = 'Escala publicada: ' . date('d/m/Y', strtotime($periodStart)) . ' a ' . date('d/m/Y', strtotime($periodEnd));
            logActivity($pdo, $clientId, $title, 'success', 'Turno', null);
        } catch (Throwable $e) {
            error_log('publish_turnos_period: erro ao registrar atividade - ' . $e->getMessage());
        }

        send_json([
            'success' => true,
            'status' => 'publicado',
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'total_turnos' => $totalTurnosNoPeriodo,
            'message' => 'Escala publicada com sucesso para o período selecionado.'
        ]);
    } catch (Throwable $e) {
        send_json(['success' => false, 'message' => 'Erro ao publicar escala: ' . $e->getMessage()]);
    }
    exit;
}

// **** CONSULTAR STATUS DE PUBLICAÇÃO POR PERÍODO (POST) ****
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_turnos_publication_status') {
    ob_clean();

    $csrfTokenPost = (string)($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], $csrfTokenPost)) {
        send_json(['success' => false, 'message' => 'Token CSRF inválido. Atualize a página e tente novamente.']);
    }

    try {
        $clientId = (int)($_SESSION['client_id'] ?? 0);
        $periodStart = normalizeTurnoDate($_POST['period_start'] ?? null);
        $periodEnd = normalizeTurnoDate($_POST['period_end'] ?? null);

        if ($clientId <= 0) {
            send_json(['success' => false, 'message' => 'Sessão inválida para consultar publicação.']);
        }

        if (!$periodStart || !$periodEnd) {
            send_json(['success' => false, 'message' => 'Selecione data de início e fim do período.']);
        }

        if ($periodStart > $periodEnd) {
            send_json(['success' => false, 'message' => 'A data inicial não pode ser maior que a final.']);
        }

        $publishedRow = getTurnosPublicationForPeriod($pdo, $clientId, $periodStart, $periodEnd);
        $totalTurnosNoPeriodo = countActiveTurnosForPeriod($pdo, $clientId, $periodStart, $periodEnd);

        $status = 'sem_dados';
        if ($publishedRow) {
            $rawStatus = mb_strtolower((string)($publishedRow['status'] ?? 'publicado'));
            $status = $rawStatus === 'fechado' ? 'fechado' : 'publicado';
        } elseif ($totalTurnosNoPeriodo > 0) {
            $status = 'sem_publicacao';
        }

        send_json([
            'success' => true,
            'status' => $status,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'total_turnos' => $totalTurnosNoPeriodo,
            'updated_at' => $publishedRow['updated_at'] ?? null,
            'published_by' => isset($publishedRow['published_by']) ? (int)$publishedRow['published_by'] : null,
            'published_by_name' => isset($publishedRow['published_by']) ? getUsuarioDisplayName($pdo, (int)$publishedRow['published_by']) : null,
        ]);
    } catch (Throwable $e) {
        send_json(['success' => false, 'message' => 'Erro ao consultar publicação: ' . $e->getMessage()]);
    }
    exit;
}

// **** FECHAR ESCALA DE TURNOS POR PERÍODO (POST) ****
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'close_turnos_period') {
    ob_clean();

    $csrfTokenPost = (string)($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], $csrfTokenPost)) {
        send_json(['success' => false, 'message' => 'Token CSRF inválido. Atualize a página e tente novamente.']);
    }

    try {
        $clientId = (int)($_SESSION['client_id'] ?? 0);
        $publishedBy = (int)($_SESSION['user_id'] ?? 0);
        $periodStart = normalizeTurnoDate($_POST['period_start'] ?? null);
        $periodEnd = normalizeTurnoDate($_POST['period_end'] ?? null);

        if ($clientId <= 0) {
            send_json(['success' => false, 'message' => 'Sessão inválida para fechar escala.']);
        }

        if (!$periodStart || !$periodEnd) {
            send_json(['success' => false, 'message' => 'Selecione data de início e fim do período.']);
        }

        if ($periodStart > $periodEnd) {
            send_json(['success' => false, 'message' => 'A data inicial não pode ser maior que a final.']);
        }

        $totalTurnosNoPeriodo = countActiveTurnosForPeriod($pdo, $clientId, $periodStart, $periodEnd);
        if ($totalTurnosNoPeriodo <= 0) {
            send_json(['success' => false, 'message' => 'Não há turnos ativos para este período.']);
        }

        $stmtUpsert = $pdo->prepare(
            "INSERT INTO turnos_publicacoes (client_id, period_start, period_end, status, published_by)
             VALUES (?, ?, ?, 'fechado', ?)
             ON DUPLICATE KEY UPDATE
                status = 'fechado',
                published_by = VALUES(published_by),
                updated_at = CURRENT_TIMESTAMP"
        );
        $stmtUpsert->execute([$clientId, $periodStart, $periodEnd, $publishedBy > 0 ? $publishedBy : null]);

        try {
            $title = 'Escala fechada: ' . date('d/m/Y', strtotime($periodStart)) . ' a ' . date('d/m/Y', strtotime($periodEnd));
            logActivity($pdo, $clientId, $title, 'warning', 'Turno', null);
        } catch (Throwable $e) {
            error_log('close_turnos_period: erro ao registrar atividade - ' . $e->getMessage());
        }

        send_json([
            'success' => true,
            'status' => 'fechado',
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'total_turnos' => $totalTurnosNoPeriodo,
            'message' => 'Escala fechada com sucesso para o período selecionado.'
        ]);
    } catch (Throwable $e) {
        send_json(['success' => false, 'message' => 'Erro ao fechar escala: ' . $e->getMessage()]);
    }
    exit;
}

// **** REABRIR ESCALA DE TURNOS POR PERÍODO (POST) ****
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reopen_turnos_period') {
    ob_clean();

    $csrfTokenPost = (string)($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], $csrfTokenPost)) {
        send_json(['success' => false, 'message' => 'Token CSRF inválido. Atualize a página e tente novamente.']);
    }

    try {
        $clientId = (int)($_SESSION['client_id'] ?? 0);
        $publishedBy = (int)($_SESSION['user_id'] ?? 0);
        $periodStart = normalizeTurnoDate($_POST['period_start'] ?? null);
        $periodEnd = normalizeTurnoDate($_POST['period_end'] ?? null);

        if ($clientId <= 0) {
            send_json(['success' => false, 'message' => 'Sessão inválida para reabrir escala.']);
        }

        if (!$periodStart || !$periodEnd) {
            send_json(['success' => false, 'message' => 'Selecione data de início e fim do período.']);
        }

        if ($periodStart > $periodEnd) {
            send_json(['success' => false, 'message' => 'A data inicial não pode ser maior que a final.']);
        }

        $existingPublication = getTurnosPublicationForPeriod($pdo, $clientId, $periodStart, $periodEnd);
        if (!$existingPublication || mb_strtolower((string)($existingPublication['status'] ?? '')) !== 'fechado') {
            send_json(['success' => false, 'message' => 'Este período não está fechado.']);
        }

        $stmtUpdate = $pdo->prepare(
            "UPDATE turnos_publicacoes
             SET status = 'publicado', published_by = ?, updated_at = CURRENT_TIMESTAMP
             WHERE client_id = ? AND period_start = ? AND period_end = ?"
        );
        $stmtUpdate->execute([$publishedBy > 0 ? $publishedBy : null, $clientId, $periodStart, $periodEnd]);

        $totalTurnosNoPeriodo = countActiveTurnosForPeriod($pdo, $clientId, $periodStart, $periodEnd);

        try {
            $title = 'Escala reaberta: ' . date('d/m/Y', strtotime($periodStart)) . ' a ' . date('d/m/Y', strtotime($periodEnd));
            logActivity($pdo, $clientId, $title, 'info', 'Turno', null);
        } catch (Throwable $e) {
            error_log('reopen_turnos_period: erro ao registrar atividade - ' . $e->getMessage());
        }

        send_json([
            'success' => true,
            'status' => 'publicado',
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'total_turnos' => $totalTurnosNoPeriodo,
            'message' => 'Escala reaberta com sucesso para o período selecionado.'
        ]);
    } catch (Throwable $e) {
        send_json(['success' => false, 'message' => 'Erro ao reabrir escala: ' . $e->getMessage()]);
    }
    exit;
}

// **** APAGAR PUBLICAÇÃO DE ESCALA POR PERÍODO (POST) ****
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'unpublish_turnos_period') {
    ob_clean();

    $csrfTokenPost = (string)($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], $csrfTokenPost)) {
        send_json(['success' => false, 'message' => 'Token CSRF inválido. Atualize a página e tente novamente.']);
    }

    try {
        $clientId = (int)($_SESSION['client_id'] ?? 0);
        $periodStart = normalizeTurnoDate($_POST['period_start'] ?? null);
        $periodEnd = normalizeTurnoDate($_POST['period_end'] ?? null);

        if ($clientId <= 0) {
            send_json(['success' => false, 'message' => 'Sessão inválida para remover publicação.']);
        }

        if (!$periodStart || !$periodEnd) {
            send_json(['success' => false, 'message' => 'Selecione data de início e fim do período.']);
        }

        if ($periodStart > $periodEnd) {
            send_json(['success' => false, 'message' => 'A data inicial não pode ser maior que a final.']);
        }

        $existingPublication = getTurnosPublicationForPeriod($pdo, $clientId, $periodStart, $periodEnd);
        if ($existingPublication && mb_strtolower((string)($existingPublication['status'] ?? '')) === 'fechado') {
            send_json(['success' => false, 'message' => 'Este período está fechado. Reabra antes de remover a publicação.']);
        }

        $stmtDelete = $pdo->prepare(
            "DELETE FROM turnos_publicacoes
             WHERE client_id = ? AND period_start = ? AND period_end = ?"
        );
        $stmtDelete->execute([$clientId, $periodStart, $periodEnd]);
        $deleted = $stmtDelete->rowCount() > 0;

        $totalTurnosNoPeriodo = countActiveTurnosForPeriod($pdo, $clientId, $periodStart, $periodEnd);

        $newStatus = $totalTurnosNoPeriodo > 0 ? 'sem_publicacao' : 'sem_dados';

        if ($deleted) {
            try {
                $title = 'Publicação removida: ' . date('d/m/Y', strtotime($periodStart)) . ' a ' . date('d/m/Y', strtotime($periodEnd));
                logActivity($pdo, $clientId, $title, 'warning', 'Turno', null);
            } catch (Throwable $e) {
                error_log('unpublish_turnos_period: erro ao registrar atividade - ' . $e->getMessage());
            }
        }

        send_json([
            'success' => true,
            'status' => $newStatus,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'total_turnos' => $totalTurnosNoPeriodo,
            'removed' => $deleted,
            'message' => $deleted
                ? 'Publicação removida com sucesso para o período selecionado.'
                : 'Não existia publicação salva para este período.'
        ]);
    } catch (Throwable $e) {
        send_json(['success' => false, 'message' => 'Erro ao remover publicação: ' . $e->getMessage()]);
    }
    exit;
}

// **** CRIAR TURNOS EM MASSA (POST) ****
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_bulk_turnos') {
    ob_clean();

    $csrfTokenPost = (string)($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], $csrfTokenPost)) {
        send_json(['success' => false, 'message' => 'Token CSRF inválido. Atualize a página e tente novamente.']);
    }

    try {
        $turno_tipo = trim((string)($_POST['turno_tipo'] ?? ''));
        $horario_inicio = trim((string)($_POST['horario_inicio'] ?? ''));
        $horario_fim = trim((string)($_POST['horario_fim'] ?? ''));
        $dias_semana = trim((string)($_POST['dias_semana'] ?? ''));
        $data_inicio = normalizeTurnoDate($_POST['data_inicio'] ?? null);
        $data_fim = normalizeTurnoDate($_POST['data_fim'] ?? null);
        $escala = trim((string)($_POST['escala'] ?? ''));
        $status = trim((string)($_POST['status'] ?? 'ativo'));
        $employeeIdsRaw = $_POST['employee_ids'] ?? [];

        if (!is_array($employeeIdsRaw)) {
            $employeeIdsRaw = [];
        }

        $employeeIds = [];
        foreach ($employeeIdsRaw as $empId) {
            $id = (int)$empId;
            if ($id > 0) {
                $employeeIds[$id] = true;
            }
        }
        $employeeIds = array_keys($employeeIds);

        if ($turno_tipo === '' || $horario_inicio === '' || $horario_fim === '' || $dias_semana === '' || $escala === '') {
            send_json(['success' => false, 'message' => 'Preencha todos os campos obrigatórios para criação em massa.']);
        }

        if (empty($employeeIds)) {
            send_json(['success' => false, 'message' => 'Selecione pelo menos um funcionário para criação em massa.']);
        }

        $hasDateStart = trim((string)($_POST['data_inicio'] ?? '')) !== '';
        $hasDateEnd = trim((string)($_POST['data_fim'] ?? '')) !== '';
        if ($hasDateStart xor $hasDateEnd) {
            send_json(['success' => false, 'message' => 'Preencha data de início e data de fim para definir a vigência da escala.']);
        }

        if (($hasDateStart && !$data_inicio) || ($hasDateEnd && !$data_fim)) {
            send_json(['success' => false, 'message' => 'Formato inválido na vigência da escala.']);
        }

        if ($data_inicio && $data_fim && $data_inicio > $data_fim) {
            send_json(['success' => false, 'message' => 'A data de início não pode ser maior que a data de fim.']);
        }

        $closedPublication = findClosedTurnosPublicationOverlap($pdo, $clientId, $data_inicio, $data_fim);
        if ($closedPublication) {
            $closedRange = formatTurnoDateRange(
                (string)($closedPublication['period_start'] ?? ''),
                (string)($closedPublication['period_end'] ?? '')
            );
            send_json(['success' => false, 'message' => 'A criação em massa cruza um período fechado (' . $closedRange . '). Reabra o período antes de alterar a escala.']);
        }

        $clientId = (int)($_SESSION['client_id'] ?? 0);
        if ($clientId <= 0) {
            send_json(['success' => false, 'message' => 'Sessão inválida para criar turnos em massa.']);
        }

        $placeholders = implode(',', array_fill(0, count($employeeIds), '?'));
        $sqlEmployees = "SELECT id, name, status FROM employees WHERE client_id = ? AND id IN ($placeholders)";
        $paramsEmployees = array_merge([$clientId], $employeeIds);
        $stmtEmployees = $pdo->prepare($sqlEmployees);
        $stmtEmployees->execute($paramsEmployees);
        $employeeRows = $stmtEmployees->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (empty($employeeRows)) {
            send_json(['success' => false, 'message' => 'Nenhum funcionário válido encontrado para criação em massa.']);
        }

        $createdCount = 0;
        $conflicts = [];
        $invalidStatus = [];

        $stmtInsert = $pdo->prepare(
            "INSERT INTO turnos (funcionario_id, turno_tipo, horario_inicio, horario_fim, dias_semana, data_inicio, data_fim, escala, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        foreach ($employeeRows as $employeeRow) {
            $employeeId = (int)($employeeRow['id'] ?? 0);
            $employeeName = (string)($employeeRow['name'] ?? ('ID ' . $employeeId));
            $empStatus = mb_strtolower(trim((string)($employeeRow['status'] ?? '')));

            if (in_array($empStatus, ['inactive', 'inativo', 'ferias', 'férias'], true)) {
                $invalidStatus[] = $employeeName;
                continue;
            }

            $conflictingTurno = findTurnoConflictForEmployee(
                $pdo,
                $clientId,
                $employeeId,
                $dias_semana,
                $horario_inicio,
                $horario_fim,
                $data_inicio,
                $data_fim,
                null
            );

            if ($conflictingTurno) {
                $conflictLabel = (string)($conflictingTurno['turno_tipo'] ?? 'Turno');
                $conflictStart = substr((string)($conflictingTurno['horario_inicio'] ?? ''), 0, 5);
                $conflictEnd = substr((string)($conflictingTurno['horario_fim'] ?? ''), 0, 5);
                $conflicts[] = $employeeName . ' (' . $conflictLabel . ' ' . $conflictStart . '-' . $conflictEnd . ')';
                continue;
            }

            $stmtInsert->execute([
                $employeeId,
                $turno_tipo,
                $horario_inicio,
                $horario_fim,
                $dias_semana,
                $data_inicio,
                $data_fim,
                $escala,
                $status,
            ]);
            $createdCount += 1;
        }

        if ($createdCount > 0) {
            $titulo = 'Turnos em massa criados: ' . $createdCount;
            try {
                logActivity($pdo, $clientId, $titulo, 'info', 'Turno', null);
            } catch (Throwable $e) {
                error_log('save_bulk_turnos: erro ao registrar atividade - ' . $e->getMessage());
            }
        }

        $message = 'Nenhum turno criado.';
        if ($createdCount > 0) {
            $message = $createdCount . ' turno(s) criado(s) com sucesso.';
        }

        if (!empty($conflicts) || !empty($invalidStatus)) {
            $issues = [];
            if (!empty($conflicts)) {
                $issues[] = 'Conflitos: ' . implode('; ', array_slice($conflicts, 0, 6)) . (count($conflicts) > 6 ? '; ...' : '');
            }
            if (!empty($invalidStatus)) {
                $issues[] = 'Ignorados por status: ' . implode(', ', array_slice($invalidStatus, 0, 6)) . (count($invalidStatus) > 6 ? ', ...' : '');
            }
            $message .= ' ' . implode(' | ', $issues);
        }

        send_json([
            'success' => $createdCount > 0,
            'created_count' => $createdCount,
            'conflicts' => $conflicts,
            'invalid_status' => $invalidStatus,
            'message' => $message,
        ]);
    } catch (Throwable $e) {
        send_json(['success' => false, 'message' => 'Erro ao criar turnos em massa: ' . $e->getMessage()]);
    }
    exit;
}

// **** DELETAR TURNO (POST) ****
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_turno') {
    // ✅ Limpa qualquer output anterior
    ob_clean();

    $csrfTokenPost = (string)($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], $csrfTokenPost)) {
        send_json(['success' => false, 'message' => 'Token CSRF inválido. Atualize a página e tente novamente.']);
    }

    try {
        $turno_id = (int)$_POST['turno_id'];

        $stmtTurno = $pdo->prepare(
            "SELECT t.id, t.data_inicio, t.data_fim
             FROM turnos t
             INNER JOIN employees e ON t.funcionario_id = e.id
             WHERE t.id = ? AND e.client_id = ?
             LIMIT 1"
        );
        $stmtTurno->execute([$turno_id, $_SESSION['client_id']]);
        $turnoRow = $stmtTurno->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$turnoRow) {
            send_json(['success' => false, 'message' => 'Turno não encontrado']);
        }

        $closedPublication = findClosedTurnosPublicationOverlap(
            $pdo,
            (int)$_SESSION['client_id'],
            normalizeTurnoDate((string)($turnoRow['data_inicio'] ?? '')),
            normalizeTurnoDate((string)($turnoRow['data_fim'] ?? ''))
        );
        if ($closedPublication) {
            $closedRange = formatTurnoDateRange(
                (string)($closedPublication['period_start'] ?? ''),
                (string)($closedPublication['period_end'] ?? '')
            );
            send_json(['success' => false, 'message' => 'Este turno pertence a um período fechado (' . $closedRange . '). Reabra o período antes de excluir.']);
        }

        // Deletar apenas se pertencer ao cliente
        $stmt = $pdo->prepare("
            DELETE t FROM turnos t
            INNER JOIN employees e ON t.funcionario_id = e.id
            WHERE t.id = ? AND e.client_id = ?
        ");
        $stmt->execute([$turno_id, $_SESSION['client_id']]);

        if ($stmt->rowCount() > 0) {
            send_json(['success' => true, 'message' => 'Turno excluído com sucesso']);
        } else {
            send_json(['success' => false, 'message' => 'Turno não encontrado']);
        }
    } catch (Exception $e) {
        send_json(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
    }
    exit;
}

// **** CRIAR SOLICITAÇÃO DE TROCA DE TURNO (POST) ****
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_turno_swap_request') {
    $csrfTokenPost = (string)($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], $csrfTokenPost)) {
        header('Location: dashboard.php?section=turnos&swap=csrf');
        exit;
    }

    if (!isset($_SESSION['client_id'])) {
        header('Location: dashboard.php?section=turnos&swap=unauthorized');
        exit;
    }

    $clientId = (int)$_SESSION['client_id'];

    try {
        $requesterTurnoId = (int)($_POST['requester_turno_id'] ?? 0);
        $targetTurnoId = (int)($_POST['target_turno_id'] ?? 0);
        $requestedDate = trim((string)($_POST['requested_date'] ?? ''));
        $reason = trim((string)($_POST['reason'] ?? ''));

        if ($requesterTurnoId <= 0 || $targetTurnoId <= 0 || $requesterTurnoId === $targetTurnoId) {
            header('Location: dashboard.php?section=turnos&swap=invalid');
            exit;
        }

        if ($requestedDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedDate)) {
            header('Location: dashboard.php?section=turnos&swap=invalid');
            exit;
        }

        $stmtTurnos = $pdo->prepare(
            "SELECT t.id, t.funcionario_id, t.turno_tipo, t.horario_inicio, t.horario_fim, t.dias_semana,
                    e.name AS employee_name
             FROM turnos t
             INNER JOIN employees e ON e.id = t.funcionario_id
             WHERE t.id IN (?, ?) AND e.client_id = ?"
        );
        $stmtTurnos->execute([$requesterTurnoId, $targetTurnoId, $clientId]);
        $turnosRows = $stmtTurnos->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (count($turnosRows) !== 2) {
            header('Location: dashboard.php?section=turnos&swap=notfound');
            exit;
        }

        $byId = [];
        foreach ($turnosRows as $row) {
            $byId[(int)$row['id']] = $row;
        }
        $requesterTurno = $byId[$requesterTurnoId] ?? null;
        $targetTurno = $byId[$targetTurnoId] ?? null;
        if (!$requesterTurno || !$targetTurno) {
            header('Location: dashboard.php?section=turnos&swap=notfound');
            exit;
        }

        $requesterEmployeeId = (int)($requesterTurno['funcionario_id'] ?? 0);
        $targetEmployeeId = (int)($targetTurno['funcionario_id'] ?? 0);
        if ($requesterEmployeeId <= 0 || $targetEmployeeId <= 0 || $requesterEmployeeId === $targetEmployeeId) {
            header('Location: dashboard.php?section=turnos&swap=invalid');
            exit;
        }

        $stmtDup = $pdo->prepare(
            "SELECT id
             FROM turno_swap_requests
             WHERE client_id = ?
                             AND status IN ('pendente_colega', 'pendente_admin', 'pendente')
               AND ((requester_turno_id = ? AND target_turno_id = ?) OR (requester_turno_id = ? AND target_turno_id = ?))
             LIMIT 1"
        );
        $stmtDup->execute([$clientId, $requesterTurnoId, $targetTurnoId, $targetTurnoId, $requesterTurnoId]);
        if ($stmtDup->fetch(PDO::FETCH_ASSOC)) {
            header('Location: dashboard.php?section=turnos&swap=duplicate');
            exit;
        }

        $conflictForTarget = findTurnoConflictForEmployee(
            $pdo,
            $clientId,
            $targetEmployeeId,
            (string)($requesterTurno['dias_semana'] ?? ''),
            (string)($requesterTurno['horario_inicio'] ?? ''),
            (string)($requesterTurno['horario_fim'] ?? ''),
            $targetTurnoId
        );
        if ($conflictForTarget) {
            header('Location: dashboard.php?section=turnos&swap=conflict');
            exit;
        }

        $conflictForRequester = findTurnoConflictForEmployee(
            $pdo,
            $clientId,
            $requesterEmployeeId,
            (string)($targetTurno['dias_semana'] ?? ''),
            (string)($targetTurno['horario_inicio'] ?? ''),
            (string)($targetTurno['horario_fim'] ?? ''),
            $requesterTurnoId
        );
        if ($conflictForRequester) {
            header('Location: dashboard.php?section=turnos&swap=conflict');
            exit;
        }

        $stmtCreateSwap = $pdo->prepare(
            'INSERT INTO turno_swap_requests (client_id, requester_employee_id, target_employee_id, requester_turno_id, target_turno_id, requested_date, reason, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmtCreateSwap->execute([
            $clientId,
            $requesterEmployeeId,
            $targetEmployeeId,
            $requesterTurnoId,
            $targetTurnoId,
            $requestedDate !== '' ? $requestedDate : null,
            $reason !== '' ? $reason : null,
            'pendente_colega'
        ]);

        try {
            logActivity(
                $pdo,
                $clientId,
                'Solicitação de troca de turno: ' . (string)($requesterTurno['employee_name'] ?? 'Funcionário') . ' ↔ ' . (string)($targetTurno['employee_name'] ?? 'Funcionário'),
                'info',
                'Turnos',
                $requesterEmployeeId
            );
        } catch (Throwable $eLog) {
        }

        header('Location: dashboard.php?section=turnos&swap=created');
        exit;
    } catch (Throwable $e) {
        error_log('Erro ao criar solicitação de troca de turno: ' . $e->getMessage());
        header('Location: dashboard.php?section=turnos&swap=error');
        exit;
    }
}

// **** APROVAÇÕES CENTRALIZADAS NA SECÇÃO DE SOLICITAÇÕES ****
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $solicitacaoAction = (string)$_POST['action'];
    $solicitacoesActions = [
        'approve_presence_request',
        'reject_presence_request',
        'approve_gorjeta_request',
        'reject_gorjeta_request',
        'approve_employee_change_request',
        'reject_employee_change_request',
        'approve_ferias_request',
        'reject_ferias_request',
        'approve_turno_swap_request',
        'reject_turno_swap_request',
        'create_ferias_admin',
        'edit_ferias_admin',
        'cancel_ferias_admin',
    ];

    if (in_array($solicitacaoAction, $solicitacoesActions, true)) {
        $solicitacaoCard = trim((string)($_POST['solicitacao_card'] ?? ''));
        $solicitacaoCardAllowed = ['justificativas', 'presenca', 'gorjetas', 'ferias', 'trocas_turno', 'historico'];
        if (!in_array($solicitacaoCard, $solicitacaoCardAllowed, true)) {
            $solicitacaoCard = '';
        }
        $solicitacoesRedirect = 'dashboard.php?section=solicitacoes'
            . ($solicitacaoCard !== '' ? '&solicitacao_card=' . urlencode($solicitacaoCard) : '');

        $csrfTokenPost = (string)($_POST['csrf_token'] ?? '');
        if (empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], $csrfTokenPost)) {
            header('Location: ' . $solicitacoesRedirect . '&review=csrf');
            exit;
        }

        if (!isset($_SESSION['client_id'])) {
            header('Location: ' . $solicitacoesRedirect . '&review=unauthorized');
            exit;
        }

        $clientId = (int)$_SESSION['client_id'];

        try {
            if ($solicitacaoAction === 'approve_presence_request' || $solicitacaoAction === 'reject_presence_request') {
                $employeeId = (int)($_POST['employee_id'] ?? 0);
                $targetDate = trim((string)($_POST['target_date'] ?? ''));

                if ($employeeId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
                    header('Location: ' . $solicitacoesRedirect . '&review=invalid');
                    exit;
                }

                $stmtEmp = $pdo->prepare('SELECT id FROM employees WHERE id = ? AND client_id = ? LIMIT 1');
                $stmtEmp->execute([$employeeId, $clientId]);
                if (!$stmtEmp->fetch(PDO::FETCH_ASSOC)) {
                    header('Location: ' . $solicitacoesRedirect . '&review=notfound');
                    exit;
                }

                $dateColumn = 'data_registro';
                try {
                    $cols = $pdo->query('SHOW COLUMNS FROM registros_ponto')->fetchAll(PDO::FETCH_COLUMN) ?: [];
                    if (!in_array('data_registro', $cols, true) && in_array('data', $cols, true)) {
                        $dateColumn = 'data';
                    }
                } catch (Throwable $e) {
                }

                if ($solicitacaoAction === 'approve_presence_request') {
                    // Atualiza via JOIN para funcionar tanto com schema novo (client_id) quanto legado
                    // onde registros_ponto pode ter client_id nulo/0.
                    $stmt = $pdo->prepare("UPDATE registros_ponto rp INNER JOIN employees e ON e.id = rp.funcionario_id SET rp.status_confirmacao = 'confirmado' WHERE rp.funcionario_id = ? AND e.client_id = ? AND DATE(rp.{$dateColumn}) = ?");
                    $stmt->execute([$employeeId, $clientId, $targetDate]);
                } else {
                    $stmt = $pdo->prepare("UPDATE registros_ponto rp INNER JOIN employees e ON e.id = rp.funcionario_id SET rp.status = 'invalidado', rp.status_confirmacao = 'pendente' WHERE rp.funcionario_id = ? AND e.client_id = ? AND DATE(rp.{$dateColumn}) = ?");
                    $stmt->execute([$employeeId, $clientId, $targetDate]);
                }
            }

            if ($solicitacaoAction === 'approve_gorjeta_request' || $solicitacaoAction === 'reject_gorjeta_request') {
                $gorjetaId = (int)($_POST['gorjeta_id'] ?? 0);
                if ($gorjetaId <= 0) {
                    header('Location: ' . $solicitacoesRedirect . '&review=invalid');
                    exit;
                }

                $stmtGorjeta = $pdo->prepare('SELECT id FROM gorjetas WHERE id = ? AND client_id = ? LIMIT 1');
                $stmtGorjeta->execute([$gorjetaId, $clientId]);
                if (!$stmtGorjeta->fetch(PDO::FETCH_ASSOC)) {
                    header('Location: ' . $solicitacoesRedirect . '&review=notfound');
                    exit;
                }

                $newStatus = $solicitacaoAction === 'approve_gorjeta_request' ? 'pago' : 'rejeitado';
                $stmtUp = $pdo->prepare('UPDATE gorjetas SET status = ? WHERE id = ? AND client_id = ?');
                $stmtUp->execute([$newStatus, $gorjetaId, $clientId]);
            }

            if ($solicitacaoAction === 'approve_ferias_request' || $solicitacaoAction === 'reject_ferias_request') {
                $feriasId      = (int)($_POST['ferias_id'] ?? 0);
                $motivoRejeicao = trim((string)($_POST['motivo_rejeicao'] ?? ''));
                if ($feriasId <= 0) {
                    header('Location: ' . $solicitacoesRedirect . '&review=invalid');
                    exit;
                }

                $feriasCols = $pdo->query('SHOW COLUMNS FROM ferias')->fetchAll(PDO::FETCH_COLUMN) ?: [];
                $feriasCols = array_map(static fn($c) => mb_strtolower((string)$c), $feriasCols);
                $feriasEmployeeCol = in_array('funcionario_id', $feriasCols, true)
                    ? 'funcionario_id'
                    : (in_array('employee_id', $feriasCols, true) ? 'employee_id' : 'funcionario_id');
                $feriasHasClientCol = in_array('client_id', $feriasCols, true);

                // Auto-migration: coluna motivo_rejeicao
                if (!in_array('motivo_rejeicao', $feriasCols, true)) {
                    $pdo->exec("ALTER TABLE ferias ADD COLUMN motivo_rejeicao VARCHAR(500) DEFAULT NULL");
                    $feriasCols[] = 'motivo_rejeicao';
                }

                $feriasSql = "SELECT f.id, f.{$feriasEmployeeCol} AS employee_id,
                                     f.data_inicio, f.data_fim, e.name AS employee_name
                    FROM ferias f
                    INNER JOIN employees e ON e.id = f.{$feriasEmployeeCol}
                    WHERE f.id = ? AND e.client_id = ?";
                $feriasParams = [$feriasId, $clientId];
                if ($feriasHasClientCol) {
                    $feriasSql .= ' AND f.client_id = ?';
                    $feriasParams[] = $clientId;
                }
                $feriasSql .= ' LIMIT 1';

                $stmtFerias = $pdo->prepare($feriasSql);
                $stmtFerias->execute($feriasParams);
                $feriasRow = $stmtFerias->fetch(PDO::FETCH_ASSOC);
                if (!$feriasRow) {
                    header('Location: ' . $solicitacoesRedirect . '&review=notfound');
                    exit;
                }

                $newFeriasStatus = $solicitacaoAction === 'approve_ferias_request' ? 'aprovada' : 'rejeitada';
                if ($solicitacaoAction === 'reject_ferias_request') {
                    $pdo->prepare('UPDATE ferias SET status = ?, motivo_rejeicao = ? WHERE id = ?')
                        ->execute([$newFeriasStatus, $motivoRejeicao ?: null, $feriasId]);
                } else {
                    $pdo->prepare('UPDATE ferias SET status = ? WHERE id = ?')
                        ->execute([$newFeriasStatus, $feriasId]);
                }

                $feriasEmployeeId = (int)($feriasRow['employee_id'] ?? 0);
                if ($solicitacaoAction === 'approve_ferias_request' && $feriasEmployeeId > 0) {
                    // Só marca "ferias" se o período aprovado já estiver em curso hoje;
                    // períodos futuros ficam como 'ativo' até à data de início (reposição automática trata o resto).
                    $aprIni = (string)($feriasRow['data_inicio'] ?? '');
                    $aprFim = (string)($feriasRow['data_fim'] ?? '');
                    $aprHoje = date('Y-m-d');
                    if ($aprIni !== '' && $aprFim !== '' && $aprIni <= $aprHoje && $aprFim >= $aprHoje) {
                        $pdo->prepare('UPDATE employees SET status = ? WHERE id = ? AND client_id = ?')
                            ->execute(['ferias', $feriasEmployeeId, $clientId]);
                    }
                }

                // Notificar funcionário
                try {
                    $notifCheck = $pdo->query("SHOW TABLES LIKE 'notificacoes'");
                    if ($notifCheck->rowCount() > 0 && $feriasEmployeeId > 0) {
                        $dIni = !empty($feriasRow['data_inicio']) ? date('d/m/Y', strtotime($feriasRow['data_inicio'])) : '?';
                        $dFim = !empty($feriasRow['data_fim'])    ? date('d/m/Y', strtotime($feriasRow['data_fim']))    : '?';
                        if ($solicitacaoAction === 'approve_ferias_request') {
                            $notifMsg = "O seu pedido de férias de $dIni a $dFim foi aprovado.";
                        } else {
                            $notifMsg = $motivoRejeicao
                                ? "O seu pedido de férias de $dIni a $dFim foi rejeitado. Motivo: $motivoRejeicao"
                                : "O seu pedido de férias de $dIni a $dFim foi rejeitado pelo administrador.";
                        }
                        $pdo->prepare("INSERT INTO notificacoes (funcionario_id, client_id, mensagem, data_envio, lida) VALUES (?, ?, ?, NOW(), 0)")
                            ->execute([$feriasEmployeeId, $clientId, $notifMsg]);
                    }
                } catch (Throwable $eNotif) {
                    error_log('ferias notif erro: ' . $eNotif->getMessage());
                }
            }

            if ($solicitacaoAction === 'approve_turno_swap_request' || $solicitacaoAction === 'reject_turno_swap_request') {
                $requestId = (int)($_POST['turno_swap_request_id'] ?? 0);
                $reviewNote = trim((string)($_POST['review_note'] ?? ''));

                if ($requestId <= 0) {
                    header('Location: ' . $solicitacoesRedirect . '&review=invalid');
                    exit;
                }

                $stmtReqSwap = $pdo->prepare(
                    "SELECT r.id, r.status, r.requester_employee_id, r.target_employee_id,
                            r.requester_turno_id, r.target_turno_id,
                            rt.turno_tipo AS requester_turno_tipo, rt.horario_inicio AS requester_horario_inicio, rt.horario_fim AS requester_horario_fim, rt.dias_semana AS requester_dias,
                            tt.turno_tipo AS target_turno_tipo, tt.horario_inicio AS target_horario_inicio, tt.horario_fim AS target_horario_fim, tt.dias_semana AS target_dias,
                            er.name AS requester_name, et.name AS target_name
                     FROM turno_swap_requests r
                     INNER JOIN turnos rt ON rt.id = r.requester_turno_id
                     INNER JOIN turnos tt ON tt.id = r.target_turno_id
                     INNER JOIN employees er ON er.id = r.requester_employee_id
                     INNER JOIN employees et ON et.id = r.target_employee_id
                     WHERE r.id = ? AND r.client_id = ?
                     LIMIT 1"
                );
                $stmtReqSwap->execute([$requestId, $clientId]);
                $swapRow = $stmtReqSwap->fetch(PDO::FETCH_ASSOC);

                if (!$swapRow) {
                    header('Location: ' . $solicitacoesRedirect . '&review=notfound');
                    exit;
                }

                $swapStatusCurrent = mb_strtolower(trim((string)($swapRow['status'] ?? '')));
                if (!in_array($swapStatusCurrent, ['pendente_admin', 'pendente'], true)) {
                    header('Location: ' . $solicitacoesRedirect . '&review=blocked');
                    exit;
                }

                $requesterEmployeeId = (int)($swapRow['requester_employee_id'] ?? 0);
                $targetEmployeeId = (int)($swapRow['target_employee_id'] ?? 0);
                $requesterTurnoId = (int)($swapRow['requester_turno_id'] ?? 0);
                $targetTurnoId = (int)($swapRow['target_turno_id'] ?? 0);

                if ($requesterEmployeeId <= 0 || $targetEmployeeId <= 0 || $requesterTurnoId <= 0 || $targetTurnoId <= 0 || $requesterEmployeeId === $targetEmployeeId) {
                    header('Location: ' . $solicitacoesRedirect . '&review=invalid');
                    exit;
                }

                $stmtTurnoOwnership = $pdo->prepare('SELECT id FROM turnos WHERE (id = ? AND funcionario_id = ?) OR (id = ? AND funcionario_id = ?)');
                $stmtTurnoOwnership->execute([$requesterTurnoId, $requesterEmployeeId, $targetTurnoId, $targetEmployeeId]);
                $ownershipRows = $stmtTurnoOwnership->fetchAll(PDO::FETCH_ASSOC) ?: [];
                if (count($ownershipRows) < 2) {
                    header('Location: ' . $solicitacoesRedirect . '&review=notfound');
                    exit;
                }

                if ($solicitacaoAction === 'approve_turno_swap_request') {
                    $conflictForTarget = findTurnoConflictForEmployee(
                        $pdo,
                        $clientId,
                        $targetEmployeeId,
                        (string)($swapRow['requester_dias'] ?? ''),
                        (string)($swapRow['requester_horario_inicio'] ?? ''),
                        (string)($swapRow['requester_horario_fim'] ?? ''),
                        $targetTurnoId
                    );
                    if ($conflictForTarget) {
                        header('Location: ' . $solicitacoesRedirect . '&review=conflict');
                        exit;
                    }

                    $conflictForRequester = findTurnoConflictForEmployee(
                        $pdo,
                        $clientId,
                        $requesterEmployeeId,
                        (string)($swapRow['target_dias'] ?? ''),
                        (string)($swapRow['target_horario_inicio'] ?? ''),
                        (string)($swapRow['target_horario_fim'] ?? ''),
                        $requesterTurnoId
                    );
                    if ($conflictForRequester) {
                        header('Location: ' . $solicitacoesRedirect . '&review=conflict');
                        exit;
                    }

                    $pdo->beginTransaction();
                    try {
                        $stmtSwapA = $pdo->prepare('UPDATE turnos SET funcionario_id = ? WHERE id = ?');
                        $stmtSwapA->execute([$targetEmployeeId, $requesterTurnoId]);

                        $stmtSwapB = $pdo->prepare('UPDATE turnos SET funcionario_id = ? WHERE id = ?');
                        $stmtSwapB->execute([$requesterEmployeeId, $targetTurnoId]);

                        $stmtMarkSwap = $pdo->prepare('UPDATE turno_swap_requests SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_note = ? WHERE id = ? AND client_id = ?');
                        $stmtMarkSwap->execute(['aprovada', (int)($_SESSION['user_id'] ?? 0), $reviewNote, $requestId, $clientId]);

                        $pdo->commit();
                    } catch (Throwable $eSwap) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        throw $eSwap;
                    }

                    // Notificar ambos os funcionários sobre aprovação
                    try {
                        $notifCheck = $pdo->query("SHOW TABLES LIKE 'notificacoes'");
                        if ($notifCheck->rowCount() > 0) {
                            $rTipo = (string)($swapRow['requester_turno_tipo'] ?? 'turno');
                            $tTipo = (string)($swapRow['target_turno_tipo'] ?? 'turno');
                            $stmtN = $pdo->prepare("INSERT INTO notificacoes (funcionario_id, client_id, mensagem, data_envio, lida) VALUES (?, ?, ?, NOW(), 0)");
                            $stmtN->execute([$requesterEmployeeId, $clientId,
                                "A sua troca de turno ($rTipo ↔ $tTipo) foi aprovada pelo administrador."]);
                            $stmtN->execute([$targetEmployeeId, $clientId,
                                "A troca de turno ($tTipo ↔ $rTipo) foi aprovada pelo administrador."]);
                        }
                    } catch (Throwable $eNotif) {
                        error_log('turno_swap aprovação notif erro: ' . $eNotif->getMessage());
                    }

                    try {
                        logActivity(
                            $pdo,
                            $clientId,
                            'Troca de turno aprovada: ' . (string)($swapRow['requester_name'] ?? 'Funcionário') . ' ↔ ' . (string)($swapRow['target_name'] ?? 'Funcionário'),
                            'success',
                            'Turnos',
                            $requesterEmployeeId
                        );
                    } catch (Throwable $eLogSwap) {
                    }
                } else {
                    $stmtMarkSwap = $pdo->prepare('UPDATE turno_swap_requests SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_note = ? WHERE id = ? AND client_id = ?');
                    $stmtMarkSwap->execute(['rejeitada', (int)($_SESSION['user_id'] ?? 0), $reviewNote, $requestId, $clientId]);

                    // Notificar ambos os funcionários sobre rejeição
                    try {
                        $notifCheck = $pdo->query("SHOW TABLES LIKE 'notificacoes'");
                        if ($notifCheck->rowCount() > 0) {
                            $rTipo   = (string)($swapRow['requester_turno_tipo'] ?? 'turno');
                            $tTipo   = (string)($swapRow['target_turno_tipo'] ?? 'turno');
                            $motNote = $reviewNote ? ' Motivo: ' . $reviewNote : '';
                            $stmtN   = $pdo->prepare("INSERT INTO notificacoes (funcionario_id, client_id, mensagem, data_envio, lida) VALUES (?, ?, ?, NOW(), 0)");
                            $stmtN->execute([$requesterEmployeeId, $clientId,
                                "A sua troca de turno ($rTipo ↔ $tTipo) foi rejeitada pelo administrador.$motNote"]);
                            $stmtN->execute([$targetEmployeeId, $clientId,
                                "A troca de turno ($tTipo ↔ $rTipo) foi rejeitada pelo administrador.$motNote"]);
                        }
                    } catch (Throwable $eNotif) {
                        error_log('turno_swap rejeição notif erro: ' . $eNotif->getMessage());
                    }
                }
            }

            if ($solicitacaoAction === 'create_ferias_admin') {
                $employeeId = (int)($_POST['employee_id'] ?? 0);
                $novoInicio = trim((string)($_POST['data_inicio'] ?? ''));
                $novoFim = trim((string)($_POST['data_fim'] ?? ''));
                $novoMotivo = trim((string)($_POST['motivo'] ?? ''));

                if ($employeeId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $novoInicio) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $novoFim) || $novoInicio > $novoFim) {
                    header('Location: dashboard.php?section=ferias&review=invalid');
                    exit;
                }

                $stmtEmp = $pdo->prepare('SELECT id, vacation_days FROM employees WHERE id = ? AND client_id = ? LIMIT 1');
                $stmtEmp->execute([$employeeId, $clientId]);
                $empRowCreate = $stmtEmp->fetch(PDO::FETCH_ASSOC);
                if (!$empRowCreate) {
                    header('Location: dashboard.php?section=ferias&review=notfound');
                    exit;
                }

                $feriasCols = $pdo->query('SHOW COLUMNS FROM ferias')->fetchAll(PDO::FETCH_COLUMN) ?: [];
                $feriasCols = array_map(static fn($c) => mb_strtolower((string)$c), $feriasCols);
                $feriasEmployeeCol = in_array('funcionario_id', $feriasCols, true)
                    ? 'funcionario_id'
                    : (in_array('employee_id', $feriasCols, true) ? 'employee_id' : 'funcionario_id');
                $feriasHasClientCol = in_array('client_id', $feriasCols, true);
                $feriasHasMotivoCol = in_array('motivo', $feriasCols, true);

                // Validação de saldo: dias pedidos não podem exceder o saldo anual disponível,
                // salvo se o administrador marcar explicitamente "ignorar saldo".
                $ignorarSaldo = !empty($_POST['ignorar_saldo']);
                if (!$ignorarSaldo) {
                    $anoCreate = substr($novoInicio, 0, 4);
                    $diasPedidosCreate = (int)((strtotime($novoFim) - strtotime($novoInicio)) / 86400) + 1;
                    $saldoTotalCreate = max(0, (int)($empRowCreate['vacation_days'] ?? 22));

                    $usadosSql = "SELECT COALESCE(SUM(DATEDIFF(LEAST(data_fim, '$anoCreate-12-31'), GREATEST(data_inicio, '$anoCreate-01-01')) + 1), 0) AS total
                        FROM ferias
                        WHERE {$feriasEmployeeCol} = ?
                          AND LOWER(COALESCE(status, '')) IN ('aprovada', 'aprovado')
                          AND data_fim >= '$anoCreate-01-01' AND data_inicio <= '$anoCreate-12-31'";
                    $usadosParamsCreate = [$employeeId];
                    if ($feriasHasClientCol) {
                        $usadosSql .= ' AND client_id = ?';
                        $usadosParamsCreate[] = $clientId;
                    }
                    $stmtUsadosCreate = $pdo->prepare($usadosSql);
                    $stmtUsadosCreate->execute($usadosParamsCreate);
                    $diasUsadosCreate = (int)$stmtUsadosCreate->fetchColumn();

                    if ($diasPedidosCreate > max(0, $saldoTotalCreate - $diasUsadosCreate)) {
                        header('Location: dashboard.php?section=ferias&review=saldo');
                        exit;
                    }
                }

                // Evita criar férias aprovadas que sobreponham outro período já aprovado do mesmo funcionário.
                $overlapSql = "SELECT f.id
                    FROM ferias f
                    INNER JOIN employees e ON e.id = f.{$feriasEmployeeCol}
                    WHERE f.{$feriasEmployeeCol} = ? AND e.client_id = ?
                    AND LOWER(COALESCE(f.status, '')) IN ('aprovada','aprovado')
                    AND COALESCE(f.data_inicio, '0000-00-00') <= ?
                    AND COALESCE(f.data_fim, '0000-00-00') >= ?";
                $overlapParams = [$employeeId, $clientId, $novoFim, $novoInicio];
                if ($feriasHasClientCol) {
                    $overlapSql .= ' AND f.client_id = ?';
                    $overlapParams[] = $clientId;
                }
                $overlapSql .= ' LIMIT 1';
                $stmtOverlap = $pdo->prepare($overlapSql);
                $stmtOverlap->execute($overlapParams);
                if ($stmtOverlap->fetch(PDO::FETCH_ASSOC)) {
                    header('Location: dashboard.php?section=ferias&review=conflict');
                    exit;
                }

                $insertCols = [$feriasEmployeeCol, 'data_inicio', 'data_fim', 'status'];
                $insertVals = [$employeeId, $novoInicio, $novoFim, 'aprovada'];
                if ($feriasHasMotivoCol) {
                    $insertCols[] = 'motivo';
                    $insertVals[] = $novoMotivo;
                }
                if ($feriasHasClientCol) {
                    $insertCols[] = 'client_id';
                    $insertVals[] = $clientId;
                }

                $placeholders = implode(',', array_fill(0, count($insertCols), '?'));
                $insertSql = 'INSERT INTO ferias (' . implode(',', $insertCols) . ') VALUES (' . $placeholders . ')';
                $stmtInsert = $pdo->prepare($insertSql);
                $stmtInsert->execute($insertVals);

                // Só marca o funcionário como "ferias" se o período criado já estiver em curso hoje.
                $todayIso = date('Y-m-d');
                if ($novoInicio <= $todayIso && $novoFim >= $todayIso) {
                    $stmtUpEmployeeStatus = $pdo->prepare('UPDATE employees SET status = ? WHERE id = ? AND client_id = ?');
                    $stmtUpEmployeeStatus->execute(['ferias', $employeeId, $clientId]);
                }
            }

            if ($solicitacaoAction === 'edit_ferias_admin' || $solicitacaoAction === 'cancel_ferias_admin') {
                $feriasId = (int)($_POST['ferias_id'] ?? 0);
                if ($feriasId <= 0) {
                    header('Location: dashboard.php?section=ferias&review=invalid');
                    exit;
                }

                $feriasCols = $pdo->query('SHOW COLUMNS FROM ferias')->fetchAll(PDO::FETCH_COLUMN) ?: [];
                $feriasCols = array_map(static fn($c) => mb_strtolower((string)$c), $feriasCols);
                $feriasEmployeeCol = in_array('funcionario_id', $feriasCols, true)
                    ? 'funcionario_id'
                    : (in_array('employee_id', $feriasCols, true) ? 'employee_id' : 'funcionario_id');
                $feriasHasClientCol = in_array('client_id', $feriasCols, true);
                $feriasHasMotivoCol = in_array('motivo', $feriasCols, true);

                $feriasSql = "SELECT f.id, f.status, f.data_inicio, f.data_fim, f.{$feriasEmployeeCol} AS employee_id
                    FROM ferias f
                    INNER JOIN employees e ON e.id = f.{$feriasEmployeeCol}
                    WHERE f.id = ? AND e.client_id = ?";
                $feriasParams = [$feriasId, $clientId];
                if ($feriasHasClientCol) {
                    $feriasSql .= ' AND f.client_id = ?';
                    $feriasParams[] = $clientId;
                }
                $feriasSql .= ' LIMIT 1';

                $stmtFerias = $pdo->prepare($feriasSql);
                $stmtFerias->execute($feriasParams);
                $feriasRow = $stmtFerias->fetch(PDO::FETCH_ASSOC);
                if (!$feriasRow) {
                    header('Location: dashboard.php?section=ferias&review=notfound');
                    exit;
                }

                $statusAtual = mb_strtolower(trim((string)($feriasRow['status'] ?? '')));
                if ($statusAtual === 'aprovado') {
                    $statusAtual = 'aprovada';
                }

                $inicioAtual = (string)($feriasRow['data_inicio'] ?? '');
                $fimAtual = (string)($feriasRow['data_fim'] ?? '');
                $todayIso = date('Y-m-d');

                if ($solicitacaoAction === 'edit_ferias_admin') {
                    // Edição só é permitida para férias agendadas (aprovadas e com início no futuro).
                    if ($statusAtual !== 'aprovada' || $inicioAtual === '' || $todayIso >= $inicioAtual) {
                        header('Location: dashboard.php?section=ferias&review=blocked');
                        exit;
                    }

                    $novoInicio = trim((string)($_POST['data_inicio'] ?? ''));
                    $novoFim = trim((string)($_POST['data_fim'] ?? ''));
                    $novoMotivo = trim((string)($_POST['motivo'] ?? ''));
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $novoInicio) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $novoFim) || $novoInicio > $novoFim) {
                        header('Location: dashboard.php?section=ferias&review=invalid');
                        exit;
                    }

                    if ($feriasHasMotivoCol) {
                        $stmtEdit = $pdo->prepare('UPDATE ferias SET data_inicio = ?, data_fim = ?, motivo = ? WHERE id = ?');
                        $stmtEdit->execute([$novoInicio, $novoFim, $novoMotivo, $feriasId]);
                    } else {
                        $stmtEdit = $pdo->prepare('UPDATE ferias SET data_inicio = ?, data_fim = ? WHERE id = ?');
                        $stmtEdit->execute([$novoInicio, $novoFim, $feriasId]);
                    }
                }

                if ($solicitacaoAction === 'cancel_ferias_admin') {
                    $canCancel = false;
                    if ($statusAtual === 'aprovada') {
                        if ($inicioAtual !== '' && $todayIso < $inicioAtual) {
                            // Agendada: pode cancelar livremente.
                            $canCancel = true;
                        } elseif ($inicioAtual !== '' && $fimAtual !== '' && $todayIso >= $inicioAtual && $todayIso <= $fimAtual) {
                            // Em curso: regra restrita - apenas no primeiro dia.
                            $canCancel = ($todayIso === $inicioAtual);
                        }
                    }

                    if (!$canCancel) {
                        header('Location: dashboard.php?section=ferias&review=blocked');
                        exit;
                    }

                    $stmtCancel = $pdo->prepare('UPDATE ferias SET status = ? WHERE id = ?');
                    $stmtCancel->execute(['cancelada', $feriasId]);

                    $feriasEmployeeId = (int)($feriasRow['employee_id'] ?? 0);
                    if ($feriasEmployeeId > 0) {
                        $stmtUpEmployeeStatus = $pdo->prepare('UPDATE employees SET status = ? WHERE id = ? AND client_id = ?');
                        $stmtUpEmployeeStatus->execute(['ativo', $feriasEmployeeId, $clientId]);
                    }
                }
            }

            if ($solicitacaoAction === 'approve_employee_change_request' || $solicitacaoAction === 'reject_employee_change_request') {
                $requestId = (int)($_POST['request_id'] ?? 0);
                $reviewNote = trim((string)($_POST['review_note'] ?? ''));

                if ($requestId <= 0) {
                    header('Location: dashboard.php?section=funcionarios&review=invalid');
                    exit;
                }

                $stmtReq = $pdo->prepare(
                    "SELECT r.id, r.employee_id, r.payload_json, r.status, e.name AS employee_name
                     FROM employee_change_requests r
                     INNER JOIN employees e ON e.id = r.employee_id
                     WHERE r.id = ? AND r.client_id = ?
                     LIMIT 1"
                );
                $stmtReq->execute([$requestId, $clientId]);
                $reqRow = $stmtReq->fetch(PDO::FETCH_ASSOC);

                if (!$reqRow) {
                    header('Location: dashboard.php?section=funcionarios&review=notfound');
                    exit;
                }

                if (mb_strtolower(trim((string)($reqRow['status'] ?? ''))) !== 'pendente') {
                    header('Location: dashboard.php?section=funcionarios&review=blocked');
                    exit;
                }

                if ($solicitacaoAction === 'approve_employee_change_request') {
                    $payload = json_decode((string)($reqRow['payload_json'] ?? '{}'), true);
                    if (!is_array($payload) || empty($payload)) {
                        header('Location: dashboard.php?section=funcionarios&review=invalid');
                        exit;
                    }

                    $allowedCols = ['status', 'salary_base', 'subsidio_alimentacao', 'bonus', 'contractType'];
                    $updates = [];
                    $params = [];

                    foreach ($allowedCols as $col) {
                        if (!array_key_exists($col, $payload)) {
                            continue;
                        }

                        $val = $payload[$col];
                        if (in_array($col, ['salary_base', 'subsidio_alimentacao', 'bonus'], true)) {
                            if (!is_numeric($val)) {
                                continue;
                            }
                            $val = number_format((float)$val, 2, '.', '');
                        }

                        if ($col === 'status') {
                            $statusNorm = mb_strtolower(trim((string)$val));
                            $mapStatus = [
                                'active' => 'active',
                                'ativo' => 'active',
                                'inactive' => 'inactive',
                                'inativo' => 'inactive',
                                'ferias' => 'ferias',
                                'férias' => 'ferias',
                            ];
                            if (!isset($mapStatus[$statusNorm])) {
                                continue;
                            }
                            $val = $mapStatus[$statusNorm];
                        }

                        if ($col === 'contractType' && $val !== null && $val !== '') {
                            if (!in_array((string)$val, ['efetivo', 'temporario', 'part-time', 'estagio', 'freelancer'], true)) {
                                continue;
                            }
                        }

                        $updates[] = "{$col} = ?";
                        $params[] = $val;
                    }

                    if (!empty($updates)) {
                        $params[] = (int)$reqRow['employee_id'];
                        $params[] = $clientId;
                        $sqlApply = 'UPDATE employees SET ' . implode(', ', $updates) . ' WHERE id = ? AND client_id = ?';
                        $stmtApply = $pdo->prepare($sqlApply);
                        $stmtApply->execute($params);
                    }

                    $stmtMark = $pdo->prepare('UPDATE employee_change_requests SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_note = ? WHERE id = ? AND client_id = ?');
                    $stmtMark->execute(['aprovada', (int)($_SESSION['user_id'] ?? 0), $reviewNote, $requestId, $clientId]);

                    try {
                        logActivity(
                            $pdo,
                            $clientId,
                            'Aprovação de alteração crítica: ' . (string)($reqRow['employee_name'] ?? ('#' . $reqRow['employee_id'])),
                            'success',
                            'Aprovação',
                            (int)$reqRow['employee_id']
                        );
                    } catch (Throwable $eLog) {
                    }
                } else {
                    $stmtMark = $pdo->prepare('UPDATE employee_change_requests SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_note = ? WHERE id = ? AND client_id = ?');
                    $stmtMark->execute(['rejeitada', (int)($_SESSION['user_id'] ?? 0), $reviewNote, $requestId, $clientId]);
                }
            }

            $fromSection = trim((string)($_POST['from_section'] ?? ''));
            $feriasActions = ['create_ferias_admin', 'edit_ferias_admin', 'cancel_ferias_admin', 'approve_ferias_request', 'reject_ferias_request'];
            $targetSection = in_array($solicitacaoAction, $feriasActions, true) && ($fromSection === 'ferias' || in_array($solicitacaoAction, ['create_ferias_admin', 'edit_ferias_admin', 'cancel_ferias_admin'], true))
                ? 'ferias'
                : (in_array($solicitacaoAction, ['approve_employee_change_request', 'reject_employee_change_request'], true) ? 'funcionarios' : 'solicitacoes');
            $reviewCode = $solicitacaoAction === 'create_ferias_admin' ? 'created' : 'ok';
            $redirectFinal = 'dashboard.php?section=' . $targetSection . '&review=' . $reviewCode;
            if ($targetSection === 'solicitacoes' && $solicitacaoCard !== '') {
                $redirectFinal .= '&solicitacao_card=' . urlencode($solicitacaoCard);
            }
            header('Location: ' . $redirectFinal);
            exit;
        } catch (Throwable $e) {
            error_log('Erro ao processar solicitacao centralizada: ' . $e->getMessage());
            $fromSectionErr = trim((string)($_POST['from_section'] ?? ''));
            $feriasActionsErr = ['create_ferias_admin', 'edit_ferias_admin', 'cancel_ferias_admin', 'approve_ferias_request', 'reject_ferias_request'];
            $targetSectionError = in_array($solicitacaoAction, $feriasActionsErr, true) && ($fromSectionErr === 'ferias' || in_array($solicitacaoAction, ['create_ferias_admin', 'edit_ferias_admin', 'cancel_ferias_admin'], true))
                ? 'ferias'
                : (in_array($solicitacaoAction, ['approve_employee_change_request', 'reject_employee_change_request'], true) ? 'funcionarios' : 'solicitacoes');
            $redirectError = 'dashboard.php?section=' . $targetSectionError . '&review=error';
            if ($targetSectionError === 'solicitacoes' && $solicitacaoCard !== '') {
                $redirectError .= '&solicitacao_card=' . urlencode($solicitacaoCard);
            }
            header('Location: ' . $redirectError);
            exit;
        }
    }
}

// O utilizador precisa ter sessão admin válida antes de renderizar o dashboard
if (!isset($_SESSION['user_id'])) {
    session_unset();
    session_destroy();
    header("Location: views/login.php?error=sessao_invalida");
    exit();
}

// O utilizador está logado, podemos aceder às suas informações da sessão
$user_id = (int)$_SESSION['user_id'];
$username = (string)($_SESSION['username'] ?? ($_SESSION['fullname'] ?? 'Administrador'));
// Nome exibido da empresa/cliente (usa sessão se disponível, senão fallback)
$company_name = $_SESSION['company_name'] ?? $_SESSION['client_name'] ?? 'Sabor do Neto';
$fullname = $_SESSION['fullname'] ?? 'Utilizador'; // Nome completo do utilizador, com fallback
$specialty = $_SESSION['specialty'] ?? 'Administrador(a)'; // Cargo ou função, com fallback

// **** OBTÉM O CLIENT_ID DO UTILIZADOR LOGADO ****
if (!isset($_SESSION['client_id'])) {
    // Se o client_id não estiver na sessão (o que é crucial para multi-tenancy),
    // é um erro grave ou sessão inválida. Redirecionar para login.
    session_unset();
    session_destroy();
    header("Location: views/login.php?error=sessao_invalida_cliente");
    exit();
}
$loggedInClientId = $_SESSION['client_id']; // ID do cliente do utilizador logado
// ******************************************************

$trialSubscriptionStatus = 'trial';
$trialEndsAtIso = '';
$trialBannerVisible = false;

try {
    $trialStmt = $pdo->prepare("SELECT subscription_status, trial_ends_at FROM usuarios WHERE id = ? LIMIT 1");
    $trialStmt->execute([$user_id]);
    $trialRow = $trialStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$trialRow && $loggedInClientId > 0) {
        $trialStmt = $pdo->prepare("SELECT subscription_status, trial_ends_at FROM clients WHERE id = ? LIMIT 1");
        $trialStmt->execute([$loggedInClientId]);
        $trialRow = $trialStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    if ($trialRow) {
        $trialSubscriptionStatus = trim((string)($trialRow['subscription_status'] ?? 'trial')) ?: 'trial';
        $trialEndsAtValue = trim((string)($trialRow['trial_ends_at'] ?? ''));

        if ($trialEndsAtValue === '') {
            $trialEndsAtValue = date('Y-m-d H:i:s', strtotime('+7 days'));
        }

        $trialEndsAtTimestamp = strtotime($trialEndsAtValue);
        if ($trialEndsAtTimestamp !== false && $trialEndsAtTimestamp > time()) {
            $trialEndsAtIso = date(DATE_ATOM, $trialEndsAtTimestamp);
            $trialBannerVisible = strtolower($trialSubscriptionStatus) === 'trial' || $trialEndsAtValue !== '';
        }
    }
} catch (Throwable $e) {
    error_log('Erro ao carregar trial do dashboard: ' . $e->getMessage());
}

// ===== Configuração de horários do estabelecimento =====
try {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS estabelecimento_horarios (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            hora_abertura TIME NOT NULL DEFAULT '09:00:00',
            hora_encerramento TIME NOT NULL DEFAULT '23:00:00',
            hora_entrada_padrao TIME NOT NULL DEFAULT '09:00:00',
            tolerancia_atraso_min INT NOT NULL DEFAULT 5,
            updated_by INT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_estabelecimento_horarios_client (client_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    if (!$pdo->query("SHOW COLUMNS FROM registros_ponto LIKE 'tipo_dia'")->fetch()) {
        $pdo->exec("ALTER TABLE registros_ponto ADD COLUMN tipo_dia VARCHAR(30) NULL AFTER status");
    }
    if (!$pdo->query("SHOW COLUMNS FROM registros_ponto LIKE 'falta_tipo'")->fetch()) {
        $pdo->exec("ALTER TABLE registros_ponto ADD COLUMN falta_tipo VARCHAR(20) NULL AFTER tipo_dia");
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS employee_change_requests (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            employee_id INT NOT NULL,
            request_type VARCHAR(60) NOT NULL DEFAULT 'critical_update',
            payload_json LONGTEXT NOT NULL,
            reason TEXT NULL,
            status ENUM('pendente','aprovada','rejeitada') NOT NULL DEFAULT 'pendente',
            requested_by INT NULL,
            reviewed_by INT NULL,
            reviewed_at DATETIME NULL,
            review_note TEXT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_ecr_client_status (client_id, status),
            KEY idx_ecr_employee (employee_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    if (!$pdo->query("SHOW COLUMNS FROM employee_documents LIKE 'expiry_date'")->fetch()) {
        $pdo->exec("ALTER TABLE employee_documents ADD COLUMN expiry_date DATE NULL AFTER description");
    }
} catch (Throwable $eHor) {
    error_log('Erro ao preparar estrutura de horários/presença: ' . $eHor->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_estabelecimento_horarios') {
    $horaAbertura = trim((string)($_POST['hora_abertura'] ?? '09:00'));
    $horaEncerramento = trim((string)($_POST['hora_encerramento'] ?? '23:00'));
    $horaEntradaPadrao = trim((string)($_POST['hora_entrada_padrao'] ?? '09:00'));
    $toleranciaAtrasoMin = max(0, min(180, (int)($_POST['tolerancia_atraso_min'] ?? 5)));

    $isValidTime = static function ($value): bool {
        return (bool)preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value);
    };

    if (!$isValidTime($horaAbertura) || !$isValidTime($horaEncerramento) || !$isValidTime($horaEntradaPadrao)) {
        header('Location: dashboard.php?section=definicoes&horarios_saved=0');
        exit;
    }

    try {
        $stmtSaveHorario = $pdo->prepare(
            "INSERT INTO estabelecimento_horarios (client_id, hora_abertura, hora_encerramento, hora_entrada_padrao, tolerancia_atraso_min, updated_by)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
             hora_abertura = VALUES(hora_abertura),
             hora_encerramento = VALUES(hora_encerramento),
             hora_entrada_padrao = VALUES(hora_entrada_padrao),
             tolerancia_atraso_min = VALUES(tolerancia_atraso_min),
             updated_by = VALUES(updated_by),
             updated_at = NOW()"
        );
        $stmtSaveHorario->execute([
            (int)$loggedInClientId,
            $horaAbertura . ':00',
            $horaEncerramento . ':00',
            $horaEntradaPadrao . ':00',
            $toleranciaAtrasoMin,
            isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
        ]);

        header('Location: dashboard.php?section=definicoes&horarios_saved=1');
        exit;
    } catch (Throwable $eSaveHorario) {
        error_log('Erro ao salvar horários do estabelecimento: ' . $eSaveHorario->getMessage());
        header('Location: dashboard.php?section=definicoes&horarios_saved=0');
        exit;
    }
}

$estHorario = [
    'hora_abertura' => '09:00:00',
    'hora_encerramento' => '23:00:00',
    'hora_entrada_padrao' => '09:00:00',
    'tolerancia_atraso_min' => 5,
];
try {
    $stmtEstHorario = $pdo->prepare('SELECT hora_abertura, hora_encerramento, hora_entrada_padrao, tolerancia_atraso_min FROM estabelecimento_horarios WHERE client_id = ? LIMIT 1');
    $stmtEstHorario->execute([(int)$loggedInClientId]);
    $rowEstHorario = $stmtEstHorario->fetch(PDO::FETCH_ASSOC);
    if ($rowEstHorario) {
        $estHorario = array_merge($estHorario, $rowEstHorario);
    }
} catch (Throwable $eLoadHorario) {
    error_log('Erro ao carregar horários do estabelecimento: ' . $eLoadHorario->getMessage());
}

// --- LÊ AS PREFERÊNCIAS DO UTILIZADOR DA SESSÃO ---
$user_preferences = $_SESSION['user_preferences'] ?? [
    'theme' => 'light',
    'profile_picture' => '../assets/images/perfil.png'
];

// Garante que as chaves existam
if (!isset($user_preferences['theme'])) {
    $user_preferences['theme'] = 'light';
}
if (!isset($user_preferences['profile_picture'])) {
    $user_preferences['profile_picture'] = '../assets/images/perfil.png';
}

// Aplica a classe do tema ao body
$body_class = ($user_preferences['theme'] === 'dark') ? 'dark-theme' : '';

// Resolve caminho seguro para a foto do utilizador, independentemente do formato armazenado
$default_profile = '../assets/images/perfil.png';
$raw_profile = $user_preferences['profile_picture'] ?? '';

if (!function_exists('normalize_profile_picture_path')) {
    function normalize_profile_picture_path($path, $default_profile)
    {
        $path = trim((string) $path);

        if ($path === '') {
            return $default_profile;
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        // Garante prefixo relativo à pasta admin
        $normalized = (strpos($path, '../') === 0)
            ? $path
            : '../' . ltrim($path, '/');

        $candidate = realpath(__DIR__ . '/' . $normalized);
        if ($candidate === false || !file_exists($candidate)) {
            $candidate2 = realpath(__DIR__ . '/../' . ltrim($normalized, '/'));
            if ($candidate2 === false || !file_exists($candidate2)) {
                return $default_profile;
            }
        }

        return $normalized;
    }
}

$profile_picture = normalize_profile_picture_path($raw_profile, $default_profile);
$_SESSION['user_preferences']['profile_picture'] = $profile_picture;

// --- ESTATÍSTICAS DO DASHBOARD (FILTRADAS POR CLIENT_ID) ---
try {
    // Funcionários Ativos (conta também quem está de férias — 'ferias' conta como ativo para fins de RH)
    $stmtAtivos = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE client_id = ? AND status IN ('active','ferias')");
    $stmtAtivos->execute([$loggedInClientId]);
    $funcionariosAtivos = $stmtAtivos->fetchColumn();

    // Novas Contratações (último mês) - MANTEM esta contagem por SQL
    $stmtNovasContratacoes = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE client_id = ? AND startDate >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)");
    $stmtNovasContratacoes->execute([$loggedInClientId]);
    $novasContratacoesMes = $stmtNovasContratacoes->fetchColumn();
    $recentHiresCount = $novasContratacoesMes;

    // Dados hardcoded (se não houver tabelas específicas)
    $feriasPendentes = "12";
    $custosSalariaisMes = "€ 55.000";
} catch (PDOException $e) {
    echo "<p style='color: red;'>Erro ao carregar estatísticas: " . $e->getMessage() . "</p>";
    $funcionariosAtivos = "N/A";
    $recentHiresCount = "N/A";
    $novasContratacoesMes = "N/A";
}



// Auto-migração: vacation_days e endDate
try {
    if (!$pdo->query("SHOW COLUMNS FROM employees LIKE 'vacation_days'")->fetch()) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN vacation_days INT NOT NULL DEFAULT 22 AFTER bonus");
    }
    if (!$pdo->query("SHOW COLUMNS FROM employees LIKE 'endDate'")->fetch()) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN endDate DATE NULL AFTER startDate");
    }
} catch (Throwable $e) {
    error_log('migrate employees vacation_days/endDate: ' . $e->getMessage());
}

// Reposição automática de status: funcionários marcados como 'ferias' sem um
// período aprovado em curso hoje voltam a 'ativo' (cobre férias terminadas e
// aprovações futuras que não devem refletir 'ferias' antes da data de início).
try {
    $checkFeriasTableAutoExp = $pdo->query("SHOW TABLES LIKE 'ferias'");
    if ($checkFeriasTableAutoExp && $checkFeriasTableAutoExp->rowCount() > 0) {
        $feriasColsAutoExp = $pdo->query('SHOW COLUMNS FROM ferias')->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $feriasColsAutoExp = array_map(static fn($c) => mb_strtolower((string)$c), $feriasColsAutoExp);
        $feriasEmployeeColAutoExp = in_array('funcionario_id', $feriasColsAutoExp, true)
            ? 'funcionario_id'
            : (in_array('employee_id', $feriasColsAutoExp, true) ? 'employee_id' : 'funcionario_id');
        $todayIsoAutoExp = date('Y-m-d');
        $pdo->prepare("UPDATE employees e
                SET e.status = 'ativo'
                WHERE e.client_id = ? AND e.status = 'ferias'
                  AND NOT EXISTS (
                      SELECT 1 FROM ferias f
                      WHERE f.{$feriasEmployeeColAutoExp} = e.id
                        AND LOWER(COALESCE(f.status, '')) IN ('aprovada', 'aprovado')
                        AND f.data_inicio <= ? AND f.data_fim >= ?
                  )")
            ->execute([$loggedInClientId, $todayIsoAutoExp, $todayIsoAutoExp]);
    }
} catch (Throwable $e) {
    error_log('Auto-reposição de status de férias: ' . $e->getMessage());
}

// **** CARREGAR FUNCIONÁRIOS DA BASE DE DADOS FILTRANDO PELO CLIENT_ID ****
try {
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE client_id = ? ORDER BY name ASC");
    $stmt->execute([$loggedInClientId]);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $employees = [];
    echo "<p style='color: red;'>Erro ao carregar funcionários: " . $e->getMessage() . "</p>";
    error_log("Erro no dashboard.php (carregar funcionários): " . $e->getMessage());
}
// **** FIM DO CARREGAMENTO DE FUNCIONÁRIOS ****

// **** CARREGAR PRESENÇAS ****
$presencas = [];
try {
    // Check if registros_ponto has client_id for a safe JOIN
    $rpHasClientId = false;
    try { $rpHasClientId = (bool)$pdo->query("SHOW COLUMNS FROM registros_ponto LIKE 'client_id'")->fetch(); } catch (Exception $_e) {}
    $rpJoinExtra = $rpHasClientId ? ' AND rp.client_id = p.client_id' : '';

    $stmt = $pdo->prepare("
        SELECT p.*, e.name, COALESCE(e.profile_picture,'') AS profile_picture,
               GROUP_CONCAT(
                   CONCAT_WS('|',
                       COALESCE(rp.hora_entrada,''),
                       COALESCE(rp.hora_saida,''),
                       COALESCE(rp.observacao, rp.obs, '')
                   ) ORDER BY rp.id SEPARATOR ';;'
               ) AS ponto_timeline
        FROM presencas p
        INNER JOIN employees e ON e.id = p.funcionario_id
        LEFT JOIN registros_ponto rp ON rp.funcionario_id = p.funcionario_id
            AND rp.data_registro = p.data_registro$rpJoinExtra
        WHERE p.client_id = ?
        GROUP BY p.id
        ORDER BY p.data_registro DESC
        LIMIT 100
    ");
    $stmt->execute([$loggedInClientId]);
    $presencas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $presencas = [];
}

// **** CARREGAR TURNOS ****
$turnos = [];
try {
    $stmt = $pdo->prepare("SELECT t.*, e.name FROM turnos t 
        INNER JOIN employees e ON e.id = t.funcionario_id 
        WHERE e.client_id = ? 
        ORDER BY t.created_at DESC 
        LIMIT 100");
    $stmt->execute([$loggedInClientId]);
    $turnos = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $turnos = [];
}

// **** CARREGAR GORJETAS ****
$gorjetas = [];
try {
    $stmt = $pdo->prepare("SELECT g.*, e.name FROM gorjetas g 
        INNER JOIN employees e ON e.id = g.funcionario_id 
        WHERE g.client_id = ? 
        ORDER BY g.data DESC 
        LIMIT 100");
    $stmt->execute([$loggedInClientId]);
    $gorjetas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $gorjetas = [];
}

// **** CARREGAR FOLHA DE PAGAMENTO ****
$folhaPagamento = [];
try {
    $currentMonth = date('n');
    $currentYear = date('Y');
    $stmt = $pdo->prepare("SELECT f.*, e.name FROM folha_pagamento f 
        INNER JOIN employees e ON e.id = f.employee_id 
        WHERE f.client_id = ? AND f.fiscal_year = ? AND f.fiscal_month = ? 
        ORDER BY e.name ASC");
    $stmt->execute([$loggedInClientId, $currentYear, $currentMonth]);
    $folhaPagamento = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $folhaPagamento = [];
}

// **** ENRIQUECER DADOS REAIS PARA O RELATÓRIO RESUMIDO ****
$reportMonth = (int)date('n');
$reportYear = (int)date('Y');
$horasPorFuncionario = [];
$faltasPorFuncionario = [];
$faltasVariaveisPorFuncionario = [];
$gorjetasPorFuncionario = [];
$folhaPorFuncionario = [];

foreach ($folhaPagamento as $folhaRow) {
    $empIdFolha = (int)($folhaRow['employee_id'] ?? 0);
    if ($empIdFolha > 0) {
        $folhaPorFuncionario[$empIdFolha] = $folhaRow;
    }
}

try {
    $stmtHoras = $pdo->prepare("SELECT
            rp.funcionario_id,
            ROUND(SUM(
                CASE
                    WHEN rp.hora_entrada IS NOT NULL
                         AND rp.hora_saida IS NOT NULL
                         AND EXISTS (
                            SELECT 1
                            FROM turnos t
                            WHERE t.funcionario_id = rp.funcionario_id
                              AND LOWER(COALESCE(t.status, 'ativo')) = 'ativo'
                              AND (t.data_inicio IS NULL OR DATE(rp.data_registro) >= DATE(t.data_inicio))
                              AND (t.data_fim IS NULL OR DATE(rp.data_registro) <= DATE(t.data_fim))
                              AND (
                                  COALESCE(TRIM(t.dias_semana), '') = ''
                                  OR LOWER(t.dias_semana) LIKE
                                      CASE DAYOFWEEK(rp.data_registro)
                                          WHEN 1 THEN '%dom%'
                                          WHEN 2 THEN '%seg%'
                                          WHEN 3 THEN '%ter%'
                                          WHEN 4 THEN '%qua%'
                                          WHEN 5 THEN '%qui%'
                                          WHEN 6 THEN '%sex%'
                                          WHEN 7 THEN '%sab%'
                                      END
                              )
                         ) THEN
                        TIMESTAMPDIFF(
                            MINUTE,
                            CONCAT(rp.data_registro, ' ', rp.hora_entrada),
                            CASE
                                WHEN rp.hora_saida < rp.hora_entrada
                                    THEN DATE_ADD(CONCAT(rp.data_registro, ' ', rp.hora_saida), INTERVAL 1 DAY)
                                ELSE CONCAT(rp.data_registro, ' ', rp.hora_saida)
                            END
                        )
                    ELSE 0
                END
            ) / 60, 2) AS horas_total
        FROM registros_ponto rp
        WHERE rp.client_id = ? AND YEAR(rp.data_registro) = ? AND MONTH(rp.data_registro) = ?
        GROUP BY rp.funcionario_id");
    $stmtHoras->execute([$loggedInClientId, $reportYear, $reportMonth]);
    foreach (($stmtHoras->fetchAll(PDO::FETCH_ASSOC) ?: []) as $rowHoras) {
        $horasPorFuncionario[(int)$rowHoras['funcionario_id']] = (float)($rowHoras['horas_total'] ?? 0);
    }
} catch (Throwable $e) {
    $horasPorFuncionario = [];
}

try {
    // Faltas = dias de ausência SEM justificativa aprovada
    $stmtFaltasMes = $pdo->prepare("SELECT
            faltas_union.funcionario_id,
            COUNT(DISTINCT faltas_union.dia_falta) AS faltas_total
        FROM (
            SELECT
                p.funcionario_id,
                DATE(p.data_registro) AS dia_falta
            FROM presencas p
            INNER JOIN employees e ON e.id = p.funcionario_id
            WHERE e.client_id = ?
              AND YEAR(p.data_registro) = ?
              AND MONTH(p.data_registro) = ?
              AND LOWER(COALESCE(p.status, '')) IN ('falta', 'ausente')
              AND NOT EXISTS (
                  SELECT 1 FROM justificativas_presenca j
                  WHERE j.employee_id = p.funcionario_id
                    AND j.data_ocorrencia = DATE(p.data_registro)
                    AND j.client_id = e.client_id
                    AND LOWER(j.status) = 'aprovada'
              )

            UNION ALL

            SELECT
                rp.funcionario_id,
                DATE(rp.data_registro) AS dia_falta
            FROM registros_ponto rp
            INNER JOIN employees e2 ON e2.id = rp.funcionario_id
            WHERE e2.client_id = ?
              AND YEAR(rp.data_registro) = ?
              AND MONTH(rp.data_registro) = ?
              AND (
                    LOWER(COALESCE(rp.status, '')) IN ('falta', 'ausente')
                 OR LOWER(COALESCE(rp.tipo_dia, '')) IN ('falta', 'ausente')
                 OR COALESCE(TRIM(rp.falta_tipo), '') <> ''
              )
              AND NOT EXISTS (
                  SELECT 1 FROM justificativas_presenca j2
                  WHERE j2.employee_id = rp.funcionario_id
                    AND j2.data_ocorrencia = DATE(rp.data_registro)
                    AND j2.client_id = e2.client_id
                    AND LOWER(j2.status) = 'aprovada'
              )
        ) AS faltas_union
        GROUP BY faltas_union.funcionario_id");
    $stmtFaltasMes->execute([
        $loggedInClientId, $reportYear, $reportMonth,
        $loggedInClientId, $reportYear, $reportMonth
    ]);
    foreach (($stmtFaltasMes->fetchAll(PDO::FETCH_ASSOC) ?: []) as $rowFaltas) {
        $faltasPorFuncionario[(int)$rowFaltas['funcionario_id']] = (int)($rowFaltas['faltas_total'] ?? 0);
    }
} catch (Throwable $e) {
    $faltasPorFuncionario = [];
}

try {
    $stmtFaltasVariaveis = $pdo->prepare("SELECT
            employee_id AS funcionario_id,
            COALESCE(faltas_dias, 0) AS faltas_dias
        FROM folha_variaveis_mensais
        WHERE client_id = ? AND fiscal_year = ? AND fiscal_month = ?");
    $stmtFaltasVariaveis->execute([$loggedInClientId, $reportYear, $reportMonth]);
    foreach (($stmtFaltasVariaveis->fetchAll(PDO::FETCH_ASSOC) ?: []) as $rowVarFalta) {
        $faltasVariaveisPorFuncionario[(int)$rowVarFalta['funcionario_id']] = (float)($rowVarFalta['faltas_dias'] ?? 0);
    }
} catch (Throwable $e) {
    $faltasVariaveisPorFuncionario = [];
}

try {
    // Apenas gorjetas com status 'pago' são consideradas rendimento efectivo
    $stmtGorjetasMes = $pdo->prepare("SELECT
            funcionario_id,
            ROUND(SUM(COALESCE(valor, 0)), 2) AS gorjetas_total
        FROM gorjetas
        WHERE client_id = ?
          AND YEAR(COALESCE(data, created_at)) = ?
          AND MONTH(COALESCE(data, created_at)) = ?
          AND LOWER(COALESCE(status, '')) = 'pago'
        GROUP BY funcionario_id");
    $stmtGorjetasMes->execute([$loggedInClientId, $reportYear, $reportMonth]);
    foreach (($stmtGorjetasMes->fetchAll(PDO::FETCH_ASSOC) ?: []) as $rowGorjetas) {
        $gorjetasPorFuncionario[(int)$rowGorjetas['funcionario_id']] = (float)($rowGorjetas['gorjetas_total'] ?? 0);
    }
} catch (Throwable $e) {
    $gorjetasPorFuncionario = [];
}

foreach ($employees as &$emp) {
    $empId = (int)($emp['id'] ?? 0);
    $folhaEmp = $folhaPorFuncionario[$empId] ?? null;

    $salarioBaseReal = (float)($folhaEmp['salario_base'] ?? ($emp['salary_base'] ?? 0));
    $gorjetasReais = (float)($gorjetasPorFuncionario[$empId] ?? ($folhaEmp['gorjetas'] ?? 0));
    $horasReais = (float)($horasPorFuncionario[$empId] ?? 0);
    $faltasRegistos = array_key_exists($empId, $faltasPorFuncionario) ? (float)$faltasPorFuncionario[$empId] : null;
    $faltasFolha = (float)($folhaEmp['faltas_dias'] ?? 0);
    $faltasVariaveis = (float)($faltasVariaveisPorFuncionario[$empId] ?? 0);
    $faltasReaisBase = $faltasRegistos;
    if ($faltasReaisBase === null || $faltasReaisBase <= 0) {
        $faltasReaisBase = max($faltasFolha, $faltasVariaveis, 0);
    }
    $faltasReais = (int)round($faltasReaisBase);
    $totalLiquidoReal = (float)($folhaEmp['salario_liquido'] ?? 0);

    if ($totalLiquidoReal <= 0) {
        $totalLiquidoReal = max(0, $salarioBaseReal + $gorjetasReais);
    }

    $emp['rel_horas_trabalhadas'] = round($horasReais, 2);
    $emp['rel_faltas'] = $faltasReais;
    $emp['rel_salary_base'] = round($salarioBaseReal, 2);
    $emp['rel_gorjetas'] = round($gorjetasReais, 2);
    $emp['rel_total_liquido'] = round($totalLiquidoReal, 2);
}
unset($emp);

// **** VALIDAÇÃO DE DADOS PARA RELATÓRIOS ****
$relatoriosValidacao = [
    'avisos' => [],
    'dados_ok' => true
];

try {
    // Verificar registos de ponto este mês
    $stmtValidRP = $pdo->prepare("SELECT COUNT(*) FROM registros_ponto WHERE client_id = ? AND YEAR(data_registro) = ? AND MONTH(data_registro) = ?");
    $stmtValidRP->execute([$loggedInClientId, $reportYear, $reportMonth]);
    $countRP = (int)$stmtValidRP->fetchColumn();
    if ($countRP === 0) {
        $relatoriosValidacao['avisos'][] = "⚠️ Sem registos de ponto em " . ($mesesPt[$reportMonth] ?? 'Mês') . " — verifique se os colaboradores marcam entrada/saída.";
        $relatoriosValidacao['dados_ok'] = false;
    }

    // Verificar presenças este mês
    $stmtValidPres = $pdo->prepare("SELECT COUNT(*) FROM presencas WHERE client_id = ? AND YEAR(data_registro) = ? AND MONTH(data_registro) = ?");
    $stmtValidPres->execute([$loggedInClientId, $reportYear, $reportMonth]);
    $countPres = (int)$stmtValidPres->fetchColumn();
    if ($countPres === 0) {
        $relatoriosValidacao['avisos'][] = "⚠️ Sem registo de presenças em " . ($mesesPt[$reportMonth] ?? 'Mês') . " — verifique o sistema de marcação de presença.";
        $relatoriosValidacao['dados_ok'] = false;
    }

    // Verificar gorjetas com status 'pago' este mês
    $stmtValidGor = $pdo->prepare("SELECT COUNT(*) FROM gorjetas WHERE client_id = ? AND YEAR(COALESCE(data, created_at)) = ? AND MONTH(COALESCE(data, created_at)) = ? AND LOWER(COALESCE(status, '')) = 'pago'");
    $stmtValidGor->execute([$loggedInClientId, $reportYear, $reportMonth]);
    $countGorPago = (int)$stmtValidGor->fetchColumn();
    
    $stmtValidGorTotal = $pdo->prepare("SELECT COUNT(*) FROM gorjetas WHERE client_id = ? AND YEAR(COALESCE(data, created_at)) = ? AND MONTH(COALESCE(data, created_at)) = ?");
    $stmtValidGorTotal->execute([$loggedInClientId, $reportYear, $reportMonth]);
    $countGorTotal = (int)$stmtValidGorTotal->fetchColumn();
    
    if ($countGorTotal > 0 && $countGorPago === 0) {
        $relatoriosValidacao['avisos'][] = "ℹ️ Gorjetas pendentes sem processar em " . ($mesesPt[$reportMonth] ?? 'Mês') . " — verifique o estado das gorjetas (podem estar 'pendente', 'confirmado' ou 'rejeitado').";
    }

    // Verificar justificativas aprovadas este mês
    $stmtValidJust = $pdo->prepare("SELECT COUNT(*) FROM justificativas_presenca WHERE client_id = ? AND YEAR(data_ocorrencia) = ? AND MONTH(data_ocorrencia) = ? AND LOWER(status) = 'aprovada'");
    $stmtValidJust->execute([$loggedInClientId, $reportYear, $reportMonth]);
    $countJustAprov = (int)$stmtValidJust->fetchColumn();
    if ($countJustAprov === 0 && $countPres > 0) {
        $relatoriosValidacao['avisos'][] = "ℹ️ Sem justificativas aprovadas em " . ($mesesPt[$reportMonth] ?? 'Mês') . " — faltas podem estar a ser contabilizadas integralmente.";
    }

    // Verificar turnos ativos
    $stmtValidTurnos = $pdo->prepare("SELECT COUNT(*) FROM turnos WHERE client_id = ? AND LOWER(COALESCE(status, '')) = 'ativo'");
    $stmtValidTurnos->execute([$loggedInClientId]);
    $countTurnosAtivos = (int)$stmtValidTurnos->fetchColumn();
    if ($countTurnosAtivos === 0 && count($employees) > 0) {
        $relatoriosValidacao['avisos'][] = "⚠️ Sem turnos ativos — verifique se todos os colaboradores têm turnos configurados e ativos.";
        $relatoriosValidacao['dados_ok'] = false;
    }
} catch (Throwable $e) {
    error_log("Erro na validação de dados para relatórios: " . $e->getMessage());
}

// **** CÁLCULO DA FOLHA DE PAGAMENTO (PORTUGAL) POR PERÍODO MENSAL ****
$folhaCalculos = [];
$folhaResumo = [
    'custo_total' => 0.0,
    'funcionarios_pagos' => 0,
    'pendencias' => 0,
    'total_funcionarios' => 0,
];
$folhaFiscalYear = isset($_GET['folha_ano']) ? (int)$_GET['folha_ano'] : (int)date('Y');
$folhaFiscalMonth = isset($_GET['folha_mes']) ? (int)$_GET['folha_mes'] : (int)date('n');
$folhaFiscalMonth = max(1, min(12, $folhaFiscalMonth));
$currentYear = (int)date('Y');
$folhaFiscalYear = max($currentYear - 5, min($currentYear + 1, $folhaFiscalYear));
$mesesPt = [1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Marco', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'];
$folhaPeriodoLabel = ($mesesPt[$folhaFiscalMonth] ?? 'Mes') . ' ' . $folhaFiscalYear;
$folhaFechada = false;
$payrollConfigYear = isset($_GET['config_ano']) ? (int)$_GET['config_ano'] : $folhaFiscalYear;
$payrollConfigYear = max($currentYear - 5, min($currentYear + 1, $payrollConfigYear));
$payrollConfigSaveAttempted = isset($_GET['payroll_config_saved']);
$payrollConfigSaved = isset($_GET['payroll_config_saved']) && $_GET['payroll_config_saved'] === '1';
$payrollAdminConfig = [
    'default_subsidios'   => 0.0,
    'default_horas_extra' => 1.0,   // fator (1.25 = 25% acima)
    'default_bonus'       => 0.0,
];
$payrollAdminTaxRules = [
    'social_security_rate'          => 0.0,
    'employer_social_security_rate' => 0.0,
    'brackets'                      => [],
];

if (!function_exists('getPayrollGorjetaDateColumn')) {
    function getPayrollGorjetaDateColumn(PDO $pdo): string
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
        } catch (Throwable $e) {
            error_log('Erro ao detectar coluna de data das gorjetas: ' . $e->getMessage());
        }

        $column = 'data';
        return $column;
    }
}

if (!function_exists('getConfirmedGorjetasForPeriod')) {
    function getConfirmedGorjetasForPeriod(PDO $pdo, int $clientId, int $fiscalYear, int $fiscalMonth): array
    {
        $result = [
            'by_employee' => [],
            'total' => 0.0,
        ];

        if (!payrollTableExists($pdo, 'gorjetas')) {
            return $result;
        }

        $employeeColumn = payrollColumnExists($pdo, 'gorjetas', 'funcionario_id')
            ? 'funcionario_id'
            : (payrollColumnExists($pdo, 'gorjetas', 'employee_id') ? 'employee_id' : null);
        if ($employeeColumn === null) {
            return $result;
        }

        $statusFilter = '';
        if (payrollColumnExists($pdo, 'gorjetas', 'status')) {
            // Incluir "pendente" para refletir imediatamente gorjetas recém-registradas na folha.
            // Status rejeitados/cancelados continuam fora do cálculo por não estarem nesta lista.
            $statusFilter = " AND LOWER(TRIM(COALESCE(status, ''))) IN ('pago', 'paid', 'confirmado', 'aprovado', 'pendente') ";
        }

        $dateCandidates = [];
        foreach (['data_registro', 'data', 'created_at'] as $candidate) {
            if (payrollColumnExists($pdo, 'gorjetas', $candidate)) {
                $dateCandidates[] = $candidate;
            }
        }

        $dateSelect = '';
        if (!empty($dateCandidates)) {
            $dateCols = array_map(static fn($col) => "{$col} AS {$col}", $dateCandidates);
            $dateSelect = ', ' . implode(', ', $dateCols);
        }

        $sql = "SELECT {$employeeColumn} AS employee_id, valor {$dateSelect}
                FROM gorjetas
                WHERE client_id = ? {$statusFilter}";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$clientId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $employeeId = (int)($row['employee_id'] ?? 0);
                $valor = round((float)($row['valor'] ?? 0), 2);
                if ($employeeId <= 0 || $valor <= 0) {
                    continue;
                }

                $dateTime = null;
                foreach ($dateCandidates as $candidate) {
                    $candidateRaw = trim((string)($row[$candidate] ?? ''));
                    if ($candidateRaw === '') {
                        continue;
                    }

                    $parsedDate = DateTime::createFromFormat('Y-m-d H:i:s', $candidateRaw)
                        ?: DateTime::createFromFormat('Y-m-d', $candidateRaw)
                        ?: DateTime::createFromFormat('d/m/Y H:i:s', $candidateRaw)
                        ?: DateTime::createFromFormat('d/m/Y', $candidateRaw)
                        ?: DateTime::createFromFormat('d-m-Y H:i:s', $candidateRaw)
                        ?: DateTime::createFromFormat('d-m-Y', $candidateRaw);

                    if (!$parsedDate) {
                        $ts = strtotime($candidateRaw);
                        if ($ts !== false) {
                            $parsedDate = (new DateTime())->setTimestamp($ts);
                        }
                    }

                    if (!$parsedDate) {
                        continue;
                    }

                    $parsedYear = (int)$parsedDate->format('Y');
                    // Ignora datas corrompidas (ex.: -0001) e tenta próxima coluna de data.
                    if ($parsedYear < 2000 || $parsedYear > 2100) {
                        continue;
                    }

                    $dateTime = $parsedDate;
                    break;
                }

                // Sem data válida, não conseguimos atribuir ao período fiscal com segurança.
                if (!$dateTime) {
                    continue;
                }

                $rowYear = (int)$dateTime->format('Y');
                $rowMonth = (int)$dateTime->format('n');
                if ($rowYear !== $fiscalYear || $rowMonth !== $fiscalMonth) {
                    continue;
                }

                if (!isset($result['by_employee'][$employeeId])) {
                    $result['by_employee'][$employeeId] = 0.0;
                }
                $result['by_employee'][$employeeId] += $valor;
                $result['total'] += $valor;
            }
            foreach ($result['by_employee'] as $empId => $sumValor) {
                $result['by_employee'][$empId] = round((float)$sumValor, 2);
            }
            $result['total'] = round((float)$result['total'], 2);
        } catch (Throwable $e) {
            error_log('Erro ao carregar gorjetas confirmadas do período: ' . $e->getMessage());
        }

        return $result;
    }
}

if (!function_exists('syncPayrollRowsForPeriod')) {
    function syncPayrollRowsForPeriod(PDO $pdo, array $employees, int $clientId, int $fiscalYear, int $fiscalMonth, ?int $userId = null): int
    {
        if (empty($employees)) {
            return 0;
        }

        $gorjetaManualColExists = payrollColumnExists($pdo, 'folha_variaveis_mensais', 'gorjeta_manual');
        $varSelectCols = 'employee_id, horas_extra, faltas_dias, bonus, subsidios_extra, outros_descontos, status, is_locked'
            . ($gorjetaManualColExists ? ', gorjeta_manual' : '');
        $stmtVars = $pdo->prepare(
            "SELECT {$varSelectCols}
             FROM folha_variaveis_mensais
             WHERE client_id = ? AND fiscal_year = ? AND fiscal_month = ?"
        );
        $stmtVars->execute([$clientId, $fiscalYear, $fiscalMonth]);
        $variaveisMensais = [];
        foreach ($stmtVars->fetchAll(PDO::FETCH_ASSOC) as $varRow) {
            $variaveisMensais[(int)$varRow['employee_id']] = $varRow;
        }

        $folhaTaxRules = obterRegrasFiscais($pdo, $fiscalYear);
        $folhaConfigDefaults = obterConfiguracaoFolha($pdo, $clientId, $fiscalYear);
        $confirmedGorjetasPeriod = getConfirmedGorjetasForPeriod($pdo, $clientId, $fiscalYear, $fiscalMonth);
        $confirmedGorjetasByEmployee = $confirmedGorjetasPeriod['by_employee'] ?? [];

        $statusPagamentoExistente = [];
        if (payrollTableExists($pdo, 'folha_pagamento') && payrollColumnExists($pdo, 'folha_pagamento', 'status_pagamento')) {
            $stmtSP = $pdo->prepare(
                "SELECT employee_id, status_pagamento, data_pagamento
                 FROM folha_pagamento
                 WHERE client_id = ? AND fiscal_year = ? AND fiscal_month = ?"
            );
            $stmtSP->execute([$clientId, $fiscalYear, $fiscalMonth]);
            foreach ($stmtSP->fetchAll(PDO::FETCH_ASSOC) as $spRow) {
                $statusPagamentoExistente[(int)$spRow['employee_id']] = [
                    'status_pagamento' => $spRow['status_pagamento'] ?? 'pendente',
                    'data_pagamento'   => $spRow['data_pagamento'],
                ];
            }
        }

        $gorjetasAutoSplitAtivo = (int)($folhaConfigDefaults['gorjetas_auto_split'] ?? 0) === 1;
        $gorjetasTotalMes = (float)($folhaConfigDefaults['gorjetas_total_mes'] ?? 0.0);
        $numAtivosParaGorjeta = 0;
        if ($gorjetasAutoSplitAtivo) {
            foreach ($employees as $_e) {
                $st = mb_strtolower(trim((string)($_e['status'] ?? '')));
                if (in_array($st, ['active', 'ativo', 'ativa'], true)) {
                    $numAtivosParaGorjeta++;
                }
            }
        }
        $gorjetaAutoValor = ($gorjetasAutoSplitAtivo && $numAtivosParaGorjeta > 0)
            ? round($gorjetasTotalMes / $numAtivosParaGorjeta, 2)
            : 0.0;

        $syncedRows = 0;
        foreach ($employees as $employee) {
            $employeeId = (int)($employee['id'] ?? 0);
            if ($employeeId <= 0) {
                continue;
            }

            $vars = $variaveisMensais[$employeeId] ?? [
                'horas_extra' => 0,
                'faltas_dias' => 0,
                'bonus' => 0,
                'subsidios_extra' => 0,
                'outros_descontos' => 0,
                'gorjeta_manual' => 0,
                'status' => 'ativo',
                'is_locked' => 0,
            ];

            $salarioBase = (float)($employee['salary_base'] ?? 0);
            $faltasDias = 0.0;
            $descontoFaltas = 0.0;

            if ($gorjetasAutoSplitAtivo) {
                $empSt = mb_strtolower(trim((string)($employee['status'] ?? '')));
                $gorjetaFuncionario = (float)($confirmedGorjetasByEmployee[$employeeId] ?? 0.0)
                    + (in_array($empSt, ['active', 'ativo', 'ativa'], true)
                        ? $gorjetaAutoValor
                        : 0.0);
            } else {
                $gorjetaFuncionario = (float)($confirmedGorjetasByEmployee[$employeeId] ?? 0.0)
                    + max(0.0, (float)($vars['gorjeta_manual'] ?? 0.0));
            }

            $dadosFuncionario = [
                'salario_base'          => $salarioBase,
                'subsidio_alimentacao'  => (float)($employee['subsidio_alimentacao'] ?? 0) + (float)($vars['subsidios_extra'] ?? 0) + (float)($folhaConfigDefaults['default_subsidios'] ?? 0),
                'subsidios_tributaveis' => 0.0,
                'horas_extra'           => (float)($vars['horas_extra'] ?? 0) * max(1.0, (float)($folhaConfigDefaults['default_horas_extra'] ?? 1.0)),
                'bonus'                 => (float)($employee['bonus'] ?? 0) + (float)($vars['bonus'] ?? 0) + (float)($folhaConfigDefaults['default_bonus'] ?? 0),
                'gorjetas'              => $gorjetaFuncionario,
            ];

            $taxRulesForEmployee = obterSnapshotRegrasFolha(
                $pdo,
                $clientId,
                $employeeId,
                $fiscalYear,
                $fiscalMonth,
                $folhaTaxRules
            );

            $resultadoFolha = calcularFolhaPagamento($dadosFuncionario, $taxRulesForEmployee);
            $outrosDescontos = 0.0;
            $resultadoFolha['faltas_dias'] = round($faltasDias, 2);
            $resultadoFolha['desconto_faltas'] = round($descontoFaltas, 2);
            $resultadoFolha['outros_descontos'] = round($outrosDescontos, 2);
            $resultadoFolha['horas_extra_mensal'] = round((float)($vars['horas_extra'] ?? 0), 2);
            $resultadoFolha['bonus_mensal'] = round((float)($vars['bonus'] ?? 0), 2);
            $resultadoFolha['subsidios_extra'] = round((float)($vars['subsidios_extra'] ?? 0), 2);
            $resultadoFolha['gorjeta_manual'] = round((float)($vars['gorjeta_manual'] ?? 0), 2);
            $resultadoFolha['total_descontos'] = 0.0;
            $resultadoFolha['salario_liquido'] = round((float)$resultadoFolha['salario_bruto'], 2);
            $resultadoFolha['status_folha'] = (string)($vars['status'] ?? 'ativo');
            $resultadoFolha['is_locked'] = (int)($vars['is_locked'] ?? 0);
            $resultadoFolha['fiscal_month'] = $fiscalMonth;
            $resultadoFolha['fiscal_year'] = $fiscalYear;
            $resultadoFolha['status_pagamento'] = $statusPagamentoExistente[$employeeId]['status_pagamento'] ?? 'pendente';
            $resultadoFolha['data_pagamento'] = $statusPagamentoExistente[$employeeId]['data_pagamento'] ?? null;

            upsertFolhaPagamento($pdo, $clientId, $employeeId, $fiscalYear, $fiscalMonth, $resultadoFolha);
            $syncedRows++;
        }

        return $syncedRows;
    }
}

try {
    // Estrutura preparada para variáveis mensais e fecho mensal.
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS folha_variaveis_mensais (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            employee_id INT NOT NULL,
            fiscal_year INT NOT NULL,
            fiscal_month TINYINT NOT NULL,
            horas_extra DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            faltas_dias DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            bonus DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            subsidios_extra DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            outros_descontos DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status VARCHAR(20) NOT NULL DEFAULT 'ativo',
            is_locked TINYINT(1) NOT NULL DEFAULT 0,
            updated_by INT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_folha_variaveis (client_id, employee_id, fiscal_year, fiscal_month),
            KEY idx_folha_variaveis_period (client_id, fiscal_year, fiscal_month)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS folha_fechamentos_mensais (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            fiscal_year INT NOT NULL,
            fiscal_month TINYINT NOT NULL,
            is_closed TINYINT(1) NOT NULL DEFAULT 0,
            closed_by INT NULL,
            closed_at TIMESTAMP NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_folha_fechamento (client_id, fiscal_year, fiscal_month)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    ensurePayrollSettingsTable($pdo);

    // Migração idempotente: coluna gorjeta_manual em folha_variaveis_mensais
    try {
        if (!payrollColumnExists($pdo, 'folha_variaveis_mensais', 'gorjeta_manual')) {
            $pdo->exec("ALTER TABLE folha_variaveis_mensais ADD COLUMN gorjeta_manual DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER outros_descontos");
        }
    } catch (Throwable $eMig) {
        error_log('Migração gorjeta_manual: ' . $eMig->getMessage());
    }

    // Inicialização automática do período mensal:
    // cria um ciclo limpo e independente quando o mês ainda não tem dados.
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmtVarsCount = $pdo->prepare(
            "SELECT COUNT(*)
             FROM folha_variaveis_mensais
             WHERE client_id = ? AND fiscal_year = ? AND fiscal_month = ?"
        );
        $stmtVarsCount->execute([(int)$loggedInClientId, $folhaFiscalYear, $folhaFiscalMonth]);
        $varsCountCurrentMonth = (int)$stmtVarsCount->fetchColumn();

        $payrollCountCurrentMonth = 0;
        if (payrollTableExists($pdo, 'folha_pagamento')) {
            $stmtPayrollCount = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM folha_pagamento
                 WHERE client_id = ? AND fiscal_year = ? AND fiscal_month = ?"
            );
            $stmtPayrollCount->execute([(int)$loggedInClientId, $folhaFiscalYear, $folhaFiscalMonth]);
            $payrollCountCurrentMonth = (int)$stmtPayrollCount->fetchColumn();
        }

        $stmtCloseCheck = $pdo->prepare(
            "SELECT is_closed
             FROM folha_fechamentos_mensais
             WHERE client_id = ? AND fiscal_year = ? AND fiscal_month = ?
             LIMIT 1"
        );
        $stmtCloseCheck->execute([(int)$loggedInClientId, $folhaFiscalYear, $folhaFiscalMonth]);
        $isClosedCurrentMonth = (int)$stmtCloseCheck->fetchColumn() === 1;

        $isNewMonthlyCycle = !$isClosedCurrentMonth && $varsCountCurrentMonth === 0 && $payrollCountCurrentMonth === 0;

        if ($isNewMonthlyCycle && !empty($employees)) {
            $pdo->beginTransaction();
            try {
                $seedVarsStmt = $pdo->prepare(
                    "INSERT INTO folha_variaveis_mensais
                    (client_id, employee_id, fiscal_year, fiscal_month, horas_extra, faltas_dias, bonus, subsidios_extra, outros_descontos, gorjeta_manual, status, is_locked, updated_by)
                    VALUES (?, ?, ?, ?, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'ativo', 0, ?)
                    ON DUPLICATE KEY UPDATE
                    horas_extra = VALUES(horas_extra),
                    faltas_dias = VALUES(faltas_dias),
                    gorjeta_manual = VALUES(gorjeta_manual),
                    updated_by = VALUES(updated_by),
                    updated_at = NOW()"
                );

                foreach ($employees as $employee) {
                    $seedEmployeeId = (int)($employee['id'] ?? 0);
                    if ($seedEmployeeId <= 0) {
                        continue;
                    }
                    $seedVarsStmt->execute([
                        (int)$loggedInClientId,
                        $seedEmployeeId,
                        $folhaFiscalYear,
                        $folhaFiscalMonth,
                        isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
                    ]);
                }

                // Garantia explícita para novo ciclo mensal:
                // gorjetas=0, faltas=0 e status de pagamento pendente (quando houver registos no mês).
                if (payrollTableExists($pdo, 'folha_pagamento') && payrollColumnExists($pdo, 'folha_pagamento', 'status_pagamento')) {
                    $resetStatusStmt = $pdo->prepare(
                        "UPDATE folha_pagamento
                         SET status_pagamento = 'pendente', data_pagamento = NULL, updated_at = NOW()
                         WHERE client_id = ? AND fiscal_year = ? AND fiscal_month = ?"
                    );
                    $resetStatusStmt->execute([(int)$loggedInClientId, $folhaFiscalYear, $folhaFiscalMonth]);
                }

                // Em modo automático, o total mensal de gorjetas não deve herdar do ciclo anterior.
                if (payrollTableExists($pdo, 'payroll_settings') && payrollColumnExists($pdo, 'payroll_settings', 'gorjetas_total_mes')) {
                    $resetGorjetasMesStmt = $pdo->prepare(
                        "UPDATE payroll_settings
                         SET gorjetas_total_mes = 0.00, updated_at = NOW(), updated_by = ?
                         WHERE client_id = ? AND fiscal_year = ?"
                    );
                    $resetGorjetasMesStmt->execute([
                        isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
                        (int)$loggedInClientId,
                        $folhaFiscalYear,
                    ]);
                }

                $pdo->commit();
            } catch (Throwable $eSeedMonth) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Inicialização do novo mês (folha): ' . $eSeedMonth->getMessage());
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_payroll_config') {
        $postConfigYear = isset($_POST['config_year']) ? (int)$_POST['config_year'] : $payrollConfigYear;
        $postConfigYear = max($currentYear - 5, min($currentYear + 1, $postConfigYear));

        $defaultSubsidios  = max(0.0, (float)($_POST['default_subsidios'] ?? 0));
        // Fator de horas extras: multiplicador (1.0 = sem acréscimo; 1.25 = 25% acima)
        $fatorHorasExtra   = max(1.0, (float)($_POST['fator_horas_extra'] ?? 1.0));
        $defaultBonus      = max(0.0, (float)($_POST['default_bonus'] ?? 0));
        $gorjetasAutoSplit = isset($_POST['gorjetas_auto_split']) ? 1 : 0;
        $gorjetasTotalMes  = max(0.0, (float)($_POST['gorjetas_total_mes'] ?? 0));

        $pdo->beginTransaction();
        try {
            $saveSettings = $pdo->prepare(
                "INSERT INTO payroll_settings (client_id, fiscal_year, default_subsidios, default_horas_extra, default_bonus, gorjetas_auto_split, gorjetas_total_mes, updated_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                 default_subsidios = VALUES(default_subsidios),
                 default_horas_extra = VALUES(default_horas_extra),
                 default_bonus = VALUES(default_bonus),
                 gorjetas_auto_split = VALUES(gorjetas_auto_split),
                 gorjetas_total_mes = VALUES(gorjetas_total_mes),
                 updated_by = VALUES(updated_by),
                 updated_at = NOW()"
            );
            $saveSettings->execute([
                (int)$loggedInClientId,
                $postConfigYear,
                $defaultSubsidios,
                $fatorHorasExtra,
                $defaultBonus,
                $gorjetasAutoSplit,
                $gorjetasTotalMes,
                isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
            ]);

            $pdo->commit();
            header('Location: dashboard.php?section=definicoes&config_ano=' . $postConfigYear . '&payroll_config_saved=1');
            exit;
        } catch (Throwable $cfgError) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Erro ao salvar configuracao salarial: ' . $cfgError->getMessage());
            header('Location: dashboard.php?section=definicoes&config_ano=' . $postConfigYear . '&payroll_config_saved=0');
            exit;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_folha_variavel') {
        $postYear = isset($_POST['fiscal_year']) ? (int)$_POST['fiscal_year'] : $folhaFiscalYear;
        $postMonth = isset($_POST['fiscal_month']) ? (int)$_POST['fiscal_month'] : $folhaFiscalMonth;
        $postEmployeeId = isset($_POST['employee_id']) ? (int)$_POST['employee_id'] : 0;
        $postYear = max($currentYear - 5, min($currentYear + 1, $postYear));
        $postMonth = max(1, min(12, $postMonth));

        $checkCloseStmt = $pdo->prepare(
            "SELECT is_closed FROM folha_fechamentos_mensais
             WHERE client_id = ? AND fiscal_year = ? AND fiscal_month = ?
             LIMIT 1"
        );
        $checkCloseStmt->execute([(int)$loggedInClientId, $postYear, $postMonth]);
        $isClosedNow = (int)$checkCloseStmt->fetchColumn() === 1;

        if (!$isClosedNow && $postEmployeeId > 0) {
            $checkEmpStmt = $pdo->prepare("SELECT id FROM employees WHERE id = ? AND client_id = ? LIMIT 1");
            $checkEmpStmt->execute([$postEmployeeId, (int)$loggedInClientId]);
            $employeeExists = (bool)$checkEmpStmt->fetch(PDO::FETCH_ASSOC);

            if (!$employeeExists) {
                header('Location: dashboard.php?section=folha-pagamento&folha_mes=' . $postMonth . '&folha_ano=' . $postYear);
                exit;
            }

            // Segurança: funcionário já pago no período não pode mais ter variáveis da folha editadas.
            if (payrollTableExists($pdo, 'folha_pagamento') && payrollColumnExists($pdo, 'folha_pagamento', 'status_pagamento')) {
                $paidCheckStmt = $pdo->prepare(
                    "SELECT status_pagamento
                     FROM folha_pagamento
                     WHERE client_id = ? AND employee_id = ? AND fiscal_year = ? AND fiscal_month = ?
                     LIMIT 1"
                );
                $paidCheckStmt->execute([(int)$loggedInClientId, $postEmployeeId, $postYear, $postMonth]);
                $statusPagamentoAtual = mb_strtolower(trim((string)$paidCheckStmt->fetchColumn()));
                if ($statusPagamentoAtual === 'pago') {
                    header('Location: dashboard.php?section=folha-pagamento&folha_mes=' . $postMonth . '&folha_ano=' . $postYear);
                    exit;
                }
            }

            $saveStmt = $pdo->prepare(
                "INSERT INTO folha_variaveis_mensais
                (client_id, employee_id, fiscal_year, fiscal_month, horas_extra, faltas_dias, bonus, subsidios_extra, outros_descontos, gorjeta_manual, status, is_locked, updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                horas_extra = VALUES(horas_extra),
                faltas_dias = VALUES(faltas_dias),
                bonus = VALUES(bonus),
                subsidios_extra = VALUES(subsidios_extra),
                outros_descontos = VALUES(outros_descontos),
                gorjeta_manual = VALUES(gorjeta_manual),
                status = VALUES(status),
                is_locked = VALUES(is_locked),
                updated_by = VALUES(updated_by),
                updated_at = NOW()"
            );

            $statusFolha = 'ativo';

            $saveStmt->execute([
                (int)$loggedInClientId,
                $postEmployeeId,
                $postYear,
                $postMonth,
                (float)($_POST['horas_extra'] ?? 0),
                0.0,
                (float)($_POST['bonus_mensal'] ?? 0),
                (float)($_POST['subsidios_mensais'] ?? 0),
                0.0,
                max(0.0, (float)($_POST['gorjeta_manual'] ?? 0)),
                $statusFolha,
                isset($_POST['is_locked']) ? 1 : 0,
                isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
            ]);
        }

        header('Location: dashboard.php?section=folha-pagamento&folha_mes=' . $postMonth . '&folha_ano=' . $postYear);
        exit;
    }

    // ── Marcar funcionário como pago (AJAX JSON) ──
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_as_paid') {
        header('Content-Type: application/json; charset=utf-8');
        $mpYear  = (int)($_POST['fiscal_year']  ?? 0);
        $mpMonth = (int)($_POST['fiscal_month'] ?? 0);
        $mpEmpId = (int)($_POST['employee_id']  ?? 0);

        if ($mpYear < 2000 || $mpYear > 2100 || $mpMonth < 1 || $mpMonth > 12 || $mpEmpId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Parâmetros inválidos.']);
            exit;
        }

        // ── Verificar se folha está fechada ──
        $chkClose = $pdo->prepare(
            "SELECT is_closed FROM folha_fechamentos_mensais
             WHERE client_id = ? AND fiscal_year = ? AND fiscal_month = ?
             LIMIT 1"
        );
        $chkClose->execute([(int)$loggedInClientId, $mpYear, $mpMonth]);
        $isClosedForPayment = (int)$chkClose->fetchColumn() === 1;

        if ($isClosedForPayment) {
            echo json_encode(['ok' => false, 'error' => 'Folha fechada. Não é possível alterar status de pagamento.']);
            exit;
        }

        // Verificar que o funcionário pertence ao cliente autenticado
        $chkEmp = $pdo->prepare('SELECT id FROM employees WHERE id = ? AND client_id = ? LIMIT 1');
        $chkEmp->execute([$mpEmpId, (int)$loggedInClientId]);
        if (!$chkEmp->fetch()) {
            echo json_encode(['ok' => false, 'error' => 'Acesso negado.']);
            exit;
        }
        // Garantir que a coluna existe antes de atualizar
        if (payrollTableExists($pdo, 'folha_pagamento') && payrollColumnExists($pdo, 'folha_pagamento', 'status_pagamento')) {
            $upd = $pdo->prepare(
                "UPDATE folha_pagamento
                 SET status_pagamento = 'pago', data_pagamento = NOW(), updated_at = NOW()
                 WHERE client_id = ? AND employee_id = ? AND fiscal_year = ? AND fiscal_month = ?"
            );
            $upd->execute([(int)$loggedInClientId, $mpEmpId, $mpYear, $mpMonth]);
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    // ── Reverter pagamento (permitir nova edição das variáveis) ──
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'unmark_as_paid') {
        header('Content-Type: application/json; charset=utf-8');
        $upYear  = (int)($_POST['fiscal_year']  ?? 0);
        $upMonth = (int)($_POST['fiscal_month'] ?? 0);
        $upEmpId = (int)($_POST['employee_id']  ?? 0);

        if ($upYear < 2000 || $upYear > 2100 || $upMonth < 1 || $upMonth > 12 || $upEmpId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Parâmetros inválidos.']);
            exit;
        }

        // ── Verificar se folha está fechada ──
        $chkCloseUp = $pdo->prepare(
            "SELECT is_closed FROM folha_fechamentos_mensais
             WHERE client_id = ? AND fiscal_year = ? AND fiscal_month = ?
             LIMIT 1"
        );
        $chkCloseUp->execute([(int)$loggedInClientId, $upYear, $upMonth]);
        $isClosedForUnpay = (int)$chkCloseUp->fetchColumn() === 1;

        if ($isClosedForUnpay) {
            echo json_encode(['ok' => false, 'error' => 'Folha fechada. Não é possível alterar status de pagamento.']);
            exit;
        }

        // Verificar que o funcionário pertence ao cliente autenticado
        $chkEmpUp = $pdo->prepare('SELECT id FROM employees WHERE id = ? AND client_id = ? LIMIT 1');
        $chkEmpUp->execute([$upEmpId, (int)$loggedInClientId]);
        if (!$chkEmpUp->fetch()) {
            echo json_encode(['ok' => false, 'error' => 'Acesso negado.']);
            exit;
        }

        if (payrollTableExists($pdo, 'folha_pagamento') && payrollColumnExists($pdo, 'folha_pagamento', 'status_pagamento')) {
            $updUnpay = $pdo->prepare(
                "UPDATE folha_pagamento
                 SET status_pagamento = 'pendente', data_pagamento = NULL, updated_at = NOW()
                 WHERE client_id = ? AND employee_id = ? AND fiscal_year = ? AND fiscal_month = ?"
            );
            $updUnpay->execute([(int)$loggedInClientId, $upEmpId, $upYear, $upMonth]);
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    // ── Marcar todos como pagos (POST redirect) ──
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_all_paid') {
        $maYear  = (int)($_POST['fiscal_year']  ?? $folhaFiscalYear);
        $maMonth = (int)($_POST['fiscal_month'] ?? $folhaFiscalMonth);
        $maYear  = max($currentYear - 5, min($currentYear + 1, $maYear));
        $maMonth = max(1, min(12, $maMonth));

        // ── Verificar se folha está fechada ──
        $chkCloseMAll = $pdo->prepare(
            "SELECT is_closed FROM folha_fechamentos_mensais
             WHERE client_id = ? AND fiscal_year = ? AND fiscal_month = ?
             LIMIT 1"
        );
        $chkCloseMAll->execute([(int)$loggedInClientId, $maYear, $maMonth]);
        $isClosedForPaymentAll = (int)$chkCloseMAll->fetchColumn() === 1;

        if (!$isClosedForPaymentAll) {
            if (payrollTableExists($pdo, 'folha_pagamento') && payrollColumnExists($pdo, 'folha_pagamento', 'status_pagamento')) {
                $updAll = $pdo->prepare(
                    "UPDATE folha_pagamento
                     SET status_pagamento = 'pago', data_pagamento = NOW(), updated_at = NOW()
                     WHERE client_id = ? AND fiscal_year = ? AND fiscal_month = ? AND status_pagamento = 'pendente'"
                );
                $updAll->execute([(int)$loggedInClientId, $maYear, $maMonth]);
            }
        }

        header('Location: dashboard.php?section=folha-pagamento&folha_mes=' . $maMonth . '&folha_ano=' . $maYear);
        exit;
    }

    // ── Handler: Fechar folha de pagamento ──
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'close_folha') {
        $cfYear  = (int)($_POST['fiscal_year']  ?? $folhaFiscalYear);
        $cfMonth = (int)($_POST['fiscal_month'] ?? $folhaFiscalMonth);
        $cfYear  = max($currentYear - 5, min($currentYear + 1, $cfYear));
        $cfMonth = max(1, min(12, $cfMonth));
        $closeSucceeded = false;
        $closeReason = '';

        try {
            syncPayrollRowsForPeriod(
                $pdo,
                $employees,
                (int)$loggedInClientId,
                $cfYear,
                $cfMonth,
                isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null
            );
        } catch (Throwable $eSyncClose) {
            error_log('Erro ao sincronizar folha antes do fecho: ' . $eSyncClose->getMessage());
            header(
                'Location: dashboard.php?section=folha-pagamento&folha_mes=' . $cfMonth . '&folha_ano=' . $cfYear
                    . '&folha_close=0&folha_close_reason=sync_error'
            );
            exit;
        }

        $employeesInFolha = count($employees);

        if ($employeesInFolha <= 0) {
            $closeReason = 'no_employees';
        }

        $payrollRowsCount = 0;
        if ($closeReason === '' && payrollTableExists($pdo, 'folha_pagamento')) {
            $payrollRowsStmt = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM folha_pagamento
                 WHERE client_id = ? AND fiscal_year = ? AND fiscal_month = ?"
            );
            $payrollRowsStmt->execute([(int)$loggedInClientId, $cfYear, $cfMonth]);
            $payrollRowsCount = (int)$payrollRowsStmt->fetchColumn();
        }

        if ($closeReason === '' && $payrollRowsCount <= 0) {
            $closeReason = 'no_payroll_rows';
        }

        if ($closeReason === '' && $payrollRowsCount < $employeesInFolha) {
            $closeReason = 'missing_calculations';
        }

        if ($closeReason === '' && payrollTableExists($pdo, 'folha_pagamento')) {
            $invalidPayrollStmt = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM folha_pagamento
                 WHERE client_id = ? AND fiscal_year = ? AND fiscal_month = ?
                   AND (
                        salario_base IS NULL OR
                        salario_bruto IS NULL OR
                        total_descontos IS NULL OR
                        salario_liquido IS NULL
                   )"
            );
            $invalidPayrollStmt->execute([(int)$loggedInClientId, $cfYear, $cfMonth]);
            $invalidPayrollRows = (int)$invalidPayrollStmt->fetchColumn();
            if ($invalidPayrollRows > 0) {
                $closeReason = 'invalid_calculations';
            }
        }

        if ($closeReason !== '') {
            header(
                'Location: dashboard.php?section=folha-pagamento&folha_mes=' . $cfMonth . '&folha_ano=' . $cfYear
                    . '&folha_close=0&folha_close_reason=' . urlencode($closeReason)
            );
            exit;
        }

        // Verificar que nenhum funcionário está sendo editado/não aplicados
        // (simplificado: assumir que pode fechar mesmo com pendências)

        // Guardar snapshots antes de fechar
        try {
            $stmtSnap = $pdo->prepare(
                "SELECT fp.*, 
                        COALESCE(fvm.horas_extra, 0.00) AS horas_extra_mensal_snapshot,
                        COALESCE(fvm.faltas_dias, 0.00) AS faltas_dias_snapshot,
                        COALESCE(fvm.bonus, 0.00) AS bonus_mensal_snapshot,
                        COALESCE(fvm.subsidios_extra, 0.00) AS subsidios_extra_snapshot,
                        COALESCE(fvm.outros_descontos, 0.00) AS outros_descontos_snapshot,
                        COALESCE(fvm.gorjeta_manual, 0.00) AS gorjeta_manual_snapshot,
                        COALESCE(fvm.status, 'ativo') AS status_folha_snapshot,
                        COALESCE(fvm.is_locked, 0) AS is_locked_snapshot
                 FROM folha_pagamento fp
                 LEFT JOIN folha_variaveis_mensais fvm
                   ON fvm.client_id = fp.client_id
                  AND fvm.employee_id = fp.employee_id
                  AND fvm.fiscal_year = fp.fiscal_year
                  AND fvm.fiscal_month = fp.fiscal_month
                      WHERE fp.client_id = ? AND fp.fiscal_year = ? AND fp.fiscal_month = ?"
            );
            $stmtSnap->execute([(int)$loggedInClientId, $cfYear, $cfMonth]);

            foreach ($stmtSnap->fetchAll(PDO::FETCH_ASSOC) as $snapRow) {
                $empId = (int)$snapRow['employee_id'];
                if ($empId > 0) {
                    $snapshotData = $snapRow;
                    $snapshotData['horas_extra_mensal'] = (float)($snapRow['horas_extra_mensal_snapshot'] ?? 0);
                    $snapshotData['faltas_dias'] = (float)($snapRow['faltas_dias_snapshot'] ?? 0);
                    $snapshotData['bonus_mensal'] = (float)($snapRow['bonus_mensal_snapshot'] ?? 0);
                    $snapshotData['subsidios_extra'] = (float)($snapRow['subsidios_extra_snapshot'] ?? 0);
                    $snapshotData['outros_descontos'] = (float)($snapRow['outros_descontos_snapshot'] ?? 0);
                    $snapshotData['gorjeta_manual'] = (float)($snapRow['gorjeta_manual_snapshot'] ?? 0);
                    $snapshotData['status_folha'] = (string)($snapRow['status_folha_snapshot'] ?? 'ativo');
                    $snapshotData['is_locked'] = (int)($snapRow['is_locked_snapshot'] ?? 0);
                    $snapshotData['desconto_faltas'] = round(((float)($snapRow['salario_base'] ?? 0) / 30) * (float)($snapRow['faltas_dias_snapshot'] ?? 0), 2);
                    $snapshotData['total_bruto'] = (float)($snapRow['salario_bruto'] ?? 0);
                    $snapshotData['total_liquido'] = (float)($snapRow['salario_liquido'] ?? 0);

                    guardarFolhaSnapshot(
                        $pdo,
                        (int)$loggedInClientId,
                        $empId,
                        $cfYear,
                        $cfMonth,
                        $snapshotData,
                        isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null
                    );
                }
            }

            // Marcar folha como fechada
            $closeFolha = $pdo->prepare(
                "INSERT INTO folha_fechamentos_mensais (client_id, fiscal_year, fiscal_month, is_closed, closed_by, closed_at)
                 VALUES (?, ?, ?, 1, ?, NOW())
                 ON DUPLICATE KEY UPDATE
                 is_closed = 1,
                 closed_by = VALUES(closed_by),
                 closed_at = NOW()"
            );
            $closeFolha->execute([(int)$loggedInClientId, $cfYear, $cfMonth, isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null]);
            $closeSucceeded = true;

            error_log("Folha de pagamento fechada: ano=$cfYear, mês=$cfMonth, client_id=$loggedInClientId");
        } catch (Exception $e) {
            error_log("Erro ao fechar folha: " . $e->getMessage());
        }

        $closeStatus = $closeSucceeded ? '1' : '0';
        $redirectReason = $closeSucceeded ? '' : '&folha_close_reason=unexpected_error';
        header('Location: dashboard.php?section=folha-pagamento&folha_mes=' . $cfMonth . '&folha_ano=' . $cfYear . '&folha_close=' . $closeStatus . $redirectReason);
        exit;
    }

    // ── Handler: Reabrir folha de pagamento (admin only) ──
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reopen_folha') {
        $rfYear  = (int)($_POST['fiscal_year']  ?? $folhaFiscalYear);
        $rfMonth = (int)($_POST['fiscal_month'] ?? $folhaFiscalMonth);
        $rfYear  = max($currentYear - 5, min($currentYear + 1, $rfYear));
        $rfMonth = max(1, min(12, $rfMonth));

        // Verificar se é admin (user_level = 'admin' ou similar)
        $isAdmin = isset($_SESSION['user_level']) && in_array(mb_strtolower($_SESSION['user_level']), ['admin', 'administrador', 'superadmin'], true);

        if (!$isAdmin) {
            error_log("Permissão negada: utilizador {$_SESSION['user_id']} tentou reabrir folha");
            header('Location: dashboard.php?section=folha-pagamento&folha_mes=' . $rfMonth . '&folha_ano=' . $rfYear . '&error=permissao');
            exit;
        }

        try {
            // Remover snapshot do histórico (permite recalcular)
            $stmtDelSnap = $pdo->prepare(
                "DELETE FROM folha_pagamento_historico
                 WHERE client_id = ? AND fiscal_year = ? AND fiscal_month = ?"
            );
            $stmtDelSnap->execute([(int)$loggedInClientId, $rfYear, $rfMonth]);

            // Marcar folha como aberta novamente
            $reopenFolha = $pdo->prepare(
                "INSERT INTO folha_fechamentos_mensais (client_id, fiscal_year, fiscal_month, is_closed)
                 VALUES (?, ?, ?, 0)
                 ON DUPLICATE KEY UPDATE
                 is_closed = 0,
                 closed_at = NULL,
                 closed_by = NULL,
                 updated_at = NOW()"
            );
            $reopenFolha->execute([(int)$loggedInClientId, $rfYear, $rfMonth]);

            error_log("Folha de pagamento reabert: ano=$rfYear, mês=$rfMonth, client_id=$loggedInClientId, admin_id={$_SESSION['user_id']}");
        } catch (Exception $e) {
            error_log("Erro ao reabrir folha: " . $e->getMessage());
            header('Location: dashboard.php?section=folha-pagamento&folha_mes=' . $rfMonth . '&folha_ano=' . $rfYear . '&folha_reopen=0');
            exit;
        }

        header('Location: dashboard.php?section=folha-pagamento&folha_mes=' . $rfMonth . '&folha_ano=' . $rfYear . '&folha_reopen=1');
        exit;
    }

    // ── Verificar se folha está fechada ──
    $closeStmt = $pdo->prepare(
        "SELECT is_closed FROM folha_fechamentos_mensais
         WHERE client_id = ? AND fiscal_year = ? AND fiscal_month = ?
         LIMIT 1"
    );
    $closeStmt->execute([(int)$loggedInClientId, $folhaFiscalYear, $folhaFiscalMonth]);
    $folhaFechada = (int)$closeStmt->fetchColumn() === 1;

    // ── Se folha está fechada, usar histórico ──
    $usarHistorico = $folhaFechada; // Flag para usar snapshots do histórico
    $folhaCalculos = []; // Array para guardar resultados (calculados ou histórico)

    $gorjetaManualColExists = payrollColumnExists($pdo, 'folha_variaveis_mensais', 'gorjeta_manual');
    $varSelectCols = 'employee_id, horas_extra, faltas_dias, bonus, subsidios_extra, outros_descontos, status, is_locked'
        . ($gorjetaManualColExists ? ', gorjeta_manual' : '');
    $stmtVars = $pdo->prepare(
        "SELECT {$varSelectCols}
         FROM folha_variaveis_mensais
         WHERE client_id = ? AND fiscal_year = ? AND fiscal_month = ?"
    );
    $stmtVars->execute([(int)$loggedInClientId, $folhaFiscalYear, $folhaFiscalMonth]);
    $variaveisMensais = [];
    foreach ($stmtVars->fetchAll(PDO::FETCH_ASSOC) as $varRow) {
        $variaveisMensais[(int)$varRow['employee_id']] = $varRow;
    }

    $folhaTaxRules = obterRegrasFiscais($pdo, $folhaFiscalYear);
    $folhaConfigDefaults = obterConfiguracaoFolha($pdo, (int)$loggedInClientId, $folhaFiscalYear);
    $payrollAdminConfig = obterConfiguracaoFolha($pdo, (int)$loggedInClientId, $payrollConfigYear);
    $payrollAdminTaxRules = obterRegrasFiscais($pdo, $payrollConfigYear);
    $confirmedGorjetasPeriod = getConfirmedGorjetasForPeriod($pdo, (int)$loggedInClientId, $folhaFiscalYear, $folhaFiscalMonth);
    $confirmedGorjetasByEmployee = $confirmedGorjetasPeriod['by_employee'] ?? [];

    // Gorjetas: modo automático (divide total) ou manual (por funcionário)
    $gorjetasAutoSplitAtivo = (int)($folhaConfigDefaults['gorjetas_auto_split'] ?? 0) === 1;
    $gorjetasTotalMes       = (float)($folhaConfigDefaults['gorjetas_total_mes'] ?? 0.0);

    // Pré-carregar status de pagamento existentes (para não sobrescrever ao recalcular)
    $statusPagamentoExistente = [];
    if (payrollTableExists($pdo, 'folha_pagamento') && payrollColumnExists($pdo, 'folha_pagamento', 'status_pagamento')) {
        $stmtSP = $pdo->prepare(
            "SELECT employee_id, status_pagamento, data_pagamento
             FROM folha_pagamento
             WHERE client_id = ? AND fiscal_year = ? AND fiscal_month = ?"
        );
        $stmtSP->execute([(int)$loggedInClientId, $folhaFiscalYear, $folhaFiscalMonth]);
        foreach ($stmtSP->fetchAll(PDO::FETCH_ASSOC) as $spRow) {
            $statusPagamentoExistente[(int)$spRow['employee_id']] = [
                'status_pagamento' => $spRow['status_pagamento'] ?? 'pendente',
                'data_pagamento'   => $spRow['data_pagamento'],
            ];
        }
    }

    // Calcular gorjeta por funcionário conforme modo
    $numAtivosParaGorjeta = 0;
    if ($gorjetasAutoSplitAtivo && !empty($employees)) {
        foreach ($employees as $_e) {
            $st = mb_strtolower(trim((string)($_e['status'] ?? '')));
            if (in_array($st, ['active', 'ativo', 'ativa'], true)) {
                $numAtivosParaGorjeta++;
            }
        }
    }
    $gorjetaAutoValor = ($gorjetasAutoSplitAtivo && $numAtivosParaGorjeta > 0)
        ? round($gorjetasTotalMes / $numAtivosParaGorjeta, 2)
        : 0.0;

    foreach ($employees as $employee) {
        $employeeId = (int)($employee['id'] ?? 0);
        if ($employeeId <= 0) {
            continue;
        }

        // ── Se folha está fechada, usar histórico (não recalcular) ──
        if ($usarHistorico) {
            $snapshotHistorico = obterFolhaHistorico(
                $pdo,
                (int)$loggedInClientId,
                $employeeId,
                $folhaFiscalYear,
                $folhaFiscalMonth
            );

            if ($snapshotHistorico && is_array($snapshotHistorico)) {
                $folhaCalculos[$employeeId] = $snapshotHistorico;
                upsertFolhaPagamento($pdo, (int)$loggedInClientId, $employeeId, $folhaFiscalYear, $folhaFiscalMonth, $snapshotHistorico); // Manter em sincronia

                $folhaResumo['total_funcionarios']++;
                $folhaResumo['custo_total'] += (float)($snapshotHistorico['custo_total_empresa'] ?? 0);

                if (isset($snapshotHistorico['status_pagamento']) && $snapshotHistorico['status_pagamento'] === 'pago') {
                    $folhaResumo['funcionarios_pagos']++;
                } else {
                    $folhaResumo['pendencias']++;
                }
                continue; // Pular cálculo, usar snapshot
            }
        }

        // ── Se não tem histórico ou folha não está fechada, calcular normalmente ──
        $vars = $variaveisMensais[$employeeId] ?? [
            'horas_extra' => 0,
            'faltas_dias' => 0,
            'bonus' => 0,
            'subsidios_extra' => 0,
            'outros_descontos' => 0,
            'gorjeta_manual' => 0,
            'status' => 'ativo',
            'is_locked' => 0,
        ];

        $salarioBase = (float)($employee['salary_base'] ?? 0);
        $faltasDias = 0.0;
        $descontoFaltas = 0.0;

        // Gorjeta: automática (dividida igualmente) ou manual (por funcionário)
        if ($gorjetasAutoSplitAtivo) {
            // Só funcionários ativos recebem gorjeta automática
            $empSt = mb_strtolower(trim((string)($employee['status'] ?? '')));
            $gorjetaFuncionario = (float)($confirmedGorjetasByEmployee[$employeeId] ?? 0.0)
                + (in_array($empSt, ['active', 'ativo', 'ativa'], true)
                    ? $gorjetaAutoValor
                    : 0.0);
        } else {
            $gorjetaFuncionario = (float)($confirmedGorjetasByEmployee[$employeeId] ?? 0.0)
                + max(0.0, (float)($vars['gorjeta_manual'] ?? 0.0));
        }

        $dadosFuncionario = [
            'salario_base'         => $salarioBase,
            'subsidio_alimentacao' => (float)($employee['subsidio_alimentacao'] ?? 0) + (float)($vars['subsidios_extra'] ?? 0) + (float)($folhaConfigDefaults['default_subsidios'] ?? 0),
            'subsidios_tributaveis' => 0.0,
            'horas_extra'          => (float)($vars['horas_extra'] ?? 0) * max(1.0, (float)($folhaConfigDefaults['default_horas_extra'] ?? 1.0)),
            'bonus'                => (float)($employee['bonus'] ?? 0) + (float)($vars['bonus'] ?? 0) + (float)($folhaConfigDefaults['default_bonus'] ?? 0),
            'gorjetas'             => $gorjetaFuncionario,
        ];

        // Snapshot por colaborador/mes: mantem taxa e parcela IRS historicas apos alteracoes futuras.
        $taxRulesForEmployee = obterSnapshotRegrasFolha(
            $pdo,
            (int)$loggedInClientId,
            $employeeId,
            $folhaFiscalYear,
            $folhaFiscalMonth,
            $folhaTaxRules
        );

        $resultadoFolha = calcularFolhaPagamento($dadosFuncionario, $taxRulesForEmployee);
        $outrosDescontos = 0.0;
        $resultadoFolha['faltas_dias'] = round($faltasDias, 2);
        $resultadoFolha['desconto_faltas'] = round($descontoFaltas, 2);
        $resultadoFolha['outros_descontos'] = round($outrosDescontos, 2);
        $resultadoFolha['horas_extra_mensal'] = round((float)($vars['horas_extra'] ?? 0), 2);
        $resultadoFolha['bonus_mensal'] = round((float)($vars['bonus'] ?? 0), 2);
        $resultadoFolha['subsidios_extra'] = round((float)($vars['subsidios_extra'] ?? 0), 2);
        $resultadoFolha['gorjeta_manual'] = round((float)($vars['gorjeta_manual'] ?? 0), 2);
        $resultadoFolha['total_descontos'] = 0.0;
        $resultadoFolha['salario_liquido'] = round((float)$resultadoFolha['salario_bruto'], 2);
        $resultadoFolha['status_folha'] = (string)($vars['status'] ?? 'ativo');
        $resultadoFolha['is_locked'] = (int)($vars['is_locked'] ?? 0);
        $resultadoFolha['fiscal_month'] = $folhaFiscalMonth;
        $resultadoFolha['fiscal_year'] = $folhaFiscalYear;
        // Status de pagamento vem do snapshot DB (não do status do funcionário)
        $resultadoFolha['status_pagamento'] = $statusPagamentoExistente[$employeeId]['status_pagamento'] ?? 'pendente';
        $resultadoFolha['data_pagamento']   = $statusPagamentoExistente[$employeeId]['data_pagamento']   ?? null;

        $folhaCalculos[$employeeId] = $resultadoFolha;

        upsertFolhaPagamento($pdo, (int)$loggedInClientId, $employeeId, $folhaFiscalYear, $folhaFiscalMonth, $resultadoFolha);

        $folhaResumo['total_funcionarios']++;
        $folhaResumo['custo_total'] += (float)($resultadoFolha['custo_total_empresa'] ?? 0);
        if (($resultadoFolha['status_pagamento'] ?? 'pendente') === 'pago') {
            $folhaResumo['funcionarios_pagos']++;
        } else {
            $folhaResumo['pendencias']++;
        }
    }
} catch (Throwable $e) {
    error_log('Erro ao calcular folha de pagamento: ' . $e->getMessage());
}

// Contagem de funcionários ativos (usando o array $employees)
$activeEmployeesCount = 0;
if (isset($employees) && is_array($employees)) {
    foreach ($employees as $employee) {
        // Contar apenas funcionários que são ativos ou estão de férias; ignorar inativos
        $st = isset($employee['status']) ? mb_strtolower(trim($employee['status'])) : '';
        if ($st === 'active' || $st === 'ativo' || $st === 'ferias' || $st === 'férias') {
            $activeEmployeesCount++;
        }
    }
}

// Contagem de presenças do dia atual
try {
    $sqlPresentes = "SELECT COUNT(*) as total 
                     FROM presencas p
                     INNER JOIN employees e ON p.funcionario_id = e.id
                     WHERE p.status = 'presente' 
                     AND DATE(p.data_registro) = CURDATE()
                     AND e.client_id = ?";

    $stmtPresentes = $pdo->prepare($sqlPresentes);
    $stmtPresentes->execute([$loggedInClientId]);
    $presentCount = (int)$stmtPresentes->fetchColumn();
} catch (PDOException $e) {
    error_log("Erro ao contar presenças: " . $e->getMessage());
    $presentCount = 0;
}



// Contagem de faltas (código corrigido)
try {
    $sqlFaltas = "SELECT COUNT(*) as total 
                  FROM presencas p
                  INNER JOIN employees e ON p.funcionario_id = e.id
                  WHERE p.status = 'falta' 
                  AND DATE(p.data_registro) = CURDATE()
                  AND e.client_id = ?";

    $stmtFaltas = $pdo->prepare($sqlFaltas);
    $stmtFaltas->execute([$loggedInClientId]);
    $faltasHoje = (int)$stmtFaltas->fetchColumn();

} catch (PDOException $e) {
    error_log("Erro ao contar faltas: " . $e->getMessage());
    $faltasHoje = 0;
}

// **** CARREGAR DADOS DE PRESENÇA E PONTO PARA MERGE COM EMPLOYEES ****
try {
    $stmtPresenca = $pdo->prepare("
        SELECT funcionario_id, status as presence_status 
        FROM presencas 
        WHERE DATE(data_registro) = CURDATE()
    ");
    $stmtPresenca->execute();
    $presencaData = $stmtPresenca->fetchAll(PDO::FETCH_ASSOC);

    // Criar mapa funcionario_id => presence_status
    $presencaMap = [];
    foreach ($presencaData as $p) {
        $presencaMap[$p['funcionario_id']] = $p['presence_status'];
    }

    // Adicionar presence_status ao array $employees
    foreach ($employees as &$emp) {
        $emp['presence_status'] = $presencaMap[$emp['id']] ?? null;
    }
    unset($emp); // Liberar referência
} catch (PDOException $e) {
    error_log("Erro ao carregar dados de presença: " . $e->getMessage());
}

// atividade recente do funcionario
// topo do dashboard.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifique se este caminho está correto para chegar ao seu config

$recentActivities = [];
$loggedInClientId = $_SESSION['client_id'] ?? null;
if ($loggedInClientId) {
    try {
        // Evita exceção se as colunas employee_id ou status ainda não existirem
        $hasEmpCol = false;
        $hasStatusCol = false;
        try {
            $check = $pdo->query("SHOW COLUMNS FROM atividades_recentes LIKE 'employee_id'")->fetch();
            if ($check) $hasEmpCol = true;
            $check2 = $pdo->query("SHOW COLUMNS FROM atividades_recentes LIKE 'status'")->fetch();
            if ($check2) $hasStatusCol = true;
        } catch (Exception $ie) {
        }

        // monta campos dinamicamente para evitar exceções se colunas não existirem
        $fields = ['ar.title', 'ar.type', 'ar.timestamp'];
        $join = '';
        if ($hasStatusCol) $fields[] = 'ar.status';
        if ($hasEmpCol) {
            $fields[] = 'ar.employee_id';
            $fields[] = 'e.name AS employee_name';
            $fields[] = 'e.profile_picture AS employee_profile_picture';
            $join = 'LEFT JOIN employees e ON ar.employee_id = e.id';
        }

        $sql = "SELECT " . implode(', ', $fields) . " FROM atividades_recentes ar $join WHERE ar.client_id = ? AND ar.timestamp >= (NOW() - INTERVAL 24 HOUR) ORDER BY ar.timestamp DESC LIMIT 10";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$loggedInClientId]);
        $recentActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao ler atividades: " . $e->getMessage());
        $recentActivities = [];
    }
}



// ferias solicitacoes
$feriasPendentes = [];
$feriasHistoricoSolic = [];
try {
    $feriasCols = $pdo->query('SHOW COLUMNS FROM ferias')->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $feriasCols = array_map(static fn($c) => mb_strtolower((string)$c), $feriasCols);
    $feriasEmployeeCol = in_array('funcionario_id', $feriasCols, true) ? 'funcionario_id' : (in_array('employee_id', $feriasCols, true) ? 'employee_id' : 'funcionario_id');

    $feriasDecisionCol = '';
    foreach (['decidido_em', 'updated_at', 'reviewed_at', 'approved_at', 'rejected_at', 'cancelled_at', 'canceled_at'] as $candidateCol) {
        if (in_array($candidateCol, $feriasCols, true)) {
            $feriasDecisionCol = $candidateCol;
            break;
        }
    }
    $feriasDecisionSelect = $feriasDecisionCol !== '' ? ", f.{$feriasDecisionCol} AS decision_at" : '';

    $stmtFerias = $pdo->prepare("SELECT f.id, f.{$feriasEmployeeCol} AS employee_id, f.data_inicio, f.data_fim, f.status, f.motivo{$feriasDecisionSelect}, e.name AS employee_name, e.profile_picture AS employee_profile_picture
        FROM ferias f
        INNER JOIN employees e ON e.id = f.{$feriasEmployeeCol}
        WHERE e.client_id = ?
        ORDER BY f.id DESC");
    $stmtFerias->execute([(int)$loggedInClientId]);
    $feriasRows = $stmtFerias->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $todayIso = date('Y-m-d');
    foreach ($feriasRows as $fRow) {
        $statusRaw = mb_strtolower(trim((string)($fRow['status'] ?? 'pendente')));
        if ($statusRaw === 'aprovado') {
            $statusRaw = 'aprovada';
        }
        if (in_array($statusRaw, ['rejeitado', 'recusado', 'recusada'], true)) {
            $statusRaw = 'rejeitada';
        }
        if ($statusRaw === 'cancelado') {
            $statusRaw = 'cancelada';
        }

        if (in_array($statusRaw, ['pendente', 'pending', ''], true)) {
            $feriasPendentes[] = $fRow;
            continue;
        }

        $fimRaw = trim((string)($fRow['data_fim'] ?? ''));
        $fimIso = '';
        if ($fimRaw !== '') {
            $fimTs = strtotime($fimRaw);
            if ($fimTs !== false) {
                $fimIso = date('Y-m-d', $fimTs);
            }
        }
        $isHistorico = in_array($statusRaw, ['aprovada', 'rejeitada', 'cancelada'], true);

        if ($isHistorico) {
            $fRow['status_norm'] = $statusRaw;
            $decisionRaw = trim((string)($fRow['decision_at'] ?? ''));
            $decisionTs = $decisionRaw !== '' ? strtotime($decisionRaw) : false;
            if ($decisionTs === false || $decisionTs === null) {
                $decisionTs = strtotime((string)($fRow['data_inicio'] ?? '')) ?: 0;
            }
            $fRow['decision_sort_ts'] = (int)$decisionTs;
            $feriasHistoricoSolic[] = $fRow;
        }
    }

    usort($feriasHistoricoSolic, static function (array $a, array $b): int {
        $aTs = (int)($a['decision_sort_ts'] ?? 0);
        $bTs = (int)($b['decision_sort_ts'] ?? 0);
        if ($aTs === $bTs) {
            return ((int)($b['id'] ?? 0)) <=> ((int)($a['id'] ?? 0));
        }
        return $bTs <=> $aTs;
    });
} catch (Throwable $e) {
    error_log('Erro ao carregar solicitações de férias: ' . $e->getMessage());
    $feriasPendentes = [];
    $feriasHistoricoSolic = [];
}






//atividades  recentes
// (lidas acima de forma segura)
// APENAS PARA TESTE: veja se os dados aparecem na tela:
// var_dump($recentActivities);



?>



<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken); ?>">
    <title>Painel RH - RHNeto Pro - <?php echo htmlspecialchars($fullname); ?></title>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@600;700;800&display=swap"
        rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Toastify CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- Seu CSS -->
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?php echo (int)@filemtime(__DIR__ . '/assets/css/dashboard.css'); ?>">
    <!-- Bibliotecas JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Chart.js para gráficos -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <!-- Seu JS -->
    <script src="assets/js/dashboard.js?v=<?php echo (int)@filemtime(__DIR__ . '/assets/js/dashboard.js'); ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>


<style>
.solicitacao-pending-badge {
    transition: box-shadow 0.2s, background 0.2s;
    z-index: 20;
}

.trial-banner {
    margin: 16px auto 0;
    max-width: 1320px;
    padding: 10px 14px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    gap: 12px;
    background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 55%, #2563eb 100%);
    color: #fff;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.2);
}

.trial-banner__icon {
    width: 38px;
    height: 38px;
    min-width: 38px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    background: rgba(255, 255, 255, 0.16);
    font-size: 16px;
}

.trial-banner__content {
    flex: 1;
    min-width: 0;
}

.trial-banner__actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.trial-banner__title {
    font-family: 'Poppins', sans-serif;
    font-weight: 700;
    font-size: 14px;
    line-height: 1.2;
}

.trial-banner__text {
    margin-top: 3px;
    font-size: 12px;
    opacity: 0.92;
}

.trial-banner__countdown {
    font-variant-numeric: tabular-nums;
    font-weight: 700;
}

.trial-banner__badge {
    padding: 7px 10px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.18);
    border: 1px solid rgba(255, 255, 255, 0.22);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    white-space: nowrap;
}

.trial-banner__button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    border-radius: 12px;
    background: #ffffff;
    color: #1d4ed8;
    font-size: 12px;
    font-weight: 800;
    text-decoration: none;
    white-space: nowrap;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
    box-shadow: 0 8px 16px rgba(15, 23, 42, 0.14);
}

.trial-banner__button:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 18px rgba(15, 23, 42, 0.18);
}

@media (max-width: 768px) {
    .trial-banner {
        margin: 12px 12px 0;
        padding: 10px 12px;
        gap: 10px;
        flex-wrap: wrap;
    }

    .trial-banner__badge {
        display: none;
    }

    .trial-banner__actions {
        width: 100%;
        justify-content: flex-start;
    }

    .trial-banner__button {
        width: 100%;
        justify-content: center;
    }
}

@keyframes solicitacaoBadgeFloat {
    0% {
        transform: translateY(0);
        box-shadow: 0 2px 8px #f59e0b33;
    }

    100% {
        transform: translateY(-7px) scale(1.08);
        box-shadow: 0 8px 18px #f59e0b55;
    }
}
</style>

<body class="<?php echo $body_class; ?>">

    <!-- Navigation -->
    <nav class="navbar">
        <div class="navbar-container">
            <div class="navbar-content">
                <!-- Logo -->
                <div class="logo" onclick="showSection('inicio')">
                    <div class="logo-icon">
                        <img src="rh.png" alt="RHNeto Pro" class="logo-image">
                    </div>
                    <span class="logo-text">
                        RHNeto Pro
                    </span>
                </div>
                <!-- Desktop Navigation -->
                <div class="nav-links">
                    <div class="nav-link active" data-section="inicio" onclick="showSection('inicio')">
                        <i class="fas fa-home"></i>
                        <span>Início</span>
                    </div>
                    <div class="nav-link" data-section="funcionarios" onclick="showSection('funcionarios')">
                        <i class="fas fa-users"></i>
                        <span>Funcionários</span>
                    </div>
                    <div class="nav-link" data-section="assiduidade" onclick="showSection('assiduidade')">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Presença</span>
                    </div>
                    <div class="nav-link" data-section="turnos" onclick="showSection('turnos')">
                        <i class="fas fa-business-time"></i>
                        <span>Turnos</span>
                    </div>
                    <div class="nav-link" data-section="folha-pagamento" onclick="showSection('folha-pagamento')">
                        <i class="fas fa-money-check-alt"></i>
                        <span>Folha</span>
                    </div>
                    <div class="nav-link" data-section="gorjetas" onclick="showSection('gorjetas')">
                        <i class="fas fa-coins"></i>
                        <span>Gorjetas</span>
                    </div>
                    <div class="nav-link" data-section="relatorios" onclick="showSection('relatorios')">
                        <i class="fas fa-chart-line"></i>
                        <span>Relatórios</span>
                    </div>
                    <div class="nav-link" data-section="ferias" onclick="showSection('ferias')">
                        <i class="fas fa-umbrella-beach"></i>
                        <span>Férias</span>
                    </div>

                    <div class="nav-link" data-section="solicitacoes" onclick="showSection('solicitacoes')"
                        style="position:relative;">
                        <i class="fas fa-file-alt"></i>
                        <span>Solicitações</span>
                        <?php if (!empty($solicitacoesPendentesTotal) && $solicitacoesPendentesTotal > 0): ?>
                        <span class="solicitacao-pending-badge"
                            style="position:absolute; top:6px; right:-10px; background:#f59e0b; color:#fff; font-size:0.85em; font-weight:700; border-radius:12px; padding:2px 8px; min-width:22px; text-align:center; box-shadow:0 2px 8px #f59e0b33; animation:solicitacaoBadgeFloat 1.2s infinite alternate;">
                            <?php echo $solicitacoesPendentesTotal; ?>
                        </span>
                        <?php endif; ?>
                    </div>

                    <div class="user-section">
                        <div class="nav-link profile-link" onclick="toggleProfileMenu()">
                            <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="Perfil"
                                class="profile-avatar admin-profile-avatar">
                            <div class="profile-info">
                                <div class="profile-name"><?php echo htmlspecialchars(explode(' ', $fullname)[0]); ?>
                                </div>
                                <div class="profile-specialty"><?php echo htmlspecialchars($specialty); ?></div>
                            </div>

                            <div class="profile-dropdown" id="profile-dropdown" onclick="event.stopPropagation();">
                                <div class="dropdown-item"
                                    onclick="event.stopPropagation(); triggerAdminProfilePhotoPicker();">
                                    <i class="fas fa-camera"></i>
                                    Alterar foto de perfil
                                </div>
                                <div class="dropdown-item"
                                    onclick="event.stopPropagation(); showSection('definicoes'); toggleProfileMenu(false);">
                                    <i class="fas fa-user-cog"></i>

                                    Configurações
                                </div>
                                <div class="dropdown-item"
                                    onclick="event.stopPropagation(); window.location.href='views/login.php';">
                                    <i class="fas fa-sign-out-alt"></i>
                                    Sair
                                </div>
                            </div>
                            <input type="file" id="admin-profile-photo-input" accept="image/*" style="display: none;"
                                onchange="handleAdminProfilePhotoSelected(event)">
                        </div>
                    </div>
                </div>
    </nav>

    <?php if ($trialBannerVisible && $trialEndsAtIso !== ''): ?>
        <div class="trial-banner" data-trial-banner data-trial-end="<?php echo htmlspecialchars($trialEndsAtIso); ?>" aria-live="polite">
            <div class="trial-banner__icon">
                <i class="fas fa-gift"></i>
            </div>
            <div class="trial-banner__content">
                <div class="trial-banner__title">7 dias grátis para testar o painel</div>
                <div class="trial-banner__text">
                    Termina em <span class="trial-banner__countdown" data-trial-countdown>--d --h --m --s</span>
                </div>
            </div>
            <div class="trial-banner__actions">
                <a class="trial-banner__button" href="/planos/">
                    <i class="fas fa-bolt"></i>
                    Assinar agora
                </a>
                <div class="trial-banner__badge">TRIAL</div>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const banner = document.querySelector('[data-trial-banner]');
                const countdown = banner ? banner.querySelector('[data-trial-countdown]') : null;
                const endValue = banner ? banner.getAttribute('data-trial-end') : '';

                if (!banner || !countdown || !endValue) {
                    return;
                }

                const trialEnd = new Date(endValue);

                function pad(value) {
                    return String(value).padStart(2, '0');
                }

                function renderCountdown() {
                    const remaining = trialEnd.getTime() - Date.now();

                    if (remaining <= 0) {
                        countdown.textContent = '0d 00h 00m 00s';
                        return;
                    }

                    const totalSeconds = Math.floor(remaining / 1000);
                    const days = Math.floor(totalSeconds / 86400);
                    const hours = Math.floor((totalSeconds % 86400) / 3600);
                    const minutes = Math.floor((totalSeconds % 3600) / 60);
                    const seconds = totalSeconds % 60;

                    countdown.textContent = `${days}d ${pad(hours)}h ${pad(minutes)}m ${pad(seconds)}s`;
                }

                renderCountdown();
                setInterval(renderCountdown, 1000);
            });
        </script>
    <?php endif; ?>

    <!-- Main Content -->

    <main class="main-content">
        <!-- Início Section -->
        <?php require $ADMIN_DIR . '/sections/inicio.php'; ?>















        <section id="funcionarios-section" class="content-section">
            <div class="frhd">
                <div class="frhd-left">
                    <div class="frhd-icon"><i class="fas fa-users"></i></div>
                    <div>
                        <h2 class="frhd-title">Funcionários</h2>
                        <p class="frhd-sub"><?php echo $totalEmployees ?? 0; ?> no total &middot; <?php echo $activeCount ?? 0; ?> ativos hoje</p>
                    </div>
                </div>
                <button id="addEmployeeBtn" type="button" class="frhd-add-btn">
                    <i class="fas fa-plus"></i> Novo Funcionário
                </button>
            </div>


            <?php
            $totalEmployees = count($employees);
            $activeCount = 0; $inactiveCount = 0; $feriasCount = 0; $newThisMonth = 0;
            $currentMonth = date('Y-m');
            foreach ($employees as $emp) {
                $st = mb_strtolower(trim((string)($emp['status'] ?? '')));
                if ($st === 'active') $activeCount++;
                elseif ($st === 'inactive' || $st === 'inativo') $inactiveCount++;
                elseif ($st === 'ferias' || $st === 'férias') $feriasCount++;
                $startField = $emp['startDate'] ?? $emp['start_date'] ?? '';
                if (!empty($startField) && strpos($startField, $currentMonth) === 0) $newThisMonth++;
            }
            $pctAtivos = $totalEmployees > 0 ? round(($activeCount / $totalEmployees) * 100) : 0;
            ?>

            <!-- KPI Strip -->
            <div class="fr-kpi-strip">
                <div class="fr-kpi fr-kpi-total">
                    <div class="fr-kpi-icon"><i class="fas fa-users"></i></div>
                    <div class="fr-kpi-body">
                        <span class="fr-kpi-val"><?= $totalEmployees ?></span>
                        <span class="fr-kpi-lbl">Total</span>
                    </div>
                </div>
                <div class="fr-kpi fr-kpi-active">
                    <div class="fr-kpi-icon"><i class="fas fa-user-check"></i></div>
                    <div class="fr-kpi-body">
                        <span class="fr-kpi-val"><?= $activeCount ?></span>
                        <span class="fr-kpi-lbl">Ativos</span>
                        <span class="fr-kpi-pct"><?= $pctAtivos ?>%</span>
                    </div>
                </div>
                <div class="fr-kpi fr-kpi-inactive">
                    <div class="fr-kpi-icon"><i class="fas fa-user-times"></i></div>
                    <div class="fr-kpi-body">
                        <span class="fr-kpi-val"><?= $inactiveCount ?></span>
                        <span class="fr-kpi-lbl">Inativos</span>
                    </div>
                </div>
                <div class="fr-kpi fr-kpi-ferias">
                    <div class="fr-kpi-icon"><i class="fas fa-umbrella-beach"></i></div>
                    <div class="fr-kpi-body">
                        <span class="fr-kpi-val"><?= $feriasCount ?></span>
                        <span class="fr-kpi-lbl">Em Férias</span>
                    </div>
                </div>
                <div class="fr-kpi fr-kpi-new">
                    <div class="fr-kpi-icon"><i class="fas fa-user-plus"></i></div>
                    <div class="fr-kpi-body">
                        <span class="fr-kpi-val"><?= $newThisMonth ?></span>
                        <span class="fr-kpi-lbl">Novas admissões</span>
                        <span class="fr-kpi-pct">este mês</span>
                    </div>
                </div>
            </div>

            <div class="data-table fr-table-wrap">
                <!-- Toolbar -->
                <div class="fr-toolbar">
                    <div class="fr-toolbar-top">
                        <div class="fr-search-wrap">
                            <i class="fas fa-search fr-search-icon"></i>
                            <input type="text" id="employeeTableSearch" class="fr-search"
                                placeholder="Pesquisar por nome, email, cargo…">
                        </div>
                        <div class="fr-toolbar-right">
                            <button type="button" class="fr-filter-toggle" id="frFilterToggle" onclick="document.getElementById('frAdvFilters').classList.toggle('fr-adv-open')">
                                <i class="fas fa-sliders-h"></i> Filtros
                                <span class="fr-filter-badge" id="frFilterBadge" style="display:none"></span>
                            </button>
                            <div class="fr-export-wrap" style="position:relative;">
                                <button class="fr-export-btn" onclick="toggleExportDropdown()">
                                    <i class="fas fa-arrow-up-from-bracket"></i> Exportar <i class="fas fa-chevron-down" style="font-size:.7em;margin-left:2px;"></i>
                                </button>
                                <div id="exportDropdown" class="fr-export-menu" style="display:none;">
                                    <a href="#" onclick="exportEmployeesPDF(); return false;" class="fr-export-item">
                                        <i class="fas fa-file-pdf" style="color:#e74c3c;"></i> PDF
                                    </a>
                                    <a href="#" onclick="exportEmployeesExcel(); return false;" class="fr-export-item">
                                        <i class="fas fa-file-excel" style="color:#27ae60;"></i> Excel
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Status chips -->
                    <div class="fr-chips">
                        <button class="fr-chip fr-chip-all active" data-chip-status="">
                            <i class="fas fa-th-large"></i> Todos
                            <span class="fr-chip-count"><?= $totalEmployees ?></span>
                        </button>
                        <button class="fr-chip fr-chip-active" data-chip-status="active">
                            <span class="fr-dot fr-dot-green"></span> Ativos
                            <span class="fr-chip-count"><?= $activeCount ?></span>
                        </button>
                        <button class="fr-chip fr-chip-inactive" data-chip-status="inactive">
                            <span class="fr-dot fr-dot-red"></span> Inativos
                            <span class="fr-chip-count"><?= $inactiveCount ?></span>
                        </button>
                        <button class="fr-chip fr-chip-ferias" data-chip-status="ferias">
                            <span class="fr-dot fr-dot-blue"></span> Férias
                            <span class="fr-chip-count"><?= $feriasCount ?></span>
                        </button>
                    </div>

                    <!-- Advanced filters (collapsible) -->
                    <div class="fr-adv-filters" id="frAdvFilters">
                        <select id="employeeTableStatus" class="fr-select" style="display:none">
                            <option value="">Todos os status</option>
                            <option value="active">Ativo</option>
                            <option value="inactive">Inativo</option>
                            <option value="ferias">Férias</option>
                        </select>
                        <select id="employeeTablePosition" class="fr-select">
                            <option value="">Cargo</option>
                        </select>
                        <select id="employeeTableDepartment" class="fr-select">
                            <option value="">Departamento</option>
                        </select>
                        <select id="employeeTableContractType" class="fr-select">
                            <option value="">Tipo de contrato</option>
                            <option value="efetivo">Efetivo</option>
                            <option value="temporario">Temporário</option>
                            <option value="part-time">Part-time</option>
                            <option value="estagio">Estágio</option>
                            <option value="freelancer">Freelancer</option>
                        </select>
                        <select id="employeeTableExpiry" class="fr-select">
                            <option value="">Vigência</option>
                            <option value="expiring">Expira em 30d</option>
                            <option value="expired">Expirado</option>
                            <option value="active">Sem data de fim</option>
                        </select>
                        <button type="button" class="fr-clear-btn" onclick="clearAllFilters()">
                            <i class="fas fa-times"></i> Limpar
                        </button>
                    </div>
                </div>

                <style>
                /* ═══════════════════════════════════════════════
                   FUNCIONÁRIOS — REDESIGN PROFISSIONAL
                ═══════════════════════════════════════════════ */

                /* Header */
                .frhd { display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap; margin-bottom:1.5rem; background:var(--card-bg,#1e293b); border:1px solid var(--border-color,rgba(255,255,255,.07)); border-radius:14px; padding:.875rem 1.25rem; width:100%; box-sizing:border-box; }
                .frhd-left { display:flex; align-items:center; gap:14px; }
                .frhd-icon { width:48px; height:48px; border-radius:14px; background:linear-gradient(135deg,#3b82f6,#1d4ed8); display:flex; align-items:center; justify-content:center; color:#fff; font-size:1.25rem; box-shadow:0 4px 14px rgba(59,130,246,.35); flex-shrink:0; }
                .frhd-title { margin:0; font-size:1.5rem; font-weight:700; color:var(--text-primary,#f1f5f9); line-height:1.1; }
                .frhd-sub { margin:2px 0 0; font-size:.8rem; color:var(--text-secondary,#94a3b8); }
                .frhd-add-btn { display:inline-flex; align-items:center; gap:8px; padding:10px 20px; background:linear-gradient(135deg,#3b82f6,#2563eb); color:#fff; border:none; border-radius:10px; font-size:.875rem; font-weight:600; cursor:pointer; transition:all .2s; box-shadow:0 4px 12px rgba(59,130,246,.3); white-space:nowrap; }
                .frhd-add-btn:hover { transform:translateY(-1px); box-shadow:0 6px 18px rgba(59,130,246,.4); }

                /* KPI strip */
                .fr-kpi-strip { display:grid; grid-template-columns:repeat(5,1fr); gap:.875rem; margin-bottom:1.75rem; }
                @media(max-width:900px){ .fr-kpi-strip{ grid-template-columns:repeat(3,1fr); } }
                @media(max-width:560px){ .fr-kpi-strip{ grid-template-columns:repeat(2,1fr); } }
                .fr-kpi { display:flex; align-items:center; gap:14px; background:var(--card-bg,#1e293b); border:1px solid var(--border-color,rgba(255,255,255,.07)); border-radius:14px; padding:1rem 1.1rem; transition:transform .15s,box-shadow .15s; }
                .fr-kpi:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(0,0,0,.18); }
                .fr-kpi-icon { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.05rem; flex-shrink:0; }
                .fr-kpi-total .fr-kpi-icon  { background:rgba(148,163,184,.12); color:#94a3b8; }
                .fr-kpi-active .fr-kpi-icon  { background:rgba(16,185,129,.12); color:#10b981; }
                .fr-kpi-inactive .fr-kpi-icon{ background:rgba(239,68,68,.12); color:#ef4444; }
                .fr-kpi-ferias .fr-kpi-icon  { background:rgba(59,130,246,.12); color:#3b82f6; }
                .fr-kpi-new .fr-kpi-icon     { background:rgba(167,139,250,.12); color:#a78bfa; }
                .fr-kpi-body { display:flex; flex-direction:column; }
                .fr-kpi-val { font-size:1.6rem; font-weight:700; color:var(--text-primary,#f1f5f9); line-height:1; }
                .fr-kpi-lbl { font-size:.72rem; font-weight:500; color:var(--text-secondary,#94a3b8); margin-top:2px; }
                .fr-kpi-pct { font-size:.68rem; color:#64748b; margin-top:1px; }

                /* Toolbar */
                .fr-table-wrap .fr-toolbar { background:var(--card-bg,#1e293b); border:1px solid var(--border-color,rgba(255,255,255,.07)); border-radius:14px; padding:1rem 1.1rem; margin-bottom:.1rem; display:flex; flex-direction:column; gap:.75rem; }
                .fr-toolbar-top { display:flex; align-items:center; gap:.75rem; flex-wrap:wrap; }
                .fr-search-wrap { position:relative; flex:1; min-width:200px; }
                .fr-search-icon { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#64748b; font-size:.85rem; pointer-events:none; }
                .fr-search { width:100%; padding:9px 12px 9px 36px; border:1px solid var(--border-color,rgba(255,255,255,.12)); border-radius:9px; background:var(--input-bg,#0f172a); color:var(--text-primary,#f1f5f9); font-size:.875rem; outline:none; transition:border-color .2s; }
                .fr-search:focus { border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.12); }
                .fr-search::placeholder { color:#475569; }
                .fr-toolbar-right { display:flex; align-items:center; gap:.5rem; flex-shrink:0; }
                .fr-filter-toggle { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; border:1px solid var(--border-color,rgba(255,255,255,.12)); border-radius:9px; background:transparent; color:var(--text-secondary,#94a3b8); font-size:.8rem; font-weight:600; cursor:pointer; transition:all .2s; position:relative; }
                .fr-filter-toggle:hover { border-color:#3b82f6; color:#3b82f6; }
                .fr-filter-toggle.pa-filter-open,
                .fr-filter-toggle.active { border-color:#3b82f6; color:#60a5fa; background:rgba(59,130,246,.08); }
                .fr-filter-badge { position:absolute; top:-5px; right:-5px; width:16px; height:16px; border-radius:50%; background:#3b82f6; color:#fff; font-size:.6rem; font-weight:700; display:flex; align-items:center; justify-content:center; }
                .fr-export-btn { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; border:1px solid var(--border-color,rgba(255,255,255,.12)); border-radius:9px; background:transparent; color:var(--text-secondary,#94a3b8); font-size:.8rem; font-weight:600; cursor:pointer; transition:all .2s; }
                .fr-export-btn:hover { border-color:#10b981; color:#10b981; }
                .fr-export-menu { position:absolute; right:0; top:calc(100% + 6px); background:var(--card-bg,#1e293b); border:1px solid var(--border-color,rgba(255,255,255,.1)); border-radius:10px; min-width:150px; box-shadow:0 8px 24px rgba(0,0,0,.3); z-index:50; overflow:hidden; }
                .fr-export-item { display:flex; align-items:center; gap:9px; padding:10px 14px; color:var(--text-primary,#f1f5f9); font-size:.85rem; text-decoration:none; transition:background .15s; }
                .fr-export-item:hover { background:rgba(255,255,255,.05); }

                /* Status chips */
                .fr-chips { display:flex; gap:.5rem; flex-wrap:wrap; }
                .fr-chip { display:inline-flex; align-items:center; gap:6px; padding:6px 13px; border:1px solid transparent; border-radius:999px; font-size:.78rem; font-weight:600; cursor:pointer; transition:all .18s; background:rgba(255,255,255,.04); color:var(--text-secondary,#94a3b8); }
                .fr-chip:hover { background:rgba(255,255,255,.08); }
                .fr-chip.active { color:#fff; border-color:transparent; }
                .fr-chip-all.active   { background:rgba(99,102,241,.25); color:#a5b4fc; border-color:rgba(99,102,241,.35); }
                .fr-chip-active.active  { background:rgba(16,185,129,.2); color:#34d399; border-color:rgba(16,185,129,.35); }
                .fr-chip-inactive.active{ background:rgba(239,68,68,.2); color:#f87171; border-color:rgba(239,68,68,.35); }
                .fr-chip-ferias.active  { background:rgba(59,130,246,.2); color:#60a5fa; border-color:rgba(59,130,246,.35); }
                .fr-chip-count { opacity:.65; font-size:.7rem; }
                .fr-dot { width:7px; height:7px; border-radius:50%; display:inline-block; }
                .fr-dot-green{ background:#10b981; }
                .fr-dot-red  { background:#ef4444; }
                .fr-dot-blue { background:#3b82f6; }

                /* Advanced filters */
                .fr-adv-filters { display:none; flex-wrap:wrap; gap:.5rem; align-items:center; padding-top:.25rem; }
                .fr-adv-filters.fr-adv-open { display:flex; flex-basis:100%; width:100%; }
                .fr-select { padding:7px 10px; border:1px solid var(--border-color,rgba(255,255,255,.12)); border-radius:8px; background:var(--input-bg,#0f172a); color:var(--text-primary,#f1f5f9); font-size:.78rem; cursor:pointer; outline:none; }
                .fr-select:focus { border-color:#3b82f6; }
                .fr-clear-btn { padding:6px 12px; border:1px solid rgba(239,68,68,.3); border-radius:8px; background:rgba(239,68,68,.08); color:#f87171; font-size:.78rem; cursor:pointer; transition:all .18s; }
                .fr-clear-btn:hover { background:rgba(239,68,68,.15); }

                /* Table */
                .fr-table { border-collapse:separate; border-spacing:0; width:100%; }
                .fr-thead-row th { padding:.75rem 1rem; font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#64748b; border-bottom:1px solid var(--border-color,rgba(255,255,255,.07)); background:transparent; }
                .fr-th-check { width:48px; text-align:center; }
                .fr-th-emp   { min-width:220px; }
                .fr-th-role  { min-width:160px; }
                .fr-th-status{ width:140px; }
                .fr-th-acts  { width:110px; text-align:center; }

                /* Rows */
                .fr-row { transition:background .15s; }
                .fr-row:hover { background:rgba(59,130,246,.05) !important; }
                .fr-row-dim { opacity:.6; }
                .fr-row-dim:hover { opacity:.85; }

                /* Cells */
                .fr-td-check { width:48px; text-align:center; padding:.875rem 0; vertical-align:middle; }
                .fr-td-emp   { padding:.75rem 1rem .75rem .5rem; vertical-align:middle; }
                .fr-td-role  { padding:.75rem 1rem; vertical-align:middle; }
                .fr-td-status{ padding:.75rem .5rem; vertical-align:middle; }
                .fr-td-acts  { padding:.75rem .5rem; vertical-align:middle; text-align:center; }

                /* Employee cell */
                .fr-emp-cell { display:flex; align-items:center; gap:12px; }
                .fr-av { width:40px; height:40px; border-radius:12px; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; font-size:.9rem; flex-shrink:0; overflow:hidden; }
                .fr-av-img { width:100%; height:100%; object-fit:cover; }
                .fr-emp-info { display:flex; flex-direction:column; gap:1px; min-width:0; }
                .fr-emp-name { font-size:.88rem; font-weight:600; color:var(--text-primary,#f1f5f9); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
                .fr-emp-email { font-size:.72rem; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
                .fr-contract-badge { display:inline-flex; align-items:center; gap:4px; margin-top:4px; padding:2px 6px; border-radius:4px; font-size:.65rem; font-weight:600; width:fit-content; }
                .fr-contract-expired { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; }
                .fr-contract-expiring{ background:#fffbeb; color:#d97706; border:1px solid #fde68a; }

                /* Roteiro do dia: mini timeline horizontal compacta (célula da tabela) */
                .fr-td-roteiro { max-width:1px; }
                .fr-roteiro { display:flex; align-items:center; gap:.3rem; white-space:nowrap; overflow:hidden; font-size:.78rem; }
                .fr-roteiro-item { display:inline-flex; align-items:center; gap:.3rem; flex-shrink:0; }
                .fr-roteiro-dot {
                    width:17px; height:17px; border-radius:50%; flex-shrink:0;
                    display:flex; align-items:center; justify-content:center;
                    font-size:.58rem; color:#0f172a; box-shadow:0 0 0 2px rgba(255,255,255,.05);
                }
                .fr-roteiro-dot.in       { background:#4ade80; }
                .fr-roteiro-dot.regresso { background:#86efac; }
                .fr-roteiro-dot.pausa    { background:#fbbf24; }
                .fr-roteiro-dot.out      { background:#f87171; }
                .fr-roteiro-dot.ativo    { background:#38bdf8; }
                .fr-roteiro-time  { font-weight:700; color:var(--text-primary,#e2e8f0); }
                .fr-roteiro-label { color:var(--text-secondary,#94a3b8); font-size:.72rem; }
                .fr-roteiro-sep { width:14px; height:2px; background:rgba(255,255,255,.14); flex-shrink:0; border-radius:2px; }
                .fr-roteiro-more {
                    flex-shrink:0; font-size:.68rem; font-weight:700; color:#60a5fa;
                    background:rgba(59,130,246,.14); padding:.1rem .45rem; border-radius:999px;
                }

                /* Roteiro do dia: timeline vertical completa (modal "Ver Detalhes") */
                .roteiro-dia { margin:0; padding:0; }
                .roteiro-evento { display:grid; grid-template-columns:3rem 1.25rem 1fr; align-items:flex-start; gap:0 .5rem; position:relative; }
                .roteiro-hora { font-size:.8rem; font-weight:700; font-variant-numeric:tabular-nums; color:var(--text-secondary,#94a3b8); text-align:right; padding-top:.05rem; line-height:1.4rem; }
                .roteiro-dot-col { display:flex; flex-direction:column; align-items:center; }
                .roteiro-dot { width:.75rem; height:.75rem; border-radius:50%; border:2px solid currentColor; background:#0f172a; flex-shrink:0; margin-top:.3rem; }
                .roteiro-line { width:2px; flex:1; min-height:1.6rem; background:rgba(255,255,255,.1); margin-bottom:-2px; }
                .roteiro-info { padding-bottom:1.1rem; }
                .roteiro-lbl { font-size:.85rem; font-weight:600; color:var(--text-primary,#e2e8f0); display:flex; align-items:center; gap:.4rem; flex-wrap:wrap; line-height:1.4rem; }
                .roteiro-lbl .fas { font-size:.78rem; }
                .tl-entrada  .roteiro-dot, .tl-entrada  .roteiro-lbl { color:#22c55e; }
                .tl-regresso .roteiro-dot, .tl-regresso .roteiro-lbl { color:#4ade80; }
                .tl-pausa    .roteiro-dot, .tl-pausa    .roteiro-lbl { color:#f59e0b; }
                .tl-saida    .roteiro-dot, .tl-saida    .roteiro-lbl { color:#f87171; }
                .tl-ativo    .roteiro-dot, .tl-ativo    .roteiro-lbl { color:#38bdf8; }
                .tl-entrada  .roteiro-dot { background:#22c55e; }
                .tl-regresso .roteiro-dot { background:#4ade80; }
                .tl-pausa    .roteiro-dot { background:#f59e0b; }
                .tl-saida    .roteiro-dot { background:#f87171; }
                @keyframes rot-pulse-dot { 0%,100%{box-shadow:0 0 0 0 rgba(56,189,248,.5)} 50%{box-shadow:0 0 0 5px rgba(56,189,248,0)} }
                .tl-ativo .roteiro-dot { animation:rot-pulse-dot 1.8s ease-in-out infinite; background:#38bdf8; }

                /* Role cell */
                .fr-role-pos  { display:block; font-size:.83rem; font-weight:600; color:var(--text-primary,#f1f5f9); }
                .fr-role-dept { display:inline-flex; align-items:center; margin-top:4px; padding:2px 8px; background:rgba(99,102,241,.12); color:#a5b4fc; border-radius:4px; font-size:.68rem; font-weight:600; }

                /* Presence pills */
                .fr-presence { display:inline-flex; align-items:center; gap:5px; margin-top:5px; padding:2px 7px; border-radius:999px; font-size:.68rem; font-weight:600; }
                .fr-pdot { width:6px; height:6px; border-radius:50%; display:inline-block; }
                .fr-p-present  { background:rgba(16,185,129,.1); color:#10b981; }
                .fr-p-present .fr-pdot { background:#10b981; }
                .fr-p-late     { background:rgba(245,158,11,.1); color:#f59e0b; }
                .fr-p-late .fr-pdot { background:#f59e0b; }
                .fr-p-absent   { background:rgba(239,68,68,.1); color:#ef4444; }
                .fr-p-absent .fr-pdot { background:#ef4444; }
                .fr-p-unknown  { background:rgba(100,116,139,.1); color:#64748b; }
                .fr-p-unknown .fr-pdot { background:#475569; }

                /* Action buttons */
                .fr-acts { display:flex; align-items:center; justify-content:center; gap:5px; }
                .fr-btn { width:32px; height:32px; border-radius:8px; border:none; display:inline-flex; align-items:center; justify-content:center; font-size:.78rem; cursor:pointer; transition:all .18s; }
                .fr-btn-view   { background:rgba(59,130,246,.12); color:#3b82f6; }
                .fr-btn-view:hover{ background:#3b82f6; color:#fff; }
                .fr-btn-edit   { background:rgba(234,179,8,.12); color:#ca8a04; }
                .fr-btn-edit:hover{ background:#eab308; color:#fff; }
                .fr-btn-deact  { background:rgba(239,68,68,.1); color:#ef4444; }
                .fr-btn-deact:hover{ background:#ef4444; color:#fff; }
                .fr-btn-activate{ background:rgba(16,185,129,.12); color:#10b981; }
                .fr-btn-activate:hover{ background:#10b981; color:#fff; }
                .fr-btn-off    { opacity:.35; cursor:not-allowed; }
                .fr-btn:hover:not(:disabled) { transform:translateY(-1px); box-shadow:0 3px 10px rgba(0,0,0,.2); }
                .fr-btn:disabled { opacity:.35; cursor:not-allowed; filter:grayscale(.6); }
                .fr-btn:disabled:hover { background:inherit; color:inherit; }

                /* Checkbox */
                .fr-checkbox { width:16px; height:16px; accent-color:#3b82f6; cursor:pointer; }

                .active-relatorio-card {
                    background: linear-gradient(120deg, #2563eb 0%, #60a5fa 100%);
                    color: #fff;
                    border: 2px solid #2563eb;
                    box-shadow: 0 8px 24px 0 #2563eb44, 0 2px 8px #2563eb22;
                    transform: translateY(-6px) scale(1.03);
                    z-index: 2;
                    transition: box-shadow 0.2s, border 0.2s, background 0.2s, transform 0.18s;
                }

                @keyframes slideDown {
                    from {
                        opacity: 0;
                        transform: translateY(-15px);
                    }

                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }

                #employeesTable .employee-actions,
                #turnosTable .employee-actions,
                #assiduidade-section .employee-actions,
                #folha-pagamento-section .employee-actions,
                #feriasSectionTable .employee-actions,
                #gorjetas-section .employee-actions,
                #gorjetas-section .gorjeta-actions {
                    display: flex;
                    gap: 0.4rem;
                    flex-wrap: nowrap;
                    justify-content: center;
                    align-items: center;
                    padding: 0;
                    margin: 0;
                }

                #employeesTable .employee-action-btn,
                #turnosTable .employee-action-btn,
                #assiduidade-section .employee-action-btn,
                #folha-pagamento-section .employee-action-btn,
                #feriasSectionTable .employee-action-btn,
                #gorjetas-section .employee-action-btn {
                    min-width: 66px !important;
                    height: 30px !important;
                    min-height: 30px !important;
                    max-height: 30px !important;
                    padding: 0.3rem 0.5rem !important;
                    border-radius: 7px !important;
                    font-size: 0.74rem !important;
                    line-height: 1 !important;
                    font-weight: 700;
                    letter-spacing: 0.01em;
                    gap: 0.26rem !important;
                    box-shadow: 0 3px 10px rgba(15, 23, 42, 0.14);
                    transition: transform 0.14s ease, box-shadow 0.18s ease, filter 0.18s ease;
                }

                #employeesTable .employee-action-btn i,
                #turnosTable .employee-action-btn i,
                #assiduidade-section .employee-action-btn i,
                #folha-pagamento-section .employee-action-btn i,
                #feriasSectionTable .employee-action-btn i,
                #gorjetas-section .employee-action-btn i {
                    font-size: 0.72rem !important;
                }

                #employeesTable .employee-action-btn:not(:disabled):hover,
                #turnosTable .employee-action-btn:not(:disabled):hover,
                #assiduidade-section .employee-action-btn:not(:disabled):hover,
                #folha-pagamento-section .employee-action-btn:not(:disabled):hover,
                #feriasSectionTable .employee-action-btn:not(:disabled):hover,
                #gorjetas-section .employee-action-btn:not(:disabled):hover {
                    transform: translateY(-1px);
                    box-shadow: 0 6px 14px rgba(15, 23, 42, 0.2);
                    filter: brightness(1.03);
                }

                #employeesTable .employee-action-btn.btn-activate,
                #turnosTable .employee-action-btn.btn-activate,
                #assiduidade-section .employee-action-btn.btn-activate,
                #folha-pagamento-section .employee-action-btn.btn-activate,
                #gorjetas-section .employee-action-btn.btn-activate {
                    min-width: 62px !important;
                    background: linear-gradient(145deg, #10b981, #059669) !important;
                }

                #presencaTable td:last-child {
                    text-align: center;
                }

                #presencaTable td:last-child .employee-actions {
                    width: 100%;
                    justify-content: center !important;
                }

                #gorjetasTable thead th:last-child {
                    text-align: center !important;
                    padding-right: 1rem !important;
                }

                #gorjetasTable tbody td:last-child {
                    display: table-cell !important;
                    text-align: center !important;
                    padding-right: 1rem !important;
                }

                #gorjetasTable tbody td:last-child .employee-actions {
                    width: 100%;
                    justify-content: center !important;
                }

                @media (max-width: 1200px) {
                    #employeesTable .employee-actions,
                    #turnosTable .employee-actions,
                    #assiduidade-section .employee-actions,
                    #folha-pagamento-section .employee-actions,
                    #gorjetas-section .employee-actions,
                    #gorjetas-section .gorjeta-actions {
                        flex-wrap: wrap;
                        justify-content: flex-end;
                    }

                    #presencaTable td:last-child .employee-actions {
                        justify-content: center !important;
                    }
                }

                /* ── Add Employee Modal (am-*) ────────────────── */
                #addEmployeeModal { overflow-y:auto; padding:24px 16px 48px; }
                #turnoModal, #bulkTurnoModal, #turnoSwapModal { overflow-y:auto; padding:24px 16px 48px; }
                #gorjetaModal, #gorjetaViewModal { overflow-y:auto; padding:24px 16px 48px; }
                .am-sheet {
                    background:#0f172a;
                    border:1px solid rgba(255,255,255,.1);
                    border-radius:20px;
                    width:100%; max-width:660px;
                    padding:28px 28px 24px;
                    position:relative;
                    box-shadow:0 24px 60px rgba(0,0,0,.5);
                    margin:0 auto;
                }
                .am-close {
                    position:absolute; top:14px; right:14px;
                    background:rgba(255,255,255,.07); border:none;
                    color:#94a3b8; width:32px; height:32px;
                    border-radius:8px; font-size:19px; cursor:pointer;
                    display:grid; place-items:center; transition:background .15s,color .15s;
                    line-height:1;
                }
                .am-close:hover { background:rgba(255,255,255,.14); color:#e2e8f0; }
                .am-header {
                    display:flex; align-items:center; gap:14px;
                    margin-bottom:20px; padding-bottom:16px;
                    border-bottom:1px solid rgba(255,255,255,.08);
                }
                .am-header-icon {
                    width:44px; height:44px; border-radius:12px; flex-shrink:0;
                    background:linear-gradient(135deg,#3b82f6,#2563eb);
                    display:grid; place-items:center; color:#fff; font-size:18px;
                    box-shadow:0 6px 16px rgba(37,99,235,.35);
                }
                .am-title { margin:0; font-size:1.2rem; font-weight:700; color:#e2e8f0; }
                .am-subtitle { margin:2px 0 0; font-size:.78rem; color:#64748b; }
                .am-error {
                    background:rgba(239,68,68,.1); border:1px solid rgba(239,68,68,.3);
                    color:#fca5a5; padding:10px 14px; border-radius:10px;
                    font-size:.85rem; margin-bottom:14px;
                }
                /* Avatar row */
                .am-avatar-row {
                    display:flex; align-items:center; gap:16px;
                    margin-bottom:20px; padding:14px 16px;
                    background:rgba(255,255,255,.04);
                    border-radius:12px; border:1px solid rgba(255,255,255,.07);
                }
                .am-av-preview {
                    width:68px; height:68px; border-radius:50%;
                    background:linear-gradient(135deg,#667eea,#764ba2);
                    display:grid; place-items:center; color:#fff;
                    font-size:26px; overflow:hidden; flex-shrink:0;
                    border:3px solid rgba(255,255,255,.1);
                }
                .am-av-preview img { width:100%;height:100%;object-fit:cover;border-radius:50%; }
                .am-file-label {
                    display:inline-flex; align-items:center; gap:6px;
                    padding:7px 14px; border-radius:8px;
                    background:rgba(59,130,246,.14); color:#93c5fd;
                    font-size:.8rem; font-weight:600; cursor:pointer;
                    border:1px solid rgba(59,130,246,.25); transition:background .15s;
                }
                .am-file-label:hover { background:rgba(59,130,246,.25); }
                .am-av-hint { display:block; font-size:.72rem; color:#475569; margin-top:5px; }
                /* Sections */
                .am-section { margin-bottom:18px; }
                .am-sec-lbl {
                    font-size:.7rem; font-weight:700; color:#64748b;
                    text-transform:uppercase; letter-spacing:.07em;
                    display:flex; align-items:center; gap:6px;
                    margin-bottom:10px; padding-bottom:8px;
                    border-bottom:1px solid rgba(255,255,255,.06);
                }
                .am-sec-lbl i { color:#3b82f6; }
                /* Grids */
                .am-g2 { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
                .am-g3 { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; }
                .am-f { display:flex; flex-direction:column; }
                .am-f-full { grid-column:1/-1; }
                .am-lbl { font-size:.75rem; font-weight:600; color:#94a3b8; margin-bottom:4px; }
                .am-opt { font-weight:400; opacity:.6; }
                .am-inp {
                    background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1);
                    border-radius:8px; padding:9px 12px; color:#e2e8f0;
                    font-size:.875rem; outline:none; width:100%; box-sizing:border-box;
                    transition:border-color .15s, background .15s;
                }
                .am-inp::placeholder { color:#475569; }
                .am-inp:focus { border-color:#3b82f6; background:rgba(59,130,246,.07); }
                .am-sel {
                    cursor:pointer; appearance:none;
                    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%2364748b' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
                    background-repeat:no-repeat; background-position:right 10px center; padding-right:30px;
                }
                .am-sel option { background:#1e293b; color:#e2e8f0; }
                .am-ico-wrap { position:relative; }
                .am-ico {
                    position:absolute; left:10px; top:50%; transform:translateY(-50%);
                    color:#475569; font-size:.78rem; pointer-events:none;
                }
                .am-inp-ico { padding-left:28px; }
                .am-hint { font-size:.7rem; color:#475569; margin-top:3px; }
                /* Footer */
                .am-footer {
                    display:flex; justify-content:flex-end; gap:10px;
                    margin-top:22px; padding-top:16px;
                    border-top:1px solid rgba(255,255,255,.08);
                }
                .am-btn-cancel {
                    padding:10px 20px; border-radius:10px;
                    border:1px solid rgba(255,255,255,.11);
                    background:transparent; color:#94a3b8;
                    font-size:.875rem; font-weight:600; cursor:pointer; transition:background .15s;
                }
                .am-btn-cancel:hover { background:rgba(255,255,255,.06); }
                .am-btn-submit {
                    display:inline-flex; align-items:center; gap:8px;
                    padding:10px 22px; border-radius:10px; border:none;
                    background:linear-gradient(135deg,#3b82f6,#2563eb); color:#fff;
                    font-size:.875rem; font-weight:700; cursor:pointer;
                    box-shadow:0 4px 14px rgba(37,99,235,.3); transition:opacity .15s;
                }
                .am-btn-submit:hover { opacity:.9; }
                @media(max-width:580px){
                    .am-g2,.am-g3 { grid-template-columns:1fr; }
                    .am-sheet { padding:20px 14px; }
                }

                /* ── Edit / View modal overrides ────────────── */
                #editEmployeeModal { overflow-y:auto; padding:24px 16px 48px; }
                #viewEmployeeModal { overflow-y:auto; padding:24px 16px 48px; }
                .vm-sheet { max-width:700px; }
                /* View Modal — hero */
                .vm-hero {
                    display:flex; align-items:center; gap:20px;
                    margin-bottom:20px; padding:18px 20px;
                    background:rgba(255,255,255,.04);
                    border-radius:14px; border:1px solid rgba(255,255,255,.07);
                }
                .vm-hero-av {
                    width:80px; height:80px; border-radius:50%; overflow:hidden; flex-shrink:0;
                    background:linear-gradient(135deg,#667eea,#764ba2);
                    display:grid; place-items:center; color:#fff; font-size:32px;
                    border:3px solid rgba(255,255,255,.12);
                }
                .vm-hero-av img { width:100%;height:100%;object-fit:cover;border-radius:50%; }
                .vm-hero-info { flex:1; min-width:0; }
                .vm-hero-name { margin:0 0 3px; font-size:1.15rem; font-weight:700; color:#e2e8f0; }
                .vm-hero-pos { font-size:.82rem; color:#64748b; margin:0 0 8px; }
                /* View Modal — sections */
                .vm-section { margin-bottom:18px; }
                .vm-sec-lbl {
                    font-size:.7rem; font-weight:700; color:#64748b;
                    text-transform:uppercase; letter-spacing:.07em;
                    display:flex; align-items:center; gap:6px;
                    margin-bottom:10px; padding-bottom:8px;
                    border-bottom:1px solid rgba(255,255,255,.06);
                }
                .vm-sec-lbl i { color:#3b82f6; }
                .vm-g2 { display:grid; grid-template-columns:1fr 1fr; gap:12px 20px; }
                .vm-g3 { display:grid; grid-template-columns:repeat(3,1fr); gap:12px 16px; }
                .vm-full { grid-column:1/-1; }
                .vm-field-label { font-size:.7rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:.05em; margin-bottom:3px; }
                .vm-field-value { font-size:.88rem; color:#cbd5e1; min-height:1.1em; }
                /* Ponto box */
                .vm-ponto-box {
                    background:rgba(16,185,129,.07); border:1px solid rgba(16,185,129,.18);
                    border-radius:10px; padding:14px; margin-top:12px;
                }
                /* History */
                .vm-history-box {
                    background:rgba(139,92,246,.06); border:1px solid rgba(139,92,246,.18);
                    border-radius:10px; padding:10px;
                    max-height:200px; overflow-y:auto;
                    color:#c4b5fd; font-size:.85rem;
                }
                /* Docs */
                .vm-checklist {
                    padding:10px 12px; border:1px solid rgba(148,163,184,.2);
                    border-radius:8px; background:rgba(15,23,42,.4);
                    color:#94a3b8; font-size:.83rem; margin-bottom:10px;
                }
                .vm-docs-list { max-height:280px; overflow-y:auto; }
                /* PDF button */
                .vm-btn-pdf {
                    display:inline-flex; align-items:center; gap:8px;
                    padding:10px 20px; border-radius:10px;
                    border:1px solid rgba(239,68,68,.3);
                    background:rgba(239,68,68,.1); color:#fca5a5;
                    font-size:.875rem; font-weight:600; cursor:pointer; transition:background .15s;
                }
                .vm-btn-pdf:hover { background:rgba(239,68,68,.2); }
                @media(max-width:580px){
                    .vm-g2,.vm-g3 { grid-template-columns:1fr; }
                    .vm-hero { flex-direction:column; text-align:center; }
                }

                /* ── Upload Document Modal drop zone (udm-*) ──── */
                #uploadDocumentModal { overflow-y:auto; padding:24px 16px 48px; }
                .udm-dropzone {
                    display:flex; flex-direction:column; align-items:center;
                    justify-content:center; gap:6px; padding:26px 16px;
                    border:2px dashed rgba(59,130,246,.28);
                    border-radius:12px; background:rgba(59,130,246,.04);
                    cursor:pointer; transition:border-color .2s, background .2s;
                    text-align:center;
                }
                .udm-dropzone:hover { border-color:rgba(59,130,246,.55); background:rgba(59,130,246,.09); }
                .udm-dz-icon { font-size:2rem; color:#3b82f6; opacity:.75; margin-bottom:2px; }
                .udm-dz-title { font-size:.875rem; font-weight:600; color:#cbd5e1; }
                .udm-dz-hint { font-size:.72rem; color:#475569; }
                .udm-dropzone.udm-has-file { border-color:rgba(16,185,129,.4); background:rgba(16,185,129,.05); border-style:solid; }
                .udm-dropzone.udm-has-file .udm-dz-icon { color:#10b981; opacity:1; }
                .udm-dropzone.udm-has-file .udm-dz-title { color:#6ee7b7; }
                </style>

                 <table id="employeesTable" class="table fr-table">
                    <thead>
                        <tr class="fr-thead-row">
                            <th class="fr-th-check">
                                <input type="checkbox" id="selectAllCheckbox" class="employee-checkbox fr-checkbox" title="Selecionar tudo">
                            </th>
                            <th class="fr-th-emp">Funcionário</th>
                            <th class="fr-th-role">Cargo &amp; Departamento</th>
                            <th class="fr-th-status">Estado</th>
                            <th class="fr-th-acts">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $employee):
                            $statusRaw = (string)($employee['status'] ?? '');
                            $statusNormalized = mb_strtolower(trim($statusRaw));
                            $isDisabledRow = in_array($statusNormalized, ['inactive', 'inativo', 'ferias', 'férias'], true);
                            $profilePicture = !empty($employee['profile_picture']) ? htmlspecialchars($employee['profile_picture']) : '';

                            $badgeClass = 'status-badge ';
                            switch ($statusRaw) {
                                case 'active':
                                    $badgeClass .= 'status-active';
                                    $statusLabel = 'Ativo';
                                    break;
                                case 'inactive':
                                    $badgeClass .= 'status-inactive';
                                    $statusLabel = 'Inativo';
                                    break;
                                case 'ferias':
                                    $badgeClass .= 'status-ferias';
                                    $statusLabel = 'Férias';
                                    break;
                                default:
                                    $badgeClass .= 'status-inactive';
                                    $statusLabel = $statusRaw !== '' ? $statusRaw : '—';
                                    break;
                            }

                            $rowAttributes = [
                                'data-employee-id' => $employee['id'] ?? '',
                                'data-name' => $employee['name'] ?? '',
                                'data-fullname' => $employee['name'] ?? '',
                                'data-position' => $employee['position'] ?? '',
                                'data-department' => $employee['department'] ?? '',
                                'data-email' => $employee['email'] ?? '',
                                'data-phone' => $employee['phone'] ?? '',
                                'data-start-date' => $employee['startDate'] ?? '',
                                'data-end-date' => $employee['endDate'] ?? '',
                                'data-vacation-days' => isset($employee['vacation_days']) ? (int)$employee['vacation_days'] : 22,
                                'data-contract-type' => $employee['contractType'] ?? '',
                                'data-status' => $statusRaw,
                                'data-status-label' => $statusLabel
                            ];

                            $attrString = '';
                            foreach ($rowAttributes as $attrKey => $attrValue) {
                                if ($attrValue === null || $attrValue === '') {
                                    continue;
                                }
                                $attrString .= ' ' . $attrKey . '="' . htmlspecialchars((string)$attrValue, ENT_QUOTES, 'UTF-8') . '"';
                            }
                            $isInactiveOnly = ($statusRaw === 'inactive' || $statusRaw === 'inativo');
                        ?>
                        <?php
                            $empId   = htmlspecialchars((string)($employee['id'] ?? ''), ENT_QUOTES, 'UTF-8');
                            $empName = htmlspecialchars($employee['name'] ?? '', ENT_QUOTES, 'UTF-8');
                            $nameParts = explode(' ', $empName);
                            $displayName = count($nameParts) > 1 ? $nameParts[0] . ' ' . end($nameParts) : $empName;
                            $empEmail = htmlspecialchars($employee['email'] ?? '', ENT_QUOTES, 'UTF-8');
                            $empPos   = htmlspecialchars($employee['position'] ?? '—', ENT_QUOTES, 'UTF-8');
                            $empDept  = htmlspecialchars($employee['department'] ?? '—', ENT_QUOTES, 'UTF-8');
                            $avatarInitials = strtoupper(substr($employee['name'] ?? 'U', 0, 2));

                            // Avatar gradient por letra
                            $gradients = ['#667eea,#764ba2','#f093fb,#f5576c','#4facfe,#00f2fe','#43e97b,#38f9d7','#fa709a,#fee140','#a18cd1,#fbc2eb','#ffecd2,#fcb69f','#a1c4fd,#c2e9fb','#fd7043,#ff8a65','#26c6da,#00acc1'];
                            $gi = ord($avatarInitials[0]) % count($gradients);
                            [$gc1, $gc2] = explode(',', $gradients[$gi]);

                            // Contract expiry
                            $expiryBadge = '';
                            $endDateStr = trim((string)($employee['endDate'] ?? ''));
                            if ($endDateStr !== '' && $endDateStr !== '0000-00-00') {
                                $endTs = strtotime($endDateStr);
                                if ($endTs !== false) {
                                    $daysLeft = (int)((strtotime(date('Y-m-d')) - $endTs) / -86400);
                                    if ($daysLeft < 0) {
                                        $expiryBadge = '<span class="fr-contract-badge fr-contract-expired"><i class="fas fa-exclamation-triangle"></i> Expirado</span>';
                                    } elseif ($daysLeft <= 30) {
                                        $expiryBadge = '<span class="fr-contract-badge fr-contract-expiring"><i class="fas fa-clock"></i> Expira em ' . $daysLeft . 'd</span>';
                                    }
                                }
                            }

                            // Presence pill
                            $pStatus = (string)($employee['presence_status'] ?? '');
                            if ($pStatus === 'presente') {
                                $presencePill = '<span class="fr-presence fr-p-present"><span class="fr-pdot"></span>Presente</span>';
                            } elseif ($pStatus === 'atrasado') {
                                $presencePill = '<span class="fr-presence fr-p-late"><span class="fr-pdot"></span>Atrasado</span>';
                            } elseif (in_array($pStatus, ['falta', 'falta_justificada'], true)) {
                                $presencePill = '<span class="fr-presence fr-p-absent"><span class="fr-pdot"></span>Falta</span>';
                            } elseif ($statusRaw === 'active') {
                                $presencePill = '<span class="fr-presence fr-p-unknown"><span class="fr-pdot"></span>Não registado</span>';
                            } else {
                                $presencePill = '';
                            }
                        ?>
                        <tr<?php echo $isDisabledRow ? ' class="disabled-row employee-row fr-row fr-row-dim"' : ' class="employee-row fr-row"'; echo $attrString; ?>>

                            <!-- Checkbox -->
                            <td class="fr-td-check">
                                <input type="checkbox" class="employee-checkbox fr-checkbox"
                                    data-employee-id="<?= $empId ?>"
                                    data-employee-name="<?= $empName ?>">
                            </td>

                            <!-- Funcionário: avatar + nome + email + expiry -->
                            <td class="fr-td-emp">
                                <div class="fr-emp-cell">
                                    <div class="fr-av" style="background:linear-gradient(135deg,<?= $gc1 ?>,<?= $gc2 ?>);">
                                        <?php if ($profilePicture): ?>
                                            <img src="../<?= $profilePicture ?>" alt="<?= $empName ?>" class="fr-av-img">
                                        <?php else: ?>
                                            <?= $avatarInitials ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="fr-emp-info">
                                        <span class="fr-emp-name"><?= $displayName ?></span>
                                        <?php if ($empEmail): ?>
                                            <span class="fr-emp-email"><?= $empEmail ?></span>
                                        <?php endif; ?>
                                        <?= $expiryBadge ?>
                                    </div>
                                </div>
                            </td>

                            <!-- Cargo + Departamento -->
                            <td class="fr-td-role">
                                <span class="fr-role-pos"><?= $empPos ?></span>
                                <?php if ($empDept && $empDept !== '—'): ?>
                                    <span class="fr-role-dept"><?= $empDept ?></span>
                                <?php endif; ?>
                            </td>

                            <!-- Estado + presença -->
                            <td class="fr-td-status">
                                <span class="<?= htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8') ?>"
                                    id="status-<?= $empId ?>">
                                    <?php
                                        $icon = 'fa-circle-info';
                                        if ($statusRaw === 'active')   $icon = 'fa-check-circle';
                                        elseif ($statusRaw === 'inactive') $icon = 'fa-times-circle';
                                        elseif ($statusRaw === 'ferias')   $icon = 'fa-umbrella-beach';
                                    ?>
                                    <i class="fas <?= $icon ?>"></i>
                                    <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <?= $presencePill ?>
                            </td>

                            <!-- Ações: icon-only buttons -->
                            <td class="fr-td-acts">
                                <div class="fr-acts">
                                    <button type="button" class="fr-btn fr-btn-view btn-view"
                                        data-id="<?= $empId ?>" title="Ver detalhes">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" class="fr-btn fr-btn-edit btn-edit<?= $isDisabledRow ? ' fr-btn-off' : '' ?>"
                                        data-id="<?= $empId ?>"
                                        <?= $isDisabledRow ? 'disabled title="Inativo ou em férias"' : 'title="Editar"' ?>>
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <?php if ($isDisabledRow): ?>
                                        <button type="button" class="fr-btn fr-btn-activate btn-activate"
                                            data-id="<?= $empId ?>" title="Ativar">
                                            <i class="fas fa-user-check"></i>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="fr-btn fr-btn-deact btn-employee-deactivate"
                                            data-id="<?= $empId ?>" title="Desativar">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                            <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div id="bulkActionsBar" class="bulk-actions-bar" aria-hidden="true">

                <div class="bulk-left">
                    <button type="button" class="bulk-close" onclick="closeBulkActionsBar()">
                        <i class="fas fa-times"></i>
                    </button>

                    <div class="bulk-info">
                        <i class="fas fa-check-square"></i>
                        <span id="bulkCount">
                            <strong>0</strong> funcionários selecionados
                        </span>
                    </div>
                </div>

                <div class="bulk-actions">
                    <div class="bulk-primary">
                        <button type="button" onclick="bulkMarkVacation()">
                            <i class="fas fa-umbrella-beach"></i> Férias
                        </button>

                        <button type="button" onclick="bulkChangeStatus()">
                            <i class="fas fa-toggle-on"></i> Status
                        </button>

                        <button type="button" onclick="bulkChangeDepartment()">
                            <i class="fas fa-exchange-alt"></i> Departamento
                        </button>

                        <button type="button" onclick="bulkExportSelected()">
                            <i class="fas fa-download"></i> Exportar
                        </button>

                        <button type="button" onclick="openNotifyModal()">
                            <i class="fas fa-sms"></i> Notificar
                        </button>
                    </div>

                    <div class="bulk-danger">
                        <button type="button" onclick="clearBulkSelection()" class="btn-secondary">
                            <i class="fas fa-times"></i> Limpar
                        </button>

                        <button type="button" onclick="bulkDeleteSelected()" class="btn-danger">
                            <i class="fas fa-trash-alt"></i> Excluir
                        </button>
                    </div>
                </div>

            </div>

            <div id="notifyModal" class="modal" style="display: none;">
                <div class="modal-content notify-modal-content"
                    style="max-width: 480px; background: #fafdff; border-radius: 18px; box-shadow: 0 8px 32px rgba(52,152,219,0.13); padding: 2.2rem 2.2rem 1.5rem 2.2rem; position: relative;">
                    <button class="bulk-close" aria-label="Fechar" onclick="closeNotifyModal()"
                        style="position: absolute; top: 18px; right: 22px; color: #217dbb; background: none; border: none; font-size: 2.1em; font-weight: 700; cursor: pointer; transition: color 0.2s; z-index: 2;">&times;</button>

                    <div class="notify-modal-header" style="text-align: center; margin-bottom: 1.2rem;">
                        <div
                            style="display: flex; justify-content: center; align-items: center; margin-bottom: 0.5rem;">
                            <span
                                style="background: linear-gradient(135deg, #3498db 0%, #217dbb 100%); color: #fff; border-radius: 50%; width: 56px; height: 56px; display: flex; align-items: center; justify-content: center; font-size: 2em; box-shadow: 0 2px 12px rgba(52,152,219,0.13);">
                                <i class="fas fa-comment-dots"></i>
                            </span>
                        </div>
                        <h2
                            style="color: #217dbb; font-size: 1.45em; font-weight: 800; margin: 0 0 0.2em 0; letter-spacing: 0.5px;">
                            Enviar SMS</h2>
                        <p style="color: #6c7a89; font-size: 1em; margin: 0;">Envie uma mensagem rápida para os
                            funcionários
                            selecionados.</p>
                    </div>

                    <div class="notify-audience"
                        style="display: flex; flex-direction: column; align-items: center; gap: 0.5rem; margin-bottom: 1.1rem;">
                        <span id="selectedCount" class="selected-count-badge"
                            style="background: #eaf6fb; color: #217dbb; font-weight: 700; border-radius: 8px; padding: 0.3em 1em; font-size: 1em;">0
                            funcionários selecionados</span>
                        <div id="notifyRecipientPreview" class="recipient-preview"
                            style="color: #7b8ca6; font-size: 0.98em; text-align: center;">Nenhum destinatário
                            selecionado.
                        </div>
                    </div>

                    <label for="smsMessage" class="notify-label"
                        style="font-weight: 700; color: #217dbb; margin-bottom: 0.4em; display: block;">Mensagem <span
                            style="font-weight:400; color:#7b8ca6;">(máx 160 caracteres)</span></label>
                    <div style="margin-bottom: 0.8em; background: #eef7fd; border: 1px solid #d9ebf8; border-radius: 10px; padding: 0.65em 0.85em;">
                        <div style="font-weight: 700; color: #217dbb; margin-bottom: 0.45em;">Canal de envio</div>
                        <div style="display: flex; flex-wrap: wrap; gap: 0.85em; color: #2c3e50; font-size: 0.97em;">
                            <label style="display: flex; align-items: center; gap: 0.35em; cursor: pointer;">
                                <input type="radio" name="notifyDeliveryMode" value="app" />
                                App do funcionário
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.35em; cursor: pointer;">
                                <input type="radio" name="notifyDeliveryMode" value="phone" />
                                Número de telefone
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.35em; cursor: pointer;">
                                <input type="radio" name="notifyDeliveryMode" value="both" checked />
                                Ambos
                            </label>
                        </div>
                    </div>
                    <textarea id="smsMessage" maxlength="160"
                        placeholder="Digite uma mensagem clara e objetiva para a equipa."
                        style="width: 100%; min-height: 90px; border-radius: 10px; border: 1.5px solid #dbeafe; background: #fafdff; color: #34495e; font-size: 1.08em; padding: 0.8em 1em; margin-bottom: 0.5em; box-shadow: 0 1px 4px rgba(52,152,219,0.04);"></textarea>
                    <div class="notify-meta-row"
                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.1em;">
                        <p id="charLimitMsg" style="display:none; margin:0; color:#e74c3c; font-size:0.98em;"></p>
                        <span id="smsCharCounter" style="color: #217dbb; font-size: 0.98em;">160 restantes</span>
                    </div>

                    <div class="modal-actions notify-actions"
                        style="display: flex; gap: 1.1em; justify-content: center;">
                        <button type="button" onclick="sendBulkSMS()" class="btn-primary"
                            style="background: linear-gradient(90deg, #3498db 0%, #217dbb 100%); color: #fff; font-weight: 700; border: none; border-radius: 8px; padding: 0.7em 2.1em; font-size: 1.08em; box-shadow: 0 2px 8px rgba(52,152,219,0.10); cursor: pointer; transition: background 0.2s;">
                            <i class="fas fa-paper-plane"></i> Enviar SMS
                        </button>
                        <button type="button" onclick="closeNotifyModal()" class="btn-danger"
                            style="background: #e74c3c; color: #fff; font-weight: 700; border: none; border-radius: 8px; padding: 0.7em 2.1em; font-size: 1.08em; box-shadow: 0 2px 8px rgba(231,76,60,0.10); cursor: pointer; transition: background 0.2s;">Cancelar</button>
                    </div>
                </div>
            </div>


            <div id="editEmployeeModal" class="modal" style="display:none;">
                <div class="am-sheet">

                    <button class="am-close" type="button"
                        onclick="document.getElementById('editEmployeeModal').style.display='none'"
                        aria-label="Fechar">&times;</button>

                    <div class="am-header">
                        <div class="am-header-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706);box-shadow:0 6px 16px rgba(217,119,6,.35);">
                            <i class="fas fa-user-edit"></i>
                        </div>
                        <div>
                            <h2 class="am-title">Editar Funcionário</h2>
                            <p class="am-subtitle">Actualize os dados do colaborador</p>
                        </div>
                    </div>

                    <div id="editEmployeeInlineError" class="am-error" style="display:none;"></div>

                    <form id="editEmployeeForm">
                        <input type="hidden" id="employee-id" name="id">

                        <!-- Avatar -->
                        <div class="am-avatar-row">
                            <div id="edit-avatar-preview" class="am-av-preview">
                                <span id="edit-avatar-initials">FN</span>
                            </div>
                            <div>
                                <label class="am-file-label" for="edit-profile-picture">
                                    <i class="fas fa-camera"></i> Alterar foto
                                </label>
                                <input type="file" id="edit-profile-picture" name="profile_picture" accept="image/*" style="display:none;">
                                <span class="am-av-hint">JPG, PNG &mdash; máx. 2 MB</span>
                            </div>
                        </div>

                        <!-- Básicas -->
                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-id-card"></i> Informações Básicas</div>
                            <div class="am-g2">
                                <div class="am-f am-f-full">
                                    <label class="am-lbl" for="edit-name">Nome Completo *</label>
                                    <input class="am-inp" type="text" id="edit-name" name="name" required>
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="edit-position">Cargo *</label>
                                    <input class="am-inp" type="text" id="edit-position" name="position" required>
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="edit-department">Departamento *</label>
                                    <input class="am-inp" type="text" id="edit-department" name="department" required>
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="edit-email">Email *</label>
                                    <input class="am-inp" type="email" id="edit-email" name="email" required>
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="edit-phone">Telefone</label>
                                    <input class="am-inp" type="text" id="edit-phone" name="phone">
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="edit-status">Estado</label>
                                    <select class="am-inp am-sel" id="edit-status" name="status" required>
                                        <option value="active">Ativo</option>
                                        <option value="inactive">Inativo</option>
                                        <option value="ferias">Férias</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Pessoal -->
                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-user"></i> Informações Pessoais</div>
                            <div class="am-g2">
                                <div class="am-f">
                                    <label class="am-lbl" for="edit-birthDate">Data de Nascimento</label>
                                    <input class="am-inp" type="date" id="edit-birthDate" name="birthDate">
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="edit-nif">NIF</label>
                                    <input class="am-inp" type="text" id="edit-nif" name="nif" placeholder="123456789" maxlength="9">
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="edit-niss">NISS</label>
                                    <input class="am-inp" type="text" id="edit-niss" name="niss" placeholder="12345678901" maxlength="11">
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="edit-emergencyContact">Contacto de Emergência</label>
                                    <input class="am-inp" type="text" id="edit-emergencyContact" name="emergencyContact" placeholder="Nome: +351 ...">
                                </div>
                                <div class="am-f am-f-full">
                                    <label class="am-lbl" for="edit-address">Morada</label>
                                    <input class="am-inp" type="text" id="edit-address" name="address" placeholder="Rua, Número, Cidade, Código Postal">
                                </div>
                            </div>
                        </div>

                        <!-- Contrato -->
                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-file-contract"></i> Contrato</div>
                            <div class="am-g2">
                                <div class="am-f">
                                    <label class="am-lbl" for="edit-startDate">Início</label>
                                    <input class="am-inp" type="date" id="edit-startDate" name="startDate">
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="edit-endDate">Fim <span class="am-opt">(branco = efectivo)</span></label>
                                    <input class="am-inp" type="date" id="edit-endDate" name="endDate">
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="edit-contractType">Tipo</label>
                                    <select class="am-inp am-sel" id="edit-contractType" name="contractType">
                                        <option value="">Selecione...</option>
                                        <option value="efetivo">Efetivo</option>
                                        <option value="temporario">Temporário</option>
                                        <option value="part-time">Part-time</option>
                                        <option value="estagio">Estágio</option>
                                        <option value="freelancer">Freelancer</option>
                                    </select>
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="edit-vacation-days">Dias de Férias</label>
                                    <input class="am-inp" type="number" id="edit-vacation-days" name="vacation_days" placeholder="22" min="0" max="365">
                                    <span class="am-hint">Mínimo legal PT: 22 dias</span>
                                </div>
                            </div>
                        </div>

                        <!-- Remuneração -->
                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-euro-sign"></i> Remuneração</div>
                            <div class="am-g3">
                                <div class="am-f">
                                    <label class="am-lbl" for="edit-salary_base">Salário Base</label>
                                    <div class="am-ico-wrap">
                                        <span class="am-ico">€</span>
                                        <input class="am-inp am-inp-ico" type="number" id="edit-salary_base" name="salary_base" placeholder="0.00" min="0" step="0.01">
                                    </div>
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="edit-subsidio_alimentacao">Sub. Alimentação</label>
                                    <div class="am-ico-wrap">
                                        <span class="am-ico"><i class="fas fa-utensils"></i></span>
                                        <input class="am-inp am-inp-ico" type="number" id="edit-subsidio_alimentacao" name="subsidio_alimentacao" placeholder="0.00" min="0" step="0.01">
                                    </div>
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="edit-bonus">Bónus</label>
                                    <div class="am-ico-wrap">
                                        <span class="am-ico"><i class="fas fa-gift"></i></span>
                                        <input class="am-inp am-inp-ico" type="number" id="edit-bonus" name="bonus" placeholder="0.00" min="0" step="0.01">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Alteração crítica -->
                        <div class="am-section">
                            <div class="am-sec-lbl" style="color:#f59e0b;border-color:rgba(245,158,11,.2);">
                                <i class="fas fa-triangle-exclamation" style="color:#f59e0b;"></i> Alteração Crítica
                            </div>
                            <div class="am-f">
                                <label class="am-lbl" for="edit-approval-reason">Motivo <span class="am-opt">(status, contrato ou remuneração)</span></label>
                                <textarea class="am-inp" id="edit-approval-reason" name="approval_reason" rows="2"
                                    placeholder="Explique o motivo quando alterar status, contrato ou remuneração." style="resize:vertical;"></textarea>
                            </div>
                        </div>

                        <!-- Acesso -->
                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-shield-alt"></i> Acesso</div>
                            <div class="am-g2">
                                <div class="am-f">
                                    <label class="am-lbl" for="edit-pin">PIN <span class="am-opt">(branco = manter actual)</span></label>
                                    <input class="am-inp" type="password" id="edit-pin" name="pin" placeholder="Novo PIN (4+ dígitos)" minlength="4" autocomplete="new-password">
                                </div>
                            </div>
                        </div>

                        <div class="am-footer">
                            <button type="button" class="am-btn-cancel"
                                onclick="document.getElementById('editEmployeeModal').style.display='none'">Cancelar</button>
                            <button type="submit" class="am-btn-submit" style="background:linear-gradient(135deg,#f59e0b,#d97706);box-shadow:0 4px 14px rgba(217,119,6,.28);">
                                <i class="fas fa-floppy-disk"></i> Guardar Alterações
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal para Adicionar Funcionário -->
            <div id="addEmployeeModal" class="modal" style="display:none;">
                <div class="am-sheet">

                    <button class="am-close close-btn-add" type="button" aria-label="Fechar">&times;</button>

                    <div class="am-header">
                        <div class="am-header-icon"><i class="fas fa-user-plus"></i></div>
                        <div>
                            <h2 class="am-title">Novo Funcionário</h2>
                            <p class="am-subtitle">Preencha os dados para registar o novo colaborador</p>
                        </div>
                    </div>

                    <div id="addEmployeeInlineError" class="am-error" style="display:none;"></div>

                    <form id="addEmployeeForm" enctype="multipart/form-data" autocomplete="off">

                        <!-- Foto de perfil -->
                        <div class="am-avatar-row">
                            <div id="add-avatar-preview" class="am-av-preview">
                                <i class="fas fa-user"></i>
                            </div>
                            <div>
                                <label class="am-file-label" for="add-profile-picture">
                                    <i class="fas fa-camera"></i> Escolher foto
                                </label>
                                <input type="file" id="add-profile-picture" name="profile_picture" accept="image/*" style="display:none;">
                                <span class="am-av-hint">JPG, PNG &mdash; máx. 2 MB</span>
                            </div>
                        </div>

                        <!-- Informações Básicas -->
                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-id-card"></i> Informações Básicas</div>
                            <div class="am-g2">
                                <div class="am-f am-f-full">
                                    <label class="am-lbl" for="add-name">Nome Completo *</label>
                                    <input class="am-inp" type="text" id="add-name" name="name" required placeholder="Ex: Ana Silva">
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="add-position">Cargo *</label>
                                    <input class="am-inp" type="text" id="add-position" name="position" required placeholder="Ex: Cozinheiro">
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="add-department">Departamento *</label>
                                    <input class="am-inp" type="text" id="add-department" name="department" required placeholder="Ex: Cozinha">
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="add-email">Email *</label>
                                    <input class="am-inp" type="email" id="add-email" name="email" required placeholder="funcionario@email.com">
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="add-phone">Telefone</label>
                                    <input class="am-inp" type="text" id="add-phone" name="phone" placeholder="+351 900 000 000">
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="add-status">Estado</label>
                                    <select class="am-inp am-sel" id="add-status" name="status" required>
                                        <option value="active">Ativo</option>
                                        <option value="inactive">Inativo</option>
                                        <option value="ferias">Férias</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Informações Pessoais -->
                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-user"></i> Informações Pessoais</div>
                            <div class="am-g2">
                                <div class="am-f">
                                    <label class="am-lbl" for="add-birthDate">Data de Nascimento</label>
                                    <input class="am-inp" type="date" id="add-birthDate" name="birthDate">
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="add-nif">NIF</label>
                                    <input class="am-inp" type="text" id="add-nif" name="nif" placeholder="123456789" maxlength="9">
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="add-niss">NISS</label>
                                    <input class="am-inp" type="text" id="add-niss" name="niss" placeholder="12345678901" maxlength="11">
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="add-emergencyContact">Contacto de Emergência</label>
                                    <input class="am-inp" type="text" id="add-emergencyContact" name="emergencyContact" placeholder="Nome: +351 ...">
                                </div>
                                <div class="am-f am-f-full">
                                    <label class="am-lbl" for="add-address">Morada</label>
                                    <input class="am-inp" type="text" id="add-address" name="address" placeholder="Rua, Número, Cidade, Código Postal">
                                </div>
                            </div>
                        </div>

                        <!-- Contrato -->
                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-file-contract"></i> Contrato</div>
                            <div class="am-g2">
                                <div class="am-f">
                                    <label class="am-lbl" for="add-startDate">Início *</label>
                                    <input class="am-inp" type="date" id="add-startDate" name="startDate" required>
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="add-endDate">Fim <span class="am-opt">(branco = efectivo)</span></label>
                                    <input class="am-inp" type="date" id="add-endDate" name="endDate">
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="add-contractType">Tipo</label>
                                    <select class="am-inp am-sel" id="add-contractType" name="contractType">
                                        <option value="">Selecione...</option>
                                        <option value="efetivo">Efetivo</option>
                                        <option value="temporario">Temporário</option>
                                        <option value="part-time">Part-time</option>
                                        <option value="estagio">Estágio</option>
                                        <option value="freelancer">Freelancer</option>
                                    </select>
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="add-vacation-days">Dias de Férias</label>
                                    <input class="am-inp" type="number" id="add-vacation-days" name="vacation_days" value="22" min="0" max="365" placeholder="22">
                                    <span class="am-hint">Mínimo legal PT: 22 dias</span>
                                </div>
                            </div>
                        </div>

                        <!-- Remuneração -->
                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-euro-sign"></i> Remuneração</div>
                            <div class="am-g3">
                                <div class="am-f">
                                    <label class="am-lbl" for="add-salary_base">Salário Base</label>
                                    <div class="am-ico-wrap">
                                        <span class="am-ico">€</span>
                                        <input class="am-inp am-inp-ico" type="number" id="add-salary_base" name="salary_base" placeholder="0.00" min="0" step="0.01">
                                    </div>
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="add-subsidio_alimentacao">Sub. Alimentação</label>
                                    <div class="am-ico-wrap">
                                        <span class="am-ico"><i class="fas fa-utensils"></i></span>
                                        <input class="am-inp am-inp-ico" type="number" id="add-subsidio_alimentacao" name="subsidio_alimentacao" placeholder="0.00" min="0" step="0.01">
                                    </div>
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="add-bonus">Bónus</label>
                                    <div class="am-ico-wrap">
                                        <span class="am-ico"><i class="fas fa-gift"></i></span>
                                        <input class="am-inp am-inp-ico" type="number" id="add-bonus" name="bonus" placeholder="0.00" min="0" step="0.01">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Acesso -->
                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-shield-alt"></i> Acesso</div>
                            <div class="am-g2">
                                <div class="am-f">
                                    <label class="am-lbl" for="add-pin">PIN <span class="am-opt">(opcional)</span></label>
                                    <input class="am-inp" type="password" id="add-pin" name="pin" placeholder="Mínimo 4 dígitos" minlength="4">
                                    <span class="am-hint">Deixe em branco para não definir PIN</span>
                                </div>
                            </div>
                        </div>

                        <div class="am-footer">
                            <button type="button" class="am-btn-cancel close-btn-add">Cancelar</button>
                            <button type="submit" class="am-btn-submit">
                                <i class="fas fa-user-plus"></i> Registar Funcionário
                            </button>
                        </div>

                    </form>
                </div>
            </div>

            <!-- Modal para Visualizar Detalhes do Funcionário -->
            <div id="viewEmployeeModal" class="modal" style="display:none;">
                <div class="am-sheet vm-sheet">

                    <button class="am-close close-btn-view" type="button" aria-label="Fechar">&times;</button>

                    <!-- Hero: avatar + nome + posição + estado -->
                    <div class="vm-hero">
                        <div id="view-avatar" class="vm-hero-av">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="vm-hero-info">
                            <h2 class="vm-hero-name" id="view-name"></h2>
                            <p class="vm-hero-pos">
                                <span id="view-position"></span>
                                <span id="vm-dept-sep"> &bull; </span>
                                <span id="view-department"></span>
                            </p>
                            <div id="view-status"></div>
                        </div>
                    </div>

                    <div id="employeeDetailsContent">

                        <!-- Básicas -->
                        <div class="vm-section">
                            <div class="vm-sec-lbl"><i class="fas fa-id-card"></i> Contacto</div>
                            <div class="vm-g2">
                                <div>
                                    <div class="vm-field-label">Email</div>
                                    <div class="vm-field-value" id="view-email"></div>
                                </div>
                                <div>
                                    <div class="vm-field-label">Telefone</div>
                                    <div class="vm-field-value" id="view-phone"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Pessoal -->
                        <div class="vm-section">
                            <div class="vm-sec-lbl"><i class="fas fa-user"></i> Informações Pessoais</div>
                            <div class="vm-g2">
                                <div>
                                    <div class="vm-field-label">Data de Nascimento</div>
                                    <div class="vm-field-value" id="view-birthDate"></div>
                                </div>
                                <div>
                                    <div class="vm-field-label">NIF</div>
                                    <div class="vm-field-value" id="view-nif"></div>
                                </div>
                                <div>
                                    <div class="vm-field-label">NISS</div>
                                    <div class="vm-field-value" id="view-niss"></div>
                                </div>
                                <div>
                                    <div class="vm-field-label">Contacto de Emergência</div>
                                    <div class="vm-field-value" id="view-emergencyContact"></div>
                                </div>
                                <div class="vm-full">
                                    <div class="vm-field-label">Morada</div>
                                    <div class="vm-field-value" id="view-address"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Contrato -->
                        <div class="vm-section">
                            <div class="vm-sec-lbl"><i class="fas fa-file-contract"></i> Contrato</div>
                            <div class="vm-g2">
                                <div>
                                    <div class="vm-field-label">Início</div>
                                    <div class="vm-field-value" id="view-startDate"></div>
                                </div>
                                <div>
                                    <div class="vm-field-label">Fim de Contrato</div>
                                    <div class="vm-field-value" id="view-endDate"></div>
                                </div>
                                <div>
                                    <div class="vm-field-label">Tipo</div>
                                    <div class="vm-field-value" id="view-contractType"></div>
                                </div>
                                <div>
                                    <div class="vm-field-label">Dias de Férias</div>
                                    <div class="vm-field-value" id="view-vacation-days"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Turno & Ponto -->
                        <div class="vm-section">
                            <div class="vm-sec-lbl" style="color:#10b981;">
                                <i class="fas fa-clock" style="color:#10b981;"></i> Turno &amp; Ponto
                            </div>
                            <div class="vm-g3">
                                <div>
                                    <div class="vm-field-label">Turno Atual</div>
                                    <div class="vm-field-value" id="view-turno-atual">—</div>
                                </div>
                                <div>
                                    <div class="vm-field-label">Horário</div>
                                    <div class="vm-field-value" id="view-turno-horario">—</div>
                                </div>
                                <div>
                                    <div class="vm-field-label">Status Turno</div>
                                    <div class="vm-field-value" id="view-turno-status">—</div>
                                </div>
                            </div>
                            <div class="vm-ponto-box">
                                <div class="vm-sec-lbl" style="border:none;padding:0;margin-bottom:8px;color:#10b981;">
                                    <i class="fas fa-stamp" style="color:#10b981;"></i> Último Registo de Ponto
                                </div>
                                <div class="vm-g3">
                                    <div>
                                        <div class="vm-field-label">Data</div>
                                        <div class="vm-field-value" id="view-ponto-data">—</div>
                                    </div>
                                    <div>
                                        <div class="vm-field-label">Entrada</div>
                                        <div class="vm-field-value" id="view-ponto-entrada" style="color:#10b981;font-weight:600;">—</div>
                                    </div>
                                    <div>
                                        <div class="vm-field-label">Saída</div>
                                        <div class="vm-field-value" id="view-ponto-saida" style="color:#ef4444;font-weight:600;">—</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Histórico -->
                        <div class="vm-section">
                            <div class="vm-sec-lbl" style="color:#8b5cf6;">
                                <i class="fas fa-scroll" style="color:#8b5cf6;"></i> Histórico Individual
                            </div>
                            <div id="view-employee-history" class="vm-history-box">
                                <div style="color:#64748b;font-size:.85rem;">Sem histórico disponível.</div>
                            </div>
                        </div>

                        <!-- Documentos -->
                        <div class="vm-section">
                            <div class="vm-sec-lbl" style="justify-content:space-between;">
                                <span style="display:flex;align-items:center;gap:6px;">
                                    <i class="fas fa-paperclip"></i> Documentos
                                </span>
                                <button onclick="openUploadDocumentModal()" type="button"
                                    class="am-btn-submit" style="padding:5px 12px;font-size:.76rem;margin-left:auto;background:linear-gradient(135deg,#10b981,#059669);box-shadow:none;">
                                    <i class="fas fa-upload"></i> Anexar
                                </button>
                            </div>
                            <div id="view-documents-checklist" class="vm-checklist">
                                Checklist documental será carregado com os documentos do funcionário.
                            </div>
                            <div id="view-documents-list" class="vm-docs-list">
                                <p style="color:#64748b;font-size:.85rem;text-align:center;padding:16px;">
                                    Selecione um funcionário para ver os documentos.</p>
                            </div>
                        </div>

                    </div>
                    </div>

                    <div class="am-footer">
                        <button onclick="downloadEmployeePDF()" type="button" class="vm-btn-pdf">
                            <i class="fas fa-file-pdf"></i> Baixar PDF
                        </button>
                        <button type="button" onclick="closeViewEmployeeModal(event)" class="am-btn-submit">
                            <i class="fas fa-times"></i> Fechar
                        </button>
                    </div>
                </div>
            </div>

            <!-- Modal Upload Documento -->
            <div id="uploadDocumentModal" class="modal" style="display:none;">
                <div class="am-sheet" style="max-width:520px;">

                    <button class="am-close close-btn-upload-doc" type="button" aria-label="Fechar">&times;</button>

                    <div class="am-header">
                        <div class="am-header-icon" style="background:linear-gradient(135deg,#10b981,#059669);box-shadow:0 6px 16px rgba(5,150,105,.35);">
                            <i class="fas fa-file-upload"></i>
                        </div>
                        <div>
                            <h2 class="am-title">Anexar Documento</h2>
                            <p class="am-subtitle">Associe um ficheiro ao perfil do funcionário</p>
                        </div>
                    </div>

                    <form id="uploadDocumentForm" enctype="multipart/form-data">
                        <input type="hidden" id="upload-employee-id" name="employee_id">

                        <!-- Tipo -->
                        <div class="am-section">
                            <div class="am-f">
                                <label class="am-lbl" for="upload-document-type">Tipo de Documento *</label>
                                <select class="am-inp am-sel" id="upload-document-type" name="document_type" required>
                                    <option value="">Selecione o tipo...</option>
                                    <option value="Contrato">Contrato</option>
                                    <option value="Certidão">Certidão</option>
                                    <option value="Identificação">Identificação (BI/CC)</option>
                                    <option value="Comprovativo Morada">Comprovativo de Morada</option>
                                    <option value="Outros">Outros</option>
                                </select>
                            </div>
                        </div>

                        <!-- Ficheiro -->
                        <div class="am-section">
                            <label class="am-lbl">Ficheiro *</label>
                            <label class="udm-dropzone" for="upload-document-file" id="udmDropzone">
                                <div class="udm-dz-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                                <span class="udm-dz-title" id="udm-file-name">Clique para escolher ou arraste aqui</span>
                                <span class="udm-dz-hint">PDF, DOC, DOCX, JPG, PNG, XLS &mdash; máx. 5 MB</span>
                            </label>
                            <input type="file" id="upload-document-file" name="document"
                                accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.txt,.xls,.xlsx" required style="display:none;">
                        </div>

                        <!-- Descrição -->
                        <div class="am-section">
                            <div class="am-f">
                                <label class="am-lbl" for="upload-description">Descrição <span class="am-opt">(opcional)</span></label>
                                <textarea class="am-inp" id="upload-description" name="description" rows="3"
                                    placeholder="Observações sobre o documento..." style="resize:vertical;"></textarea>
                            </div>
                        </div>

                        <!-- Validade -->
                        <div class="am-section">
                            <div class="am-f">
                                <label class="am-lbl" for="upload-expiry-date">
                                    Data de Validade <span class="am-opt">(opcional)</span>
                                </label>
                                <input class="am-inp" type="date" id="upload-expiry-date" name="expiry_date">
                                <span class="am-hint"><i class="fas fa-bell" style="color:#f59e0b;font-size:.7rem;"></i> Receberá alertas automáticos antes da expiração</span>
                            </div>
                        </div>

                        <div class="am-footer">
                            <button type="button" class="am-btn-cancel close-btn-upload-doc">Cancelar</button>
                            <button type="submit" class="am-btn-submit" style="background:linear-gradient(135deg,#10b981,#059669);box-shadow:0 4px 14px rgba(5,150,105,.3);">
                                <i class="fas fa-upload"></i> Enviar Documento
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal: Marcar Férias em Lote -->
            <div id="bulkVacationModal" class="modal" style="display: none;">
                <div class="modal-content" style="max-width: 500px;">
                    <span class="close-btn" onclick="document.getElementById('bulkVacationModal').style.display='none'"
                        style="color: #ecf0f1; float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
                    <h3 style="text-align: center; color: #3498db; margin-bottom: 25px;">
                        <i class="fas fa-umbrella-beach"></i> Marcar Férias em Lote
                    </h3>

                    <form id="bulkVacationForm">
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 8px; color: #ecf0f1; font-weight: 600;">
                                Data Início
                            </label>
                            <input type="date" id="vacationStartDate" name="start_date" required
                                style="width: 100%; padding: 12px; border: 2px solid #34495e; border-radius: 8px; background: #2c3e50; color: #ecf0f1; font-size: 14px;">
                        </div>

                        <div class="form-group">
                            <label style="display: block; margin-bottom: 8px; color: #ecf0f1; font-weight: 600;">
                                Data Fim
                            </label>
                            <input type="date" id="vacationEndDate" name="end_date" required
                                style="width: 100%; padding: 12px; border: 2px solid #34495e; border-radius: 8px; background: #2c3e50; color: #ecf0f1; font-size: 14px;">
                        </div>

                        <div class="form-group">
                            <label style="display: block; margin-bottom: 8px; color: #ecf0f1; font-weight: 600;">
                                Observação
                            </label>
                            <textarea id="vacationNote" name="note" rows="3"
                                style="width: 100%; padding: 12px; border: 2px solid #34495e; border-radius: 8px; background: #2c3e50; color: #ecf0f1; font-size: 14px;"></textarea>
                        </div>

                        <div style="display: flex; gap: 1rem; margin-top: 20px;">
                            <button type="submit" class="btn"
                                style="flex: 1; background: #3498db; color: white; padding: 12px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                                <i class="fas fa-check"></i> Confirmar
                            </button>
                            <button type="button"
                                onclick="document.getElementById('bulkVacationModal').style.display='none'" class="btn"
                                style="flex: 1; background: #95a5a6; color: white; padding: 12px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                                <i class="fas fa-times"></i> Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal: Alterar Status em Lote -->
            <div id="bulkStatusModal" class="modal" style="display: none;">
                <div class="modal-content" style="max-width: 500px;">
                    <span class="close-btn" onclick="document.getElementById('bulkStatusModal').style.display='none'"
                        style="color: #ecf0f1; float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
                    <h3 style="text-align: center; color: #3498db; margin-bottom: 25px;">
                        <i class="fas fa-toggle-on"></i> Alterar Status em Lote
                    </h3>

                    <form id="bulkStatusForm">
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 8px; color: #ecf0f1; font-weight: 600;">
                                Novo Status
                            </label>
                            <select id="bulkNewStatus" name="status" required
                                style="width: 100%; padding: 12px; border: 2px solid #34495e; border-radius: 8px; background: #2c3e50; color: #ecf0f1; font-size: 14px;">
                                <option value="">Selecione...</option>
                                <option value="active">Ativo</option>
                                <option value="inactive">Inativo</option>
                                <option value="ferias">Férias</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label style="display: block; margin-bottom: 8px; color: #ecf0f1; font-weight: 600;">
                                Razão (Opcional)
                            </label>
                            <textarea id="bulkStatusReason" name="reason" rows="2"
                                style="width: 100%; padding: 12px; border: 2px solid #34495e; border-radius: 8px; background: #2c3e50; color: #ecf0f1; font-size: 14px;"></textarea>
                        </div>

                        <div style="display: flex; gap: 1rem; margin-top: 20px;">
                            <button type="submit" class="btn"
                                style="flex: 1; background: #3498db; color: white; padding: 12px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                                <i class="fas fa-check"></i> Confirmar
                            </button>
                            <button type="button"
                                onclick="document.getElementById('bulkStatusModal').style.display='none'" class="btn"
                                style="flex: 1; background: #95a5a6; color: white; padding: 12px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                                <i class="fas fa-times"></i> Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal: Alterar Departamento em Lote -->
            <div id="bulkDepartmentModal" class="modal" style="display: none;">
                <div class="modal-content" style="max-width: 500px;">
                    <span class="close-btn"
                        onclick="document.getElementById('bulkDepartmentModal').style.display='none'"
                        style="color: #ecf0f1; float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
                    <h3 style="text-align: center; color: #3498db; margin-bottom: 25px;">
                        <i class="fas fa-exchange-alt"></i> Alterar Departamento em Lote
                    </h3>
                    <form id="bulkDepartmentForm">
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 8px; color: #ecf0f1; font-weight: 600;">
                                Novo Departamento
                            </label>
                            <input type="text" id="bulkNewDepartment" name="department" required
                                style="width: 100%; padding: 12px; border: 2px solid #34495e; border-radius: 8px; background: #2c3e50; color: #ecf0f1; font-size: 14px;"
                                placeholder="Ex: Marketing">
                        </div>
                        <div style="display: flex; gap: 1rem; margin-top: 20px;">
                            <button type="submit" class="btn"
                                style="flex: 1; background: #3498db; color: white; padding: 12px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                                <i class="fas fa-check"></i> Confirmar
                            </button>
                            <button type="button"
                                onclick="document.getElementById('bulkDepartmentModal').style.display='none'"
                                class="btn"
                                style="flex: 1; background: #95a5a6; color: white; padding: 12px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                                <i class="fas fa-times"></i> Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </section>




















        <?php
        $historyServerStart = '';
        $historyServerEnd = '';
        $presencaServerStart = '';
        $presencaServerEnd = '';

        $historyStartInput = trim((string)($_GET['hist_server_start'] ?? ''));
        $historyEndInput = trim((string)($_GET['hist_server_end'] ?? ''));
        $presencaStartInput = trim((string)($_GET['presenca_server_start'] ?? ''));
        $presencaEndInput = trim((string)($_GET['presenca_server_end'] ?? ''));

        if ($historyStartInput !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $historyStartInput) === 1) {
            $historyServerStart = $historyStartInput;
        }
        if ($historyEndInput !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $historyEndInput) === 1) {
            $historyServerEnd = $historyEndInput;
        }
        if ($presencaStartInput !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $presencaStartInput) === 1) {
            $presencaServerStart = $presencaStartInput;
        }
        if ($presencaEndInput !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $presencaEndInput) === 1) {
            $presencaServerEnd = $presencaEndInput;
        }

        if ($historyServerStart !== '' && $historyServerEnd !== '' && strcmp($historyServerStart, $historyServerEnd) > 0) {
            $tmpHistDate = $historyServerStart;
            $historyServerStart = $historyServerEnd;
            $historyServerEnd = $tmpHistDate;
        }
        if ($presencaServerStart !== '' && $presencaServerEnd !== '' && strcmp($presencaServerStart, $presencaServerEnd) > 0) {
            $tmpPresDate = $presencaServerStart;
            $presencaServerStart = $presencaServerEnd;
            $presencaServerEnd = $tmpPresDate;
        }

        // Solicitações section server filter
        $solServerStart = '';
        $solServerEnd = '';
        $solStartInput = trim((string)($_GET['sol_server_start'] ?? ''));
        $solEndInput   = trim((string)($_GET['sol_server_end'] ?? ''));
        if ($solStartInput !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $solStartInput) === 1) {
            $solServerStart = $solStartInput;
        }
        if ($solEndInput !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $solEndInput) === 1) {
            $solServerEnd = $solEndInput;
        }
        if ($solServerStart !== '' && $solServerEnd !== '' && strcmp($solServerStart, $solServerEnd) > 0) {
            [$solServerStart, $solServerEnd] = [$solServerEnd, $solServerStart];
        }

        // History pagination
        $histPage    = max(1, (int)($_GET['hist_page'] ?? 1));
        $histPerPage = 50;
        $histOffset  = ($histPage - 1) * $histPerPage;
        ?>

        <?php require $ADMIN_DIR . '/sections/notificacoes.php'; ?>

        <section id="assiduidade-section" class="content-section">

            <style>
            /* ── togglePresencaHistoryBtn ── */
            #togglePresencaHistoryBtn {
                transition: transform .16s ease, box-shadow .2s ease, background .2s ease, border-color .2s ease;
            }
            #togglePresencaHistoryBtn:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(15,23,42,.16); }
            #togglePresencaHistoryBtn:active { transform: translateY(0); box-shadow: 0 2px 6px rgba(15,23,42,.2); }
            #togglePresencaHistoryBtn:focus-visible { outline: none; box-shadow: 0 0 0 3px rgba(37,99,235,.25); }
            #togglePresencaHistoryBtn.history-active {
                background: linear-gradient(135deg,#1d4ed8,#2563eb);
                color:#fff; border:1px solid #1e40af; box-shadow:0 6px 18px rgba(37,99,235,.35);
            }
            #togglePresencaHistoryBtn.history-active:hover { box-shadow:0 8px 22px rgba(37,99,235,.42); }
            #togglePresencaHistoryBtn.history-active i { color:#fff; }

            /* ── pa-* Presença section design system ── */
            .pa-hdr { display:flex; align-items:center; justify-content:space-between; gap:14px; margin-bottom:1.5rem; background:var(--card-bg,#1e293b); border:1px solid var(--border-color,rgba(255,255,255,.07)); border-radius:14px; padding:.875rem 1.25rem; width:100%; box-sizing:border-box; }
            .pa-hdr-icon {
                width:46px; height:46px; border-radius:13px; flex-shrink:0;
                background:linear-gradient(135deg,#3b82f6,#1d4ed8);
                display:flex; align-items:center; justify-content:center;
                color:#fff; font-size:1.15rem;
                box-shadow:0 4px 14px rgba(59,130,246,.35);
            }
            .pa-hdr-title { font-size:1.25rem; font-weight:700; color:var(--text-primary); margin:0; }
            .pa-hdr-sub  { font-size:.78rem; color:var(--text-secondary); margin:0; }

            /* KPI strip */
            .pa-kpi-strip {
                display:grid;
                grid-template-columns:repeat(6,1fr);
                gap:.65rem; margin-bottom:1.25rem;
            }
            @media(max-width:1100px){ .pa-kpi-strip{ grid-template-columns:repeat(3,1fr); } }
            @media(max-width:640px) { .pa-kpi-strip{ grid-template-columns:repeat(2,1fr); } }
            .pa-kpi-card {
                background:var(--bg-secondary); border:1px solid var(--border-primary);
                border-radius:12px; padding:.8rem 1rem;
                cursor:pointer; text-align:left;
                transition:transform .15s, box-shadow .15s, border-color .15s;
                position:relative; overflow:hidden;
            }
            .pa-kpi-card::before {
                content:''; position:absolute; top:0; left:0; right:0; height:3px;
                border-radius:12px 12px 0 0;
                background:var(--pa-accent,#3b82f6); opacity:0; transition:opacity .15s;
            }
            .pa-kpi-card:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(0,0,0,.22); border-color:var(--pa-accent,#3b82f6); }
            .pa-kpi-card:hover::before, .pa-kpi-active::before { opacity:1; }
            .pa-kpi-active { border-color:var(--pa-accent,#3b82f6); box-shadow:0 0 0 2px rgba(59,130,246,.2); }
            .pa-kpi-top { display:flex; align-items:center; justify-content:space-between; margin-bottom:.45rem; }
            .pa-kpi-num { font-size:1.75rem; font-weight:800; color:var(--text-primary); letter-spacing:-.03em; line-height:1; }
            .pa-kpi-ico {
                width:32px; height:32px; border-radius:8px; flex-shrink:0;
                background:rgba(255,255,255,.07);
                display:flex; align-items:center; justify-content:center;
                color:var(--pa-accent,#3b82f6); font-size:.88rem;
            }
            .pa-kpi-lbl { font-size:.72rem; font-weight:600; color:var(--text-secondary); text-transform:uppercase; letter-spacing:.04em; }

            /* Toolbar */
            .pa-toolbar {
                background:var(--bg-secondary); border:1px solid var(--border-primary);
                border-radius:12px 12px 0 0; border-bottom:none;
                padding:1rem 1.1rem .8rem;
            }
            .pa-toolbar-row1 { display:flex; align-items:center; gap:.6rem; flex-wrap:wrap; margin-bottom:.6rem; }
            .pa-toolbar-row2 { display:flex; align-items:center; gap:.6rem; flex-wrap:wrap; }
            .pa-tbar-title { font-size:1rem; font-weight:700; color:var(--text-primary); white-space:nowrap; flex-shrink:0; }
            .pa-inp {
                background:var(--bg-tertiary); border:1px solid var(--border-primary);
                color:var(--text-primary); border-radius:8px;
                padding:.48rem .75rem; font-size:.875rem; outline:none;
                transition:border-color .15s;
            }
            .pa-inp:focus { border-color:#3b82f6; }
            .pa-inp::placeholder { color:var(--text-secondary); }
            .pa-search { flex:1; min-width:200px; }
            .pa-chip {
                font-weight:600; color:var(--text-primary); font-size:.85rem;
                white-space:nowrap; background:var(--bg-tertiary);
                border:1px solid var(--border-primary); border-radius:8px;
                padding:.42rem .7rem; flex-shrink:0;
            }
            .pa-spacer { flex:1; }

            /* Table employee cell */
            .pa-emp-cell { display:flex; align-items:center; gap:.55rem; }
            .pa-emp-av {
                width:32px; height:32px; border-radius:50%; overflow:hidden; flex-shrink:0;
                background:linear-gradient(135deg,#475569,#334155);
                color:#fff; display:flex; align-items:center; justify-content:center;
                font-size:.7rem; font-weight:700;
            }
            .pa-emp-av img { width:100%; height:100%; object-fit:cover; }
            .pa-emp-name { font-weight:600; font-size:.875rem; }

            /* Action buttons */
            .pa-acts { display:flex; gap:.35rem; align-items:center; justify-content:center; }
            .pa-btn {
                width:30px; height:30px; border-radius:7px; border:none;
                display:inline-flex; align-items:center; justify-content:center;
                font-size:.78rem; cursor:pointer;
                transition:transform .12s, box-shadow .12s;
                flex-shrink:0;
            }
            .pa-btn:hover { transform:translateY(-1px); box-shadow:0 3px 8px rgba(0,0,0,.25); }
            .pa-btn:disabled { opacity:.45; cursor:not-allowed; transform:none; box-shadow:none; }
            .pa-btn-view   { background:linear-gradient(135deg,#3b82f6,#2563eb); color:#fff; }
            .pa-btn-edit   { background:linear-gradient(135deg,#f59e0b,#d97706); color:#fff; }
            .pa-btn-assign { background:linear-gradient(135deg,#64748b,#475569); color:#fff; }

            /* Filter toggle active state */
            #paFilterToggle.pa-filter-open {
                border-color:#3b82f6; color:#60a5fa;
                background:rgba(59,130,246,.08);
            }

            /* Attendance modals */
            #modalVerPresenca,
            #modalEditarPresenca { overflow-y:auto; padding:24px 16px 48px; }
            </style>


            <div class="pa-hdr">
                <div class="pa-hdr-icon"><i class="fas fa-calendar-check"></i></div>
                <div>
                    <h2 class="pa-hdr-title">Marcação de Presença</h2>
                    <p class="pa-hdr-sub">Registos diários, ponto e assiduidade</p>
                </div>
            </div>

            <div id="presencaHistoryPanel"
                style="max-height: 0; overflow: hidden; opacity: 0; transition: max-height 0.35s ease, opacity 0.25s ease, margin-top 0.25s ease; margin-top: 0;">
                <div class="data-table" style="margin-top: 1.25rem;">
                    <div class="table-header"
                        style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap;">
                        <h3
                            style="margin: 0; font-size: 1.125rem; font-weight: 600; color: var(--text-primary); white-space: nowrap;">
                            Histórico de Presença
                        </h3>

                        <div style="display:flex; gap:.75rem; flex-wrap:wrap; align-items:center; margin-left:auto;">
                            <input type="text" id="searchHistoryPresenca" placeholder="Pesquisar no histórico..."
                                class="search-input" style="width: 260px; min-width: 220px;">

                            <input type="date" id="filterHistoryPresencaStart" class="search-input"
                                style="min-width: 160px;" title="Data inicial do histórico"
                                value="<?php echo htmlspecialchars($historyServerStart); ?>">

                            <input type="date" id="filterHistoryPresencaEnd" class="search-input"
                                style="min-width: 160px;" title="Data final do histórico"
                                value="<?php echo htmlspecialchars($historyServerEnd); ?>">

                            <select id="filterHistoryPresencaStatus" class="search-input" style="min-width: 180px;">
                                <option value="">Todos os status</option>
                                <option value="presente">Presente</option>
                                <option value="falta">Falta</option>
                                <option value="em-aberto">Em aberto</option>
                                <option value="invalidado">Invalidado</option>
                            </select>

                            <button id="clearHistoryPresenca" class="btn btn-secondary"
                                style="padding: 0.6rem 1rem; display: none; white-space: nowrap; background: var(--bg-tertiary); color: var(--text-primary); border: 1px solid var(--border-primary);">
                                <i class="fas fa-times"></i> Limpar
                            </button>

                            <button type="button" id="applyHistoryPresencaServer" class="btn btn-primary"
                                onclick="applyHistoryPresencaServerFilter()"
                                style="padding: 0.6rem 1rem; white-space: nowrap;">
                                <i class="fas fa-database"></i> Aplicar
                            </button>

                            <button type="button" id="clearHistoryPresencaServer" class="btn btn-secondary"
                                onclick="clearHistoryPresencaServerFilter()"
                                style="padding: 0.6rem 1rem; white-space: nowrap; background: var(--bg-tertiary); color: var(--text-primary); border: 1px solid var(--border-primary);">
                                <i class="fas fa-eraser"></i> Limpar
                            </button>

                            <span id="resultCountHistoryPresenca"
                                style="font-weight: 600; color: var(--text-primary); font-size: 0.9rem; white-space: nowrap; background: var(--bg-tertiary); border: 1px solid var(--border-primary); border-radius: 8px; padding: 0.45rem 0.75rem;"></span>

                            <div class="dropdown" style="position: relative; display: inline-block;">
                                <button class="btn btn-accent" style="white-space: nowrap;"
                                    onclick="toggleExportHistoryPresencaDropdown()">
                                    <i class="fas fa-download"></i>
                                    <span>Exportar Histórico</span>
                                    <i class="fas fa-chevron-down" style="margin-left: 5px; font-size: 0.8em;"></i>
                                </button>
                                <div id="exportHistoryPresencaDropdown" class="dropdown-content"
                                    style="display: none; position: absolute; right: 0; background-color: white; min-width: 210px; box-shadow: 0 8px 16px rgba(0,0,0,0.2); border-radius: 8px; z-index: 1; margin-top: 5px;">
                                    <a href="#" onclick="exportHistoryPresencaPDF(); return false;"
                                        style="color: #1f2937; padding: 12px 16px; text-decoration: none; display: block; border-bottom: 1px solid #e5e7eb;">
                                        <i class="fas fa-file-pdf" style="color: #e74c3c; margin-right: 8px;"></i>
                                        Exportar PDF
                                    </a>
                                    <a href="#" onclick="exportHistoryPresencaCSV(); return false;"
                                        style="color: #1f2937; padding: 12px 16px; text-decoration: none; display: block;">
                                        <i class="fas fa-file-csv" style="color: #27ae60; margin-right: 8px;"></i>
                                        Exportar CSV
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <table class="table" id="historyPresencaTable">
                        <thead>
                            <tr>
                                <th>Funcionário</th>
                                <th>Data</th>
                                <th>Status</th>
                                <th>Tipo de Dia</th>
                                <th>Entrada</th>
                                <th>Saída</th>
                                <th>Observação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $historicoPresenca = [];
                            $histPresencaDateColumn = 'data_registro';
                            $histPontoDateColumn = 'data_registro';

                            try {
                                $histColsPresenca = $pdo->query("SHOW COLUMNS FROM presencas")->fetchAll(PDO::FETCH_COLUMN) ?: [];
                                if (!in_array('data_registro', $histColsPresenca, true) && in_array('data', $histColsPresenca, true)) {
                                    $histPresencaDateColumn = 'data';
                                }
                            } catch (Exception $e) {
                                // Mantém padrão data_registro
                            }

                            try {
                                $histColsPonto = $pdo->query("SHOW COLUMNS FROM registros_ponto")->fetchAll(PDO::FETCH_COLUMN) ?: [];
                                if (!in_array('data_registro', $histColsPonto, true) && in_array('data', $histColsPonto, true)) {
                                    $histPontoDateColumn = 'data';
                                }
                            } catch (Exception $e) {
                                // Mantém padrão data_registro
                            }

                            $historyDateFiltersSql = '';
                            $historyParamsBase = [(int)$loggedInClientId];

                            if ($historyServerStart !== '') {
                                $historyDateFiltersSql .= " AND DATE(p.{$histPresencaDateColumn}) >= ?";
                                $historyParamsBase[] = $historyServerStart;
                            }
                            if ($historyServerEnd !== '') {
                                $historyDateFiltersSql .= " AND DATE(p.{$histPresencaDateColumn}) <= ?";
                                $historyParamsBase[] = $historyServerEnd;
                            }

                            // Count total rows for pagination
                            $histTotalRows  = 0;
                            $histTotalPages = 1;
                            try {
                                $stmtHistCount = $pdo->prepare(
                                    "SELECT COUNT(*)
                         FROM presencas p
                         INNER JOIN employees e ON e.id = p.funcionario_id
                         WHERE e.client_id = ? {$historyDateFiltersSql}"
                                );
                                $stmtHistCount->execute($historyParamsBase);
                                $histTotalRows  = (int)$stmtHistCount->fetchColumn();
                                $histTotalPages = max(1, (int)ceil($histTotalRows / $histPerPage));
                                if ($histPage > $histTotalPages) {
                                    $histPage   = $histTotalPages;
                                    $histOffset = ($histPage - 1) * $histPerPage;
                                }
                            } catch (Exception $e) {
                                error_log('Erro ao contar histórico de presença: ' . $e->getMessage());
                            }

                            $historyParams = array_merge($historyParamsBase, [$histPerPage, $histOffset]);

                            try {
                                $stmtHistoricoPresenca = $pdo->prepare(
                                    "SELECT
                            p.funcionario_id,
                            e.name AS funcionario_nome,
                            p.status,
                            p.{$histPresencaDateColumn} AS data_registro,
                            rp.hora_entrada,
                            rp.hora_saida,
                            rp.tipo_dia,
                            rp.falta_tipo,
                            rp.obs
                         FROM presencas p
                         INNER JOIN employees e ON e.id = p.funcionario_id
                         LEFT JOIN registros_ponto rp
                            ON rp.id = (
                                SELECT rp2.id
                                FROM registros_ponto rp2
                                WHERE rp2.funcionario_id = p.funcionario_id
                                  AND DATE(rp2.{$histPontoDateColumn}) = DATE(p.{$histPresencaDateColumn})
                                ORDER BY rp2.{$histPontoDateColumn} DESC, rp2.id DESC
                                LIMIT 1
                            )
                         WHERE e.client_id = ? {$historyDateFiltersSql}
                         ORDER BY p.{$histPresencaDateColumn} DESC, p.id DESC
                         LIMIT ? OFFSET ?"
                                );
                                $stmtHistoricoPresenca->execute($historyParams);
                                $historicoPresenca = $stmtHistoricoPresenca->fetchAll(PDO::FETCH_ASSOC) ?: [];
                            } catch (Exception $e) {
                                error_log('Erro ao carregar histórico de presença: ' . $e->getMessage());
                                $historicoPresenca = [];
                            }

                            if (empty($historicoPresenca)):
                            ?>
                            <tr>
                                <td colspan="7"
                                    style="text-align: center; color: var(--text-secondary); padding: 1rem;">
                                    Sem histórico de presença para apresentar.
                                </td>
                            </tr>
                            <?php
                            else:
                                foreach ($historicoPresenca as $histRow):
                                    $rawDataHist = (string)($histRow['data_registro'] ?? '');
                                    $dataHistIso = '';
                                    $dataHistFmt = '--/--/----';
                                    if ($rawDataHist !== '') {
                                        $tsHist = strtotime($rawDataHist);
                                        if ($tsHist !== false) {
                                            $dataHistIso = date('Y-m-d', $tsHist);
                                            $dataHistFmt = date('d/m/Y H:i', $tsHist);
                                        }
                                    }

                                    $histEntrada = !empty($histRow['hora_entrada']) ? htmlspecialchars(substr((string)$histRow['hora_entrada'], 0, 5)) : '--:--';
                                    $histSaida = !empty($histRow['hora_saida']) ? htmlspecialchars(substr((string)$histRow['hora_saida'], 0, 5)) : '--:--';
                                    $histStatusRaw = mb_strtolower(trim((string)($histRow['status'] ?? '')));
                                    $histFaltaTipo = mb_strtolower(trim((string)($histRow['falta_tipo'] ?? '')));
                                    $histTipoDiaRaw = mb_strtolower(trim((string)($histRow['tipo_dia'] ?? ($histStatusRaw === 'falta' ? 'falta' : 'normal'))));
                                    $histTipoDiaMap = [
                                        'normal' => 'Normal',
                                        'folga' => 'Folga',
                                        'feriado' => 'Feriado',
                                        'falta' => 'Falta',
                                    ];
                                    $histTipoDiaLabel = $histTipoDiaMap[$histTipoDiaRaw] ?? 'Normal';

                                    if ($histStatusRaw === 'falta') {
                                        $histStatusLabel = $histFaltaTipo === 'justificada' ? 'Falta Justificada' : 'Falta Injustificada';
                                        $histStatusClass = 'status-falta';
                                        $histStatusKey = 'falta';
                                    } elseif ($histStatusRaw === 'presente' && $histEntrada !== '--:--' && $histSaida === '--:--') {
                                        $histStatusLabel = 'Em Aberto';
                                        $histStatusClass = 'status-warning';
                                        $histStatusKey = 'em-aberto';
                                    } elseif ($histStatusRaw === 'presente') {
                                        $histStatusLabel = 'Presente';
                                        $histStatusClass = 'status-presente';
                                        $histStatusKey = 'presente';
                                    } elseif ($histStatusRaw === 'invalidado') {
                                        $histStatusLabel = 'Invalidado';
                                        $histStatusClass = 'status-nao-marcado';
                                        $histStatusKey = 'invalidado';
                                    } else {
                                        $histStatusLabel = 'Não Registado';
                                        $histStatusClass = 'status-nao-marcado';
                                        $histStatusKey = 'nao-registrado';
                                    }

                                    $obsHist = trim((string)($histRow['obs'] ?? ''));
                                    if ($obsHist === '') {
                                        $obsHist = '-';
                                    }
                                ?>
                            <tr data-history-name="<?php echo htmlspecialchars(mb_strtolower((string)($histRow['funcionario_nome'] ?? ''))); ?>"
                                data-history-date="<?php echo htmlspecialchars($dataHistIso); ?>"
                                data-history-status-key="<?php echo htmlspecialchars($histStatusKey); ?>">
                                <td style="font-weight: 600;">
                                    <?php echo htmlspecialchars((string)($histRow['funcionario_nome'] ?? 'Funcionário')); ?>
                                </td>
                                <td><?php echo htmlspecialchars($dataHistFmt); ?></td>
                                <td><span class="status-badge <?php echo $histStatusClass; ?>"><?php echo htmlspecialchars($histStatusLabel); ?></span></td>
                                <td><?php echo htmlspecialchars($histTipoDiaLabel); ?></td>
                                <td><?php echo $histEntrada; ?></td>
                                <td><?php echo $histSaida; ?></td>
                                <td><?php echo htmlspecialchars($obsHist); ?></td>
                            </tr>
                            <?php
                                endforeach;
                            endif;
                            ?>
                        </tbody>
                    </table>
                    <?php if ($histTotalPages > 1): ?>
                    <div style="display:flex; justify-content:center; align-items:center; gap:.75rem; padding:1rem 0; flex-wrap:wrap;">
                        <?php if ($histPage > 1): ?>
                        <button type="button" class="btn btn-secondary" onclick="goToHistoryPage(<?php echo $histPage - 1; ?>)"
                            style="padding:.5rem 1rem;">
                            <i class="fas fa-chevron-left"></i> Anterior
                        </button>
                        <?php endif; ?>
                        <span style="color:var(--text-secondary); font-size:.9rem; background:var(--bg-tertiary); border:1px solid var(--border-primary); border-radius:8px; padding:.4rem .8rem;">
                            Página <?php echo $histPage; ?> de <?php echo $histTotalPages; ?>
                            &nbsp;&middot;&nbsp;<?php echo number_format($histTotalRows); ?> registo(s)
                        </span>
                        <?php if ($histPage < $histTotalPages): ?>
                        <button type="button" class="btn btn-secondary" onclick="goToHistoryPage(<?php echo $histPage + 1; ?>)"
                            style="padding:.5rem 1rem;">
                            Próxima <i class="fas fa-chevron-right"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="presencaSummaryCards" class="pa-kpi-strip">
                <button type="button" class="pa-kpi-card" data-status-key="" style="--pa-accent:#60a5fa;"
                    onclick="document.getElementById('filterPresencaStatus').value=''; if(window.filterPresencaTable){window.filterPresencaTable();}">
                    <div class="pa-kpi-top">
                        <span id="summaryPresencaVisible" class="pa-kpi-num">0</span>
                        <span class="pa-kpi-ico"><i class="fas fa-list"></i></span>
                    </div>
                    <span class="pa-kpi-lbl">Visíveis</span>
                </button>

                <button type="button" class="pa-kpi-card" data-status-key="presente" style="--pa-accent:#10b981;"
                    onclick="document.getElementById('filterPresencaStatus').value='presente'; if(window.filterPresencaTable){window.filterPresencaTable();}">
                    <div class="pa-kpi-top">
                        <span id="summaryPresencaPresentes" class="pa-kpi-num">0</span>
                        <span class="pa-kpi-ico" style="color:#10b981;"><i class="fas fa-user-check"></i></span>
                    </div>
                    <span class="pa-kpi-lbl">Presentes</span>
                </button>

                <button type="button" class="pa-kpi-card" data-status-key="falta" style="--pa-accent:#f87171;"
                    onclick="document.getElementById('filterPresencaStatus').value='falta'; if(window.filterPresencaTable){window.filterPresencaTable();}">
                    <div class="pa-kpi-top">
                        <span id="summaryPresencaFaltas" class="pa-kpi-num">0</span>
                        <span class="pa-kpi-ico" style="color:#f87171;"><i class="fas fa-user-times"></i></span>
                    </div>
                    <span class="pa-kpi-lbl">Faltas</span>
                </button>

                <button type="button" class="pa-kpi-card" data-status-key="atrasado" style="--pa-accent:#fbbf24;"
                    onclick="document.getElementById('filterPresencaStatus').value='atrasado'; if(window.filterPresencaTable){window.filterPresencaTable();}">
                    <div class="pa-kpi-top">
                        <span id="summaryPresencaAtrasados" class="pa-kpi-num">0</span>
                        <span class="pa-kpi-ico" style="color:#fbbf24;"><i class="fas fa-clock"></i></span>
                    </div>
                    <span class="pa-kpi-lbl">Atrasados</span>
                </button>

                <button type="button" class="pa-kpi-card" data-status-key="em-aberto" style="--pa-accent:#fb923c;"
                    onclick="document.getElementById('filterPresencaStatus').value='em-aberto'; if(window.filterPresencaTable){window.filterPresencaTable();}">
                    <div class="pa-kpi-top">
                        <span id="summaryPresencaEmAberto" class="pa-kpi-num">0</span>
                        <span class="pa-kpi-ico" style="color:#fb923c;"><i class="fas fa-hourglass-half"></i></span>
                    </div>
                    <span class="pa-kpi-lbl">Em aberto</span>
                </button>

                <button type="button" class="pa-kpi-card" data-status-key="sem-turno" style="--pa-accent:#94a3b8;"
                    onclick="document.getElementById('filterPresencaStatus').value='sem-turno'; if(window.filterPresencaTable){window.filterPresencaTable();}">
                    <div class="pa-kpi-top">
                        <span id="summaryPresencaSemTurno" class="pa-kpi-num">0</span>
                        <span class="pa-kpi-ico" style="color:#94a3b8;"><i class="fas fa-calendar-times"></i></span>
                    </div>
                    <span class="pa-kpi-lbl">Sem turno</span>
                </button>
            </div>

            <div class="data-table">
                <div class="pa-toolbar">
                    <div class="pa-toolbar-row1">
                        <span class="pa-tbar-title">Registos de Presença e Ponto</span>
                        <input type="text" id="searchPresenca" placeholder="Pesquisar funcionários..."
                            class="pa-inp pa-search">
                        <span id="resultCountPresenca" class="pa-chip"></span>
                        <div class="pa-spacer"></div>
                        <button type="button" class="fr-filter-toggle" id="paFilterToggle"
                            onclick="document.getElementById('paAdvFilters').classList.toggle('fr-adv-open'); this.classList.toggle('pa-filter-open')">
                            <i class="fas fa-sliders-h"></i> Filtros
                            <span class="fr-filter-badge" id="paFilterBadge" style="display:none"></span>
                        </button>
                        <button type="button" id="togglePresencaHistoryBtn" class="btn btn-secondary" disabled
                            aria-expanded="false"
                            aria-controls="presencaHistoryPanel"
                            title="Indisponível no momento"
                            style="padding:.5rem .9rem; white-space:nowrap;">
                            <i class="fas fa-history"></i>
                            <span>Histórico</span>
                            <i class="fas fa-chevron-down" style="margin-left:.3rem; font-size:.78em;"></i>
                        </button>
                        <div class="dropdown" style="position:relative; display:inline-block;">
                            <button class="btn btn-accent" style="white-space:nowrap; padding:.5rem .9rem;"
                                onclick="toggleExportPresencaDropdown()">
                                <i class="fas fa-download"></i>
                                <span>Exportar</span>
                                <i class="fas fa-chevron-down" style="margin-left:4px; font-size:.78em;"></i>
                            </button>
                            <div id="exportPresencaDropdown" class="dropdown-content"
                                style="display:none; position:absolute; right:0; background-color:white; min-width:180px; box-shadow:0 8px 16px rgba(0,0,0,.2); border-radius:8px; z-index:1; margin-top:5px;">
                                <a href="#" id="exportPresencaPDF" onclick="exportPresencaPDF(); return false;"
                                    style="color:#1f2937; padding:12px 16px; text-decoration:none; display:block; border-bottom:1px solid #e5e7eb;">
                                    <i class="fas fa-file-pdf" style="color:#e74c3c; margin-right:8px;"></i> Exportar PDF
                                </a>
                                <a href="#" id="expotpresenca" onclick="exportPresencaCSV(); return false;"
                                    style="color:#1f2937; padding:12px 16px; text-decoration:none; display:block;">
                                    <i class="fas fa-file-csv" style="color:#27ae60; margin-right:8px;"></i> Exportar CSV
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Collapsible advanced filters -->
                    <div class="fr-adv-filters" id="paAdvFilters">
                        <input type="date" id="filterPresencaStart" class="fr-select"
                            title="Data inicial" value="<?php echo htmlspecialchars($presencaServerStart); ?>">
                        <input type="date" id="filterPresencaEnd" class="fr-select"
                            title="Data final" value="<?php echo htmlspecialchars($presencaServerEnd); ?>">
                        <select id="filterPresencaStatus" class="fr-select">
                            <option value="">Todos os status</option>
                            <option value="presente">Presente</option>
                            <option value="falta">Falta</option>
                            <option value="atrasado">Atrasado</option>
                            <option value="em-aberto">Em aberto</option>
                            <option value="nao-registrado">Não registado</option>
                            <option value="sem-turno">Sem turno</option>
                            <option value="ferias">Férias</option>
                            <option value="inativo">Inativo</option>
                        </select>
                        <button id="clearFiltersPresenca" class="fr-clear-btn" style="display:none;">
                            <i class="fas fa-times"></i> Limpar
                        </button>
                        <button type="button" id="applyPresencaServer" class="fr-select"
                            onclick="applyPresencaServerFilter()"
                            style="cursor:pointer; background:rgba(59,130,246,.12); color:#60a5fa; border-color:rgba(59,130,246,.25); white-space:nowrap;">
                            <i class="fas fa-database"></i> Aplicar período
                        </button>
                        <button type="button" id="clearPresencaServer" class="fr-clear-btn"
                            onclick="clearPresencaServerFilter()"
                            style="border-color:rgba(148,163,184,.25); color:#94a3b8; background:rgba(148,163,184,.07);">
                            <i class="fas fa-eraser"></i> Limpar período
                        </button>
                    </div>
                </div>

                <table class="table fr-table" id="presencaTable">
                    <thead>
                        <tr class="fr-thead-row">
                            <th class="fr-th-emp">Funcionário</th>
                            <th class="fr-th-status">Status</th>
                            <th>Data</th>
                            <th>Roteiro</th>
                            <th class="fr-th-acts">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // **IMPORTANTE:** O bloco de código a seguir assume que a variável $pdo 
                        // para a conexão com o banco de dados está definida e disponível aqui.

                        $pontoDateColumn = 'data_registro';
                        $presencaDateColumn = 'data_registro';

                        try {
                            $colsPonto = $pdo->query("SHOW COLUMNS FROM registros_ponto")->fetchAll(PDO::FETCH_COLUMN) ?: [];
                            if (!in_array('data_registro', $colsPonto, true) && in_array('data', $colsPonto, true)) {
                                $pontoDateColumn = 'data';
                            }
                        } catch (Exception $e) {
                            // Mantém padrão data_registro
                        }

                        $pontoUpdatedSelect = in_array('updated_at', $colsPonto ?? [], true)
                            ? 'updated_at'
                            : 'NULL AS updated_at';

                        try {
                            $colsPresenca = $pdo->query("SHOW COLUMNS FROM presencas")->fetchAll(PDO::FETCH_COLUMN) ?: [];
                            if (!in_array('data_registro', $colsPresenca, true) && in_array('data', $colsPresenca, true)) {
                                $presencaDateColumn = 'data';
                            }
                        } catch (Exception $e) {
                            // Mantém padrão data_registro
                        }

                        $expectedStartByEmployee = [];
                        try {
                            $stmtExpectedStart = $pdo->prepare(
                                "SELECT t.funcionario_id, t.horario_inicio
                     FROM turnos t
                     INNER JOIN employees e ON e.id = t.funcionario_id
                     WHERE e.client_id = ? AND LOWER(COALESCE(t.status, '')) IN ('ativo', 'active')
                     ORDER BY t.id DESC"
                            );
                            $stmtExpectedStart->execute([(int)$loggedInClientId]);
                            $rowsExpectedStart = $stmtExpectedStart->fetchAll(PDO::FETCH_ASSOC) ?: [];
                            foreach ($rowsExpectedStart as $rowExpected) {
                                $empIdExp = (int)($rowExpected['funcionario_id'] ?? 0);
                                if ($empIdExp > 0 && !isset($expectedStartByEmployee[$empIdExp])) {
                                    $expectedStartByEmployee[$empIdExp] = (string)($rowExpected['horario_inicio'] ?? '');
                                }
                            }
                        } catch (Exception $e) {
                            error_log('Erro ao mapear horários esperados por funcionário: ' . $e->getMessage());
                        }

                        $justificativaLatestByEmployee = [];
                        $justificativasPendentes = [];
                        $allJustificativas = [];
                        try {
                            $pdo->exec(
                                "CREATE TABLE IF NOT EXISTS justificativas_presenca (
                        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        client_id INT NOT NULL,
                        employee_id INT NOT NULL,
                        data_ocorrencia DATE NOT NULL,
                        tipo ENUM('falta','atraso') NOT NULL,
                        motivo TEXT NOT NULL,
                        anexo_path VARCHAR(255) NULL,
                        status ENUM('pendente','aprovada','rejeitada') NOT NULL DEFAULT 'pendente',
                        admin_observacao TEXT NULL,
                        decidido_por INT NULL,
                        decidido_em DATETIME NULL,
                        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        KEY idx_justificativas_client_status (client_id, status),
                        KEY idx_justificativas_employee_data (employee_id, data_ocorrencia)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
                            );

                            $solJustSql    = '';
                            $solJustParams = [(int)$loggedInClientId];
                            if ($solServerStart !== '') {
                                $solJustSql    .= ' AND j.data_ocorrencia >= ?';
                                $solJustParams[] = $solServerStart;
                            }
                            if ($solServerEnd !== '') {
                                $solJustSql    .= ' AND j.data_ocorrencia <= ?';
                                $solJustParams[] = $solServerEnd;
                            }

                            $stmtJustificativas = $pdo->prepare(
                                "SELECT j.id, j.employee_id, j.data_ocorrencia, j.tipo, j.motivo, j.anexo_path, j.status, j.created_at,
                        j.admin_observacao, j.decidido_por, j.decidido_em,
                            e.name AS employee_name, e.profile_picture AS employee_profile_picture
                     FROM justificativas_presenca j
                     INNER JOIN employees e ON e.id = j.employee_id
                     WHERE j.client_id = ? {$solJustSql}
                     ORDER BY j.created_at DESC, j.id DESC"
                            );
                            $stmtJustificativas->execute($solJustParams);
                            $allJustificativas = $stmtJustificativas->fetchAll(PDO::FETCH_ASSOC) ?: [];

                            foreach ($allJustificativas as $jRow) {
                                $empJustId = (int)($jRow['employee_id'] ?? 0);
                                if ($empJustId <= 0) {
                                    continue;
                                }

                                if (!isset($justificativaLatestByEmployee[$empJustId])) {
                                    $justificativaLatestByEmployee[$empJustId] = $jRow;
                                }

                                if (mb_strtolower(trim((string)($jRow['status'] ?? ''))) === 'pendente') {
                                    $justificativasPendentes[] = $jRow;
                                }
                            }
                        } catch (Exception $e) {
                            error_log('Erro ao carregar justificativas na assiduidade: ' . $e->getMessage());
                        }

                        $pontoPeriodSql = '';
                        $presencaPeriodSql = '';
                        if ($presencaServerStart !== '') {
                            $pontoPeriodSql .= " AND DATE({$pontoDateColumn}) >= ?";
                            $presencaPeriodSql .= " AND DATE({$presencaDateColumn}) >= ?";
                        }
                        if ($presencaServerEnd !== '') {
                            $pontoPeriodSql .= " AND DATE({$pontoDateColumn}) <= ?";
                            $presencaPeriodSql .= " AND DATE({$presencaDateColumn}) <= ?";
                        }

                        foreach ($employees as $employee):
                            // 1. Lógica para buscar o registro de ponto mais recente do funcionário
                            $stmt = $pdo->prepare("
                    SELECT id, status, hora_entrada, hora_saida, obs, status_confirmacao, tipo_dia, falta_tipo, {$pontoDateColumn} AS data_registro, {$pontoUpdatedSelect}
                    FROM registros_ponto 
                    WHERE funcionario_id = ? {$pontoPeriodSql}
                    ORDER BY {$pontoDateColumn} DESC, id DESC
                    LIMIT 1
                ");
                            $pontoParams = [(int)$employee['id']];
                            if ($presencaServerStart !== '') {
                                $pontoParams[] = $presencaServerStart;
                            }
                            if ($presencaServerEnd !== '') {
                                $pontoParams[] = $presencaServerEnd;
                            }
                            $stmt->execute($pontoParams);
                            $registro = $stmt->fetch(PDO::FETCH_ASSOC);

                            // Buscar presença mais recente para refletir Presente/Falta persistidos
                            $stmtPresencaHoje = $pdo->prepare("
                    SELECT status, {$presencaDateColumn} AS data_registro
                    FROM presencas
                    WHERE funcionario_id = ? {$presencaPeriodSql}
                    ORDER BY {$presencaDateColumn} DESC, id DESC
                    LIMIT 1
                ");
                            $presencaParams = [(int)$employee['id']];
                            if ($presencaServerStart !== '') {
                                $presencaParams[] = $presencaServerStart;
                            }
                            if ($presencaServerEnd !== '') {
                                $presencaParams[] = $presencaServerEnd;
                            }
                            $stmtPresencaHoje->execute($presencaParams);
                            $presencaHoje = $stmtPresencaHoje->fetch(PDO::FETCH_ASSOC);
                            $presencaStatus = isset($presencaHoje['status']) ? mb_strtolower(trim((string)$presencaHoje['status'])) : '';

                            // Data de referência para exibição/filtros: ponto mais recente, fallback presença
                            $rawDate = $registro['data_registro'] ?? ($presencaHoje['data_registro'] ?? null);
                            $dateIso = '';
                            $dateDisplay = '--/--/----';
                            if (!empty($rawDate)) {
                                $tsDate = strtotime((string)$rawDate);
                                if ($tsDate !== false) {
                                    $dateIso = date('Y-m-d', $tsDate);
                                    $dateDisplay = date('d/m/Y', $tsDate);
                                }
                            }

                            // Roteiro do dia — todos os períodos (entrada/pausa/regresso/saída) do dia de referência
                            $_timelineEventos = [];
                            if ($dateIso !== '') {
                                try {
                                    $stmtTimelinePresenca = $pdo->prepare("
                                        SELECT hora_entrada, hora_saida, observacao
                                        FROM registros_ponto
                                        WHERE funcionario_id = ? AND {$pontoDateColumn} = ?
                                        ORDER BY id ASC
                                    ");
                                    $stmtTimelinePresenca->execute([(int)$employee['id'], $dateIso]);
                                    $_pontosTimeline = $stmtTimelinePresenca->fetchAll(PDO::FETCH_ASSOC) ?: [];
                                } catch (Exception $e) {
                                    $_pontosTimeline = [];
                                }

                                $_totalPontosTimeline = count($_pontosTimeline);
                                foreach ($_pontosTimeline as $_tiTimeline => $_tpTimeline) {
                                    $hEntTimeline = substr((string)($_tpTimeline['hora_entrada'] ?? ''), 0, 5);
                                    $hSaiTimeline = substr((string)($_tpTimeline['hora_saida'] ?? ''), 0, 5);
                                    $obsTimeline = mb_strtolower(trim((string)($_tpTimeline['observacao'] ?? '')));

                                    if ($hEntTimeline) {
                                        if ($_tiTimeline === 0) {
                                            $_timelineEventos[] = ['hora' => $hEntTimeline, 'label' => 'Entrada', 'icon' => 'fa-sign-in-alt', 'cls' => 'in'];
                                        } else {
                                            $_timelineEventos[] = ['hora' => $hEntTimeline, 'label' => 'Regresso ao trabalho', 'icon' => 'fa-undo-alt', 'cls' => 'regresso'];
                                        }
                                    }

                                    if ($hSaiTimeline) {
                                        if (str_contains($obsTimeline, 'pausa')) {
                                            if (str_contains($obsTimeline, 'almo')) {
                                                $iconTimeline = 'fa-utensils'; $lblTimeline = 'Pausa Almoço';
                                            } elseif (str_contains($obsTimeline, 'cigar')) {
                                                $iconTimeline = 'fa-smoking'; $lblTimeline = 'Pausa Cigarro';
                                            } else {
                                                $iconTimeline = 'fa-pause-circle'; $lblTimeline = 'Pausa';
                                            }
                                            $_timelineEventos[] = ['hora' => $hSaiTimeline, 'label' => $lblTimeline, 'icon' => $iconTimeline, 'cls' => 'pausa'];
                                        } else {
                                            $_timelineEventos[] = ['hora' => $hSaiTimeline, 'label' => 'Saída', 'icon' => 'fa-sign-out-alt', 'cls' => 'out'];
                                        }
                                    } elseif ($_tiTimeline === $_totalPontosTimeline - 1) {
                                        $_timelineEventos[] = ['hora' => null, 'label' => 'Em curso', 'icon' => 'fa-circle', 'cls' => 'ativo'];
                                    }
                                }
                            }
                            $_timelineEventosJson = htmlspecialchars(json_encode($_timelineEventos, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

                            // 2. Determinar o status automático
                            $entrada = isset($registro['hora_entrada']) && $registro['hora_entrada'] !== null && $registro['hora_entrada'] !== '';
                            $saida = isset($registro['hora_saida']) && $registro['hora_saida'] !== null && $registro['hora_saida'] !== '';
                            $confirmado = isset($registro['status_confirmacao']) && $registro['status_confirmacao'] === 'confirmado';

                            $horasTrabalhadas = '--:--';
                            if ($entrada && $saida) {
                                $entradaTs = strtotime('1970-01-01 ' . $registro['hora_entrada']);
                                $saidaTs = strtotime('1970-01-01 ' . $registro['hora_saida']);
                                if ($entradaTs !== false && $saidaTs !== false) {
                                    if ($saidaTs < $entradaTs) {
                                        // Suporte a virada de dia (ex.: turno noturno)
                                        $saidaTs += 24 * 60 * 60;
                                    }
                                    $diffMin = max(0, (int) floor(($saidaTs - $entradaTs) / 60));
                                    $h = (int) floor($diffMin / 60);
                                    $m = $diffMin % 60;
                                    $horasTrabalhadas = sprintf('%02d:%02d', $h, $m);
                                }
                            }

                            $tipoDia = mb_strtolower(trim((string)($registro['tipo_dia'] ?? 'normal')));
                            $tipoDiaMap = [
                                'normal' => 'Normal',
                                'folga' => 'Folga',
                                'feriado' => 'Feriado',
                                'falta' => 'Falta',
                            ];
                            if (!isset($tipoDiaMap[$tipoDia])) {
                                $tipoDia = 'normal';
                            }
                            $tipoDiaLabel = $tipoDiaMap[$tipoDia];

                            $faltaTipoRaw = mb_strtolower(trim((string)($registro['falta_tipo'] ?? '')));
                            if (!in_array($faltaTipoRaw, ['justificada', 'injustificada'], true)) {
                                $faltaTipoRaw = '';
                            }
                            $faltaTipoLabel = $faltaTipoRaw === 'justificada' ? 'Falta Justificada' : ($faltaTipoRaw === 'injustificada' ? 'Falta Injustificada' : '-');

                            $temTurno = isset($expectedStartByEmployee[(int)$employee['id']]) && $expectedStartByEmployee[(int)$employee['id']] !== '';
                            $horarioPrevisto = $temTurno ? $expectedStartByEmployee[(int)$employee['id']] : null;
                            $atrasoTexto = '—';
                            if ($entrada && !in_array($tipoDia, ['folga', 'feriado', 'falta'], true)) {
                                $entradaTsCalc = strtotime('1970-01-01 ' . (string)$registro['hora_entrada']);
                                $previstoTsCalc = strtotime('1970-01-01 ' . (string)$horarioPrevisto);
                                $toleranciaMin = max(0, (int)($estHorario['tolerancia_atraso_min'] ?? 0));

                                if ($entradaTsCalc !== false && $previstoTsCalc !== false) {
                                    $diffMinAtraso = (int) floor(($entradaTsCalc - $previstoTsCalc) / 60) - $toleranciaMin;
                                    if ($diffMinAtraso > 0) {
                                        $atrasoTexto = 'Atrasado (+' . $diffMinAtraso . ' min)';
                                    } else {
                                        $atrasoTexto = 'Pontual';
                                    }
                                }
                            }

                            if (!$temTurno) {
                                $status_texto = 'SEM TURNO';
                                $status_classe = 'status-nao-marcado';
                            } elseif (isset($registro['status']) && $registro['status'] === 'invalidado') {
                                $status_texto = '—';
                                $status_classe = 'status-nao-marcado';
                            } elseif ($presencaStatus === 'falta') {
                                $status_texto = $faltaTipoRaw === 'justificada' ? 'FALTA JUSTIFICADA' : 'FALTA INJUSTIFICADA';
                                $status_classe = 'status-falta';
                            } elseif ($presencaStatus === 'presente') {
                                $status_texto = 'PRESENTE';
                                $status_classe = 'status-presente';
                            } elseif (!$entrada) {
                                // Se não marcou presença:
                                $agora = date('H:i');
                                $horaTurno = substr($horarioPrevisto, 0, 5);
                                $toleranciaMin = max(0, (int)($estHorario['tolerancia_atraso_min'] ?? 0));
                                $agoraTs = strtotime('1970-01-01 ' . $agora);
                                $turnoTs = strtotime('1970-01-01 ' . $horaTurno);
                                $toleranciaTs = $turnoTs !== false ? $turnoTs + ($toleranciaMin * 60) : false;
                                if ($agoraTs !== false && $toleranciaTs !== false) {
                                    if ($agoraTs > $toleranciaTs) {
                                        $status_texto = 'FALTA';
                                        $status_classe = 'status-falta';
                                    } elseif ($agoraTs > $turnoTs) {
                                        $status_texto = 'ATRASADO';
                                        $status_classe = 'status-warning';
                                    } else {
                                        $status_texto = 'NÃO REGISTADO';
                                        $status_classe = 'status-nao-marcado';
                                    }
                                } else {
                                    $status_texto = 'NÃO REGISTADO';
                                    $status_classe = 'status-nao-marcado';
                                }
                            } elseif ($entrada && (!$saida && !$confirmado)) {
                                $status_texto = 'EM ABERTO';
                                $status_classe = 'status-warning';
                            } else {
                                $status_texto = 'PRESENTE';
                                $status_classe = 'status-presente';
                            }

                            if (isset($registro['status']) && $registro['status'] === 'invalidado') {
                                $confirmacaoTexto = 'Invalidado';
                            } elseif (isset($registro['status']) && $registro['status'] === 'presente') {
                                $confirmacaoTexto = (isset($registro['status_confirmacao']) && $registro['status_confirmacao'] === 'confirmado') ? 'Confirmado' : 'Pendente';
                            } else {
                                $confirmacaoTexto = '-';
                            }

                            $obsTexto = isset($registro['obs']) && $registro['obs'] !== '' ? (string)$registro['obs'] : '-';

                            $registroUpdatedFmt = '-';
                            if (!empty($registro['updated_at'])) {
                                $tsUpdated = strtotime((string)$registro['updated_at']);
                                if ($tsUpdated !== false) {
                                    $registroUpdatedFmt = date('d/m/Y H:i', $tsUpdated);
                                }
                            }

                            $justificativaAtual = $justificativaLatestByEmployee[(int)$employee['id']] ?? null;
                            $justificativaStatusLabel = 'Sem justificativa';
                            $justificativaBadgeClass = 'status-nao-marcado';

                            if (is_array($justificativaAtual)) {
                                $justStatus = mb_strtolower(trim((string)($justificativaAtual['status'] ?? 'pendente')));
                                if ($justStatus === 'aprovada') {
                                    $justificativaStatusLabel = 'Aprovada';
                                    $justificativaBadgeClass = 'status-presente';
                                } elseif ($justStatus === 'rejeitada') {
                                    $justificativaStatusLabel = 'Rejeitada';
                                    $justificativaBadgeClass = 'status-falta';
                                } else {
                                    $justificativaStatusLabel = 'Pendente';
                                    $justificativaBadgeClass = 'status-warning';
                                }
                            }

                            $justificativaAdminObs = is_array($justificativaAtual)
                                ? trim((string)($justificativaAtual['admin_observacao'] ?? ''))
                                : '';
                            $justificativaDecididoPor = is_array($justificativaAtual)
                                ? trim((string)($justificativaAtual['decidido_por'] ?? ''))
                                : '';
                            $justificativaDecididoEmFmt = '-';
                            if (is_array($justificativaAtual) && !empty($justificativaAtual['decidido_em'])) {
                                $tsDecidido = strtotime((string)$justificativaAtual['decidido_em']);
                                if ($tsDecidido !== false) {
                                    $justificativaDecididoEmFmt = date('d/m/Y H:i', $tsDecidido);
                                }
                            }
                            $justificativaAnexo = is_array($justificativaAtual)
                                ? trim((string)($justificativaAtual['anexo_path'] ?? ''))
                                : '';
                        ?>
                        <tr class="fr-row" data-employee-id="<?php echo (int)$employee['id']; ?>"
                            data-funcionario-nome="<?php echo htmlspecialchars((string)$employee['name']); ?>"
                            data-presenca-date="<?php echo htmlspecialchars($dateIso); ?>"
                            data-presenca-year="<?php echo $dateIso ? htmlspecialchars(substr($dateIso, 0, 4)) : ''; ?>"
                            data-presenca-month="<?php echo $dateIso ? htmlspecialchars(substr($dateIso, 0, 7)) : ''; ?>"
                            data-expected-start="<?php echo htmlspecialchars(substr((string)$horarioPrevisto, 0, 5)); ?>"
                            data-tolerancia-min="<?php echo (int)($estHorario['tolerancia_atraso_min'] ?? 0); ?>"
                            data-tipo-dia="<?php echo htmlspecialchars($tipoDiaLabel); ?>"
                            data-falta-tipo="<?php echo htmlspecialchars($faltaTipoRaw); ?>"
                            data-obs="<?php echo htmlspecialchars($obsTexto); ?>"
                            data-confirmacao="<?php echo htmlspecialchars($confirmacaoTexto); ?>"
                            data-hora-entrada="<?php echo isset($registro['hora_entrada']) && $registro['hora_entrada'] !== null ? htmlspecialchars(substr((string)$registro['hora_entrada'], 0, 5)) : '--:--'; ?>"
                            data-hora-saida="<?php echo isset($registro['hora_saida']) && $registro['hora_saida'] !== null ? htmlspecialchars(substr((string)$registro['hora_saida'], 0, 5)) : '--:--'; ?>"
                            data-horas="<?php echo htmlspecialchars($horasTrabalhadas); ?>"
                            data-atraso="<?php echo htmlspecialchars($atrasoTexto); ?>"
                            data-updated-at="<?php echo htmlspecialchars($registroUpdatedFmt); ?>"
                            data-date-display="<?php echo htmlspecialchars($dateDisplay); ?>"
                            data-just-label="<?php echo htmlspecialchars($justificativaStatusLabel); ?>"
                            data-just-motivo="<?php echo is_array($justificativaAtual) ? htmlspecialchars(mb_substr((string)($justificativaAtual['motivo'] ?? ''), 0, 300)) : ''; ?>"
                            data-just-data="<?php echo is_array($justificativaAtual) ? htmlspecialchars((string)($justificativaAtual['data_ocorrencia'] ?? '')) : ''; ?>"
                            data-just-tipo="<?php echo is_array($justificativaAtual) ? htmlspecialchars((string)($justificativaAtual['tipo'] ?? '')) : ''; ?>"
                            data-just-admin-obs="<?php echo htmlspecialchars($justificativaAdminObs !== '' ? $justificativaAdminObs : '-'); ?>"
                            data-just-decidido-por="<?php echo htmlspecialchars($justificativaDecididoPor !== '' ? ('#' . $justificativaDecididoPor) : '-'); ?>"
                            data-just-decidido-em="<?php echo htmlspecialchars($justificativaDecididoEmFmt); ?>"
                            data-just-anexo="<?php echo htmlspecialchars($justificativaAnexo); ?>"
                            data-employee-status-key="<?php echo htmlspecialchars((string)($employee['status'] ?? '')); ?>"
                            data-roteiro="<?php echo $_timelineEventosJson; ?>">
                            <td class="fr-td-emp">
                                <div class="fr-emp-cell">
                                    <div class="fr-av" style="background:linear-gradient(135deg,#475569,#334155);">
                                        <?php if (!empty($employee['profile_picture'])): ?>
                                        <img src="../<?php echo htmlspecialchars($employee['profile_picture']); ?>"
                                            alt="<?php echo htmlspecialchars($employee['name']); ?>" class="fr-av-img">
                                        <?php else: ?>
                                        <?php
                                            $partsName = preg_split('/\s+/', trim((string)$employee['name'])) ?: [];
                                            $initials = '';
                                            if (!empty($partsName[0])) $initials .= mb_strtoupper(mb_substr($partsName[0], 0, 1));
                                            if (count($partsName) > 1 && !empty($partsName[1])) $initials .= mb_strtoupper(mb_substr($partsName[1], 0, 1));
                                            echo htmlspecialchars($initials ?: 'FN');
                                        ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="fr-emp-info">
                                        <span class="fr-emp-name"><?php echo htmlspecialchars($employee['name']); ?></span>
                                    </div>
                                </div>
                            </td>

                            <td class="fr-td-status">
                                <?php
                                // Priorizar status do funcionario (ex: ferias, inativo) sobre status automatico
                                $empStatusRaw = isset($employee['status']) ? $employee['status'] : '';
                                $empStatus = mb_strtolower(trim((string)$empStatusRaw));

                                if (in_array($empStatus, ['ferias', 'férias'], true)) {
                                    $pClass = 'status-ferias';
                                    $pLabel = 'Ferias';
                                    $statusKey = 'ferias';
                                } elseif (in_array($empStatus, ['inactive', 'inativo'], true)) {
                                    $pClass = 'status-inactive';
                                    $pLabel = 'Inativo';
                                    $statusKey = 'inativo';
                                } else {
                                    $pClass = $status_classe;
                                    $pLabel = $status_texto;
                                    $normalizedStatusTexto = mb_strtolower(trim((string)$status_texto));

                                    if (mb_stripos($normalizedStatusTexto, 'sem turno') !== false) {
                                        $statusKey = 'sem-turno';
                                    } elseif (mb_stripos($normalizedStatusTexto, 'falta') !== false) {
                                        $statusKey = 'falta';
                                    } elseif (mb_stripos($normalizedStatusTexto, 'em aberto') !== false) {
                                        $statusKey = 'em-aberto';
                                    } elseif (mb_stripos($normalizedStatusTexto, 'atras') !== false) {
                                        $statusKey = 'atrasado';
                                    } elseif (mb_stripos($normalizedStatusTexto, 'presente') !== false) {
                                        $statusKey = 'presente';
                                    } elseif (mb_stripos($normalizedStatusTexto, 'nao regist') !== false || mb_stripos($normalizedStatusTexto, 'não regist') !== false) {
                                        $statusKey = 'nao-registrado';
                                    } else {
                                        $statusKey = 'invalidado';
                                    }
                                }
                                    ?>
                                <span class="status-badge <?php echo $pClass; ?>"
                                    id="attendance-status-<?php echo $employee['id']; ?>"
                                    data-status-key="<?php echo htmlspecialchars($statusKey); ?>"><?php echo $pLabel; ?></span>
                            </td>

                            <td><?php echo $dateDisplay; ?></td>
                            <?php $_totalEventosCell = count($_timelineEventos); ?>
                            <td class="fr-td-roteiro">
                                <div class="fr-roteiro">
                                    <?php if ($_totalEventosCell === 0): ?>
                                        <span class="fr-roteiro-label">Sem registo</span>
                                    <?php elseif ($_totalEventosCell <= 3): ?>
                                        <?php foreach ($_timelineEventos as $_iCell => $_evCell): ?>
                                            <?php if ($_iCell > 0): ?><span class="fr-roteiro-sep"></span><?php endif; ?>
                                            <span class="fr-roteiro-item" title="<?php echo htmlspecialchars($_evCell['label'] . ($_evCell['hora'] ? ' ' . $_evCell['hora'] : '')); ?>">
                                                <span class="fr-roteiro-dot <?php echo $_evCell['cls']; ?>"><i class="fas <?php echo $_evCell['icon']; ?>"></i></span>
                                                <?php if ($_evCell['hora']): ?><span class="fr-roteiro-time"><?php echo $_evCell['hora']; ?></span><?php endif; ?>
                                            </span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <?php $_firstCell = $_timelineEventos[0]; $_lastCell = $_timelineEventos[$_totalEventosCell - 1]; $_hiddenCell = $_totalEventosCell - 2; ?>
                                        <span class="fr-roteiro-item" title="<?php echo htmlspecialchars($_firstCell['label'] . ' ' . $_firstCell['hora']); ?>">
                                            <span class="fr-roteiro-dot <?php echo $_firstCell['cls']; ?>"><i class="fas <?php echo $_firstCell['icon']; ?>"></i></span>
                                            <span class="fr-roteiro-time"><?php echo $_firstCell['hora']; ?></span>
                                        </span>
                                        <span class="fr-roteiro-sep"></span>
                                        <span class="fr-roteiro-more" title="+<?php echo $_hiddenCell; ?> evento(s) — clique em Ver detalhes">+<?php echo $_hiddenCell; ?></span>
                                        <span class="fr-roteiro-sep"></span>
                                        <span class="fr-roteiro-item" title="<?php echo htmlspecialchars($_lastCell['label'] . ' ' . ($_lastCell['hora'] ?? '')); ?>">
                                            <span class="fr-roteiro-dot <?php echo $_lastCell['cls']; ?>"><i class="fas <?php echo $_lastCell['icon']; ?>"></i></span>
                                            <?php if ($_lastCell['hora']): ?><span class="fr-roteiro-time"><?php echo $_lastCell['hora']; ?></span><?php else: ?><span class="fr-roteiro-label">Em curso</span><?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="fr-td-acts">
                                <div class="fr-acts">
                                    <?php if ($statusKey === 'sem-turno'): ?>
                                    <button type="button" class="fr-btn fr-btn-activate" title="Atribuir turno"
                                        onclick="resolverSemTurno(<?php echo (int)$employee['id']; ?>, '<?php echo htmlspecialchars((string)$employee['name'], ENT_QUOTES); ?>')">
                                        <i class="fas fa-calendar-plus"></i>
                                    </button>
                                    <?php endif; ?>
                                    <button type="button" class="fr-btn fr-btn-view" title="Ver detalhes"
                                        onclick="verDetalhesPresenca(<?php echo $employee['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" class="fr-btn fr-btn-edit" title="Editar registo"
                                        onclick="editarPresenca(<?php echo $employee['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <!-- Modal de Visualização de Presença -->
            <div id="modalVerPresenca" class="modal" style="display:none;">
                <div class="am-sheet vm-sheet">
                    <button class="am-close" id="closeVerPresenca" type="button">&times;</button>

                    <!-- Navigation -->
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:.5rem; margin-bottom:1rem;">
                        <button type="button" id="view-presenca-prev" class="am-btn-cancel"
                            onclick="showPrevPresencaDetails()" style="padding:.38rem .75rem;">
                            <i class="fas fa-chevron-left"></i> Anterior
                        </button>
                        <span id="view-presenca-nav-indicator" class="pa-chip" style="min-width:56px; text-align:center;">0/0</span>
                        <button type="button" id="view-presenca-next" class="am-btn-cancel"
                            onclick="showNextPresencaDetails()" style="padding:.38rem .75rem;">
                            Próximo <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>

                    <!-- Hero -->
                    <div class="vm-hero">
                        <div id="view-presenca-av" class="vm-hero-av" style="font-size:.88rem; font-weight:700;">--</div>
                        <div class="vm-hero-info">
                            <h2 class="vm-hero-name" id="view-presenca-funcionario"></h2>
                            <div id="view-presenca-status" style="margin-top:4px;"></div>
                        </div>
                    </div>

                    <!-- Roteiro do dia -->
                    <div class="vm-section">
                        <div class="vm-sec-lbl"><i class="fas fa-route"></i> Roteiro do Dia</div>
                        <div id="view-presenca-roteiro-full" class="roteiro-dia">
                            <span class="fr-roteiro-label">Sem registo.</span>
                        </div>
                    </div>

                    <!-- Registo do dia -->
                    <div class="vm-section">
                        <div class="vm-sec-lbl"><i class="fas fa-calendar-check"></i> Registo do Dia</div>
                        <div class="vm-g3">
                            <div>
                                <div class="vm-field-label">Data</div>
                                <div class="vm-field-value" id="view-presenca-data">-</div>
                            </div>
                            <div>
                                <div class="vm-field-label">Tipo de Dia</div>
                                <div class="vm-field-value" id="view-presenca-tipo-dia">-</div>
                            </div>
                            <div>
                                <div class="vm-field-label">Turno Previsto</div>
                                <div class="vm-field-value" id="view-presenca-turno-previsto">-</div>
                            </div>
                            <div>
                                <div class="vm-field-label">Última Atualização</div>
                                <div class="vm-field-value" id="view-presenca-updated-at">-</div>
                            </div>
                        </div>
                    </div>

                    <!-- Horas -->
                    <div class="vm-section">
                        <div class="vm-sec-lbl"><i class="fas fa-clock"></i> Horas</div>
                        <div class="vm-g3">
                            <div>
                                <div class="vm-field-label">Entrada</div>
                                <div class="vm-field-value" id="view-presenca-entrada">-</div>
                            </div>
                            <div>
                                <div class="vm-field-label">Saída</div>
                                <div class="vm-field-value" id="view-presenca-saida">-</div>
                            </div>
                            <div>
                                <div class="vm-field-label">Horas</div>
                                <div class="vm-field-value" id="view-presenca-horas">-</div>
                            </div>
                            <div>
                                <div class="vm-field-label">Atraso</div>
                                <div class="vm-field-value" id="view-presenca-atraso">-</div>
                            </div>
                            <div>
                                <div class="vm-field-label">Atraso (min)</div>
                                <div class="vm-field-value" id="view-presenca-atraso-minutos">—</div>
                            </div>
                            <div>
                                <div class="vm-field-label">Horas Extras</div>
                                <div class="vm-field-value" id="view-presenca-horas-extras">00:00</div>
                            </div>
                        </div>
                    </div>

                    <!-- Resumo -->
                    <div class="vm-section">
                        <div class="vm-sec-lbl"><i class="fas fa-chart-bar"></i> Resumo</div>
                        <div class="vm-g3">
                            <div>
                                <div class="vm-field-label">Dias Trabalhados</div>
                                <div class="vm-field-value" id="view-presenca-dias-trabalhados">0</div>
                            </div>
                            <div>
                                <div class="vm-field-label">Número de Faltas</div>
                                <div class="vm-field-value" id="view-presenca-numero-faltas">0</div>
                            </div>
                            <div>
                                <div class="vm-field-label">Confirmação</div>
                                <div class="vm-field-value" id="view-presenca-confirmacao">-</div>
                            </div>
                        </div>
                    </div>

                    <!-- Observações -->
                    <div class="vm-section">
                        <div class="vm-sec-lbl"><i class="fas fa-sticky-note"></i> Observações</div>
                        <div class="vm-g2">
                            <div>
                                <div class="vm-field-label">Tipo de Falta</div>
                                <div class="vm-field-value" id="view-presenca-falta-tipo">-</div>
                            </div>
                            <div class="vm-full">
                                <div class="vm-field-label">Observação</div>
                                <div class="vm-field-value" id="view-presenca-obs">-</div>
                            </div>
                        </div>
                    </div>
















                    <!-- Justificativa -->
                    <div id="view-presenca-just-section" class="vm-section" style="display:none; border:1px solid rgba(139,92,246,.35); border-radius:10px; padding:.75rem;">
                        <div class="vm-sec-lbl" style="color:#a78bfa;"><i class="fas fa-file-alt"></i> Justificativa</div>
                        <div class="vm-g3">
                            <div>
                                <div class="vm-field-label">Estado</div>
                                <div class="vm-field-value" id="view-presenca-just-status">-</div>
                            </div>
                            <div>
                                <div class="vm-field-label">Tipo</div>
                                <div class="vm-field-value" id="view-presenca-just-tipo">-</div>
                            </div>
                            <div>
                                <div class="vm-field-label">Data Ocorrência</div>
                                <div class="vm-field-value" id="view-presenca-just-data">-</div>
                            </div>
                            <div>
                                <div class="vm-field-label">Decidido Em</div>
                                <div class="vm-field-value" id="view-presenca-just-decidido-em">-</div>
                            </div>
                            <div>
                                <div class="vm-field-label">Decidido Por</div>
                                <div class="vm-field-value" id="view-presenca-just-decidido-por">-</div>
                            </div>
                        </div>
                        <div style="margin-top:.65rem;">
                            <div class="vm-field-label">Motivo</div>
                            <div class="vm-field-value" id="view-presenca-just-motivo" style="word-break:break-word;">-</div>
                        </div>
                        <div style="margin-top:.5rem;">
                            <div class="vm-field-label">Observação do Admin</div>
                            <div class="vm-field-value" id="view-presenca-just-admin-obs" style="word-break:break-word;">-</div>
                        </div>
                        <div id="view-presenca-just-anexo-wrap" style="display:none; margin-top:.5rem;">
                            <div class="vm-field-label">Anexo</div>
                            <a id="view-presenca-just-anexo" href="#" target="_blank" rel="noopener noreferrer" style="color:#93c5fd; font-size:.875rem;">Ver anexo</a>
                        </div>
                    </div>

                    <!-- Últimos 7 dias -->
                    <div id="view-presenca-history-section" class="vm-section" style="border:1px solid var(--border-primary); border-radius:10px; padding:.75rem;">
                        <div class="vm-sec-lbl"><i class="fas fa-chart-line"></i> Últimos 7 dias</div>
                        <div style="display:grid; grid-template-columns:88px 1fr 60px 60px; gap:.5rem; padding:0 4px 6px; color:var(--text-secondary); font-size:.78rem; font-weight:700;">
                            <span>Data</span><span>Status</span><span>Entrada</span><span>Saída</span>
                        </div>
                        <div id="view-presenca-mini-history-body" style="max-height:180px; overflow:auto; border-top:1px solid var(--border-primary); border-bottom:1px solid var(--border-primary);">
                            <div style="padding:8px; color:var(--text-secondary);">Abra um registo para carregar o histórico.</div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="am-footer" style="flex-wrap:wrap; margin-top:1rem;">
                        <button type="button" class="am-btn-cancel" onclick="copyPresencaDetailLink()"><i class="fas fa-link"></i> Copiar link</button>
                        <button type="button" class="am-btn-cancel" onclick="printPresencaDetail()"><i class="fas fa-print"></i> Imprimir</button>
                        <button type="button" class="am-btn-cancel" onclick="exportPresencaDetailPDF()"><i class="fas fa-file-pdf"></i> PDF</button>
                        <button type="button" class="am-btn-cancel" onclick="openSolicitacoesFromViewModal()"><i class="fas fa-inbox"></i> Solicitações</button>
                        <button type="button" class="am-btn-submit" onclick="editarPresencaFromViewModal()"><i class="fas fa-edit"></i> Editar registo</button>
                    </div>
                </div>
            </div>




            <!-- Modal de Edição de Presença -->
            <div id="modalEditarPresenca" class="modal" style="display:none;">
                <div class="am-sheet" style="max-width:440px;">
                    <button class="am-close" id="closeEditarPresenca" type="button">&times;</button>
                    <div class="am-header">
                        <div class="am-header-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
                            <i class="fas fa-edit"></i>
                        </div>
                        <div>
                            <h2 class="am-title">Editar Registo</h2>
                            <p class="am-subtitle">Alterar dados de presença</p>
                        </div>
                    </div>
                    <form id="formEditarPresenca">
                        <input type="hidden" id="edit-presenca-employee-id" name="employee_id">
                        <input type="hidden" id="edit-presenca-target-date" name="target_date" value="">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-tag"></i> Classificação</div>
                            <div class="am-g2">
                                <div class="am-f am-f-full">
                                    <label class="am-lbl">Tipo de Dia</label>
                                    <select id="edit-presenca-tipo-dia" name="tipo_dia" class="am-inp am-sel" required>
                                        <option value="normal">Normal</option>
                                        <option value="folga">Folga</option>
                                        <option value="feriado">Feriado</option>
                                        <option value="falta">Falta</option>
                                    </select>
                                </div>
                                <div class="am-f am-f-full">
                                    <label class="am-lbl">Status</label>
                                    <select id="edit-presenca-status" name="status" class="am-inp am-sel" required>
                                        <option value="presente">Presente</option>
                                        <option value="falta">Falta</option>
                                        <option value="invalidado">Invalidado</option>
                                    </select>
                                </div>
                                <div id="edit-presenca-falta-tipo-wrap" class="am-f am-f-full" style="display:none;">
                                    <label class="am-lbl">Tipo de Falta</label>
                                    <select id="edit-presenca-falta-tipo" name="falta_tipo" class="am-inp am-sel">
                                        <option value="injustificada">Falta Injustificada</option>
                                        <option value="justificada">Falta Justificada</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-clock"></i> Horários</div>
                            <div class="am-g2">
                                <div class="am-f">
                                    <label class="am-lbl">Entrada</label>
                                    <input type="time" id="edit-presenca-entrada" name="hora_entrada" class="am-inp">
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl">Saída</label>
                                    <input type="time" id="edit-presenca-saida" name="hora_saida" class="am-inp">
                                </div>
                            </div>
                            <div id="edit-presenca-time-error" class="am-error" style="display:none; margin-top:6px;">
                                A hora de saída deve ser maior que a hora de entrada.
                            </div>
                        </div>
                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-sticky-note"></i> Observação</div>
                            <div class="am-f">
                                <textarea id="edit-presenca-obs" name="obs" class="am-inp" rows="2" style="resize:vertical;"></textarea>
                            </div>
                        </div>
                        <div class="am-footer">
                            <button type="button" class="am-btn-cancel" id="cancelEditarPresenca">Cancelar</button>
                            <button type="submit" class="am-btn-submit" style="background:linear-gradient(135deg,#f59e0b,#d97706); box-shadow:0 4px 14px rgba(245,158,11,.3);">Guardar</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>













        <?php
        $justificativasAprovadas = 0;
        $justificativasRejeitadas = 0;
        $solicitacoesHistoricoAprovadas = [];
        $solicitacoesHistoricoRejeitadas = [];
        foreach ($allJustificativas as $justificativaResumo) {
            $statusResumo = mb_strtolower(trim((string)($justificativaResumo['status'] ?? 'pendente')));
            if ($statusResumo === 'aprovada') {
                $justificativasAprovadas++;
                $solicitacoesHistoricoAprovadas[] = [
                    'tipo' => 'Justificativa',
                    'funcionario' => (string)($justificativaResumo['employee_name'] ?? 'Funcionário'),
                    'employee_profile_picture' => $justificativaResumo['employee_profile_picture'] ?? '',
                    'data_ref' => (string)($justificativaResumo['data_ocorrencia'] ?? ''),
                    'detalhe' => (string)($justificativaResumo['tipo'] ?? 'falta'),
                    'status_label' => 'Aprovada'
                ];
            } elseif ($statusResumo === 'rejeitada') {
                $justificativasRejeitadas++;
                $solicitacoesHistoricoRejeitadas[] = [
                    'tipo' => 'Justificativa',
                    'funcionario' => (string)($justificativaResumo['employee_name'] ?? 'Funcionário'),
                    'employee_profile_picture' => $justificativaResumo['employee_profile_picture'] ?? '',
                    'data_ref' => (string)($justificativaResumo['data_ocorrencia'] ?? ''),
                    'detalhe' => (string)($justificativaResumo['tipo'] ?? 'falta'),
                    'status_label' => 'Rejeitada'
                ];
            }
        }

        $presencasPendentes = [];
        $presencasAprovadasSolic = 0;
        $presencasRejeitadasSolic = 0;
        try {
            $hasClientIdInPonto = false;
            try {
                $hasClientIdInPonto = (bool)$pdo->query("SHOW COLUMNS FROM registros_ponto LIKE 'client_id'")->fetch();
            } catch (Throwable $e) {
            }

            $solPontoSql    = '';
            $solPontoParams = [(int)$loggedInClientId];
            if ($solServerStart !== '') {
                $solPontoSql    .= " AND DATE(rp.{$pontoDateColumn}) >= ?";
                $solPontoParams[] = $solServerStart;
            }
            if ($solServerEnd !== '') {
                $solPontoSql    .= " AND DATE(rp.{$pontoDateColumn}) <= ?";
                $solPontoParams[] = $solServerEnd;
            }

            if ($hasClientIdInPonto) {
                $stmtSolicPres = $pdo->prepare(
                    "SELECT rp.id, rp.funcionario_id AS employee_id, rp.status, rp.status_confirmacao,
                    rp.hora_entrada, rp.hora_saida, rp.tipo_dia, rp.falta_tipo,
                    DATE(rp.{$pontoDateColumn}) AS data_ref, e.name AS employee_name, e.profile_picture AS employee_profile_picture
             FROM registros_ponto rp
             INNER JOIN employees e ON e.id = rp.funcionario_id
             WHERE e.client_id = ? {$solPontoSql}
             ORDER BY rp.{$pontoDateColumn} DESC, rp.id DESC"
                );
                $stmtSolicPres->execute($solPontoParams);
            } else {
                $stmtSolicPres = $pdo->prepare(
                    "SELECT rp.id, rp.funcionario_id AS employee_id, rp.status, rp.status_confirmacao,
                    rp.hora_entrada, rp.hora_saida, rp.tipo_dia, rp.falta_tipo,
                    DATE(rp.{$pontoDateColumn}) AS data_ref, e.name AS employee_name, e.profile_picture AS employee_profile_picture
             FROM registros_ponto rp
             INNER JOIN employees e ON e.id = rp.funcionario_id
             WHERE e.client_id = ? {$solPontoSql}
             ORDER BY rp.{$pontoDateColumn} DESC, rp.id DESC"
                );
                $stmtSolicPres->execute($solPontoParams);
            }

            $presRows = $stmtSolicPres->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $seenPresence = [];
            foreach ($presRows as $pRow) {
                $empId = (int)($pRow['employee_id'] ?? 0);
                $dateRef = (string)($pRow['data_ref'] ?? '');
                if ($empId <= 0 || $dateRef === '') {
                    continue;
                }

                $key = $empId . '|' . $dateRef;
                if (isset($seenPresence[$key])) {
                    continue;
                }
                $seenPresence[$key] = true;

                $status = mb_strtolower(trim((string)($pRow['status'] ?? '')));
                $confirm = mb_strtolower(trim((string)($pRow['status_confirmacao'] ?? 'pendente')));
                $hasAnyData =
                    trim((string)($pRow['hora_entrada'] ?? '')) !== '' ||
                    trim((string)($pRow['hora_saida'] ?? '')) !== '' ||
                    $status !== '';

                if (!$hasAnyData) {
                    continue;
                }

                if ($status === 'invalidado') {
                    $presencasRejeitadasSolic++;
                    $solicitacoesHistoricoRejeitadas[] = [
                        'tipo' => 'Presença',
                        'funcionario' => (string)($pRow['employee_name'] ?? 'Funcionário'),
                        'employee_profile_picture' => $pRow['employee_profile_picture'] ?? '',
                        'data_ref' => (string)($pRow['data_ref'] ?? ''),
                        'detalhe' => 'Entrada: ' . (trim((string)($pRow['hora_entrada'] ?? '')) !== '' ? substr((string)$pRow['hora_entrada'], 0, 5) : '--:--') . ' | Saída: ' . (trim((string)($pRow['hora_saida'] ?? '')) !== '' ? substr((string)$pRow['hora_saida'], 0, 5) : '--:--'),
                        'status_label' => 'Rejeitada'
                    ];
                    continue;
                }

                if ($confirm === 'confirmado') {
                    $presencasAprovadasSolic++;
                    $solicitacoesHistoricoAprovadas[] = [
                        'tipo' => 'Presença',
                        'funcionario' => (string)($pRow['employee_name'] ?? 'Funcionário'),
                        'employee_profile_picture' => $pRow['employee_profile_picture'] ?? '',
                        'data_ref' => (string)($pRow['data_ref'] ?? ''),
                        'detalhe' => 'Entrada: ' . (trim((string)($pRow['hora_entrada'] ?? '')) !== '' ? substr((string)$pRow['hora_entrada'], 0, 5) : '--:--') . ' | Saída: ' . (trim((string)($pRow['hora_saida'] ?? '')) !== '' ? substr((string)$pRow['hora_saida'], 0, 5) : '--:--'),
                        'status_label' => 'Aprovada'
                    ];
                    continue;
                }

                $presencasPendentes[] = $pRow;
            }
        } catch (Throwable $e) {
            error_log('Erro ao carregar solicitações de presença: ' . $e->getMessage());
        }

        $trocasTurnoPendentes = [];
        $trocasTurnoAprovadasSolic = 0;
        $trocasTurnoRejeitadasSolic = 0;
        try {
            $solSwapSql = '';
            $solSwapParams = [(int)$loggedInClientId];
            if ($solServerStart !== '') {
                $solSwapSql .= ' AND DATE(r.requested_at) >= ?';
                $solSwapParams[] = $solServerStart;
            }
            if ($solServerEnd !== '') {
                $solSwapSql .= ' AND DATE(r.requested_at) <= ?';
                $solSwapParams[] = $solServerEnd;
            }

            $stmtSwapSolic = $pdo->prepare(
                "SELECT r.id, r.requested_date, r.reason, r.status, r.requested_at,
                        r.requester_employee_id, r.target_employee_id,
                        r.requester_turno_id, r.target_turno_id,
                        er.name AS requester_name, er.profile_picture AS requester_profile_picture,
                        et.name AS target_name, et.profile_picture AS target_profile_picture,
                        rt.turno_tipo AS requester_turno_tipo, rt.horario_inicio AS requester_horario_inicio, rt.horario_fim AS requester_horario_fim, rt.dias_semana AS requester_dias,
                        tt.turno_tipo AS target_turno_tipo, tt.horario_inicio AS target_horario_inicio, tt.horario_fim AS target_horario_fim, tt.dias_semana AS target_dias
                 FROM turno_swap_requests r
                 INNER JOIN employees er ON er.id = r.requester_employee_id
                 INNER JOIN employees et ON et.id = r.target_employee_id
                 LEFT JOIN turnos rt ON rt.id = r.requester_turno_id
                 LEFT JOIN turnos tt ON tt.id = r.target_turno_id
                 WHERE r.client_id = ? {$solSwapSql}
                 ORDER BY r.requested_at DESC, r.id DESC"
            );
            $stmtSwapSolic->execute($solSwapParams);
            $swapRows = $stmtSwapSolic->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($swapRows as $swapRow) {
                $swapStatus = mb_strtolower(trim((string)($swapRow['status'] ?? 'pendente_colega')));
                $requestDate = (string)($swapRow['requested_date'] ?? '');
                $detail = 'Troca: '
                    . (string)($swapRow['requester_turno_tipo'] ?? '-') . ' '
                    . substr((string)($swapRow['requester_horario_inicio'] ?? ''), 0, 5) . '-'
                    . substr((string)($swapRow['requester_horario_fim'] ?? ''), 0, 5)
                    . ' ↔ '
                    . (string)($swapRow['target_turno_tipo'] ?? '-') . ' '
                    . substr((string)($swapRow['target_horario_inicio'] ?? ''), 0, 5) . '-'
                    . substr((string)($swapRow['target_horario_fim'] ?? ''), 0, 5);
                if ($requestDate !== '') {
                    $detail .= ' | Data: ' . $requestDate;
                }

                if (in_array($swapStatus, ['aprovada', 'aprovado'], true)) {
                    $trocasTurnoAprovadasSolic++;
                    $solicitacoesHistoricoAprovadas[] = [
                        'tipo' => 'Troca Turno',
                        'funcionario' => (string)($swapRow['requester_name'] ?? 'Funcionário'),
                        'employee_profile_picture' => $swapRow['requester_profile_picture'] ?? '',
                        'data_ref' => (string)($swapRow['requested_at'] ?? ''),
                        'detalhe' => $detail,
                        'status_label' => 'Aprovada'
                    ];
                    continue;
                }

                if (in_array($swapStatus, ['rejeitada', 'rejeitado'], true)) {
                    $trocasTurnoRejeitadasSolic++;
                    $solicitacoesHistoricoRejeitadas[] = [
                        'tipo' => 'Troca Turno',
                        'funcionario' => (string)($swapRow['requester_name'] ?? 'Funcionário'),
                        'employee_profile_picture' => $swapRow['requester_profile_picture'] ?? '',
                        'data_ref' => (string)($swapRow['requested_at'] ?? ''),
                        'detalhe' => $detail,
                        'status_label' => 'Rejeitada'
                    ];
                    continue;
                }

                if (in_array($swapStatus, ['rejeitada_colega', 'rejeitado_colega'], true)) {
                    $trocasTurnoRejeitadasSolic++;
                    $solicitacoesHistoricoRejeitadas[] = [
                        'tipo' => 'Troca Turno',
                        'funcionario' => (string)($swapRow['requester_name'] ?? 'Funcionário'),
                        'employee_profile_picture' => $swapRow['requester_profile_picture'] ?? '',
                        'data_ref' => (string)($swapRow['requested_at'] ?? ''),
                        'detalhe' => $detail,
                        'status_label' => 'Rejeitada (colega)'
                    ];
                    continue;
                }

                if (in_array($swapStatus, ['pendente_admin', 'pendente'], true)) {
                    $trocasTurnoPendentes[] = $swapRow;
                }
            }
        } catch (Throwable $e) {
            error_log('Erro ao carregar solicitações de troca de turno: ' . $e->getMessage());
        }

        $gorjetasPendentes = [];
        $gorjetasAprovadasSolic = 0;
        $gorjetasRejeitadasSolic = 0;
        $feriasAprovadasSolic = 0;
        $feriasRejeitadasSolic = 0;
        try {
            $gorjetaDateColumn = 'data';
            try {
                $gorjetaCols = $pdo->query('SHOW COLUMNS FROM gorjetas')->fetchAll(PDO::FETCH_COLUMN) ?: [];
                if (!in_array('data', $gorjetaCols, true) && in_array('data_registro', $gorjetaCols, true)) {
                    $gorjetaDateColumn = 'data_registro';
                }
            } catch (Throwable $e) {
            }

            $stmtSolicGor = $pdo->prepare(
                "SELECT g.id, g.funcionario_id AS employee_id, g.valor, g.status, g.forma_pagamento, g.origem,
                g.turno, DATE(g.{$gorjetaDateColumn}) AS data_ref, e.name AS employee_name, e.profile_picture AS employee_profile_picture
         FROM gorjetas g
         INNER JOIN employees e ON e.id = g.funcionario_id
         WHERE g.client_id = ?
         ORDER BY g.{$gorjetaDateColumn} DESC, g.id DESC"
            );
            $stmtSolicGor->execute([(int)$loggedInClientId]);
            $gorRows = $stmtSolicGor->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($gorRows as $gRow) {
                $status = mb_strtolower(trim((string)($gRow['status'] ?? 'pendente')));
                if (in_array($status, ['pago', 'paid', 'confirmado', 'aprovado'], true)) {
                    $gorjetasAprovadasSolic++;
                    $solicitacoesHistoricoAprovadas[] = [
                        'tipo' => 'Gorjeta',
                        'funcionario' => (string)($gRow['employee_name'] ?? 'Funcionário'),
                        'employee_profile_picture' => $gRow['employee_profile_picture'] ?? '',
                        'data_ref' => (string)($gRow['data_ref'] ?? ''),
                        'detalhe' => '€' . number_format((float)($gRow['valor'] ?? 0), 2, ',', '.') . ' | Turno: ' . (string)($gRow['turno'] ?? '-'),
                        'status_label' => 'Aprovada'
                    ];
                    continue;
                }
                if (in_array($status, ['rejeitado', 'rejeitada', 'cancelado', 'cancelada'], true)) {
                    $gorjetasRejeitadasSolic++;
                    $solicitacoesHistoricoRejeitadas[] = [
                        'tipo' => 'Gorjeta',
                        'funcionario' => (string)($gRow['employee_name'] ?? 'Funcionário'),
                        'employee_profile_picture' => $gRow['employee_profile_picture'] ?? '',
                        'data_ref' => (string)($gRow['data_ref'] ?? ''),
                        'detalhe' => '€' . number_format((float)($gRow['valor'] ?? 0), 2, ',', '.') . ' | Turno: ' . (string)($gRow['turno'] ?? '-'),
                        'status_label' => 'Rejeitada'
                    ];
                    continue;
                }
                $gorjetasPendentes[] = $gRow;
            }
        } catch (Throwable $e) {
            error_log('Erro ao carregar solicitações de gorjetas: ' . $e->getMessage());
        }

        try {
            $feriasColsHist = $pdo->query('SHOW COLUMNS FROM ferias')->fetchAll(PDO::FETCH_COLUMN) ?: [];
            $feriasColsHist = array_map(static fn($c) => mb_strtolower((string)$c), $feriasColsHist);
            $feriasEmployeeColHist = in_array('funcionario_id', $feriasColsHist, true)
                ? 'funcionario_id'
                : (in_array('employee_id', $feriasColsHist, true) ? 'employee_id' : 'funcionario_id');

            $stmtSolicFeriasHist = $pdo->prepare(
                "SELECT f.id, f.{$feriasEmployeeColHist} AS employee_id, f.data_inicio, f.data_fim, f.status, f.motivo,
                        e.name AS employee_name, e.profile_picture AS employee_profile_picture
                 FROM ferias f
                 INNER JOIN employees e ON e.id = f.{$feriasEmployeeColHist}
                 WHERE e.client_id = ?
                 ORDER BY f.id DESC"
            );
            $stmtSolicFeriasHist->execute([(int)$loggedInClientId]);
            $feriasRowsHist = $stmtSolicFeriasHist->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($feriasRowsHist as $fRowHist) {
                $fStatus = mb_strtolower(trim((string)($fRowHist['status'] ?? 'pendente')));
                if (in_array($fStatus, ['pendente', 'pending', ''], true)) {
                    continue;
                }

                $detalhe = 'Período: '
                    . (string)($fRowHist['data_inicio'] ?? '-')
                    . ' a '
                    . (string)($fRowHist['data_fim'] ?? '-');
                $motivoFerias = trim((string)($fRowHist['motivo'] ?? ''));
                if ($motivoFerias !== '') {
                    $detalhe .= ' | Motivo: ' . $motivoFerias;
                }

                if (in_array($fStatus, ['aprovada', 'aprovado'], true)) {
                    $feriasAprovadasSolic++;
                    $solicitacoesHistoricoAprovadas[] = [
                        'tipo' => 'Férias',
                        'funcionario' => (string)($fRowHist['employee_name'] ?? 'Funcionário'),
                        'employee_profile_picture' => $fRowHist['employee_profile_picture'] ?? '',
                        'data_ref' => (string)($fRowHist['data_inicio'] ?? ''),
                        'detalhe' => $detalhe,
                        'status_label' => 'Aprovada'
                    ];
                    continue;
                }

                if (in_array($fStatus, ['rejeitada', 'rejeitado', 'recusada', 'recusado'], true)) {
                    $feriasRejeitadasSolic++;
                    $solicitacoesHistoricoRejeitadas[] = [
                        'tipo' => 'Férias',
                        'funcionario' => (string)($fRowHist['employee_name'] ?? 'Funcionário'),
                        'employee_profile_picture' => $fRowHist['employee_profile_picture'] ?? '',
                        'data_ref' => (string)($fRowHist['data_inicio'] ?? ''),
                        'detalhe' => $detalhe,
                        'status_label' => 'Rejeitada'
                    ];
                }
            }
        } catch (Throwable $e) {
            error_log('Erro ao carregar histórico de férias: ' . $e->getMessage());
        }

        usort($solicitacoesHistoricoAprovadas, function (array $a, array $b): int {
            $timeA = strtotime((string)($a['data_ref'] ?? '')) ?: 0;
            $timeB = strtotime((string)($b['data_ref'] ?? '')) ?: 0;
            return $timeB <=> $timeA;
        });

        usort($solicitacoesHistoricoRejeitadas, function (array $a, array $b): int {
            $timeA = strtotime((string)($a['data_ref'] ?? '')) ?: 0;
            $timeB = strtotime((string)($b['data_ref'] ?? '')) ?: 0;
            return $timeB <=> $timeA;
        });

        $solicitacoesHistoricoDecididas = array_merge($solicitacoesHistoricoAprovadas, $solicitacoesHistoricoRejeitadas);
        usort($solicitacoesHistoricoDecididas, function (array $a, array $b): int {
            $timeA = strtotime((string)($a['data_ref'] ?? '')) ?: 0;
            $timeB = strtotime((string)($b['data_ref'] ?? '')) ?: 0;
            return $timeB <=> $timeA;
        });

        $solicitacoesPendentesTotal = count($justificativasPendentes) + count($presencasPendentes) + count($gorjetasPendentes) + count($feriasPendentes) + count($trocasTurnoPendentes);
        $solicitacoesAprovadasTotal = $justificativasAprovadas + $presencasAprovadasSolic + $gorjetasAprovadasSolic + $feriasAprovadasSolic + $trocasTurnoAprovadasSolic;
        $solicitacoesRejeitadasTotal = $justificativasRejeitadas + $presencasRejeitadasSolic + $gorjetasRejeitadasSolic + $feriasRejeitadasSolic + $trocasTurnoRejeitadasSolic;
        $solicitacoesTotal =
            count($allJustificativas) +
            count($presencasPendentes) + $presencasAprovadasSolic + $presencasRejeitadasSolic +
            count($gorjetasPendentes) + $gorjetasAprovadasSolic + $gorjetasRejeitadasSolic +
            count($feriasPendentes) + $feriasAprovadasSolic + $feriasRejeitadasSolic +
            count($trocasTurnoPendentes) + $trocasTurnoAprovadasSolic + $trocasTurnoRejeitadasSolic;
        ?>























        <section id="solicitacoes-section" class="content-section">
            <?php
                $solReview = trim((string)($_GET['review'] ?? ''));
                $solPanelOpen = ($solServerStart !== '' || $solServerEnd !== '');
                $solTotalPendentes = count($justificativasPendentes) + count($presencasPendentes)
                    + count($gorjetasPendentes) + count($feriasPendentes) + count($trocasTurnoPendentes);
                $solTotalHistorico = (int)($solicitacoesAprovadasTotal + $solicitacoesRejeitadasTotal);
            ?>
            <?php if ($solReview === 'ok'): ?>
            <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;padding:.85rem 1.1rem;border-radius:10px;background:linear-gradient(90deg,#14532d,#166534);color:#ecfdf5;">
                <i class="fas fa-check-circle"></i> Operação concluída com sucesso.
            </div>
            <?php elseif ($solReview === 'error'): ?>
            <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;padding:.85rem 1.1rem;border-radius:10px;background:linear-gradient(90deg,#7f1d1d,#991b1b);color:#fef2f2;">
                <i class="fas fa-exclamation-circle"></i> Ocorreu um erro. Tente novamente.
            </div>
            <?php elseif ($solReview === 'csrf'): ?>
            <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;padding:.85rem 1.1rem;border-radius:10px;background:linear-gradient(90deg,#7f1d1d,#991b1b);color:#fef2f2;">
                <i class="fas fa-shield-alt"></i> Sessão expirada. Recarregue a página.
            </div>
            <?php endif; ?>

            <div class="frhd">
                <div class="frhd-left">
                    <div class="frhd-icon" style="background:linear-gradient(135deg,#6366f1,#4f46e5);box-shadow:0 4px 14px rgba(99,102,241,.35);">
                        <i class="fas fa-inbox"></i>
                    </div>
                    <div>
                        <h2 class="frhd-title">Solicitações</h2>
                        <p class="frhd-sub"><?php echo (int)$solTotalPendentes; ?> pendente<?php echo $solTotalPendentes !== 1 ? 's' : ''; ?> &middot; <?php echo (int)$solTotalHistorico; ?> no histórico</p>
                    </div>
                </div>
                <button type="button" class="fr-filter-toggle <?php echo $solPanelOpen ? 'pa-filter-open' : ''; ?>" id="solFilterToggle"
                    onclick="document.getElementById('solAdvFilters').classList.toggle('fr-adv-open'); this.classList.toggle('pa-filter-open')">
                    <i class="fas fa-calendar-alt"></i> Período
                    <span class="fr-filter-badge" id="solFilterBadge" style="<?php echo $solPanelOpen ? 'display:flex' : 'display:none'; ?>">
                        <?php echo (int)(($solServerStart !== '') + ($solServerEnd !== '')); ?>
                    </span>
                </button>
            </div>

            <style>
            .sol-kpi-total .fr-kpi-icon  { background:rgba(99,102,241,.12); color:#818cf8; }
            .sol-kpi-justif .fr-kpi-icon { background:rgba(245,158,11,.12); color:#fbbf24; }
            .sol-kpi-pres .fr-kpi-icon   { background:rgba(59,130,246,.12);  color:#60a5fa; }
            .sol-kpi-gorj .fr-kpi-icon   { background:rgba(16,185,129,.12);  color:#34d399; }
            .sol-kpi-fer .fr-kpi-icon    { background:rgba(14,165,233,.12);  color:#38bdf8; }
            .sol-kpi-troca .fr-kpi-icon  { background:rgba(163,230,53,.1);   color:#a3e635; }
            @keyframes solicitacaoBadgeFloat {
                0%   { transform:translateY(0) scale(1); }
                50%  { transform:translateY(-6px) scale(1.08); }
                100% { transform:translateY(0) scale(1); }
            }
            </style>
            <div class="fr-kpi-strip" style="grid-template-columns:repeat(6,1fr);">
                <div class="fr-kpi sol-kpi-total" style="position:relative;">
                    <div class="fr-kpi-icon"><i class="fas fa-inbox"></i></div>
                    <div class="fr-kpi-body">
                        <span class="fr-kpi-val"><?php echo (int)$solTotalPendentes; ?></span>
                        <span class="fr-kpi-lbl">Total Pendentes</span>
                        <span class="fr-kpi-pct">aguardam decisão</span>
                    </div>
                    <?php if ($solTotalPendentes > 0): ?>
                    <span style="position:absolute;top:-6px;right:-6px;min-width:20px;height:20px;border-radius:50%;background:#ef4444;color:#fff;font-size:.65rem;font-weight:900;display:flex;align-items:center;justify-content:center;animation:solicitacaoBadgeFloat 1.5s ease-in-out infinite;"><?php echo (int)$solTotalPendentes; ?></span>
                    <?php endif; ?>
                </div>
                <div class="fr-kpi sol-kpi-justif">
                    <div class="fr-kpi-icon"><i class="fas fa-file-medical"></i></div>
                    <div class="fr-kpi-body">
                        <span class="fr-kpi-val"><?php echo count($justificativasPendentes); ?></span>
                        <span class="fr-kpi-lbl">Justificativas</span>
                        <span class="fr-kpi-pct">faltas &amp; atrasos</span>
                    </div>
                </div>
                <div class="fr-kpi sol-kpi-pres">
                    <div class="fr-kpi-icon"><i class="fas fa-user-check"></i></div>
                    <div class="fr-kpi-body">
                        <span class="fr-kpi-val"><?php echo count($presencasPendentes); ?></span>
                        <span class="fr-kpi-lbl">Presenças</span>
                        <span class="fr-kpi-pct">marcações manuais</span>
                    </div>
                </div>
                <div class="fr-kpi sol-kpi-gorj">
                    <div class="fr-kpi-icon"><i class="fas fa-coins"></i></div>
                    <div class="fr-kpi-body">
                        <span class="fr-kpi-val"><?php echo count($gorjetasPendentes); ?></span>
                        <span class="fr-kpi-lbl">Gorjetas</span>
                        <span class="fr-kpi-pct">aguardam validação</span>
                    </div>
                </div>
                <div class="fr-kpi sol-kpi-fer">
                    <div class="fr-kpi-icon"><i class="fas fa-umbrella-beach"></i></div>
                    <div class="fr-kpi-body">
                        <span class="fr-kpi-val"><?php echo count($feriasPendentes); ?></span>
                        <span class="fr-kpi-lbl">Férias</span>
                        <span class="fr-kpi-pct">pedidos de férias</span>
                    </div>
                </div>
                <div class="fr-kpi sol-kpi-troca">
                    <div class="fr-kpi-icon"><i class="fas fa-exchange-alt"></i></div>
                    <div class="fr-kpi-body">
                        <span class="fr-kpi-val"><?php echo count($trocasTurnoPendentes); ?></span>
                        <span class="fr-kpi-lbl">Trocas</span>
                        <span class="fr-kpi-pct">aguardam admin</span>
                    </div>
                </div>
            </div>

            <div class="data-table fr-table-wrap" style="margin-top:.5rem;">
                <div class="fr-toolbar">
                    <div class="fr-toolbar-top">
                        <div class="fr-search-wrap">
                            <i class="fas fa-search fr-search-icon"></i>
                            <input type="text" id="solSearchInput" class="fr-search" placeholder="Pesquisar funcionário…">
                        </div>
                        <div class="fr-toolbar-right">
                            <span id="solResultCount" style="font-size:.78rem;color:#64748b;white-space:nowrap;"></span>
                        </div>
                    </div>
                    <div class="fr-chips">
                        <button class="fr-chip sol-chip-all active" data-sol-chip="" onclick="applySolChip(this)">
                            <i class="fas fa-th-large"></i> Pendentes
                            <span class="fr-chip-count"><?php echo (int)$solTotalPendentes; ?></span>
                        </button>
                        <button class="fr-chip sol-chip-justif" data-sol-chip="justificativa" onclick="applySolChip(this)">
                            <span class="fr-dot" style="background:#fbbf24;"></span> Justificativas
                            <span class="fr-chip-count"><?php echo count($justificativasPendentes); ?></span>
                        </button>
                        <button class="fr-chip sol-chip-pres" data-sol-chip="presenca" onclick="applySolChip(this)">
                            <span class="fr-dot fr-dot-blue"></span> Presenças
                            <span class="fr-chip-count"><?php echo count($presencasPendentes); ?></span>
                        </button>
                        <button class="fr-chip sol-chip-gorj" data-sol-chip="gorjeta" onclick="applySolChip(this)">
                            <span class="fr-dot fr-dot-green"></span> Gorjetas
                            <span class="fr-chip-count"><?php echo count($gorjetasPendentes); ?></span>
                        </button>
                        <button class="fr-chip sol-chip-fer" data-sol-chip="ferias" onclick="applySolChip(this)">
                            <span class="fr-dot" style="background:#38bdf8;"></span> Férias
                            <span class="fr-chip-count"><?php echo count($feriasPendentes); ?></span>
                        </button>
                        <button class="fr-chip sol-chip-troca" data-sol-chip="troca" onclick="applySolChip(this)">
                            <span class="fr-dot" style="background:#a3e635;"></span> Trocas
                            <span class="fr-chip-count"><?php echo count($trocasTurnoPendentes); ?></span>
                        </button>
                        <button class="fr-chip sol-chip-hist" data-sol-chip="historico" onclick="applySolChip(this)">
                            <span class="fr-dot" style="background:#64748b;"></span> Histórico
                            <span class="fr-chip-count"><?php echo (int)$solTotalHistorico; ?></span>
                        </button>
                    </div>
                    <div class="fr-adv-filters <?php echo $solPanelOpen ? 'fr-adv-open' : ''; ?>" id="solAdvFilters">
                        <input type="date" id="filterSolStart" class="fr-select" style="min-width:160px;"
                            title="Data inicial" value="<?php echo htmlspecialchars($solServerStart); ?>">
                        <input type="date" id="filterSolEnd" class="fr-select" style="min-width:160px;"
                            title="Data final" value="<?php echo htmlspecialchars($solServerEnd); ?>">
                        <button type="button" onclick="applySolicitacoesServerFilter()"
                            style="padding:.5rem 1rem;white-space:nowrap;background:linear-gradient(145deg,#3b82f6,#2563eb);color:#fff;border:none;border-radius:8px;font-size:.8rem;font-weight:600;cursor:pointer;">
                            <i class="fas fa-database"></i> Aplicar período
                        </button>
                        <button type="button" class="fr-clear-btn" onclick="clearSolicitacoesServerFilter()">
                            <i class="fas fa-eraser"></i> Limpar
                        </button>
                        <?php if ($solPanelOpen): ?>
                        <span style="font-size:.82rem;color:var(--text-secondary);background:var(--bg-tertiary);border:1px solid var(--border-primary);border-radius:8px;padding:.35rem .65rem;white-space:nowrap;">
                            <i class="fas fa-filter" style="margin-right:.3rem;"></i>
                            <?php
                            if ($solServerStart !== '' && $solServerEnd !== '') {
                                echo htmlspecialchars(date('d/m/Y', strtotime($solServerStart))) . ' – ' . htmlspecialchars(date('d/m/Y', strtotime($solServerEnd)));
                            } elseif ($solServerStart !== '') {
                                echo 'desde ' . htmlspecialchars(date('d/m/Y', strtotime($solServerStart)));
                            } else {
                                echo 'até ' . htmlspecialchars(date('d/m/Y', strtotime($solServerEnd)));
                            }
                            ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <style>
                .sol-chip-all.active     { background:rgba(99,102,241,.2);  color:#a5b4fc; border-color:rgba(99,102,241,.35); }
                .sol-chip-justif.active  { background:rgba(245,158,11,.2);  color:#fbbf24; border-color:rgba(245,158,11,.35); }
                .sol-chip-pres.active    { background:rgba(59,130,246,.2);  color:#60a5fa; border-color:rgba(59,130,246,.35); }
                .sol-chip-gorj.active    { background:rgba(16,185,129,.2);  color:#34d399; border-color:rgba(16,185,129,.35); }
                .sol-chip-fer.active     { background:rgba(14,165,233,.2);  color:#38bdf8; border-color:rgba(14,165,233,.35); }
                .sol-chip-troca.active   { background:rgba(163,230,53,.1);  color:#a3e635; border-color:rgba(163,230,53,.25); }
                .sol-chip-hist.active    { background:rgba(100,116,139,.18); color:#94a3b8; border-color:rgba(100,116,139,.3); }
                .sol-tipo-badge { display:inline-flex;align-items:center;gap:5px;padding:2px 8px;border-radius:6px;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em; }
                .sol-tipo-justif { background:rgba(245,158,11,.15); color:#fbbf24; }
                .sol-tipo-pres   { background:rgba(59,130,246,.15); color:#93c5fd; }
                .sol-tipo-gorj   { background:rgba(16,185,129,.15); color:#6ee7b7; }
                .sol-tipo-fer    { background:rgba(14,165,233,.15); color:#7dd3fc; }
                .sol-tipo-troca  { background:rgba(163,230,53,.1);  color:#bef264; }
                </style>

                <table class="table fr-table" id="solMainTable">
                    <thead>
                        <tr class="fr-thead-row">
                            <th class="fr-th-emp">Funcionário</th>
                            <th>Tipo</th>
                            <th>Data</th>
                            <th>Detalhe</th>
                            <th class="fr-th-status">Estado</th>
                            <th class="fr-th-acts">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="solMainTableBody">
                        <?php if ($solTotalPendentes === 0): ?>
                        <tr id="solEmptyState">
                            <td colspan="6" style="text-align:center;color:var(--text-secondary);padding:3rem 1rem;">
                                <i class="fas fa-inbox" style="font-size:2.5rem;opacity:.2;display:block;margin-bottom:.75rem;"></i>
                                Sem solicitações pendentes. Tudo em dia!
                            </td>
                        </tr>
                        <?php endif; ?>

                        <?php foreach ($justificativasPendentes as $pend):
                            $pendDataFmt = !empty($pend['data_ocorrencia']) ? date('d/m/Y', strtotime((string)$pend['data_ocorrencia'])) : 'N/D';
                            $pendTipoRaw = mb_strtolower(trim((string)($pend['tipo'] ?? 'falta')));
                            $tiposLabelMap = [
                                'falta'=>'Falta','atraso'=>'Atraso','doenca'=>'Doença',
                                'consulta_medica'=>'Consulta Médica','assistencia_familiar'=>'Assist. Familiar',
                                'falecimento_familiar'=>'Falecimento','casamento'=>'Casamento',
                                'maternidade_paternidade'=>'Maternidade/Pat.','formacao_profissional'=>'Formação',
                                'convocacao_judicial'=>'Conv. Judicial','acidente'=>'Acidente',
                                'transporte'=>'Transporte','motivo_pessoal'=>'Motivo Pessoal','outro'=>'Outro',
                            ];
                            $pendTipoLabel = $tiposLabelMap[$pendTipoRaw] ?? ucfirst(str_replace('_', ' ', $pendTipoRaw));
                            $pendMotivo = trim((string)($pend['motivo'] ?? ''));
                            $pendAnexo  = trim((string)($pend['anexo_path'] ?? ''));
                            $jEmpName   = (string)($pend['employee_name'] ?? 'Funcionário');
                            $jEmpPic    = (string)($pend['employee_profile_picture'] ?? '');
                            $jInitials  = strtoupper(mb_substr($jEmpName, 0, 2));
                        ?>
                        <tr class="fr-row" data-sol-tipo="justificativa" data-sol-nome="<?php echo htmlspecialchars(mb_strtolower($jEmpName)); ?>">
                            <td class="fr-td-emp">
                                <div class="fr-emp-cell">
                                    <div class="fr-av" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
                                        <?php if ($jEmpPic !== ''): ?>
                                        <img class="fr-av-img" src="../<?php echo htmlspecialchars($jEmpPic); ?>" alt=""
                                            onerror="this.parentElement.textContent='<?php echo $jInitials; ?>'; this.remove();">
                                        <?php else: echo $jInitials; endif; ?>
                                    </div>
                                    <span class="fr-emp-name"><?php echo htmlspecialchars($jEmpName); ?></span>
                                </div>
                            </td>
                            <td><span class="sol-tipo-badge sol-tipo-justif"><i class="fas fa-file-medical"></i> <?php echo htmlspecialchars($pendTipoLabel); ?></span></td>
                            <td class="fr-td-role"><?php echo htmlspecialchars($pendDataFmt); ?></td>
                            <td class="fr-td-role" style="max-width:220px;">
                                <?php if ($pendMotivo !== ''): ?>
                                <span style="font-size:.8rem;color:var(--text-secondary);"><?php echo htmlspecialchars(mb_substr($pendMotivo,0,60)).(mb_strlen($pendMotivo)>60?'…':''); ?></span>
                                <?php else: ?><span style="color:#475569;">—</span><?php endif; ?>
                                <?php if ($pendAnexo !== ''): ?>
                                <a href="../<?php echo htmlspecialchars($pendAnexo); ?>" target="_blank" rel="noopener noreferrer"
                                    class="fr-btn" style="margin-left:4px;font-size:.65rem;padding:1px 6px;" title="Ver anexo"><i class="fas fa-paperclip"></i></a>
                                <?php endif; ?>
                            </td>
                            <td class="fr-td-status"><span class="status-badge status-warning">Pendente</span></td>
                            <td class="fr-td-acts">
                                <div class="fr-acts">
                                    <form method="POST" action="../api/employees/review_justificativa.php" style="display:contents;">
                                        <input type="hidden" name="justificativa_id" value="<?php echo (int)$pend['id']; ?>">
                                        <input type="hidden" name="decision" value="aprovar">
                                        <input type="hidden" name="return_url" value="../../admin/dashboard.php?section=solicitacoes&solicitacao_card=justificativas">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                        <button type="submit" class="fr-btn fr-btn-activate" title="Aprovar"><i class="fas fa-check"></i></button>
                                    </form>
                                    <form method="POST" action="../api/employees/review_justificativa.php" style="display:contents;">
                                        <input type="hidden" name="justificativa_id" value="<?php echo (int)$pend['id']; ?>">
                                        <input type="hidden" name="decision" value="rejeitar">
                                        <input type="hidden" name="return_url" value="../../admin/dashboard.php?section=solicitacoes&solicitacao_card=justificativas">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                        <button type="submit" class="fr-btn fr-btn-deact" title="Rejeitar"><i class="fas fa-times"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <?php foreach ($presencasPendentes as $pSolic):
                            $pDataFmt  = !empty($pSolic['data_ref']) ? date('d/m/Y', strtotime((string)$pSolic['data_ref'])) : 'N/D';
                            $pEntrada  = trim((string)($pSolic['hora_entrada'] ?? '')) !== '' ? substr((string)$pSolic['hora_entrada'], 0, 5) : '--:--';
                            $pSaida    = trim((string)($pSolic['hora_saida']   ?? '')) !== '' ? substr((string)$pSolic['hora_saida'],   0, 5) : '--:--';
                            $pEmpName  = (string)($pSolic['employee_name'] ?? 'Funcionário');
                            $pEmpPic   = (string)($pSolic['employee_profile_picture'] ?? '');
                            $pInitials = strtoupper(mb_substr($pEmpName, 0, 2));
                        ?>
                        <tr class="fr-row" data-sol-tipo="presenca" data-sol-nome="<?php echo htmlspecialchars(mb_strtolower($pEmpName)); ?>">
                            <td class="fr-td-emp">
                                <div class="fr-emp-cell">
                                    <div class="fr-av" style="background:linear-gradient(135deg,#3b82f6,#2563eb);">
                                        <?php if ($pEmpPic !== ''): ?>
                                        <img class="fr-av-img" src="../<?php echo htmlspecialchars($pEmpPic); ?>" alt=""
                                            onerror="this.parentElement.textContent='<?php echo $pInitials; ?>'; this.remove();">
                                        <?php else: echo $pInitials; endif; ?>
                                    </div>
                                    <span class="fr-emp-name"><?php echo htmlspecialchars($pEmpName); ?></span>
                                </div>
                            </td>
                            <td><span class="sol-tipo-badge sol-tipo-pres"><i class="fas fa-user-check"></i> Presença</span></td>
                            <td class="fr-td-role"><?php echo htmlspecialchars($pDataFmt); ?></td>
                            <td class="fr-td-role"><span style="font-size:.8rem;color:var(--text-secondary);">Entrada <?php echo htmlspecialchars($pEntrada); ?> · Saída <?php echo htmlspecialchars($pSaida); ?></span></td>
                            <td class="fr-td-status"><span class="status-badge status-warning">Pendente</span></td>
                            <td class="fr-td-acts">
                                <div class="fr-acts">
                                    <form method="POST" action="dashboard.php?section=solicitacoes" style="display:contents;">
                                        <input type="hidden" name="action" value="approve_presence_request">
                                        <input type="hidden" name="solicitacao_card" value="presenca">
                                        <input type="hidden" name="employee_id" value="<?php echo (int)$pSolic['employee_id']; ?>">
                                        <input type="hidden" name="target_date" value="<?php echo htmlspecialchars((string)$pSolic['data_ref']); ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                        <button type="submit" class="fr-btn fr-btn-activate" title="Aprovar"><i class="fas fa-check"></i></button>
                                    </form>
                                    <form method="POST" action="dashboard.php?section=solicitacoes" style="display:contents;">
                                        <input type="hidden" name="action" value="reject_presence_request">
                                        <input type="hidden" name="solicitacao_card" value="presenca">
                                        <input type="hidden" name="employee_id" value="<?php echo (int)$pSolic['employee_id']; ?>">
                                        <input type="hidden" name="target_date" value="<?php echo htmlspecialchars((string)$pSolic['data_ref']); ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                        <button type="submit" class="fr-btn fr-btn-deact" title="Rejeitar"><i class="fas fa-times"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <?php foreach ($gorjetasPendentes as $gSolic):
                            $gDataFmt  = !empty($gSolic['data_ref']) ? date('d/m/Y', strtotime((string)$gSolic['data_ref'])) : 'N/D';
                            $gEmpName  = (string)($gSolic['employee_name'] ?? 'Funcionário');
                            $gEmpPic   = (string)($gSolic['employee_profile_picture'] ?? '');
                            $gInitials = strtoupper(mb_substr($gEmpName, 0, 2));
                        ?>
                        <tr class="fr-row" data-sol-tipo="gorjeta" data-sol-nome="<?php echo htmlspecialchars(mb_strtolower($gEmpName)); ?>">
                            <td class="fr-td-emp">
                                <div class="fr-emp-cell">
                                    <div class="fr-av" style="background:linear-gradient(135deg,#10b981,#059669);">
                                        <?php if ($gEmpPic !== ''): ?>
                                        <img class="fr-av-img" src="../<?php echo htmlspecialchars($gEmpPic); ?>" alt=""
                                            onerror="this.parentElement.textContent='<?php echo $gInitials; ?>'; this.remove();">
                                        <?php else: echo $gInitials; endif; ?>
                                    </div>
                                    <span class="fr-emp-name"><?php echo htmlspecialchars($gEmpName); ?></span>
                                </div>
                            </td>
                            <td><span class="sol-tipo-badge sol-tipo-gorj"><i class="fas fa-coins"></i> Gorjeta</span></td>
                            <td class="fr-td-role"><?php echo htmlspecialchars($gDataFmt); ?></td>
                            <td class="fr-td-role"><span style="font-size:.8rem;color:var(--text-secondary);">€<?php echo number_format((float)($gSolic['valor']??0),2,',','.'); ?> · <?php echo htmlspecialchars((string)($gSolic['turno']??'-')); ?></span></td>
                            <td class="fr-td-status"><span class="status-badge status-warning">Pendente</span></td>
                            <td class="fr-td-acts">
                                <div class="fr-acts">
                                    <form method="POST" action="dashboard.php?section=solicitacoes" style="display:contents;">
                                        <input type="hidden" name="action" value="approve_gorjeta_request">
                                        <input type="hidden" name="solicitacao_card" value="gorjetas">
                                        <input type="hidden" name="gorjeta_id" value="<?php echo (int)$gSolic['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                        <button type="submit" class="fr-btn fr-btn-activate" title="Aprovar"><i class="fas fa-check"></i></button>
                                    </form>
                                    <form method="POST" action="dashboard.php?section=solicitacoes" style="display:contents;">
                                        <input type="hidden" name="action" value="reject_gorjeta_request">
                                        <input type="hidden" name="solicitacao_card" value="gorjetas">
                                        <input type="hidden" name="gorjeta_id" value="<?php echo (int)$gSolic['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                        <button type="submit" class="fr-btn fr-btn-deact" title="Rejeitar"><i class="fas fa-times"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <?php foreach ($feriasPendentes as $fSolic):
                            $fDataInicio = !empty($fSolic['data_inicio']) ? date('d/m/Y', strtotime((string)$fSolic['data_inicio'])) : 'N/D';
                            $fDataFim    = !empty($fSolic['data_fim'])    ? date('d/m/Y', strtotime((string)$fSolic['data_fim']))    : 'N/D';
                            $fMotivo     = trim((string)($fSolic['motivo'] ?? ''));
                            $fEmpName    = (string)($fSolic['employee_name'] ?? 'Funcionário');
                            $fEmpPic     = (string)($fSolic['employee_profile_picture'] ?? '');
                            $fInitials   = strtoupper(mb_substr($fEmpName, 0, 2));
                            $fDias       = (!empty($fSolic['data_inicio']) && !empty($fSolic['data_fim']))
                                ? max(0, (int)(strtotime($fSolic['data_fim']) - strtotime($fSolic['data_inicio'])) / 86400 + 1) : 0;
                        ?>
                        <tr class="fr-row" data-sol-tipo="ferias" data-sol-nome="<?php echo htmlspecialchars(mb_strtolower($fEmpName)); ?>">
                            <td class="fr-td-emp">
                                <div class="fr-emp-cell">
                                    <div class="fr-av" style="background:linear-gradient(135deg,#0ea5e9,#0284c7);">
                                        <?php if ($fEmpPic !== ''): ?>
                                        <img class="fr-av-img" src="../<?php echo htmlspecialchars($fEmpPic); ?>" alt=""
                                            onerror="this.parentElement.textContent='<?php echo $fInitials; ?>'; this.remove();">
                                        <?php else: echo $fInitials; endif; ?>
                                    </div>
                                    <div class="fr-emp-info">
                                        <span class="fr-emp-name"><?php echo htmlspecialchars($fEmpName); ?></span>
                                        <?php if ($fDias > 0): ?><span class="fr-emp-email"><?php echo $fDias; ?> dia<?php echo $fDias !== 1 ? 's' : ''; ?></span><?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><span class="sol-tipo-badge sol-tipo-fer"><i class="fas fa-umbrella-beach"></i> Férias</span></td>
                            <td class="fr-td-role"><?php echo htmlspecialchars($fDataInicio); ?> – <?php echo htmlspecialchars($fDataFim); ?></td>
                            <td class="fr-td-role"><span style="font-size:.8rem;color:var(--text-secondary);"><?php echo $fMotivo !== '' ? htmlspecialchars(mb_substr($fMotivo,0,50)).(mb_strlen($fMotivo)>50?'…':'') : '—'; ?></span></td>
                            <td class="fr-td-status"><span class="status-badge status-warning">Pendente</span></td>
                            <td class="fr-td-acts">
                                <div class="fr-acts">
                                    <form method="POST" action="dashboard.php?section=solicitacoes" style="display:contents;">
                                        <input type="hidden" name="action" value="approve_ferias_request">
                                        <input type="hidden" name="solicitacao_card" value="ferias">
                                        <input type="hidden" name="ferias_id" value="<?php echo (int)$fSolic['id']; ?>">
                                        <input type="hidden" name="from_section" value="solicitacoes">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                        <button type="submit" class="fr-btn fr-btn-activate" title="Aprovar férias"><i class="fas fa-check"></i></button>
                                    </form>
                                    <button type="button" class="fr-btn fr-btn-deact" title="Rejeitar férias"
                                        onclick="openSolFeriasRejectPrompt(<?php echo (int)$fSolic['id']; ?>, '<?php echo htmlspecialchars(addslashes($fEmpName)); ?>')">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <?php foreach ($trocasTurnoPendentes as $swapSolic):
                            $swapDate      = !empty($swapSolic['requested_date']) ? date('d/m/Y', strtotime((string)$swapSolic['requested_date'])) : '-';
                            $swapReason    = trim((string)($swapSolic['reason'] ?? ''));
                            $swapRequester = (string)($swapSolic['requester_name'] ?? 'Funcionário');
                            $swapTarget    = (string)($swapSolic['target_name'] ?? 'Colega');
                            $swapReqPic    = (string)($swapSolic['requester_profile_picture'] ?? '');
                            $swapInitials  = strtoupper(mb_substr($swapRequester, 0, 2));
                            $swapReqTurno  = trim((string)($swapSolic['requester_turno_tipo'] ?? '-')).' '.substr((string)($swapSolic['requester_horario_inicio']??''),0,5).'-'.substr((string)($swapSolic['requester_horario_fim']??''),0,5);
                            $swapTgtTurno  = trim((string)($swapSolic['target_turno_tipo'] ?? '-')).' '.substr((string)($swapSolic['target_horario_inicio']??''),0,5).'-'.substr((string)($swapSolic['target_horario_fim']??''),0,5);
                        ?>
                        <tr class="fr-row" data-sol-tipo="troca" data-sol-nome="<?php echo htmlspecialchars(mb_strtolower($swapRequester)); ?>">
                            <td class="fr-td-emp">
                                <div class="fr-emp-cell">
                                    <div class="fr-av" style="background:linear-gradient(135deg,#65a30d,#4d7c0f);">
                                        <?php if ($swapReqPic !== ''): ?>
                                        <img class="fr-av-img" src="../<?php echo htmlspecialchars($swapReqPic); ?>" alt=""
                                            onerror="this.parentElement.textContent='<?php echo $swapInitials; ?>'; this.remove();">
                                        <?php else: echo $swapInitials; endif; ?>
                                    </div>
                                    <div class="fr-emp-info">
                                        <span class="fr-emp-name"><?php echo htmlspecialchars($swapRequester); ?></span>
                                        <span class="fr-emp-email">↔ <?php echo htmlspecialchars($swapTarget); ?></span>
                                    </div>
                                </div>
                            </td>
                            <td><span class="sol-tipo-badge sol-tipo-troca"><i class="fas fa-exchange-alt"></i> Troca</span></td>
                            <td class="fr-td-role"><?php echo htmlspecialchars($swapDate); ?></td>
                            <td class="fr-td-role" style="max-width:200px;"><span style="font-size:.78rem;color:var(--text-secondary);"><?php echo htmlspecialchars(trim($swapReqTurno)); ?> → <?php echo htmlspecialchars(trim($swapTgtTurno)); ?><?php if ($swapReason !== '') { echo ' · '.htmlspecialchars(mb_substr($swapReason,0,40)); } ?></span></td>
                            <td class="fr-td-status"><span class="status-badge status-warning">Pendente admin</span></td>
                            <td class="fr-td-acts">
                                <div class="fr-acts">
                                    <form method="POST" action="dashboard.php?section=solicitacoes" style="display:contents;">
                                        <input type="hidden" name="action" value="approve_turno_swap_request">
                                        <input type="hidden" name="solicitacao_card" value="trocas_turno">
                                        <input type="hidden" name="turno_swap_request_id" value="<?php echo (int)$swapSolic['id']; ?>">
                                        <input type="hidden" name="review_note" value="Aprovada no painel de solicitações.">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                        <button type="submit" class="fr-btn fr-btn-activate" title="Aprovar troca"><i class="fas fa-check"></i></button>
                                    </form>
                                    <form method="POST" action="dashboard.php?section=solicitacoes" style="display:contents;">
                                        <input type="hidden" name="action" value="reject_turno_swap_request">
                                        <input type="hidden" name="solicitacao_card" value="trocas_turno">
                                        <input type="hidden" name="turno_swap_request_id" value="<?php echo (int)$swapSolic['id']; ?>">
                                        <input type="hidden" name="review_note" value="Rejeitada no painel de solicitações.">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                        <button type="submit" class="fr-btn fr-btn-deact" title="Rejeitar troca"><i class="fas fa-times"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                    </tbody>
                </table>

                <table class="table fr-table" id="solHistoricoTable" style="display:none;">
                    <thead>
                        <tr class="fr-thead-row">
                            <th class="fr-th-emp">Funcionário</th>
                            <th>Tipo</th>
                            <th>Data</th>
                            <th>Detalhe</th>
                            <th class="fr-th-status">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($solicitacoesHistoricoDecididas)): ?>
                        <tr>
                            <td colspan="5" style="text-align:center;color:var(--text-secondary);padding:3rem 1rem;">
                                <i class="fas fa-history" style="font-size:2.5rem;opacity:.2;display:block;margin-bottom:.75rem;"></i>
                                Sem registos no histórico.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($solicitacoesHistoricoDecididas as $emp):
                            $hStatusLabel = (string)($emp['status_label'] ?? '');
                            $hStatusLow   = mb_strtolower($hStatusLabel);
                            $hStatusClass = 'status-falta';
                            if (in_array($hStatusLow, ['agendado','agendada'], true))              { $hStatusClass = 'status-warning'; }
                            elseif (in_array($hStatusLow, ['aprovada','em curso','pago','paga'], true)) { $hStatusClass = 'status-presente'; }
                            elseif (in_array($hStatusLow, ['terminada','concluida','concluída'], true)) { $hStatusClass = 'status-nao-marcado'; }
                            $hFoto = !empty($emp['employee_profile_picture']) ? $emp['employee_profile_picture'] : ($emp['profile_picture'] ?? '');
                            $hNome = !empty($emp['employee_name']) ? $emp['employee_name'] : ($emp['name'] ?? ($emp['funcionario'] ?? 'Funcionário'));
                            $hInitials = strtoupper(mb_substr($hNome, 0, 2));
                            $hTipoRaw  = mb_strtolower(trim((string)($emp['tipo'] ?? '-')));
                            $hTipoBadgeClass = 'sol-tipo-justif'; $hTipoIcon = 'fa-file-alt';
                            if (str_contains($hTipoRaw,'gorjet'))                               { $hTipoBadgeClass='sol-tipo-gorj';  $hTipoIcon='fa-coins'; }
                            elseif (str_contains($hTipoRaw,'feria')||str_contains($hTipoRaw,'féria')) { $hTipoBadgeClass='sol-tipo-fer'; $hTipoIcon='fa-umbrella-beach'; }
                            elseif (str_contains($hTipoRaw,'presen'))                           { $hTipoBadgeClass='sol-tipo-pres';  $hTipoIcon='fa-user-check'; }
                            elseif (str_contains($hTipoRaw,'troca')||str_contains($hTipoRaw,'turno')) { $hTipoBadgeClass='sol-tipo-troca'; $hTipoIcon='fa-exchange-alt'; }
                            $hDataFmt = (string)($emp['data_ref'] ?? '');
                            $hDataFmt = $hDataFmt !== '' ? date('d/m/Y', strtotime($hDataFmt)) : '-';
                        ?>
                        <tr class="fr-row" data-sol-hist-tipo="<?php echo htmlspecialchars($hTipoRaw); ?>" data-sol-nome="<?php echo htmlspecialchars(mb_strtolower($hNome)); ?>">
                            <td class="fr-td-emp">
                                <div class="fr-emp-cell">
                                    <div class="fr-av" style="background:linear-gradient(135deg,#667eea,#764ba2);">
                                        <?php if ($hFoto !== ''): ?>
                                        <img class="fr-av-img" src="../<?php echo htmlspecialchars($hFoto); ?>" alt=""
                                            onerror="this.parentElement.textContent='<?php echo $hInitials; ?>'; this.remove();">
                                        <?php else: echo $hInitials; endif; ?>
                                    </div>
                                    <span class="fr-emp-name"><?php echo htmlspecialchars($hNome); ?></span>
                                </div>
                            </td>
                            <td><span class="sol-tipo-badge <?php echo $hTipoBadgeClass; ?>"><i class="fas <?php echo $hTipoIcon; ?>"></i> <?php echo htmlspecialchars((string)($emp['tipo'] ?? '-')); ?></span></td>
                            <td class="fr-td-role"><?php echo htmlspecialchars($hDataFmt); ?></td>
                            <td class="fr-td-role"><span style="font-size:.8rem;color:var(--text-secondary);"><?php echo htmlspecialchars(mb_substr((string)($emp['detalhe'] ?? '-'), 0, 60)); ?></span></td>
                            <td class="fr-td-status"><span class="status-badge <?php echo $hStatusClass; ?>"><?php echo htmlspecialchars($hStatusLabel !== '' ? $hStatusLabel : '-'); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div id="solHistoricoToolbar" style="display:none;margin-top:.75rem;gap:.5rem;flex-wrap:wrap;align-items:center;">
                    <select id="solHistoricoFiltroTipo" class="fr-select" style="min-width:190px;">
                        <option value="">Todos os tipos</option>
                        <option value="férias">Férias</option>
                        <option value="gorjeta">Gorjeta</option>
                        <option value="presença">Presença</option>
                        <option value="troca turno">Troca de Turno</option>
                        <option value="justificativa">Justificativa</option>
                    </select>
                    <button type="button" id="btnExportarHistorico" class="fr-filter-toggle">
                        <i class="fas fa-file-export"></i> Exportar
                    </button>
                    <button type="button" id="btnLimparHistorico" class="fr-clear-btn">
                        <i class="fas fa-trash-alt"></i> Limpar histórico
                    </button>
                </div>
            </div>

            <form method="POST" action="dashboard.php?section=solicitacoes" id="solFeriasRejectForm" style="display:none;">
                <input type="hidden" name="action" value="reject_ferias_request">
                <input type="hidden" name="solicitacao_card" value="ferias">
                <input type="hidden" name="ferias_id" id="solFeriasRejectId" value="">
                <input type="hidden" name="motivo_rejeicao" id="solFeriasRejectMotivo" value="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            </form>

            <script>
            function openSolFeriasRejectPrompt(feriasId, empName) {
                Swal.fire({
                    title: 'Rejeitar férias',
                    html: 'Funcionário: <strong>' + escSol(empName) + '</strong>',
                    input: 'textarea',
                    inputPlaceholder: 'Opcional — indique o motivo da rejeição…',
                    showCancelButton: true,
                    confirmButtonText: 'Rejeitar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#ef4444',
                    inputAttributes: { maxlength: 500 },
                    background: 'var(--card-bg, #1e293b)',
                    color: 'var(--text-primary, #f1f5f9)'
                }).then(function(result) {
                    if (!result.isConfirmed) return;
                    document.getElementById('solFeriasRejectId').value = feriasId;
                    document.getElementById('solFeriasRejectMotivo').value = result.value || '';
                    document.getElementById('solFeriasRejectForm').submit();
                });
            }
            function escSol(v) {
                return String(v || '').replace(/[&<>"']/g, function(c) {
                    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];
                });
            }

            (function initSolicitacoes() {
                var searchInput = document.getElementById('solSearchInput');
                var resultCount = document.getElementById('solResultCount');
                var mainTable   = document.getElementById('solMainTable');
                var histTable   = document.getElementById('solHistoricoTable');
                var histToolbar = document.getElementById('solHistoricoToolbar');
                var histFiltro  = document.getElementById('solHistoricoFiltroTipo');
                var chips       = document.querySelectorAll('[data-sol-chip]');
                var currentChip = '';

                function getMainRows() { return mainTable ? Array.from(mainTable.querySelectorAll('tr.fr-row[data-sol-tipo]')) : []; }
                function getHistRows()  { return histTable  ? Array.from(histTable.querySelectorAll('tr.fr-row[data-sol-hist-tipo]')) : []; }

                function updateCount(vis, tot) {
                    if (resultCount) resultCount.textContent = vis < tot ? vis + ' de ' + tot : tot + ' resultado' + (tot !== 1 ? 's' : '');
                }

                function applyMainFilters() {
                    var q = searchInput ? searchInput.value.trim().toLowerCase() : '';
                    var rows = getMainRows(); var vis = 0;
                    rows.forEach(function(row) {
                        var show = (currentChip === '' || row.getAttribute('data-sol-tipo') === currentChip)
                            && (q === '' || (row.getAttribute('data-sol-nome') || '').includes(q));
                        row.style.display = show ? '' : 'none';
                        if (show) vis++;
                    });
                    var emptyRow = document.getElementById('solEmptyState');
                    if (emptyRow) emptyRow.style.display = (vis === 0 && rows.length > 0) ? '' : 'none';
                    updateCount(vis, rows.length);
                }

                function applyHistFilters() {
                    var q = searchInput ? searchInput.value.trim().toLowerCase() : '';
                    var tf = histFiltro ? histFiltro.value.toLowerCase() : '';
                    var rows = getHistRows(); var vis = 0;
                    rows.forEach(function(row) {
                        var show = (tf === '' || (row.getAttribute('data-sol-hist-tipo') || '') === tf)
                            && (q === '' || (row.getAttribute('data-sol-nome') || '').includes(q));
                        row.style.display = show ? '' : 'none';
                        if (show) vis++;
                    });
                    updateCount(vis, rows.length);
                }

                window.applySolChip = function(chipBtn) {
                    chips.forEach(function(c) { c.classList.remove('active'); });
                    chipBtn.classList.add('active');
                    currentChip = chipBtn.getAttribute('data-sol-chip') || '';
                    var isHist = currentChip === 'historico';
                    if (mainTable) mainTable.style.display = isHist ? 'none' : '';
                    if (histTable) histTable.style.display = isHist ? '' : 'none';
                    if (histToolbar) histToolbar.style.display = isHist ? 'flex' : 'none';
                    isHist ? applyHistFilters() : applyMainFilters();
                };

                if (searchInput) searchInput.addEventListener('input', function() { currentChip === 'historico' ? applyHistFilters() : applyMainFilters(); });
                if (histFiltro)  histFiltro.addEventListener('change', applyHistFilters);

                (function restoreChipFromQuery() {
                    var params = new URLSearchParams(window.location.search);
                    if (params.get('section') !== 'solicitacoes') return;
                    var cardMap = { justificativas:'justificativa', presenca:'presenca', gorjetas:'gorjeta', ferias:'ferias', trocas_turno:'troca', historico:'historico' };
                    var chipVal = cardMap[params.get('solicitacao_card') || ''] || '';
                    if (chipVal) { var t = document.querySelector('[data-sol-chip="' + chipVal + '"]'); if (t) window.applySolChip(t); }
                })();

                (function persistScroll() {
                    var KEY = 'dashboard_solicitacoes_scroll_y';
                    var params = new URLSearchParams(window.location.search);
                    if (params.get('section') === 'solicitacoes') {
                        var y = parseInt(sessionStorage.getItem(KEY) || '', 10);
                        if (!isNaN(y) && y >= 0) {
                            requestAnimationFrame(function() { requestAnimationFrame(function() { window.scrollTo(0, y); sessionStorage.removeItem(KEY); }); });
                        }
                    }
                    document.querySelectorAll('form[action*="section=solicitacoes"], form[action*="review_justificativa.php"]').forEach(function(form) {
                        form.addEventListener('submit', function() { sessionStorage.setItem(KEY, String(window.scrollY || 0)); });
                    });
                })();

                applyMainFilters();
            })();
            </script>
        </section>


















        <!-- TURNOS SECTION: gerência de turnos -->
        <?php require $ADMIN_DIR . '/sections/turnos.php'; ?>
















        <section id="relatorios-section" class="content-section">



            <!-- HEADER -->
            <div class="frhd">
                <div class="frhd-left">
                    <div class="frhd-icon" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed);box-shadow:0 4px 14px rgba(139,92,246,.35);"><i class="fas fa-chart-bar"></i></div>
                    <div>
                        <h2 class="frhd-title">Relatórios</h2>
                        <p class="frhd-sub">Período <?php echo htmlspecialchars(sprintf('%02d/%04d', (int)$reportMonth, (int)$reportYear)); ?> &middot; <?php echo count($employees); ?> funcionário<?php echo count($employees) !== 1 ? 's' : ''; ?></p>
                    </div>
                </div>
                <a href="optimize_indexes.php" target="_blank" class="frhd-add-btn" style="background:rgba(59,130,246,0.12);color:#3b82f6;border:1px solid rgba(59,130,246,0.25);font-size:.8rem;text-decoration:none;" title="Otimiza índices do banco para relatórios rápidos (executar 1x após 1-2 meses)">
                    <i class="fas fa-bolt"></i> Otimizar Performance
                </a>
            </div>

           

            <!-- NAVEGAÇÃO DOS RELATÓRIOS -->
            <style>
                .rp-nav-strip {
                    display:grid; grid-template-columns:repeat(5,1fr); gap:.75rem; margin-bottom:1.5rem;
                }
                .rp-nav-card {
                    display:flex; align-items:center; gap:.65rem;
                    background:var(--card-bg,#1e293b); border:1px solid rgba(255,255,255,.07);
                    border-radius:14px; padding:.75rem 1rem; cursor:pointer;
                    transition:all .2s; text-align:left; width:100%;
                }
                .rp-nav-card:hover { border-color:rgba(255,255,255,.15); transform:translateY(-1px); }
                .rp-nav-icon {
                    width:38px; height:38px; border-radius:10px; flex-shrink:0;
                    display:grid; place-items:center; font-size:.95rem;
                    transition:background .2s;
                }
                .rp-nav-body { display:flex; flex-direction:column; min-width:0; }
                .rp-nav-val  { font-size:1.3rem; font-weight:800; color:#e2e8f0; line-height:1.1; }
                .rp-nav-lbl  { font-size:.7rem; color:#64748b; font-weight:600; text-transform:uppercase; letter-spacing:.05em; margin-top:2px; }
                /* active per section */
                .rp-nav-resumido.active  { background:rgba(245,158,11,.1); border-color:rgba(245,158,11,.35); }
                .rp-nav-resumido.active .rp-nav-val { color:#fbbf24; }
                .rp-nav-presenca.active  { background:rgba(59,130,246,.1); border-color:rgba(59,130,246,.35); }
                .rp-nav-presenca.active .rp-nav-val { color:#60a5fa; }
                .rp-nav-turnos.active    { background:rgba(167,139,250,.1); border-color:rgba(167,139,250,.35); }
                .rp-nav-turnos.active .rp-nav-val { color:#a78bfa; }
                .rp-nav-gorjetas.active  { background:rgba(34,211,238,.1); border-color:rgba(34,211,238,.35); }
                .rp-nav-gorjetas.active .rp-nav-val { color:#22d3ee; }
                .rp-nav-folha.active     { background:rgba(251,113,133,.1); border-color:rgba(251,113,133,.35); }
                .rp-nav-folha.active .rp-nav-val { color:#fb7185; }
                @media(max-width:960px){ .rp-nav-strip{ grid-template-columns:repeat(3,1fr); } }
                @media(max-width:560px){ .rp-nav-strip{ grid-template-columns:repeat(2,1fr); } }

                .rp-alert-strip { display:flex; flex-wrap:wrap; gap:.6rem; margin-bottom:1.25rem; }
                .rp-alert {
                    display:inline-flex; align-items:center; gap:.45rem;
                    padding:.5rem .9rem; border-radius:10px; font-size:.8rem; font-weight:600;
                    border:1px solid; cursor:pointer; transition:all .18s; white-space:nowrap;
                }
                .rp-alert:hover { filter:brightness(1.18); transform:translateY(-1px); }
                .rp-alert strong { font-size:.95rem; }
                .rp-alert-ok { background:rgba(16,185,129,.1); color:#34d399; border-color:rgba(16,185,129,.3); cursor:default; }
                .rp-alert-ok:hover { filter:none; transform:none; }
            </style>

            <?php
                // Alertas operacionais — pendências que merecem atenção hoje
                $alFuncFaltas = array_filter($employees, function($e) {
                    $f = (int)($e['rel_faltas'] ?? ($e['faltas'] ?? 0));
                    return $f >= 3;
                });
                $alFuncFerias = array_filter($employees, fn($e) => strtolower(trim((string)($e['status'] ?? ''))) === 'ferias');
                $alGorjPendentes = array_filter($gorjetas, fn($g) => strtolower(trim((string)($g['status'] ?? ''))) === 'pendente');
                $alFolhaPendente = array_filter($folhaPagamento, fn($f) => strtolower(trim((string)($f['status'] ?? ''))) !== 'pago');

                $rpAlerts = [];
                if (count($alFuncFaltas) > 0) {
                    $rpAlerts[] = ['icon'=>'fa-user-clock','color'=>'#f87171','bg'=>'rgba(239,68,68,.12)','val'=>count($alFuncFaltas),'lbl'=>'funcionário'.(count($alFuncFaltas)!==1?'s':'').' com faltas elevadas','target'=>'funcionarios-resumido'];
                }
                if (count($alFolhaPendente) > 0) {
                    $rpAlerts[] = ['icon'=>'fa-file-invoice-dollar','color'=>'#fb7185','bg'=>'rgba(251,113,133,.12)','val'=>count($alFolhaPendente),'lbl'=>'pagamento'.(count($alFolhaPendente)!==1?'s':'').' de folha pendente'.(count($alFolhaPendente)!==1?'s':''),'target'=>'folha'];
                }
                if (count($alGorjPendentes) > 0) {
                    $rpAlerts[] = ['icon'=>'fa-hand-holding-usd','color'=>'#fbbf24','bg'=>'rgba(245,158,11,.12)','val'=>count($alGorjPendentes),'lbl'=>'gorjeta'.(count($alGorjPendentes)!==1?'s':'').' pendente'.(count($alGorjPendentes)!==1?'s':''),'target'=>'gorjetas'];
                }
                if (count($alFuncFerias) > 0) {
                    $rpAlerts[] = ['icon'=>'fa-umbrella-beach','color'=>'#60a5fa','bg'=>'rgba(59,130,246,.12)','val'=>count($alFuncFerias),'lbl'=>'funcionário'.(count($alFuncFerias)!==1?'s':'').' de férias','target'=>'funcionarios-resumido'];
                }
            ?>
            <div class="rp-alert-strip">
                <?php if (empty($rpAlerts)): ?>
                    <div class="rp-alert rp-alert-ok"><i class="fas fa-check-circle"></i> Tudo em dia — sem pendências para este período.</div>
                <?php else: foreach ($rpAlerts as $al): ?>
                    <button type="button" class="rp-alert" style="background:<?php echo $al['bg']; ?>;color:<?php echo $al['color']; ?>;border-color:<?php echo $al['color']; ?>55;"
                        onclick="switchRelatorio('<?php echo $al['target']; ?>', document.querySelector('.rp-nav-card[data-relatorio=&quot;<?php echo $al['target']; ?>&quot;]'))">
                        <i class="fas <?php echo $al['icon']; ?>"></i> <strong><?php echo $al['val']; ?></strong> <?php echo htmlspecialchars($al['lbl']); ?>
                    </button>
                <?php endforeach; endif; ?>
            </div>

            <div class="rp-nav-strip">
                <button type="button" class="rp-nav-card rp-nav-resumido relatorio-tab active" data-relatorio="funcionarios-resumido" onclick="switchRelatorio('funcionarios-resumido',this)">
                    <div class="rp-nav-icon" style="background:rgba(245,158,11,.15);color:#f59e0b;"><i class="fas fa-chart-bar"></i></div>
                    <div class="rp-nav-body">
                        <span class="rp-nav-val"><?php echo count($employees); ?></span>
                        <span class="rp-nav-lbl">Resumido</span>
                    </div>
                </button>
                <button type="button" class="rp-nav-card rp-nav-presenca relatorio-tab" data-relatorio="presenca" onclick="switchRelatorio('presenca',this)">
                    <div class="rp-nav-icon" style="background:rgba(59,130,246,.15);color:#60a5fa;"><i class="fas fa-calendar-check"></i></div>
                    <div class="rp-nav-body">
                        <span class="rp-nav-val"><?php echo count($presencas); ?></span>
                        <span class="rp-nav-lbl">Presenças</span>
                    </div>
                </button>
                <button type="button" class="rp-nav-card rp-nav-turnos relatorio-tab" data-relatorio="turnos" onclick="switchRelatorio('turnos',this)">
                    <div class="rp-nav-icon" style="background:rgba(167,139,250,.15);color:#a78bfa;"><i class="fas fa-clock"></i></div>
                    <div class="rp-nav-body">
                        <span class="rp-nav-val"><?php echo count($turnos); ?></span>
                        <span class="rp-nav-lbl">Turnos</span>
                    </div>
                </button>
                <button type="button" class="rp-nav-card rp-nav-gorjetas relatorio-tab" data-relatorio="gorjetas" onclick="switchRelatorio('gorjetas',this)">
                    <div class="rp-nav-icon" style="background:rgba(34,211,238,.15);color:#22d3ee;"><i class="fas fa-hand-holding-usd"></i></div>
                    <div class="rp-nav-body">
                        <span class="rp-nav-val"><?php echo count($gorjetas); ?></span>
                        <span class="rp-nav-lbl">Gorjetas</span>
                    </div>
                </button>
                <button type="button" class="rp-nav-card rp-nav-folha relatorio-tab" data-relatorio="folha" onclick="switchRelatorio('folha',this)">
                    <div class="rp-nav-icon" style="background:rgba(251,113,133,.15);color:#fb7185;"><i class="fas fa-file-invoice-dollar"></i></div>
                    <div class="rp-nav-body">
                        <span class="rp-nav-val"><?php echo count($folhaPagamento); ?></span>
                        <span class="rp-nav-lbl">Folha</span>
                    </div>
                </button>
            </div>


            <!-- TABELA DE RELATÓRIO RESUMIDO DE FUNCIONÁRIOS -->
            <div class="relatorio-content" id="content-funcionarios-resumido" style="display:block;">
            <div class="data-table fr-table-wrap" id="relatorio-funcionarios-resumido">
                <?php
                    $rTotalFuncs   = count($employees);
                    $rAtivosFuncs  = count(array_filter($employees, fn($e) => strtolower($e['status'] ?? 'ativo') === 'ativo'));
                    $rOutrosFuncs  = $rTotalFuncs - $rAtivosFuncs;
                    $rPctAtivos    = $rTotalFuncs > 0 ? round($rAtivosFuncs / $rTotalFuncs * 100) : 0;
                ?>
                <div class="fr-kpi-strip" style="grid-template-columns:repeat(3,1fr);margin-bottom:1.5rem;">
                    <div class="fr-kpi">
                        <div class="fr-kpi-icon" style="background:rgba(245,158,11,.14);color:#f59e0b;"><i class="fas fa-users"></i></div>
                        <div class="fr-kpi-body">
                            <span class="fr-kpi-val"><?php echo $rTotalFuncs; ?></span>
                            <span class="fr-kpi-lbl">Total Funcionários</span>
                            <span class="fr-kpi-pct">período <?php echo htmlspecialchars(sprintf('%02d/%04d',(int)$reportMonth,(int)$reportYear)); ?></span>
                        </div>
                    </div>
                    <div class="fr-kpi">
                        <div class="fr-kpi-icon" style="background:rgba(16,185,129,.14);color:#10b981;"><i class="fas fa-user-check"></i></div>
                        <div class="fr-kpi-body">
                            <span class="fr-kpi-val"><?php echo $rAtivosFuncs; ?></span>
                            <span class="fr-kpi-lbl">Ativos</span>
                            <span class="fr-kpi-pct"><?php echo $rPctAtivos; ?>% do total</span>
                        </div>
                    </div>
                    <div class="fr-kpi">
                        <div class="fr-kpi-icon" style="background:rgba(239,68,68,.12);color:#f87171;"><i class="fas fa-user-slash"></i></div>
                        <div class="fr-kpi-body">
                            <span class="fr-kpi-val"><?php echo $rOutrosFuncs; ?></span>
                            <span class="fr-kpi-lbl">Inativos / Férias</span>
                            <span class="fr-kpi-pct"><?php echo 100 - $rPctAtivos; ?>% do total</span>
                        </div>
                    </div>
                </div>
                <!-- GRÁFICO: Distribuição de Status e Cargos -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:2rem;margin-bottom:1.5rem;">
                    <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:1rem;">
                        <canvas id="chartFuncionariosStatus" style="max-height:250px;"></canvas>
                    </div>
                    <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:1rem;">
                        <canvas id="chartFuncionariosCargos" style="max-height:250px;"></canvas>
                    </div>
                </div>
                <div class="fr-toolbar">
                    <div class="fr-toolbar-top">
                        <div class="fr-search-wrap">
                            <i class="fas fa-search fr-search-icon"></i>
                            <input type="text" id="relatorioSearchName" name="filtro_nome" class="fr-search" placeholder="Pesquisar por nome…">
                        </div>
                        <div class="fr-toolbar-right">
                            <span id="relatorioResultCount" style="font-size:.78rem;color:#64748b;white-space:nowrap;"></span>
                            <button type="button" class="fr-filter-toggle" id="relatorioFilterToggle"
                                onclick="document.getElementById('relatorioAdvFilters').classList.toggle('fr-adv-open');this.classList.toggle('pa-filter-open')">
                                <i class="fas fa-sliders-h"></i> Filtros
                            </button>
                            <button id="btnExportarFuncionarios" type="button" class="fr-export-btn">
                                <i class="fas fa-download"></i> Exportar
                            </button>
                        </div>
                    </div>
                    <div class="fr-adv-filters" id="relatorioAdvFilters">
                        <select id="relatorioFilterStatus" name="filtro_status" class="fr-select">
                            <option value="">Todos os status</option>
                            <option value="ativo">Ativo</option>
                            <option value="ferias">Férias</option>
                            <option value="inativo">Inativo</option>
                        </select>
                        <select id="relatorioFilterCargo" class="fr-select">
                            <option value="">Todos os cargos</option>
                        </select>
                        <select id="relatorioFilterDepartamento" class="fr-select">
                            <option value="">Todos os departamentos</option>
                        </select>
                        <input type="date" id="relatorioPeriodoInicio" class="fr-select" title="Data inicial (admissão)">
                        <input type="date" id="relatorioPeriodoFim" class="fr-select" title="Data final (admissão)">
                        <input type="number" id="relatorioFilterTotalMin" class="fr-select" placeholder="Total mín (€)" step="0.01" min="0">
                        <input type="number" id="relatorioFilterTotalMax" class="fr-select" placeholder="Total máx (€)" step="0.01" min="0">
                        <button type="button" class="fr-clear-btn" onclick="['relatorioFilterStatus','relatorioFilterCargo','relatorioFilterDepartamento','relatorioPeriodoInicio','relatorioPeriodoFim','relatorioFilterTotalMin','relatorioFilterTotalMax'].forEach(function(id){document.getElementById(id).value='';});document.getElementById('relatorioSearchName').value='';document.getElementById('relatorioSearchName').dispatchEvent(new Event('input'));">Limpar</button>
                    </div>
                </div>
                <table class="table fr-table">
                    <thead>
                        <tr class="fr-thead-row">
                            <th>Funcionário</th>
                            <th>Cargo</th>
                            <th>Horas</th>
                            <th>Faltas</th>
                            <th>Base (€)</th>
                            <th>Gorjetas (€)</th>
                            <th>Total (€)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $emp):
                            $empNome = (string)($emp['name'] ?? '');
                            $empCargo = (string)($emp['role'] ?? ($emp['position'] ?? ''));
                            $empDepartamento = (string)($emp['department'] ?? '');
                            $empStatusRaw = mb_strtolower(trim((string)($emp['status'] ?? '')));
                            $empStatusFilter = match($empStatusRaw) {
                                'active', 'ativo' => 'ativo',
                                'inativo' => 'inativo',
                                'ferias', 'férias' => 'ferias',
                                default => $empStatusRaw
                            };
                            $empStatusLabel = match($empStatusFilter) {
                                'ativo' => 'Ativo',
                                'inativo' => 'Inativo',
                                'ferias' => 'Férias',
                                default => ($empStatusRaw !== '' ? ucfirst($empStatusRaw) : '—')
                            };
                            $empHorasTrabalhadas = (float)($emp['rel_horas_trabalhadas'] ?? ($emp['horas_trabalhadas'] ?? 0));
                            $empFaltas = (int)($emp['rel_faltas'] ?? ($emp['faltas'] ?? 0));
                            $empSalarioBase = (float)($emp['rel_salary_base'] ?? ($emp['salary_base'] ?? 0));
                            $empGorjetas = (float)($emp['rel_gorjetas'] ?? ($emp['gorjetas'] ?? 0));
                            $empTotalLiquido = (float)($emp['rel_total_liquido'] ?? ($emp['total_liquido'] ?? 0));
                            // Verificar se Total Líquido é fallback (sem folha processada)
                            $empId = (int)($emp['id'] ?? 0);
                            $folhaEmp = $folhaPorFuncionario[$empId] ?? null;
                            $totalLiquidoIsFallback = empty($folhaEmp) || empty($folhaEmp['salario_liquido']) || (float)($folhaEmp['salario_liquido'] ?? 0) <= 0;
                            $totalLiquidoTooltip = $totalLiquidoIsFallback ? 'Valor estimado (bruto base + gorjetas). Aguarda folha processada.' : 'Valor de folha processada';
                            $empDataAdmissaoIso = '';
                            if (!empty($emp['startDate'])) {
                                $admissaoTs = strtotime((string)$emp['startDate']);
                                if ($admissaoTs !== false) {
                                    $empDataAdmissaoIso = date('Y-m-d', $admissaoTs);
                                }
                            }
                        ?>
                        <tr class="fr-row"
                            data-rel-name="<?php echo htmlspecialchars($empNome, ENT_QUOTES, 'UTF-8'); ?>"
                            data-rel-cargo="<?php echo htmlspecialchars($empCargo, ENT_QUOTES, 'UTF-8'); ?>"
                            data-rel-department="<?php echo htmlspecialchars($empDepartamento, ENT_QUOTES, 'UTF-8'); ?>"
                            data-rel-status="<?php echo htmlspecialchars($empStatusFilter, ENT_QUOTES, 'UTF-8'); ?>"
                            data-rel-hours="<?php echo htmlspecialchars((string)$empHorasTrabalhadas, ENT_QUOTES, 'UTF-8'); ?>"
                            data-rel-faltas="<?php echo htmlspecialchars((string)$empFaltas, ENT_QUOTES, 'UTF-8'); ?>"
                            data-rel-date="<?php echo htmlspecialchars($empDataAdmissaoIso, ENT_QUOTES, 'UTF-8'); ?>"
                            data-rel-total="<?php echo htmlspecialchars((string)$empTotalLiquido, ENT_QUOTES, 'UTF-8'); ?>">
                            <td class="fr-td-emp">
                                <div class="fr-emp-cell">
                                    <div class="fr-av" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed);">
                                        <?php if (!empty($emp['profile_picture'])): ?>
                                        <img class="fr-av-img" src="../<?php echo htmlspecialchars($emp['profile_picture']); ?>"
                                            alt="<?php echo htmlspecialchars($emp['name']); ?>"
                                            onerror="this.style.display='none'">
                                        <?php else: ?>
                                        <?php echo strtoupper(mb_substr($emp['name'] ?? '?',0,2)); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="fr-emp-info">
                                        <span class="fr-emp-name"><?php echo htmlspecialchars($empNome !== '' ? $empNome : '—'); ?></span>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($empCargo !== '' ? $empCargo : '—'); ?></td>
                            <td><?php echo number_format($empHorasTrabalhadas, 2, ',', '.'); ?></td>
                            <td><?php echo $empFaltas; ?></td>
                            <td>€ <?php echo number_format($empSalarioBase, 2, ',', '.'); ?></td>
                            <td>€ <?php echo number_format($empGorjetas, 2, ',', '.'); ?></td>
                            <td title="<?php echo htmlspecialchars($totalLiquidoTooltip); ?>" style="<?php echo $totalLiquidoIsFallback ? 'background:rgba(251,146,60,0.1);color:#ea580c;font-weight:600;position:relative;' : ''; ?>">
                                € <?php echo number_format($empTotalLiquido,2,',','.'); ?>
                                <?php if ($totalLiquidoIsFallback): ?>
                                    <span style="font-size:0.65rem;margin-left:0.25rem;vertical-align:super;background:#f97316;color:white;padding:0.1rem 0.3rem;border-radius:2px;font-weight:700;">EST.</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($empStatusLabel); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            </div>











            <div class="relatorio-content" id="content-presenca" style="display:none;">
            <div class="data-table fr-table-wrap" id="relatorio-presenca-table">
                <?php
                    $rTotalPresc   = count($presencas);
                    $rPresentes    = count(array_filter($presencas, fn($p) => strtolower($p['status'] ?? '') === 'presente'));
                    $rFaltas       = $rTotalPresc - $rPresentes;
                    $rTaxaPresc    = $rTotalPresc > 0 ? round($rPresentes / $rTotalPresc * 100) : 0;
                ?>
                <div class="fr-kpi-strip" style="grid-template-columns:repeat(3,1fr);margin-bottom:1.5rem;">
                    <div class="fr-kpi">
                        <div class="fr-kpi-icon" style="background:rgba(59,130,246,.14);color:#60a5fa;"><i class="fas fa-calendar-alt"></i></div>
                        <div class="fr-kpi-body">
                            <span class="fr-kpi-val"><?php echo $rTotalPresc; ?></span>
                            <span class="fr-kpi-lbl">Total Registos</span>
                            <span class="fr-kpi-pct">no período</span>
                        </div>
                    </div>
                    <div class="fr-kpi">
                        <div class="fr-kpi-icon" style="background:rgba(16,185,129,.14);color:#10b981;"><i class="fas fa-user-check"></i></div>
                        <div class="fr-kpi-body">
                            <span class="fr-kpi-val"><?php echo $rPresentes; ?></span>
                            <span class="fr-kpi-lbl">Presenças</span>
                            <span class="fr-kpi-pct"><?php echo $rTaxaPresc; ?>% taxa</span>
                        </div>
                    </div>
                    <div class="fr-kpi">
                        <div class="fr-kpi-icon" style="background:rgba(239,68,68,.12);color:#f87171;"><i class="fas fa-user-times"></i></div>
                        <div class="fr-kpi-body">
                            <span class="fr-kpi-val"><?php echo $rFaltas; ?></span>
                            <span class="fr-kpi-lbl">Faltas / Ausências</span>
                            <span class="fr-kpi-pct"><?php echo 100 - $rTaxaPresc; ?>% do total</span>
                        </div>
                    </div>
                </div>
                <!-- GRÁFICO: Presença vs Faltas -->
                <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:1rem;margin-bottom:1.5rem;height:280px;">
                    <canvas id="chartPresencaStatus" style="max-height:250px;"></canvas>
                </div>
                <div class="fr-toolbar">
                    <div class="fr-toolbar-top">
                        <div class="fr-search-wrap">
                            <i class="fas fa-search fr-search-icon"></i>
                            <input type="text" id="presencaSearchInput" class="fr-search" placeholder="Pesquisar funcionário…">
                        </div>
                        <div class="fr-toolbar-right">
                            <span id="presencaResultCount" style="font-size:.78rem;color:#64748b;white-space:nowrap;"></span>
                            <button type="button" class="fr-filter-toggle" id="presencaFilterToggle"
                                onclick="document.getElementById('presencaAdvFilters').classList.toggle('fr-adv-open');this.classList.toggle('pa-filter-open')">
                                <i class="fas fa-sliders-h"></i> Filtros
                            </button>
                            <button id="btnExportarPresencas" type="button" class="fr-export-btn">
                                <i class="fas fa-download"></i> Exportar
                            </button>
                        </div>
                    </div>
                    <div class="fr-adv-filters" id="presencaAdvFilters">
                        <input type="date" id="presencaStartDate" class="fr-select" title="Data inicial">
                        <input type="date" id="presencaEndDate" class="fr-select" title="Data final">
                        <select id="presencaStatusFilter" class="fr-select">
                            <option value="">Todos</option>
                            <option value="presente">Presente</option>
                            <option value="ausente">Ausente</option>
                            <option value="falta">Falta</option>
                        </select>
                        <button type="button" class="fr-clear-btn" onclick="document.getElementById('presencaStartDate').value='';document.getElementById('presencaEndDate').value='';document.getElementById('presencaStatusFilter').value='';document.getElementById('presencaSearchInput').value='';document.getElementById('presencaSearchInput').dispatchEvent(new Event('input'));">Limpar</button>
                    </div>
                </div>
                <style>
                    .prc-tl { display:flex; flex-wrap:wrap; align-items:center; gap:4px; }
                    .prc-event { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:20px; font-size:.73rem; font-weight:600; white-space:nowrap; letter-spacing:.01em; }
                    .prc-event-entrada  { background:rgba(16,185,129,.15); color:#10b981; border:1px solid rgba(16,185,129,.28); }
                    .prc-event-regresso { background:rgba(59,130,246,.12); color:#60a5fa; border:1px solid rgba(59,130,246,.22); }
                    .prc-event-pausa    { background:rgba(245,158,11,.14); color:#f59e0b; border:1px solid rgba(245,158,11,.25); }
                    .prc-event-saida    { background:rgba(239,68,68,.12);  color:#f87171; border:1px solid rgba(239,68,68,.22); }
                    .prc-event-ativo    { background:rgba(52,211,153,.11); color:#34d399; border:1px solid rgba(52,211,153,.22); }
                    .prc-arr            { color:rgba(148,163,184,.4); font-size:.72rem; margin:0 1px; }
                    .prc-no-events      { font-size:.78rem; color:rgba(148,163,184,.42); font-style:italic; }
                    .prc-date-badge     { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; background:rgba(99,102,241,.1); color:#818cf8; border:1px solid rgba(99,102,241,.22); border-radius:20px; font-size:.75rem; font-weight:600; white-space:nowrap; }
                </style>
                <table class="table fr-table" id="presencaTable">
                    <thead>
                        <tr class="fr-thead-row">
                            <th class="fr-th-emp">Funcionário</th>
                            <th style="white-space:nowrap;">Data</th>
                            <th style="min-width:240px;">Roteiro do Dia</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody id="presencaTableBody">
                        <?php foreach ($presencas as $p):
                            $pNome     = htmlspecialchars($p['name'] ?? 'N/D');
                            $pData     = ($p['data_registro'] ?? '') ? date('d/m/Y', strtotime((string)$p['data_registro'])) : 'N/D';
                            $pDiaSem   = ($p['data_registro'] ?? '') ? ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'][(int)date('w', strtotime((string)$p['data_registro']))] : '';
                            $pDataIso  = ($p['data_registro'] ?? '') ? date('Y-m-d', strtotime((string)$p['data_registro'])) : '';
                            $pStatus   = strtolower(trim($p['status'] ?? 'presente'));
                            $pPhoto    = trim((string)($p['profile_picture'] ?? ''));
                            $pInitials = strtoupper(mb_substr($pNome, 0, 2));

                            $statusClass = match($pStatus) {
                                'presente' => 'status-presente',
                                'ausente'  => 'status-ausente',
                                'falta'    => 'status-falta',
                                default    => 'status-outro'
                            };
                            $statusLabel = ucfirst($pStatus);

                            // Build day timeline from GROUP_CONCAT ponto_timeline
                            $tlEvents = [];
                            $tlRaw    = trim((string)($p['ponto_timeline'] ?? ''));
                            if ($tlRaw !== '') {
                                $periods = explode(';;', $tlRaw);
                                $lastIdx = count($periods) - 1;
                                foreach ($periods as $ti => $period) {
                                    [$hEnt, $hSai, $obs] = array_pad(explode('|', $period, 3), 3, '');
                                    $hEnt   = trim($hEnt);
                                    $hSai   = trim($hSai);
                                    $obsLow = mb_strtolower(trim($obs));
                                    if ($hEnt !== '') {
                                        $tlEvents[] = $ti === 0
                                            ? ['hora' => substr($hEnt,0,5), 'tipo' => 'entrada',  'label' => 'Entrada',  'icon' => 'fa-sign-in-alt']
                                            : ['hora' => substr($hEnt,0,5), 'tipo' => 'regresso', 'label' => 'Regresso', 'icon' => 'fa-undo-alt'];
                                    }
                                    if ($hSai !== '') {
                                        if (str_contains($obsLow, 'pausa')) {
                                            $pLbl = str_contains($obsLow,'almo') ? 'P. Almoço' : (str_contains($obsLow,'cigar') ? 'P. Cigarro' : 'Pausa');
                                            $pIco = str_contains($obsLow,'almo') ? 'fa-utensils' : (str_contains($obsLow,'cigar') ? 'fa-smoking' : 'fa-pause-circle');
                                            $tlEvents[] = ['hora' => substr($hSai,0,5), 'tipo' => 'pausa', 'label' => $pLbl, 'icon' => $pIco];
                                        } else {
                                            $tlEvents[] = ['hora' => substr($hSai,0,5), 'tipo' => 'saida', 'label' => 'Saída', 'icon' => 'fa-sign-out-alt'];
                                        }
                                    } elseif ($ti === $lastIdx && !empty($tlEvents) && end($tlEvents)['tipo'] !== 'saida') {
                                        $tlEvents[] = ['hora' => null, 'tipo' => 'ativo', 'label' => 'Em serviço', 'icon' => 'fa-circle'];
                                    }
                                }
                            }
                        ?>
                        <tr class="fr-row"
                            data-presenca-nome="<?php echo mb_strtolower($pNome); ?>"
                            data-presenca-status="<?php echo htmlspecialchars($pStatus); ?>"
                            data-presenca-date="<?php echo htmlspecialchars($pDataIso); ?>">

                            <td class="fr-td-emp">
                                <div class="fr-emp-cell">
                                    <div class="fr-av" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);">
                                        <?php if ($pPhoto !== ''): ?>
                                        <img class="fr-av-img" src="../<?php echo htmlspecialchars($pPhoto); ?>" alt="Avatar">
                                        <?php else: ?>
                                        <?php echo $pInitials; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="fr-emp-info">
                                        <span class="fr-emp-name"><?php echo $pNome; ?></span>
                                        <span class="fr-emp-email"><?php echo htmlspecialchars($pDiaSem); ?></span>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <span class="prc-date-badge">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo $pData; ?>
                                </span>
                            </td>

                            <td>
                                <?php if (!empty($tlEvents)): ?>
                                <div class="prc-tl">
                                    <?php foreach ($tlEvents as $eIdx => $ev): ?>
                                    <?php if ($eIdx > 0): ?><span class="prc-arr">›</span><?php endif; ?>
                                    <span class="prc-event prc-event-<?php echo $ev['tipo']; ?>">
                                        <i class="fas <?php echo $ev['icon']; ?>"></i>
                                        <?php if ($ev['hora'] !== null): echo $ev['hora'] . '&thinsp;'; endif; ?><?php echo htmlspecialchars($ev['label']); ?>
                                    </span>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <span class="prc-no-events"><i class="fas fa-minus-circle" style="margin-right:4px;opacity:.5;"></i>Sem registos de ponto</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>







            </div>

            <div class="relatorio-content" id="content-turnos" style="display:none;">
            <div class="data-table fr-table-wrap" id="relatorio-turnos-table">
                <?php
                    $rTurnosTotal  = count($turnos);
                    $rTurnosAtivos = count(array_filter($turnos, fn($t) => strtolower($t['status'] ?? 'ativo') === 'ativo'));
                    $rTurnosTipos  = count(array_unique(array_filter(array_map(fn($t) => $t['turno_tipo'] ?? '', $turnos))));
                ?>
                <div class="fr-kpi-strip" style="grid-template-columns:repeat(3,1fr);margin-bottom:1.5rem;">
                    <div class="fr-kpi">
                        <div class="fr-kpi-icon" style="background:rgba(167,139,250,.14);color:#a78bfa;"><i class="fas fa-clock"></i></div>
                        <div class="fr-kpi-body">
                            <span class="fr-kpi-val"><?php echo $rTurnosTotal; ?></span>
                            <span class="fr-kpi-lbl">Atribuições</span>
                            <span class="fr-kpi-pct">total de registos</span>
                        </div>
                    </div>
                    <div class="fr-kpi">
                        <div class="fr-kpi-icon" style="background:rgba(16,185,129,.14);color:#10b981;"><i class="fas fa-check-circle"></i></div>
                        <div class="fr-kpi-body">
                            <span class="fr-kpi-val"><?php echo $rTurnosAtivos; ?></span>
                            <span class="fr-kpi-lbl">Ativos</span>
                            <span class="fr-kpi-pct"><?php echo $rTurnosTotal > 0 ? round($rTurnosAtivos/$rTurnosTotal*100) : 0; ?>% do total</span>
                        </div>
                    </div>
                    <div class="fr-kpi">
                        <div class="fr-kpi-icon" style="background:rgba(139,92,246,.14);color:#c4b5fd;"><i class="fas fa-layer-group"></i></div>
                        <div class="fr-kpi-body">
                            <span class="fr-kpi-val"><?php echo $rTurnosTipos; ?></span>
                            <span class="fr-kpi-lbl">Tipos de Turno</span>
                            <span class="fr-kpi-pct">distintos</span>
                        </div>
                    </div>
                </div>
                <!-- GRÁFICO: Distribuição de Funcionários por Turno -->
                <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:1rem;margin-bottom:1.5rem;height:280px;">
                    <canvas id="chartTurnosDistribuicao" style="max-height:250px;"></canvas>
                </div>
                <div class="fr-toolbar">
                    <div class="fr-toolbar-top">
                        <div class="fr-search-wrap">
                            <i class="fas fa-search fr-search-icon"></i>
                            <input type="text" id="turnosSearchInput" class="fr-search" placeholder="Pesquisar turno…">
                        </div>
                        <div class="fr-toolbar-right">
                            <span id="turnosResultCount" style="font-size:.78rem;color:#64748b;white-space:nowrap;"></span>
                            <button type="button" class="fr-filter-toggle" id="turnosFilterToggle"
                                onclick="document.getElementById('turnosAdvFilters').classList.toggle('fr-adv-open');this.classList.toggle('pa-filter-open')">
                                <i class="fas fa-sliders-h"></i> Filtros
                            </button>
                            <button id="btnExportarTurnos" type="button" class="fr-export-btn">
                                <i class="fas fa-download"></i> Exportar
                            </button>
                        </div>
                    </div>
                    <div class="fr-adv-filters" id="turnosAdvFilters">
                        <input type="date" id="turnosStartDate" class="fr-select" title="Data inicial">
                        <input type="date" id="turnosEndDate" class="fr-select" title="Data final">
                        <select id="turnosStatusFilter" class="fr-select">
                            <option value="">Todos</option>
                            <option value="ativo">Ativo</option>
                            <option value="inativo">Inativo</option>
                        </select>
                        <button type="button" class="fr-clear-btn" onclick="document.getElementById('turnosStartDate').value='';document.getElementById('turnosEndDate').value='';document.getElementById('turnosStatusFilter').value='';document.getElementById('turnosSearchInput').value='';document.getElementById('turnosSearchInput').dispatchEvent(new Event('input'));">Limpar</button>
                    </div>
                </div>
                <table class="table fr-table">
                    <thead>
                        <tr class="fr-thead-row">
                            <th>Funcionário</th>
                            <th>Turno</th>
                            <th>Horário</th>
                            <th>Dias</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($turnos as $t):
                            $tNome = htmlspecialchars($t['name'] ?? 'N/D');
                            $tTipo = htmlspecialchars($t['turno_tipo'] ?? 'N/D');
                            $tHorario = date('H:i', strtotime($t['horario_inicio'])) . ' - ' . date('H:i', strtotime($t['horario_fim']));
                            $tDias = htmlspecialchars($t['dias_semana'] ?? 'N/D');
                            $tDataIso = '';
                            if (!empty($t['data_inicio'])) {
                                $tDataTs = strtotime((string)$t['data_inicio']);
                                if ($tDataTs !== false) {
                                    $tDataIso = date('Y-m-d', $tDataTs);
                                }
                            } elseif (!empty($t['created_at'])) {
                                $tCreatedTs = strtotime((string)$t['created_at']);
                                if ($tCreatedTs !== false) {
                                    $tDataIso = date('Y-m-d', $tCreatedTs);
                                }
                            }
                            $tStatus = strtolower(trim($t['status'] ?? 'ativo'));
                            $tEmpId = (int)($t['funcionario_id'] ?? 0);
                            $tEmpInfo = null;
                            foreach ($employees as $emp) {
                                if ($emp['id'] == $tEmpId) {
                                    $tEmpInfo = $emp;
                                    break;
                                }
                            }
                            $statusClass = match($tStatus) {
                                'ativo' => 'status-presente',
                                'inativo' => 'status-nao-marcado',
                                default => 'status-outro'
                            };
                        ?>
                        <tr class="fr-row" data-turno-nome="<?php echo mb_strtolower($tNome); ?>" data-turno-tipo="<?php echo mb_strtolower($tTipo); ?>" data-turno-status="<?php echo htmlspecialchars($tStatus); ?>" data-turno-date="<?php echo htmlspecialchars($tDataIso); ?>">
                            <td class="fr-td-emp">
                                <div class="fr-emp-cell">
                                    <div class="fr-av" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed);">
                                        <?php if ($tEmpInfo && !empty($tEmpInfo['profile_picture'])): ?>
                                        <img class="fr-av-img" src="../<?php echo htmlspecialchars($tEmpInfo['profile_picture']); ?>" alt="Avatar">
                                        <?php else: ?>
                                        <?php echo strtoupper(mb_substr($tNome, 0, 2)); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="fr-emp-info">
                                        <span class="fr-emp-name"><?php echo $tNome; ?></span>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo $tTipo; ?></td>
                            <td><?php echo $tHorario; ?></td>
                            <td><?php echo $tDias; ?></td>
                            <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo ucfirst($tStatus); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            </div>

            <div class="relatorio-content" id="content-gorjetas" style="display:none;">
            <div class="data-table fr-table-wrap" id="relatorio-gorjetas-table">
                <?php
                    $rGorjTotalRecs = count($gorjetas);
                    $rGorjTotal     = array_sum(array_column($gorjetas, 'valor'));
                    $rGorjPend      = count(array_filter($gorjetas, fn($g) => strtolower($g['status'] ?? '') === 'pendente'));
                ?>
                <div class="fr-kpi-strip" style="grid-template-columns:repeat(3,1fr);margin-bottom:1.5rem;">
                    <div class="fr-kpi">
                        <div class="fr-kpi-icon" style="background:rgba(34,211,238,.14);color:#22d3ee;"><i class="fas fa-hand-holding-usd"></i></div>
                        <div class="fr-kpi-body">
                            <span class="fr-kpi-val"><?php echo $rGorjTotalRecs; ?></span>
                            <span class="fr-kpi-lbl">Total Registos</span>
                            <span class="fr-kpi-pct">gorjetas lançadas</span>
                        </div>
                    </div>
                    <div class="fr-kpi">
                        <div class="fr-kpi-icon" style="background:rgba(6,182,212,.14);color:#06b6d4;"><i class="fas fa-euro-sign"></i></div>
                        <div class="fr-kpi-body">
                            <span class="fr-kpi-val" style="font-size:1.1rem;">€ <?php echo number_format((float)$rGorjTotal, 2, ',', '.'); ?></span>
                            <span class="fr-kpi-lbl">Total (€)</span>
                            <span class="fr-kpi-pct">soma do período</span>
                        </div>
                    </div>
                    <div class="fr-kpi">
                        <div class="fr-kpi-icon" style="background:rgba(245,158,11,.14);color:#fbbf24;"><i class="fas fa-clock"></i></div>
                        <div class="fr-kpi-body">
                            <span class="fr-kpi-val"><?php echo $rGorjPend; ?></span>
                            <span class="fr-kpi-lbl">Pendentes</span>
                            <span class="fr-kpi-pct">por processar</span>
                        </div>
                    </div>
                </div>
                <!-- GRÁFICO: Top Gorjetas por Funcionário -->
                <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:1rem;margin-bottom:1.5rem;height:280px;">
                    <canvas id="chartGorjetasTop" style="max-height:250px;"></canvas>
                </div>
                <div class="fr-toolbar">
                    <div class="fr-toolbar-top">
                        <div class="fr-search-wrap">
                            <i class="fas fa-search fr-search-icon"></i>
                            <input type="text" id="gorjetasSearchInput" class="fr-search" placeholder="Pesquisar funcionário…">
                        </div>
                        <div class="fr-toolbar-right">
                            <span id="gorjetasResultCount" style="font-size:.78rem;color:#64748b;white-space:nowrap;"></span>
                            <button type="button" class="fr-filter-toggle" id="gorjetasFilterToggle"
                                onclick="document.getElementById('gorjetasAdvFilters').classList.toggle('fr-adv-open');this.classList.toggle('pa-filter-open')">
                                <i class="fas fa-sliders-h"></i> Filtros
                            </button>
                            <button id="btnExportarGorjetas" type="button" class="fr-export-btn">
                                <i class="fas fa-download"></i> Exportar
                            </button>
                        </div>
                    </div>
                    <div class="fr-adv-filters" id="gorjetasAdvFilters">
                        <input type="date" id="gorjetasStartDate" class="fr-select" title="Data inicial">
                        <input type="date" id="gorjetasEndDate" class="fr-select" title="Data final">
                        <select id="gorjetasStatusFilter" class="fr-select">
                            <option value="">Todos</option>
                            <option value="pendente">Pendente</option>
                            <option value="pago">Pago</option>
                            <option value="cancelado">Cancelado</option>
                            <option value="rejeitado">Rejeitado</option>
                        </select>
                        <button type="button" class="fr-clear-btn" onclick="document.getElementById('gorjetasStartDate').value='';document.getElementById('gorjetasEndDate').value='';document.getElementById('gorjetasStatusFilter').value='';document.getElementById('gorjetasSearchInput').value='';document.getElementById('gorjetasSearchInput').dispatchEvent(new Event('input'));">Limpar</button>
                    </div>
                </div>
                <table class="table fr-table">
                    <thead>
                        <tr class="fr-thead-row">
                            <th>Funcionário</th>
                            <th>Valor (€)</th>
                            <th>Data</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($gorjetas as $g):
                            $gNome = htmlspecialchars($g['name'] ?? 'N/D');
                            $gValor = number_format((float)($g['valor'] ?? 0), 2, ',', '.');
                            $gData = ($g['data'] ?? '') && $g['data'] !== '0000-00-00' ? date('d/m/Y', strtotime($g['data'])) : 'N/D';
                            $gDataIso = ($g['data'] ?? '') && $g['data'] !== '0000-00-00' ? date('Y-m-d', strtotime((string)$g['data'])) : '';
                            $gStatus = strtolower(trim($g['status'] ?? 'pendente'));
                            $gEmpId = (int)($g['funcionario_id'] ?? 0);
                            $gEmpInfo = null;
                            foreach ($employees as $emp) {
                                if ($emp['id'] == $gEmpId) {
                                    $gEmpInfo = $emp;
                                    break;
                                }
                            }
                            $statusClass = match($gStatus) {
                                'pago' => 'status-presente',
                                'pendente' => 'status-warning',
                                'cancelado' => 'status-nao-marcado',
                                'rejeitado' => 'status-falta',
                                default => 'status-outro'
                            };
                        ?>
                        <tr class="fr-row" data-gorjeta-nome="<?php echo mb_strtolower($gNome); ?>" data-gorjeta-status="<?php echo htmlspecialchars($gStatus); ?>" data-gorjeta-date="<?php echo htmlspecialchars($gDataIso); ?>">
                            <td class="fr-td-emp">
                                <div class="fr-emp-cell">
                                    <div class="fr-av" style="background:linear-gradient(135deg,#06b6d4,#0284c7);">
                                        <?php if ($gEmpInfo && !empty($gEmpInfo['profile_picture'])): ?>
                                        <img class="fr-av-img" src="../<?php echo htmlspecialchars($gEmpInfo['profile_picture']); ?>" alt="Avatar">
                                        <?php else: ?>
                                        <?php echo strtoupper(mb_substr($gNome, 0, 2)); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="fr-emp-info">
                                        <span class="fr-emp-name"><?php echo $gNome; ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>€ <?php echo $gValor; ?></td>
                            <td><?php echo $gData; ?></td>
                            <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo ucfirst($gStatus); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            </div>
            </div>

            <div class="relatorio-content" id="content-folha" style="display:none;">
            <div class="data-table fr-table-wrap" id="relatorio-folha-table">
                <?php
                    $rFolhaTotalRecs  = count($folhaPagamento);
                    $rFolhaBrutoSum   = array_sum(array_column($folhaPagamento, 'salary_bruto'));
                    $rFolhaLiquidoSum = array_sum(array_column($folhaPagamento, 'salary_liquido'));
                ?>
                <div class="fr-kpi-strip" style="grid-template-columns:repeat(3,1fr);margin-bottom:1.5rem;">
                    <div class="fr-kpi">
                        <div class="fr-kpi-icon" style="background:rgba(251,113,133,.14);color:#fb7185;"><i class="fas fa-users"></i></div>
                        <div class="fr-kpi-body">
                            <span class="fr-kpi-val"><?php echo $rFolhaTotalRecs; ?></span>
                            <span class="fr-kpi-lbl">Funcionários</span>
                            <span class="fr-kpi-pct">na folha</span>
                        </div>
                    </div>
                    <div class="fr-kpi">
                        <div class="fr-kpi-icon" style="background:rgba(239,68,68,.14);color:#f87171;"><i class="fas fa-money-bill-wave"></i></div>
                        <div class="fr-kpi-body">
                            <span class="fr-kpi-val" style="font-size:1.05rem;">€ <?php echo number_format((float)$rFolhaBrutoSum, 2, ',', '.'); ?></span>
                            <span class="fr-kpi-lbl">Total Bruto</span>
                            <span class="fr-kpi-pct">custo total</span>
                        </div>
                    </div>
                    <div class="fr-kpi">
                        <div class="fr-kpi-icon" style="background:rgba(16,185,129,.14);color:#4ade80;"><i class="fas fa-wallet"></i></div>
                        <div class="fr-kpi-body">
                            <span class="fr-kpi-val" style="font-size:1.05rem;">€ <?php echo number_format((float)$rFolhaLiquidoSum, 2, ',', '.'); ?></span>
                            <span class="fr-kpi-lbl">Total Líquido</span>
                            <span class="fr-kpi-pct">a pagar</span>
                        </div>
                    </div>
                </div>
                <!-- GRÁFICO: Custos Salariais -->
                <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:1rem;margin-bottom:1.5rem;height:280px;">
                    <canvas id="chartFolhaCustos" style="max-height:250px;"></canvas>
                </div>
                <div class="fr-toolbar">
                    <div class="fr-toolbar-top">
                        <div class="fr-search-wrap">
                            <i class="fas fa-search fr-search-icon"></i>
                            <input type="text" id="folhaSearchInput" class="fr-search" placeholder="Pesquisar funcionário…">
                        </div>
                        <div class="fr-toolbar-right">
                            <span id="folhaResultCount" style="font-size:.78rem;color:#64748b;white-space:nowrap;"></span>
                            <button type="button" class="fr-filter-toggle" id="folhaFilterToggle"
                                onclick="document.getElementById('folhaAdvFilters').classList.toggle('fr-adv-open');this.classList.toggle('pa-filter-open')">
                                <i class="fas fa-sliders-h"></i> Filtros
                            </button>
                            <button id="btnExportarFolha" type="button" class="fr-export-btn">
                                <i class="fas fa-download"></i> Exportar
                            </button>
                        </div>
                    </div>
                    <div class="fr-adv-filters" id="folhaAdvFilters">
                        <input type="date" id="folhaStartDate" class="fr-select" title="Data inicial">
                        <input type="date" id="folhaEndDate" class="fr-select" title="Data final">
                        <button type="button" class="fr-clear-btn" onclick="document.getElementById('folhaStartDate').value='';document.getElementById('folhaEndDate').value='';document.getElementById('folhaSearchInput').value='';document.getElementById('folhaSearchInput').dispatchEvent(new Event('input'));">Limpar</button>
                    </div>
                </div>
                <table class="table fr-table">
                    <thead>
                        <tr class="fr-thead-row">
                            <th>Funcionário</th>
                            <th>Bruto (€)</th>
                            <th>Líquido (€)</th>
                            <th>Período</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $currentMonth = date('n'); $currentYear = date('Y'); $mesesPt = [1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr', 5 => 'Mai', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago', 9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez'];
                        foreach ($folhaPagamento as $f):
                            $fNome = htmlspecialchars($f['name'] ?? 'N/D');
                            $fBruto = number_format((float)($f['salary_bruto'] ?? 0), 2, ',', '.');
                            $fLiquido = number_format((float)($f['salary_liquido'] ?? 0), 2, ',', '.');
                            $fMes = $mesesPt[(int)($f['fiscal_month'] ?? 1)] . ' ' . ($f['fiscal_year'] ?? $currentYear);
                            $fDataIso = sprintf('%04d-%02d-01', (int)($f['fiscal_year'] ?? $currentYear), (int)($f['fiscal_month'] ?? 1));
                            $fEmpId = (int)($f['employee_id'] ?? 0);
                            $fEmpInfo = null;
                            foreach ($employees as $emp) {
                                if ($emp['id'] == $fEmpId) {
                                    $fEmpInfo = $emp;
                                    break;
                                }
                            }
                        ?>
                        <tr class="fr-row" data-folha-nome="<?php echo mb_strtolower($fNome); ?>" data-folha-date="<?php echo htmlspecialchars($fDataIso); ?>">
                            <td class="fr-td-emp">
                                <div class="fr-emp-cell">
                                    <div class="fr-av" style="background:linear-gradient(135deg,#ef4444,#dc2626);">
                                        <?php if ($fEmpInfo && !empty($fEmpInfo['profile_picture'])): ?>
                                        <img class="fr-av-img" src="../<?php echo htmlspecialchars($fEmpInfo['profile_picture']); ?>" alt="Avatar">
                                        <?php else: ?>
                                        <?php echo strtoupper(mb_substr($fNome, 0, 2)); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="fr-emp-info">
                                        <span class="fr-emp-name"><?php echo $fNome; ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>€ <?php echo $fBruto; ?></td>
                            <td>€ <?php echo $fLiquido; ?></td>
                            <td><?php echo $fMes; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            </div>

            <div class="relatorio-content" id="content-ferias" style="display:none;">
            <div class="data-table fr-table-wrap" id="relatorio-ferias-table">
                <table class="table fr-table">
                    <thead>
                        <tr class="fr-thead-row">
                            <th>Funcionário</th>
                            <th>Período</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($employees as $emp) {
                            $status = mb_strtolower(trim((string)($emp['status'] ?? '')));
                            if ($status === 'ferias' || $status === 'férias') {
                                $nome = htmlspecialchars($emp['name'] ?? '—');
                                $periodo = '—';
                                $statusLabel = 'Em Férias';
                                $initials = strtoupper(mb_substr($emp['name'] ?? '?', 0, 2));
                                echo '<tr class="fr-row">';
                                echo '<td class="fr-td-emp">';
                                echo '<div class="fr-emp-cell">';
                                echo '<div class="fr-av" style="background:linear-gradient(135deg,#0ea5e9,#0284c7);">';
                                if (!empty($emp['profile_picture'])) {
                                    echo '<img class="fr-av-img" src="' . htmlspecialchars($emp['profile_picture']) . '" alt="Avatar">';
                                } else {
                                    echo $initials;
                                }
                                echo '</div>';
                                echo '<div class="fr-emp-info"><span class="fr-emp-name">' . $nome . '</span></div>';
                                echo '</div>';
                                echo '</td>';
                                echo '<td>' . $periodo . '</td>';
                                echo '<td><span class="status-badge status-ferias"><i class="fas fa-umbrella-beach"></i> ' . $statusLabel . '</span></td>';
                                echo '</tr>';
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            </div>

        </section>

        <script>
        // Dados para os gráficos dos relatórios
        document.addEventListener('DOMContentLoaded', function() {
            const colors = ['#667eea', '#764ba2', '#f093fb', '#4facfe', '#00f2fe', '#43e97b', '#fa709a', '#fee140'];
            const chartsData = {};

            // 1. FUNCIONÁRIOS - Status Distribution
            const funcionariosStatus = <?php
                $statusCount = [];
                foreach ($employees as $emp) {
                    $status = strtolower($emp['status'] ?? 'ativo');
                    $statusCount[$status] = ($statusCount[$status] ?? 0) + 1;
                }
                echo json_encode($statusCount);
            ?>;
            
            if (document.getElementById('chartFuncionariosStatus')) {
                const ctx1 = document.getElementById('chartFuncionariosStatus').getContext('2d');
                new Chart(ctx1, {
                    type: 'doughnut',
                    data: {
                        labels: Object.keys(funcionariosStatus).map(s => s.charAt(0).toUpperCase() + s.slice(1)),
                        datasets: [{
                            data: Object.values(funcionariosStatus),
                            backgroundColor: colors.slice(0, Object.keys(funcionariosStatus).length),
                            borderColor: '#1e293b',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { color: '#e2e8f0', font: { size: 12 } } },
                            title: { display: true, text: 'Distribuição de Status', color: '#e2e8f0' }
                        }
                    }
                });
            }

            // 2. FUNCIONÁRIOS - Cargo Distribution
            const funcionariosCargos = <?php
                $cargoCount = [];
                foreach ($employees as $emp) {
                    $cargo = $emp['role'] ?? ($emp['position'] ?? 'Sem Cargo');
                    $cargoCount[$cargo] = ($cargoCount[$cargo] ?? 0) + 1;
                }
                // Limitar a top 8 cargos
                arsort($cargoCount);
                $cargoCount = array_slice($cargoCount, 0, 8, true);
                echo json_encode($cargoCount);
            ?>;
            
            if (document.getElementById('chartFuncionariosCargos')) {
                const ctx2 = document.getElementById('chartFuncionariosCargos').getContext('2d');
                new Chart(ctx2, {
                    type: 'bar',
                    data: {
                        labels: Object.keys(funcionariosCargos),
                        datasets: [{
                            label: 'Funcionários',
                            data: Object.values(funcionariosCargos),
                            backgroundColor: colors[0],
                            borderColor: '#667eea',
                            borderWidth: 2,
                            borderRadius: 6
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { labels: { color: '#e2e8f0' } },
                            title: { display: true, text: 'Distribuição por Cargo', color: '#e2e8f0' }
                        },
                        scales: {
                            x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,0.05)' } },
                            y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,0.05)' } }
                        }
                    }
                });
            }

            // 3. PRESENÇA - Status Distribution (Últimos 7 dias)
            const presencaStatus = <?php
                $statusPresenca = ['presente' => 0, 'ausente' => 0, 'falta' => 0];
                $hoje = new DateTime();
                $umaSemanaAtras = clone $hoje;
                $umaSemanaAtras->modify('-7 days');
                
                foreach ($presencas as $p) {
                    $status = strtolower($p['status'] ?? 'presente');
                    if (isset($statusPresenca[$status])) {
                        $dataRegistro = $p['data_registro'] ?? date('Y-m-d');
                        $dataObj = new DateTime($dataRegistro);
                        if ($dataObj >= $umaSemanaAtras && $dataObj <= $hoje) {
                            $statusPresenca[$status]++;
                        }
                    }
                }
                echo json_encode($statusPresenca);
            ?>;
            
            if (document.getElementById('chartPresencaStatus')) {
                const ctx3 = document.getElementById('chartPresencaStatus').getContext('2d');
                new Chart(ctx3, {
                    type: 'bar',
                    data: {
                        labels: ['Presente', 'Ausente', 'Falta'],
                        datasets: [{
                            label: 'Presenças (7 dias)',
                            data: [presencaStatus.presente ?? 0, presencaStatus.ausente ?? 0, presencaStatus.falta ?? 0],
                            backgroundColor: ['#43e97b', '#fa709a', '#fee140'],
                            borderColor: ['#43e97b', '#fa709a', '#fee140'],
                            borderWidth: 2,
                            borderRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { labels: { color: '#e2e8f0' } },
                            title: { display: true, text: 'Presenças vs Faltas (7 dias)', color: '#e2e8f0' }
                        },
                        scales: {
                            x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,0.05)' } },
                            y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,0.05)' } }
                        }
                    }
                });
            }

            // 4. TURNOS - Distribution
            const turnosDistribuicao = <?php
                $turnoCount = [];
                foreach ($turnos as $t) {
                    $turno = $t['nome_turno'] ?? 'Sem Turno';
                    $turnoCount[$turno] = ($turnoCount[$turno] ?? 0) + 1;
                }
                echo json_encode($turnoCount);
            ?>;
            
            if (document.getElementById('chartTurnosDistribuicao')) {
                const ctx4 = document.getElementById('chartTurnosDistribuicao').getContext('2d');
                new Chart(ctx4, {
                    type: 'bar',
                    data: {
                        labels: Object.keys(turnosDistribuicao),
                        datasets: [{
                            label: 'Funcionários por Turno',
                            data: Object.values(turnosDistribuicao),
                            backgroundColor: colors[1],
                            borderColor: '#764ba2',
                            borderWidth: 2,
                            borderRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { labels: { color: '#e2e8f0' } },
                            title: { display: true, text: 'Distribuição por Turno', color: '#e2e8f0' }
                        },
                        scales: {
                            x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,0.05)' } },
                            y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,0.05)' } }
                        }
                    }
                });
            }

            // 6. GORJETAS - Top Gorjetas por Funcionário
            const gorjetasTop = <?php
                $gorjetasPorFunc = [];
                foreach ($gorjetas as $g) {
                    $nome = $g['name'] ?? 'Desconhecido';
                    $valor = (float)($g['valor'] ?? 0);
                    if (!isset($gorjetasPorFunc[$nome])) {
                        $gorjetasPorFunc[$nome] = 0;
                    }
                    $gorjetasPorFunc[$nome] += $valor;
                }
                arsort($gorjetasPorFunc);
                $gorjetasPorFunc = array_slice($gorjetasPorFunc, 0, 10, true);
                echo json_encode($gorjetasPorFunc);
            ?>;
            
            if (document.getElementById('chartGorjetasTop')) {
                const ctx6 = document.getElementById('chartGorjetasTop').getContext('2d');
                new Chart(ctx6, {
                    type: 'bar',
                    data: {
                        labels: Object.keys(gorjetasTop),
                        datasets: [{
                            label: 'Gorjetas (€)',
                            data: Object.values(gorjetasTop),
                            backgroundColor: colors[2],
                            borderColor: '#f093fb',
                            borderWidth: 2,
                            borderRadius: 6
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { labels: { color: '#e2e8f0' } },
                            title: { display: true, text: 'Top Gorjetas por Funcionário', color: '#e2e8f0' }
                        },
                        scales: {
                            x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,0.05)' } },
                            y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,0.05)' } }
                        }
                    }
                });
            }

            // 7. FOLHA - Custos Salariais Top Funcionários
            const folhaCustos = <?php
                $custosPorFunc = [];
                foreach ($folhaPagamento as $f) {
                    $nome = $f['name'] ?? 'Desconhecido';
                    $total = (float)($f['total_bruto'] ?? 0);
                    if (!isset($custosPorFunc[$nome])) {
                        $custosPorFunc[$nome] = 0;
                    }
                    $custosPorFunc[$nome] += $total;
                }
                arsort($custosPorFunc);
                $custosPorFunc = array_slice($custosPorFunc, 0, 10, true);
                echo json_encode($custosPorFunc);
            ?>;
            
            if (document.getElementById('chartFolhaCustos')) {
                const ctx7 = document.getElementById('chartFolhaCustos').getContext('2d');
                new Chart(ctx7, {
                    type: 'bar',
                    data: {
                        labels: Object.keys(folhaCustos),
                        datasets: [{
                            label: 'Custos Salariais (€)',
                            data: Object.values(folhaCustos),
                            backgroundColor: colors[3],
                            borderColor: '#4facfe',
                            borderWidth: 2,
                            borderRadius: 6
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { labels: { color: '#e2e8f0' } },
                            title: { display: true, text: 'Custos Salariais (Top)', color: '#e2e8f0' }
                        },
                        scales: {
                            x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,0.05)' } },
                            y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,0.05)' } }
                        }
                    }
                });
            }
        });
        </script>









       <section id="ferias-section" class="content-section">
    <?php
    $feriasAll = [];
    $feriasPendentesCount = 0;
    $feriasAgendadasCount = 0;
    $feriasEmCursoCount = 0;
    $feriasConcluidasCount = 0;
    $feriasRejeitadasCount = 0;
    $feriasCanceladasCount = 0;
    $todayDate = date('Y-m-d');

    try {
        $feriasSectionCols = $pdo->query('SHOW COLUMNS FROM ferias')->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $feriasSectionCols = array_map(static fn($c) => mb_strtolower((string)$c), $feriasSectionCols);
        $feriasSectionEmployeeCol = in_array('funcionario_id', $feriasSectionCols, true)
            ? 'funcionario_id'
            : (in_array('employee_id', $feriasSectionCols, true) ? 'employee_id' : 'funcionario_id');

        $hasMotivoRejeicao = in_array('motivo_rejeicao', $feriasSectionCols, true);
        $motRejeicaoSel = $hasMotivoRejeicao ? ', f.motivo_rejeicao' : '';

        $stmtFeriasSection = $pdo->prepare("SELECT f.id, f.{$feriasSectionEmployeeCol} AS employee_id,
                    f.data_inicio, f.data_fim, f.status, f.motivo{$motRejeicaoSel},
                    e.name AS employee_name, e.profile_picture AS employee_profile_picture, e.position AS employee_position
             FROM ferias f
             INNER JOIN employees e ON e.id = f.{$feriasSectionEmployeeCol}
             WHERE e.client_id = ?
             ORDER BY FIELD(f.status,'pendente','aprovada','rejeitada','cancelada'), f.data_inicio DESC, f.id DESC");
        $stmtFeriasSection->execute([(int)$loggedInClientId]);
        $feriasAll = $stmtFeriasSection->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Aviso de conflito de equipa: marca cada pedido ativo (pendente/aprovada) que se
        // sobrepõe a outro pedido ativo de um colega com a mesma função (cargo).
        $feriasAtivosPorPosicao = [];
        foreach ($feriasAll as $fc) {
            $fcStatus = mb_strtolower(trim((string)($fc['status'] ?? '')));
            if ($fcStatus === 'aprovado') $fcStatus = 'aprovada';
            if (!in_array($fcStatus, ['pendente', 'pending', 'aprovada'], true)) continue;
            $fcPos = trim((string)($fc['employee_position'] ?? ''));
            if ($fcPos === '') continue;
            $feriasAtivosPorPosicao[$fcPos][] = $fc;
        }
        foreach ($feriasAll as &$fc) {
            $fc['conflito_count'] = 0;
            $fcStatus = mb_strtolower(trim((string)($fc['status'] ?? '')));
            if ($fcStatus === 'aprovado') $fcStatus = 'aprovada';
            if (!in_array($fcStatus, ['pendente', 'pending', 'aprovada'], true)) continue;
            $fcPos = trim((string)($fc['employee_position'] ?? ''));
            if ($fcPos === '' || empty($feriasAtivosPorPosicao[$fcPos])) continue;
            $n = 0;
            foreach ($feriasAtivosPorPosicao[$fcPos] as $other) {
                if ((int)$other['id'] === (int)$fc['id']) continue;
                if ((int)$other['employee_id'] === (int)$fc['employee_id']) continue;
                if ((string)$other['data_inicio'] <= (string)$fc['data_fim'] && (string)$other['data_fim'] >= (string)$fc['data_inicio']) {
                    $n++;
                }
            }
            $fc['conflito_count'] = $n;
        }
        unset($fc);

        foreach ($feriasAll as $fStats) {
            $st = mb_strtolower(trim((string)($fStats['status'] ?? 'pendente')));
            if ($st === 'aprovado') $st = 'aprovada';
            $ini = (string)($fStats['data_inicio'] ?? '');
            $fim = (string)($fStats['data_fim'] ?? '');
            if ($st === 'pendente') { $feriasPendentesCount++; }
            elseif ($st === 'aprovada') {
                if ($ini !== '' && $todayDate < $ini) $feriasAgendadasCount++;
                elseif ($fim !== '' && $todayDate > $fim) $feriasConcluidasCount++;
                else $feriasEmCursoCount++;
            }
            elseif ($st === 'rejeitada') { $feriasRejeitadasCount++; }
            elseif ($st === 'cancelada') { $feriasCanceladasCount++; }
        }
    } catch (Throwable $e) {
        error_log('Erro ao carregar seção de férias: ' . $e->getMessage());
    }
    $feriasReview = trim((string)($_GET['review'] ?? ''));
    ?>

    <?php if ($feriasReview === 'created'): ?>
    <div class="alert-success" style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;padding:.85rem 1.1rem;border-radius:10px;background:linear-gradient(90deg,#14532d,#166534);color:#ecfdf5;">
        <i class="fas fa-check-circle"></i> Férias criadas e aprovadas com sucesso.
    </div>
    <?php elseif ($feriasReview === 'ok'): ?>
    <div class="alert-success" style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;padding:.85rem 1.1rem;border-radius:10px;background:linear-gradient(90deg,#14532d,#166534);color:#ecfdf5;">
        <i class="fas fa-check-circle"></i> Operação concluída com sucesso.
    </div>
    <?php elseif ($feriasReview === 'error'): ?>
    <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;padding:.85rem 1.1rem;border-radius:10px;background:linear-gradient(90deg,#7f1d1d,#991b1b);color:#fef2f2;">
        <i class="fas fa-exclamation-circle"></i> Ocorreu um erro. Tente novamente.
    </div>
    <?php elseif ($feriasReview === 'blocked'): ?>
    <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;padding:.85rem 1.1rem;border-radius:10px;background:linear-gradient(90deg,#78350f,#92400e);color:#fffbeb;">
        <i class="fas fa-ban"></i> Operação não permitida para o estado atual das férias.
    </div>
    <?php elseif ($feriasReview === 'saldo'): ?>
    <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;padding:.85rem 1.1rem;border-radius:10px;background:linear-gradient(90deg,#78350f,#92400e);color:#fffbeb;">
        <i class="fas fa-exclamation-triangle"></i> O funcionário não tem saldo de dias suficiente para este período. Marque "Ignorar saldo" para criar mesmo assim.
    </div>
    <?php endif; ?>

    <div class="frhd">
        <div class="frhd-left">
            <div class="frhd-icon" style="background:linear-gradient(135deg,#0ea5e9,#0284c7);box-shadow:0 4px 14px rgba(14,165,233,.35);"><i class="fas fa-umbrella-beach"></i></div>
            <div>
                <h2 class="frhd-title">Férias</h2>
                <p class="frhd-sub"><?php echo count($feriasAll); ?> registos &middot; <?php echo (int)$feriasPendentesCount; ?> pendente<?php echo $feriasPendentesCount !== 1 ? 's' : ''; ?> hoje</p>
            </div>
        </div>
        <button type="button" class="frhd-add-btn" style="background:linear-gradient(135deg,#0ea5e9,#0284c7);box-shadow:0 4px 12px rgba(14,165,233,.3);" onclick="openFeriasCreateModal()">
            <i class="fas fa-plus"></i> Nova Marcação
        </button>
    </div>

    <style>
        .fv-kpi-pending .fr-kpi-icon { background:rgba(245,158,11,.12); color:#f59e0b; }
        .fv-kpi-sched .fr-kpi-icon   { background:rgba(59,130,246,.12);  color:#3b82f6; }
        .fv-kpi-active .fr-kpi-icon  { background:rgba(16,185,129,.12);  color:#10b981; }
        .fv-kpi-done .fr-kpi-icon    { background:rgba(163,230,53,.1);   color:#a3e635; }
        .fv-kpi-rej .fr-kpi-icon     { background:rgba(239,68,68,.12);   color:#ef4444; }
        .fv-kpi-pending { position:relative; }
        .fv-kpi-badge { position:absolute;top:-6px;right:-6px;min-width:20px;height:20px;border-radius:50%;background:#ef4444;color:#fff;font-size:.65rem;font-weight:900;display:flex;align-items:center;justify-content:center;animation:solicitacaoBadgeFloat 1.5s ease-in-out infinite; }
    </style>
    <div class="fr-kpi-strip" style="grid-template-columns:repeat(5,1fr);">
        <div class="fr-kpi fv-kpi-pending">
            <?php if ($feriasPendentesCount > 0): ?>
            <span class="fv-kpi-badge"><?php echo (int)$feriasPendentesCount; ?></span>
            <?php endif; ?>
            <div class="fr-kpi-icon"><i class="fas fa-clock"></i></div>
            <div class="fr-kpi-body">
                <span class="fr-kpi-val"><?php echo (int)$feriasPendentesCount; ?></span>
                <span class="fr-kpi-lbl">Pendentes</span>
                <span class="fr-kpi-pct">aguardam aprovação</span>
            </div>
        </div>
        <div class="fr-kpi fv-kpi-sched">
            <div class="fr-kpi-icon"><i class="fas fa-calendar-plus"></i></div>
            <div class="fr-kpi-body">
                <span class="fr-kpi-val"><?php echo (int)$feriasAgendadasCount; ?></span>
                <span class="fr-kpi-lbl">Agendadas</span>
                <span class="fr-kpi-pct">início futuro</span>
            </div>
        </div>
        <div class="fr-kpi fv-kpi-active">
            <div class="fr-kpi-icon"><i class="fas fa-person-walking-luggage"></i></div>
            <div class="fr-kpi-body">
                <span class="fr-kpi-val"><?php echo (int)$feriasEmCursoCount; ?></span>
                <span class="fr-kpi-lbl">Em curso</span>
                <span class="fr-kpi-pct">a decorrer agora</span>
            </div>
        </div>
        <div class="fr-kpi fv-kpi-done">
            <div class="fr-kpi-icon"><i class="fas fa-calendar-check"></i></div>
            <div class="fr-kpi-body">
                <span class="fr-kpi-val"><?php echo (int)$feriasConcluidasCount; ?></span>
                <span class="fr-kpi-lbl">Concluídas</span>
                <span class="fr-kpi-pct">terminou</span>
            </div>
        </div>
        <div class="fr-kpi fv-kpi-rej">
            <div class="fr-kpi-icon"><i class="fas fa-times-circle"></i></div>
            <div class="fr-kpi-body">
                <span class="fr-kpi-val"><?php echo (int)$feriasRejeitadasCount; ?></span>
                <span class="fr-kpi-lbl">Rejeitadas</span>
                <span class="fr-kpi-pct">pedidos recusados</span>
            </div>
        </div>
    </div>

    <div class="data-table fr-table-wrap" style="margin-top:.5rem;">
        <div class="fr-toolbar">
            <div class="fr-toolbar-top">
                <div class="fr-search-wrap">
                    <i class="fas fa-search fr-search-icon"></i>
                    <input type="text" id="feriasSearchInput" class="fr-search" placeholder="Pesquisar funcionário…">
                </div>
                <div class="fr-toolbar-right">
                    <span id="feriasResultCount" style="font-size:.78rem;color:#64748b;white-space:nowrap;"></span>
                    <button type="button" class="fr-filter-toggle" id="feriasViewToggle" onclick="toggleFeriasView()">
                        <i class="fas fa-calendar-alt"></i> <span id="feriasViewToggleLabel">Calendário</span>
                    </button>
                    <button type="button" class="fr-filter-toggle" id="feriasFilterToggle"
                        onclick="document.getElementById('feriasAdvFilters').classList.toggle('fr-adv-open'); this.classList.toggle('pa-filter-open')">
                        <i class="fas fa-sliders-h"></i> Filtros
                        <span class="fr-filter-badge" id="feriasFilterBadge" style="display:none"></span>
                    </button>
                </div>
            </div>

            <div class="fr-chips">
                <button class="fr-chip fv-chip-all active" data-fv-chip="">
                    <i class="fas fa-th-large"></i> Todos
                    <span class="fr-chip-count"><?php echo count($feriasAll); ?></span>
                </button>
                <?php if ($feriasPendentesCount > 0): ?>
                <button class="fr-chip fv-chip-pending" data-fv-chip="pendente">
                    <span class="fr-dot" style="background:#f59e0b;"></span> Pendentes
                    <span class="fr-chip-count"><?php echo (int)$feriasPendentesCount; ?></span>
                </button>
                <?php endif; ?>
                <button class="fr-chip fv-chip-sched" data-fv-chip="agendada">
                    <span class="fr-dot fr-dot-blue"></span> Agendadas
                    <span class="fr-chip-count"><?php echo (int)$feriasAgendadasCount; ?></span>
                </button>
                <button class="fr-chip fv-chip-active" data-fv-chip="em_curso">
                    <span class="fr-dot fr-dot-green"></span> Em curso
                    <span class="fr-chip-count"><?php echo (int)$feriasEmCursoCount; ?></span>
                </button>
                <button class="fr-chip fv-chip-done" data-fv-chip="terminada">
                    <span class="fr-dot" style="background:#64748b;"></span> Concluídas
                    <span class="fr-chip-count"><?php echo (int)$feriasConcluidasCount; ?></span>
                </button>
                <button class="fr-chip fv-chip-rej" data-fv-chip="rejeitada">
                    <span class="fr-dot fr-dot-red"></span> Rejeitadas
                    <span class="fr-chip-count"><?php echo (int)$feriasRejeitadasCount; ?></span>
                </button>
            </div>

            <div class="fr-adv-filters" id="feriasAdvFilters">
                <select id="feriasStatusFilter" class="fr-select" style="min-width:170px;">
                    <option value="">Todos os estados</option>
                    <option value="pendente">Pendente</option>
                    <option value="agendada">Agendada</option>
                    <option value="em_curso">Em curso</option>
                    <option value="terminada">Concluída</option>
                    <option value="rejeitada">Rejeitada</option>
                    <option value="cancelada">Cancelada</option>
                </select>
                <input type="date" id="feriasDateFrom" class="fr-select" style="min-width:148px;" title="Data início (de)" />
                <input type="date" id="feriasDateTo" class="fr-select" style="min-width:148px;" title="Data início (até)" />
                <button type="button" class="fr-clear-btn" id="clearFiltersFerias"><i class="fas fa-times"></i> Limpar</button>
            </div>
        </div>

        <style>
            .fv-chip-all.active    { background:rgba(14,165,233,.2); color:#38bdf8; border-color:rgba(14,165,233,.35); }
            .fv-chip-pending.active{ background:rgba(245,158,11,.2); color:#fbbf24; border-color:rgba(245,158,11,.35); }
            .fv-chip-sched.active  { background:rgba(59,130,246,.2); color:#60a5fa; border-color:rgba(59,130,246,.35); }
            .fv-chip-active.active { background:rgba(16,185,129,.2); color:#34d399; border-color:rgba(16,185,129,.35); }
            .fv-chip-done.active   { background:rgba(100,116,139,.18); color:#94a3b8; border-color:rgba(100,116,139,.3); }
            .fv-chip-rej.active    { background:rgba(239,68,68,.2); color:#f87171; border-color:rgba(239,68,68,.35); }
        </style>

        <?php
            // Dados leves para a vista de calendário (reaproveitados no cliente, sem nova consulta).
            $feriasCalendarData = [];
            foreach ($feriasAll as $fcCal) {
                $fcStatusRaw = mb_strtolower(trim((string)($fcCal['status'] ?? 'pendente')));
                if ($fcStatusRaw === 'aprovado') $fcStatusRaw = 'aprovada';
                $fcIni = (string)($fcCal['data_inicio'] ?? '');
                $fcFim = (string)($fcCal['data_fim'] ?? '');
                $fcKey = 'pendente';
                if ($fcStatusRaw === 'aprovada') {
                    if ($fcIni !== '' && $todayDate < $fcIni) { $fcKey = 'agendada'; }
                    elseif ($fcFim !== '' && $todayDate > $fcFim) { $fcKey = 'terminada'; }
                    else { $fcKey = 'em_curso'; }
                } elseif ($fcStatusRaw === 'rejeitada') { $fcKey = 'rejeitada'; }
                elseif ($fcStatusRaw === 'cancelada') { $fcKey = 'cancelada'; }
                if ($fcIni === '' || $fcFim === '') continue;
                $feriasCalendarData[] = [
                    'nome' => (string)($fcCal['employee_name'] ?? 'Funcionário'),
                    'inicio' => $fcIni,
                    'fim' => $fcFim,
                    'estado' => $fcKey,
                    'conflito' => (int)($fcCal['conflito_count'] ?? 0) > 0,
                ];
            }
        ?>
        <div id="feriasCalendarView" style="display:none;">
            <div class="fv-cal-header">
                <button type="button" class="fr-clear-btn" onclick="feriasCalNav(-1)"><i class="fas fa-chevron-left"></i></button>
                <span id="feriasCalLabel" class="fv-cal-label"></span>
                <button type="button" class="fr-clear-btn" onclick="feriasCalNav(1)"><i class="fas fa-chevron-right"></i></button>
                <button type="button" class="fr-clear-btn" onclick="feriasCalNav(0)" style="margin-left:auto;">Hoje</button>
            </div>
            <div id="feriasCalGrid" class="fv-cal-grid"></div>
            <div class="fv-cal-legend">
                <span><i class="fr-dot" style="background:#fbbf24;"></i> Pendente</span>
                <span><i class="fr-dot fr-dot-blue"></i> Agendada</span>
                <span><i class="fr-dot fr-dot-green"></i> Em curso</span>
                <span><i class="fr-dot" style="background:#64748b;"></i> Concluída</span>
                <span><i class="fas fa-exclamation-triangle" style="color:#f87171;"></i> Conflito de equipa</span>
            </div>
        </div>
        <style>
            .fv-cal-header { display:flex; align-items:center; gap:.6rem; margin-bottom:.75rem; }
            .fv-cal-label { font-weight:700; font-size:.95rem; color:var(--text-primary,#f1f5f9); min-width:160px; text-align:center; }
            .fv-cal-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:4px; }
            .fv-cal-dow { font-size:.7rem; font-weight:700; color:#64748b; text-align:center; padding:.3rem 0; text-transform:uppercase; }
            .fv-cal-day { min-height:84px; border-radius:8px; background:var(--card-bg,#1e293b); border:1px solid var(--border-color,rgba(255,255,255,.07)); padding:.3rem; display:flex; flex-direction:column; gap:2px; }
            .fv-cal-day.fv-cal-outside { opacity:.35; }
            .fv-cal-day.fv-cal-today { border-color:#0ea5e9; box-shadow:0 0 0 1px #0ea5e9 inset; }
            .fv-cal-daynum { font-size:.72rem; color:#94a3b8; font-weight:600; }
            .fv-cal-bar { font-size:.64rem; padding:1px 5px; border-radius:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; color:#0f172a; font-weight:600; }
            .fv-cal-bar-pendente { background:#fbbf24; }
            .fv-cal-bar-agendada { background:#60a5fa; }
            .fv-cal-bar-em_curso { background:#34d399; }
            .fv-cal-bar-terminada { background:#94a3b8; }
            .fv-cal-bar-rejeitada,.fv-cal-bar-cancelada { display:none; }
            .fv-cal-legend { display:flex; gap:1rem; flex-wrap:wrap; margin-top:.75rem; font-size:.74rem; color:#94a3b8; }
            .fv-cal-legend span { display:flex; align-items:center; gap:5px; }
        </style>
        <table class="table fr-table" id="feriasSectionTable">
            <thead>
                <tr class="fr-thead-row">
                    <th class="fr-th-emp">Funcionário</th>
                    <th>Início</th>
                    <th>Fim</th>
                    <th>Duração</th>
                    <th class="fr-th-status">Estado</th>
                    <th class="fr-th-acts">Ações</th>
                </tr>
            </thead>
            <tbody id="feriasSectionTableBody">
                <?php if (empty($feriasAll)): ?>
                <tr id="ferias-empty-state">
                    <td colspan="6" style="text-align:center;color:var(--text-secondary);padding:3rem 1rem;">
                        <i class="fas fa-umbrella-beach" style="font-size:2.5rem;opacity:.2;display:block;margin-bottom:.75rem;"></i>
                        Ainda não existem registos de férias.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($feriasAll as $fRow):
                    $fStatusRaw = mb_strtolower(trim((string)($fRow['status'] ?? 'pendente')));
                    if ($fStatusRaw === 'aprovado') $fStatusRaw = 'aprovada';

                    $fInicioIso = (string)($fRow['data_inicio'] ?? '');
                    $fFimIso    = (string)($fRow['data_fim'] ?? '');
                    $fInicioFmt = $fInicioIso !== '' ? date('d/m/Y', strtotime($fInicioIso)) : 'N/D';
                    $fFimFmt    = $fFimIso    !== '' ? date('d/m/Y', strtotime($fFimIso))    : 'N/D';

                    $fStatusLabel = 'Pendente';
                    $fStatusClass = 'status-warning';
                    $fStatusFilterKey = 'pendente';
                    if ($fStatusRaw === 'aprovada') {
                        if ($fInicioIso !== '' && $todayDate < $fInicioIso) {
                            $fStatusLabel = 'Agendada';  $fStatusClass = 'status-atrasado';    $fStatusFilterKey = 'agendada';
                        } elseif ($fFimIso !== '' && $todayDate > $fFimIso) {
                            $fStatusLabel = 'Concluída'; $fStatusClass = 'status-nao-marcado'; $fStatusFilterKey = 'terminada';
                        } else {
                            $fStatusLabel = 'Em curso';  $fStatusClass = 'status-presente';    $fStatusFilterKey = 'em_curso';
                        }
                    } elseif ($fStatusRaw === 'rejeitada') {
                        $fStatusLabel = 'Rejeitada'; $fStatusClass = 'status-falta';    $fStatusFilterKey = 'rejeitada';
                    } elseif ($fStatusRaw === 'cancelada') {
                        $fStatusLabel = 'Cancelada'; $fStatusClass = 'status-inactive'; $fStatusFilterKey = 'cancelada';
                    }

                    $duracaoLabel = '-';
                    if ($fInicioIso !== '' && $fFimIso !== '') {
                        try {
                            $d1 = new DateTime($fInicioIso); $d2 = new DateTime($fFimIso);
                            if ($d2 >= $d1) { $dias = (int)$d1->diff($d2)->days + 1; $duracaoLabel = $dias . ' dia' . ($dias > 1 ? 's' : ''); }
                        } catch (Throwable $e) {}
                    }

                    $employeeName   = (string)($fRow['employee_name'] ?? 'Funcionário');
                    $employeePic    = (string)($fRow['employee_profile_picture'] ?? '');
                    $motivoTooltip  = trim((string)($fRow['motivo'] ?? ''));
                    $motivoRejeicao = trim((string)($fRow['motivo_rejeicao'] ?? ''));
                    $feriasIdRow    = (int)($fRow['id'] ?? 0);
                    $empInitials    = strtoupper(mb_substr($employeeName, 0, 2));
                    $conflitoCount  = (int)($fRow['conflito_count'] ?? 0);
                    $conflitoTitle  = $conflitoCount > 0
                        ? ($conflitoCount . ' colega(s) da mesma função com férias a sobrepor-se a este período')
                        : '';
                ?>
                <tr class="fr-row"
                    data-ferias-nome="<?php echo htmlspecialchars(mb_strtolower($employeeName)); ?>"
                    data-ferias-status="<?php echo htmlspecialchars($fStatusFilterKey); ?>"
                    data-ferias-inicio="<?php echo htmlspecialchars($fInicioIso); ?>"
                    data-ferias-fim="<?php echo htmlspecialchars($fFimIso); ?>">
                    <td class="fr-td-emp">
                        <div class="fr-emp-cell">
                            <div class="fr-av" style="background:linear-gradient(135deg,#667eea,#764ba2);border-radius:12px;">
                                <?php if ($employeePic !== ''): ?>
                                <img class="fr-av-img" src="../<?php echo htmlspecialchars($employeePic); ?>" alt=""
                                    onerror="this.parentElement.textContent='<?php echo $empInitials; ?>'; this.remove();">
                                <?php else: echo $empInitials; endif; ?>
                            </div>
                            <div class="fr-emp-info">
                                <span class="fr-emp-name"><?php echo htmlspecialchars($employeeName); ?></span>
                                <span class="fr-emp-email"><?php echo htmlspecialchars($duracaoLabel !== '-' ? $duracaoLabel : '—'); ?></span>
                            </div>
                        </div>
                    </td>
                    <td class="fr-td-role"><?php echo htmlspecialchars($fInicioFmt); ?></td>
                    <td class="fr-td-role"><?php echo htmlspecialchars($fFimFmt); ?></td>
                    <td class="fr-td-role"><?php echo htmlspecialchars($duracaoLabel); ?></td>
                    <td class="fr-td-status">
                        <span class="status-badge <?php echo $fStatusClass; ?>"><?php echo htmlspecialchars($fStatusLabel); ?></span>
                        <?php if ($conflitoCount > 0): ?>
                        <span class="status-badge status-falta" title="<?php echo htmlspecialchars($conflitoTitle); ?>" style="margin-left:4px;">
                            <i class="fas fa-exclamation-triangle"></i> Conflito
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="fr-td-acts">
                        <div class="fr-acts">
                            <button type="button" class="fr-btn fr-btn-view" title="Ver detalhes"
                                data-ferias-id="<?php echo $feriasIdRow; ?>"
                                data-ferias-funcionario="<?php echo htmlspecialchars($employeeName); ?>"
                                data-ferias-inicio="<?php echo htmlspecialchars($fInicioFmt); ?>"
                                data-ferias-fim="<?php echo htmlspecialchars($fFimFmt); ?>"
                                data-ferias-duracao="<?php echo htmlspecialchars($duracaoLabel); ?>"
                                data-ferias-status-label="<?php echo htmlspecialchars($fStatusLabel); ?>"
                                data-ferias-motivo="<?php echo htmlspecialchars($motivoTooltip); ?>"
                                data-ferias-motivo-rejeicao="<?php echo htmlspecialchars($motivoRejeicao); ?>"
                                onclick="openFeriasViewModal(this)"><i class="fas fa-eye"></i></button>

                            <?php if ($fStatusRaw === 'pendente'): ?>
                            <form method="POST" style="display:contents;" class="ferias-approve-form">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="action" value="approve_ferias_request">
                                <input type="hidden" name="ferias_id" value="<?php echo $feriasIdRow; ?>">
                                <input type="hidden" name="from_section" value="ferias">
                                <button type="submit" class="fr-btn fr-btn-activate" title="Aprovar"><i class="fas fa-check"></i></button>
                            </form>
                            <button type="button" class="fr-btn fr-btn-deact" title="Rejeitar"
                                onclick="openFeriasRejectModal(<?php echo $feriasIdRow; ?>, '<?php echo htmlspecialchars(addslashes($employeeName)); ?>')">
                                <i class="fas fa-times"></i>
                            </button>
                            <?php elseif ($fStatusFilterKey === 'agendada'): ?>
                            <button type="button" class="fr-btn fr-btn-edit" title="Editar"
                                data-ferias-id="<?php echo $feriasIdRow; ?>"
                                data-ferias-funcionario="<?php echo htmlspecialchars($employeeName); ?>"
                                data-ferias-inicio-iso="<?php echo htmlspecialchars($fInicioIso); ?>"
                                data-ferias-fim-iso="<?php echo htmlspecialchars($fFimIso); ?>"
                                data-ferias-motivo="<?php echo htmlspecialchars($motivoTooltip); ?>"
                                onclick="openFeriasEditModal(this)"><i class="fas fa-edit"></i></button>
                            <form method="POST" style="display:contents;" class="ferias-cancel-form"
                                data-confirm-message="Cancelar estas férias agendadas?">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="action" value="cancel_ferias_admin">
                                <input type="hidden" name="ferias_id" value="<?php echo $feriasIdRow; ?>">
                                <button type="submit" class="fr-btn fr-btn-deact" title="Cancelar"><i class="fas fa-ban"></i></button>
                            </form>
                            <?php elseif ($fStatusFilterKey === 'em_curso'): ?>
                            <?php $canCancelInCourse = ($fInicioIso !== '' && $todayDate === $fInicioIso); ?>
                            <form method="POST" style="display:contents;" class="ferias-cancel-form"
                                data-confirm-message="Cancelar férias em curso? Só permitido no primeiro dia.">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="action" value="cancel_ferias_admin">
                                <input type="hidden" name="ferias_id" value="<?php echo $feriasIdRow; ?>">
                                <button type="submit" class="fr-btn fr-btn-deact <?php echo $canCancelInCourse ? '' : 'fr-btn-off'; ?>"
                                    title="<?php echo $canCancelInCourse ? 'Cancelar' : 'Só pode cancelar no primeiro dia'; ?>"
                                    <?php echo $canCancelInCourse ? '' : 'disabled'; ?>><i class="fas fa-ban"></i></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>


    <!-- ── Férias: Modal Ver ─────────────────────────── -->
    <div id="feriasViewModal" class="modal" style="display:none;overflow-y:auto;padding:24px 16px 48px;">
        <div class="am-sheet vm-sheet" style="max-width:560px;">
            <button class="am-close" type="button" onclick="closeFeriasViewModal()" aria-label="Fechar">&times;</button>

            <div class="vm-hero">
                <div class="vm-hero-av" id="fvmAvatar"><i class="fas fa-umbrella-beach"></i></div>
                <div class="vm-hero-info">
                    <h2 class="vm-hero-name" id="fvmName"></h2>
                    <p class="vm-hero-pos" id="fvmPeriod" style="margin:0 0 8px;"></p>
                    <div id="fvmStatusBadge"></div>
                </div>
            </div>

            <div class="vm-section">
                <div class="vm-sec-lbl"><i class="fas fa-calendar-alt"></i> Período</div>
                <div class="vm-g2">
                    <div>
                        <div class="vm-field-label">Data Início</div>
                        <div class="vm-field-value" id="fvmInicio"></div>
                    </div>
                    <div>
                        <div class="vm-field-label">Data Fim</div>
                        <div class="vm-field-value" id="fvmFim"></div>
                    </div>
                    <div>
                        <div class="vm-field-label">Duração</div>
                        <div class="vm-field-value" id="fvmDuracao"></div>
                    </div>
                    <div>
                        <div class="vm-field-label">Estado</div>
                        <div class="vm-field-value" id="fvmEstado"></div>
                    </div>
                </div>
            </div>

            <div class="vm-section" id="fvmMotivoSection">
                <div class="vm-sec-lbl"><i class="fas fa-comment-alt"></i> Motivo</div>
                <div class="vm-field-value" id="fvmMotivo" style="color:#cbd5e1;line-height:1.55;"></div>
            </div>

            <div id="fvmRejeicaoSection" style="display:none;margin-bottom:18px;">
                <div class="vm-sec-lbl"><i class="fas fa-ban" style="color:#ef4444;"></i> <span style="color:#f87171;">Motivo da Rejeição</span></div>
                <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:10px;padding:12px 14px;color:#fca5a5;font-size:.87rem;line-height:1.55;" id="fvmMotRejeicao"></div>
            </div>

            <div class="am-footer">
                <button type="button" class="am-btn-cancel" onclick="closeFeriasViewModal()">Fechar</button>
            </div>
        </div>
    </div>

    <!-- ── Férias: Modal Criar ────────────────────────── -->
    <div id="feriasCreateModal" class="modal" style="display:none;overflow-y:auto;padding:24px 16px 48px;">
        <div class="am-sheet" style="max-width:580px;">
            <button class="am-close" type="button" onclick="closeFeriasCreateModal()" aria-label="Fechar">&times;</button>

            <div class="am-header">
                <div class="am-header-icon" style="background:linear-gradient(135deg,#10b981,#059669);box-shadow:0 6px 16px rgba(5,150,105,.35);">
                    <i class="fas fa-umbrella-beach"></i>
                </div>
                <div>
                    <h3 class="am-title">Nova Marcação de Férias</h3>
                    <p class="am-subtitle">Aprovação direta pelo administrador</p>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="create_ferias_admin">

                <div class="am-section">
                    <div class="am-sec-lbl"><i class="fas fa-user"></i> Funcionário</div>
                    <div class="am-f">
                        <label class="am-lbl" for="feriasCreateEmployee">Selecione o funcionário <span style="color:#ef4444;">*</span></label>
                        <select id="feriasCreateEmployee" name="employee_id" class="am-inp am-sel" required>
                            <option value="">Selecione um funcionário...</option>
                            <?php foreach ($employees as $emp):
                                $empStatusRaw = mb_strtolower(trim((string)($emp['status'] ?? '')));
                                if (in_array($empStatusRaw, ['inativo', 'inactive'], true)) continue;
                            ?>
                            <option value="<?php echo (int)($emp['id'] ?? 0); ?>"><?php echo htmlspecialchars((string)($emp['name'] ?? 'Funcionário')); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="am-section">
                    <div class="am-sec-lbl"><i class="fas fa-calendar-alt"></i> Período</div>
                    <div class="am-g2">
                        <div class="am-f">
                            <label class="am-lbl" for="feriasCreateInicio">Data Início <span style="color:#ef4444;">*</span></label>
                            <input id="feriasCreateInicio" name="data_inicio" type="date" class="am-inp" required>
                        </div>
                        <div class="am-f">
                            <label class="am-lbl" for="feriasCreateFim">Data Fim <span style="color:#ef4444;">*</span></label>
                            <input id="feriasCreateFim" name="data_fim" type="date" class="am-inp" required>
                        </div>
                    </div>
                    <div style="margin-top:8px;padding:8px 12px;border-radius:8px;background:rgba(16,185,129,.07);border:1px solid rgba(16,185,129,.18);font-size:.78rem;color:#6ee7b7;" id="feriasCreateDuracaoPreview" style="display:none;"></div>
                </div>

                <div class="am-section">
                    <div class="am-sec-lbl"><i class="fas fa-comment-alt"></i> Observações</div>
                    <div class="am-f">
                        <label class="am-lbl" for="feriasCreateMotivo">Motivo / Notas <span class="am-opt">(opcional)</span></label>
                        <textarea id="feriasCreateMotivo" name="motivo" rows="3" class="am-inp" style="resize:vertical;" placeholder="Notas internas ou justificação…"></textarea>
                    </div>
                    <label style="display:flex;align-items:center;gap:8px;margin-top:10px;font-size:.82rem;color:#cbd5e1;cursor:pointer;">
                        <input type="checkbox" name="ignorar_saldo" value="1" style="width:16px;height:16px;">
                        Ignorar saldo de dias disponível (exceção / abono especial)
                    </label>
                </div>

                <div class="am-footer">
                    <button type="button" class="am-btn-cancel" onclick="closeFeriasCreateModal()">Cancelar</button>
                    <button type="submit" class="am-btn-submit" style="background:linear-gradient(135deg,#10b981,#059669);box-shadow:0 4px 14px rgba(5,150,105,.3);">
                        <i class="fas fa-check"></i> Criar e Aprovar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Férias: Modal Editar ───────────────────────── -->
    <div id="feriasEditModal" class="modal" style="display:none;overflow-y:auto;padding:24px 16px 48px;">
        <div class="am-sheet" style="max-width:540px;">
            <button class="am-close" type="button" onclick="closeFeriasEditModal()" aria-label="Fechar">&times;</button>

            <div class="am-header">
                <div class="am-header-icon">
                    <i class="fas fa-edit"></i>
                </div>
                <div>
                    <h3 class="am-title">Editar Férias</h3>
                    <p class="am-subtitle" id="feriasEditEmployee">—</p>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="edit_ferias_admin">
                <input type="hidden" name="ferias_id" id="feriasEditId" value="">

                <div class="am-section">
                    <div class="am-sec-lbl"><i class="fas fa-calendar-alt"></i> Período</div>
                    <div class="am-g2">
                        <div class="am-f">
                            <label class="am-lbl" for="feriasEditInicio">Data Início <span style="color:#ef4444;">*</span></label>
                            <input id="feriasEditInicio" name="data_inicio" type="date" class="am-inp" required>
                        </div>
                        <div class="am-f">
                            <label class="am-lbl" for="feriasEditFim">Data Fim <span style="color:#ef4444;">*</span></label>
                            <input id="feriasEditFim" name="data_fim" type="date" class="am-inp" required>
                        </div>
                    </div>
                </div>

                <div class="am-section">
                    <div class="am-sec-lbl"><i class="fas fa-comment-alt"></i> Observações</div>
                    <div class="am-f">
                        <label class="am-lbl" for="feriasEditMotivo">Motivo / Notas <span class="am-opt">(opcional)</span></label>
                        <textarea id="feriasEditMotivo" name="motivo" rows="3" class="am-inp" style="resize:vertical;"></textarea>
                    </div>
                </div>

                <div class="am-footer">
                    <button type="button" class="am-btn-cancel" onclick="closeFeriasEditModal()">Cancelar</button>
                    <button type="submit" class="am-btn-submit"><i class="fas fa-save"></i> Guardar Alterações</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Férias: Modal Rejeitar ─────────────────────── -->
    <div id="feriasRejectModal" class="modal" style="display:none;overflow-y:auto;padding:24px 16px 48px;">
        <div class="am-sheet" style="max-width:500px;">
            <button class="am-close" type="button" onclick="closeFeriasRejectModal()" aria-label="Fechar">&times;</button>

            <div class="am-header">
                <div class="am-header-icon" style="background:linear-gradient(135deg,#dc2626,#b91c1c);box-shadow:0 6px 16px rgba(185,28,28,.35);">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div>
                    <h3 class="am-title">Rejeitar Pedido de Férias</h3>
                    <p class="am-subtitle" id="feriasRejectModalEmployee">—</p>
                </div>
            </div>

            <form method="POST" id="feriasRejectForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="reject_ferias_request">
                <input type="hidden" name="ferias_id" id="feriasRejectId" value="">
                <input type="hidden" name="from_section" value="ferias">

                <div class="am-section">
                    <div class="am-sec-lbl"><i class="fas fa-comment-alt" style="color:#ef4444;"></i> Motivo da Rejeição</div>
                    <div class="am-f">
                        <label class="am-lbl" for="feriasRejectMotivo">Indique o motivo <span style="color:#ef4444;">*</span></label>
                        <textarea id="feriasRejectMotivo" name="motivo_rejeicao" rows="4" class="am-inp" style="resize:vertical;" placeholder="O pedido é rejeitado porque…"></textarea>
                        <span class="am-hint">Este motivo será comunicado ao funcionário.</span>
                    </div>
                </div>

                <div class="am-footer">
                    <button type="button" class="am-btn-cancel" onclick="closeFeriasRejectModal()">Cancelar</button>
                    <button type="submit" class="am-btn-submit" style="background:linear-gradient(135deg,#dc2626,#b91c1c);box-shadow:0 4px 14px rgba(185,28,28,.3);">
                        <i class="fas fa-times"></i> Confirmar Rejeição
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    var feriasCalendarData = <?php echo json_encode($feriasCalendarData, JSON_UNESCAPED_UNICODE); ?>;
    var feriasCalCursor = new Date();
    feriasCalCursor.setDate(1);

    function toggleFeriasView() {
        var table = document.getElementById('feriasSectionTable');
        var cal = document.getElementById('feriasCalendarView');
        var label = document.getElementById('feriasViewToggleLabel');
        if (!table || !cal) return;
        var showingCalendar = cal.style.display !== 'none';
        if (showingCalendar) {
            cal.style.display = 'none';
            table.style.display = '';
            if (label) label.textContent = 'Calendário';
        } else {
            cal.style.display = '';
            table.style.display = 'none';
            if (label) label.textContent = 'Tabela';
            renderFeriasCalendar();
        }
    }

    function feriasCalNav(direction) {
        if (direction === 0) {
            feriasCalCursor = new Date();
        } else {
            feriasCalCursor.setMonth(feriasCalCursor.getMonth() + direction);
        }
        feriasCalCursor.setDate(1);
        renderFeriasCalendar();
    }

    function renderFeriasCalendar() {
        var grid = document.getElementById('feriasCalGrid');
        var labelEl = document.getElementById('feriasCalLabel');
        if (!grid || !labelEl) return;

        var year = feriasCalCursor.getFullYear();
        var month = feriasCalCursor.getMonth();
        var monthNames = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
        labelEl.textContent = monthNames[month] + ' de ' + year;

        var firstOfMonth = new Date(year, month, 1);
        var startOffset = (firstOfMonth.getDay() + 6) % 7; // semana começa à segunda-feira
        var gridStart = new Date(year, month, 1 - startOffset);
        var todayIso = new Date().toISOString().slice(0, 10);

        var dowLabels = ['Seg','Ter','Qua','Qui','Sex','Sáb','Dom'];
        var html = dowLabels.map(function(d) { return '<div class="fv-cal-dow">' + d + '</div>'; }).join('');

        for (var i = 0; i < 42; i++) {
            var d = new Date(gridStart);
            d.setDate(gridStart.getDate() + i);
            var dIso = d.toISOString().slice(0, 10);
            var outside = d.getMonth() !== month;
            var isToday = dIso === todayIso;

            var dayItems = feriasCalendarData.filter(function(f) {
                return f.inicio <= dIso && f.fim >= dIso;
            });

            var bars = dayItems.slice(0, 3).map(function(f) {
                var icon = f.conflito ? '<i class="fas fa-exclamation-triangle"></i> ' : '';
                return '<div class="fv-cal-bar fv-cal-bar-' + f.estado + '" title="' + escapeHtmlFerias(f.nome) + '">' + icon + escapeHtmlFerias(f.nome) + '</div>';
            }).join('');
            var more = dayItems.length > 3 ? '<div class="fv-cal-bar" style="background:transparent;color:#64748b;">+' + (dayItems.length - 3) + '</div>' : '';

            html += '<div class="fv-cal-day' + (outside ? ' fv-cal-outside' : '') + (isToday ? ' fv-cal-today' : '') + '">'
                + '<span class="fv-cal-daynum">' + d.getDate() + '</span>'
                + bars + more
                + '</div>';
        }

        grid.innerHTML = html;
    }

    function escapeHtmlFerias(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function openFeriasViewModal(btn) {
        var modal = document.getElementById('feriasViewModal');
        if (!modal || !btn) return;

        var funcionario    = btn.getAttribute('data-ferias-funcionario') || 'Funcionário';
        var inicio         = btn.getAttribute('data-ferias-inicio') || 'N/D';
        var fim            = btn.getAttribute('data-ferias-fim') || 'N/D';
        var duracao        = btn.getAttribute('data-ferias-duracao') || '-';
        var statusLabel    = btn.getAttribute('data-ferias-status-label') || '-';
        var motivo         = btn.getAttribute('data-ferias-motivo') || '';
        var motivoRejeicao = btn.getAttribute('data-ferias-motivo-rejeicao') || '';

        var initials = funcionario.replace(/\s+/g,'').substring(0,2).toUpperCase();
        var avEl = document.getElementById('fvmAvatar');
        if (avEl) avEl.innerHTML = '<span style="font-size:1.4rem;font-weight:700;">' + escapeHtmlFerias(initials) + '</span>';

        var setTxt = function(id, val) { var el = document.getElementById(id); if (el) el.textContent = val; };
        setTxt('fvmName',    funcionario);
        setTxt('fvmPeriod',  inicio + ' — ' + fim);
        setTxt('fvmInicio',  inicio);
        setTxt('fvmFim',     fim);
        setTxt('fvmDuracao', duracao);
        setTxt('fvmEstado',  statusLabel);

        var badgeEl = document.getElementById('fvmStatusBadge');
        if (badgeEl) {
            var cls = 'status-warning';
            var sl = statusLabel.toLowerCase();
            if (sl === 'em curso') cls = 'status-presente';
            else if (sl === 'concluída' || sl === 'concluida') cls = 'status-nao-marcado';
            else if (sl === 'rejeitada') cls = 'status-falta';
            else if (sl === 'cancelada') cls = 'status-inactive';
            else if (sl === 'agendada') cls = 'status-atrasado';
            badgeEl.innerHTML = '<span class="status-badge ' + cls + '">' + escapeHtmlFerias(statusLabel) + '</span>';
        }

        var motivoSection = document.getElementById('fvmMotivoSection');
        var motivoEl = document.getElementById('fvmMotivo');
        if (motivoSection && motivoEl) {
            if (motivo) { motivoEl.textContent = motivo; motivoSection.style.display = ''; }
            else { motivoSection.style.display = 'none'; }
        }

        var rejSection = document.getElementById('fvmRejeicaoSection');
        var rejEl = document.getElementById('fvmMotRejeicao');
        if (rejSection && rejEl) {
            if (motivoRejeicao) { rejEl.textContent = motivoRejeicao; rejSection.style.display = ''; }
            else { rejSection.style.display = 'none'; }
        }

        modal.style.display = 'block';
    }

    function closeFeriasViewModal() {
        var modal = document.getElementById('feriasViewModal');
        if (modal) modal.style.display = 'none';
    }

    function openFeriasRejectModal(feriasId, funcionarioName) {
        var modal = document.getElementById('feriasRejectModal');
        if (!modal) return;
        var idEl = document.getElementById('feriasRejectId');
        var empEl = document.getElementById('feriasRejectModalEmployee');
        var motivoEl = document.getElementById('feriasRejectMotivo');
        if (idEl) idEl.value = feriasId;
        if (empEl) empEl.textContent = 'Funcionário: ' + (funcionarioName || '');
        if (motivoEl) motivoEl.value = '';
        modal.style.display = 'block';
    }

    function closeFeriasRejectModal() {
        var modal = document.getElementById('feriasRejectModal');
        if (modal) modal.style.display = 'none';
    }

    function feriasDuracaoLabel(inicioVal, fimVal) {
        if (!inicioVal || !fimVal) return '';
        var d1 = new Date(inicioVal), d2 = new Date(fimVal);
        if (isNaN(d1) || isNaN(d2) || d2 < d1) return '';
        var dias = Math.round((d2 - d1) / 86400000) + 1;
        return '<i class="fas fa-info-circle"></i> ' + dias + ' dia' + (dias > 1 ? 's' : '') + ' de férias';
    }

    function openFeriasCreateModal() {
        var modal = document.getElementById('feriasCreateModal');
        if (!modal) return;
        var employeeEl = document.getElementById('feriasCreateEmployee');
        var inicioEl   = document.getElementById('feriasCreateInicio');
        var fimEl      = document.getElementById('feriasCreateFim');
        var motivoEl   = document.getElementById('feriasCreateMotivo');
        var previewEl  = document.getElementById('feriasCreateDuracaoPreview');
        if (employeeEl) employeeEl.value = '';
        if (inicioEl)   inicioEl.value   = '';
        if (fimEl)      fimEl.value      = '';
        if (motivoEl)   motivoEl.value   = '';
        if (previewEl)  { previewEl.innerHTML = ''; previewEl.style.display = 'none'; }
        modal.style.display = 'block';
    }

    function closeFeriasCreateModal() {
        var modal = document.getElementById('feriasCreateModal');
        if (modal) modal.style.display = 'none';
    }

    function openFeriasEditModal(btn) {
        var modal = document.getElementById('feriasEditModal');
        if (!modal || !btn) return;
        var idEl       = document.getElementById('feriasEditId');
        var inicioEl   = document.getElementById('feriasEditInicio');
        var fimEl      = document.getElementById('feriasEditFim');
        var motivoEl   = document.getElementById('feriasEditMotivo');
        var subtitleEl = document.getElementById('feriasEditEmployee');
        if (idEl)       idEl.value       = btn.getAttribute('data-ferias-id')        || '';
        if (inicioEl)   inicioEl.value   = btn.getAttribute('data-ferias-inicio-iso') || '';
        if (fimEl)      fimEl.value      = btn.getAttribute('data-ferias-fim-iso')   || '';
        if (motivoEl)   motivoEl.value   = btn.getAttribute('data-ferias-motivo')    || '';
        if (subtitleEl) subtitleEl.textContent = btn.getAttribute('data-ferias-funcionario') || '';
        modal.style.display = 'block';
    }

    function closeFeriasEditModal() {
        var modal = document.getElementById('feriasEditModal');
        if (modal) modal.style.display = 'none';
    }

    (function() {
        var ini = document.getElementById('feriasCreateInicio');
        var fim = document.getElementById('feriasCreateFim');
        var preview = document.getElementById('feriasCreateDuracaoPreview');
        function updatePreview() {
            if (!preview) return;
            var label = feriasDuracaoLabel(ini && ini.value, fim && fim.value);
            if (label) { preview.innerHTML = label; preview.style.display = ''; }
            else { preview.innerHTML = ''; preview.style.display = 'none'; }
        }
        if (ini) ini.addEventListener('change', updatePreview);
        if (fim) fim.addEventListener('change', updatePreview);
    })();

    (function initFeriasCancelSweetAlert() {
        var forms = document.querySelectorAll('#feriasSectionTable .ferias-cancel-form');
        forms.forEach(function(form) {
            form.addEventListener('submit', function(event) {
                event.preventDefault();

                var submitButton = form.querySelector('button[type="submit"]');
                if (submitButton && submitButton.disabled) {
                    return;
                }

                var confirmMessage = form.getAttribute('data-confirm-message') || 'Confirmar cancelamento destas férias?';

                if (typeof showConfirm === 'function') {
                    showConfirm(
                        'Confirmar cancelamento',
                        confirmMessage,
                        'Sim, cancelar',
                        'Cancelar'
                    ).then(function(result) {
                        if (result && result.isConfirmed) {
                            form.submit();
                        }
                    });
                    return;
                }

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Confirmar cancelamento',
                        text: confirmMessage,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc2626',
                        cancelButtonColor: '#6b7280',
                        confirmButtonText: 'Sim, cancelar',
                        cancelButtonText: 'Cancelar'
                    }).then(function(result) {
                        if (result && result.isConfirmed) {
                            form.submit();
                        }
                    });
                    return;
                }

                if (window.confirm(confirmMessage)) {
                    form.submit();
                }
            });
        });
    })();

    window.addEventListener('click', function(event) {
        var viewModal = document.getElementById('feriasViewModal');
        var createModal = document.getElementById('feriasCreateModal');
        var editModal = document.getElementById('feriasEditModal');
        var rejectModal = document.getElementById('feriasRejectModal');
        if (event.target === viewModal) closeFeriasViewModal();
        if (event.target === createModal) closeFeriasCreateModal();
        if (event.target === editModal) closeFeriasEditModal();
        if (event.target === rejectModal) closeFeriasRejectModal();
    });

    (function() {
        var rejectForm = document.getElementById('feriasRejectForm');
        if (!rejectForm) return;
        rejectForm.addEventListener('submit', function(e) {
            var motivoEl = document.getElementById('feriasRejectMotivo');
            if (!motivoEl || !motivoEl.value.trim()) {
                e.preventDefault();
                motivoEl && motivoEl.focus();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'warning', title: 'Motivo obrigatório', text: 'Por favor, indique o motivo da rejeição.', confirmButtonColor: '#3b82f6' });
                } else {
                    alert('Por favor, indique o motivo da rejeição.');
                }
            }
        });
    })();

    (function() {
        var searchInput = document.getElementById('feriasSearchInput');
        var statusFilter = document.getElementById('feriasStatusFilter');
        var dateFrom = document.getElementById('feriasDateFrom');
        var dateTo = document.getElementById('feriasDateTo');
        var clearBtn = document.getElementById('clearFiltersFerias');
        var resultCount = document.getElementById('feriasResultCount');
        var body = document.getElementById('feriasSectionTableBody');
        if (!searchInput || !statusFilter || !dateFrom || !dateTo || !clearBtn || !resultCount || !body) {
            return;
        }

        function updateFeriasFilterBadge() {
            var badge = document.getElementById('feriasFilterBadge');
            if (!badge) return;
            var count = [statusFilter.value, dateFrom.value, dateTo.value].filter(Boolean).length;
            if (count > 0) { badge.textContent = String(count); badge.style.display = 'flex'; }
            else { badge.style.display = 'none'; }
        }

        function applyFeriasFilters() {
            var term = (searchInput.value || '').toLowerCase().trim();
            var status = (statusFilter.value || '').toLowerCase().trim();
            var fromVal = (dateFrom.value || '').trim();
            var toVal = (dateTo.value || '').trim();
            var rows = body.querySelectorAll('tr[data-ferias-nome]');
            var visibleCount = 0;

            rows.forEach(function(row) {
                var nome = (row.getAttribute('data-ferias-nome') || '').toLowerCase();
                var rowStatus = (row.getAttribute('data-ferias-status') || '').toLowerCase();
                var rowInicio = (row.getAttribute('data-ferias-inicio') || '').trim();
                var rowFim = (row.getAttribute('data-ferias-fim') || '').trim();

                var okNome = term === '' || nome.indexOf(term) !== -1;
                var okStatus = status === '' || rowStatus === status;

                // Interseção de período: férias cujo intervalo cruza o intervalo filtrado.
                var okPeriodo = true;
                if (fromVal !== '' && rowFim !== '' && rowFim < fromVal) {
                    okPeriodo = false;
                }
                if (toVal !== '' && rowInicio !== '' && rowInicio > toVal) {
                    okPeriodo = false;
                }

                var show = okNome && okStatus && okPeriodo;
                row.style.display = show ? '' : 'none';
                if (show) {
                    visibleCount++;
                }
            });

            resultCount.textContent = visibleCount + ' resultado' + (visibleCount === 1 ? '' : 's');

            var emptyState = document.getElementById('ferias-empty-state');
            if (emptyState) {
                emptyState.style.display = visibleCount === 0 ? '' : 'none';
                if (visibleCount === 0) {
                    var cell = emptyState.querySelector('td');
                    if (cell) {
                        cell.textContent = 'Nenhum registo encontrado para os filtros aplicados.';
                    }
                }
            }

            updateFeriasFilterBadge();
        }

        function clearFeriasFilters() {
            searchInput.value = '';
            statusFilter.value = '';
            dateFrom.value = '';
            dateTo.value = '';
            applyFeriasFilters();
        }

        searchInput.addEventListener('input', applyFeriasFilters);
        statusFilter.addEventListener('change', applyFeriasFilters);
        dateFrom.addEventListener('change', applyFeriasFilters);
        dateTo.addEventListener('change', applyFeriasFilters);
        clearBtn.addEventListener('click', function() {
            clearFeriasFilters();
            setFeriasActiveChip('');
        });

        // Chips
        function setFeriasActiveChip(chipVal) {
            document.querySelectorAll('[data-fv-chip]').forEach(function(chip) {
                chip.classList.toggle('active', chip.getAttribute('data-fv-chip') === chipVal);
            });
        }

        document.querySelectorAll('[data-fv-chip]').forEach(function(chip) {
            chip.addEventListener('click', function() {
                var val = this.getAttribute('data-fv-chip');
                statusFilter.value = val;
                setFeriasActiveChip(val);
                applyFeriasFilters();
            });
        });

        applyFeriasFilters();
    })();
    </script>
</section>



        <!-- Folha de Pagamento Section -->
        <?php require $ADMIN_DIR . '/sections/folha-pagamento.php'; ?>













        <!-- Gorjetas Section -->
        <?php require $ADMIN_DIR . '/sections/gorjetas.php'; ?>












        <!-- Definições Section -->
        <?php require $ADMIN_DIR . '/sections/definicoes.php'; ?>
    </main>




<script>
document.addEventListener("DOMContentLoaded", function () {

    const modal = document.getElementById('modalAdminProfile');
    const btnClose = document.getElementById('btnCloseAdminProfileModal');
    const btnCancel = document.getElementById('btnCancelAdminProfileModal');

    function closeModal() {
        if (modal) modal.style.display = 'none';
    }

    // botão X
    if (btnClose) {
        btnClose.addEventListener('click', closeModal);
    }

    // botão cancelar
    if (btnCancel) {
        btnCancel.addEventListener('click', closeModal);
    }

    // clicar fora fecha
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModal();
            }
        });
    }

});
</script>




    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>





 <script>
                                (function() {
                                    var modal = document.getElementById('modalAdminProfile');
                                    // Seleciona o botão "Editar Perfil" do card correto
                                    var adminCard = Array.from(document.querySelectorAll('.info-card')).find(function(card) {
                                        var title = card.querySelector('.card-title');
                                        return title && title.innerText.trim() === 'Perfil do Administrador';
                                    });
                                    var btn = adminCard ? adminCard.querySelector('.btn.btn-primary') : null;

                                    function openModal() {
                                        if (modal) {
                                            modal.style.display = 'flex';
                                            window.scrollTo({
                                                top: 0,
                                                behavior: 'smooth'
                                            });
                                        }
                                    }

                                    function closeModal() {
                                        if (modal) modal.style.display = 'none';
                                    }
                                    document.getElementById('btnCloseAdminProfileModal') && document.getElementById('btnCloseAdminProfileModal').addEventListener('click', closeModal);
                                    document.getElementById('btnCancelAdminProfileModal') && document.getElementById('btnCancelAdminProfileModal').addEventListener('click', closeModal);
                                    if (modal) {
                                        modal.addEventListener('click', function(e) {
                                            if (e.target === modal) closeModal();
                                        });
                                    }
                                    if (btn) {
                                        btn.addEventListener('click', function(e) {
                                            e.preventDefault();
                                            openModal();
                                        });
                                    }
                                })();
                            </script>

<style>
    /* Relatório Tabs Styling */
    .relatorios-cards-grid .relatorio-tab {
        text-align: left;
        border: none;
        cursor: pointer;
        width: 100%;
        transition: box-shadow 0.2s, border 0.2s, background 0.2s, transform 0.18s;
    }

    .relatorios-cards-grid .relatorio-tab.active {
        background: linear-gradient(120deg, #2563eb 0%, #60a5fa 100%);
        color: #fff;
        border: 2px solid #2563eb;
        box-shadow: 0 8px 24px 0 #2563eb44, 0 2px 8px #2563eb22;
        transform: translateY(-6px) scale(1.03);
        z-index: 2;
        position: relative;
    }

    .relatorios-cards-grid .relatorio-tab.active h2,
    .relatorios-cards-grid .relatorio-tab.active p,
    .relatorios-cards-grid .relatorio-tab.active i {
        color: #ffffff !important;
    }

    .relatorios-cards-grid .relatorio-tab.active > div > div:last-child {
        background: rgba(255, 255, 255, 0.22) !important;
    }

    .relatorios-cards-grid .relatorio-tab h2 {
        font-size: 1.25rem !important;
        line-height: 1.2 !important;
    }

    .relatorios-cards-grid .relatorio-tab p {
        font-size: 0.82rem !important;
    }

    .relatorios-cards-grid .relatorio-tab > p {
        font-size: 0.72rem !important;
    }

    .relatorios-cards-grid .relatorio-tab i {
        font-size: 1.05rem !important;
    }

    @media (max-width: 768px) {
        .relatorios-cards-grid .relatorio-tab h2 {
            font-size: 1.1rem !important;
        }

        .relatorios-cards-grid .relatorio-tab p {
            font-size: 0.76rem !important;
        }

        .relatorios-cards-grid .relatorio-tab > p {
            font-size: 0.68rem !important;
        }
    }

    .relatorio-content {
        animation: fadeIn 0.3s ease-in;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-5px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Gráficos - Chart Container Styling */
    canvas {
        max-width: 100%;
        max-height: 100%;
    }

    .chart-container {
        position: relative;
        width: 100%;
        height: 280px;
        background: rgba(255,255,255,0.03);
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 8px;
        padding: 1rem;
        margin-top: 1.5rem;
        margin-bottom: 1.5rem;
    }

    @media (max-width: 768px) {
        .chart-container {
            height: 200px;
        }
    }
</style>




</body>

</html>
