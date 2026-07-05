# 📱 Guia Completo: Como Funciona o Envio de SMS e Como Integrar em Outro Projeto

## Índice
1. [Como Funciona (Explicação Técnica)](#como-funciona)
2. [Fluxo Completo de Dados](#fluxo-completo)
3. [Integração Passo-a-Passo](#integração-passo-a-passo)
4. [Exemplos de Implementação](#exemplos)
5. [Troubleshooting](#troubleshooting)

---

## 1. Como Funciona {#como-funciona}

### 1.1 Visão Geral do Sistema

O módulo SMS funciona em **3 camadas**:

```
┌─────────────────────────────────────────────────────────┐
│ CAMADA 1: FRONTEND (Admin / Dashboard)                  │
│ ├─ Admin clica botão "Enviar SMS"                       │
│ ├─ JavaScript coleta funcionários selecionados          │
│ └─ Envia POST para /api/employees/notify_employees.php │
└──────────────────────┬──────────────────────────────────┘
                       ↓
┌─────────────────────────────────────────────────────────┐
│ CAMADA 2: BACKEND (PHP / Validação)                     │
│ ├─ Valida sessão do usuário                             │
│ ├─ Busca dados do funcionário no BD                     │
│ ├─ Insere notificação interna (app vê)                  │
│ └─ Prepara lista para envio                             │
└──────────────────────┬──────────────────────────────────┘
                       ↓
┌─────────────────────────────────────────────────────────┐
│ CAMADA 3: SMS SENDER (Processamento)                    │
│ ├─ Normaliza telefone (937493326 → 351937493326)       │
│ ├─ Valida formato (9 ou 11-15 dígitos)                 │
│ ├─ Prepara payload JSON                                 │
│ └─ Envia cURL para API do Infobip                       │
└──────────────────────┬──────────────────────────────────┘
                       ↓
┌─────────────────────────────────────────────────────────┐
│ CAMADA 4: PROVEDOR (Infobip)                            │
│ ├─ Recebe requisição HTTPS                              │
│ ├─ Processa fila de SMS                                 │
│ ├─ Envia para operadora de telefone                     │
│ └─ Retorna status de entrega                            │
└──────────────────────┬──────────────────────────────────┘
                       ↓
┌─────────────────────────────────────────────────────────┐
│ CAMADA 5: HISTÓRICO (Auditoria)                         │
│ ├─ Registra cada SMS em sms_history                     │
│ ├─ Armazena: status, provider_message_id, erro          │
│ └─ Permite consultas e relatórios                       │
└─────────────────────────────────────────────────────────┘
```

### 1.2 Os 3 Arquivos Principais

#### **Arquivo 1: config/sms_config.php**
**Propósito:** Carregar configuração do Infobip

```php
// 1. Tenta carregar de variáveis de ambiente
$config = [
    'enabled' => getenv('INFOBIP_ENABLED') 
    'api_key' => getenv('INFOBIP_API_KEY')
    // ... etc
];

// 2. Se existir arquivo local, faz override (local vence)
if (file_exists(__DIR__ . '/sms_config.local.php')) {
    $localConfig = require __DIR__ . '/sms_config.local.php';
    $config = array_replace_recursive($config, $localConfig);
}

// 3. Retorna config final
return $config;
```

**Por que 2 formas?**
- **Variáveis de ambiente** = Para servidores em produção (mais seguro)
- **Arquivo local** = Para desenvolvimento local (prático)

---

#### **Arquivo 2: includes/sms_sender.php**
**Propósito:** 2 funções principais

**Função 1: `normalizeSmsPhone($phone)`**
```php
// Entrada: "937493326"
// Saída:   "351937493326"

// Lógica:
1. Remove tudo que não é número
   "937493326" → "937493326"

2. Se 9 dígitos → adiciona "351" (Portugal)
   "937493326" → "351937493326"

3. Se 11-15 dígitos → mantém como está
   "351937493326" → "351937493326"
   "447491163443" → "447491163443" (UK)

4. Se outra quantidade → retorna null (inválido)
   "123" → null ❌
```

**Por que normalizar?**
- Admin pode inserir telefone de várias formas
- Infobip precisa de formato internacional consistente
- Evita erros de envio

---

**Função 2: `sendInfobipSms($employees, $message, $config)`**
```php
// Entrada:
[
    ['id' => 1, 'name' => 'João', 'phone' => '937493326'],
    ['id' => 2, 'name' => 'Maria', 'phone' => '931234567']
]

// Processamento:
1. Valida se config está preenchida
   - api_key existe?
   - base_url existe?
   - enabled = true?

2. Para cada funcionário:
   a. Normaliza telefone
   b. Se inválido → skipped_count++
   c. Se válido → adiciona à lista de destinos

3. Monta payload JSON
{
    "messages": [{
        "destinations": [
            {"to": "351937493326"},
            {"to": "351931234567"}
        ],
        "sender": "447491163443",
        "content": {
            "text": "Olá, mensagem importante!"
        }
    }]
}

4. Envia cURL POST para Infobip
   URL: https://x19gll.api.infobip.com/sms/3/messages
   Headers: Authorization: App API_KEY
   
5. Recebe resposta e processa status
   - Sucesso → sent_count++
   - Falha → failed_count++ (guarda motivo)

6. Retorna objeto com resumo completo
{
    'configured': true,
    'sent_count': 2,
    'failed_count': 0,
    'skipped_count': 0,
    'details': [
        {
            'employee_id': 1,
            'status': 'sent',
            'provider_message_id': 'msg123'
        }
    ]
}
```

---

#### **Arquivo 3: api/employees/notify_employees.php**
**Propósito:** Endpoint que junta tudo

```
POST /api/employees/notify_employees.php
Content-Type: application/x-www-form-urlencoded

ids=[1,2,3]&message=Olá funcionários!

┌──────────────────────────────────────────┐
│ 1. VALIDA SESSÃO                         │
│    ├─ User_id existe na sessão?          │
│    ├─ Client_id existe?                  │
│    └─ Se não → erro 401 Unauthorized     │
└──────────────────────┬───────────────────┘
                       ↓
┌──────────────────────────────────────────┐
│ 2. BUSCA FUNCIONÁRIOS NO BD              │
│    SELECT id, name, phone FROM employees │
│    WHERE id IN (1,2,3)                   │
│    AND client_id = ?                     │
│    └─ Garante que admin vê só seu BD     │
└──────────────────────┬───────────────────┘
                       ↓
┌──────────────────────────────────────────┐
│ 3. INSERE NOTIFICAÇÃO INTERNA            │
│    INSERT INTO atividades_recentes       │
│    INSERT INTO notificacoes              │
│    └─ Funcionário vê no seu portal       │
└──────────────────────┬───────────────────┘
                       ↓
┌──────────────────────────────────────────┐
│ 4. ENVIA SMS REAL                        │
│    sendInfobipSms($employees, $msg, ..)  │
│    └─ Normaliza e envia para Infobip     │
└──────────────────────┬───────────────────┘
                       ↓
┌──────────────────────────────────────────┐
│ 5. REGISTRA HISTÓRICO                    │
│    INSERT INTO sms_history               │
│    ├─ employee_id                        │
│    ├─ phone (normalizado)                │
│    ├─ status (sent/failed/skipped)       │
│    ├─ provider_message_id                │
│    └─ error_message (se houver)          │
└──────────────────────┬───────────────────┘
                       ↓
┌──────────────────────────────────────────┐
│ 6. RETORNA JSON COM RESUMO               │
│    {                                     │
│      'success': true,                    │
│      'message': '3 enviados...',          │
│      'sms': {                             │
│        'sent_count': 3,                   │
│        'failed_count': 0,                 │
│        'details': [...]                  │
│      }                                    │
│    }                                      │
└──────────────────────────────────────────┘
```

---

## 2. Fluxo Completo de Dados {#fluxo-completo}

### 2.1 Exemplo Real: Admin envia SMS para 3 funcionários

```
PASSO 1: Admin no Dashboard
┌─────────────────────────────────────────┐
│ Painel Admin → Funcionários             │
│ ☑ João Silva (937493326)               │
│ ☑ Maria Ferreira (931234567)           │
│ ☑ Carlos Oliveira (938765432)          │
│                                         │
│ Mensagem: "Turno amanhã às 9h"         │
│ [Enviar SMS]                            │
└─────────────────────────────────────────┘
              ↓
PASSO 2: JavaScript coleta dados
┌─────────────────────────────────────────┐
│ Variáveis JS:                           │
│ ids = [15, 42, 87]                      │
│ message = "Turno amanhã às 9h"         │
│                                         │
│ fetch('/api/employees/notify_employees.php', {
│   method: 'POST',
│   body: new FormData({
│     ids: JSON.stringify([15, 42, 87]),
│     message: 'Turno amanhã às 9h'
│   })
│ })
└─────────────────────────────────────────┘
              ↓
PASSO 3: Backend - Validação
┌─────────────────────────────────────────┐
│ PHP verifica:                           │
│ $_SESSION['user_id'] = 12 ✓             │
│ $_SESSION['client_id'] = 3 ✓            │
│                                         │
│ Query BD:                               │
│ SELECT * FROM employees                 │
│ WHERE id IN (15, 42, 87)                │
│ AND client_id = 3                       │
│                                         │
│ Resultado:                              │
│ [                                       │
│   {id: 15, name: 'João', phone: '937...'
│   {id: 42, name: 'Maria', phone: '931...'
│   {id: 87, name: 'Carlos', phone: '938...'
│ ]                                       │
└─────────────────────────────────────────┘
              ↓
PASSO 4: Insere Notificação Interna
┌─────────────────────────────────────────┐
│ INSERT INTO atividades_recentes:        │
│ - message: "Turno amanhã às 9h"         │
│ - client_id: 3                          │
│ - employee_id: 15 ✓                     │
│ - timestamp: NOW()                      │
│                                         │
│ INSERT INTO atividades_recentes:        │
│ - employee_id: 42 ✓                     │
│                                         │
│ INSERT INTO atividades_recentes:        │
│ - employee_id: 87 ✓                     │
│                                         │
│ ✓ Funcionários verão no app em tempo!   │
└─────────────────────────────────────────┘
              ↓
PASSO 5: Normaliza Telefones
┌─────────────────────────────────────────┐
│ Função normalizeSmsPhone():              │
│                                         │
│ '937493326'                             │
│ → remove não-dígitos → '937493326'      │
│ → tem 9 dígitos → '351937493326' ✓     │
│                                         │
│ '931234567'                             │
│ → '931234567' (9) → '351931234567' ✓   │
│                                         │
│ '938765432'                             │
│ → '938765432' (9) → '351938765432' ✓   │
│                                         │
│ Resultado:                              │
│ [                                       │
│   '351937493326',                       │
│   '351931234567',                       │
│   '351938765432'                        │
│ ]                                       │
└─────────────────────────────────────────┘
              ↓
PASSO 6: Envia para Infobip
┌─────────────────────────────────────────┐
│ POST https://x19gll.api.infobip.com/   │
│         sms/3/messages                  │
│                                         │
│ Headers:                                │
│ Authorization: App 67ae14ee0af648b8a   │
│ Content-Type: application/json          │
│                                         │
│ Body (JSON):                            │
│ {                                       │
│   "messages": [{                        │
│     "destinations": [                   │
│       {"to": "351937493326"},           │
│       {"to": "351931234567"},           │
│       {"to": "351938765432"}            │
│     ],                                  │
│     "sender": "447491163443",           │
│     "content": {                        │
│       "text": "Turno amanhã às 9h"     │
│     }                                   │
│   }]                                    │
│ }                                       │
│                                         │
│ cURL timeout: 15 segundos               │
└─────────────────────────────────────────┘
              ↓
PASSO 7: Infobip Processa
┌─────────────────────────────────────────┐
│ Infobip recebe e retorna:               │
│                                         │
│ HTTP 200 OK                             │
│ {                                       │
│   "messages": [                         │
│     {                                   │
│       "to": "351937493326",             │
│       "messageId": "msg-123456",        │
│       "status": {                       │
│         "groupName": "SENT",            │
│         "description": "Sent to SMS.." │
│       }                                 │
│     },                                  │
│     {                                   │
│       "to": "351931234567",             │
│       "messageId": "msg-123457",        │
│       "status": {                       │
│         "groupName": "SENT"             │
│       }                                 │
│     },                                  │
│     {                                   │
│       "to": "351938765432",             │
│       "messageId": "msg-123458",        │
│       "status": {                       │
│         "groupName": "SENT"             │
│       }                                 │
│     }                                   │
│   ]                                     │
│ }                                       │
└─────────────────────────────────────────┘
              ↓
PASSO 8: Registra Histórico
┌─────────────────────────────────────────┐
│ INSERT INTO sms_history:                │
│                                         │
│ ├─ client_id: 3                         │
│ ├─ employee_id: 15                      │
│ ├─ phone: '351937493326'                │
│ ├─ message: 'Turno amanhã às 9h'       │
│ ├─ provider: 'infobip'                  │
│ ├─ provider_message_id: 'msg-123456'   │
│ ├─ status: 'sent' ✓                     │
│ ├─ error_message: null                  │
│ └─ sent_at: 2026-04-28 10:30:45        │
│                                         │
│ [REPEAT para employee_id 42 e 87]       │
│                                         │
│ ✓ Histórico registrado para auditoria   │
└─────────────────────────────────────────┘
              ↓
PASSO 9: Retorna Sucesso ao Admin
┌─────────────────────────────────────────┐
│ JavaScript recebe:                      │
│                                         │
│ {                                       │
│   "success": true,                      │
│   "message": "Notificações internas    │
│              enviadas para 3            │
│              funcionário(s). SMS real:  │
│              3 enviado(s), 0 falha(s),  │
│              0 ignorado(s).",           │
│   "sms": {                              │
│     "configured": true,                 │
│     "sent_count": 3,                    │
│     "failed_count": 0,                  │
│     "skipped_count": 0,                 │
│     "details": [                        │
│       {                                 │
│         "employee_id": 15,              │
│         "employee_name": "João",        │
│         "phone": "351937493326",        │
│         "status": "sent",               │
│         "provider_message_id": "msg-123456"
│       },                                │
│       ... (mais 2)                      │
│     ]                                   │
│   }                                     │
│ }                                       │
│                                         │
│ Admin vê Toast Verde:                   │
│ "✓ SMS enviado com sucesso!"           │
└─────────────────────────────────────────┘
```

---

## 3. Integração Passo-a-Passo {#integração-passo-a-passo}

### 3.1 Preparar Projeto de Destino

Seu projeto precisa ter:

```
seu_projeto/
├── config/
│   └── db_connect.php          # Conexão PDO com MySQL
├── includes/
│   └── activity_logger.php     # Helper de logs (pode estar vazio)
├── api/
│   └── employees/
│       └── (será aqui)
└── app/
    └── (se tiver portal de funcionário)
```

**Banco de dados obrigatório:**
```sql
CREATE TABLE employees (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100),
    phone VARCHAR(20),
    client_id INT,
    ...
);
```

---

### 3.2 Passo 1: Copiar Arquivo de Configuração

**1. Crie a pasta e o arquivo:**

```bash
# No seu projeto
mkdir -p config
touch config/sms_config.php
```

**2. Copie este conteúdo:**

```php
<?php
// config/sms_config.php

$defaultSmsConfig = [
    'infobip' => [
        'enabled' => filter_var(getenv('INFOBIP_ENABLED') ?: '0', FILTER_VALIDATE_BOOLEAN),
        'base_url' => rtrim((string)(getenv('INFOBIP_BASE_URL') ?: ''), '/'),
        'api_key' => trim((string)(getenv('INFOBIP_API_KEY') ?: '')),
        'sender' => trim((string)(getenv('INFOBIP_SENDER') ?: '')),
        'timeout_seconds' => max(5, (int)(getenv('INFOBIP_TIMEOUT') ?: 15)),
    ],
];

// Suporte a arquivo local com credenciais (não versionar!)
$localConfigPath = __DIR__ . '/sms_config.local.php';
if (file_exists($localConfigPath)) {
    $localSmsConfig = require $localConfigPath;
    if (is_array($localSmsConfig)) {
        $defaultSmsConfig = array_replace_recursive($defaultSmsConfig, $localSmsConfig);
    }
}

return $defaultSmsConfig;
```

**3. Crie arquivo de configuração local (não versione!):**

```bash
# No seu projeto
touch config/sms_config.local.php
```

```php
<?php
// config/sms_config.local.php
// ⚠️ ADICIONE AO .gitignore!

return [
    'infobip' => [
        'enabled' => true,
        'base_url' => 'https://x19gll.api.infobip.com',
        'api_key' => 'SUA_API_KEY_DO_INFOBIP_AQUI',
        'sender' => 'SEU_SENDER_ID_DO_INFOBIP_AQUI',
        'timeout_seconds' => 15,
    ],
];
```

**4. Adicione ao .gitignore:**

```bash
# .gitignore
config/sms_config.local.php
```

---

### 3.3 Passo 2: Copiar Funções de SMS

**1. Crie pasta:**

```bash
mkdir -p includes
touch includes/sms_sender.php
```

**2. Copie todo o conteúdo de `sms_sender.php` do módulo:**

[Veja o arquivo completo em sms_module/includes/sms_sender.php]

Funções incluídas:
- `normalizeSmsPhone($phone)`
- `sendInfobipSms($employees, $message, $config)`

---

### 3.4 Passo 3: Copiar Endpoint de Envio

**1. Crie pasta:**

```bash
mkdir -p api/employees
touch api/employees/notify_employees.php
```

**2. Copie o arquivo completo:**

[Veja o arquivo completo em sms_module/api/notify_employees.php]

**Certifique-se que:**
- `require_once __DIR__ . '/../../config/db_connection.php'` existe
- `require_once __DIR__ . '/../../includes/sms_sender.php'` existe
- Seu `db_connection.php` está configurado e $pdo funciona

---

### 3.5 Passo 4: Criar Tabela de Histórico

**1. Execute no MySQL:**

```sql
CREATE TABLE IF NOT EXISTS sms_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    employee_id INT NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    message TEXT NOT NULL,
    provider VARCHAR(50) DEFAULT NULL,
    provider_message_id VARCHAR(120) DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    error_message TEXT DEFAULT NULL,
    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sms_history_client_employee (client_id, employee_id),
    KEY idx_sms_history_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Ou execute via PHP:**

```php
<?php
$pdo = new PDO('mysql:host=localhost;dbname=seu_db', 'usuario', 'senha');

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS sms_history (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        employee_id INT NOT NULL,
        phone VARCHAR(20) DEFAULT NULL,
        message TEXT NOT NULL,
        provider VARCHAR(50) DEFAULT NULL,
        provider_message_id VARCHAR(120) DEFAULT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        error_message TEXT DEFAULT NULL,
        sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_sms_history_client_employee (client_id, employee_id),
        KEY idx_sms_history_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
```

---

### 3.6 Passo 5: Testar Integração

**1. Criar script de teste:**

```php
<?php
// test_sms.php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['client_id'] = 1;

require_once 'config/db_connection.php';
require_once 'includes/sms_sender.php';

$config = require 'config/sms_config.php';

// Teste 1: Normalização de telefone
echo "=== TESTE 1: Normalização ===\n";
$phones = ['937493326', '351937493326', '123'];
foreach ($phones as $phone) {
    $normalized = normalizeSmsPhone($phone);
    echo "  '$phone' → '" . ($normalized ?? 'null') . "'\n";
}

// Teste 2: Envio para 1 funcionário
echo "\n=== TESTE 2: Envio Real ===\n";
$testEmployees = [
    ['id' => 1, 'name' => 'Teste', 'phone' => '937493326']
];

$result = sendInfobipSms($testEmployees, 'Teste SMS', $config);

echo "  Configurado: " . ($result['configured'] ? 'SIM' : 'NÃO') . "\n";
echo "  Enviados: " . $result['sent_count'] . "\n";
echo "  Falhas: " . $result['failed_count'] . "\n";
echo "  Ignorados: " . $result['skipped_count'] . "\n";

if (!empty($result['error'])) {
    echo "  Erro: " . $result['error'] . "\n";
}

print_r($result['details']);
```

**2. Executar:**

```bash
php test_sms.php
```

---

## 4. Exemplos de Implementação {#exemplos}

### 4.1 Exemplo: Enviar SMS para Todos os Funcionários

```php
<?php
// send_sms_all.php

session_start();
$_SESSION['user_id'] = 1;
$_SESSION['client_id'] = 1;

require_once 'config/db_connection.php';
require_once 'includes/sms_sender.php';

$config = require 'config/sms_config.php';

// Buscar todos os funcionários
$stmt = $pdo->prepare("
    SELECT id, name, phone FROM employees 
    WHERE client_id = ?
    AND phone IS NOT NULL
    LIMIT 50
");
$stmt->execute([$_SESSION['client_id']]);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Enviar SMS
$result = sendInfobipSms(
    $employees,
    "Aviso importante: Consulte seu supervisor",
    $config
);

// Registrar no histórico
foreach ($result['details'] as $detail) {
    $pdo->prepare("
        INSERT INTO sms_history 
        (client_id, employee_id, phone, message, provider, 
         provider_message_id, status, error_message, sent_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ")->execute([
        $_SESSION['client_id'],
        $detail['employee_id'],
        $detail['phone'],
        "Aviso importante: Consulte seu supervisor",
        'infobip',
        $detail['provider_message_id'] ?? null,
        $detail['status'],
        $detail['error'] ?? null
    ]);
}

echo "SMS enviados: " . $result['sent_count'];
```

---

### 4.2 Exemplo: Enviar SMS com Filtro

```php
<?php
// send_sms_filtered.php

session_start();

require_once 'config/db_connection.php';
require_once 'includes/sms_sender.php';

$config = require 'config/sms_config.php';

// Buscar apenas funcionários do departamento "Limpeza"
$stmt = $pdo->prepare("
    SELECT e.id, e.name, e.phone 
    FROM employees e
    WHERE e.client_id = ?
    AND e.department = ?
    AND e.phone IS NOT NULL
");
$stmt->execute([$_SESSION['client_id'], 'Limpeza']);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

$result = sendInfobipSms(
    $employees,
    "Limpeza amanhã às 6h da manhã",
    $config
);

echo "Enviados: " . $result['sent_count'] . "\n";
echo "Falhas: " . $result['failed_count'] . "\n";
```

---

### 4.3 Exemplo: Enviar SMS Agendado

```php
<?php
// scheduled_sms.php
// Execute via cron: */5 * * * * php /var/www/scheduled_sms.php

require_once 'config/db_connection.php';
require_once 'includes/sms_sender.php';

$config = require 'config/sms_config.php';

// Buscar SMS agendados para agora
$stmt = $pdo->prepare("
    SELECT id, client_id, employee_ids, message 
    FROM sms_scheduled
    WHERE scheduled_at <= NOW()
    AND status = 'pending'
");
$stmt->execute();
$scheduled = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($scheduled as $sms) {
    $employeeIds = json_decode($sms['employee_ids'], true);
    
    // Buscar dados dos funcionários
    $placeholders = implode(',', array_fill(0, count($employeeIds), '?'));
    $stmt2 = $pdo->prepare("
        SELECT id, name, phone FROM employees
        WHERE id IN ($placeholders)
    ");
    $stmt2->execute($employeeIds);
    $employees = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    // Enviar
    $result = sendInfobipSms($employees, $sms['message'], $config);
    
    // Marcar como enviado
    $pdo->prepare("
        UPDATE sms_scheduled 
        SET status = 'sent', sent_at = NOW()
        WHERE id = ?
    ")->execute([$sms['id']]);
}
```

---

## 5. Troubleshooting {#troubleshooting}

### Problema 1: "SMS real não configurado"

**Causa:** Arquivo `sms_config.local.php` não existe ou está vazio

**Solução:**
```bash
# 1. Verificar se arquivo existe
ls -la config/sms_config.local.php

# 2. Se não existe, criar
cat > config/sms_config.local.php << 'EOF'
<?php
return [
    'infobip' => [
        'enabled' => true,
        'base_url' => 'https://x19gll.api.infobip.com',
        'api_key' => 'SEU_API_KEY',
        'sender' => 'SEU_SENDER',
        'timeout_seconds' => 15,
    ],
];
EOF

# 3. Verificar conteúdo
cat config/sms_config.local.php
```

---

### Problema 2: "Nenhum telefone válido para envio"

**Causa:** Funcionários não têm phone preenchido ou formato inválido

**Solução:**
```sql
-- 1. Ver funcionários e telefones
SELECT id, name, phone, LENGTH(REPLACE(phone, '-', '')) as digits
FROM employees
WHERE client_id = 1;

-- 2. Testar normalização PHP
<?php
require_once 'includes/sms_sender.php';

$testPhones = ['937493326', '123', '', null];
foreach ($testPhones as $phone) {
    $result = normalizeSmsPhone($phone ?? '');
    echo "'" . ($phone ?? 'null') . "' → " . ($result ?? 'null') . "\n";
}
// Esperado:
// '937493326' → '351937493326'
// '123' → null
// '' → null
// 'null' → null
```

---

### Problema 3: "Falha ao comunicar com Infobip"

**Causa:** Erro na conexão com API ou credenciais inválidas

**Solução:**
```php
<?php
// test_infobip_connection.php

$config = require 'config/sms_config.php';
$infobip = $config['infobip'];

echo "Configuração:\n";
echo "  Enabled: " . ($infobip['enabled'] ? 'SIM' : 'NÃO') . "\n";
echo "  Base URL: " . $infobip['base_url'] . "\n";
echo "  API Key: " . substr($infobip['api_key'], 0, 20) . "...\n";
echo "  Sender: " . $infobip['sender'] . "\n\n";

// Testar conexão
$ch = curl_init($infobip['base_url'] . '/sms/3/messages');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: App ' . $infobip['api_key'],
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'messages' => [[
            'destinations' => [['to' => '351937493326']],
            'sender' => $infobip['sender'],
            'content' => ['text' => 'Test']
        ]]
    ]),
    CURLOPT_TIMEOUT => 10,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "Resultado:\n";
echo "  HTTP Status: " . $httpCode . "\n";
echo "  cURL Error: " . ($curlError ?: 'nenhum') . "\n";
echo "  Response: " . (strlen($response) > 100 ? substr($response, 0, 100) . "..." : $response) . "\n";
```

---

### Problema 4: SMS Enviados mas Status=Failed

**Causa:** Telefone inválido para operadora, ou SMS rejeitado

**Solução:**
```sql
-- Ver erros no histórico
SELECT 
    employee_id, 
    phone, 
    status, 
    error_message, 
    sent_at
FROM sms_history
WHERE status = 'failed'
ORDER BY sent_at DESC;

-- Analisar padrão de falhas
SELECT 
    error_message,
    COUNT(*) as total
FROM sms_history
WHERE status = 'failed'
GROUP BY error_message;
```

---

### Problema 5: "Permissão Negada" ao Escrever Log

**Causa:** Arquivo local sem permissão

**Solução:**
```bash
# Linux/Mac
chmod 600 config/sms_config.local.php
chmod 755 config/

# Windows PowerShell (como Admin)
icacls "config\sms_config.local.php" /inheritance:r /grant:r "%USERNAME%:F"
```

---

## Checklist de Integração Completa

- [ ] **Arquivo sms_config.php** copiado em `config/`
- [ ] **Arquivo sms_config.local.php** criado com credenciais Infobip
- [ ] **Arquivo adicionado ao .gitignore**
- [ ] **Arquivo sms_sender.php** copiado em `includes/`
- [ ] **Arquivo notify_employees.php** copiado em `api/employees/`
- [ ] **Tabela sms_history** criada no banco
- [ ] **db_connection.php** funciona (testado)
- [ ] **Script de teste executado** com sucesso
- [ ] **Primeiro SMS enviado** com sucesso
- [ ] **Histórico registrado** em sms_history
- [ ] **Admin vê resumo** com send_count > 0

---

## Conclusão

O módulo SMS é modular e pronto para integrar em qualquer projeto PHP com:
- ✅ Banco de dados MySQL (tabela `employees`)
- ✅ PDO configurado
- ✅ Acesso a variáveis de sessão
- ✅ Conta Infobip com API key válida

Seguindo os passos acima, você consegue integrar em qualquer projeto em menos de 30 minutos! 🚀
