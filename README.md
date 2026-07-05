# Sistema de Gestão de Funcionários - RH Neto ProWeb

Sistema de gestão de recursos humanos para restauração.

## 📁 Estrutura do Projeto

```
rhneto-proweb/
├── admin/                      # Área administrativa
│   ├── login.php              # Login de administradores
│   ├── dashboard.php          # Dashboard administrativo
│   ├── logout.php             # Logout administrativo
│   └── ...
├── employee/                   # Portal do funcionário
│   ├── employee_login.php     # Login de funcionários
│   ├── employee_portal.php    # Portal do funcionário
│   └── ...
├── api/                        # APIs REST
│   ├── employees/             # CRUD de funcionários
│   ├── gorjetas/              # Gestão de gorjetas
│   ├── turnos/                # Gestão de turnos
│   └── presenca/              # Registo de ponto e presenças
├── config/                     # Configurações
│   ├── db_connect.php         # Conexão com banco de dados
│   └── db_connection.php
├── assets/                     # Recursos estáticos
│   └── images/                # Imagens
├── includes/                   # Arquivos auxiliares
└── index.php                   # Página inicial (redireciona para admin)
```

## 🚀 Como Usar

### Acesso Administrativo
- URL: `http://localhost/rhneto-proweb/admin/login.php`

### Portal do Funcionário
- URL: `http://localhost/rhneto-proweb/employee/employee_login.php`

## 🔧 Configuração

1. Certifique-se de que o XAMPP está rodando (Apache + MySQL)
2. Banco de dados: `sistema_cadastro`
3. As configurações de conexão estão em `/config/db_connect.php`

## 📝 Notas

- Após a reorganização, alguns caminhos de include foram atualizados
- Imagens movidas para `assets/images/`
- APIs organizadas por módulo
