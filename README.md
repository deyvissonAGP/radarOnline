# 📡 Radar de vagas tech

Painel pessoal que reúne vagas de tecnologia de várias fontes, descarta o que não é vaga, junta as duplicadas e avisa as novidades no Telegram.

A cada 3 horas, o Radar busca vagas na Gupy, em repositórios de vagas do GitHub, na Himalayas, na Remotar e na Programathor. Ele classifica cada vaga por área, nível, modelo de trabalho, contrato, tecnologias e salário, e mostra tudo numa página com busca e filtros. Só contam vagas publicadas nas últimas **72 horas** (configurável).

**Stack:** PHP 8.3 · Laravel 12 · PostgreSQL 16 · Docker · FrankenPHP (produção)

---

## Sumário

1. [Funcionalidades](#funcionalidades)
2. [Como funciona](#como-funciona)
3. [Rodando localmente](#rodando-localmente)
4. [Produção](#produção)
5. [Uso no dia a dia](#uso-no-dia-a-dia)
6. [Telegram](#telegram)
7. [Fontes de vagas](#fontes-de-vagas)
8. [Filtro e classificação](#filtro-e-classificação)
9. [Configuração (.env)](#configuração-env)
10. [Comandos](#comandos)
11. [Testes](#testes)
12. [Estrutura do projeto](#estrutura-do-projeto)
13. [Adicionar uma fonte nova](#adicionar-uma-fonte-nova)
14. [Solução de problemas](#solução-de-problemas)

---

## Funcionalidades

| Página | O que tem |
|---|---|
| **Vagas** (`/`) | Contadores, situação de cada fonte e lista de vagas com busca (título, empresa e descrição, ignorando acentos) e filtros por área, modelo, tecnologia, nível, fonte e salário mínimo. Em cada vaga, botões **Candidatei** e **Descartar**, com abas próprias |
| **Resumo** (`/resumo`) | Texto pronto com os números do dia, com botão de copiar |
| **Regras** (`/regras`) | As regras do filtro, os itens descartados recentemente e o histórico de coletas |
| **Rotinas** (`/rotinas`) | Botões para coletar, reaplicar regras, limpar e usar o Telegram, com histórico e saída de cada execução |
| **Entrar** (`/entrar`) | Login com usuário e senha, com opção de manter conectado por 30 dias |

Automático, sem precisar entrar no servidor:

- **Coleta a cada 3 horas**, seguida do envio das vagas novas no Telegram.
- **Resumo diário às 8h** (horário de Brasília) no Telegram.
- **Aviso no Telegram quando uma fonte quebra**: erro ou zero vagas em 2 coletas seguidas.
- **Limpeza** de vagas com mais de 30 dias. As marcadas como "Candidatei" ficam.
- **Backup diário** do banco, guardado por 14 dias (só em produção).

---

## Como funciona

### Fluxo de uma vaga

```
fonte ──► coletor ──► JobData ──► filtro ──► classificador ──► banco ──► página · resumo · Telegram
          (parse)   (formato      (é vaga de   (área, nível,     │
                     comum)        tecnologia?) modelo, salário)  └─ duplicadas viram uma só
```

1. **Coletor:** cada fonte tem uma classe que lê o formato dela (API, JSON embutido ou HTML) e devolve um `JobData`, o formato comum a todas.
2. **Janela de tempo:** vagas publicadas há mais de 72 horas são ignoradas.
3. **Filtro:** decide se é vaga de tecnologia pelo título e descarta cursos, grupos de vagas e propaganda de currículo. As regras são públicas, em `/regras`.
4. **Classificador:** identifica área, nível, modelo de trabalho, CLT/PJ, tecnologias e faixa salarial.
5. **Duplicadas:** a mesma vaga em fontes diferentes (mesmo cargo e mesma empresa) conta uma vez só.
6. **Saída:** página, resumo e Telegram leem do banco.

### Processos (serviços Docker)

| Serviço | Papel |
|---|---|
| `app` | Site (Laravel). Em produção, roda em FrankenPHP |
| `scheduler` | Agendador: dispara a coleta a cada 3h e o resumo às 8h (`routes/console.php`) |
| `worker` | Fila: executa as rotinas pedidas pelos botões da página Rotinas |
| `db` | PostgreSQL |
| `backup` | `pg_dump` diário em `./backups` (só em produção) |

Os botões da página Rotinas **não** rodam a tarefa dentro da requisição. A coleta leva 1 a 2 minutos e seria cortada pelo limite de tempo do PHP e da Cloudflare. Por isso o botão coloca a tarefa na fila (tabela `jobs`), o `worker` executa, e a página acompanha o andamento. Execuções manuais e automáticas ficam no mesmo histórico (tabela `execucoes`).

---

## Rodando localmente

**Pré-requisito:** Docker (no Windows ou Mac, o Docker Desktop).

```bash
git clone git@github.com:deyvissonAGP/radarOnline.git
cd radarOnline

# 1. Configuração local
cp .env.example .env

# 2. Instala as dependências, gera a APP_KEY e cria as tabelas
docker compose run --rm app sh docker/instalar.sh

# 3. Sobe site, banco, agendador e worker
docker compose up -d

# 4. Primeira coleta (ou pelo botão em /rotinas)
docker compose exec app php artisan vagas:coletar
```

Abra **http://localhost:8081**. Sem `RADAR_SENHA` no `.env`, o site local abre sem login.

| Serviço local | Endereço |
|---|---|
| Site | http://localhost:8081 |
| PostgreSQL | `localhost:5433` · banco, usuário e senha `radar` |

---

## Produção

O passo a passo completo está em **[PRODUCAO.md](PRODUCAO.md)**. Em resumo:

```bash
git clone git@github.com:deyvissonAGP/radarOnline.git && cd radarOnline
cp .env.producao.example .env      # preencher chave, senhas, domínio e porta
docker compose -f docker-compose.prod.yml up -d --build
```

O site escuta só em `127.0.0.1:${RADAR_PORTA}`. Um proxy (aqui, o Cloudflare Tunnel do servidor) publica o subdomínio com HTTPS.

O que a configuração de produção já traz:

- imagem com o código dentro, sem dependências de desenvolvimento, rodando como usuário sem privilégios (`www-data`);
- `APP_DEBUG=false`, logs diários guardados por 14 dias e logs do Docker com limite de tamanho;
- banco sem porta aberta para fora e senha obrigatória;
- `restart: unless-stopped` em todos os serviços, que voltam sozinhos depois de um reboot;
- tela de login obrigatória: sem senha configurada, o site fica bloqueado;
- backup diário do banco;
- rota `/saude` para monitoramento externo (UptimeRobot): responde 503 se a última coleta tiver mais de 4 horas.

### Atualizar o servidor

No seu computador:

```bash
git add -A && git commit -m "descrição da mudança" && git push
```

No servidor:

```bash
cd ~/apps/radarOnline
git pull
docker compose -f docker-compose.prod.yml up -d --build
```

As migrations rodam sozinhas quando o `app` inicia.

---

## Uso no dia a dia

Tudo pode ser feito pelo site, na página **Rotinas**:

| Botão | Quando usar |
|---|---|
| Coletar vagas | Para não esperar a próxima coleta automática. Dá para escolher uma fonte só |
| Reaplicar as regras | Depois de mudar `config/regras.php` ou `VAGAS_AREAS` |
| Apagar vagas antigas | Raramente: a coleta já faz isso |
| Enviar vagas novas | Para mandar no Telegram agora, sem esperar a próxima coleta |
| Ver o que seria enviado | Para conferir os filtros do Telegram sem enviar nada |
| Enviar resumo do dia | Para mandar o resumo agora |
| Testar o Telegram | Depois de configurar ou trocar o token ou o chat |
| Marcar tudo como enviado | Antes do primeiro envio, para não receber o acumulado de uma vez |

Pelo terminal do servidor, se preferir:

```bash
alias radar='docker compose -f ~/apps/radarOnline/docker-compose.prod.yml exec scheduler php artisan'
radar vagas:coletar
```

> ⚠️ Em produção, sempre rode o `artisan` **dentro do container**, como no alias acima. No servidor só existe o código-fonte; as dependências (`vendor/`) estão na imagem.

---

## Telegram

### 1. Criar o bot

1. No Telegram, abra o **@BotFather** e mande `/newbot`.
2. Escolha um nome e um username terminado em `bot`.
3. Copie o **token** (`123456789:AAH...`). Quem tiver o token controla o bot.

### 2. Descobrir o chat id

1. Abra a conversa com o seu bot, clique em **Iniciar** e mande qualquer mensagem.
2. Rode o comando abaixo (no servidor, ele lê o token do `.env` sem mostrá-lo):

   ```bash
   curl -s "https://api.telegram.org/bot$(grep '^TELEGRAM_BOT_TOKEN=' .env | cut -d= -f2-)/getUpdates" | grep -o '"chat":{"id":-\?[0-9]*'
   ```

3. Use o número **exatamente como aparece, com o sinal de menos se houver**:

   | Formato | O que é |
   |---|---|
   | `91392530` (positivo) | conversa privada com você |
   | `-5195940414` | grupo |
   | `-100...` | supergrupo ou canal (o bot precisa ser administrador do canal) |

### 3. Configurar e testar

No `.env`:

```
TELEGRAM_BOT_TOKEN=123456789:AAH...
TELEGRAM_CHAT_ID=91392530
```

Os containers só leem o `.env` quando iniciam. Recrie e teste:

```bash
docker compose -f docker-compose.prod.yml up -d --force-recreate app scheduler worker
docker compose -f docker-compose.prod.yml exec worker php artisan vagas:testar-telegram
```

Depois, em **Rotinas**, clique em **Marcar tudo como enviado**. Daqui em diante chegam só as vagas novas, até 15 por rodada.

### Receber só o que interessa

| Variável | Exemplo | Comportamento |
|---|---|---|
| `TELEGRAM_AREAS` | `desenvolvimento,dados` | Só essas áreas |
| `TELEGRAM_TECNOLOGIAS` | `php,laravel` | Só vagas que citam pelo menos uma |
| `TELEGRAM_MODELOS` | `remoto,hibrido` | Vagas sem modelo identificado passam também |
| `TELEGRAM_NIVEIS` | `pleno,senior` | Vagas sem nível identificado passam também |

Vazio = sem filtro. Vagas marcadas como "Descartar" nunca são enviadas. Use **Ver o que seria enviado** para conferir o efeito.

---

## Fontes de vagas

| Fonte | Situação | Como lê |
|---|---|---|
| Gupy | ✅ ligada | Página de busca do portal.gupy.io (JSON embutido), um termo amplo por vez |
| GitHub | ✅ ligada | API do GitHub: issues de `backend-br/vagas`, `frontendbr/vagas`, `react-brasil/vagas`, `soujava/vagas-java`, `qa-brasil/vagas` e outros |
| Himalayas | ✅ ligada | API pública de vagas remotas abertas para quem mora no Brasil |
| Remotar | 🧪 experimental | API do site, formato não documentado |
| Programathor | 🧪 experimental | HTML da listagem `/jobs` e data da página de cada vaga nova |
| LinkedIn | ⛔ desligada | Ver aviso abaixo |

- **Experimental** quer dizer que a fonte não tem formato documentado e pode quebrar se o site mudar. O aviso de fonte quebrada no Telegram e a situação no topo da página mostram quando isso acontecer.
- **LinkedIn:** os Termos de Uso proíbem coleta automatizada, e o site bloqueia IPs. O coletor existe, mas vem desligado. Se ligar (`FONTE_LINKEDIN=true`), é por sua conta e risco, nunca com sua conta logada.
- **Atribuição:** Himalayas e Remotar pedem crédito e link para a vaga original. O Radar mostra a fonte em cada vaga e sempre aponta para o anúncio original.
- **Testadas e deixadas de fora:** Remotive e We Work Remotely (poucas vagas, e a Remotive limita a 4 consultas por dia) e o RSS do Indeed (desativado, responde 403).

---

## Filtro e classificação

### Áreas

A área vem do título da vaga. As regras ficam em `config/regras.php` e aparecem em `/regras`.

| Área | Chave | Exemplos |
|---|---|---|
| Desenvolvimento | `desenvolvimento` | Desenvolvedor PHP, Software Engineer, Mobile, Tech Lead |
| Dados e IA | `dados` | Engenheiro de Dados, Cientista de Dados, BI, ML |
| Infra, cloud e DevOps | `infra` | DevOps, SRE, Cloud, Redes |
| QA e testes | `qa` | QA Engineer, Analista de Testes, SDET |
| Segurança | `seguranca` | Segurança da Informação, AppSec, SOC |
| Design de produto | `design` | Product Designer, UX/UI |
| Produto e agilidade | `produto` | Product Manager, Product Owner, Scrum Master |
| Gestão de tecnologia | `gestao` | CTO, Head de Engenharia, Gerente de TI |
| Suporte, TI e ERP | `suporte` | Suporte N1/N2, Help Desk, Consultor SAP |
| Outras de tecnologia | `outras` | Título genérico ("Principal Engineer") com pelo menos 3 tecnologias na descrição |

Para aceitar só algumas áreas, use `VAGAS_AREAS=desenvolvimento,dados,infra` e depois **Reaplicar as regras**.

### O que mais é identificado

- **Tecnologias:** cerca de 40 (PHP, Laravel, JavaScript, React, Python, Java, .NET, Go, AWS, Docker…). Servem para filtros, não para aceitar ou recusar a vaga.
- **Nível:** estágio, júnior, pleno, sênior, especialista.
- **Modelo:** remoto, híbrido, presencial.
- **Contrato:** CLT, PJ.
- **Salário:** faixa mensal lida da descrição ("R$ 5.000 a R$ 7.000", "até R$ 4 mil"), ignorando benefícios como VR, VA e auxílios. A Himalayas informa em dólar; o filtro de salário da página compara só valores em reais.

---

## Configuração (.env)

| Variável | Padrão | Para que serve |
|---|---|---|
| `APP_URL` | `http://localhost:8081` | Endereço do site, usado nos links do resumo e do Telegram |
| `RADAR_USUARIO` / `RADAR_SENHA` | – | Login do site. Em produção é obrigatório |
| `RADAR_PORTA` | `8090` | Porta local do site em produção (só em 127.0.0.1) |
| `RADAR_SAUDE_HORAS` | `4` | Depois de quantas horas sem coleta o `/saude` responde erro |
| `VAGAS_JANELA_HORAS` | `72` | Idade máxima de uma vaga para aparecer |
| `VAGAS_MANTER_DIAS` | `30` | Depois disso, a vaga é apagada (menos as "Candidatei") |
| `VAGAS_AREAS` | vazio = todas | Áreas aceitas pelo filtro |
| `VAGAS_ALERTA_FALHAS` | `2` | Falhas seguidas de uma fonte antes do aviso no Telegram |
| `FONTE_GUPY`, `FONTE_GITHUB`, `FONTE_HIMALAYAS`, `FONTE_REMOTAR`, `FONTE_PROGRAMATHOR`, `FONTE_LINKEDIN` | `true` (LinkedIn `false`) | Liga e desliga cada fonte |
| `GITHUB_REPOSITORIOS` | lista de repositórios BR | Repositórios lidos no GitHub |
| `GITHUB_TOKEN` | – | Opcional. Um token sem permissões sobe o limite de 60 para 5.000 requisições por hora |
| `TELEGRAM_BOT_TOKEN` / `TELEGRAM_CHAT_ID` | – | Bot e conversa do Telegram |
| `TELEGRAM_MAX_POR_RODADA` | `15` | Máximo de vagas por envio |
| `TELEGRAM_AREAS`, `TELEGRAM_TECNOLOGIAS`, `TELEGRAM_MODELOS`, `TELEGRAM_NIVEIS` | vazio | Filtros pessoais do Telegram |
| `QUEUE_CONNECTION` | `database` | Precisa ser `database` para os botões de Rotinas funcionarem |

Os termos de busca e as páginas lidas por fonte ficam em `config/vagas.php`. As regras do filtro ficam em `config/regras.php`.

> Depois de mudar o `.env` em produção, recrie os containers: `docker compose -f docker-compose.prod.yml up -d --force-recreate app scheduler worker`.

---

## Comandos

| Comando | O que faz |
|---|---|
| `vagas:coletar [--fonte=gupy]` | Coleta de todas as fontes ativas (ou de uma), grava, avisa fonte quebrada e limpa as antigas |
| `vagas:reavaliar` | Reaplica filtro e classificação às vagas já gravadas |
| `vagas:limpar` | Apaga vagas e históricos mais antigos que `VAGAS_MANTER_DIAS` |
| `vagas:notificar [--simular] [--marcar-enviadas]` | Envia as vagas novas no Telegram, mostra o que seria enviado ou marca tudo como enviado |
| `vagas:resumo [--telegram]` | Mostra o resumo do dia e, opcionalmente, envia no Telegram |
| `vagas:testar-telegram` | Envia uma mensagem de teste |
| `schedule:list` | Mostra as rotinas automáticas e o próximo horário |

Localmente: `docker compose exec app php artisan <comando>`.
Em produção: `docker compose -f docker-compose.prod.yml exec scheduler php artisan <comando>`.

---

## Testes

```bash
docker compose exec app php artisan test
```

São 86 testes, que cobrem:

- os coletores, com respostas reais das fontes reduzidas em `tests/fixtures/`;
- o filtro e as áreas;
- a extração de salário;
- o fluxo de coleta e as páginas;
- as marcações e os filtros;
- o Telegram (simulado);
- o login;
- a página Rotinas.

Os testes usam SQLite em memória e não acessam a internet.

---

## Estrutura do projeto

```
app/
├── Console/Commands/       comandos vagas:* (coletar, notificar, resumo, reavaliar, limpar, testar-telegram)
├── Http/
│   ├── Controllers/        VagasController, RotinasController, AcessoController
│   └── Middleware/         SenhaDeAcesso (exige login)
├── Jobs/ExecutarRotina.php executa no worker uma rotina pedida pela página
├── Models/                 Vaga, Coleta, Execucao
└── Radar/
    ├── Collectors/         um coletor por fonte (Gupy, GitHub, Himalayas, Remotar, Programathor, LinkedIn)
    ├── Filter/             JobFilter: é vaga de tecnologia? de qual área?
    ├── Classifier.php      nível, modelo, contrato e tecnologias
    ├── Salario.php         faixa salarial a partir do texto
    ├── Rotinas.php         lista de rotinas que os botões e o agendador podem executar
    └── Services/           ColetaService, SaudeFontes, Estatisticas, Resumo, Telegram, Acesso
config/
├── regras.php              regras públicas do filtro (áreas, bloqueios, tecnologias)
├── vagas.php               fontes, termos de busca, janela, Telegram
└── radar.php               login e monitoramento
docker/
├── Dockerfile, instalar.sh ambiente de desenvolvimento
└── producao/               imagem de produção (FrankenPHP) e script de início
routes/
├── web.php                 páginas
└── console.php             agendamento (coleta a cada 3h, resumo às 8h)
docker-compose.yml          desenvolvimento
docker-compose.prod.yml     produção
```

---

## Adicionar uma fonte nova

1. Crie uma classe em `app/Radar/Collectors/` que estenda `BaseCollector` e implemente `key()`, `label()` e `collect()`, devolvendo uma lista de `JobData`.
2. Separe a leitura do formato num método `parse()` e teste com uma resposta real salva em `tests/fixtures/` (veja `tests/Unit/ColetoresTest.php`).
3. Registre a classe em `config/vagas.php`, dentro de `fontes`, e crie a variável `FONTE_...` para ligar e desligar.

Filtro, classificação, duplicadas, janela de tempo, salário e aviso de fonte quebrada são aplicados automaticamente a qualquer fonte.

Fontes que valem estudar: Coodesh, Sólides, InHire, Recrutei, Nerdin, UpVagas e Catho.

---

## Solução de problemas

| Sintoma | Causa | Solução |
|---|---|---|
| `Bind for 0.0.0.0:XXXX failed: port is already allocated` | A porta já é usada por outro projeto | Troque `RADAR_PORTA` no `.env` (ou a porta no `docker-compose.yml`, em desenvolvimento) |
| `could not translate host name "db"` | O container do banco ficou sem rede depois de uma falha | `docker compose down` e `docker compose up -d` (os dados ficam) |
| `vendor/autoload.php: No such file` | `php artisan` rodado direto no servidor | Rode dentro do container (veja [Uso no dia a dia](#uso-no-dia-a-dia)) |
| `Telegram não configurado` com o `.env` preenchido | Os containers não foram recriados, ou a variável está duplicada no `.env` (vale a última linha) | Deixe uma linha só de cada e rode `up -d --force-recreate app scheduler worker` |
| `Bad Request: chat not found` | Chat id errado: faltou o `-` de grupo, ou o id é de outro bot | Confira com o `getUpdates` (veja [Telegram](#telegram)) |
| Botão de Rotinas fica em "Na fila" | O `worker` não está rodando, ou `QUEUE_CONNECTION` não é `database` | `docker compose -f docker-compose.prod.yml ps` e confira o `.env`. Depois de 20 minutos, a execução é dada como falha e o botão é liberado |
| Fonte aparece como "falhando" | O site mudou o formato ou está fora do ar | Veja a saída em Rotinas ou em `/regras` e ajuste o coletor |
| `/saude` responde 503 | Nenhuma coleta nas últimas 4 horas | Veja se o `scheduler` está rodando: `docker compose -f docker-compose.prod.yml logs --tail 50 scheduler` |
| Site responde 503 em produção | `RADAR_SENHA` vazia | Defina usuário e senha no `.env` e recrie os containers |
