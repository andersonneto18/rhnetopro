<?php
session_start();
date_default_timezone_set('Europe/Lisbon');
require_once '../../config/db_connection.php'; // deve expor $pdo (PDO)

try {
    $stmt = $pdo->prepare("SELECT id, name FROM employees ORDER BY name ASC");
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $employees = [];
    error_log("marcar_assiduidade_public error: " . $e->getMessage());
}
?>
<!doctype html>
<html lang="pt">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Portal Funcionário - Marcar Presença</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
 body{font-family:Arial;padding:1.5rem;background:#f5f7fb}
 .card{max-width:640px;margin:0 auto;background:#fff;padding:1rem;border-radius:8px;box-shadow:0 6px 18px rgba(0,0,0,.06)}
 select{width:100%;padding:.6rem;border:1px solid #ddd;border-radius:6px}
 .actions{display:flex;gap:.5rem;margin-top:.75rem}
 button{flex:1;padding:.6rem;border:0;border-radius:6px;color:#fff;font-weight:700;cursor:pointer}
 .btn-entrada{background:#16a34a} .btn-saida{background:#f59e0b}
 .btn-presente{background:#0ea5e9} .btn-falta{background:#ef4444}
 .msg{margin-top:.75rem}
</style>
</head>
<body>
<div class="card">
  <h2>Marcar Presença</h2>
  <p>Selecione seu nome e clique na ação desejada.</p>

  <label for="employeeSelect" style="font-weight:600;">Funcionário</label>
  <select id="employeeSelect">
    <option value="">-- Selecionar --</option>
    <?php foreach ($employees as $emp): ?>
      <option value="<?php echo (int)$emp['id']; ?>"><?php echo htmlspecialchars($emp['name']); ?></option>
    <?php endforeach; ?>
  </select>

  <div class="actions">
    <button class="btn-entrada" onclick="registrarPonto('entrada')"><i class="fas fa-door-open"></i> Entrada</button>
    <button class="btn-saida" onclick="registrarPonto('saida')"><i class="fas fa-door-closed"></i> Saída</button>
    <button class="btn-presente" onclick="marcarPresenca('presente')"><i class="fas fa-check"></i> Presente</button>
    <button class="btn-falta" onclick="marcarPresenca('falta')"><i class="fas fa-times"></i> Falta</button>
  </div>

  <div id="msg" class="msg"></div>
  <p style="margin-top:1rem"><a href="dashboard.php">Voltar ao Dashboard</a></p>
</div>

<script>
function getSelectedId(){ return document.getElementById('employeeSelect').value; }
function showMsg(t, ok=true){ const el=document.getElementById('msg'); el.textContent=t; el.style.color=ok?'green':'crimson'; }

// desativa todos os botões de ação por alguns segundos
function disableActionsTemporarily(seconds = 3) {
    const buttons = document.querySelectorAll('.actions button, .actions .btn-entrada, .actions .btn-saida, .actions .btn-presente, .actions .btn-falta, .actions button');
    buttons.forEach(b => { b.disabled = true; b.style.opacity = '.6'; });
    setTimeout(() => {
        buttons.forEach(b => { b.disabled = false; b.style.opacity = '1'; });
    }, seconds * 1000);
}

async function registrarPonto(tipo){
  const id = getSelectedId(); if(!id){ showMsg('Selecione o funcionário.', false); return; }
  showMsg('Registrando ponto...');
  try{
    const res = await fetch('registrar_ponto_public.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ tipo, funcionario_id: id })
    });
    const data = await res.json();
    if(data.success){
      showMsg(data.message || ('Ponto registrado (' + (data.hora || tipo) + ')'));
      disableActionsTemporarily(3);
    } else {
      showMsg(data.message || 'Erro ao registrar ponto', false);
    }
  } catch(e){
    console.error(e);
    showMsg('Erro de comunicação com o servidor.', false);
  }
}

async function marcarPresenca(status){
  const id = getSelectedId(); if(!id){ showMsg('Selecione o funcionário.', false); return; }
  showMsg('Gravando presença...');
  try{
    const res = await fetch('salvar_presenca_public.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ id, status })
    });
    const data = await res.json();
    if(data.success){
      showMsg(data.message || ('Presença: ' + status + ' gravada.'));
      disableActionsTemporarily(3);
    } else {
      showMsg(data.message || 'Erro ao gravar presença', false);
    }
  } catch(e){
    console.error(e);
    showMsg('Erro de comunicação com o servidor.', false);
  }
}
</script>
</body>
</html>