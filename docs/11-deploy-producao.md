# 11 — Deploy em Produção (sem Docker)

> O servidor de produção **não roda Docker**. Este documento assume hospedagem tradicional (VPS ou hospedagem compartilhada com acesso SSH) rodando Apache ou Nginx + PHP-FPM + MySQL diretamente no sistema operacional.

## 1. Requisitos do servidor

- PHP 8.4 (exigido pelo Laravel 13/Symfony atual), com extensões: `openssl`, `pdo_mysql`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `gd`, `intl`, `zip`.
- MySQL 8 ou MariaDB compatível.
- Composer disponível no servidor (ou build local + upload de `vendor/` via deploy, caso o host não permita `composer install`).
- Acesso a cron do sistema (obrigatório para o Scheduler).
- Certificado TLS válido (Let's Encrypt/Certbot ou equivalente do provedor de hospedagem).

## 2. Diferenças dev (Docker) x produção

| Aspecto | Dev (Docker) | Produção |
|---|---|---|
| Webserver | Nginx em container | Apache ou Nginx do próprio servidor |
| PHP | PHP-FPM em container | PHP-FPM (ou mod_php) do servidor |
| Banco | MySQL em container | MySQL/MariaDB gerenciado pelo host |
| Build de assets | `npm run dev`/`build` no WSL2 host | **Build feito fora do servidor** (local ou CI), só o resultado (`public/build`) é enviado |
| Node.js | Só no host WSL2, nunca em produção | Não instalado no servidor |
| Filas | `sync` | `sync` (mesma configuração — sem diferença) |

## 3. HTTPS obrigatório

Requisito técnico direto do Web Push (doc 10) e boa prática para um diário emocional sensível. Configurar Let's Encrypt (Certbot) ou certificado fornecido pelo provedor antes de habilitar notificações.

## 4. Build de assets fora do servidor

Como o servidor não tem Node, o pipeline de deploy é:
1. Localmente (ou em CI): `npm install && npm run build` → gera `public/build/`.
2. `public/build/` é versionado no deploy (via `git` incluindo a pasta gerada apenas no pacote de release, ou enviado via `rsync`/upload manual) — **não** via `.gitignore` padrão do Laravel, que ignora `public/build`; ajustar o processo de release para incluir esses arquivos no artefato enviado ao servidor.

## 5. `.env` de produção

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://seu-dominio.com

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync

VAPID_PUBLIC_KEY=...
VAPID_PRIVATE_KEY=...
VAPID_SUBJECT=mailto:seu-email@exemplo.com
```

## 6. Cron do Scheduler

Única entrada necessária no crontab do usuário do servidor:
```
* * * * * php /caminho/para/ancora/artisan schedule:run >> /dev/null 2>&1
```

## 7. Permissões

`storage/` e `bootstrap/cache/` precisam ser graváveis pelo usuário do PHP-FPM/Apache (`chmod -R 775` + owner correto, geralmente `www-data` ou o usuário do processo PHP-FPM da hospedagem).

## 8. Processo de deploy (manual, dado o porte do projeto)

1. `git pull` (ou upload do artefato) no servidor.
2. `composer install --no-dev --optimize-autoloader`
3. Copiar/gerar `public/build/` (ver seção 4).
4. `php artisan migrate --force`
5. `php artisan config:cache && php artisan route:cache && php artisan view:cache`
6. `php artisan queue:restart` (no-op dado `sync`, mas inofensivo manter por consistência caso mude no futuro).

Não há necessidade de zero-downtime deploy sofisticado (Envoyer, etc.) dado o uso pessoal — uma janela curta de indisponibilidade durante o `migrate` é aceitável.

## 9. Backup do banco

Cron adicional (diário) rodando `mysqldump` para um arquivo com data, mantendo os últimos N dias e, idealmente, copiando para um local fora do próprio servidor (ex.: download manual periódico, ou upload para armazenamento externo do próprio usuário). Dado que são dados emocionais sensíveis e pessoais, perder o histórico seria o pior cenário de falha do projeto.

## 10. Checklist pré/pós-deploy

- [ ] `.env` de produção revisado (`APP_DEBUG=false`, `APP_ENV=production`, chaves VAPID preenchidas)
- [ ] HTTPS ativo e válido
- [ ] `MoodCatalogSeeder` rodado (categorias/sentimentos populados)
- [ ] Usuário único criado (comando artisan do doc 04)
- [ ] Cron do Scheduler configurado e testado (`schedule:run` manual uma vez)
- [ ] Backup automático do MySQL configurado
- [ ] PWA instalável testada no domínio real (ícones, manifest, service worker)
- [ ] Um lembrete de teste disparado com sucesso em produção
