# Colocando o Radar em produção

Guia para subir o Radar num servidor que já roda outros projetos, num subdomínio
(aqui, `radar.confeitae.com.br`; troque se quiser outro nome).

## Como fica no servidor

```
internet ──HTTPS──► proxy que você já usa (nginx, Caddy...) ──HTTP──► 127.0.0.1:8090
                                                                        │
                                           ┌────────────────────────────┤ docker-compose.prod.yml
                                           │ app        site (FrankenPHP), pede usuário e senha
                                           │ scheduler  coleta a cada 3h, Telegram, resumo das 8h
                                           │ db         Postgres, sem porta aberta para fora
                                           │ backup     pg_dump diário em ./backups (14 dias)
                                           └────────────────────────────
```

O Radar **não ocupa as portas 80 e 443**: quem atende o subdomínio e cuida do certificado
é o proxy que já serve seus outros projetos.

**Depois de subir, nada precisa ser rodado à mão.** O `scheduler` coleta a cada 3 horas,
avisa no Telegram e manda o resumo às 8h. Todos os serviços têm `restart: unless-stopped`
e voltam sozinhos depois de um reboot do servidor (desde que o Docker inicie com o sistema:
`sudo systemctl enable docker`).

## 1. DNS

No painel do domínio, crie um registro **A** `radar` apontando para o IP do servidor
(o mesmo IP de `confeitae.com.br`, se for o mesmo servidor).

## 2. Código no servidor

O jeito mais simples de atualizar depois é usar git (um repositório privado no GitHub, por exemplo):

```bash
git clone git@github.com:SEU_USUARIO/radar-vagas.git ~/radar-vagas
cd ~/radar-vagas
```

## 3. Configuração

```bash
cp .env.producao.example .env
nano .env
```

Preencha os campos marcados com ⚠️:

| Variável | Como gerar |
|---|---|
| `APP_KEY` | `echo "base64:$(openssl rand -base64 32)"` |
| `APP_URL` | `https://radar.confeitae.com.br` |
| `RADAR_USUARIO` / `RADAR_SENHA` | usuário que quiser; senha com `openssl rand -base64 24` |
| `DB_PASSWORD` | `openssl rand -hex 24` (não mude depois que o banco for criado) |
| `RADAR_PORTA` | `8090`, ou outra livre: confira com `sudo ss -ltnp \| grep 8090` |

Sem `RADAR_SENHA`, o site responde 503 em produção (de propósito).

```bash
chmod 600 .env
```

## 4. Subir

```bash
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml ps
```

O `app` roda as migrations sozinho ao iniciar. Teste local no servidor:

```bash
curl -s http://127.0.0.1:8090/up          # 200
curl -s -u USUARIO:SENHA http://127.0.0.1:8090/ | head -5
```

## 5. Proxy reverso

Descubra qual proxy o servidor usa: `sudo ss -ltnp | grep ':443'`.

### nginx

`/etc/nginx/sites-available/radar.confeitae.com.br`:

```nginx
server {
    listen 80;
    server_name radar.confeitae.com.br;

    location / {
        proxy_pass http://127.0.0.1:8090;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/radar.confeitae.com.br /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d radar.confeitae.com.br     # gera o HTTPS e redireciona o HTTP
```

### Caddy

No `Caddyfile` do servidor (o certificado sai sozinho):

```
radar.confeitae.com.br {
    reverse_proxy 127.0.0.1:8090
}
```

```bash
sudo systemctl reload caddy
```

### Proxy que roda dentro do Docker (Nginx Proxy Manager, Traefik)

Um container não enxerga o `127.0.0.1` do servidor. Nesse caso, ligue o serviço `app` à
rede do proxy e aponte o proxy para `radar-vagas-app-1:8080`. Me chame que eu ajusto o compose.

## 6. Primeira coleta e Telegram

```bash
alias radar='docker compose -f docker-compose.prod.yml exec scheduler php artisan'

radar vagas:coletar                       # primeira coleta (1 a 2 minutos)
radar vagas:notificar --marcar-enviadas   # evita receber centenas de vagas de uma vez
```

Abra `https://radar.confeitae.com.br`: o navegador pede usuário e senha.

## 7. Monitoramento

O Radar já avisa no Telegram quando uma **fonte** quebra. Para saber se o **agendador** parou
(ou o servidor caiu), cadastre um monitor gratuito no [UptimeRobot](https://uptimerobot.com):

- Tipo: HTTP(s), URL `https://radar.confeitae.com.br/saude`, intervalo de 30 a 60 minutos.
- `/saude` responde **200** se a última coleta tem menos de 4 horas e **503** se não.
  Não pede senha e não mostra nada além do horário da última coleta.

## Dia a dia

| Tarefa | Comando (na pasta do projeto) |
|---|---|
| Atualizar para a versão nova | `git pull && docker compose -f docker-compose.prod.yml up -d --build` |
| Ver logs do site / agendador | `docker compose -f docker-compose.prod.yml logs -f app` (ou `scheduler`) |
| Log do Laravel (1 arquivo por dia, 14 dias) | `docker compose -f docker-compose.prod.yml exec app ls storage/logs` |
| Coletar agora | `radar vagas:coletar` |
| Depois de mudar `config/regras.php` | `radar vagas:reavaliar` |
| Parar tudo | `docker compose -f docker-compose.prod.yml down` (os dados ficam) |

### Backups

Ficam em `./backups/radar-AAAA-MM-DD.sql.gz`, um por dia, guardados por 14 dias.
As vagas voltam sozinhas na próxima coleta; o que o backup protege de verdade são as suas
marcações de "Candidatei". Para restaurar:

```bash
gunzip -c backups/radar-2026-10-28.sql.gz | \
  docker compose -f docker-compose.prod.yml exec -T db psql -U radar -d radar
```

Vale copiar a pasta `backups` para fora do servidor de vez em quando (ou com `rclone` num cron).
