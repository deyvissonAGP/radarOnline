# 📡 Radar de vagas tech

Coleta vagas de tecnologia (desenvolvimento, dados e IA, infra e DevOps, QA, segurança, design de produto, produto e agilidade, gestão de tecnologia, suporte e TI) de várias fontes a cada 3 horas, descarta o que não é vaga de tecnologia, junta duplicadas e mostra tudo numa página com filtros. Também manda as vagas novas no Telegram e gera um resumo diário pronto para postar.

Só contam vagas publicadas nas últimas **72 horas** (configurável).

## O que vem pronto

| Parte | Onde |
|---|---|
| Página com contadores e filtros (área, remoto, tecnologia, nível, fonte, salário) | `http://localhost:8081` |
| Busca no título, empresa e descrição (ignora acentos: "senior" acha "Sênior") | campo de busca da página |
| Marcar vagas como "Candidatei" ou "Descartar", com listas próprias | botões em cada vaga |
| Situação de cada fonte (ok / falhando) | topo da página |
| Resumo do dia com botão de copiar | `/resumo` |
| Regras do filtro, itens descartados e histórico de coletas | `/regras` |
| Botões para coletar, reaplicar regras, limpar e usar o Telegram, com histórico e saída de cada execução | `/rotinas` |
| Coleta manual | `php artisan vagas:coletar` |
| Aviso de vagas novas no Telegram | `php artisan vagas:notificar` |
| Resumo no terminal (e no Telegram com `--telegram`) | `php artisan vagas:resumo` |
| Reaplicar as regras às vagas já gravadas | `php artisan vagas:reavaliar` |
| Agendamento automático (coleta a cada 3h, resumo às 8h) | `routes/console.php` |

## Fontes

| Fonte | Situação | Como lê |
|---|---|---|
| Gupy | ✅ ligada | Página de busca do portal.gupy.io (JSON embutido), um termo amplo por vez (`termos` em `config/vagas.php`) |
| GitHub (backend-br, frontendbr, react-brasil, soujava, qa-brasil e outros) | ✅ ligada | API do GitHub (cada issue é uma vaga) |
| Himalayas | ✅ ligada | API pública de vagas remotas, só as abertas para quem mora no Brasil, da mais nova para a mais antiga |
| Remotar | 🧪 experimental | API do site, da mais nova para a mais antiga (formato não documentado) |
| Programathor | 🧪 experimental | HTML da listagem `/jobs` + data da página de cada vaga nova |
| LinkedIn | ⛔ desligada | Listagem pública, veja o aviso abaixo |

"Experimental" quer dizer que o código foi escrito para o formato que esses sites costumam usar, mas não pude testar contra o site de verdade. Depois da primeira coleta, a tabela do comando mostra quais fontes funcionaram. Se alguma falhar, me mande a saída do erro que eu ajusto.

**Sobre o LinkedIn:** os Termos de Uso do LinkedIn proíbem coleta automatizada, e o site bloqueia IPs que fazem muitas requisições. O coletor existe, mas vem desligado. Se decidir ligar (`FONTE_LINKEDIN=true`), faça isso por sua conta e risco, nunca com sua conta logada.

## Instalação (Docker)

Você precisa só do Docker (no Windows, o Docker Desktop).

```bash
# 1. Entre na pasta do projeto e instale (baixa o Laravel e cria as tabelas)
docker compose run --rm app sh docker/instalar.sh

# 2. Suba o site, o banco e o agendador
docker compose up -d

# 3. Faça a primeira coleta
docker compose exec app php artisan vagas:coletar
```

Abra **http://localhost:8081**. A partir daí o serviço `scheduler` coleta sozinho a cada 3 horas enquanto o Docker estiver rodando.

**Produção:** veja [PRODUCAO.md](PRODUCAO.md) (Docker com HTTPS pelo proxy do servidor, tela de login, backup diário e monitoramento).

Para rodar os testes:

```bash
docker compose exec app php artisan test
```

## Telegram

1. No Telegram, fale com o **@BotFather**, mande `/newbot` e copie o token.
2. Mande qualquer mensagem para o seu bot.
3. Abra `https://api.telegram.org/bot<SEU_TOKEN>/getUpdates` no navegador e copie o número em `"chat":{"id": ...}`.
   Para um canal: adicione o bot como administrador e use `@nome_do_canal` como chat id.
4. Coloque no `.env`:
   ```
   TELEGRAM_BOT_TOKEN=123456:ABC...
   TELEGRAM_CHAT_ID=987654321
   ```
5. Para não receber de uma vez todas as vagas já coletadas, marque-as como avisadas antes:
   ```bash
   docker compose exec app php artisan vagas:notificar --marcar-enviadas
   ```

Daqui em diante, cada coleta avisa as vagas novas (até 15 por rodada) e o resumo chega todo dia às 8h.

**Receber só o que interessa:** no `.env`, `TELEGRAM_MODELOS=remoto,hibrido` e `TELEGRAM_NIVEIS=pleno,senior` limitam os avisos. Vagas em que o Radar não conseguiu identificar o modelo ou o nível são enviadas mesmo assim, para nenhuma vaga boa se perder. Vagas marcadas como "Descartar" não são enviadas.

**Aviso de fonte quebrada:** quando uma fonte dá erro ou volta sem nenhuma vaga em 2 coletas seguidas (`VAGAS_ALERTA_FALHAS`), chega uma mensagem no Telegram. O aviso vai uma vez só; a situação continua visível no topo da página até a fonte voltar.

## Áreas

Cada vaga aceita ganha uma área, decidida pelo título (regras em `config/regras.php`, visíveis em `/regras`):

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
| Outras de tecnologia | `outras` | Títulos genéricos ("Principal Engineer") com pelo menos 3 tecnologias na descrição |

- **Só algumas áreas:** `VAGAS_AREAS=desenvolvimento,dados,infra` no `.env`. As outras passam a ser descartadas (motivo "área desligada"). Depois de mudar, rode `php artisan vagas:reavaliar`.
- **Telegram só com o que interessa:** `TELEGRAM_AREAS=desenvolvimento` e `TELEGRAM_TECNOLOGIAS=php,laravel` (uma das tecnologias basta). Os nomes das tecnologias estão em `/regras`.

## Ajustes comuns

- **Mudar o que conta como vaga:** `config/regras.php`. As regras aparecem automaticamente em `/regras`. Depois de mudar, `php artisan vagas:reavaliar` aplica as regras novas às vagas já gravadas.
- **Termos de busca, janela de horas, fontes:** `config/vagas.php` e `.env`.
- **Repositórios do GitHub:** `GITHUB_REPOSITORIOS` no `.env`. Um token sem nenhuma permissão em `GITHUB_TOKEN` sobe o limite de 60 para 5.000 requisições por hora.
- **Testar uma fonte só:** `php artisan vagas:coletar --fonte=remotar`
- **Salário:** o Radar lê a faixa da descrição ("R$ 5.000 a R$ 7.000", "salário: R$ 6.500", "até R$ 4 mil") e ignora valores de benefícios (VR, VA, auxílios). A Himalayas informa em dólar; o filtro de salário da página compara só valores em reais.
- **Candidaturas:** vagas marcadas como "Candidatei" não são apagadas na limpeza de `VAGAS_MANTER_DIAS`.

## Adicionar uma fonte nova

1. Crie uma classe em `app/Radar/Collectors/` que estenda `BaseCollector` e implemente `key()`, `label()` e `collect()`, devolvendo uma lista de `JobData`.
2. Separe a leitura do formato num método `parse()` e teste com um arquivo de exemplo em `tests/fixtures/` (veja `tests/Unit/ColetoresTest.php`).
3. Registre em `config/vagas.php`, dentro de `fontes`.

O filtro, a classificação (remoto, nível, CLT/PJ, frameworks), as duplicadas e a janela de tempo são aplicados automaticamente a qualquer fonte.

Próximas fontes que valem a pena: Coodesh, Sólides, InHire, Recrutei, Nerdin, UpVagas e Catho.

Testadas e deixadas de fora: Remotive e We Work Remotely (poucas vagas e atrasadas em 24h; a Remotive pede no máximo 4 consultas por dia) e o RSS do Indeed (desativado, responde 403).

## Como funciona

```
fontes ──► coletor (parse) ──► JobData ──► filtro ──► classificador ──► banco
                                              │                          │
                                     regras públicas            duplicadas viram uma só
                                                                         │
                                         página · resumo · Telegram ◄────┘
```

- `app/Radar/Filter/JobFilter.php`: aplica as regras de `config/regras.php`
- `app/Radar/Classifier.php`: detecta modelo de trabalho, senioridade, contrato e frameworks
- `app/Radar/Services/ColetaService.php`: grava, ignora o que está fora da janela e marca duplicadas
- `app/Radar/Services/Estatisticas.php`: os números do topo da página e do resumo
- `app/Radar/Salario.php`: encontra a faixa salarial no texto da vaga
- `app/Radar/Services/SaudeFontes.php`: situação de cada fonte, usada no topo da página e no aviso de fonte quebrada
