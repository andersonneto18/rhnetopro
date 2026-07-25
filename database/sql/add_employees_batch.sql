-- Adiciona funcionários ao client_id=51 (andersonneto)
-- PIN em texto simples: o sistema faz upgrade automático para hash no primeiro login.
INSERT INTO employees (name, status, startDate, client_id, pin, vacation_days) VALUES
('Nelita',   'active', CURDATE(), 51, '2001', 22),
('Osmar',    'active', CURDATE(), 51, '2002', 22),
('Edson',    'active', CURDATE(), 51, '2003', 22),
('Kelvio',   'active', CURDATE(), 51, '2004', 22),
('Ruben',    'active', CURDATE(), 51, '2005', 22),
('Tiago',    'active', CURDATE(), 51, '2006', 22),
('Esmy',     'active', CURDATE(), 51, '2007', 22),
('Joenesia', 'active', CURDATE(), 51, '2008', 22),
('Darcio',   'active', CURDATE(), 51, '2009', 22),
('Vanessa',  'active', CURDATE(), 51, '2010', 22),
('Nadia',    'active', CURDATE(), 51, '2011', 22),
('Rui',      'active', CURDATE(), 51, '2012', 22),
('Sofia',    'active', CURDATE(), 51, '2013', 22),
('Amelia',   'active', CURDATE(), 51, '2014', 22),
('Celina',   'active', CURDATE(), 51, '2015', 22);
