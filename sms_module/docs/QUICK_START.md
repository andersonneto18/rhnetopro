# Integração Rápida - SMS Module

## Passo 1: Preparar a configuração

O módulo SMS já está integrado no projeto principal. Para ativar o envio real de SMS:

1. **Copiar o arquivo de configuração local:**
```bash
cd sms_module/config
cp sms_config.local.example.php sms_config.local.php
```

2. **Editar com suas credenciais do Infobip:**
```php
return [
    'infobip' => [
        'enabled' => true,
        'base_url' => 'https://x19gll.api.infobip.com',
        'api_key' => 'SUA_API_KEY_AQUI', // Cole sua chave
        'sender' => 'SEU_SENDER_ID_AQUI',    // Seu sender ID
        'timeout_seconds' => 15,
    ],
];
```

## Passo 2: Verificar permissões de arquivo

```bash
# Linux/Mac
chmod 600 sms_module/config/sms_config.local.php

# Windows (via PowerShell como Admin)
icacls "sms_module\config\sms_config.local.php" /inheritance:r /grant:r "%USERNAME%:F"
```

## Passo 3: Testar no painel admin

1. Ir para **Admin → Funcionários**
2. Selecionar funcionários com telefone preenchido
3. Clicar em **"Enviar SMS"**
4. Escrever mensagem e enviar
5. Verificar resposta com status de envio

## Arquivo de Histórico

O módulo cria automaticamente a tabela `sms_history` na primeira execução.

Consultar histórico de SMS:
```sql
SELECT 
    e.name AS funcionario,
    s.phone,
    s.message,
    s.status,
    s.error_message,
    s.sent_at
FROM sms_history s
INNER JOIN employees e ON e.id = s.employee_id
ORDER BY s.sent_at DESC
LIMIT 50;
```

## Resposta do Endpoint

Após enviar SMS, o painel mostra:
- ✅ Quantos SMS foram entregues
- ⚠️ Quantos falharam
- ⏭️ Quantos foram ignorados (telefone inválido)

Exemplo:
```
✓ Notificações internas enviadas para 5 funcionário(s). 
SMS real: 5 enviado(s), 0 falha(s), 0 ignorado(s).
```

## Fluxo de Dados

```
Admin Seleciona Funcionários
        ↓
Frontend: POST /api/employees/notify_employees.php
        ↓
Backend:
  ├─ Valida Sessão & Permissões
  ├─ Busca: id, name, phone do funcionário
  ├─ Insere: Notificação interna (app vê no portal)
  ├─ Para cada funcionário:
  │   ├─ Normaliza telefone (ex: 937493326 → 351937493326)
  │   ├─ Envia via Infobip (curl POST)
  │   └─ Registra resultado em sms_history
  └─ Retorna resumo (sent, failed, skipped)
        ↓
Frontend: Mostra feedback ao admin
```

## Normalização de Telefones

O módulo normaliza automaticamente:

| Entrada | Saída |
|---------|-------|
| 937493326 | 351937493326 |
| +351937493326 | 351937493326 |
| 0051937493326 | 351937493326 |
| 351937493326 | 351937493326 |
| 123 | null (inválido) |

## Localização de Arquivos no Projeto

```
app-rhnetopro/
├── config/
│   ├── sms_config.php              ← Carregamento
│   └── sms_config.local.php        ← Credenciais (não versionado)
├── includes/
│   └── sms_sender.php              ← Funções de envio
├── api/employees/
│   └── notify_employees.php        ← Endpoint principal
└── sms_module/                     ← CÓPIA DOCUMENTADA (esta pasta)
    ├── config/
    ├── includes/
    ├── api/
    └── docs/
```

## Variáveis de Ambiente (Alternativa)

Se preferir usar variáveis de ambiente em vez de arquivo local:

```bash
export INFOBIP_ENABLED=true
export INFOBIP_BASE_URL=https://x19gll.api.infobip.com
export INFOBIP_API_KEY=sua_api_key
export INFOBIP_SENDER=seu_sender_id
```

## Status de SMS

A tabela `sms_history` registra:

| Status | Significado |
|--------|------------|
| sent | SMS entregue ao provedor |
| failed | SMS falhou (veja error_message) |
| skipped | Funcionário ignorado (sem telefone válido) |
| pending | SMS em processamento |

## Segurança

- ✅ Credenciais não estão no Git (arquivo local no .gitignore)
- ✅ Validação de sessão obrigatória
- ✅ Verificação de permissão por client_id
- ✅ HTTPS com Infobip
- ✅ Tratamento de erro seguro

## Debug

Para ver logs de erro:

```bash
# Linux/Mac
tail -f /var/log/php-fpm.log

# Windows
# Verificar php.ini para error_log path
```

No arquivo `notify_employees.php`, erros são logados:
```php
error_log('notify_employees.php sms_history erro: ' . $historyError->getMessage());
```

## Próximos Passos (Opcional)

- [ ] Adicionar visualização de histórico de SMS no admin
- [ ] Criar página de relatório de entrega
- [ ] Integrar com webhooks do Infobip para atualizações em tempo real
- [ ] Adicionar confirmação de leitura do SMS
- [ ] Criar templates de SMS reutilizáveis

---

**Módulo criado em:** 28/04/2026
**Provider:** Infobip
**Status:** Pronto para produção
