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

    // Repara tabelas legadas criadas sem AUTO_INCREMENT em `id` (visto em produção: toda
    // solicitação após a primeira falhava com "Duplicate entry '0' for key 'PRIMARY'",
    // pois cada INSERT sem valor explícito de id caía sempre em 0).
    $idColInfo = $pdo->query("SHOW COLUMNS FROM turno_swap_requests LIKE 'id'")->fetch(PDO::FETCH_ASSOC);
    if ($idColInfo && stripos((string)($idColInfo['Extra'] ?? ''), 'auto_increment') === false) {
        if ($pdo->query("SELECT id FROM turno_swap_requests WHERE id = 0 LIMIT 1")->fetch(PDO::FETCH_ASSOC)) {
            $maxId = (int)$pdo->query("SELECT COALESCE(MAX(id), 0) AS m FROM turno_swap_requests")->fetch(PDO::FETCH_ASSOC)['m'];
            $pdo->exec("UPDATE turno_swap_requests SET id = " . ($maxId + 1) . " WHERE id = 0");
        }
        $pdo->exec("ALTER TABLE turno_swap_requests MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT");
    }
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
                    $encerrarAntecipado = false;
                    if ($statusAtual === 'aprovada') {
                        if ($inicioAtual !== '' && $todayIso < $inicioAtual) {
                            // Agendada (ainda não começou): pode cancelar livremente, 0 dias usados.
                            $canCancel = true;
                        } elseif ($inicioAtual !== '' && $fimAtual !== '' && $todayIso >= $inicioAtual && $todayIso <= $fimAtual) {
                            if ($todayIso === $inicioAtual) {
                                // Primeiro dia: ainda não usou nenhum dia, cancela totalmente.
                                $canCancel = true;
                            } else {
                                // Em curso, já passou o primeiro dia: encerra antecipadamente em vez de
                                // bloquear — conta como usados só os dias já decorridos (até ontem).
                                $canCancel = true;
                                $encerrarAntecipado = true;
                            }
                        }
                    }

                    if (!$canCancel) {
                        header('Location: dashboard.php?section=ferias&review=blocked');
                        exit;
                    }

                    if ($encerrarAntecipado) {
                        $novoFim = date('Y-m-d', strtotime($todayIso . ' -1 day'));
                        $stmtCancel = $pdo->prepare('UPDATE ferias SET data_fim = ? WHERE id = ?');
                        $stmtCancel->execute([$novoFim, $feriasId]);
                    } else {
                        $stmtCancel = $pdo->prepare('UPDATE ferias SET status = ? WHERE id = ?');
                        $stmtCancel->execute(['cancelada', $feriasId]);
                    }

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
    <!-- CSS do dashboard, dividido em partes (mesma ordem de cascata do antigo dashboard.css) -->
    <link rel="stylesheet" href="assets/css/base.css?v=<?php echo (int)@filemtime(__DIR__ . '/assets/css/base.css'); ?>">
    <link rel="stylesheet" href="assets/css/layout.css?v=<?php echo (int)@filemtime(__DIR__ . '/assets/css/layout.css'); ?>">
    <link rel="stylesheet" href="assets/css/components.css?v=<?php echo (int)@filemtime(__DIR__ . '/assets/css/components.css'); ?>">
    <link rel="stylesheet" href="assets/css/legacy.css?v=<?php echo (int)@filemtime(__DIR__ . '/assets/css/legacy.css'); ?>">
    <!-- Bibliotecas JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Chart.js para gráficos -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <!-- Seu JS -->
    <?php
        // dashboard.js dividido em partes sequenciais (mesma ordem/escopo global do ficheiro único original)
        foreach ([
            'dashboard-01-core.js',
            'dashboard-02-funcionarios.js',
            'dashboard-03-turnos.js',
            'dashboard-04-navegacao.js',
            'dashboard-05-utils-calendario.js',
            'dashboard-06-funcionarios-notif.js',
            'dashboard-07-folha.js',
            'dashboard-08-relatorios.js',
        ] as $__jsPart) {
            echo '<script src="assets/js/' . $__jsPart . '?v=' . (int)@filemtime($ADMIN_DIR . '/assets/js/' . $__jsPart) . '"></script>' . "\n    ";
        }
    ?>
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















        <?php require $ADMIN_DIR . '/sections/funcionarios.php'; ?>




















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

        <?php require $ADMIN_DIR . '/sections/assiduidade.php'; ?>
        <?php require $ADMIN_DIR . '/sections/solicitacoes.php'; ?>


















        <!-- TURNOS SECTION: gerência de turnos -->
        <?php require $ADMIN_DIR . '/sections/turnos.php'; ?>
















        <?php require $ADMIN_DIR . '/sections/relatorios.php'; ?>
        <?php require $ADMIN_DIR . '/sections/ferias.php'; ?>



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
