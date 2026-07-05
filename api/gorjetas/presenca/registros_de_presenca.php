<?php
session_start(); // Inicia a sessão

// Verifica se usuário está logado
if (!isset($_SESSION['user_id']) || !isset($_SESSION['client_id'])) {
    header("Location: login.php");
    exit();
}

$loggedInClientId = $_SESSION['client_id'];

// Configurações do banco de dados
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rh_neto"; 

// Tenta conectar
$conn = new mysqli($servername, $username, $password, $dbname);

// Se a conexão falhar, exibe um erro
if ($conn->connect_error) {
    die("Falha na conexão com o banco de dados: " . $conn->connect_error);
}

// Consulta para contar presentes de hoje (filtrada por client_id)
$sqlPresentes = "SELECT COUNT(*) AS presentCount 
                 FROM presencas p
                 INNER JOIN employees e ON p.funcionario_id = e.id
                 WHERE p.status = 'presente' 
                 AND DATE(p.data_registro) = CURDATE()
                 AND e.client_id = ?";

$stmtPresentes = $conn->prepare($sqlPresentes);
$stmtPresentes->bind_param("i", $loggedInClientId);
$stmtPresentes->execute();
$resultPresentes = $stmtPresentes->get_result();
$presentCount = ($resultPresentes->fetch_assoc())['presentCount'] ?? 0;

// Consulta SQL para listar registros (filtrada por client_id)
$sql = "SELECT 
            p.id, 
            e.name AS nome_funcionario, 
            p.status, 
            p.data_registro 
        FROM presencas p
        INNER JOIN employees e ON p.funcionario_id = e.id
        WHERE e.client_id = ?
        ORDER BY p.data_registro DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $loggedInClientId);
$stmt->execute();
$result = $stmt->get_result();

$registros = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $registros[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizar Registros de Presença</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #1a202c;
            color: #edf2f7;
            margin: 0;
            padding: 2rem;
        }
        .container {
            max-width: 900px;
            margin: auto;
            background-color: #2d3748;
            padding: 2rem;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        h2 {
            color: #4299e1;
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 1rem;
        }
        /* Estilo para o cartão de estatística */
        .stat-card-content {
            background-color: #1f2937;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        .stat-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #fff;
        }
        .stat-label {
            font-size: 0.9rem;
            color: #cbd5e0;
            text-transform: uppercase;
        }
        .stat-icon-container {
            background-color: #dd6b20;
            padding: 0.75rem;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .stat-icon {
            color: #fff;
            font-size: 1.5rem;
        }
        
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 0.5rem;
        }
        thead {
            background-color: #1f2937;
        }
        th, td {
            padding: 1rem;
            text-align: left;
            font-weight: 500;
        }
        tbody tr {
            background-color: #1a202c;
        }
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
        }
        .status-presente {
            background-color: #d1fae5;
            color: #10b981;
        }
        .status-falta {
            background-color: #fee2e2;
            color: #ef4444;
        }
        .no-records {
            text-align: center;
            font-style: italic;
            color: #cbd5e0;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="stat-card-content">
        <div class="stat-info">
            <div class="stat-number"><?php echo $presentCount ?? 0; ?></div>
            <div class="stat-label">Presentes Hoje</div>
        </div>
        <div class="stat-icon-container">
            <i class="fas fa-user-check stat-icon"></i>
        </div>
    </div>
    
    <h2>
        <i class="fas fa-list-check"></i>
        Registros de Presença e Falta
    </h2>

    <?php if (count($registros) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Funcionário</th>
                    <th>Status</th>
                    <th>Data e Hora</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registros as $registro): ?>
                <tr>
                    <td><?php echo htmlspecialchars($registro['id']); ?></td>
                    <td><?php echo htmlspecialchars($registro['nome_funcionario']); ?></td>
                    <td>
                        <?php 
                            $status_class = ($registro['status'] == 'presente') ? 'status-presente' : 'status-falta';
                            $status_text = ($registro['status'] == 'presente') ? 'Presença' : 'Falta';
                        ?>
                        <span class="status-badge <?php echo $status_class; ?>">
                            <?php echo $status_text; ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($registro['data_registro']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="no-records">Nenhum registro encontrado na base de dados.</p>
    <?php endif; ?>
</div>
</body>
</html>