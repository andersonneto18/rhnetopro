-- Escala de turnos de demonstração para a apresentação da aplicação.
-- Restaurante aberto todos os dias; cada funcionário trabalha 5 dias e folga 2
-- (dias de folga escalonados por pessoa, para haver sempre cobertura na equipa).
-- Início: segunda-feira 2026-07-27. Sem data de fim (turno contínuo).
--
-- NOTA: ao correr este ficheiro via `mysql < ficheiro.sql` na consola, os
-- acentos de "Terça"/"Sábado" podem ficar corrompidos consoante o charset da
-- shell. Prefira executar via PHP/PDO (charset=utf8, tal como a app usa) ou
-- garanta `mysql --default-character-set=utf8mb4` ao importar.

SET NAMES utf8mb4;

-- Equipa da Manhã (09:00–15:00)
INSERT INTO turnos (funcionario_id, turno_tipo, horario_inicio, horario_fim, dias_semana, data_inicio, data_fim, status) VALUES
(999410, 'Manhã', '09:00:00', '15:00:00', 'Quarta, Quinta, Sexta, Sábado, Domingo', '2026-07-27', NULL, 'ativo'), -- Anderson Neto — folga Seg, Ter
(999411, 'Manhã', '09:00:00', '15:00:00', 'Segunda, Quinta, Sexta, Sábado, Domingo', '2026-07-27', NULL, 'ativo'), -- Nelita — folga Ter, Qua
(999412, 'Manhã', '09:00:00', '15:00:00', 'Segunda, Terça, Sexta, Sábado, Domingo', '2026-07-27', NULL, 'ativo'), -- Osmar — folga Qua, Qui
(999413, 'Manhã', '09:00:00', '15:00:00', 'Segunda, Terça, Quarta, Sábado, Domingo', '2026-07-27', NULL, 'ativo'), -- Edson — folga Qui, Sex
(999414, 'Manhã', '09:00:00', '15:00:00', 'Segunda, Terça, Quarta, Quinta, Domingo', '2026-07-27', NULL, 'ativo'), -- Kelvio — folga Sex, Sab
(999415, 'Manhã', '09:00:00', '15:00:00', 'Segunda, Terça, Quarta, Quinta, Sexta', '2026-07-27', NULL, 'ativo'), -- Ruben — folga Sab, Dom
(999416, 'Manhã', '09:00:00', '15:00:00', 'Terça, Quarta, Quinta, Sexta, Sábado', '2026-07-27', NULL, 'ativo'), -- Tiago — folga Dom, Seg
(999417, 'Manhã', '09:00:00', '15:00:00', 'Terça, Quarta, Sexta, Sábado, Domingo', '2026-07-27', NULL, 'ativo'); -- Esmy — folga Seg, Qui

-- Equipa da Noite (17:00–23:00)
INSERT INTO turnos (funcionario_id, turno_tipo, horario_inicio, horario_fim, dias_semana, data_inicio, data_fim, status) VALUES
(999418, 'Noite', '17:00:00', '23:00:00', 'Quarta, Quinta, Sexta, Sábado, Domingo', '2026-07-27', NULL, 'ativo'), -- Joenesia — folga Seg, Ter
(999419, 'Noite', '17:00:00', '23:00:00', 'Segunda, Quinta, Sexta, Sábado, Domingo', '2026-07-27', NULL, 'ativo'), -- Darcio — folga Ter, Qua
(999420, 'Noite', '17:00:00', '23:00:00', 'Segunda, Terça, Sexta, Sábado, Domingo', '2026-07-27', NULL, 'ativo'), -- Vanessa — folga Qua, Qui
(999421, 'Noite', '17:00:00', '23:00:00', 'Segunda, Terça, Quarta, Sábado, Domingo', '2026-07-27', NULL, 'ativo'), -- Nadia — folga Qui, Sex
(999422, 'Noite', '17:00:00', '23:00:00', 'Segunda, Terça, Quarta, Quinta, Domingo', '2026-07-27', NULL, 'ativo'), -- Rui — folga Sex, Sab
(999423, 'Noite', '17:00:00', '23:00:00', 'Segunda, Terça, Quarta, Quinta, Sexta', '2026-07-27', NULL, 'ativo'), -- Sofia — folga Sab, Dom
(999424, 'Noite', '17:00:00', '23:00:00', 'Terça, Quarta, Quinta, Sexta, Sábado', '2026-07-27', NULL, 'ativo'), -- Amelia — folga Dom, Seg
(999425, 'Noite', '17:00:00', '23:00:00', 'Terça, Quarta, Sexta, Sábado, Domingo', '2026-07-27', NULL, 'ativo'); -- Celina — folga Seg, Qui
