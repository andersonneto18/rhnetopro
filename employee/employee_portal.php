<?php
session_start();
if (empty($_SESSION['employee_id'])) {
    header('Location: employee_login.php');
    exit;
}
require_once '../config/db_connection.php';

$employee_id = (int)$_SESSION['employee_id'];
$employee_name = $_SESSION['employee_name'] ?? 'Funcionário';
$client_id = (int)($_SESSION['client_id'] ?? 0);

$feriasPedidos = [];
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS ferias (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      client_id INT NOT NULL,
      funcionario_id INT NOT NULL,
      data_inicio DATE NOT NULL,
      data_fim DATE NOT NULL,
      status VARCHAR(20) NOT NULL DEFAULT 'pendente',
      motivo TEXT NULL,
      created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      KEY idx_ferias_client_employee (client_id, funcionario_id),
      KEY idx_ferias_status (status)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $stmtFerias = $pdo->prepare("SELECT id, data_inicio, data_fim, status, motivo, created_at
      FROM ferias
      WHERE client_id = ? AND funcionario_id = ?
      ORDER BY id DESC
      LIMIT 10");
  $stmtFerias->execute([$client_id, $employee_id]);
  $feriasPedidos = $stmtFerias->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  error_log('Erro ao carregar pedidos de férias (portal funcionário): ' . $e->getMessage());
}
?>
<!doctype html>
<html lang="pt">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Painel Funcionário</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="employee_portal.css">
<style>
body{font-family:Arial;padding:1.5rem;background:#f5f7fb}
.card{max-width:520px;margin:2rem auto;background:#fff;padding:1rem;border-radius:8px;box-shadow:0 6px 18px rgba(0,0,0,.06)}
h2{margin-bottom:.25rem}
.actions{display:flex;gap:.5rem;margin-top:.9rem}
button{flex:1;padding:.6rem;border:0;border-radius:6px;color:#fff;font-weight:700;cursor:pointer}
.btn-entrada{background:#16a34a} .btn-saida{background:#f59e0b}
.btn-presente{background:#0ea5e9} .btn-falta{background:#ef4444}
.msg{margin-top:.8rem}
.logout{margin-top:.8rem;display:block;text-align:right}
.sms-header{display:flex;justify-content:space-between;align-items:center;gap:.6rem;flex-wrap:wrap;margin-bottom:.55rem}
.sms-actions{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}
.sms-actions button{flex:0;padding:.35rem .65rem;background:#ef4444}
.sms-item{display:flex;align-items:flex-start;gap:.55rem;padding:.55rem;border:1px solid #d1d5db;border-radius:6px;background:#fff;margin-bottom:.4rem}
.sms-item-text{flex:1;font-size:.94rem;color:#111827}
.sms-item-time{display:block;margin-top:.2rem;color:#6b7280;font-size:.8rem}
.ferias-box{margin-top:1rem;padding:.8rem;border:1px solid #e2e8f0;border-radius:8px;background:#f8fafc}
.ferias-form{display:grid;gap:.55rem}
.ferias-row{display:grid;grid-template-columns:1fr 1fr;gap:.55rem}
.ferias-row input,.ferias-form textarea{width:100%;padding:.55rem;border:1px solid #cbd5e1;border-radius:6px}
.ferias-form textarea{resize:vertical;min-height:64px}
.ferias-submit{width:100%;padding:.6rem;border:0;border-radius:6px;background:#2563eb;color:#fff;font-weight:700;cursor:pointer}
.ferias-list{margin-top:.8rem;display:grid;gap:.45rem}
.ferias-item{padding:.55rem;border:1px solid #dbeafe;border-radius:6px;background:#fff}
.ferias-top{display:flex;justify-content:space-between;gap:.5rem;align-items:center}
.ferias-status{display:inline-flex;align-items:center;padding:.16rem .5rem;border-radius:999px;font-size:.74rem;font-weight:700;text-transform:uppercase}
.ferias-status-pendente{background:#fef3c7;color:#92400e}
.ferias-status-aprovada{background:#dcfce7;color:#166534}
.ferias-status-rejeitada{background:#fee2e2;color:#991b1b}
</style>
</head>
<body>
<div class="card">
  <h2>Olá, <?php echo htmlspecialchars($employee_name); ?></h2>
  <p>Marque sua presença ou registro de ponto:</p>

  <div class="actions">
    <button class="btn-entrada" onclick="registrarPonto('entrada')"><i class="fas fa-door-open"></i> Entrada</button>
    <button class="btn-saida" onclick="registrarPonto('saida')"><i class="fas fa-door-closed"></i> Saída</button>
  </div>

  <div class="actions" style="margin-top:.5rem">
    <button class="btn-presente" onclick="marcarPresenca('presente')"><i class="fas fa-check"></i> Presente</button>
    <button class="btn-falta" onclick="marcarPresenca('falta')"><i class="fas fa-times"></i> Falta</button>
  </div>

  <div class="ferias-box">
    <h3 style="margin:0 0 .55rem 0;font-size:1rem;color:#1e293b;"><i class="fas fa-umbrella-beach"></i> Pedido de Férias</h3>
    <form id="feriasForm" class="ferias-form">
      <div class="ferias-row">
        <div>
          <label for="feriasInicio" style="font-size:.82rem;color:#475569;display:block;margin-bottom:.2rem;">Data início</label>
          <input type="date" id="feriasInicio" name="data_inicio" required>
        </div>
        <div>
          <label for="feriasFim" style="font-size:.82rem;color:#475569;display:block;margin-bottom:.2rem;">Data término</label>
          <input type="date" id="feriasFim" name="data_fim" required>
        </div>
      </div>
      <div>
        <label for="feriasMotivo" style="font-size:.82rem;color:#475569;display:block;margin-bottom:.2rem;">Motivo (opcional)</label>
        <textarea id="feriasMotivo" name="motivo" maxlength="500" placeholder="Ex.: férias anuais"></textarea>
      </div>
      <button type="submit" class="ferias-submit"><i class="fas fa-paper-plane"></i> Enviar pedido</button>
    </form>

    <div class="ferias-list" id="feriasList">
      <?php if (empty($feriasPedidos)): ?>
        <div class="ferias-item" id="feriasEmptyState" style="color:#64748b;">Sem pedidos de férias ainda.</div>
      <?php else: ?>
        <?php foreach ($feriasPedidos as $pedido):
          $statusRaw = mb_strtolower(trim((string)($pedido['status'] ?? 'pendente')));
          if (in_array($statusRaw, ['aprovado'], true)) {
            $statusRaw = 'aprovada';
          }
          if (in_array($statusRaw, ['rejeitado', 'recusado', 'recusada'], true)) {
            $statusRaw = 'rejeitada';
          }
          $inicioIso = (string)($pedido['data_inicio'] ?? '');
          $fimIso = (string)($pedido['data_fim'] ?? '');
          $todayIso = date('Y-m-d');
          $statusLabel = $statusRaw === 'aprovada' ? 'Em curso' : ($statusRaw === 'rejeitada' ? 'Rejeitada' : 'Pendente');
          $statusClass = $statusRaw === 'aprovada' ? 'ferias-status-aprovada' : ($statusRaw === 'rejeitada' ? 'ferias-status-rejeitada' : 'ferias-status-pendente');
          if ($statusRaw === 'aprovada' && $inicioIso !== '' && $todayIso < $inicioIso) {
            $statusLabel = 'Agendada';
            $statusClass = 'ferias-status-pendente';
          } elseif ($statusRaw === 'aprovada' && $fimIso !== '' && $todayIso > $fimIso) {
            $statusLabel = 'Terminada';
            $statusClass = 'ferias-status-rejeitada';
          }
          $inicioFmt = !empty($pedido['data_inicio']) ? date('d/m/Y', strtotime((string)$pedido['data_inicio'])) : 'N/D';
          $fimFmt = !empty($pedido['data_fim']) ? date('d/m/Y', strtotime((string)$pedido['data_fim'])) : 'N/D';
          $motivoTxt = trim((string)($pedido['motivo'] ?? ''));
        ?>
          <div class="ferias-item">
            <div class="ferias-top">
              <strong style="color:#0f172a;"><?php echo htmlspecialchars($inicioFmt . ' - ' . $fimFmt); ?></strong>
              <span class="ferias-status <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusLabel); ?></span>
            </div>
            <?php if ($motivoTxt !== ''): ?>
              <div style="margin-top:.32rem;color:#475569;font-size:.88rem;"><?php echo htmlspecialchars($motivoTxt); ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="msg" style="margin-bottom:1rem;">
    <div class="sms-header">
      <strong>Histórico de SMS</strong>
      <div class="sms-actions" id="smsActions" style="display:none;">
        <label style="font-size:.85rem;color:#374151;display:flex;align-items:center;gap:.3rem;">
          <input type="checkbox" id="notificationsSelectAll"> Selecionar todas
        </label>
        <button type="button" id="btnDeleteSms" onclick="deleteSelectedSms()" disabled>Apagar selecionadas</button>
      </div>
    </div>
    <div id="notifications"></div>
  </div>
  <div id="msg" class="msg"></div>

  <a class="logout" href="employee_logout.php">Sair</a>
</div>

<script>
function showMsg(t, ok=true){ const el=document.getElementById('msg'); el.textContent=t; el.style.color=ok?'green':'crimson'; }
let notificationCanDelete = false;
let selectedNotificationIds = new Set();

function escapeHtml(value) {
  return String(value || '').replace(/[&<>"']/g, function(ch) {
    return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
  });
}

function formatNotificationDate(value) {
  if (!value) return '';
  const d = new Date(String(value).replace(' ', 'T'));
  if (Number.isNaN(d.getTime())) return '';
  return d.toLocaleString('pt-PT');
}

function updateDeleteSmsControls() {
  const btn = document.getElementById('btnDeleteSms');
  if (!btn) return;
  const count = selectedNotificationIds.size;
  btn.disabled = count === 0;
  btn.textContent = count > 0 ? `Apagar selecionadas (${count})` : 'Apagar selecionadas';
}

function toggleAllNotificationSelections(checked) {
  document.querySelectorAll('.notification-checkbox').forEach(function(cb) {
    cb.checked = !!checked;
    const id = Number(cb.value);
    if (!Number.isFinite(id) || id <= 0) return;
    if (checked) selectedNotificationIds.add(id);
    else selectedNotificationIds.delete(id);
  });
  updateDeleteSmsControls();
}

function renderNotifications(data) {
  const container = document.getElementById('notifications');
  const actions = document.getElementById('smsActions');
  const selectAll = document.getElementById('notificationsSelectAll');
  if (!container) return;

  notificationCanDelete = !!(data && data.can_delete);
  const notifications = Array.isArray(data && data.notifications) ? data.notifications : [];

  const currentIds = new Set(notifications.map(function(item) { return Number(item.id || 0); }).filter(function(id) {
    return Number.isFinite(id) && id > 0;
  }));

  selectedNotificationIds.forEach(function(id) {
    if (!currentIds.has(id)) selectedNotificationIds.delete(id);
  });

  if (actions) {
    actions.style.display = notificationCanDelete ? 'flex' : 'none';
  }

  if (selectAll) {
    selectAll.checked = false;
    selectAll.onchange = function() {
      toggleAllNotificationSelections(selectAll.checked);
    };
  }

  if (!notifications.length) {
    container.innerHTML = '<div style="padding:.55rem;border:1px dashed #d1d5db;border-radius:6px;color:#6b7280;">Sem SMS no histórico.</div>';
    selectedNotificationIds.clear();
    updateDeleteSmsControls();
    return;
  }

  container.innerHTML = notifications.map(function(n) {
    const id = Number(n.id || 0);
    const message = escapeHtml(n.mensagem || n.title || 'Mensagem sem conteúdo');
    const when = formatNotificationDate(n.data_envio || n.timestamp);
    const checkbox = notificationCanDelete && id > 0
      ? `<input type="checkbox" class="notification-checkbox" value="${id}" ${selectedNotificationIds.has(id) ? 'checked' : ''}>`
      : '';

    return `
      <div class="sms-item">
        ${checkbox}
        <div class="sms-item-text">
          <span>${message}</span>
          ${when ? `<span class="sms-item-time">${escapeHtml(when)}</span>` : ''}
        </div>
      </div>
    `;
  }).join('');

  document.querySelectorAll('.notification-checkbox').forEach(function(cb) {
    cb.addEventListener('change', function() {
      const id = Number(cb.value);
      if (!Number.isFinite(id) || id <= 0) return;
      if (cb.checked) selectedNotificationIds.add(id);
      else selectedNotificationIds.delete(id);

      if (selectAll) {
        const all = document.querySelectorAll('.notification-checkbox');
        const allChecked = all.length > 0 && Array.from(all).every(function(item) { return item.checked; });
        selectAll.checked = allChecked;
      }

      updateDeleteSmsControls();
    });
  });

  if (selectAll) {
    const all = document.querySelectorAll('.notification-checkbox');
    const allChecked = all.length > 0 && Array.from(all).every(function(item) { return item.checked; });
    selectAll.checked = allChecked;
  }

  updateDeleteSmsControls();
}

// envia marcação usando endpoints que usam session (não passa id no body)
// ADICIONADO: credentials:'same-origin' para garantir envio do cookie de sessão
async function registrarPonto(tipo){
  showMsg('Registrando ponto...');
  try{
    const res = await fetch('registrar_ponto_session.php', {
      method:'POST',
      credentials: 'same-origin',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ tipo })
    });
    const text = await res.text();
    try {
      const data = JSON.parse(text);
      if (data.success) showMsg(data.message || ('Ponto: '+(data.hora||tipo)));
      else showMsg(data.message || 'Erro ao registrar ponto', false);
    } catch(e) {
      showMsg('Resposta inválida do servidor: ' + text, false);
      console.error('Parse error registrarPonto:', text);
    }
  } catch(e){ console.error(e); showMsg('Erro de comunicação', false); }
}

async function marcarPresenca(status){
  showMsg('Gravando presença...');
  try{
    const res = await fetch('salvar_presenca_session.php', {
      method:'POST',
      credentials: 'same-origin',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ status })
    });
    const text = await res.text();
    try {
      const data = JSON.parse(text);
      if (data.success) showMsg(data.message || 'Presença gravada');
      else showMsg(data.message || 'Erro ao gravar presença', false);
    } catch(e) {
      showMsg('Resposta inválida do servidor: ' + text, false);
      console.error('Parse error marcarPresenca:', text);
    }
  } catch(e){ console.error(e); showMsg('Erro de comunicação', false); }
}

function renderFeriasItem(item) {
  var list = document.getElementById('feriasList');
  if (!list || !item) return;

  var empty = document.getElementById('feriasEmptyState');
  if (empty) {
    empty.remove();
  }

  var statusRaw = String(item.status || 'pendente').toLowerCase();
  if (statusRaw === 'aprovado') statusRaw = 'aprovada';
  if (['rejeitado', 'recusado', 'recusada'].indexOf(statusRaw) !== -1) statusRaw = 'rejeitada';

  var inicioIso = item.data_inicio ? String(item.data_inicio) : '';
  var fimIso = item.data_fim ? String(item.data_fim) : '';
  var hojeIso = new Date().toISOString().slice(0, 10);
  var statusLabel = statusRaw === 'aprovada' ? 'Em curso' : (statusRaw === 'rejeitada' ? 'Rejeitada' : 'Pendente');
  var statusClass = statusRaw === 'aprovada' ? 'ferias-status-aprovada' : (statusRaw === 'rejeitada' ? 'ferias-status-rejeitada' : 'ferias-status-pendente');
  if (statusRaw === 'aprovada' && inicioIso && hojeIso < inicioIso) {
    statusLabel = 'Agendada';
    statusClass = 'ferias-status-pendente';
  } else if (statusRaw === 'aprovada' && fimIso && hojeIso > fimIso) {
    statusLabel = 'Terminada';
    statusClass = 'ferias-status-rejeitada';
  }

  function fmt(d) {
    if (!d) return 'N/D';
    var p = String(d).split('-');
    if (p.length !== 3) return d;
    return p[2] + '/' + p[1] + '/' + p[0];
  }

  var div = document.createElement('div');
  div.className = 'ferias-item';
  div.innerHTML = '<div class="ferias-top">'
    + '<strong style="color:#0f172a;">' + escapeHtml(fmt(item.data_inicio) + ' - ' + fmt(item.data_fim)) + '</strong>'
    + '<span class="ferias-status ' + statusClass + '">' + escapeHtml(statusLabel) + '</span>'
    + '</div>'
    + (item.motivo ? '<div style="margin-top:.32rem;color:#475569;font-size:.88rem;">' + escapeHtml(item.motivo) + '</div>' : '');

  list.prepend(div);
}

async function enviarPedidoFerias(event) {
  event.preventDefault();
  var inicioEl = document.getElementById('feriasInicio');
  var fimEl = document.getElementById('feriasFim');
  var motivoEl = document.getElementById('feriasMotivo');
  if (!inicioEl || !fimEl || !motivoEl) return;

  var data_inicio = String(inicioEl.value || '').trim();
  var data_fim = String(fimEl.value || '').trim();
  var motivo = String(motivoEl.value || '').trim();

  if (!data_inicio || !data_fim) {
    showMsg('Preencha as datas de início e término.', false);
    return;
  }

  showMsg('Enviando pedido de férias...');
  try {
    var res = await fetch('solicitar_ferias.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ data_inicio: data_inicio, data_fim: data_fim, motivo: motivo })
    });

    var text = await res.text();
    var data = null;
    try {
      data = JSON.parse(text);
    } catch (e) {
      showMsg('Resposta inválida do servidor.', false);
      return;
    }

    if (!res.ok || !data.success) {
      showMsg(data.message || 'Não foi possível enviar o pedido.', false);
      return;
    }

    showMsg(data.message || 'Pedido enviado com sucesso.');
    renderFeriasItem(data.request || { data_inicio: data_inicio, data_fim: data_fim, motivo: motivo, status: 'pendente' });
    document.getElementById('feriasForm').reset();
  } catch (e) {
    console.error(e);
    showMsg('Erro de comunicação ao enviar pedido.', false);
  }
}

// busca notificações para este funcionário e exibe na tela
async function fetchNotifications() {
  try {
    const res = await fetch('get_notifications.php', { credentials: 'same-origin' });
    const data = await res.json();
    if (data.success) {
      renderNotifications(data);
    }
  } catch (e) {
    console.error('Erro ao buscar notificações', e);
  }
}

async function deleteSelectedSms() {
  if (!notificationCanDelete) {
    showMsg('Este histórico não permite exclusão.', false);
    return;
  }

  const ids = Array.from(selectedNotificationIds);
  if (!ids.length) {
    showMsg('Selecione pelo menos uma SMS para apagar.', false);
    return;
  }

  const confirmed = window.confirm(`Deseja apagar ${ids.length} SMS selecionada(s)?`);
  if (!confirmed) return;

  try {
    const res = await fetch('delete_sms.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ids: ids })
    });

    const data = await res.json();
    if (!res.ok || !data.success) {
      throw new Error(data.message || 'Erro ao apagar SMS.');
    }

    selectedNotificationIds.clear();
    showMsg(data.message || 'SMS apagadas com sucesso.');
    fetchNotifications();
  } catch (e) {
    console.error('Erro ao apagar histórico de SMS', e);
    showMsg(e.message || 'Erro ao apagar histórico de SMS.', false);
  }
}

// inicialização
window.addEventListener('load', () => {
  var feriasForm = document.getElementById('feriasForm');
  if (feriasForm) {
    feriasForm.addEventListener('submit', enviarPedidoFerias);
  }
  fetchNotifications();
  // fazer polling periódico para novas notificações
  setInterval(fetchNotifications, 15000);
});
</script>
</body>
</html>