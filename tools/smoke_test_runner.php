<?php
declare(strict_types=1);

session_start();

header('Content-Type: text/html; charset=utf-8');

$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
$isLocalRequest = in_array($remoteAddr, ['127.0.0.1', '::1', ''], true);

if (!$isLocalRequest) {
    http_response_code(403);
    echo 'Acesso negado. Esta ferramenta so pode ser usada localmente.';
    exit;
}

if (!isset($_SESSION['smoke_runner_csrf'])) {
    $_SESSION['smoke_runner_csrf'] = bin2hex(random_bytes(16));
}

$csrfToken = (string)$_SESSION['smoke_runner_csrf'];
$sessionClientId = isset($_SESSION['client_id']) ? (int)$_SESSION['client_id'] : 0;

$defaults = [
    'client_id' => $sessionClientId > 0 ? (string)$sessionClientId : '',
    'year' => date('Y'),
    'month' => date('n'),
    'scenario' => 'presente',
    'employee_name' => 'QA_FT_WEB',
    'turno' => 'Manha',
    'gorjeta_pendente' => '45.50',
    'gorjeta_paga' => '120.00',
    'dry_run' => '1',
    'cleanup_old' => '0',
];

$state = $defaults;
$resultOutput = '';
$resultExitCode = null;
$resultOk = false;
$resultRan = false;
$resultCommand = '';
$resultError = '';
$resultNotice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedCsrf = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrfToken, $postedCsrf)) {
        $resultError = 'Token CSRF invalido. Recarregue a pagina e tente novamente.';
    } else {
        $state['client_id'] = trim((string)($_POST['client_id'] ?? ''));
        $state['year'] = trim((string)($_POST['year'] ?? $defaults['year']));
        $state['month'] = trim((string)($_POST['month'] ?? $defaults['month']));
        $state['scenario'] = trim((string)($_POST['scenario'] ?? 'presente'));
        $state['employee_name'] = trim((string)($_POST['employee_name'] ?? $defaults['employee_name']));
        $state['turno'] = trim((string)($_POST['turno'] ?? $defaults['turno']));
        $state['gorjeta_pendente'] = trim((string)($_POST['gorjeta_pendente'] ?? $defaults['gorjeta_pendente']));
        $state['gorjeta_paga'] = trim((string)($_POST['gorjeta_paga'] ?? $defaults['gorjeta_paga']));
        $state['dry_run'] = isset($_POST['dry_run']) ? '1' : '0';
        $state['cleanup_old'] = isset($_POST['cleanup_old']) ? '1' : '0';

        $year = (int)$state['year'];
        $month = (int)$state['month'];
        $scenario = strtolower($state['scenario']);
        $turno = $state['turno'] !== '' ? $state['turno'] : 'Manha';
        $gorjetaPendente = str_replace(',', '.', $state['gorjeta_pendente']);
        $gorjetaPaga = str_replace(',', '.', $state['gorjeta_paga']);

        if ($state['client_id'] !== '' && !ctype_digit($state['client_id'])) {
            $resultError = 'Client ID invalido.';
        } elseif ($year < 2000 || $year > 2100) {
            $resultError = 'Ano invalido.';
        } elseif ($month < 1 || $month > 12) {
            $resultError = 'Mes invalido.';
        } elseif (!in_array($scenario, ['presente', 'falta'], true)) {
            $resultError = 'Scenario invalido.';
        } elseif (!is_numeric($gorjetaPendente) || (float)$gorjetaPendente < 0) {
            $resultError = 'Gorjeta pendente invalida.';
        } elseif (!is_numeric($gorjetaPaga) || (float)$gorjetaPaga < 0) {
            $resultError = 'Gorjeta paga invalida.';
        } else {
            if ($sessionClientId > 0 && $state['client_id'] !== '' && (int)$state['client_id'] !== $sessionClientId) {
                $resultNotice = 'Atencao: voce esta a executar com client_id diferente da sessao atual. Os dados podem nao aparecer no seu painel atual.';
            }

            $scriptPath = realpath(__DIR__ . '/smoke_test_full_flow.php');
            if ($scriptPath === false || !is_file($scriptPath)) {
                $resultError = 'Script de smoke test nao encontrado.';
            } else {
                $phpExecutable = 'C:\\xampp\\php\\php.exe';
                if (!is_file($phpExecutable)) {
                    $phpExecutable = PHP_BINARY;
                }

                $cmdParts = [
                    escapeshellarg($phpExecutable),
                    escapeshellarg($scriptPath),
                    '--year=' . escapeshellarg((string)$year),
                    '--month=' . escapeshellarg((string)$month),
                    '--scenario=' . escapeshellarg($scenario),
                    '--employee-name=' . escapeshellarg($state['employee_name'] !== '' ? $state['employee_name'] : 'QA_FT_WEB'),
                    '--turno=' . escapeshellarg($turno),
                    '--gorjeta-pendente=' . escapeshellarg((string)$gorjetaPendente),
                    '--gorjeta-paga=' . escapeshellarg((string)$gorjetaPaga),
                ];

                if ($state['client_id'] !== '') {
                    $cmdParts[] = '--client-id=' . escapeshellarg($state['client_id']);
                }
                if ($state['dry_run'] === '1') {
                    $cmdParts[] = '--dry-run';
                }
                if ($state['cleanup_old'] === '1') {
                    $cmdParts[] = '--cleanup-old';
                }

                $resultCommand = implode(' ', $cmdParts);
                $resultRan = true;

                $descriptorSpec = [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ];

                $process = proc_open($resultCommand, $descriptorSpec, $pipes, dirname($scriptPath));
                if (!is_resource($process)) {
                    $resultError = 'Nao foi possivel iniciar o processo de teste.';
                } else {
                    set_time_limit(0);

                    fclose($pipes[0]);
                    $stdout = stream_get_contents($pipes[1]);
                    $stderr = stream_get_contents($pipes[2]);
                    fclose($pipes[1]);
                    fclose($pipes[2]);

                    $resultExitCode = proc_close($process);
                    $resultOutput = trim((string)$stdout . PHP_EOL . (string)$stderr);
                    $resultOk = ($resultExitCode === 0);
                }
            }
        }
    }
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Runner de Smoke Test</title>
    <style>
        :root {
            --bg: #0b1220;
            --panel: #111b2f;
            --panel-soft: #16233d;
            --text: #e5edf9;
            --muted: #9fb1d2;
            --border: #2a3a5f;
            --accent: #2f6fed;
            --accent-soft: #5ea0ff;
            --ok-bg: #0f2f1f;
            --ok-border: #2f7a55;
            --ok-text: #a7efc8;
            --err-bg: #31171c;
            --err-border: #8d3a46;
            --err-text: #ffb3be;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", "Helvetica Neue", sans-serif;
            color: var(--text);
            background:
                radial-gradient(1300px 500px at 20% -10%, #1f3f8f55, transparent),
                radial-gradient(900px 450px at 90% 0%, #0a7f8e3d, transparent),
                var(--bg);
            min-height: 100vh;
        }

        .wrap {
            max-width: 980px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .card {
            background: linear-gradient(160deg, var(--panel), var(--panel-soft));
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 16px 36px #00000040;
            overflow: hidden;
        }

        .header {
            padding: 1.2rem 1.3rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .title {
            margin: 0;
            font-size: 1.25rem;
            letter-spacing: 0.02em;
        }

        .subtitle {
            margin: 0.25rem 0 0;
            color: var(--muted);
            font-size: 0.9rem;
        }

        .content {
            padding: 1.2rem 1.3rem 1.4rem;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.9rem;
        }

        .field {
            display: grid;
            gap: 0.35rem;
            min-width: 0;
        }

        .field label {
            color: var(--muted);
            font-size: 0.82rem;
            font-weight: 600;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .field input,
        .field select {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #0b1530;
            color: var(--text);
            padding: 0.56rem 0.65rem;
            outline: none;
        }

        .field input:focus,
        .field select:focus {
            border-color: var(--accent-soft);
            box-shadow: 0 0 0 3px #5ea0ff22;
        }

        .checks {
            margin-top: 1rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            color: var(--muted);
            font-size: 0.9rem;
        }

        .preview {
            margin-top: 1rem;
            border: 1px solid var(--border);
            background: #0a1430;
            border-radius: 12px;
            padding: 0.75rem;
        }

        .preview h3 {
            margin: 0 0 0.45rem;
            font-size: 0.95rem;
            color: #b9cced;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .preview-grid {
            display: grid;
            gap: 0.45rem;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            font-size: 0.9rem;
            color: var(--text);
        }

        .preview-grid div { background:#101d3f; border:1px solid var(--border); border-radius:8px; padding:0.5rem 0.6rem; }

        .preview-label { color: var(--muted); font-size: 0.75rem; display:block; margin-bottom:0.2rem; text-transform:uppercase; letter-spacing:0.04em; }

        .checks label {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
        }

        .actions {
            margin-top: 1rem;
            display: flex;
            gap: 0.8rem;
            flex-wrap: wrap;
        }

        .btn {
            border: none;
            border-radius: 10px;
            padding: 0.62rem 0.95rem;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-primary {
            color: #fff;
            background: linear-gradient(135deg, var(--accent), #2356bc);
        }

        .btn-secondary {
            color: var(--text);
            background: #223355;
            border: 1px solid var(--border);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }

        .result {
            margin-top: 1rem;
            border-radius: 12px;
            padding: 0.8rem;
            border: 1px solid var(--border);
            background: #0b1530;
        }

        .result.ok {
            background: var(--ok-bg);
            border-color: var(--ok-border);
            color: var(--ok-text);
        }

        .result.err {
            background: var(--err-bg);
            border-color: var(--err-border);
            color: var(--err-text);
        }

        .result.notice {
            background: #2f260f;
            border-color: #7a6430;
            color: #ffe6a4;
        }

        .mono {
            margin-top: 0.65rem;
            white-space: pre-wrap;
            font-family: Consolas, "Courier New", monospace;
            font-size: 0.84rem;
            line-height: 1.35;
            color: #dbe9ff;
            background: #081024;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 0.75rem;
            max-height: 420px;
            overflow: auto;
        }

        @media (max-width: 900px) {
            .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 560px) {
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <div class="header">
                <div>
                    <h1 class="title">Runner de Smoke Test</h1>
                    <p class="subtitle">Executa o fluxo completo: funcionario, turno, presenca/ponto, gorjetas e folha.</p>
                </div>
            </div>

            <div class="content">
                <form method="post" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">

                    <div class="grid">
                        <div class="field">
                            <label for="client_id">Client ID (opcional)</label>
                            <input id="client_id" type="number" min="1" name="client_id" value="<?php echo e((string)$state['client_id']); ?>" placeholder="Auto por usuarios">
                        </div>

                        <div class="field">
                            <label for="year">Ano</label>
                            <input id="year" type="number" min="2000" max="2100" name="year" value="<?php echo e((string)$state['year']); ?>" required>
                        </div>

                        <div class="field">
                            <label for="month">Mes</label>
                            <input id="month" type="number" min="1" max="12" name="month" value="<?php echo e((string)$state['month']); ?>" required>
                        </div>

                        <div class="field">
                            <label for="scenario">Scenario</label>
                            <select id="scenario" name="scenario">
                                <option value="presente" <?php echo $state['scenario'] === 'presente' ? 'selected' : ''; ?>>presente</option>
                                <option value="falta" <?php echo $state['scenario'] === 'falta' ? 'selected' : ''; ?>>falta</option>
                            </select>
                        </div>

                        <div class="field">
                            <label for="employee_name">Nome base do funcionario</label>
                            <input id="employee_name" type="text" name="employee_name" value="<?php echo e((string)$state['employee_name']); ?>" placeholder="Ex: ANDERSON_QA">
                        </div>

                        <div class="field">
                            <label for="turno">Turno</label>
                            <input id="turno" type="text" name="turno" value="<?php echo e((string)$state['turno']); ?>" placeholder="Manha / Tarde / Noite">
                        </div>

                        <div class="field">
                            <label for="gorjeta_pendente">Gorjeta pendente (€)</label>
                            <input id="gorjeta_pendente" type="number" step="0.01" min="0" name="gorjeta_pendente" value="<?php echo e((string)$state['gorjeta_pendente']); ?>">
                        </div>

                        <div class="field">
                            <label for="gorjeta_paga">Gorjeta paga (€)</label>
                            <input id="gorjeta_paga" type="number" step="0.01" min="0" name="gorjeta_paga" value="<?php echo e((string)$state['gorjeta_paga']); ?>">
                        </div>
                    </div>

                    <div class="preview" id="previewCard">
                        <h3>O que sera executado e adicionado</h3>
                        <div class="preview-grid">
                            <div><span class="preview-label">Funcionario</span><span id="pvName"></span></div>
                            <div><span class="preview-label">Periodo da folha</span><span id="pvPeriodo"></span></div>
                            <div><span class="preview-label">Scenario</span><span id="pvScenario"></span></div>
                            <div><span class="preview-label">Turno</span><span id="pvTurno"></span></div>
                            <div><span class="preview-label">Gorjeta pendente</span><span id="pvGorPendente"></span></div>
                            <div><span class="preview-label">Gorjeta paga</span><span id="pvGorPaga"></span></div>
                        </div>
                    </div>

                    <div class="checks">
                        <label>
                            <input type="checkbox" name="dry_run" <?php echo $state['dry_run'] === '1' ? 'checked' : ''; ?>>
                            Dry-run (rollback no final)
                        </label>

                        <label>
                            <input type="checkbox" name="cleanup_old" <?php echo $state['cleanup_old'] === '1' ? 'checked' : ''; ?>>
                            Limpar dados QA antigos antes
                        </label>
                    </div>

                    <div class="actions">
                        <button class="btn btn-primary" type="submit">Executar Teste</button>
                        <a class="btn btn-secondary" href="smoke_test_runner.php">Resetar Formulario</a>
                    </div>
                </form>

                <?php if ($resultError !== ''): ?>
                    <div class="result err">
                        <strong>Falha:</strong> <?php echo e($resultError); ?>
                    </div>
                <?php endif; ?>

                <?php if ($resultNotice !== ''): ?>
                    <div class="result notice">
                        <strong>Aviso:</strong> <?php echo e($resultNotice); ?>
                    </div>
                <?php endif; ?>

                <?php if ($resultRan): ?>
                    <div class="result <?php echo $resultOk ? 'ok' : 'err'; ?>">
                        <strong><?php echo $resultOk ? 'Execucao concluida com sucesso.' : 'Execucao concluida com erro.'; ?></strong>
                        <div style="margin-top:.35rem; font-size:.88rem; color:inherit;">
                            Exit code: <?php echo e((string)$resultExitCode); ?>
                        </div>
                        <?php if ($resultCommand !== ''): ?>
                            <div style="margin-top:.35rem; font-size:.78rem; opacity:.9; word-break:break-all;">Comando: <?php echo e($resultCommand); ?></div>
                        <?php endif; ?>
                        <div class="mono"><?php echo e($resultOutput !== '' ? $resultOutput : '(sem output)'); ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
        (function () {
            function text(id) { return document.getElementById(id); }
            function euro(v) {
                var n = parseFloat((v || '0').toString().replace(',', '.'));
                if (isNaN(n)) n = 0;
                return 'EUR ' + n.toLocaleString('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            function refreshPreview() {
                var y = text('year') ? text('year').value : '';
                var m = text('month') ? text('month').value : '';
                var s = text('scenario') ? text('scenario').value : 'presente';
                var nm = text('employee_name') ? text('employee_name').value.trim() : '';
                var tr = text('turno') ? text('turno').value.trim() : '';
                var gp = text('gorjeta_pendente') ? text('gorjeta_pendente').value : '0';
                var gg = text('gorjeta_paga') ? text('gorjeta_paga').value : '0';

                text('pvName').textContent = (nm || 'QA_FT_WEB') + ' + sufixo automatico';
                text('pvPeriodo').textContent = (y || '-') + '-' + String(m || '-').padStart(2, '0');
                text('pvScenario').textContent = s;
                text('pvTurno').textContent = tr || 'Manha';
                text('pvGorPendente').textContent = euro(gp);
                text('pvGorPaga').textContent = euro(gg);
            }

            ['year', 'month', 'scenario', 'employee_name', 'turno', 'gorjeta_pendente', 'gorjeta_paga'].forEach(function (id) {
                var el = text(id);
                if (!el) return;
                el.addEventListener('input', refreshPreview);
                el.addEventListener('change', refreshPreview);
            });

            refreshPreview();
        })();
    </script>
</body>
</html>
