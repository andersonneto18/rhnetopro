<?php

/**
 * Regras e calculo de folha (Portugal) com:
 * - SS trabalhador sobre remuneracao bruta
 * - IRS por tabela de escaloes (taxa + parcela a abater)
 * - Snapshot mensal para evitar alteracao retroativa por novas regras
 */

if (!function_exists('payrollTableExists')) {
    function payrollTableExists(PDO $pdo, string $table): bool
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        $cache[$table] = (bool)$stmt->fetchColumn();
        return $cache[$table];
    }
}

if (!function_exists('payrollColumnExists')) {
    function payrollColumnExists(PDO $pdo, string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        if (!payrollTableExists($pdo, $table)) {
            $cache[$key] = false;
            return false;
        }

        $sql = 'SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '` LIKE ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$column]);
        $cache[$key] = (bool)$stmt->fetchColumn();
        return $cache[$key];
    }
}

if (!function_exists('ensurePayrollTaxTables')) {
    function ensurePayrollTaxTables(PDO $pdo, int $fiscalYear): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS folha_pagamento (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                client_id INT NOT NULL,
                employee_id INT NOT NULL,
                fiscal_year INT NOT NULL,
                fiscal_month TINYINT NOT NULL,
                salario_base DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                subsidio_alimentacao DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                horas_extra DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                bonus DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                gorjetas DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                salario_bruto DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                base_seguranca_social DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                ss_rate DECIMAL(8,5) NULL,
                seguranca_social DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                seguranca_social_empresa DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                base_irs DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                irs DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                irs_rate_applied DECIMAL(8,5) NULL,
                total_descontos DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                salario_liquido DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                custo_total_empresa DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                status VARCHAR(32) NOT NULL DEFAULT 'calculado',
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_folha_pagamento_periodo (client_id, employee_id, fiscal_year, fiscal_month),
                KEY idx_folha_pagamento_periodo (client_id, fiscal_year, fiscal_month),
                KEY idx_folha_pagamento_employee (client_id, employee_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS tax_rules (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                fiscal_year INT NOT NULL,
                social_security_rate DECIMAL(8,5) NOT NULL DEFAULT 0.11000,
                employer_social_security_rate DECIMAL(8,5) NOT NULL DEFAULT 0.23750,
                irs_calculation_method VARCHAR(32) NOT NULL DEFAULT 'table_deduction',
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_tax_rules_year_active (fiscal_year, active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS irs_brackets (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tax_rule_id BIGINT UNSIGNED NOT NULL,
                min_amount DECIMAL(12,2) NOT NULL,
                max_amount DECIMAL(12,2) NULL,
                rate DECIMAL(8,5) NOT NULL,
                parcela_abater DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                bracket_order INT NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_irs_brackets_rule_order (tax_rule_id, bracket_order),
                CONSTRAINT fk_irs_brackets_tax_rule FOREIGN KEY (tax_rule_id) REFERENCES tax_rules(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        if (payrollTableExists($pdo, 'irs_brackets') && !payrollColumnExists($pdo, 'irs_brackets', 'parcela_abater')) {
            $pdo->exec("ALTER TABLE irs_brackets ADD COLUMN parcela_abater DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER rate");
        }

        if (payrollTableExists($pdo, 'folha_pagamento')) {
            $snapshotColumns = [
                'irs_parcela_abater' => "ALTER TABLE folha_pagamento ADD COLUMN irs_parcela_abater DECIMAL(12,2) NULL AFTER irs_rate_applied",
                'irs_bracket_min' => "ALTER TABLE folha_pagamento ADD COLUMN irs_bracket_min DECIMAL(12,2) NULL AFTER irs_parcela_abater",
                'irs_bracket_max' => "ALTER TABLE folha_pagamento ADD COLUMN irs_bracket_max DECIMAL(12,2) NULL AFTER irs_bracket_min",
                'irs_formula' => "ALTER TABLE folha_pagamento ADD COLUMN irs_formula VARCHAR(190) NULL AFTER irs_bracket_max",
            ];

            foreach ($snapshotColumns as $col => $sql) {
                if (!payrollColumnExists($pdo, 'folha_pagamento', $col)) {
                    $pdo->exec($sql);
                }
            }

            // Colunas de controlo de pagamento
            $pagamentoColumns = [
                'status_pagamento' => "ALTER TABLE folha_pagamento ADD COLUMN status_pagamento ENUM('pendente','pago') NOT NULL DEFAULT 'pendente' AFTER irs_formula",
                'data_pagamento'   => 'ALTER TABLE folha_pagamento ADD COLUMN data_pagamento DATETIME NULL DEFAULT NULL AFTER status_pagamento',
            ];
            foreach ($pagamentoColumns as $col => $sql) {
                if (!payrollColumnExists($pdo, 'folha_pagamento', $col)) {
                    $pdo->exec($sql);
                }
            }
        }

        $stmtRule = $pdo->prepare('SELECT id FROM tax_rules WHERE fiscal_year = ? AND active = 1 ORDER BY id DESC LIMIT 1');
        $stmtRule->execute([$fiscalYear]);
        $ruleId = (int)$stmtRule->fetchColumn();

        if ($ruleId <= 0) {
            $insertRule = $pdo->prepare(
                "INSERT INTO tax_rules (fiscal_year, social_security_rate, employer_social_security_rate, irs_calculation_method, active)
                 VALUES (?, 0.00000, 0.00000, 'table_deduction', 1)"
            );
            $insertRule->execute([$fiscalYear]);
            $ruleId = (int)$pdo->lastInsertId();
        }

        if ($ruleId > 0) {
            $stmtB = $pdo->prepare('SELECT COUNT(*) FROM irs_brackets WHERE tax_rule_id = ?');
            $stmtB->execute([$ruleId]);
            $hasBrackets = (int)$stmtB->fetchColumn() > 0;

            if (!$hasBrackets) {
                $insertBracket = $pdo->prepare(
                    'INSERT INTO irs_brackets (tax_rule_id, min_amount, max_amount, rate, parcela_abater, bracket_order)
                     VALUES (?, 0.00, NULL, 0.00000, 0.00, 1)'
                );
                $insertBracket->execute([$ruleId]);
            }
        }
    }
}

if (!function_exists('ensurePayrollSettingsTable')) {
    function ensurePayrollSettingsTable(PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS payroll_settings (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                client_id INT NOT NULL,
                fiscal_year INT NOT NULL,
                default_subsidios DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                default_horas_extra DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                default_bonus DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                gorjetas_auto_split TINYINT(1) NOT NULL DEFAULT 0,
                gorjetas_total_mes DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                updated_by INT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_payroll_settings (client_id, fiscal_year),
                KEY idx_payroll_settings_client_year (client_id, fiscal_year)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        // Migração idempotente: garantir colunas de gorjetas existem
        $gorjetaCols = [
            'gorjetas_auto_split' => "ALTER TABLE payroll_settings ADD COLUMN gorjetas_auto_split TINYINT(1) NOT NULL DEFAULT 0",
            'gorjetas_total_mes'  => "ALTER TABLE payroll_settings ADD COLUMN gorjetas_total_mes DECIMAL(12,2) NOT NULL DEFAULT 0.00",
        ];
        foreach ($gorjetaCols as $col => $sql) {
            if (!payrollColumnExists($pdo, 'payroll_settings', $col)) {
                $pdo->exec($sql);
            }
        }
    }
}

if (!function_exists('obterConfiguracaoFolha')) {
    function obterConfiguracaoFolha(PDO $pdo, int $clientId, int $fiscalYear): array
    {
        ensurePayrollSettingsTable($pdo);

        $defaults = [
            'default_subsidios'   => 0.0,
            'default_horas_extra' => 0.0,
            'default_bonus'       => 0.0,
            'gorjetas_auto_split' => 0,
            'gorjetas_total_mes'  => 0.0,
        ];

        $stmt = $pdo->prepare(
            'SELECT default_subsidios, default_horas_extra, default_bonus,
                    gorjetas_auto_split, gorjetas_total_mes
             FROM payroll_settings
             WHERE client_id = ? AND fiscal_year = ?
             LIMIT 1'
        );
        $stmt->execute([$clientId, $fiscalYear]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return $defaults;
        }

        return [
            'default_subsidios'   => isset($row['default_subsidios'])   ? (float)$row['default_subsidios']   : 0.0,
            'default_horas_extra' => isset($row['default_horas_extra']) ? (float)$row['default_horas_extra'] : 0.0,
            'default_bonus'       => isset($row['default_bonus'])       ? (float)$row['default_bonus']       : 0.0,
            'gorjetas_auto_split' => isset($row['gorjetas_auto_split']) ? (int)$row['gorjetas_auto_split']   : 0,
            'gorjetas_total_mes'  => isset($row['gorjetas_total_mes'])  ? (float)$row['gorjetas_total_mes']  : 0.0,
        ];
    }
}

if (!function_exists('obterRegrasFiscais')) {
    function obterRegrasFiscais(PDO $pdo, int $fiscalYear): array
    {
        return [
            'fiscal_year' => $fiscalYear,
            'social_security_rate' => 0.0,
            'employer_social_security_rate' => 0.0,
            'irs_calculation_method' => 'table_deduction',
            'brackets' => [
                ['min_amount' => 0.00, 'max_amount' => null, 'rate' => 0.00, 'parcela_abater' => 0.00, 'bracket_order' => 1],
            ],
        ];
    }
}

if (!function_exists('calcularIRS')) {
    function calcularIRS(float $baseIrs, array $taxRules): array
    {
        $baseIrs = max(0.0, $baseIrs);
        $brackets = $taxRules['brackets'] ?? [];
        $method = strtolower((string)($taxRules['irs_calculation_method'] ?? 'table_deduction'));

        if (empty($brackets)) {
            return [
                'amount' => 0.0,
                'rate_applied' => 0.0,
                'parcela_abater' => 0.0,
                'bracket_min' => 0.0,
                'bracket_max' => null,
                'formula' => 'IRS = (salario x taxa) - parcela',
            ];
        }

        if ($method === 'marginal') {
            $irs = 0.0;
            foreach ($brackets as $bracket) {
                $min = (float)$bracket['min_amount'];
                $max = $bracket['max_amount'] !== null ? (float)$bracket['max_amount'] : null;
                $rate = (float)$bracket['rate'];

                if ($baseIrs <= $min) {
                    continue;
                }

                $taxableSlice = ($max === null)
                    ? ($baseIrs - $min)
                    : max(0.0, min($baseIrs, $max) - $min);

                $irs += $taxableSlice * $rate;
            }
            $effectiveRate = $baseIrs > 0 ? ($irs / $baseIrs) : 0.0;
            return [
                'amount' => $irs,
                'rate_applied' => $effectiveRate,
                'parcela_abater' => 0.0,
                'bracket_min' => 0.0,
                'bracket_max' => null,
                'formula' => 'IRS marginal progressivo',
            ];
        }

        $selected = null;
        foreach ($brackets as $bracket) {
            $min = (float)$bracket['min_amount'];
            $max = $bracket['max_amount'] !== null ? (float)$bracket['max_amount'] : null;
            $rate = (float)$bracket['rate'];
            $parcela = (float)($bracket['parcela_abater'] ?? 0.0);

            // Intervalo [min, max) para evitar ambiguidade nos limites entre escaloes.
            $inBracket = $baseIrs >= $min && ($max === null || $baseIrs < $max);
            if ($inBracket) {
                $selected = [
                    'rate' => $rate,
                    'parcela' => $parcela,
                    'min' => $min,
                    'max' => $max,
                ];
                break;
            }
        }

        if ($selected === null) {
            $last = end($brackets);
            $selected = [
                'rate' => (float)($last['rate'] ?? 0.0),
                'parcela' => (float)($last['parcela_abater'] ?? 0.0),
                'min' => (float)($last['min_amount'] ?? 0.0),
                'max' => isset($last['max_amount']) ? (($last['max_amount'] !== null) ? (float)$last['max_amount'] : null) : null,
            ];
        }

        $irs = max(0.0, ($baseIrs * $selected['rate']) - $selected['parcela']);
        $formula = sprintf('IRS = (%.2f x %.2f%%) - %.2f', $baseIrs, $selected['rate'] * 100, $selected['parcela']);

        return [
            'amount' => $irs,
            'rate_applied' => $selected['rate'],
            'parcela_abater' => $selected['parcela'],
            'bracket_min' => $selected['min'],
            'bracket_max' => $selected['max'],
            'formula' => $formula,
        ];
    }
}

if (!function_exists('obterSnapshotRegrasFolha')) {
    function obterSnapshotRegrasFolha(
        PDO $pdo,
        int $clientId,
        int $employeeId,
        int $fiscalYear,
        int $fiscalMonth,
        array $defaultRules
    ): array {
        if (!payrollTableExists($pdo, 'folha_pagamento')) {
            return $defaultRules;
        }

        $requiredCols = ['ss_rate', 'irs_rate_applied'];
        foreach ($requiredCols as $col) {
            if (!payrollColumnExists($pdo, 'folha_pagamento', $col)) {
                return $defaultRules;
            }
        }

        $cols = ['ss_rate', 'irs_rate_applied'];
        if (payrollColumnExists($pdo, 'folha_pagamento', 'irs_parcela_abater')) {
            $cols[] = 'irs_parcela_abater';
        }
        if (payrollColumnExists($pdo, 'folha_pagamento', 'irs_bracket_min')) {
            $cols[] = 'irs_bracket_min';
        }
        if (payrollColumnExists($pdo, 'folha_pagamento', 'irs_bracket_max')) {
            $cols[] = 'irs_bracket_max';
        }

        $sql = sprintf(
            'SELECT %s FROM folha_pagamento WHERE client_id = ? AND employee_id = ? AND fiscal_year = ? AND fiscal_month = ? LIMIT 1',
            implode(', ', $cols)
        );
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$clientId, $employeeId, $fiscalYear, $fiscalMonth]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return $defaultRules;
        }

        $ssRate = isset($row['ss_rate']) ? (float)$row['ss_rate'] : 0.0;
        $irsRate = isset($row['irs_rate_applied']) ? (float)$row['irs_rate_applied'] : 0.0;
        if ($ssRate <= 0 && $irsRate <= 0) {
            return $defaultRules;
        }

        $parcela = isset($row['irs_parcela_abater']) ? (float)$row['irs_parcela_abater'] : 0.0;
        $bMin = isset($row['irs_bracket_min']) ? (float)$row['irs_bracket_min'] : 0.0;
        $bMax = array_key_exists('irs_bracket_max', $row)
            ? (($row['irs_bracket_max'] !== null) ? (float)$row['irs_bracket_max'] : null)
            : null;

        $snap = $defaultRules;
        if ($ssRate > 0) {
            $snap['social_security_rate'] = $ssRate;
        }
        if ($irsRate > 0) {
            $snap['irs_calculation_method'] = 'table_deduction';
            $snap['brackets'] = [[
                'min_amount' => $bMin,
                'max_amount' => $bMax,
                'rate' => $irsRate,
                'parcela_abater' => $parcela,
                'bracket_order' => 1,
            ]];
        }

        return $snap;
    }
}

if (!function_exists('calcularFolhaPagamento')) {
    function calcularFolhaPagamento(array $dadosFuncionario, array $taxRules): array
    {
        $salarioBase = (float)($dadosFuncionario['salario_base'] ?? 0);
        $subsidioAlimentacao = (float)($dadosFuncionario['subsidio_alimentacao'] ?? 0);
        $subsidiosTributaveis = (float)($dadosFuncionario['subsidios_tributaveis'] ?? 0);
        $horasExtra = (float)($dadosFuncionario['horas_extra'] ?? 0);
        $bonus = (float)($dadosFuncionario['bonus'] ?? 0);
        $gorjetas = (float)($dadosFuncionario['gorjetas'] ?? 0);

        // 1) Remuneracao bruta (todos os rendimentos tributaveis para esta implementacao)
        $totalSubsidios = $subsidioAlimentacao + $subsidiosTributaveis;
        $salarioBruto = $salarioBase + $horasExtra + $bonus + $totalSubsidios + $gorjetas;

        // 2) Base de incidencia (mantida para compatibilidade de estrutura)
        $baseIncidencia = $salarioBruto;

        // 3) Modo simplificado: sem descontos de SS/IRS
        $taxaSegurancaSocial = 0.0;
        $baseSegurancaSocial = $baseIncidencia;
        $segurancaSocial = 0.0;

        // 4) Sem encargos adicionais no modo simplificado
        $taxaSegurancaSocialEmpresa = 0.0;
        $segurancaSocialEmpresa = 0.0;

        // 5) Base IRS mantida para compatibilidade
        $baseIrs = $baseIncidencia;

        // 6) IRS desativado no modo simplificado
        $irs = 0.0;
        $irsRateApplied = 0.0;
        $irsParcelaAbater = 0.0;
        $irsBracketMin = 0.0;
        $irsBracketMax = null;
        $irsFormula = 'Sem descontos';

        // 7) Total de descontos
        $totalDescontos = 0.0;

        // 8) Salario liquido
        $salarioLiquido = $salarioBruto - $totalDescontos;

        // 9) Custo total para a empresa
        $custoTotalEmpresa = $salarioBruto + $segurancaSocialEmpresa;

        return [
            'salario_base' => round($salarioBase, 2),
            'subsidio_alimentacao' => round($subsidioAlimentacao, 2),
            'subsidios_tributaveis' => round($subsidiosTributaveis, 2),
            'total_subsidios' => round($totalSubsidios, 2),
            'horas_extra' => round($horasExtra, 2),
            'bonus' => round($bonus, 2),
            'gorjetas' => round($gorjetas, 2),
            'base_incidencia' => round($baseIncidencia, 2),
            'remuneracao_bruta' => round($salarioBruto, 2),
            'salario_bruto' => round($salarioBruto, 2),
            'base_seguranca_social' => round($baseSegurancaSocial, 2),
            'ss_rate' => round($taxaSegurancaSocial, 5),
            'seguranca_social' => round($segurancaSocial, 2),
            'seguranca_social_empresa' => round($segurancaSocialEmpresa, 2),
            'base_irs' => round($baseIrs, 2),
            'irs' => round($irs, 2),
            'irs_rate_applied' => round($irsRateApplied, 5),
            'irs_parcela_abater' => round($irsParcelaAbater, 2),
            'irs_bracket_min' => round($irsBracketMin, 2),
            'irs_bracket_max' => $irsBracketMax !== null ? round($irsBracketMax, 2) : null,
            'irs_formula' => $irsFormula,
            'total_descontos' => round($totalDescontos, 2),
            'salario_liquido' => round($salarioLiquido, 2),
            'custo_total_empresa' => round($custoTotalEmpresa, 2),
        ];
    }
}

if (!function_exists('upsertFolhaPagamento')) {
    function upsertFolhaPagamento(PDO $pdo, int $clientId, int $employeeId, int $fiscalYear, int $fiscalMonth, array $resultado): void
    {
        if (!payrollTableExists($pdo, 'folha_pagamento')) {
            return;
        }

        $valueMap = [
            'client_id' => $clientId,
            'employee_id' => $employeeId,
            'fiscal_year' => $fiscalYear,
            'fiscal_month' => $fiscalMonth,
            'salario_base' => (float)($resultado['salario_base'] ?? 0),
            'subsidio_alimentacao' => (float)($resultado['subsidio_alimentacao'] ?? 0),
            'horas_extra' => (float)($resultado['horas_extra'] ?? 0),
            'bonus' => (float)($resultado['bonus'] ?? 0),
            'gorjetas' => (float)($resultado['gorjetas'] ?? 0),
            'salario_bruto' => (float)($resultado['salario_bruto'] ?? 0),
            'base_seguranca_social' => (float)($resultado['base_seguranca_social'] ?? 0),
            'ss_rate' => isset($resultado['ss_rate']) ? (float)$resultado['ss_rate'] : null,
            'seguranca_social' => (float)($resultado['seguranca_social'] ?? 0),
            'seguranca_social_empresa' => (float)($resultado['seguranca_social_empresa'] ?? 0),
            'base_irs' => (float)($resultado['base_irs'] ?? 0),
            'irs' => (float)($resultado['irs'] ?? 0),
            'irs_rate_applied' => isset($resultado['irs_rate_applied']) ? (float)$resultado['irs_rate_applied'] : null,
            'irs_parcela_abater' => isset($resultado['irs_parcela_abater']) ? (float)$resultado['irs_parcela_abater'] : null,
            'irs_bracket_min' => isset($resultado['irs_bracket_min']) ? (float)$resultado['irs_bracket_min'] : null,
            'irs_bracket_max' => array_key_exists('irs_bracket_max', $resultado) ? (($resultado['irs_bracket_max'] !== null) ? (float)$resultado['irs_bracket_max'] : null) : null,
            'irs_formula' => isset($resultado['irs_formula']) ? (string)$resultado['irs_formula'] : null,
            'total_descontos' => (float)($resultado['total_descontos'] ?? 0),
            'salario_liquido' => (float)($resultado['salario_liquido'] ?? 0),
            'custo_total_empresa' => (float)($resultado['custo_total_empresa'] ?? 0),
            'status' => 'calculado',
            // status_pagamento: só definido na primeira inserção; nunca sobrescrito no recalculo
            'status_pagamento' => 'pendente',
        ];

        $insertColumns = [];
        $insertParams = [];
        $updateClauses = [];
        $bind = [];

        foreach ($valueMap as $column => $value) {
            if (!payrollColumnExists($pdo, 'folha_pagamento', $column)) {
                continue;
            }

            $insertColumns[] = $column;
            $insertParams[] = ':' . $column;
            $bind[':' . $column] = $value;

            if (!in_array($column, ['client_id', 'employee_id', 'fiscal_year', 'fiscal_month', 'status_pagamento', 'data_pagamento'], true)) {
                $updateClauses[] = $column . ' = VALUES(' . $column . ')';
            }
        }

        if (empty($insertColumns)) {
            return;
        }

        if (payrollColumnExists($pdo, 'folha_pagamento', 'updated_at')) {
            $updateClauses[] = 'updated_at = NOW()';
        }

        $sql = 'INSERT INTO folha_pagamento (' . implode(', ', $insertColumns);
        if (payrollColumnExists($pdo, 'folha_pagamento', 'created_at')) {
            $sql .= ', created_at';
        }
        if (payrollColumnExists($pdo, 'folha_pagamento', 'updated_at')) {
            $sql .= ', updated_at';
        }
        $sql .= ') VALUES (' . implode(', ', $insertParams);
        if (payrollColumnExists($pdo, 'folha_pagamento', 'created_at')) {
            $sql .= ', NOW()';
        }
        if (payrollColumnExists($pdo, 'folha_pagamento', 'updated_at')) {
            $sql .= ', NOW()';
        }
        $sql .= ') ON DUPLICATE KEY UPDATE ' . implode(', ', $updateClauses);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($bind);
    }
}

if (!function_exists('ensurePayrollHistoricoTable')) {
    function ensurePayrollHistoricoTable(PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS folha_pagamento_historico (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                client_id INT NOT NULL,
                employee_id INT NOT NULL,
                fiscal_year INT NOT NULL,
                fiscal_month TINYINT NOT NULL,
                snapshot_json LONGTEXT NOT NULL COMMENT 'JSON com todos os dados da folha (imutável)',
                snapshot_data_base64 LONGTEXT NULL COMMENT 'Backup em base64 para integridade',
                closed_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                closed_by INT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_folha_historico (client_id, employee_id, fiscal_year, fiscal_month),
                KEY idx_folha_historico_period (client_id, fiscal_year, fiscal_month),
                KEY idx_folha_historico_employee (client_id, employee_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }
}

if (!function_exists('guardarFolhaSnapshot')) {
    function guardarFolhaSnapshot(
        PDO $pdo,
        int $clientId,
        int $employeeId,
        int $fiscalYear,
        int $fiscalMonth,
        array $folhaCalculos,
        ?int $closedBy = null
    ): bool {
        try {
            ensurePayrollHistoricoTable($pdo);

            $snapshotJson = json_encode($folhaCalculos, JSON_UNESCAPED_UNICODE);
            if ($snapshotJson === false) {
                error_log("erro ao codificar JSON do snapshot folha: " . json_last_error_msg());
                return false;
            }

            $stmt = $pdo->prepare(
                "INSERT INTO folha_pagamento_historico
                (client_id, employee_id, fiscal_year, fiscal_month, snapshot_json, closed_by, closed_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                snapshot_json = VALUES(snapshot_json),
                closed_by = VALUES(closed_by),
                closed_at = NOW(),
                updated_at = NOW()"
            );

            return $stmt->execute([$clientId, $employeeId, $fiscalYear, $fiscalMonth, $snapshotJson, $closedBy]);
        } catch (Exception $e) {
            error_log("Erro ao guardar snapshot folha: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('obterFolhaHistorico')) {
    function obterFolhaHistorico(
        PDO $pdo,
        int $clientId,
        int $employeeId,
        int $fiscalYear,
        int $fiscalMonth
    ): ?array {
        try {
            if (!payrollTableExists($pdo, 'folha_pagamento_historico')) {
                return null;
            }

            $stmt = $pdo->prepare(
                "SELECT snapshot_json
                 FROM folha_pagamento_historico
                 WHERE client_id = ? AND employee_id = ? AND fiscal_year = ? AND fiscal_month = ?
                 LIMIT 1"
            );
            $stmt->execute([$clientId, $employeeId, $fiscalYear, $fiscalMonth]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row || empty($row['snapshot_json'])) {
                return null;
            }

            $decoded = json_decode($row['snapshot_json'], true);
            if (!is_array($decoded)) {
                error_log("Erro ao decodificar snapshot JSON folha");
                return null;
            }

            return $decoded;
        } catch (Exception $e) {
            error_log("Erro ao obter histórico folha: " . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('obterPeriodosComFolhas')) {
    function obterPeriodosComFolhas(PDO $pdo, int $clientId): array
    {
        $periodos = [];

        try {
            if (!payrollTableExists($pdo, 'folha_pagamento_historico')) {
                return $periodos;
            }

            $stmt = $pdo->prepare(
                "SELECT DISTINCT fiscal_year, fiscal_month
                 FROM folha_pagamento_historico
                 WHERE client_id = ?
                 ORDER BY fiscal_year DESC, fiscal_month DESC"
            );
            $stmt->execute([$clientId]);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $periodos[] = [
                    'fiscal_year' => (int)$row['fiscal_year'],
                    'fiscal_month' => (int)$row['fiscal_month'],
                ];
            }
        } catch (Exception $e) {
            error_log("Erro ao obter períodos com folhas: " . $e->getMessage());
        }

        return $periodos;
    }
}

if (!function_exists('verificarFolhaFechada')) {
    function verificarFolhaFechada(
        PDO $pdo,
        int $clientId,
        int $fiscalYear,
        int $fiscalMonth
    ): bool {
        try {
            $stmt = $pdo->prepare(
                "SELECT is_closed FROM folha_fechamentos_mensais
                 WHERE client_id = ? AND fiscal_year = ? AND fiscal_month = ?
                 LIMIT 1"
            );
            $stmt->execute([$clientId, $fiscalYear, $fiscalMonth]);
            $isClosed = (int)($stmt->fetchColumn() ?? 0);
            return $isClosed === 1;
        } catch (Exception $e) {
            error_log("Erro ao verificar folha fechada: " . $e->getMessage());
            return false;
        }
    }
}
