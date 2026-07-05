<?php
/**
 * Script de Dados de Teste para Relatórios
 * Popula o banco com dados realistas para testar a seção de Relatórios
 * Uso: Acesse /admin/seed_test_data.php no navegador ou execute via CLI
 */

// Segurança: só admin pode executar
session_start();
$isCliMode = php_sapi_name() === 'cli';

if (!$isCliMode && (!isset($_SESSION['user_id']) || empty($_SESSION['client_id']))) {
    die("❌ Acesso negado. Precisa estar logado como admin.");
}

require_once '../config/db_connection.php';

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=sistema_cadastro;charset=utf8mb4',
        'root',
        '',
        ['PDO::ATTR_ERRMODE' => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    die("❌ Erro de conexão: " . $e->getMessage());
}

$client_id = $isCliMode ? 1 : (int)$_SESSION['client_id'];
$currentYear = (int)date('Y');
$currentMonth = (int)date('n');
$currentDay = (int)date('d');

echo "🔄 Gerando dados de teste para client_id=$client_id, período=$currentMonth/$currentYear...\n";

// ===== 1. TURNOS ATIVOS =====
echo "\n1️⃣ Criando turnos ativos...\n";
try {
    // Buscar funcionários
    $stmt = $pdo->prepare("SELECT id FROM employees WHERE client_id = ? LIMIT 5");
    $stmt->execute([$client_id]);
    $employees = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($employees)) {
        echo "   ⚠️ Sem funcionários. Criando 3 funcionários de teste...\n";
        for ($i = 1; $i <= 3; $i++) {
            $pdo->prepare("INSERT INTO employees (client_id, name, email, salary_base, status, startDate, created_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())")->execute([
                $client_id,
                "Colaborador Teste $i",
                "teste$i@email.com",
                1200 + ($i * 100),
                'active'
            ]);
        }
        $stmt = $pdo->prepare("SELECT id FROM employees WHERE client_id = ? LIMIT 5");
        $stmt->execute([$client_id]);
        $employees = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM turnos WHERE client_id = ? AND LOWER(COALESCE(status, '')) = 'ativo'");
    $stmtCheck->execute([$client_id]);
    $turnosExistem = (int)$stmtCheck->fetchColumn();

    if (!$turnosExistem) {
        $turnos = [
            ['tipo' => 'Diurno', 'hora_inicio' => '09:00', 'hora_fim' => '18:00', 'dias' => 'segunda,terça,quarta,quinta,sexta'],
            ['tipo' => 'Noturno', 'hora_inicio' => '22:00', 'hora_fim' => '06:00', 'dias' => 'segunda,terça,quarta,quinta,sexta'],
            ['tipo' => 'Fim de Semana', 'hora_inicio' => '11:00', 'hora_fim' => '20:00', 'dias' => 'sábado,domingo'],
        ];

        foreach ($employees as $empId) {
            $turno = $turnos[array_rand($turnos)];
            $pdo->prepare("INSERT INTO turnos (client_id, funcionario_id, tipo, hora_inicio, hora_fim, dias_semana, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'ativo', NOW())")->execute([
                $client_id, $empId, $turno['tipo'], $turno['hora_inicio'], $turno['hora_fim'], $turno['dias']
            ]);
        }
        echo "   ✅ Turnos criados para " . count($employees) . " funcionários\n";
    } else {
        echo "   ✅ Turnos ativos já existem\n";
    }
} catch (Exception $e) {
    echo "   ❌ Erro: " . $e->getMessage() . "\n";
}

// ===== 2. REGISTOS DE PONTO =====
echo "\n2️⃣ Criando registos de ponto...\n";
try {
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM registros_ponto WHERE client_id = ? AND YEAR(data_registro) = ? AND MONTH(data_registro) = ?");
    $stmtCheck->execute([$client_id, $currentYear, $currentMonth]);
    $registosCount = (int)$stmtCheck->fetchColumn();

    if ($registosCount < 10) {
        $stmt = $pdo->prepare("SELECT id FROM employees WHERE client_id = ? LIMIT 5");
        $stmt->execute([$client_id]);
        $employees = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $horas = [
            ['entrada' => '09:00', 'saida' => '18:00'],
            ['entrada' => '08:30', 'saida' => '17:30'],
            ['entrada' => '09:15', 'saida' => '18:15'],
        ];

        for ($day = 1; $day <= min($currentDay, 20); $day++) {
            if (date('w', mktime(0, 0, 0, $currentMonth, $day, $currentYear)) == 0) continue; // Skip domingo
            
            foreach ($employees as $empId) {
                if (rand(0, 100) > 10) { // 90% de presença
                    $horaSet = $horas[array_rand($horas)];
                    $data = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $day);
                    
                    $pdo->prepare("INSERT IGNORE INTO registros_ponto (client_id, funcionario_id, data_registro, hora_entrada, hora_saida, status, created_at) VALUES (?, ?, ?, ?, ?, 'presente', NOW())")->execute([
                        $client_id, $empId, $data, $horaSet['entrada'], $horaSet['saida']
                    ]);
                }
            }
        }
        echo "   ✅ Registos de ponto criados\n";
    } else {
        echo "   ✅ Registos de ponto já existem\n";
    }
} catch (Exception $e) {
    echo "   ❌ Erro: " . $e->getMessage() . "\n";
}

// ===== 3. PRESENÇAS =====
echo "\n3️⃣ Criando presenças...\n";
try {
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM presencas WHERE client_id = ? AND YEAR(data_registro) = ? AND MONTH(data_registro) = ?");
    $stmtCheck->execute([$client_id, $currentYear, $currentMonth]);
    $presencasCount = (int)$stmtCheck->fetchColumn();

    if ($presencasCount < 10) {
        $stmt = $pdo->prepare("SELECT id FROM employees WHERE client_id = ? LIMIT 5");
        $stmt->execute([$client_id]);
        $employees = $stmt->fetchAll(PDO::FETCH_COLUMN);

        for ($day = 1; $day <= min($currentDay, 20); $day++) {
            if (date('w', mktime(0, 0, 0, $currentMonth, $day, $currentYear)) == 0) continue;
            
            foreach ($employees as $empId) {
                $data = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $day);
                $statusOpcoes = ['presente', 'presente', 'presente', 'falta', 'atraso'];
                $status = $statusOpcoes[array_rand($statusOpcoes)];
                
                $pdo->prepare("INSERT IGNORE INTO presencas (client_id, funcionario_id, data_registro, status, created_at) VALUES (?, ?, ?, ?, NOW())")->execute([
                    $client_id, $empId, $data, $status
                ]);
            }
        }
        echo "   ✅ Presenças criadas\n";
    } else {
        echo "   ✅ Presenças já existem\n";
    }
} catch (Exception $e) {
    echo "   ❌ Erro: " . $e->getMessage() . "\n";
}

// ===== 4. GORJETAS (STATUS = 'pago') =====
echo "\n4️⃣ Criando gorjetas com status 'pago'...\n";
try {
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM gorjetas WHERE client_id = ? AND YEAR(COALESCE(data, created_at)) = ? AND MONTH(COALESCE(data, created_at)) = ? AND LOWER(COALESCE(status, '')) = 'pago'");
    $stmtCheck->execute([$client_id, $currentYear, $currentMonth]);
    $gorjetasCount = (int)$stmtCheck->fetchColumn();

    if ($gorjetasCount < 5) {
        $stmt = $pdo->prepare("SELECT id FROM employees WHERE client_id = ? LIMIT 5");
        $stmt->execute([$client_id]);
        $employees = $stmt->fetchAll(PDO::FETCH_COLUMN);

        for ($i = 0; $i < 5; $i++) {
            $empId = $employees[array_rand($employees)];
            $valor = round(10 + rand(0, 50), 2);
            $data = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, rand(1, min($currentDay, 20)));
            
            $pdo->prepare("INSERT INTO gorjetas (client_id, funcionario_id, valor, data, status, created_at) VALUES (?, ?, ?, ?, 'pago', NOW())")->execute([
                $client_id, $empId, $valor, $data
            ]);
        }
        echo "   ✅ Gorjetas (pago) criadas\n";
    } else {
        echo "   ✅ Gorjetas já existem\n";
    }
} catch (Exception $e) {
    echo "   ❌ Erro: " . $e->getMessage() . "\n";
}

// ===== 5. JUSTIFICATIVAS (STATUS = 'aprovada') =====
echo "\n5️⃣ Criando justificativas aprovadas...\n";
try {
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM justificativas_presenca WHERE client_id = ? AND YEAR(data_ocorrencia) = ? AND MONTH(data_ocorrencia) = ? AND LOWER(status) = 'aprovada'");
    $stmtCheck->execute([$client_id, $currentYear, $currentMonth]);
    $justCount = (int)$stmtCheck->fetchColumn();

    if ($justCount < 2) {
        $stmt = $pdo->prepare("SELECT id FROM employees WHERE client_id = ? LIMIT 5");
        $stmt->execute([$client_id]);
        $employees = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $motivos = ['Consulta médica', 'Compromisso pessoal', 'Problema familiar', 'Motivo de saúde'];

        for ($i = 0; $i < 2; $i++) {
            $empId = $employees[array_rand($employees)];
            $dataOcor = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, rand(1, min($currentDay, 15)));
            $motivo = $motivos[array_rand($motivos)];
            
            $pdo->prepare("INSERT INTO justificativas_presenca (client_id, employee_id, data_ocorrencia, tipo, motivo, status, decidido_em, created_at) VALUES (?, ?, ?, 'falta', ?, 'aprovada', NOW(), NOW())")->execute([
                $client_id, $empId, $dataOcor, $motivo
            ]);
        }
        echo "   ✅ Justificativas aprovadas criadas\n";
    } else {
        echo "   ✅ Justificativas já existem\n";
    }
} catch (Exception $e) {
    echo "   ❌ Erro: " . $e->getMessage() . "\n";
}

echo "\n✅ Dados de teste gerados com sucesso!\n";
echo "Aceda aos Relatórios no dashboard para verificar os dados.\n";

if (!$isCliMode) {
    echo "\n<a href='dashboard.php' style='color:blue;text-decoration:underline;'>← Voltar ao Dashboard</a>";
}
?>
