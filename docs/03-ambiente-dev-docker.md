# 03 — Ambiente de Desenvolvimento (Docker)

> ⚠️ **Este ambiente Docker existe apenas para desenvolvimento local.** A produção usa hospedagem tradicional (PHP-FPM/Apache ou Nginx + MySQL), **sem containers** — ver [`11-deploy-producao.md`](./11-deploy-producao.md). Nenhuma decisão de arquitetura da aplicação deve assumir que Docker estará disponível em produção.

## 1. Pré-requisitos

- WSL2 com Ubuntu (ambiente atual do usuário).
- Docker Engine + Docker Compose já instalados (confirmado: Docker 29.1.3, Compose v5.0.1).
- Node.js instalado **direto no WSL2** (não em container) — usado apenas para `npm install && npm run build` dos assets (Vite). Produção também não roda Node, então essa escolha espelha o ambiente final.
- Composer instalado no WSL2 (ou rodando via container `app` pontualmente — decidir conforme conveniência; recomenda-se instalar no host para autocomplete/IDE funcionarem bem).

## 2. Serviços do `docker-compose.yml`

| Serviço | Imagem/base | Propósito |
|---|---|---|
| `app` | Dockerfile próprio (PHP-FPM 8.3) | Executa o Laravel (PHP-FPM), com extensões: `pdo_mysql`, `mbstring`, `bcmath`, `gd`, `intl`, `zip`, `exif`. |
| `webserver` | `nginx:alpine` | Serve a aplicação, proxy para `app:9000` via FastCGI. |
| `db` | `mysql:8.0` | Banco de dados, com volume nomeado para persistir dados entre restarts. |
| `adminer` | `adminer:latest` | Inspeção visual do banco em dev (mais leve que phpMyAdmin). |
| `mailpit` | `axllent/mailpit` | Captura e-mails (reset de senha, etc.) sem enviar de verdade. |

## 3. Dockerfile do `app` (esqueleto)

```dockerfile
FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    libzip-dev libpng-dev libicu-dev \
    && docker-php-ext-install pdo_mysql mbstring bcmath gd intl zip exif

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
```

## 4. Config Nginx (esqueleto)

```nginx
server {
    listen 80;
    root /var/www/html/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

## 5. `.env.example` (dev)

Pontos relevantes a fixar já em dev (para não haver surpresa em produção):

```
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=ancora
DB_USERNAME=ancora
DB_PASSWORD=secret

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025

VAPID_PUBLIC_KEY=
VAPID_PRIVATE_KEY=
VAPID_SUBJECT=mailto:seu-email@exemplo.com
```

## 6. Passo a passo de subida

1. `docker compose up -d --build`
2. `docker compose exec app composer install`
3. `docker compose exec app cp .env.example .env`
4. `docker compose exec app php artisan key:generate`
5. `docker compose exec app php artisan migrate --seed`
6. `docker compose exec app php artisan session:table && php artisan cache:table && php artisan migrate` (se ainda não geradas)
7. No host WSL2: `npm install && npm run dev` (ou `npm run build` para produção local)
8. Acessar `http://localhost:8000` (porta mapeada do serviço `webserver`)

## 7. HTTPS local (necessário para testar PWA/Web Push)

Push API e Service Workers exigem contexto seguro (HTTPS) — `localhost` é uma exceção permitida pelos navegadores modernos para desenvolvimento, então na maioria dos casos **testar via `http://localhost:8000` já funciona** para Service Worker/Push. Caso seja necessário testar em um dispositivo físico (celular) na mesma rede, usar uma destas opções:
- **mkcert**: gera certificado local confiável, configurado no Nginx do compose.
- **Cloudflare Tunnel / ngrok**: expõe `localhost` via HTTPS público temporário para testar no celular real.

## 8. Comandos úteis

```bash
docker compose exec app php artisan tinker
docker compose exec app php artisan migrate:fresh --seed
docker compose logs -f app
docker compose exec db mysql -u ancora -p ancora
```

## 9. Troubleshooting

- **Permissões de `storage/` e `bootstrap/cache/`**: se o container rodar como usuário diferente do host, ajustar `chmod -R 775` ou mapear UID/GID no Dockerfile.
- **MySQL não sobe a tempo do `migrate`**: usar `depends_on` com `condition: service_healthy` no compose, com healthcheck no serviço `db`.
- **Assets não atualizam**: rodar `npm run dev` (watch) no host, não dentro do container.
