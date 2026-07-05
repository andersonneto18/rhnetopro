# SMS Module - Envio de SMS em Tempo Real

Esta pasta contém todos os arquivos e APIs necessárias para enviar SMS em tempo real para o número de telefone dos funcionários usando o provedor **Infobip**.

## Estrutura

```
sms_module/
├── config/
│   ├── sms_config.php              # Configuração principal (carrega local se existir)
│   └── sms_config.local.example.php # Exemplo de configuração local com credenciais
├── includes/
│   └── sms_sender.php              # Funções de normalização e envio SMS
├── api/
│   └── notify_employees.php        # Endpoint que envia notificação interna + SMS real
└── docs/
    └── README.md                   # Este arquivo
```

## Arquivos Principais

### 1. config/sms_config.php
Carrega configuração do Infobip:
- Suporta variáveis de ambiente (`INFOBIP_*`)
- Faz override com arquivo local se existir
- Não contém credenciais sensíveis por padrão

### 2. includes/sms_sender.php
Contém 2 funções principais:

#### `normalizeSmsPhone(string $phone): ?string`
- Normaliza número de telefone para formato internacional
- Se telefone tem 9 dígitos: adiciona prefixo 351 (Portugal)
- Se 11-15 dígitos: mantém como está
- Retorna `null` se inválido

#### `sendInfobipSms(array $employees, string $message, array $config): array`
- Envia SMS para múltiplos funcionários
- Retorna array com resultado detalhado:
  - `configured`: se provedor está configurado
  - `sent_count`: quantos foram enviados com sucesso
  - `failed_count`: quantos falharam
  - `skipped_count`: quantos foram ignorados (telefone inválido)
  - `details`: array com status individual de cada funcionário
  - `error`: mensagem de erro geral (se houver)

### 3. api/notify_employees.php
Endpoint POST que:
- Recebe `ids` (array de employee_id) e `message` (string)
- Recebe `delivery_mode` (`app`, `phone` ou `both`)
- Valida todos os IDs contra o cliente da sessão
- Envia notificação interna (tabela `notificacoes`) quando `delivery_mode` inclui `app`
- Envia SMS real (via Infobip) quando `delivery_mode` inclui `phone`
- Registra histórico em `sms_history`
- Retorna resumo detalhado do envio

## Configuração

### Passo 1: Copiar arquivo de configuração
```bash
cp config/sms_config.local.example.php config/sms_config.local.php
```

### Passo 2: Editar credenciais em config/sms_config.local.php
```php
return [
    'infobip' => [
        'enabled' => true,
        'base_url' => 'https://x19gll.api.infobip.com',
        'api_key' => 'SUA_API_KEY_AQUI',
        'sender' => 'SEU_SENDER_ID_AQUI',
        'timeout_seconds' => 15,
    ],
];
```

### Alternativa: Usar variáveis de ambiente
```bash
export INFOBIP_ENABLED=true
export INFOBIP_BASE_URL=https://x19gll.api.infobip.com
export INFOBIP_API_KEY=sua_api_key
export INFOBIP_SENDER=seu_sender_id
export INFOBIP_TIMEOUT=15
```

## Uso

### Exemplo JavaScript (Frontend)
```javascript
const formData = new FormData();
formData.append('ids', JSON.stringify([123, 456, 789])); // employee IDs
formData.append('message', 'Olá, mensagem importante!');
formData.append('delivery_mode', 'both'); // app | phone | both

const response = await fetch('/api/employees/notify_employees.php', {
    method: 'POST',
    body: formData,
    credentials: 'same-origin'
});

const data = await response.json();
console.log(data.message);
console.log(data.sms); // detalhes do envio SMS
```

### Modos de Envio
- `app`: envia apenas para o app do funcionário
- `phone`: envia apenas SMS para o número de telefone
- `both`: envia para app e telefone

### Resposta do Endpoint
```json
{
    "success": true,
    "message": "Notificações internas enviadas para 3 funcionário(s). SMS real: 3 enviado(s), 0 falha(s), 0 ignorado(s).",
    "sms": {
        "configured": true,
        "sent_count": 3,
        "failed_count": 0,
        "skipped_count": 0,
        "provider": "infobip",
        "details": [
            {
                "employee_id": 123,
                "employee_name": "João Silva",
                "phone": "351937493326",
                "status": "sent",
                "provider_message_id": "msg123",
                "provider_status": "SENT"
            }
        ],
        "error": null
    }
}
```

## Banco de Dados

Tabela criada automaticamente: `sms_history`

```sql
CREATE TABLE IF NOT EXISTS sms_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    employee_id INT NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    message TEXT NOT NULL,
    provider VARCHAR(50) DEFAULT NULL,
    provider_message_id VARCHAR(120) DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    error_message TEXT DEFAULT NULL,
    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sms_history_client_employee (client_id, employee_id),
    KEY idx_sms_history_status (status)
);
```

## Segurança

1. **Credenciais**: O arquivo `config/sms_config.local.php` está no `.gitignore` e não será versionado
2. **Autenticação**: O endpoint valida sessão (`user_id` e `client_id`)
3. **Autorização**: Verifica se employee_id pertence ao client_id da sessão
4. **Dados em trânsito**: Usa HTTPS com Infobip

## Tratamento de Erros

- Telefone ausente/inválido → Status: `skipped`
- Falha na conexão com Infobip → Status: `failed` + error_message
- SMS rejeitado pelo provedor → Status: `failed` + provider_status

## Fluxo Completo

1. Admin seleciona funcionários no painel
2. Clica em "Enviar SMS"
3. JavaScript coleta IDs e mensagem
4. POST para `/api/employees/notify_employees.php`
5. Backend:
   - Valida permissões
   - Insere notificação interna (app vê no portal)
   - Busca números de telefone
   - Normaliza telefones
   - Envia para Infobip
   - Registra resultado em sms_history
6. Resposta com resumo entregue ao admin

## Integração com Projeto Principal

Os arquivos usados no projeto principal:
- [api/employees/notify_employees.php](../api/employees/notify_employees.php) — endpoint principal
- [includes/sms_sender.php](../includes/sms_sender.php) — funções de envio
- [config/sms_config.php](../config/sms_config.php) — carregamento de config
- [config/sms_config.local.php](../config/sms_config.local.php) — credenciais locais (não versionado)

## Troubleshooting

### "SMS real não configurado"
- Verificar se `config/sms_config.local.php` existe
- Verificar se `INFOBIP_ENABLED` está `true`
- Verificar se api_key, base_url e sender estão preenchidos

### "Nenhum telefone válido para envio"
- Verificar se funcionários têm campo `phone` preenchido
- Validar formato de telefone (9 ou 11-15 dígitos)

### "Falha ao comunicar com Infobip"
- Verificar conectividade com `https://x19gll.api.infobip.com`
- Verificar se API key está válida
- Consultar logs do PHP para mais detalhes

## Suporte

Para mais informações sobre a API Infobip:
- [Documentação Oficial](https://www.infobip.com/docs/sms/send-sms-message)
