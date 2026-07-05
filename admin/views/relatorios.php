<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - RHNeto Pro</title>
    <link rel="stylesheet" href="../assets/css/relatorios.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', Arial, sans-serif; background: #f7f7f7; margin: 0; }
        .relatorios-container { max-width: 900px; margin: 40px auto; padding: 24px; }
        .cards-grid { display: flex; flex-wrap: wrap; gap: 24px; justify-content: center; }
        .relatorio-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            padding: 32px 28px;
            min-width: 220px;
            flex: 1 1 220px;
            max-width: 260px;
            display: flex;
            flex-direction: column;
            align-items: center;
            cursor: pointer;
            transition: box-shadow .2s, transform .2s;
        }
        .relatorio-card:hover {
            box-shadow: 0 6px 24px rgba(0,0,0,0.13);
            transform: translateY(-4px) scale(1.03);
        }
        .relatorio-card .icon {
            font-size: 2.7rem;
            color: #2b7cff;
            margin-bottom: 18px;
        }
        .relatorio-card .title {
            font-size: 1.18rem;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .relatorio-card .desc {
            font-size: 0.98rem;
            color: #555;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="relatorios-container">
        <h1>Relatórios</h1>
        <div class="cards-grid">
            <div class="relatorio-card" onclick="abrirRelatorio('faltas')">
                <div class="icon"><i class="fas fa-user-times"></i></div>
                <div class="title">Faltas</div>
                <div class="desc">Veja todos os funcionários com faltas e detalhes por período.</div>
            </div>
            <div class="relatorio-card" onclick="abrirRelatorio('atrasos')">
                <div class="icon"><i class="fas fa-clock"></i></div>
                <div class="title">Atrasos</div>
                <div class="desc">Relatório de atrasos por funcionário e por data.</div>
            </div>
            <div class="relatorio-card" onclick="abrirRelatorio('presencas')">
                <div class="icon"><i class="fas fa-user-check"></i></div>
                <div class="title">Presenças</div>
                <div class="desc">Resumo completo de presenças e assiduidade.</div>
            </div>
            <div class="relatorio-card" onclick="abrirRelatorio('justificativas')">
                <div class="icon"><i class="fas fa-file-alt"></i></div>
                <div class="title">Justificativas</div>
                <div class="desc">Veja justificativas apresentadas e seu estado.</div>
            </div>
            <div class="relatorio-card" onclick="abrirRelatorio('ranking')">
                <div class="icon"><i class="fas fa-trophy"></i></div>
                <div class="title">Ranking de Assiduidade</div>
                <div class="desc">Funcionários com melhor presença e pontualidade.</div>
            </div>
        </div>
    </div>
    <script>
        function abrirRelatorio(tipo) {
            // Aqui você pode redirecionar ou abrir um modal com o relatório específico
            // Exemplo: window.location.href = 'relatorio_' + tipo + '.php';
            alert('Abrir relatório: ' + tipo);
        }
    </script>
</body>
</html>
