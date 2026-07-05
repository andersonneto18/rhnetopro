# RHNeto Pro — Documentação Completa do Sistema

> Sistema de Gestão de Recursos Humanos para a Área de Restauração  
> Versão da documentação: Junho 2026

---

## Índice

1. [Visão Geral](#1-visão-geral)
2. [Stack Tecnológico](#2-stack-tecnológico)
3. [Arquitetura do Sistema](#3-arquitetura-do-sistema)
4. [Estrutura de Directórios](#4-estrutura-de-directórios)
5. [Base de Dados](#5-base-de-dados)
6. [Módulos e Funcionalidades](#6-módulos-e-funcionalidades)
7. [API REST](#7-api-rest)
8. [Autenticação e Segurança](#8-autenticação-e-segurança)
9. [Cálculo de Remuneração (Portugal)](#9-cálculo-de-remuneração-portugal)
10. [Integrações Externas](#10-integrações-externas)
11. [Portal do Funcionário](#11-portal-do-funcionário)
12. [Painel Administrativo](#12-painel-administrativo)
13. [Instalação e Configuração](#13-instalação-e-configuração)
14. [Multi-tenancy](#14-multi-tenancy)
15. [Subscrições e Planos](#15-subscrições-e-planos)
16. [Ferramentas e Diagnóstico](#16-ferramentas-e-diagnóstico)
17. [Fluxos de Trabalho Principais](#17-fluxos-de-trabalho-principais)
18. [Limitações Conhecidas](#18-limitações-conhecidas)

---

## 1. Visão Geral

O **RHNeto Pro** é uma aplicação web de gestão de recursos humanos desenhada especificamente para o sector da restauração em Portugal. Permite que gestores e administradores de RH tratem de toda a operação de pessoal num único sistema, com um portal separado para os funcionários registarem o ponto e consultarem as suas informações.

### Casos de uso principais

| Perfil | Actividades |
|--------|-------------|
| Gestor / Admin RH | Criar e gerir fichas de funcionários, definir turnos, validar presenças, gerir gorjetas, calcular folha de pagamento, enviar notificações |
| Funcionário | Registar ponto via PIN, consultar turnos, reportar gorjetas, submeter justificativas de faltas |
| Técnico de TI | Instalar, configurar e manter o sistema, gerir subscrições e integrações |

### Sectores suportados

- Restaurantes e similares
- Cafés e pastelarias
- Hotéis com serviço de restauração
- Empresas de catering

---

## 2. Stack Tecnológico

### Backend

| Componente | Versão / Detalhe |
|------------|-----------------|
| PHP | 8.1+ (recomendado); mínimo 7.4 |
| Base de dados | MySQL 8+ / MariaDB 10.4+ |
| Extensões PHP | PDO, MySQLi, GD/Imagick, json, mbstring |
| Servidor web | Apache (XAMPP) ou Nginx |
| Gestor de dependências | Composer (Stripe PHP SDK) |

### Frontend

| Componente | Detalhe |
|------------|---------|
| Linguagem | HTML5, CSS3, JavaScript ES6+ (Vanilla) |
| Tipografia | Inter (corpo), Poppins (cabeçalhos) — Google Fonts |
| Ícones | Font Awesome 6.0+ |
| Notificações UI | Toastify JS |
| Formatação de telefone | Intl Tel Input |
| Design | Responsivo (mobile-first adaptado) |

### Integrações

| Serviço | Finalidade |
|---------|-----------|
| Stripe | Processamento de pagamentos e subscrições |
| Infobip | Envio de SMS para funcionários |

---

## 3. Arquitetura do Sistema

```
┌─────────────────────────────────────────────────────┐
│                   BROWSER / CLIENT                   │
│  ┌─────────────────────┐  ┌────────────────────────┐ │
│  │  Painel Admin        │  │  Portal Funcionário    │ │
│  │  admin/dashboard.php │  │  app/portal.php        │ │
│  └──────────┬──────────┘  └───────────┬────────────┘ │
└─────────────┼──────────────────────────┼─────────────┘
              │ AJAX / Fetch             │ AJAX / Fetch
┌─────────────▼──────────────────────────▼─────────────┐
│                    PHP + Apache                       │
│  ┌───────────────────────────────────────────────┐   │
│  │               api/  (REST endpoints)           │   │
│  │  employees/  turnos/  gorjetas/  presenca/     │   │
│  └───────────────────────────────────────────────┘   │
│  ┌───────────────────────────────────────────────┐   │
│  │             includes/ (helpers)                │   │
│  │  payroll_calculator.php  activity_logger.php   │   │
│  │  sms_sender.php          premium_access.php    │   │
│  └───────────────────────────────────────────────┘   │
│  ┌───────────────────────────────────────────────┐   │
│  │             config/ (conexões)                 │   │
│  │  db_connection.php (PDO)   db_connect.php      │   │
│  └───────────────────────────────────────────────┘   │
└──────────────────────────┬────────────────────────────┘
                           │ PDO / MySQLi
┌──────────────────────────▼────────────────────────────┐
│              MySQL / MariaDB                          │
│          Database: sistema_cadastro                   │
└───────────────────────────────────────────────────────┘
         │                          │
┌────────▼──────────┐   ┌───────────▼──────────────────┐
│  Stripe API       │   │  Infobip SMS API              │
│  (subscrições)    │   │  (notificações SMS)           │
└───────────────────┘   └──────────────────────────────┘
```

### Padrão de Arquitectura

- **MVC informal**: views em `admin/views/`, lógica de negócio em `admin/controllers/` e `includes/`, modelos implícitos na camada de API
- **REST API**: todos os endpoints em `api/` recebem `POST` e devolvem JSON
- **Multi-tenant**: isolamento por `client_id` em todas as tabelas
- **Sessões PHP**: autenticação de admin via `$_SESSION`; funcionário via `$_SESSION` com PIN
- **Prepared statements PDO**: prevenção de SQL injection em toda a aplicação

---

## 4. Estrutura de Directórios

```
app-rhnetopro/
│
├── admin/                          # Área administrativa
│   ├── dashboard.php               # Dashboard principal (módulo único de 500KB+ JS)
│   ├── planos.php                  # Página de planos de subscrição
│   ├── assets/
│   │   ├── css/
│   │   │   ├── dashboard.css       # Estilos do dashboard (134KB)
│   │   │   ├── login.css           # Estilos de login
│   │   │   └── relatorios-pro.css  # Estilos de relatórios
│   │   └── js/
│   │       └── dashboard.js        # Toda a lógica de frontend (507KB)
│   ├── controllers/
│   │   ├── login_process.php       # Autenticação de admin
│   │   ├── register_process.php    # Registo de novo admin
│   │   ├── logout.php              # Terminar sessão
│   │   ├── reset_password.php      # Redefinir palavra-passe
│   │   ├── upload_foto.php         # Upload de foto de perfil
│   │   ├── enviar_notificacao.php  # Envio de notificações
│   │   └── limpar_historico_solicitacoes.php
│   ├── views/
│   │   ├── login.php               # Formulário de login / signup
│   │   ├── signup.php              # Registo de administrador
│   │   ├── esqueci_senha.php       # Recuperação de palavra-passe
│   │   └── relatorios.php          # Dashboard de relatórios
│   ├── seed_test_data.php          # Carga de dados de teste
│   └── optimize_indexes.php        # Optimização de índices BD
│
├── api/                            # Endpoints REST (todos devolvem JSON)
│   ├── employees/                  # CRUD de funcionários + documentos + notificações
│   ├── gorjetas/
│   │   ├── *.php                   # CRUD e fluxo de aprovação de gorjetas
│   │   └── presenca/               # Registo de ponto e presenças
│   └── turnos/                     # CRUD de turnos
│
├── app/                            # Portal do funcionário (versão actual)
│   ├── employee_login.php          # Login por PIN
│   ├── employee_auth.php           # Autenticação
│   ├── portal.php                  # Portal principal
│   ├── employee_logout.php
│   ├── portal.js                   # Lógica do portal
│   └── portal.css                  # Estilos do portal
│
├── config/
│   ├── db_connection.php           # Ligação PDO (principal)
│   ├── db_connect.php              # Ligação MySQLi (legada)
│   └── sms_config.php              # Configuração SMS
│
├── database/
│   └── sql/
│       └── install_professional_schema_only.sql  # Schema limpo (sem dados)
│
├── demos/                          # Protótipos HTML estáticos
├── docs/                           # Documentação de utilizador (PT)
│
├── employee/                       # Portal funcionário legado
│
├── includes/
│   ├── activity_logger.php         # Registo de auditoria
│   ├── payroll_calculator.php      # Cálculo de folha (SS + IRS Portugal)
│   ├── sms_sender.php              # Wrapper Infobip
│   └── premium_access.php          # Validação de subscrição Stripe
│
├── planos/
│   ├── index.php                   # Página de preços
│   ├── create-checkout-session.php # Criação de sessão Stripe
│   └── webhook.php                 # Handler de eventos Stripe
│
├── sms_module/                     # Módulo SMS autónomo
│   ├── api/notify_employees.php
│   ├── config/
│   └── docs/
│
├── tools/                          # Scripts de diagnóstico e migração
│
├── uploads/
│   ├── profile/                    # Fotos de perfil
│   ├── documents/                  # Documentos de funcionários
│   └── justificativas/             # Justificativas de faltas
│
├── webhook/                        # Handler webhook Stripe (entrada)
├── .env                            # Chaves Stripe (NÃO commitar)
├── sistema_cadastro.sql            # Dump completo com dados de exemplo
└── index.php                       # Redireciona para admin/views/login.php
```

---

## 5. Base de Dados

**Nome da base de dados**: `sistema_cadastro`  
**Charset**: `utf8mb4` / `utf8mb4_unicode_ci`

### Diagrama de Tabelas

```
clients (1) ─────────────────── (N) usuarios
    │
    └──── (N) employees ─────── (N) employee_documents
                │                         │ uploads/documents/
                ├──── (N) turnos
                ├──── (N) presencas
                ├──── (N) registros_ponto
                ├──── (N) gorjetas
                ├──── (N) ferias
                ├──── (N) justificativas_presenca
                ├──── (N) justificativas_falta
                ├──── (N) folha_pagamento ──── (1) folha_pagamento_historico
                └──── (N) notificacoes

tax_rules (1) ──── (N) irs_brackets
payroll_settings ── (client_id)
atividades_recentes ── (client_id)
```

### Descrição das Tabelas Principais

#### `clients`
Registo de cada empresa/restaurante cliente do sistema.

| Campo | Tipo | Descrição |
|-------|------|-----------|
| id | INT UNSIGNED PK | Identificador único |
| client_name | VARCHAR(255) | Nome do restaurante/empresa |
| subscription_status | VARCHAR(20) | `trial`, `active`, `inactive`, `cancelled` |
| subscription_plan | VARCHAR(50) | Nome do plano contratado |
| subscription_start_date | DATETIME | Início da subscrição |
| subscription_end_date | DATETIME | Fim da subscrição |
| trial_ends_at | DATETIME | Data de fim do período de teste |

#### `usuarios`
Utilizadores administrativos (gestores de RH, donos).

| Campo | Tipo | Descrição |
|-------|------|-----------|
| id | INT UNSIGNED PK | Identificador único |
| nome_completo | VARCHAR(255) | Nome do utilizador |
| email | VARCHAR(255) UNIQUE | Email de acesso |
| nome_usuario | VARCHAR(100) UNIQUE | Username de acesso |
| senha | VARCHAR(255) | Hash bcrypt da palavra-passe |
| client_id | INT UNSIGNED FK | Empresa a que pertence |
| profile_picture | VARCHAR(255) | Caminho da foto de perfil |
| subscription_status | VARCHAR(20) | Estado da subscrição (espelha clients) |

#### `employees`
Ficheiro mestre de funcionários.

| Campo | Tipo | Descrição |
|-------|------|-----------|
| id | INT UNSIGNED PK | Identificador único |
| client_id | INT UNSIGNED FK | Empresa |
| name | VARCHAR(255) | Nome completo |
| position | VARCHAR(100) | Cargo (ex: Empregado de Mesa, Cozinheiro) |
| department | VARCHAR(100) | Secção/departamento |
| email | VARCHAR(255) | Email do funcionário |
| phone | VARCHAR(255) | Telemóvel |
| startDate | DATE | Data de admissão |
| status | VARCHAR(50) | `active`, `inactive`, `ferias` |
| pin_hash | VARCHAR(255) | Hash bcrypt do PIN de acesso |
| nif | VARCHAR(20) | Número de Identificação Fiscal |
| niss | VARCHAR(20) | Número de Identificação Segurança Social |
| address | TEXT | Morada |
| city | VARCHAR(100) | Cidade |
| salario_base | DECIMAL(12,2) | Salário base (€) |
| subsidio_alimentacao | DECIMAL(12,2) | Subsídio de alimentação (€) |
| bonus | DECIMAL(12,2) | Bonus/prémio (€) |
| photo | VARCHAR(255) | Caminho da foto de perfil |
| sms_notifications | TINYINT(1) | Aceita SMS (0/1) |

#### `turnos`
Turnos de trabalho atribuídos a funcionários.

| Campo | Tipo | Descrição |
|-------|------|-----------|
| id | INT UNSIGNED PK | Identificador único |
| client_id | INT UNSIGNED | Empresa |
| employee_id | INT UNSIGNED FK | Funcionário |
| start_datetime | DATETIME | Início do turno |
| end_datetime | DATETIME | Fim do turno |
| status | VARCHAR(50) | `scheduled`, `completed`, `cancelled` |

#### `presencas`
Marcações de presença diária.

| Campo | Tipo | Descrição |
|-------|------|-----------|
| id | INT UNSIGNED PK | Identificador único |
| client_id | INT UNSIGNED | Empresa |
| employee_id | INT UNSIGNED FK | Funcionário |
| data | DATE | Data da marcação |
| status | VARCHAR(50) | `presente`, `ausente`, `justificado` |
| observacoes | TEXT | Notas do gestor |

#### `registros_ponto`
Registos de entrada/saída (clock in/out).

| Campo | Tipo | Descrição |
|-------|------|-----------|
| id | INT UNSIGNED PK | Identificador único |
| client_id | INT UNSIGNED | Empresa |
| employee_id | INT UNSIGNED FK | Funcionário |
| tipo | ENUM | `entrada`, `saida` |
| timestamp | DATETIME | Momento do registo |
| ip_address | VARCHAR(45) | IP de origem |

#### `gorjetas`
Gorjetas reportadas e o seu estado de aprovação.

| Campo | Tipo | Descrição |
|-------|------|-----------|
| id | INT UNSIGNED PK | Identificador único |
| client_id | INT UNSIGNED | Empresa |
| employee_id | INT UNSIGNED FK | Funcionário |
| valor | DECIMAL(12,2) | Valor em euros |
| data | DATE | Data da gorjeta |
| status | VARCHAR(50) | `pendente`, `confirmado`, `rejeitado`, `pago` |
| observacoes | TEXT | Notas |

#### `folha_pagamento`
Cálculo mensal de remuneração por funcionário.

| Campo | Tipo | Descrição |
|-------|------|-----------|
| id | BIGINT UNSIGNED PK | Identificador único |
| client_id | INT | Empresa |
| employee_id | INT | Funcionário |
| fiscal_year | INT | Ano fiscal |
| fiscal_month | TINYINT | Mês fiscal (1-12) |
| salario_base | DECIMAL(12,2) | Salário base |
| subsidio_alimentacao | DECIMAL(12,2) | Subsídio de alimentação |
| horas_extra | DECIMAL(12,2) | Valor de horas extra |
| bonus | DECIMAL(12,2) | Bonus |
| gorjetas | DECIMAL(12,2) | Total de gorjetas do mês |
| salario_bruto | DECIMAL(12,2) | Total bruto |
| ss_rate | DECIMAL(8,5) | Taxa SS aplicada |
| seguranca_social | DECIMAL(12,2) | Desconto SS (trabalhador) |
| seguranca_social_empresa | DECIMAL(12,2) | Encargo SS (empresa) |
| irs | DECIMAL(12,2) | Retenção IRS |
| irs_rate_applied | DECIMAL(8,5) | Taxa IRS aplicada |
| total_descontos | DECIMAL(12,2) | Total de descontos |
| salario_liquido | DECIMAL(12,2) | Salário líquido a pagar |
| custo_total_empresa | DECIMAL(12,2) | Custo total para a empresa |
| status_pagamento | ENUM | `pendente`, `pago` |

#### `tax_rules` + `irs_brackets`
Configuração das regras fiscais por ano.

| Campo | Tipo | Descrição |
|-------|------|-----------|
| fiscal_year | INT | Ano a que se aplicam as regras |
| social_security_rate | DECIMAL(8,5) | Taxa SS trabalhador (ex: 0.11000 = 11%) |
| employer_social_security_rate | DECIMAL(8,5) | Taxa SS empresa (ex: 0.23750 = 23.75%) |
| irs_calculation_method | VARCHAR(32) | `table_deduction` ou `marginal` |

Os escalões IRS ficam em `irs_brackets` com campos `min_amount`, `max_amount`, `rate`, `parcela_abater`.

#### `notificacoes`
Notificações in-app para funcionários.

| Campo | Tipo | Descrição |
|-------|------|-----------|
| employee_id | INT | Destinatário |
| client_id | INT | Empresa |
| titulo | VARCHAR(255) | Título da notificação |
| mensagem | TEXT | Corpo da mensagem |
| lida | TINYINT(1) | 0=não lida, 1=lida |
| created_at | TIMESTAMP | Data de criação |

#### `atividades_recentes`
Registo de auditoria de todas as operações.

| Campo | Tipo | Descrição |
|-------|------|-----------|
| client_id | INT | Empresa |
| user_id | INT | Utilizador que executou |
| tipo | VARCHAR(50) | Tipo de acção |
| descricao | TEXT | Descrição da acção |
| entidade | VARCHAR(100) | Tabela/entidade afectada |
| entidade_id | INT | ID do registo afectado |
| created_at | TIMESTAMP | Momento da acção |

---

## 6. Módulos e Funcionalidades

### 6.1 Gestão de Funcionários

**Ficheiros principais**: `api/employees/`, `admin/dashboard.php` (secção Funcionários)

**Operações disponíveis**:
- Criar ficha de funcionário com todos os dados pessoais, contratuais e de contacto
- Editar dados (nome, cargo, departamento, salários, NIF, NISS, etc.)
- Activar / desactivar funcionário
- Definir PIN de acesso ao portal
- Upload de foto de perfil (JPEG/PNG, redimensionamento automático)
- Upload de documentos (contrato, BI, certidões — PDF, DOCX, XLSX)
- Exportar lista de funcionários para PDF ou Excel
- Acções em bloco: alterar estado, departamento, férias em grupo

**Campos da ficha**:
- Dados pessoais: nome, email, telefone, NIF, NISS, data nascimento, género, morada, cidade
- Dados laborais: cargo, departamento, data admissão, estado, contrato
- Dados financeiros: salário base, subsídio de alimentação, bonus
- Comunicação: aceitar SMS (flag), histórico de notificações

### 6.2 Gestão de Turnos

**Ficheiros principais**: `api/turnos/`

**Operações disponíveis**:
- Criar turno individual (funcionário + data/hora início + data/hora fim)
- Listagem de turnos por funcionário ou por período
- Editar e cancelar turnos
- Criação em bloco de turnos para múltiplos funcionários
- Detecção de conflitos de horário sobrepostos
- Gestão de períodos com encerramentos (feriados, folgas)

### 6.3 Registo de Ponto e Presenças

**Ficheiros principais**: `api/gorjetas/presenca/`

**Modos de registo**:
1. **Com autenticação** (`registrar_ponto_session.php`) — funcionário autenticado via PIN no portal
2. **Público** (`registrar_ponto_public.php`, `salvar_presenca_public.php`) — quiosque de ponto sem login; validação por PIN na hora

**Operações do gestor**:
- Consultar presenças por dia / mês / funcionário
- Alterar estado de presença (presente, ausente, justificado)
- Visualizar histórico de alterações de ponto
- Aprovar/rejeitar justificativas de falta com documento anexo
- Relatórios de assiduidade mensais

### 6.4 Gestão de Gorjetas

**Ficheiros principais**: `api/gorjetas/`

**Fluxo de trabalho**:
```
Funcionário reporta gorjeta (add_gorjeta_employee.php)
          ↓
       PENDENTE
          ↓
  Gestor revê (confirm_gorjeta.php / reject_gorjeta.php)
          ↓
   CONFIRMADO / REJEITADO
          ↓
  Marcado como PAGO (quando incluído na folha)
```

**Funcionalidades**:
- Funcionários reportam gorjetas via portal (valor + data + nota)
- Gestor aprova ou rejeita com justificação
- Divisão automática de gorjetas mensais por número de funcionários (`gorjetas_auto_split`)
- Estatísticas de gorjetas por funcionário e por período
- Inclusão automática de gorjetas confirmadas no cálculo da folha

### 6.5 Folha de Pagamento

**Ficheiro principal**: `includes/payroll_calculator.php`

Ver secção [9. Cálculo de Remuneração](#9-cálculo-de-remuneração-portugal) para detalhe completo.

**Funcionalidades**:
- Cálculo automático de salário líquido
- Suporte a SS (trabalhador + empresa) e IRS por escalões
- Configuração de defaults mensais (subsídios, horas extra, bonus)
- Snapshot imutável mensal (`folha_pagamento_historico`)
- Marcação de pagamento (pendente/pago)
- Histórico de períodos fechados

### 6.6 Notificações e Comunicação

**Ficheiros**: `admin/controllers/enviar_notificacao.php`, `sms_module/`, `api/employees/notify_employees.php`

**Canais disponíveis**:
- **Notificações in-app**: aparecem no portal do funcionário
- **SMS via Infobip**: envio directo para telemóvel

**Tipos de notificação**:
- Aviso de turno
- Confirmação/rejeição de gorjeta
- Aprovação/rejeição de justificativa
- Comunicados gerais
- Notificações de férias

**Funcionalidades**:
- Envio individual ou em bloco para todos os funcionários
- Preferências por funcionário (aceitar/recusar SMS)
- Histórico de SMS enviados com estado de entrega
- Templates de mensagem reutilizáveis

### 6.7 Documentos de Funcionários

**Ficheiros**: `api/employees/upload_document.php`, `api/employees/get_documents.php`

**Tipos aceites**: PDF, DOCX, XLSX, DOC, JPG, PNG  
**Localização no servidor**: `uploads/documents/`  
**Tamanho máximo**: configurado em `php.ini`

**Operações**:
- Upload de documento com categoria (contrato, identificação, certificado, outros)
- Listagem de documentos por funcionário
- Download / visualização
- Eliminação de documento

### 6.8 Relatórios

**Ficheiro**: `admin/views/relatorios.php`

**Relatórios disponíveis**:
- Resumo mensal de presenças por funcionário
- Total de gorjetas por período
- Folha de pagamento consolidada
- Actividade recente (log de auditoria)
- Exportação em PDF e Excel

---

## 7. API REST

Todos os endpoints ficam em `api/` e comunicam via `HTTP POST`. A resposta é sempre JSON.

### Formato de resposta padrão

```json
{
  "success": true,
  "message": "Operação realizada com sucesso",
  "data": { }
}
```

Em caso de erro:
```json
{
  "success": false,
  "message": "Descrição do erro"
}
```

### Endpoints de Funcionários (`api/employees/`)

| Endpoint | Descrição |
|----------|-----------|
| `create_employee.php` | Criar novo funcionário |
| `get_employee.php` | Obter dados de um funcionário |
| `update_employee.php` | Actualizar dados do funcionário |
| `delete_employee.php` | Eliminar funcionário |
| `check_email.php` | Verificar unicidade de email |
| `upload_document.php` | Fazer upload de documento (multipart) |
| `get_documents.php` | Listar documentos do funcionário |
| `delete_document.php` | Eliminar documento |
| `validate_attendance.php` | Validar assiduidade |
| `notify_employees.php` | Enviar notificação |
| `manage_notifications.php` | Gerir preferências de notificação |
| `get_sms_history.php` | Histórico de SMS |
| `review_justificativa.php` | Rever justificativa de falta |

### Endpoints de Turnos (`api/turnos/`)

| Endpoint | Descrição |
|----------|-----------|
| `create_turno.php` | Criar turno |
| `get_turnos.php` | Listar turnos |
| `get_turno_funcionario.php` | Turnos de um funcionário |
| `update_turno.php` | Editar turno |
| `delete_turno.php` | Eliminar turno |

### Endpoints de Gorjetas (`api/gorjetas/`)

| Endpoint | Descrição |
|----------|-----------|
| `create_gorjeta.php` | Criar gorjeta (gestor) |
| `add_gorjeta_employee.php` | Reportar gorjeta (funcionário) |
| `get_gorjetas.php` | Listar gorjetas |
| `update_gorjeta.php` | Editar gorjeta |
| `confirm_gorjeta.php` | Aprovar gorjeta |
| `reject_gorjeta.php` | Rejeitar gorjeta |
| `delete_gorjeta.php` | Eliminar gorjeta |

### Endpoints de Ponto (`api/gorjetas/presenca/`)

| Endpoint | Descrição |
|----------|-----------|
| `registrar_ponto.php` | Registar entrada/saída (autenticado) |
| `registrar_ponto_session.php` | Registar com sessão |
| `registrar_ponto_public.php` | Registar sem autenticação (quiosque) |
| `salvar_presenca.php` | Marcar presença (autenticado) |
| `salvar_presenca_public.php` | Marcar presença (público) |
| `registros_de_presenca.php` | Listar registos de ponto |

---

## 8. Autenticação e Segurança

### Admin

- Login por email/username + palavra-passe
- Palavras-passe armazenadas em bcrypt (`password_hash()`)
- Sessões PHP com `session_regenerate_id()` no login
- Fluxo de recuperação de palavra-passe com token temporário (tabela `admin_password_resets`, expiração configurável)
- Isolamento multi-tenant: `client_id` validado em todas as queries

### Funcionário

- Login por número de funcionário + PIN (4-6 dígitos)
- PIN armazenado em bcrypt (`password_hash()`)
- Sessão destruída no logout
- Opção de registo de ponto público (quiosque): validação do PIN na hora, sem sessão persistente

### Protecções Implementadas

| Ameaça | Mitigação |
|--------|-----------|
| SQL Injection | Prepared statements PDO em toda a aplicação |
| Brute force login | Recomendado: limitar tentativas ao nível do servidor (Fail2Ban, ModSecurity) |
| Upload de ficheiros maliciosos | Validação de extensão e MIME type nos uploads |
| XSS | `htmlspecialchars()` nos outputs; Content-Type JSON nas APIs |
| CSRF | Recomendado: implementar tokens CSRF nos formulários admin |
| Acesso não autorizado | Verificação de sessão no início de cada página protegida |

> **Nota de segurança**: O sistema deve ser executado exclusivamente sobre HTTPS em produção para proteger PINs e palavras-passe em trânsito.

---

## 9. Cálculo de Remuneração (Portugal)

**Ficheiro**: `includes/payroll_calculator.php`

### Componentes do Cálculo

```
Salário Base
+ Subsídio de Alimentação
+ Subsídios Tributáveis
+ Horas Extra
+ Bonus
+ Gorjetas Confirmadas
─────────────────────────
= SALÁRIO BRUTO

SALÁRIO BRUTO × Taxa SS Trabalhador (padrão: 11%)
= Desconto Segurança Social (trabalhador)

SALÁRIO BRUTO × Taxa SS Empresa (padrão: 23.75%)
= Encargo Segurança Social (empresa)

(SALÁRIO BRUTO - Desconto SS) → Tabela Escalões IRS
= Retenção IRS

─────────────────────────────────────────
SALÁRIO LÍQUIDO = BRUTO - SS Trabalhador - IRS
CUSTO TOTAL EMPRESA = BRUTO + SS Empresa
```

### Métodos de Cálculo IRS

| Método | Descrição |
|--------|-----------|
| `table_deduction` | Escalão único aplicável: `IRS = (base × taxa) - parcela_a_abater` |
| `marginal` | Tributação progressiva por fatia de rendimento em cada escalão |

### Tabela de Escalões IRS (`irs_brackets`)

Configurável no painel admin por ano fiscal. Campos:
- `min_amount` — limite mínimo do escalão (inclusive)
- `max_amount` — limite máximo (exclusive); `NULL` = sem limite superior
- `rate` — taxa marginal (ex: `0.28000` = 28%)
- `parcela_abater` — parcela a abater (método `table_deduction`)

### Snapshot Mensal

Cada mês processado é guardado em `folha_pagamento_historico` como JSON imutável. Isto garante que alterações futuras às regras fiscais não afectam folhas já fechadas.

### Modo Simplificado

O sistema suporta um modo sem descontos (SS e IRS = 0) para restaurantes em fase inicial ou que processam salários por outro meio. Activado quando as taxas em `tax_rules` estão a zero.

---

## 10. Integrações Externas

### Stripe (Pagamentos)

**Ficheiros**: `planos/`, `webhook/`, `includes/premium_access.php`, `.env`

**Variáveis de ambiente** (ficheiro `.env`):
```
STRIPE_SECRET_KEY=sk_...
STRIPE_PUBLISHABLE_KEY=pk_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

**Fluxo de subscrição**:
```
Utilizador escolhe plano (planos/index.php)
          ↓
Sessão de checkout criada (planos/create-checkout-session.php)
          ↓
Utilizador paga no Stripe Checkout
          ↓
Webhook recebido (webhook/index.php → planos/webhook.php)
          ↓
Tabela usuarios/clients actualizada (subscription_status = 'active')
```

**Eventos Stripe tratados**:
- `checkout.session.completed` — activar subscrição
- `customer.subscription.deleted` — cancelar subscrição
- `invoice.payment_failed` — marcar como inactivo

**Controlo de acesso premium**: `includes/premium_access.php` valida `subscription_status` antes de permitir acesso a funcionalidades pagas.

### Infobip (SMS)

**Ficheiros**: `sms_module/`, `includes/sms_sender.php`, `config/sms_config.local.php`

**Configuração** (`config/sms_config.local.php`):
```php
define('INFOBIP_API_KEY', '...');
define('INFOBIP_BASE_URL', 'https://xxxxx.api.infobip.com');
define('INFOBIP_SENDER', 'RHNeto');
```

**Funcionalidades**:
- Envio de SMS individuais e em bloco
- Rastreamento de estado de entrega
- Histórico de SMS por funcionário (`get_sms_history.php`)
- Respeito por preferências individuais de notificação

---

## 11. Portal do Funcionário

**Ficheiros**: `app/employee_login.php`, `app/portal.php`, `app/portal.js`

### Login

O funcionário autentica-se com:
1. Número de funcionário (ID interno)
2. PIN (4-6 dígitos) — validado por bcrypt

### Funcionalidades disponíveis no portal

| Funcionalidade | Descrição |
|----------------|-----------|
| Registo de ponto | Entrada e saída com timestamp |
| Consulta de presenças | Ver histórico pessoal de presenças |
| Consulta de turnos | Ver turnos agendados |
| Gorjetas | Reportar gorjeta recebida |
| Notificações | Ver notificações não lidas |
| Justificativas | Submeter justificativa de falta com documento |

### Portal Público (Quiosque)

Os endpoints `*_public.php` permitem instalar um tablet/computador de ponto fixo sem sessão persistente. O funcionário insere o PIN em cada registo.

---

## 12. Painel Administrativo

**Ficheiro principal**: `admin/dashboard.php`  
**JavaScript**: `admin/assets/js/dashboard.js` (~507KB, tudo em Vanilla JS)

### Secções do Dashboard

| Secção | Funcionalidades |
|--------|----------------|
| Funcionários | Lista, criar, editar, eliminar, exportar PDF/Excel |
| Turnos | Calendário de turnos, criar, editar, eliminar |
| Presenças | Tabela diária/mensal, validar, ver justificativas |
| Gorjetas | Lista, aprovar, rejeitar, estatísticas |
| Folha de Pagamento | Cálculo mensal, configurar taxas, fechar mês |
| Documentos | Ver e gerir documentos por funcionário |
| Notificações | Enviar mensagens individuais ou em bloco |
| Relatórios | Ver e exportar relatórios |
| Actividade Recente | Log de auditoria de todas as operações |
| Configurações | Perfil do admin, subscrição |

### Sistema de Modais

O dashboard usa um sistema de modais JavaScript para todas as operações CRUD, evitando recarregamentos de página. Cada entidade (funcionário, turno, gorjeta) tem:
- Modal de criação
- Modal de edição
- Modal de visualização de detalhes
- Confirmação de eliminação

### Acções em Bloco

- Alterar estado de múltiplos funcionários
- Atribuir férias a grupo de funcionários
- Mover grupo para departamento
- Enviar notificação a todos os funcionários
- Calcular folha para todos os funcionários do mês

---

## 13. Instalação e Configuração

### Pré-requisitos

- XAMPP (Windows) / LAMP (Linux) / MAMP (macOS)
- PHP 8.1+ com PDO, MySQLi, GD, json, mbstring
- MySQL 8+ ou MariaDB 10.4+
- Composer (para Stripe SDK)

### Passos de Instalação

**1. Copiar ficheiros**
```
Copiar o directório app-rhnetopro/ para htdocs/ (XAMPP) ou /var/www/html/
```

**2. Criar base de dados**
```sql
CREATE DATABASE sistema_cadastro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**3. Importar schema**
```bash
mysql -u root -p sistema_cadastro < database/sql/install_professional_schema_only.sql
```

**4. Configurar ligação à base de dados**

Editar `config/db_connection.php`:
```php
$host = 'localhost';
$dbname = 'sistema_cadastro';
$username = 'root';
$password = '';  // alterar em produção
```

**5. Configurar Stripe** (opcional, para subscrições)

Criar ficheiro `.env` na raiz:
```
STRIPE_SECRET_KEY=sk_test_...
STRIPE_PUBLISHABLE_KEY=pk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

**6. Configurar SMS Infobip** (opcional)

Editar `config/sms_config.local.php`:
```php
define('INFOBIP_API_KEY', 'sua_chave_aqui');
define('INFOBIP_BASE_URL', 'https://xxxxx.api.infobip.com');
define('INFOBIP_SENDER', 'RHNeto');
```

**7. Permissões de directórios de upload**
```bash
chmod 755 uploads/
chmod 755 uploads/profile/
chmod 755 uploads/documents/
chmod 755 uploads/justificativas/
```

**8. Instalar dependências Composer**
```bash
composer install
```

**9. Criar primeiro utilizador administrador**
```
Aceder a: http://localhost/app-rhnetopro/admin/views/login.php
Clicar em "Registar" e preencher o formulário
```

**10. Verificar instalação**
```
1. Login em admin/views/login.php
2. Confirmar que o dashboard carrega
3. Criar um funcionário de teste
4. Testar upload de documento
5. Testar login do funcionário no portal
```

### URLs de Acesso

| Área | URL |
|------|-----|
| Admin | `http://localhost/app-rhnetopro/admin/views/login.php` |
| Portal Funcionário | `http://localhost/app-rhnetopro/app/employee_login.php` |
| Planos/Preços | `http://localhost/app-rhnetopro/planos/index.php` |
| Raiz (redirect) | `http://localhost/app-rhnetopro/` |

---

## 14. Multi-tenancy

O sistema suporta múltiplas empresas numa única instalação. O isolamento é feito por `client_id`.

### Como funciona

1. Cada empresa tem um registo em `clients`
2. Cada admin tem um `client_id` que o associa à sua empresa
3. **Todas** as queries de dados incluem `WHERE client_id = ?` com o `client_id` da sessão activa
4. Um admin só vê os funcionários, turnos, gorjetas e presenças da sua empresa
5. Não existe administrador global (super-admin) na versão actual

### Fluxo de criação de empresa

```
Registo de admin (admin/views/signup.php / admin/controllers/register_process.php)
          ↓
Criação automática de registo em clients (client_name = nome do admin)
          ↓
Criação de registo em usuarios com client_id → novo client
          ↓
Período de trial iniciado (trial_ends_at = NOW() + 14 dias, configurável)
```

---

## 15. Subscrições e Planos

**Ficheiros**: `planos/index.php`, `includes/premium_access.php`

### Estados de subscrição

| Estado | Descrição | Acesso |
|--------|-----------|--------|
| `trial` | Período de avaliação gratuito | Funcionalidades básicas |
| `active` | Subscrição activa e paga | Todas as funcionalidades |
| `inactive` | Subscrição inactiva/expirada | Acesso bloqueado |
| `cancelled` | Subscrição cancelada | Acesso bloqueado |

### Controlo de acesso premium

`includes/premium_access.php` é incluído nas páginas do dashboard e valida:
1. Se o utilizador está autenticado
2. Se `subscription_status` é `active` ou `trial` com `trial_ends_at > NOW()`
3. Caso contrário, redireciona para a página de planos

---

## 16. Ferramentas e Diagnóstico

**Directório**: `tools/`

| Ferramenta | Descrição |
|-----------|-----------|
| `smoke_test_full_flow.php` | Teste end-to-end do fluxo principal |
| `smoke_test_runner.php` | Executor de testes de fumo |
| `atualizar_status_presencas.php` | Actualizar estados de presenças pendentes |
| `migrate_salary_fields.php` | Migração de campos de salário |
| `turnos/criar_tabela_turnos.php` | Criar tabela de turnos (migration) |
| `turnos/debug_turno.php` | Debug de problemas de turnos |
| `turnos/check_turnos.php` | Verificar integridade de turnos |
| `uploads/verificar_fotos.php` | Verificar consistência de fotos de perfil |
| `uploads/test-upload.html` | Interface de teste de upload |

**Admin migrations**:

| Ferramenta | Descrição |
|-----------|-----------|
| `admin/seed_test_data.php` | Carregar dados de teste |
| `admin/optimize_indexes.php` | Optimizar índices da base de dados |
| `includes/migrate_create_atividades_recentes.php` | Criar tabela de auditoria |

---

## 17. Fluxos de Trabalho Principais

### Fluxo: Onboarding de Novo Funcionário

```
1. Admin abre modal "Adicionar Funcionário" no dashboard
2. Preenche dados pessoais (nome, email, telefone, NIF, NISS)
3. Define cargo, departamento, data de admissão
4. Define salário base, subsídios, bonus
5. Define PIN de acesso ao portal
6. Guarda → API create_employee.php cria registo
7. (Opcional) Upload de documentos (contrato, BI)
8. (Opcional) Upload de foto de perfil
9. Funcionário já pode fazer login no portal
```

### Fluxo: Dia de Trabalho (Funcionário)

```
Manhã:
1. Funcionário acede ao portal (app/employee_login.php)
2. Autentica com ID + PIN
3. Clica "Registar Entrada" → registrar_ponto.php (tipo: entrada)

Durante o dia:
4. (Opcional) Reporta gorjeta → add_gorjeta_employee.php

Final do turno:
5. Clica "Registar Saída" → registrar_ponto.php (tipo: saida)
6. Termina sessão (logout)
```

### Fluxo: Processamento Mensal de Folha

```
Fim do mês:
1. Admin abre secção "Folha de Pagamento"
2. Selecciona mês e ano fiscal
3. Sistema calcula automaticamente para todos os funcionários
4. Admin revê valores (salário bruto, SS, IRS, líquido)
5. Ajusta variáveis se necessário (horas extra, bonus pontual)
6. Inclui gorjetas confirmadas do mês
7. Marca folha como "calculada"
8. Fecha o mês → guardarFolhaSnapshot() cria snapshot imutável
9. Marca pagamentos como "pago" após transferências
```

### Fluxo: Gestão de Justificativa de Falta

```
1. Funcionário submete justificativa no portal com documento
2. Registo criado em justificativas_presenca (status: pendente)
3. Notificação enviada ao gestor
4. Gestor abre modal de justificativas no dashboard
5. Visualiza documento anexo
6. Aprova ou rejeita com comentário
7. Estado da presença actualizado (ausente → justificado)
8. Notificação enviada ao funcionário
```

---

## 18. Limitações Conhecidas

| Limitação | Impacto | Recomendação |
|-----------|---------|--------------|
| Sem app móvel nativa | Interface web responsiva mas não optimizada para mobile | Considerar PWA ou app React Native no futuro |
| PIN de funcionário pode ser inseguro sem HTTPS | Em redes abertas, o PIN pode ser interceptado | **Obrigatório usar HTTPS em produção** |
| Sem super-admin / painel de administração global | Não é possível gerir múltiplos clientes a partir de uma só conta | Implementar perfil super-admin se necessário |
| Cálculo IRS em modo simplificado | SS e IRS a zero por defeito (configuração necessária) | Configurar tabelas fiscais no painel antes de processar folhas |
| Sem autenticação de dois factores (2FA) | Conta admin protegida apenas por palavra-passe | Implementar 2FA por email/SMS para ambiente de produção |
| Stripe como único gateway de pagamento | Dependência de um único fornecedor de pagamentos | Adicionar suporte a MBWay/SIBS para mercado PT |
| Log de auditoria básico | `atividades_recentes` não guarda valores anteriores (diff) | Implementar auditoria completa com valores before/after |
| Sem controlo de versão de documentos | Upload substitui documento existente | Implementar versionamento de documentos |

---

*Documentação gerada com base na análise completa da base de código do projeto RHNeto Pro.*  
*Data: Junho 2026 | Sistema: app-rhnetopro | Base de dados: sistema_cadastro*
