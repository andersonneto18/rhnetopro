# Estrutura do SMS Module

## Resumo de Arquivos

```
sms_module/
│
├── 📂 config/
│   ├── sms_config.php              # [PRINCIPAL] Carrega config do Infobip
│   └── sms_config.local.example.php # [EXEMPLO] Template com sua chave de API
│
├── 📂 includes/
│   └── sms_sender.php              # [PRINCIPAL] Funções de envio e normalização
│
├── 📂 api/
│   └── notify_employees.php        # [PRINCIPAL] Endpoint POST de envio
│
└── 📂 docs/
    ├── README.md                   # Documentação completa
    ├── QUICK_START.md              # Este arquivo - guia rápido
    └── STRUCTURE.md                # Arquitetura e fluxos
```

## Referência de Funções

### normalizeSmsPhone()
```php
require_once 'includes/sms_sender.php';

$phone = "937493326";
$normalized = normalizeSmsPhone($phone);
// Resultado: "351937493326"
```

### sendInfobipSms()
```php
require_once 'includes/sms_sender.php';

$employees = [
    ['id' => 1, 'name' => 'João', 'phone' => '937493326'],
    ['id' => 2, 'name' => 'Maria', 'phone' => '931234567'],
];

$config = require 'config/sms_config.php';
$result = sendInfobipSms($employees, "Olá!", $config);

// $result['sent_count'] = 2
// $result['failed_count'] = 0
// $result['details'] = array com status individual
```

### Endpoint notify_employees.php
```bash
curl -X POST http://localhost/api/employees/notify_employees.php \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "ids=[1,2,3]&message=Teste%20de%20SMS" \
  --cookie "PHPSESSID=..."
```

## Integração no Projeto

Os arquivos são **linkados** nos locais originais:

| Arquivo Original | Usar Pasta | Descrição |
|-----------------|-----------|----------|
| `/config/sms_config.php` | sms_module/config | Configuração principal |
| `/includes/sms_sender.php` | sms_module/includes | Funções de envio |
| `/api/employees/notify_employees.php` | sms_module/api | Endpoint |

**Importante:** A pasta `sms_module` é uma **cópia documentada**. Os arquivos reais estão na raiz do projeto, mas copiei para esta pasta para organizar e documentar melhor.

## Checklist de Configuração

- [ ] Criar `config/sms_config.local.php` a partir do exemplo
- [ ] Preencher `api_key` do Infobip
- [ ] Preencher `sender` (número ou ID do Infobip)
- [ ] Testar com 1 funcionário que tenha telefone
- [ ] Verificar resposta do endpoint
- [ ] Consultar tabela `sms_history` para confirmar envio
- [ ] Colocar em produção

## Troubleshooting

| Problema | Solução |
|----------|---------|
| "SMS real não configurado" | Verificar `sms_config.local.php` existe e está preenchida |
| "Nenhum telefone válido" | Confirmar que funcionário tem `phone` preenchido com 9+ dígitos |
| "Falha ao comunicar" | Verificar conectividade com Infobip, api_key válida |
| "0 enviado(s)" | Verificar se Infobip respondeu com erro (veja `error_message`) |

## Banco de Dados (Auto-criado)

```sql
sms_history(
  id,
  client_id,
  employee_id,
  phone,
  message,
  provider,
  provider_message_id,
  status,
  error_message,
  sent_at
)
```

Consultar:
```sql
SELECT * FROM sms_history ORDER BY sent_at DESC LIMIT 20;
```

## Performance

- Envio em lote: até 1000 SMS por vez
- Timeout: 15 segundos (configurável)
- Suporta múltiplos clientes simultaneamente (multi-tenant)

## Segurança

✅ Sessão obrigatória  
✅ Validação de client_id  
✅ Credenciais fora do Git  
✅ HTTPS com Infobip  
✅ Sanitização de entrada  

---

**Pronto para usar!** 🚀
