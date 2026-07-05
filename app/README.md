# 📱 Portal do Funcionário - RH Neto ProWeb

## 🎯 Acesso ao Portal

**URL:** `http://localhost:8000/app/employee_login.php`

### 📋 Credenciais de Acesso

Os funcionários fazem login com:
- **Nome completo** (cadastrado pelo admin)
- **PIN** (configurado pelo admin no dashboard)

---

## ✨ Funcionalidades Disponíveis

### 🕒 1. Registro de Ponto
- ✅ Marcar **Entrada**
- ✅ Marcar **Saída**
- ✅ Visualizar última marcação do dia
- ✅ Validação automática (não permite marcar saída sem entrada)

### 📅 2. Controle de Presença
- ✅ Marcar **Presente**
- ✅ Marcar **Falta**
- ✅ Ver status do dia atual
- ✅ Atualização em tempo real

### 💰 3. Registro de Gorjetas
- ✅ Registrar gorjetas recebidas
- ✅ Informar valor, turno e forma de pagamento
- ✅ Adicionar observações
- ✅ Ver total do mês atual
- ✅ Histórico das últimas gorjetas

### 🗓️ 4. Escala de Turnos
- ✅ Visualizar turnos atribuídos pelo admin
- ✅ Ver horários de trabalho
- ✅ Conferir dias da semana
- ✅ Consultar tipo de escala

---

## 🔄 Sincronização com Dashboard Admin

Todas as ações realizadas pelos funcionários são **automaticamente** refletidas no dashboard do administrador:

### ⚡ Atualizações em Tempo Real

1. **Registro de Ponto** → Atualiza tabela de Assiduidade do admin
2. **Marcação de Presença** → Aparece na lista de presença do dia
3. **Gorjetas Registradas** → Entra na seção de Gorjetas com status "Pendente"
4. **Atividades** → Todas as ações aparecem no painel de Atividades Recentes

### 📊 Visibilidade para o Admin

O administrador pode ver em tempo real:
- Quem fez entrada/saída
- Status de presença de cada funcionário
- Gorjetas registradas aguardando aprovação
- Histórico completo de ações

---

## 🎨 Interface

- ✅ Design moderno e responsivo
- ✅ Funciona em **desktop, tablet e smartphone**
- ✅ Notificações visuais (Toastify)
- ✅ Feedback imediato de ações
- ✅ Ícones intuitivos (Font Awesome)
- ✅ Cores organizadas por tipo de ação

---

## 🔐 Segurança

- ✅ Autenticação obrigatória via sessão PHP
- ✅ PIN criptografado (password_hash)
- ✅ Isolamento por client_id (multi-tenancy)
- ✅ Validações server-side
- ✅ Proteção contra SQL Injection
- ✅ Timeout automático de sessão

---

## 📱 Como Usar (Funcionário)

### Passo 1: Fazer Login
1. Acesse `http://localhost:8000/app/employee_login.php`
2. Digite seu **nome completo**
3. Digite seu **PIN** (fornecido pelo RH)
4. Clique em **Entrar no Portal**

### Passo 2: Registrar Ponto
1. No card "Registro de Ponto"
2. Clique em **Entrada** ao chegar
3. Clique em **Saída** ao sair

### Passo 3: Marcar Presença
1. No card "Presença Hoje"
2. Clique em **Presente** ou **Falta**

### Passo 4: Registrar Gorjeta
1. Clique em **Registrar Gorjeta**
2. Preencha:
   - Valor (€)
   - Turno (Manhã/Tarde/Noturno)
   - Forma de Pagamento
   - Origem (opcional)
   - Observações (opcional)
3. Clique em **Registrar**

### Passo 5: Ver Escala
1. Role até "Minha Escala de Turnos"
2. Confira seus horários e dias de trabalho

---

## 🛠️ Configuração para Administrador

### Como Configurar PIN do Funcionário

No dashboard admin:
1. Vá em **Funcionários**
2. Clique em **Editar** no funcionário
3. Na seção "Acesso ao Portal"
4. Digite um PIN (4-6 dígitos recomendado)
5. Salve

**O PIN é automaticamente criptografado e armazenado com segurança.**

### Como Criar Escala de Turnos

1. Vá em **Turnos**
2. Clique em **+ Criar Turno**
3. Selecione o funcionário
4. Defina tipo, horários, dias e escala
5. Salve

**O funcionário verá imediatamente em seu portal.**

---

## 🔧 Estrutura de Arquivos

```
app/
├── employee_login.php              # Tela de login
├── employee_auth.php               # Autenticação
├── portal.php                      # Portal principal (NOVO)
├── employee_logout.php             # Logout
├── registrar_ponto_session.php     # API - Registro de ponto
├── salvar_presenca_session.php     # API - Marcação de presença
└── employee_portal.php             # Portal antigo (deprecated)

api/gorjetas/
└── add_gorjeta_employee.php        # API - Registro de gorjeta
```

---

## 📊 Tabelas do Banco de Dados Utilizadas

- `employees` - Dados dos funcionários
- `registros_ponto` - Entrada/Saída
- `assiduidade` - Presença/Falta
- `gorjetas` - Gorjetas registradas
- `turnos` - Escalas de trabalho
- `atividades_recentes` - Log de ações

---

## 🚀 Melhorias Futuras

- [ ] Notificações push
- [ ] Solicitação de férias
- [ ] Chat com RH
- [ ] Ver holerites
- [ ] Histórico completo de ponto
- [ ] Relatório mensal pessoal
- [ ] Troca de turno entre colegas
- [ ] App mobile nativo

---

## ❓ Solução de Problemas

### "Funcionário não encontrado"
- Verifique se o nome está **exatamente** como cadastrado no sistema
- O nome não é case-sensitive (maiúsculas/minúsculas não importam)

### "PIN incorreto"
- Confirme o PIN com o administrador
- Se foi alterado recentemente, faça logout e login novamente

### "Entrada já registrada"
- Você já marcou entrada hoje
- Marque a saída antes de marcar nova entrada

### Gorjeta não aparece no dashboard
- Gorjetas ficam com status "Pendente"
- O admin precisa aprovar/marcar como "Pago"

---

## 📞 Suporte

Em caso de problemas técnicos, contacte o administrador do sistema ou o departamento de TI.

---

**Desenvolvido com ❤️ para RH Neto ProWeb**
