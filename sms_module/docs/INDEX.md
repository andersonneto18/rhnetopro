# 📚 Documentação SMS Module - Índice Completo

Bem-vindo à documentação do módulo de envio de SMS! Aqui você encontra tudo sobre como o sistema funciona e como usá-lo.

## 📖 Guias Disponíveis

### 1. **QUICK_START.md** ⚡
**Para:** Iniciantes que querem começar rápido
- 3 passos para ativar SMS real
- Referência rápida de funções
- Checklist de configuração
- **Tempo: 5-10 minutos**

👉 [Leia QUICK_START.md](QUICK_START.md)

---

### 2. **INTEGRACAO_OUTRO_PROJETO.md** 🔧
**Para:** Desenvolvedores integrando em projetos novos
- Explicação detalhada de como funciona (5 camadas)
- Fluxo completo com diagrama
- Passo-a-passo de integração (6 etapas)
- 4 exemplos de código prontos para copiar
- Troubleshooting completo (5 problemas + soluções)
- Checklist de integração
- **Tempo: 30-45 minutos leitura + integração**

👉 [Leia INTEGRACAO_OUTRO_PROJETO.md](INTEGRACAO_OUTRO_PROJETO.md)

---

### 3. **README.md** 📋
**Para:** Referência completa do projeto
- Estrutura de pastas
- Descrição de cada arquivo
- Configuração inicial
- Uso via JavaScript
- Formato de resposta
- Schema do banco de dados
- Segurança
- Integração com projeto principal

👉 [Leia README.md](README.md)

---

### 4. **STRUCTURE.md** 🏗️
**Para:** Entender a arquitetura técnica
- Resumo de arquivos
- Referência de funções
- Integração no projeto
- Troubleshooting rápido
- Performance

👉 [Leia STRUCTURE.md](STRUCTURE.md)

---

## 🚀 Começar Agora

### Opção 1: Ativar SMS em Projeto Existente (5 min)
1. Leia: [QUICK_START.md](QUICK_START.md)
2. Execute os 3 passos
3. Teste no painel admin

### Opção 2: Integrar em Novo Projeto (30 min)
1. Leia: [INTEGRACAO_OUTRO_PROJETO.md](INTEGRACAO_OUTRO_PROJETO.md)
2. Siga os 6 passos de integração
3. Execute o script de teste
4. Envie primeiro SMS

### Opção 3: Entender a Arquitetura Completa
1. Leia: [INTEGRACAO_OUTRO_PROJETO.md - Seção 1](INTEGRACAO_OUTRO_PROJETO.md#como-funciona)
2. Leia: [INTEGRACAO_OUTRO_PROJETO.md - Seção 2](INTEGRACAO_OUTRO_PROJETO.md#fluxo-completo)
3. Consulte: [STRUCTURE.md](STRUCTURE.md)

---

## 📁 Estrutura de Pastas

```
sms_module/
├── config/
│   ├── sms_config.php              # Carregamento de config
│   └── sms_config.local.example.php # Exemplo com credenciais
│
├── includes/
│   └── sms_sender.php              # Funções principais
│
├── api/
│   └── notify_employees.php        # Endpoint POST
│
└── docs/
    ├── INDEX.md                    # Este arquivo
    ├── README.md                   # Referência completa
    ├── QUICK_START.md              # Guia rápido
    ├── INTEGRACAO_OUTRO_PROJETO.md # Integração detalhada
    └── STRUCTURE.md                # Arquitetura
```

---

## ⚡ Quick Links

### Configuração
- [Como configurar Infobip?](QUICK_START.md#checklist-de-configuração)
- [Como usar variáveis de ambiente?](README.md#configuração)
- [Credenciais não funcionam?](INTEGRACAO_OUTRO_PROJETO.md#problema-1-sms-real-não-configurado)

### Funcionalidades
- [Como normalizar telefones?](INTEGRACAO_OUTRO_PROJETO.md#função-1-normalizesmsphone)
- [Como enviar SMS via Infobip?](INTEGRACAO_OUTRO_PROJETO.md#função-2-sendinfobipsms)
- [Como criar endpoint customizado?](INTEGRACAO_OUTRO_PROJETO.md#exemplos-de-implementação)

### Banco de Dados
- [Schema da tabela sms_history](README.md#banco-de-dados)
- [Como consultar histórico?](INTEGRACAO_OUTRO_PROJETO.md#problema-4-sms-enviados-mas-statusfailed)

### Troubleshooting
- [SMS não está sendo enviado](INTEGRACAO_OUTRO_PROJETO.md#troubleshooting)
- [Erro de permissão](INTEGRACAO_OUTRO_PROJETO.md#problema-5-permissão-negada-ao-escrever-log)
- [Telefone inválido](INTEGRACAO_OUTRO_PROJETO.md#problema-2-nenhum-telefone-válido-para-envio)

---

## 🔑 Componentes Principais

### **normalizeSmsPhone()**
Converte telefone para formato internacional
```php
'937493326' → '351937493326'  // Portugal
'351937493326' → '351937493326'  // Já normalizado
'123' → null  // Inválido
```

### **sendInfobipSms()**
Envia SMS via Infobip
```php
$result = sendInfobipSms($employees, $message, $config);
// Retorna: sent_count, failed_count, skipped_count, details
```

### **notify_employees.php**
Endpoint POST que tudo coordena
```
POST /api/employees/notify_employees.php
Content: ids=[1,2,3]&message=Olá!
Resposta: JSON com resumo de envio
```

---

## 📊 Fluxo Visual

```
Admin Painel
    ↓
JavaScript POST
    ↓
Validação Sessão
    ↓
Busca BD (employees)
    ↓
Insere Notificação Interna
    ↓
Normaliza Telefones
    ↓
Envia Infobip (cURL)
    ↓
Registra sms_history
    ↓
Retorna JSON
    ↓
Admin Vê Toast
```

---

## ✅ Checklist de Uso

### Primeiro Uso
- [ ] Li o QUICK_START.md
- [ ] Copiei sms_config.local.php
- [ ] Preenchi api_key do Infobip
- [ ] Cliquei em "Enviar SMS" no painel

### Novo Projeto
- [ ] Li INTEGRACAO_OUTRO_PROJETO.md
- [ ] Segui 6 passos de integração
- [ ] Executei script de teste
- [ ] Enviei primeiro SMS com sucesso
- [ ] Consultei sms_history no BD

### Em Produção
- [ ] Credenciais estão em .gitignore
- [ ] Tabela sms_history foi criada
- [ ] Telefones dos funcionários estão preenchidos
- [ ] HTTPS está ativo (Infobip exige)
- [ ] Monitorei histórico de erros
- [ ] Testei com 1-2 SMS antes de lote

---

## 🎯 Casos de Uso

| Caso | Guia |
|------|------|
| Ativar SMS em 5 min | [QUICK_START.md](QUICK_START.md) |
| Integrar em novo projeto | [INTEGRACAO_OUTRO_PROJETO.md](INTEGRACAO_OUTRO_PROJETO.md) |
| Entender como funciona | [INTEGRACAO_OUTRO_PROJETO.md seção 1-2](INTEGRACAO_OUTRO_PROJETO.md) |
| Enviar SMS customizado | [INTEGRACAO_OUTRO_PROJETO.md seção 4](INTEGRACAO_OUTRO_PROJETO.md#exemplos-de-implementação) |
| Debugar erro | [INTEGRACAO_OUTRO_PROJETO.md seção 5](INTEGRACAO_OUTRO_PROJETO.md#troubleshooting) |
| Consultar histórico | [README.md](README.md#banco-de-dados) |

---

## 📞 Suporte

### Erro no envio?
👉 [INTEGRACAO_OUTRO_PROJETO.md - Troubleshooting](INTEGRACAO_OUTRO_PROJETO.md#troubleshooting)

### Não consegue integrar?
👉 [INTEGRACAO_OUTRO_PROJETO.md - Passo-a-Passo](INTEGRACAO_OUTRO_PROJETO.md#integração-passo-a-passo)

### Quer exemplo de código?
👉 [INTEGRACAO_OUTRO_PROJETO.md - Exemplos](INTEGRACAO_OUTRO_PROJETO.md#exemplos-de-implementação)

---

## 🔐 Segurança

✅ Credenciais em arquivo local (não versionado)  
✅ Validação de sessão obrigatória  
✅ Verificação de permissão por client_id  
✅ HTTPS com Infobip  
✅ Sanitização de entrada  

[Mais detalhes →](README.md#segurança)

---

## 📈 Performance

- Envio em lote: até 1000 SMS por vez
- Timeout: 15 segundos (configurável)
- Multi-tenant: suporta múltiplos clientes
- Indexação: sms_history otimizada para queries

---

## 🚀 Próximos Passos (Opcional)

- [ ] Criar página de visualização de histórico SMS
- [ ] Integrar webhooks de entrega do Infobip
- [ ] Adicionar templates de SMS reutilizáveis
- [ ] Implementar confirmação de leitura
- [ ] Criar painel de estatísticas de SMS

---

## 📅 Versão

**v1.0** - 28/04/2026
- ✅ Normalização de telefones
- ✅ Integração Infobip
- ✅ Histórico em BD
- ✅ Validação multi-tenant
- ✅ Documentação completa

---

## 📝 Licença

Este módulo é parte do projeto app-rhnetopro.

---

**Pronto para usar?** 🎉

Escolha seu guia acima e comece agora!
