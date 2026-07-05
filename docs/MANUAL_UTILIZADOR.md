# Manual de Utilizador - RHNeto Pro

## ÍNDICE

1. Introdução
   1.1 Objetivo do manual
   1.2 Público-alvo
   1.3 Âmbito do documento
2. Requisitos de Sistema
   2.1 Requisitos de hardware
   2.2 Requisitos de software
   2.3 Sistemas operativos suportados
   2.4 Dependências adicionais
3. Instalação
   3.1 Obtenção do instalador
   3.2 Procedimento de instalação
   3.3 Configuração inicial
   3.4 Verificação da instalação
4. Funcionalidades
   4.1 Descrição geral da aplicação
   4.2 Funcionalidades principais
   4.3 Funcionalidades secundárias
   4.4 Limitações conhecidas
5. Utilização da Aplicação
   5.1 Interface do utilizador
   5.2 Operações básicas
   5.3 Operações avançadas
6. Resolução de Problemas
   6.1 Problemas frequentes
   6.2 Mensagens de erro
7. Manutenção e Atualizações
8. Suporte Técnico
9. Anexos

---

## 1. Introdução

### 1.1 Objetivo do manual

Este manual de utilizador destina-se a orientar administradores e funcionários na utilização do sistema **RHNeto Pro**, uma aplicação web para gestão de recursos humanos, incluindo cadastro de funcionários, turnos, gorjetas e marcação de presenças.

### 1.2 Público-alvo

- Administradores do sistema (gestores de RH)
- Funcionários que utilizam o portal para registar ponto e consultar informações pessoais.
- Técnicos de TI responsáveis pela instalação e manutenção do sistema.

### 1.3 Âmbito do documento

O documento cobre requisitos de sistema, instalação, descrição de funcionalidades, utilização diária da aplicação, resolução de problemas comuns, e procedimentos de manutenção e suporte.

## 2. Requisitos de Sistema

### 2.1 Requisitos de hardware

- Servidor com pelo menos 2 CPU cores, 4 GB de RAM.
- Espaço em disco suficiente para armazenar uploads de documentos e imagens.
- Conexão de rede estável.

### 2.2 Requisitos de software

- PHP 7.4 ou superior com extensões PDO e mysqli.
- Servidor web Apache ou Nginx.
- MySQL/MariaDB 5.7+.

### 2.3 Sistemas operativos suportados

- Windows (XAMPP, WAMP).
- Linux (Ubuntu, CentOS, Debian).
- macOS (MAMP).

### 2.4 Dependências adicionais

- Biblioteca GD ou Imagick para manipulação de imagens.
- Permissões de escrita nas pastas `uploads/` e subdiretórios.

## 3. Instalação

### 3.1 Obtenção do instalador

O sistema é distribuído como um pacote de ficheiros PHP. Obtenha-o via repositório Git, cópia ZIP ou fornecedor do software.

### 3.2 Procedimento de instalação

1. Descompactar os ficheiros no diretório raiz do servidor web (`htdocs` ou `www`).
2. Configurar a base de dados. Execute os scripts SQL em `database/sql/` para criar tabelas como `turnos`.
3. Ajustar as credenciais na configuração de base de dados (`config/db_connection.php`).

### 3.3 Configuração inicial

1. Aceder a `admin/signup.php` para criar o utilizador administrador inicial ou inserir diretamente na tabela `usuarios`.
2. Verificar que as pastas `uploads/documents` e `uploads/profile` têm permissões de gravação.
3. (Opcional) Executar scripts de migração em `includes/migrate_*.php` para ativar recursos adicionais.

### 3.4 Verificação da instalação

1. Abrir `admin/login.php` e efetuar login.
2. Confirmar que o dashboard carrega sem erros.
3. Verificar upload de um documento e alteração de foto de perfil.

## 4. Funcionalidades

### 4.1 Descrição geral da aplicação

O RHNeto Pro permite que administradores gerem dados de funcionários, definam turnos de trabalho, registem gorjetas e verifiquem presenças. Os funcionários têm um portal separado para registar ponto via PIN e consultar informações.

### 4.2 Funcionalidades principais

- Cadastro e edição de funcionários.
- Upload de documentos pessoais e fotos de perfil.
- Criação e gestão de turnos.
- Registo e validação de presenças.
- Gestão de gorjetas (nomeadamente confirmação e rejeição).
- Painel administrativo com notificações e relatório de atividades.

### 4.3 Funcionalidades secundárias

- Sistema de migrações para atualização da base de dados.
- Ferramentas de diagnóstico (em `tools/`) para turnos e uploads.
- Demonstrações de interface (`demos/`).

### 4.4 Limitações conhecidas

- Não existem versões mobile nativas; a interface é responsiva mas depende de navegador.
- A autenticação de funcionários é baseada em PIN simples (pode ser insegura se não configurada via HTTPS).
- O sistema não implementa auditoria detalhada além do log de atividades básicas.

## 5. Utilização da Aplicação

### 5.1 Interface do utilizador

- **Dashboard Admin** (`admin/dashboard.php`) com menus laterais para funcionários, turnos, gorjetas e presenças.
- **Portal Funcionário** (`app/portal.php` ou `employee/employee_portal.php`) com formulário de login por PIN e visão de pontos.

### 5.2 Operações básicas

- Adicionar funcionário: menu `Funcionários` > `Adicionar`. Preencher formulário e salvar.
- Criar turno: menu `Turnos` > `Novo Turno`. Selecionar funcionário e horários.
- Registar ponto (funcionário): entrar com PIN, clicar em `Registrar Ponto`.
- Consultar documentos: menu `Documentos` > `Visualizar`.

### 5.3 Operações avançadas

- Validar presenças: no dashboard, clicar em `Presenças` e alterar estado.
- Confirmar/Rejeitar gorjetas: menu `Gorjetas` > ação correspondente.
- Atualizar schema via scripts em `includes/` quando novas versões exigirem alterações.

## 6. Resolução de Problemas

### 6.1 Problemas frequentes

- **Erro de conexão ao BD**: verificar credenciais em `config/db_connection.php` e se o servidor MySQL está em execução.
- **Uploads falham**: conferir permissões dos diretórios `uploads/*` e tamanho máximo configurado em `php.ini`.
- **Sessão expira**: certifique‑se de que `session.save_path` está escrita e que o navegador aceita cookies.

### 6.2 Mensagens de erro

O sistema geralmente exibe mensagens em toasts ou boxes vermelhas com ícones. Registre a mensagem e analise os logs em `php_error.log` ou o painel do servidor.

## 7. Manutenção e Atualizações

- Copiar ficheiros da nova versão sobre a instalação existente (fazer backup prévio).
- Executar quaisquer scripts de migração em `includes/migrate_*.php` para adaptar o esquema.
- Limpar cache do navegador e reiniciar serviços após atualizações.

## 8. Suporte Técnico

Para suporte, contate o responsável de TI ou fornecedor do software, fornecendo detalhes do ambiente (sistema operativo, versão PHP, logs de erro).

## 9. Anexos

- **Exemplo de script SQL**: `database/sql/sql_create_turnos.sql`.
- **Demonstrações**: abra `demos/demo-avatares.html` e `demos/demo-notificacoes.html` no browser.

---

*Documento gerado automaticamente com base na estrutura do projeto.*
