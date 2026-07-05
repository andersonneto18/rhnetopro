# RHNeto Pro — Melhorias e Roadmap de Funcionalidades

> Documento de trabalho — baseado em auditoria completa do código fonte  
> Data: Junho 2026

---

## Índice

1. [Problemas Críticos de Segurança](#1-problemas-críticos-de-segurança)
2. [Funcionalidades Incompletas ou Não Implementadas](#2-funcionalidades-incompletas-ou-não-implementadas)
3. [Melhorias no Portal do Funcionário](#3-melhorias-no-portal-do-funcionário)
4. [Melhorias no Painel Administrativo](#4-melhorias-no-painel-administrativo)
5. [Módulo de Relatórios](#5-módulo-de-relatórios)
6. [Gestão de Férias e Ausências](#6-gestão-de-férias-e-ausências)
7. [Folha de Pagamento](#7-folha-de-pagamento)
8. [Segurança e Autenticação](#8-segurança-e-autenticação)
9. [Performance e Arquitectura](#9-performance-e-arquitectura)
10. [Conformidade e RGPD](#10-conformidade-e-rgpd)
11. [Novas Funcionalidades para Restauração](#11-novas-funcionalidades-para-restauração)
12. [Prioridades de Implementação](#12-prioridades-de-implementação)

---

## 1. Problemas Críticos de Segurança

> Estas correcções devem ser feitas **antes** de qualquer nova funcionalidade.

---

### 1.1 Endpoint público de ponto sem autenticação

**Ficheiro afectado**: `api/gorjetas/presenca/registrar_ponto_public.php`  
**Severidade**: Crítica

**Problema**: O endpoint aceita qualquer pedido com apenas um `funcionario_id`. Qualquer pessoa que saiba (ou adivinhe) o ID de um funcionário consegue registar entradas e saídas sem qualquer credencial.

```json
// Pedido actual — sem autenticação
POST /api/gorjetas/presenca/registrar_ponto_public.php
{ "tipo": "entrada", "funcionario_id": 1 }
// Resultado: ponto registado para o funcionário 1
```

**O que fazer**:
1. Adicionar validação de PIN obrigatória ao pedido (`funcionario_id` + `pin`)
2. Verificar o PIN com `password_verify()` contra `pin_hash` na base de dados
3. Remover as linhas de debug (`display_errors`, `error_reporting(E_ALL)`) do código de produção
4. Remover `raw` e inputs do utilizador das respostas de erro (fuga de informação)

**Ficheiro novo sugerido**: criar `api/gorjetas/presenca/registrar_ponto_public_v2.php` com:
```php
// Receber: funcionario_id + pin + tipo
// Verificar: password_verify($pin, $employee['pin_hash'])
// Só então registar o ponto
```

---

### 1.2 Ausência de rate limiting

**Severidade**: Alta

**Problema**: Nenhum endpoint tem limite de tentativas. Login, PIN e APIs públicas podem ser atacados por força bruta sem qualquer bloqueio.

**O que fazer**:
1. Criar tabela `login_attempts` (ip, endpoint, tentativas, bloqueado_ate)
2. Implementar helper `checkRateLimit(string $key, int $maxAttempts, int $windowSeconds): bool`
3. Aplicar nos endpoints:
   - `admin/controllers/login_process.php` — máximo 5 tentativas / 15 min por IP
   - `app/employee_auth.php` — máximo 5 tentativas / 10 min por funcionário
   - `registrar_ponto_public.php` — máximo 10 tentativas / 5 min por IP

```sql
CREATE TABLE login_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(255) NOT NULL,   -- ip ou funcionario_id
    endpoint VARCHAR(100) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_identifier_endpoint (identifier, endpoint, attempted_at)
);
```

---

### 1.3 CSRF não validado

**Severidade**: Alta

**Problema**: O token CSRF é gerado em `admin/dashboard.php` mas **nunca é verificado**. Os formulários no portal do funcionário não têm token. Qualquer site externo pode submeter formulários em nome de um utilizador autenticado.

**O que fazer**:
1. Criar helper centralizado `includes/csrf.php`:
```php
function csrfToken(): string { ... }
function verifyCsrfToken(string $token): bool { ... }
```
2. Incluir token em todos os formulários (`<input type="hidden" name="csrf_token">`)
3. Verificar token no início de cada controller e endpoint de escrita (POST/PUT/DELETE)
4. Aplicar no portal do funcionário: gorjetas, justificativas, férias, troca de turno, alterar PIN

---

### 1.4 Informação de debug exposta em produção

**Ficheiros afectados**: `registrar_ponto_public.php`, possivelmente outros  
**Severidade**: Média

**O que fazer**:
1. Remover `ini_set('display_errors', 1)` e `error_reporting(E_ALL)` de todos os ficheiros de produção
2. Criar ficheiro `config/environment.php` com flag `APP_DEBUG` que controla o nível de erros
3. Em produção: erros para log de ficheiro, nunca para o browser

---

## 2. Funcionalidades Incompletas ou Não Implementadas

---

### 2.1 Relatórios — apenas UI, sem backend

**Ficheiro**: `admin/views/relatorios.php`  
**Estado actual**: Os 5 cards de relatório existem mas o clique apenas executa `alert()` — nenhum dado real é gerado.

**O que fazer**: Ver secção [5. Módulo de Relatórios](#5-módulo-de-relatórios) para implementação completa.

---

### 2.2 Exportação PDF/Excel não implementada

**Estado actual**: Não existe nenhuma biblioteca de geração de PDF ou Excel no projecto (sem TCPDF, FPDF, PhpSpreadsheet, Dompdf, etc.).

**O que implementar**:

**Passo 1 — Instalar via Composer**:
```bash
composer require tecnickcom/tcpdf
composer require phpoffice/phpspreadsheet
```

**Passo 2 — Criar endpoint genérico**:
```
api/exports/
├── export_employees.php        # Lista de funcionários (PDF + Excel)
├── export_payroll.php          # Folha de pagamento do mês (PDF)
├── export_attendance.php       # Relatório de presenças (Excel)
├── export_gorjetas.php         # Relatório de gorjetas (Excel)
└── export_audit_log.php        # Log de auditoria (Excel)
```

**Passo 3 — Exemplo mínimo para folha de pagamento em PDF**:
```php
// Recebe: client_id (da sessão), fiscal_year, fiscal_month
// Gera: tabela com nome, bruto, SS, IRS, líquido de cada funcionário
// Devolve: PDF para download ou inline
```

---

### 2.3 Gestão de férias sem API dedicada

**Estado actual**: A aprovação/rejeição de férias é feita por formulário dentro de `admin/dashboard.php`. Não existe endpoint REST para o admin listar ou gerir férias.

**O que criar**:
```
api/ferias/
├── create_ferias.php           # Já existe como app/solicitar_ferias.php (mover)
├── get_ferias.php              # Listar pedidos (por funcionário ou globais)
├── update_ferias.php           # Aprovar / rejeitar / editar datas
├── delete_ferias.php           # Cancelar pedido (pelo funcionário ou admin)
└── get_ferias_calendar.php     # Dados para calendário de férias
```

---

### 2.4 Subscrição Stripe não validada no dashboard

**Ficheiro**: `includes/premium_access.php`  
**Estado actual**: A função `has_premium_access()` existe mas **não está a ser chamada** em nenhuma página do dashboard (confirmado por pesquisa no código).

**O que fazer**:
1. Adicionar `require_once '../includes/premium_access.php';` no início de `admin/dashboard.php`
2. Fazer o check após verificar a sessão:
```php
$user = getCurrentUser($pdo, $_SESSION['user_id']);
if (!has_premium_access($user)) {
    header('Location: ../planos/index.php?expired=1');
    exit;
}
```
3. Adicionar cache de 1 hora no resultado (evitar chamadas Stripe em cada página):
```php
// Guardar em $_SESSION['subscription_valid_until']
```

---

### 2.5 Alterações críticas de funcionário sem workflow de aprovação completo

**Ficheiro**: `api/employees/update_employee.php`  
**Estado actual**: Existe uma tabela `employee_change_requests` mas pedidos pendentes não têm prazo de expiração e não há notificação ao gestor quando são criados.

**O que adicionar**:
1. Campo `expires_at` na tabela `employee_change_requests` (ex: 30 dias)
2. Notificação in-app ao admin quando um pedido é criado
3. Secção dedicada no dashboard para aprovar/rejeitar pedidos pendentes
4. Email automático de notificação (opcional, requer configuração SMTP)

---

## 3. Melhorias no Portal do Funcionário

---

### 3.1 Recibo de vencimento

**Prioridade**: Alta  
**O funcionário não consegue ver nem descarregar o seu recibo de vencimento.**

**O que criar**:
- Endpoint `api/employees/get_payslip.php` que devolve os dados da `folha_pagamento` do funcionário autenticado
- Secção "Recibos de Vencimento" no portal com listagem por mês/ano
- Botão "Descarregar PDF" que gera o recibo com: nome, NIF, período, bruto, descontos, líquido, SS, IRS

```
Portal → Recibos → [Janeiro 2026] [Fevereiro 2026] ...
                    [↓ PDF]         [↓ PDF]
```

---

### 3.2 Saldo de férias

**Prioridade**: Alta  
**O funcionário não sabe quantos dias de férias tem disponíveis.**

**O que criar**:
1. Tabela `ferias_saldo`:
```sql
CREATE TABLE ferias_saldo (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    ano INT NOT NULL,
    dias_direito INT NOT NULL DEFAULT 22,
    dias_gozados INT NOT NULL DEFAULT 0,
    dias_pendentes INT NOT NULL DEFAULT 0,
    dias_disponiveis INT GENERATED ALWAYS AS (dias_direito - dias_gozados - dias_pendentes),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_saldo (client_id, employee_id, ano)
);
```
2. Mostrar no portal:
```
Férias 2026
Direito: 22 dias | Gozados: 5 | Pendentes: 3 | Disponíveis: 14
```

---

### 3.3 Notificações em tempo real

**Prioridade**: Média  
**Estado actual**: O portal lê notificações apenas ao carregar a página — sem actualização em tempo real.

**O que implementar**:
1. Polling leve (a cada 30s) via `setInterval` que chama endpoint `api/employees/get_notifications_count.php`
2. Mostrar badge com contador de não lidas no ícone de notificações
3. Marcar como lida ao abrir

```javascript
setInterval(async () => {
    const res = await fetch('../api/employees/get_notifications_count.php');
    const { count } = await res.json();
    document.querySelector('.notification-badge').textContent = count;
}, 30000);
```

---

### 3.4 Histórico de ponto completo

**Prioridade**: Média  
**Estado actual**: O portal mostra apenas os 10 últimos registos de ponto.

**O que adicionar**:
1. Filtro por mês/ano no histórico de ponto
2. Cálculo de horas trabalhadas por dia (entrada vs saída)
3. Indicação de dias com faltas ou ausência de registo
4. Total de horas do mês

---

### 3.5 Troca de turno — melhorias

**Estado actual**: O formulário de troca de turno existe no portal mas o JavaScript de decisão (`btn-turno-swap-decision`) precisa de ser verificado.

**O que adicionar**:
1. Verificar e completar o handler JS para aceitar/rejeitar trocas
2. Validar que os dois funcionários têm turnos compatíveis antes de permitir o pedido
3. Notificação automática ao colega quando o pedido é criado
4. Notificação ao admin quando uma troca é aprovada (muda a escala)

---

## 4. Melhorias no Painel Administrativo

---

### 4.1 Calendário de turnos visual

**Prioridade**: Alta  
**Estado actual**: Os turnos são apresentados numa tabela simples. Não existe vista de calendário.

**O que implementar**:
- Integrar biblioteca [FullCalendar.js](https://fullcalendar.io/) (open source)
- Vista semana e mês com turnos coloridos por funcionário
- Arrastar e largar para mover turnos
- Click para criar novo turno numa data

```html
<!-- Dependência a adicionar -->
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>
```

---

### 4.2 Dashboard com KPIs em tempo real

**Prioridade**: Alta  
**Estado actual**: O dashboard não mostra indicadores de gestão consolidados.

**Indicadores a adicionar**:
```
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│ Funcionários    │ │ Presentes hoje  │ │ Faltas hoje     │ │ Gorjetas mês    │
│ Activos: 24     │ │ 19 / 24  (79%)  │ │ 3 por justif.   │ │ € 1.240,00      │
└─────────────────┘ └─────────────────┘ └─────────────────┘ └─────────────────┘
```

Endpoint necessário: `api/dashboard/get_kpis.php` que agrega dados do dia actual.

---

### 4.3 Log de auditoria com before/after

**Ficheiro**: `includes/activity_logger.php`  
**Estado actual**: O log regista que algo aconteceu mas não guarda os valores anteriores e posteriores.

**O que fazer**:

1. Alterar a tabela `atividades_recentes`:
```sql
ALTER TABLE atividades_recentes
    ADD COLUMN user_id INT UNSIGNED NULL AFTER client_id,
    ADD COLUMN old_value JSON NULL AFTER descricao,
    ADD COLUMN new_value JSON NULL AFTER old_value;
```

2. Actualizar a função `logActivity()`:
```php
function logActivity(
    PDO $pdo,
    int $clientId,
    int $userId,         // quem fez
    string $tipo,
    string $descricao,
    array $oldValue = [],  // valor anterior
    array $newValue = []   // valor novo
): void
```

---

### 4.4 Gestão de departamentos

**Estado actual**: O campo `department` em `employees` é texto livre — sem lista controlada.

**O que criar**:
```sql
CREATE TABLE departamentos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    nome VARCHAR(100) NOT NULL,
    cor VARCHAR(7) DEFAULT '#3498db',
    ativo TINYINT(1) DEFAULT 1,
    UNIQUE KEY uq_dept (client_id, nome)
);
```

- Dropdown de departamentos no formulário de funcionário
- CRUD de departamentos no painel admin
- Filtro por departamento em todas as listagens

---

### 4.5 Gestão de cargos (posições)

**Igual ao ponto 4.4** mas para o campo `position`. Criar tabela `cargos` para evitar inconsistências de texto.

---

### 4.6 Pedidos de alteração pendentes — secção dedicada

**Estado actual**: Os pedidos de alteração de dados críticos (salário, contrato) ficam em `employee_change_requests` mas não existe interface para os ver e aprovar.

**O que criar**:
- Nova secção "Pendentes" no menu do dashboard
- Tabela com: funcionário, campo alterado, valor anterior, valor proposto, data do pedido
- Botões aprovar / rejeitar com motivo opcional
- Badge de contador no menu (ex: "Pendentes (3)")

---

## 5. Módulo de Relatórios

> O módulo actual tem apenas UI sem qualquer dado real. Esta secção define o que implementar.

---

### 5.1 Relatório de Presenças

**Endpoint a criar**: `api/reports/attendance_report.php`

**Parâmetros**: `client_id`, `mes`, `ano`, `department` (opcional), `employee_id` (opcional)

**Dados devolvidos**:
```json
{
  "employees": [
    {
      "name": "João Silva",
      "department": "Sala",
      "dias_presentes": 20,
      "dias_ausentes": 2,
      "dias_justificados": 1,
      "taxa_assiduidade": 91.3
    }
  ],
  "total_dias_uteis": 23,
  "media_assiduidade": 87.5
}
```

---

### 5.2 Relatório de Atrasos

**Endpoint a criar**: `api/reports/delays_report.php`

**Lógica**: Cruzar `registros_ponto` (hora de entrada real) com `turnos` (hora de início agendada). Se entrada > início do turno + margem (ex: 5 min) → atraso.

**Dados devolvidos**: por funcionário, número de atrasos, minutos totais de atraso, dias com atraso.

---

### 5.3 Relatório de Gorjetas

**Endpoint a criar**: `api/reports/tips_report.php`

**Dados devolvidos**: total por funcionário, total por mês, média diária, gorjetas pendentes vs confirmadas.

---

### 5.4 Relatório de Folha de Pagamento

**Endpoint a criar**: `api/reports/payroll_report.php`

**Dados devolvidos**: por funcionário — bruto, SS trabalhador, SS empresa, IRS, líquido; totais por departamento; custo total da empresa no mês.

---

### 5.5 Ranking de Assiduidade

**Endpoint a criar**: `api/reports/attendance_ranking.php`

Ordenar funcionários por taxa de assiduidade descendente. Útil para avaliação de desempenho.

---

### 5.6 Implementação do frontend de relatórios

**Substituir o `alert()` actual** por:
1. Modal com tabela de dados
2. Filtros de data, departamento e funcionário
3. Botão "Exportar PDF" e "Exportar Excel"
4. Gráfico de barras simples (pode usar Chart.js, já é gratuito e leve)

```html
<!-- Adicionar ao layout -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
```

---

## 6. Gestão de Férias e Ausências

---

### 6.1 Funcionalidades em falta

**Estado actual confirmado por auditoria de código**:
- Não existe endpoint DELETE para férias
- Não existe endpoint UPDATE para editar datas após submissão
- Não existe validação de datas no passado
- Não existe detecção de conflito com turnos agendados
- O funcionário não recebe notificação quando a sua férias é aprovada/rejeitada

**O que implementar**:

#### Validações ao criar pedido de férias
```php
// 1. Data início não pode ser no passado
if (strtotime($data_inicio) < strtotime('today')) {
    return error('Data de início não pode ser no passado');
}

// 2. Mínimo de antecedência (ex: 7 dias)
if (strtotime($data_inicio) < strtotime('+7 days')) {
    return error('Pedido deve ser feito com 7 dias de antecedência');
}

// 3. Verificar conflito com turnos agendados no período
$turnosNoperiodo = getTurnosNoPeriodo($employee_id, $data_inicio, $data_fim);
if (!empty($turnosNoperiodo)) {
    return warning('Existem ' . count($turnosNoperiodo) . ' turnos agendados neste período');
}

// 4. Verificar saldo disponível
$saldo = getSaldoFerias($employee_id, $ano);
$diasPedidos = diasUteis($data_inicio, $data_fim);
if ($diasPedidos > $saldo['dias_disponiveis']) {
    return error('Saldo insuficiente. Disponível: ' . $saldo['dias_disponiveis'] . ' dias');
}
```

#### Notificação ao funcionário após decisão
```php
// Em update_ferias.php, após aprovar/rejeitar:
notificarFuncionario($employee_id, [
    'titulo' => 'Pedido de férias ' . ($status === 'aprovada' ? 'aprovado' : 'rejeitado'),
    'mensagem' => 'O seu pedido de férias de ' . $data_inicio . ' a ' . $data_fim . ' foi ' . $status . '.'
]);
```

---

### 6.2 Calendário de férias da equipa

**Nova funcionalidade** no painel admin: vista de calendário mensal com todas as férias aprovadas sobrepostas, para facilitar planeamento.

```
Julho 2026
┌──────┬──────┬──────┬──────┬──────┬──────┬──────┐
│  Seg │  Ter │  Qua │  Qui │  Sex │  Sáb │  Dom │
├──────┼──────┼──────┼──────┼──────┼──────┼──────┤
│      │  1   │  2   │  3   │  4   │  5   │  6   │
│      │[João]│[João]│[João]│[João]│      │      │
├──────┼──────┼──────┼──────┼──────┼──────┼──────┤
│  7   │  8   │  9   │ 10   │ 11   │ 12   │ 13   │
│[Ana] │[Ana] │[Ana] │      │      │      │      │
```

---

### 6.3 Tipos de ausência

**Nova tabela**: `tipos_ausencia` para além das férias normais:

| Tipo | Descrição | Pago? | Requer documento |
|------|-----------|-------|-----------------|
| ferias | Férias anuais | Sim | Não |
| doenca | Baixa médica | Parcial | Sim (atestado) |
| nojo | Licença de luto | Sim | Sim |
| casamento | Licença de casamento | Sim | Sim |
| maternidade | Licença de maternidade | Sim | Sim |
| formacao | Formação profissional | Sim | Não |
| sem_vencimento | Licença sem vencimento | Não | Sim |

---

## 7. Folha de Pagamento

---

### 7.1 Activar cálculo de SS e IRS

**Estado actual**: O modo simplificado da função `calcularFolhaPagamento()` tem SS e IRS a zero por defeito. As taxas precisam de ser configuradas.

**O que o admin precisa de fazer** (e não existe interface para isso):
1. Criar interface de configuração de `tax_rules` no dashboard
2. Formulário para inserir escalões IRS do ano corrente
3. Campos: taxa SS trabalhador (padrão 11%), taxa SS empresa (padrão 23,75%)

**Interface sugerida no dashboard**:
```
Configurações Fiscais > Ano 2026
SS Trabalhador: [11.00]%
SS Empresa: [23.75]%
Método IRS: [Tabela com parcela a abater ▼]

Escalões IRS:
[ Mín. €    0 | Máx. €  7703 | Taxa  14.5% | Parcela €    0.00 ]
[ Mín. €7703 | Máx. € 11623 | Taxa  21.0% | Parcela €  501.70 ]
[ Mín. €11623| Máx. € 16472 | Taxa  26.5% | Parcela € 1139.98 ]
[ + Adicionar escalão ]
```

---

### 7.2 Recibo de vencimento em PDF

**Endpoint a criar**: `api/payroll/generate_payslip_pdf.php`

**Conteúdo do recibo**:
```
┌─────────────────────────────────────────────────────────┐
│              RECIBO DE VENCIMENTO                       │
│  Empresa: Restaurante XYZ            Mês: Janeiro 2026  │
├─────────────────────────────────────────────────────────┤
│  Funcionário: João Silva    NIF: 123456789              │
│  Cargo: Empregado de Mesa   NISS: 12345678901           │
├─────────────────┬───────────────────────────────────────┤
│  REMUNERAÇÕES   │                         DESCONTOS     │
├─────────────────┼───────────────────────────────────────┤
│  Salário Base   │ € 1.050,00   SS (11%)    │ €  115,50  │
│  Sub. Aliment.  │ €   154,00   IRS (28%)   │ €  215,32  │
│  Horas Extra    │ €    80,00               │            │
│  Gorjetas       │ €   230,00               │            │
├─────────────────┼───────────────────────────────────────┤
│  BRUTO          │ € 1.514,00   TOTAL DESC. │ €  330,82  │
│                 │         LÍQUIDO A PAGAR  │ € 1.183,18 │
└─────────────────┴───────────────────────────────────────┘
```

---

### 7.3 Variáveis mensais por funcionário

**Nova funcionalidade**: interface para o admin inserir, mês a mês, valores variáveis:
- Horas extra realizadas e valor
- Bonus pontual
- Subsídios adicionais
- Deduções pontuais

**Tabela já existe**: `folha_variaveis_mensais` (confirmada no schema).  
**O que falta**: interface no dashboard e endpoint de criação/edição.

---

### 7.4 Relatório de custos por departamento

```sql
SELECT
    e.department,
    COUNT(e.id) AS num_funcionarios,
    SUM(fp.salario_bruto) AS total_bruto,
    SUM(fp.seguranca_social_empresa) AS total_ss_empresa,
    SUM(fp.custo_total_empresa) AS custo_total
FROM folha_pagamento fp
JOIN employees e ON e.id = fp.employee_id
WHERE fp.client_id = ? AND fp.fiscal_year = ? AND fp.fiscal_month = ?
GROUP BY e.department
ORDER BY custo_total DESC;
```

---

## 8. Segurança e Autenticação

---

### 8.1 Autenticação de dois factores (2FA) para admin

**Método recomendado**: TOTP (Time-based One-Time Password) — compatível com Google Authenticator, Authy, etc.

**Biblioteca**: `composer require sonata-project/google-authenticator`

**Fluxo**:
```
1. Admin activa 2FA nas definições de conta
2. Sistema gera QR code para scan no Authenticator
3. Admin confirma com primeiro código de 6 dígitos
4. 2FA guardado como activo na tabela usuarios

No login:
1. Admin insere email + password (passo 1)
2. Se 2FA activo: pede código de 6 dígitos (passo 2)
3. Código verificado → sessão criada
```

**Alternativa mais simples**: 2FA por SMS (enviar código via Infobip — integração já existe).

---

### 8.2 Política de palavras-passe

**Estado actual**: Sem requisitos mínimos de complexidade.

**O que adicionar** em `admin/controllers/register_process.php` e `reset_password.php`:
```php
function validarPassword(string $password): bool {
    return strlen($password) >= 8          // mínimo 8 caracteres
        && preg_match('/[A-Z]/', $password) // pelo menos 1 maiúscula
        && preg_match('/[0-9]/', $password) // pelo menos 1 número
        && preg_match('/[^a-zA-Z0-9]/', $password); // pelo menos 1 especial
}
```

---

### 8.3 Sessões seguras

**O que configurar** em `config/session.php` (criar este ficheiro):
```php
ini_set('session.cookie_httponly', 1);   // previne acesso JS ao cookie
ini_set('session.cookie_secure', 1);     // apenas HTTPS
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', 3600); // sessão expira em 1h
session_name('RHNETO_SESS');             // nome customizado
```

---

### 8.4 Headers de segurança HTTP

**Criar middleware** `includes/security_headers.php` incluído em todas as páginas:
```php
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
// Em HTTPS:
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
```

---

### 8.5 Validação de idade mínima

**Ficheiro**: `api/employees/create_employee.php`  
**O que adicionar**: verificar que a data de nascimento indica pelo menos 16 anos (lei laboral PT).

```php
if (!empty($birthDate)) {
    $birth = new DateTime($birthDate);
    $age = $birth->diff(new DateTime())->y;
    if ($age < 16) {
        return error('Funcionário deve ter pelo menos 16 anos');
    }
}
```

---

## 9. Performance e Arquitectura

---

### 9.1 Cache de subscrição Stripe

**Problema**: `has_premium_access()` chama a API Stripe em cada verificação. Com muitos utilizadores activos, isto é lento e custa chamadas de API.

**Solução**: cache em sessão com TTL de 1 hora:
```php
function has_premium_access(array $user): bool {
    if (isset($_SESSION['premium_valid_until']) && $_SESSION['premium_valid_until'] > time()) {
        return $_SESSION['is_premium'];
    }
    $result = /* chamada Stripe */;
    $_SESSION['is_premium'] = $result;
    $_SESSION['premium_valid_until'] = time() + 3600;
    return $result;
}
```

---

### 9.2 Dividir o dashboard.js

**Problema**: `admin/assets/js/dashboard.js` tem 507KB num único ficheiro. O browser tem de carregar e parsear tudo mesmo que o utilizador só use um módulo.

**Solução proposta**: dividir em módulos lazy-loaded:
```
admin/assets/js/
├── modules/
│   ├── employees.js        # CRUD funcionários
│   ├── turnos.js           # Gestão de turnos
│   ├── gorjetas.js         # Gestão de gorjetas
│   ├── presencas.js        # Presenças e ponto
│   ├── payroll.js          # Folha de pagamento
│   ├── notifications.js    # Notificações
│   └── reports.js          # Relatórios
├── core/
│   ├── api.js              # Wrapper fetch/AJAX
│   ├── modals.js           # Sistema de modais
│   ├── toasts.js           # Notificações UI
│   └── csrf.js             # Token CSRF
└── dashboard.js            # Orquestrador (importa módulos)
```

---

### 9.3 Índices em falta na base de dados

**Queries frequentes que beneficiam de índices adicionais**:

```sql
-- Presenças por data (filtro mais comum)
ALTER TABLE presencas ADD INDEX idx_presencas_data (client_id, data);

-- Ponto por data
ALTER TABLE registros_ponto ADD INDEX idx_ponto_data (client_id, employee_id, timestamp);

-- Gorjetas por status
ALTER TABLE gorjetas ADD INDEX idx_gorjetas_status (client_id, status, data);

-- Notificações não lidas
ALTER TABLE notificacoes ADD INDEX idx_notif_nao_lidas (employee_id, lida, created_at);
```

---

### 9.4 Migração de db_connect.php para PDO

**Problema**: Existem dois sistemas de ligação — `db_connect.php` (MySQLi legado) e `db_connection.php` (PDO). Código antigo ainda usa MySQLi.

**O que fazer**: identificar todos os ficheiros que fazem `require_once 'db_connect.php'` e migrar para PDO com prepared statements. Após migração, remover `db_connect.php`.

```bash
# Ficheiros a migrar (exemplo de pesquisa)
grep -r "db_connect.php" --include="*.php" .
```

---

## 10. Conformidade e RGPD

---

### 10.1 Direito ao esquecimento (Artigo 17.º RGPD)

**O que implementar**:
- Endpoint `api/admin/anonymize_employee.php` que, em vez de eliminar, anonimiza dados pessoais:
```sql
UPDATE employees SET
    name = CONCAT('Funcionário #', id),
    email = NULL,
    phone = NULL,
    nif = NULL,
    niss = NULL,
    address = NULL,
    photo = NULL
WHERE id = ? AND client_id = ?;
```
- Mantém dados estatísticos (presenças, gorjetas agregadas) sem PII

---

### 10.2 Exportação de dados pessoais (Artigo 20.º RGPD)

**O que implementar**:
- Endpoint que gera um arquivo ZIP com todos os dados do funcionário em JSON/CSV
- Incluir: ficha pessoal, presenças, gorjetas, notificações, documentos

---

### 10.3 Consentimento para SMS

**Estado actual**: Campo `sms_notifications` existe mas não há registo de quando e como o consentimento foi dado.

**O que adicionar**:
```sql
ALTER TABLE employees
    ADD COLUMN sms_consent_date DATETIME NULL,
    ADD COLUMN sms_consent_ip VARCHAR(45) NULL;
```

---

### 10.4 Política de retenção de dados

**O que implementar**:
1. Script `tools/data_retention.php` agendado via cron
2. Remove registos de `atividades_recentes` com mais de 2 anos
3. Remove `login_attempts` com mais de 90 dias
4. Arquiva folhas fechadas com mais de 7 anos (obrigação fiscal PT)

---

## 11. Novas Funcionalidades para Restauração

> Funcionalidades específicas do sector que diferenciam o produto.

---

### 11.1 Gestão de propinas/TIPS por mesa

**Funcionalidade**: registo de gorjetas por mesa (não só por funcionário), com distribuição automática por todos os que serviram nessa mesa.

---

### 11.2 Escala semanal com templates

**Funcionalidade**: criar template de escala semanal que se repete automaticamente. Ex: "João faz sempre manhã segunda a sexta" — o sistema gera os turnos do mês automaticamente.

**Tabela sugerida**:
```sql
CREATE TABLE escala_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    dia_semana TINYINT NOT NULL,  -- 1=seg, 7=dom
    hora_inicio TIME NOT NULL,
    hora_fim TIME NOT NULL,
    ativo TINYINT(1) DEFAULT 1
);
```

---

### 11.3 Horas extra automáticas

**Funcionalidade**: se o funcionário registar saída depois da hora de fim do turno (+ margem), o sistema calcula automaticamente as horas extra e propõe ao gestor para aprovar.

---

### 11.4 Integração com POS (Ponto de Venda)

**Funcionalidade futura**: importar gorjetas automaticamente do sistema de caixa via API, eliminando o registo manual.

---

### 11.5 App mobile (PWA)

**Funcionalidade**: converter o portal do funcionário numa Progressive Web App (PWA):
1. Criar `app/manifest.json` com nome, ícones, cores
2. Criar `app/service-worker.js` para cache offline
3. Adicionar `<link rel="manifest">` no HTML
4. O funcionário pode instalar no telemóvel como app nativa

**Vantagem**: não requer publicação em App Store/Google Play.

```json
// app/manifest.json
{
  "name": "RHNeto Pro",
  "short_name": "RHNeto",
  "start_url": "/app-rhnetopro/app/portal.php",
  "display": "standalone",
  "background_color": "#ffffff",
  "theme_color": "#2c3e50",
  "icons": [...]
}
```

---

### 11.6 Notificações push (Web Push)

**Funcionalidade**: enviar notificações push para o telemóvel do funcionário mesmo sem a app aberta. Requer HTTPS e service worker (vê 11.5 acima).

**Biblioteca**: `composer require minishlink/web-push`

---

### 11.7 Painel de multi-restaurante (Super-Admin)

**Para empresas com múltiplos estabelecimentos**: vista global que agrega dados de todos os `clients`, com drill-down por estabelecimento.

---

## 12. Prioridades de Implementação

### Fase 1 — Segurança (fazer primeiro, semanas 1-2)

| Tarefa | Ficheiro(s) | Esforço |
|--------|------------|---------|
| Autenticação no endpoint público de ponto | `registrar_ponto_public.php` | 2h |
| Remover debug de produção | vários | 1h |
| CSRF: validar token nos formulários | `includes/csrf.php` + controllers | 4h |
| Rate limiting no login | `login_process.php`, `employee_auth.php` | 4h |
| Headers de segurança HTTP | `includes/security_headers.php` | 1h |
| Sessões seguras (httponly, samesite) | `config/session.php` | 1h |

---

### Fase 2 — Funcionalidades incompletas (semanas 3-5)

| Tarefa | Ficheiro(s) | Esforço |
|--------|------------|---------|
| Activar verificação de subscrição Stripe | `admin/dashboard.php` + `premium_access.php` | 3h |
| API REST de férias (CRUD completo) | `api/ferias/` | 6h |
| Relatórios com dados reais | `api/reports/` + `relatorios.php` | 12h |
| Recibo de vencimento PDF | `api/payroll/generate_payslip_pdf.php` | 8h |
| Saldo de férias por funcionário | nova tabela + endpoints | 4h |

---

### Fase 3 — Melhorias UX (semanas 6-8)

| Tarefa | Esforço |
|--------|---------|
| Calendário de turnos (FullCalendar) | 8h |
| Dashboard com KPIs em tempo real | 6h |
| Notificações push / polling | 4h |
| Histórico de ponto completo no portal | 3h |
| Calendário de férias da equipa | 6h |

---

### Fase 4 — Novas funcionalidades (semanas 9-12)

| Tarefa | Esforço |
|--------|---------|
| Exportação PDF/Excel (instalar libs) | 10h |
| 2FA por SMS ou TOTP | 8h |
| PWA para portal do funcionário | 6h |
| Escala semanal com templates | 8h |
| Horas extra automáticas | 6h |
| Auditoria com before/after | 4h |

---

### Fase 5 — Conformidade e escalabilidade (semanas 13-16)

| Tarefa | Esforço |
|--------|---------|
| RGPD: anonimização e exportação | 8h |
| Dividir dashboard.js em módulos | 12h |
| Migração completa para PDO | 6h |
| Índices de performance na BD | 2h |
| Política de retenção de dados | 4h |

---

*Documento gerado com base em auditoria directa do código fonte do projecto RHNeto Pro.*  
*Junho 2026 — Todas as referências a ficheiros foram verificadas no código actual.*
