# 📍 Verificação de Localização ao Marcar Presença

Documentação técnica de como a app confirma se um funcionário está fisicamente
no estabelecimento quando marca entrada/saída, e as tecnologias usadas para
isso.

## Objetivo

O administrador configura, uma única vez, a localização GPS do restaurante.
A partir daí, sempre que um funcionário marca ponto (entrada ou saída), o
navegador tenta captar a localização atual e o servidor calcula a distância
até ao estabelecimento, guardando o resultado para o admin rever em
Presença.

**Regra de ouro do design:** nunca bloquear a marcação de ponto por causa da
localização. GPS de telemóvel é impreciso (sobretudo em interiores), e o
funcionário pode recusar a permissão — em ambos os casos a marcação continua
a funcionar normalmente, só sem o selo de confirmação. A localização é um
**sinal de apoio à decisão do admin**, não um mecanismo de bloqueio.

## Tecnologias usadas

### 1. Geolocation API do navegador (`navigator.geolocation`)

É uma API nativa dos browsers (não é uma biblioteca externa, não precisa de
instalar nada) que permite obter a posição GPS do dispositivo do utilizador,
mediante autorização explícita dele.

```javascript
navigator.geolocation.getCurrentPosition(
    (posicao) => { /* sucesso */ },
    (erro)    => { /* falhou ou foi recusado */ },
    { enableHighAccuracy: true, timeout: 5000, maximumAge: 30000 }
);
```

- **`getCurrentPosition(sucesso, erro, opções)`** — pede a posição atual uma
  única vez (é assíncrono, por isso é sempre usado com callbacks ou, como
  fizemos aqui, envolvido numa `Promise`).
- **1º argumento (`sucesso`)** — função chamada quando a posição é obtida.
  Recebe um objeto `GeolocationPosition` com `position.coords.latitude` e
  `position.coords.longitude` (entre outros: `accuracy`, `altitude`, etc.).
- **2º argumento (`erro`)** — função chamada se o utilizador recusar a
  permissão, o dispositivo não tiver GPS, ou o pedido demorar demasiado.
- **3º argumento (`opções`)**:
  - `enableHighAccuracy: true` — pede ao dispositivo para usar o GPS real em
    vez de uma estimativa mais grosseira baseada em rede/Wi-Fi (mais lento,
    mas mais preciso).
  - `timeout: 5000` — desiste ao fim de 5 segundos se não conseguir obter
    uma posição, para não travar a marcação de ponto à espera do GPS.
  - `maximumAge: 30000` — aceita uma posição em cache de até 30 segundos, em
    vez de forçar sempre uma leitura nova (mais rápido).

**Importante:** esta API só funciona em contexto seguro (HTTPS), exceto em
`localhost` (onde os browsers abrem uma exceção para desenvolvimento). Em
produção (`https://rhnetopro.com`) funciona sem problema porque o site já
usa HTTPS.

**Permissões:** da primeira vez que uma página pede localização, o browser
mostra um popup a perguntar a autorização ao utilizador. Essa escolha fica
memorizada por origem (domínio) — o funcionário só é perguntado novamente se
limpar as permissões do site ou mudar de navegador/dispositivo.

### 2. Fórmula de Haversine (cálculo de distância)

Para saber "a que distância está o funcionário do restaurante", não basta
subtrair coordenadas diretamente — a Terra é uma esfera, não um plano. A
fórmula de Haversine calcula a distância entre dois pontos (latitude,
longitude) sobre a superfície de uma esfera:

```php
function _distanciaMetros(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $raioTerraMetros = 6371000; // raio médio da Terra
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $raioTerraMetros * $c;
}
```

Em português simples: converte as diferenças de latitude/longitude para
radianos, calcula o "ângulo" entre os dois pontos vistos do centro da Terra
(`$a` e `$c`), e multiplica esse ângulo pelo raio da Terra para obter a
distância em metros. É a fórmula padrão da indústria para este tipo de
cálculo (a mesma usada, por exemplo, pelo Google Maps para distâncias em
linha reta) e é suficientemente precisa para a escala de metros que aqui
interessa (não confundir com a distância "de estrada", que seria maior).

Esta função vive em `app/registrar_ponto_session.php` porque é o único
sítio onde a distância é efetivamente calculada — corre no **servidor**, não
no browser, para não poder ser falsificada só por alterar o JavaScript.

### 3. Base de dados — colunas adicionadas (MySQL/PDO)

Duas tabelas ganharam colunas novas, criadas de forma **condicional** (só se
ainda não existirem), seguindo o mesmo padrão já usado neste projeto para
todas as migrações leves — em vez de um sistema de migrações formal, o
próprio `admin/dashboard.php` verifica e cria as colunas em cada carregamento
da página:

```php
if (!$pdo->query("SHOW COLUMNS FROM estabelecimento_horarios LIKE 'latitude'")->fetch()) {
    $pdo->exec("ALTER TABLE estabelecimento_horarios ADD COLUMN latitude DECIMAL(10,7) NULL AFTER tolerancia_atraso_min");
}
```

- **`estabelecimento_horarios`** (1 linha por cliente/restaurante):
  `latitude`, `longitude` (`DECIMAL(10,7)` — 7 casas decimais dão precisão ao
  nível do metro), `raio_metros` (`INT`, o raio permitido).
- **`registros_ponto`** (1 linha por marcação de ponto): `ponto_latitude`,
  `ponto_longitude` (onde o funcionário estava), `distancia_metros`
  (resultado do Haversine), `localizacao_status` (`'dentro'`, `'fora'` ou
  `'sem_dados'`).

## Arquitetura do fluxo

```
┌─────────────────────┐         ┌──────────────────────┐
│   ADMIN configura    │         │  FUNCIONÁRIO marca    │
│  (Definições)         │         │  presença (Portal)    │
│                       │         │                       │
│ 1. Abre o modal de    │         │ 1. Clica em Entrada/  │
│    Horários, estando  │         │    Saída               │
│    no restaurante     │         │ 2. navigator.geoloc-   │
│ 2. Clica "Usar a      │         │    ation.getCurrent-   │
│    minha localização" │         │    Position() tenta     │
│ 3. navigator.geoloc-  │         │    captar lat/lng       │
│    ation captura      │         │ 3. Envia lat/lng (ou    │
│    lat/lng do PRÓPRIO │         │    null) junto com o    │
│    browser do admin   │         │    pedido de ponto      │
│ 4. Guarda em          │         └──────────┬────────────┘
│    estabelecimento_   │                    │
│    horarios           │                    ▼
└──────────┬────────────┘         ┌──────────────────────┐
           │                      │  SERVIDOR calcula      │
           │  (lê)                │  (registrar_ponto_     │
           └─────────────────────▶│  session.php)          │
                                  │                        │
                                  │ 1. Lê a localização do  │
                                  │    estabelecimento      │
                                  │ 2. Calcula distância    │
                                  │    (Haversine)          │
                                  │ 3. Compara com o raio   │
                                  │    permitido             │
                                  │ 4. Grava dentro/fora/   │
                                  │    sem_dados             │
                                  └──────────┬─────────────┘
                                             │
                                             ▼
                                  ┌──────────────────────┐
                                  │  ADMIN vê o resultado  │
                                  │  (Presença)            │
                                  │  📍 No local (verde)   │
                                  │  📍 Fora do local      │
                                  │     (âmbar, com        │
                                  │     distância em m)     │
                                  └──────────────────────┘
```

## Explicação ficheiro a ficheiro

### `admin/sections/definicoes.php` — botão "Usar a minha localização atual"

Dentro do modal de Horários, um botão dispara a Geolocation API e preenche
dois campos de formulário normais (`<input type="number">`) com o resultado:

```javascript
navigator.geolocation.getCurrentPosition(function(pos) {
    inpLat.value = pos.coords.latitude.toFixed(7);
    inpLng.value = pos.coords.longitude.toFixed(7);
    // ...feedback visual de sucesso...
}, function() {
    // ...feedback visual de erro (permissão recusada, etc.)...
}, { enableHighAccuracy: true, timeout: 10000 });
```

Os valores ficam só nos campos do formulário até o admin clicar em "Guardar
Horários" — nesse momento é um `<form method="post">` normal, sem AJAX, que
envia tudo para `admin/dashboard.php` como qualquer outro formulário desta
app.

### `admin/dashboard.php` — guardar a localização

O handler que já existia para guardar os horários do estabelecimento
(`action=save_estabelecimento_horarios`) foi estendido para também validar e
guardar `latitude`, `longitude` e `raio_metros`:

```php
$latitude = ($latitudeRaw !== '' && is_numeric($latitudeRaw) && (float)$latitudeRaw >= -90 && (float)$latitudeRaw <= 90)
    ? round((float)$latitudeRaw, 7) : null;
```

Válida os limites geográficos reais (latitude entre -90 e 90, longitude
entre -180 e 180) — se o valor for inválido ou estiver vazio, grava `NULL`
em vez de rejeitar todo o formulário, porque a localização é **opcional**
(o admin pode continuar a só configurar os horários, sem localização).

### `app/portal.js` — capturar a localização do funcionário

A função que já tratava de enviar o pedido de marcação de ponto
(`_executarRegistoPonto`) ganhou um passo prévio:

```javascript
function _obterLocalizacaoAtual() {
    return new Promise((resolve) => {
        if (!navigator.geolocation) { resolve(null); return; }
        navigator.geolocation.getCurrentPosition(
            (pos) => resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude }),
            () => resolve(null),
            { enableHighAccuracy: true, timeout: 5000, maximumAge: 30000 }
        );
    });
}
```

O detalhe mais importante desta função: **nunca rejeita a Promise**. Tanto o
callback de sucesso como o de erro terminam em `resolve(...)` — um resolve
com as coordenadas, o outro resolve com `null`. Isto garante que o código
que chama esta função (`await _obterLocalizacaoAtual()`) nunca cai num
`catch`/erro só por causa da localização — o pior que acontece é receber
`null` e seguir em frente sem dados de posição, exatamente a "regra de ouro"
descrita no início deste documento.

### `app/registrar_ponto_session.php` — decidir dentro/fora/sem dados

```php
if ($pontoLat !== null && $pontoLng !== null) {
    $stmtEst = $pdo->prepare('SELECT latitude, longitude, raio_metros FROM estabelecimento_horarios WHERE client_id = ? LIMIT 1');
    // ...
    if ($estRow && $estRow['latitude'] !== null && $estRow['longitude'] !== null) {
        $distanciaMetros = (int)round(_distanciaMetros(...));
        $localizacaoStatus = $distanciaMetros <= $raioPermitido ? 'dentro' : 'fora';
    }
}
```

A lógica só calcula e compara se **ambas as pontas** tiverem dados: o
funcionário autorizou a localização E o admin já configurou a localização
do estabelecimento. Se faltar qualquer uma das duas, `$localizacaoStatus`
mantém o valor por omissão `'sem_dados'` e a marcação de ponto segue o fluxo
normal (a validação de turno/horário existente não é afetada em nada).

### `admin/sections/assiduidade.php` — mostrar o resultado

Um pequeno badge condicional ao lado do estado de presença:

```php
<?php if ($locStatus === 'dentro'): ?>
<span class="fr-presence" style="background:rgba(16,185,129,.1); color:#10b981;">
    <i class="fas fa-location-dot"></i> No local
</span>
<?php elseif ($locStatus === 'fora'): ?>
<span class="fr-presence" style="background:rgba(245,158,11,.12); color:#f59e0b;">
    <i class="fas fa-location-dot"></i> Fora do local (<?php echo $distMetros; ?>m)
</span>
<?php endif; ?>
```

Quando `$locStatus === 'sem_dados'` (ou está vazio, para registos antigos
anteriores a esta funcionalidade), não aparece badge nenhum — não faz
sentido "avisar" sobre a ausência de dados numa interface já cheia de
informação.

## Segurança e limitações (importante ler antes de confiar cegamente nisto)

- **Não é à prova de fraude.** Um funcionário com conhecimentos técnicos
  pode falsificar a localização do dispositivo (apps de "GPS falso",
  emuladores, ferramentas de developer no browser). Isto é uma limitação de
  qualquer verificação de localização feita do lado do cliente — não há
  forma 100% segura de o evitar sem hardware dedicado (ex: leitor biométrico
  fixo no local).
- **Precisão do GPS varia.** Em espaços fechados ou zonas densas, o GPS pode
  errar por dezenas ou mesmo centenas de metros. Por isso o raio é
  configurável, e por isso a marcação nunca é bloqueada.
- **É um sinal, não uma prova.** Trate o badge "Fora do local" como um
  convite a perguntar ao funcionário o que se passou, não como prova
  definitiva de fraude.

## Como testar

1. Nas Definições do admin, abrir "Configurar Horários" e usar "Usar a
   minha localização atual" (ou inserir coordenadas manualmente).
2. No portal do funcionário, marcar entrada/saída — o browser deve pedir
   permissão de localização na primeira vez.
3. Em Presença (admin), o registo deve mostrar o badge correspondente.
4. Para testar "fora do local" sem sair de casa: no Chrome DevTools, aba
   **Sensors** (menu `⋮` → More tools → Sensors), pode simular uma
   localização GPS diferente da real antes de marcar o ponto.
