-- RH Neto ProWeb
-- Instalacao profissional: schema apenas (sem dados)
-- Versao para hospedagem partilhada (Hostinger): SEM CREATE DATABASE / USE.
-- Selecione a base "u673069353_rh" no phpMyAdmin ANTES de rodar este script.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

SET FOREIGN_KEY_CHECKS = 0;

-- Drop em ordem de dependencia para reinstalacao limpa
DROP TABLE IF EXISTS admin_password_resets;
DROP TABLE IF EXISTS atividades_recentes;
DROP TABLE IF EXISTS employee_documents;
DROP TABLE IF EXISTS folha_pagamento_historico;
DROP TABLE IF EXISTS folha_pagamento;
DROP TABLE IF EXISTS folha_variaveis_mensais;
DROP TABLE IF EXISTS folha_fechamentos_mensais;
DROP TABLE IF EXISTS estabelecimento_horarios;
DROP TABLE IF EXISTS gorjetas;
DROP TABLE IF EXISTS historico_alteracoes_ponto;
DROP TABLE IF EXISTS justificativas_falta;
DROP TABLE IF EXISTS justificativas_presenca;
DROP TABLE IF EXISTS notificacoes;
DROP TABLE IF EXISTS presencas;
DROP TABLE IF EXISTS registros_ponto;
DROP TABLE IF EXISTS turnos;
DROP TABLE IF EXISTS ferias;
DROP TABLE IF EXISTS payroll_settings;
DROP TABLE IF EXISTS employees;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS clients;

SET FOREIGN_KEY_CHECKS = 1;

-- =========================
-- Tabelas principais
-- =========================

CREATE TABLE clients (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_name VARCHAR(255) NOT NULL,
  subscription_status VARCHAR(20) DEFAULT 'trial',
  subscription_plan VARCHAR(50) DEFAULT NULL,
  subscription_start_date DATETIME DEFAULT NULL,
  subscription_end_date DATETIME DEFAULT NULL,
  hotmart_transaction_id VARCHAR(255) DEFAULT NULL,
  hotmart_subscriber_code VARCHAR(255) DEFAULT NULL,
  trial_ends_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_clients_subscription (subscription_status, subscription_plan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE usuarios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome_completo VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
  telefone VARCHAR(20) DEFAULT NULL,
  nif VARCHAR(15) DEFAULT NULL,
  nome_usuario VARCHAR(100) NOT NULL,
  senha VARCHAR(255) NOT NULL,
  client_id INT UNSIGNED NOT NULL,
  profile_picture VARCHAR(255) DEFAULT NULL,
  subscription_status VARCHAR(20) DEFAULT 'trial',
  subscription_plan VARCHAR(50) DEFAULT NULL,
  subscription_start_date DATETIME DEFAULT NULL,
  subscription_end_date DATETIME DEFAULT NULL,
  hotmart_transaction_id VARCHAR(255) DEFAULT NULL,
  hotmart_subscriber_code VARCHAR(255) DEFAULT NULL,
  trial_ends_at DATETIME DEFAULT NULL,
  data_cadastro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_email (email),
  UNIQUE KEY uq_usuarios_nome_usuario (nome_usuario),
  UNIQUE KEY uq_usuarios_client_nif (client_id, nif),
  KEY idx_usuarios_client (client_id),
  CONSTRAINT fk_usuarios_client
    FOREIGN KEY (client_id) REFERENCES clients(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admin_password_resets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  email VARCHAR(255) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME DEFAULT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_admin_resets_user (user_id),
  KEY idx_admin_resets_email (email),
  KEY idx_admin_resets_token (token_hash),
  KEY idx_admin_resets_expires (expires_at),
  CONSTRAINT fk_admin_resets_user
    FOREIGN KEY (user_id) REFERENCES usuarios(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE employees (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id INT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL,
  position VARCHAR(100) DEFAULT NULL,
  department VARCHAR(100) DEFAULT NULL,
  email VARCHAR(255) DEFAULT NULL,
  phone VARCHAR(255) DEFAULT NULL,
  startDate DATE DEFAULT NULL,
  status VARCHAR(50) DEFAULT 'active',
  pin_hash VARCHAR(255) DEFAULT NULL,
  -- Coluna legada mantida por compatibilidade. Use apenas pin_hash.
  pin VARCHAR(4) DEFAULT NULL,
  birthDate DATE DEFAULT NULL,
  nif VARCHAR(255) DEFAULT NULL,
  niss VARCHAR(255) DEFAULT NULL,
  address TEXT DEFAULT NULL,
  emergencyContact VARCHAR(255) DEFAULT NULL,
  contractType VARCHAR(50) DEFAULT NULL,
  profile_picture VARCHAR(255) DEFAULT NULL,
  salary_base DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  subsidio_alimentacao DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  bonus DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_employees_client_email (client_id, email),
  UNIQUE KEY uq_employees_client_nif (client_id, nif),
  UNIQUE KEY uq_employees_client_niss (client_id, niss),
  KEY idx_employees_client_status (client_id, status),
  KEY idx_employees_name (name),
  CONSTRAINT fk_employees_client
    FOREIGN KEY (client_id) REFERENCES clients(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE employee_documents (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  employee_id INT UNSIGNED NOT NULL,
  client_id INT UNSIGNED NOT NULL,
  document_name VARCHAR(255) NOT NULL,
  document_type VARCHAR(100) DEFAULT NULL,
  file_path VARCHAR(500) NOT NULL,
  file_size INT UNSIGNED DEFAULT NULL,
  file_extension VARCHAR(10) DEFAULT NULL,
  uploaded_by INT UNSIGNED DEFAULT NULL,
  description TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_docs_employee (employee_id),
  KEY idx_docs_client (client_id),
  CONSTRAINT fk_docs_employee
    FOREIGN KEY (employee_id) REFERENCES employees(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_docs_client
    FOREIGN KEY (client_id) REFERENCES clients(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE turnos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  funcionario_id INT UNSIGNED NOT NULL,
  turno_tipo VARCHAR(50) NOT NULL,
  horario_inicio TIME NOT NULL,
  horario_fim TIME NOT NULL,
  dias_semana VARCHAR(100) DEFAULT NULL,
  escala VARCHAR(50) DEFAULT NULL,
  status VARCHAR(20) DEFAULT 'ativo',
  gorjetas_base DECIMAL(10,2) DEFAULT 0.00,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_turnos_funcionario (funcionario_id),
  KEY idx_turnos_status (status),
  CONSTRAINT fk_turnos_employee
    FOREIGN KEY (funcionario_id) REFERENCES employees(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE estabelecimento_horarios (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id INT UNSIGNED NOT NULL,
  hora_abertura TIME NOT NULL DEFAULT '09:00:00',
  hora_encerramento TIME NOT NULL DEFAULT '23:00:00',
  hora_entrada_padrao TIME NOT NULL DEFAULT '09:00:00',
  tolerancia_atraso_min INT NOT NULL DEFAULT 5,
  updated_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_estabelecimento_client (client_id),
  CONSTRAINT fk_estabelecimento_client
    FOREIGN KEY (client_id) REFERENCES clients(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- Ponto, presença e justificativas
-- =========================

CREATE TABLE presencas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  funcionario_id INT UNSIGNED NOT NULL,
  client_id INT UNSIGNED NOT NULL,
  data_registro DATE NOT NULL,
  status VARCHAR(50) DEFAULT 'presente',
  obs TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_presencas_func_data (funcionario_id, data_registro),
  KEY idx_presencas_client_data (client_id, data_registro),
  CONSTRAINT fk_presencas_employee
    FOREIGN KEY (funcionario_id) REFERENCES employees(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_presencas_client
    FOREIGN KEY (client_id) REFERENCES clients(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE registros_ponto (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  funcionario_id INT UNSIGNED NOT NULL,
  client_id INT UNSIGNED NOT NULL,
  data_registro DATE NOT NULL,
  hora_entrada TIME DEFAULT NULL,
  hora_saida TIME DEFAULT NULL,
  obs TEXT DEFAULT NULL,
  status_confirmacao ENUM('pendente','confirmado') DEFAULT 'pendente',
  validado_admin TINYINT(1) DEFAULT 0,
  status VARCHAR(20) DEFAULT 'presente',
  tipo_dia VARCHAR(30) DEFAULT NULL,
  falta_tipo VARCHAR(20) DEFAULT NULL,
  observacao TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_registros_func_data (funcionario_id, data_registro),
  KEY idx_registros_client_data (client_id, data_registro),
  CONSTRAINT fk_registros_employee
    FOREIGN KEY (funcionario_id) REFERENCES employees(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_registros_client
    FOREIGN KEY (client_id) REFERENCES clients(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE historico_alteracoes_ponto (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  funcionario_id INT UNSIGNED NOT NULL,
  data_registro DATE NOT NULL,
  tipo_alteracao VARCHAR(50) NOT NULL,
  motivo TEXT NOT NULL,
  usuario_id INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_historico_func_data (funcionario_id, data_registro),
  CONSTRAINT fk_historico_employee
    FOREIGN KEY (funcionario_id) REFERENCES employees(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Estrutura alinhada com o auto-provisionamento em admin/sections/assiduidade.php
-- e app/justificar_ausencia.php (ambos usam employee_id/data_ocorrencia/tipo/anexo_path).
CREATE TABLE justificativas_presenca (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id INT NOT NULL,
  employee_id INT NOT NULL,
  data_ocorrencia DATE NOT NULL,
  tipo VARCHAR(60) NOT NULL DEFAULT 'falta',
  motivo TEXT NOT NULL,
  anexo_path VARCHAR(255) DEFAULT NULL,
  status ENUM('pendente','aprovada','rejeitada') NOT NULL DEFAULT 'pendente',
  admin_observacao TEXT DEFAULT NULL,
  decidido_por INT DEFAULT NULL,
  decidido_em DATETIME DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_justificativas_client_status (client_id, status),
  KEY idx_justificativas_employee_data (employee_id, data_ocorrencia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE justificativas_falta (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  funcionario_id INT UNSIGNED NOT NULL,
  data_falta DATE NOT NULL,
  tipo_justificativa VARCHAR(100) NOT NULL,
  descricao TEXT NOT NULL,
  documento_path VARCHAR(500) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_just_falta_func_data (funcionario_id, data_falta),
  CONSTRAINT fk_just_falta_employee
    FOREIGN KEY (funcionario_id) REFERENCES employees(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ferias (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id INT UNSIGNED NOT NULL,
  funcionario_id INT UNSIGNED NOT NULL,
  data_inicio DATE NOT NULL,
  data_fim DATE NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pendente',
  motivo TEXT DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ferias_client_employee (client_id, funcionario_id),
  KEY idx_ferias_status (status),
  CONSTRAINT fk_ferias_client
    FOREIGN KEY (client_id) REFERENCES clients(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_ferias_employee
    FOREIGN KEY (funcionario_id) REFERENCES employees(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- Notificacoes e atividade
-- =========================

CREATE TABLE notificacoes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  funcionario_id INT UNSIGNED NOT NULL,
  client_id INT UNSIGNED NOT NULL,
  mensagem TEXT NOT NULL,
  data_envio DATETIME DEFAULT CURRENT_TIMESTAMP,
  status_lida TINYINT(1) DEFAULT 0,
  lida TINYINT(1) DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_notif_func_data (funcionario_id, data_envio),
  KEY idx_notif_client_data (client_id, data_envio),
  CONSTRAINT fk_notif_employee
    FOREIGN KEY (funcionario_id) REFERENCES employees(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_notif_client
    FOREIGN KEY (client_id) REFERENCES clients(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE atividades_recentes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(255) NOT NULL,
  type VARCHAR(50) NOT NULL,
  status VARCHAR(50) DEFAULT NULL,
  timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  client_id INT UNSIGNED NOT NULL,
  employee_id INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_atividades_client_timestamp (client_id, timestamp),
  KEY idx_atividades_employee (employee_id),
  CONSTRAINT fk_atividades_client
    FOREIGN KEY (client_id) REFERENCES clients(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_atividades_employee
    FOREIGN KEY (employee_id) REFERENCES employees(id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- Gorjetas e folha (modo simples)
-- =========================

CREATE TABLE gorjetas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  funcionario_id INT UNSIGNED NOT NULL,
  client_id INT UNSIGNED NOT NULL,
  valor DECIMAL(10,2) NOT NULL,
  data DATE NOT NULL,
  turno VARCHAR(50) DEFAULT NULL,
  forma_pagamento VARCHAR(50) DEFAULT NULL,
  origem VARCHAR(50) DEFAULT NULL,
  status ENUM('pendente','pago','cancelado','rejeitado','aprovado','confirmado') DEFAULT 'pendente',
  observacao TEXT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_gorjetas_func_data (funcionario_id, data),
  KEY idx_gorjetas_client_data (client_id, data),
  KEY idx_gorjetas_status (status),
  CONSTRAINT fk_gorjetas_employee
    FOREIGN KEY (funcionario_id) REFERENCES employees(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_gorjetas_client
    FOREIGN KEY (client_id) REFERENCES clients(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payroll_settings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id INT UNSIGNED NOT NULL,
  fiscal_year INT NOT NULL,
  default_subsidios DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  default_horas_extra DECIMAL(12,2) NOT NULL DEFAULT 1.00,
  default_bonus DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  gorjetas_auto_split TINYINT(1) NOT NULL DEFAULT 0,
  gorjetas_total_mes DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  updated_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payroll_settings_client_year (client_id, fiscal_year),
  CONSTRAINT fk_payroll_settings_client
    FOREIGN KEY (client_id) REFERENCES clients(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE folha_variaveis_mensais (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id INT UNSIGNED NOT NULL,
  employee_id INT UNSIGNED NOT NULL,
  fiscal_year INT NOT NULL,
  fiscal_month TINYINT NOT NULL,
  horas_extra DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  faltas_dias DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  bonus DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  subsidios_extra DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  outros_descontos DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  gorjeta_manual DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  status VARCHAR(20) NOT NULL DEFAULT 'ativo',
  is_locked TINYINT(1) NOT NULL DEFAULT 0,
  updated_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_folha_variaveis (client_id, employee_id, fiscal_year, fiscal_month),
  KEY idx_folha_variaveis_periodo (client_id, fiscal_year, fiscal_month),
  CONSTRAINT fk_folha_variaveis_client
    FOREIGN KEY (client_id) REFERENCES clients(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_folha_variaveis_employee
    FOREIGN KEY (employee_id) REFERENCES employees(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE folha_pagamento (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id INT UNSIGNED NOT NULL,
  employee_id INT UNSIGNED NOT NULL,
  fiscal_year INT NOT NULL,
  fiscal_month TINYINT NOT NULL,
  salario_base DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  subsidio_alimentacao DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  horas_extra DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  bonus DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  gorjetas DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  salario_bruto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  base_seguranca_social DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  ss_rate DECIMAL(6,5) DEFAULT NULL,
  seguranca_social DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  seguranca_social_empresa DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  base_irs DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  irs DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  irs_rate_applied DECIMAL(6,5) DEFAULT NULL,
  irs_parcela_abater DECIMAL(12,2) DEFAULT NULL,
  irs_bracket_min DECIMAL(12,2) DEFAULT NULL,
  irs_bracket_max DECIMAL(12,2) DEFAULT NULL,
  irs_formula VARCHAR(190) DEFAULT NULL,
  status_pagamento ENUM('pendente','pago') NOT NULL DEFAULT 'pendente',
  data_pagamento DATETIME DEFAULT NULL,
  total_descontos DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  salario_liquido DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  custo_total_empresa DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  status VARCHAR(30) NOT NULL DEFAULT 'calculado',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_folha_employee_periodo (employee_id, fiscal_year, fiscal_month),
  KEY idx_folha_client_periodo (client_id, fiscal_year, fiscal_month),
  CONSTRAINT fk_folha_pagamento_client
    FOREIGN KEY (client_id) REFERENCES clients(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_folha_pagamento_employee
    FOREIGN KEY (employee_id) REFERENCES employees(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE folha_pagamento_historico (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id INT UNSIGNED NOT NULL,
  employee_id INT UNSIGNED NOT NULL,
  fiscal_year INT NOT NULL,
  fiscal_month TINYINT NOT NULL,
  snapshot_json LONGTEXT NOT NULL,
  snapshot_data_base64 LONGTEXT DEFAULT NULL,
  closed_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  closed_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_folha_hist_employee_periodo (client_id, employee_id, fiscal_year, fiscal_month),
  KEY idx_folha_hist_periodo (client_id, fiscal_year, fiscal_month),
  CONSTRAINT fk_folha_hist_client
    FOREIGN KEY (client_id) REFERENCES clients(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_folha_hist_employee
    FOREIGN KEY (employee_id) REFERENCES employees(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE folha_fechamentos_mensais (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id INT UNSIGNED NOT NULL,
  fiscal_year INT NOT NULL,
  fiscal_month TINYINT NOT NULL,
  is_closed TINYINT(1) NOT NULL DEFAULT 0,
  closed_by INT UNSIGNED DEFAULT NULL,
  closed_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_folha_fechamento_periodo (client_id, fiscal_year, fiscal_month),
  CONSTRAINT fk_folha_fechamento_client
    FOREIGN KEY (client_id) REFERENCES clients(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fim: schema profissional sem dados.
