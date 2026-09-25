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
1. Localmente: `npm run build` → gera `public/build/`.
2. **Decisão real de deploy**: hospedagem é a Hostoo (painel próprio), com deploy via a funcionalidade do painel que puxa direto de um repositório GitHub (`jhongmoreira/ancora`, público). Como esse método só clona o repositório — não roda `npm install`/`npm run build` no servidor — `public/build/` foi **removido do `.gitignore` e passou a ser versionado no git**, fugindo da convenção padrão do Laravel. Isso significa: **sempre que houver mudança em CSS/JS/Blade que afete os assets, rodar `npm run build` e commitar `public/build/` antes de fazer push** — esquecer esse passo deixa o site em produção com assets desatualizados (mesma classe de bug já visto em dev, ver docs/03 seção 9).

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

## 8. Processo de deploy (Hostoo, via GitHub)

1. Local: garantir testes passando, `npm run build`, commitar `public/build/` atualizado, `git push` para o `main` do GitHub.
2. No painel Hostoo, usar a opção de deploy que puxa do repositório GitHub (aponta para `jhongmoreira/ancora`, branch `main`) — isso resolve o "enviar os arquivos".
3. Via SSH, dentro da pasta onde o painel clonou o projeto:
   - `composer install --no-dev --optimize-autoloader`
   - Criar/editar o `.env` de produção (ver seção 5) — **nunca vai pro git**, é criado manualmente uma vez no servidor.
   - `php artisan key:generate --force` (só na primeira vez)
   - `php artisan migrate --force`
   - `php artisan db:seed --class=MoodCatalogSeeder --force` (só na primeira vez — popula o catálogo de humor/sentimentos)
   - `php artisan ancora:install` (só na primeira vez — cria o usuário único; ver docs/04)
   - `php artisan config:cache && php artisan route:cache && php artisan view:cache`
4. Resolver o **document root** apontando para a pasta `public/` do projeto (não a raiz) — ver seção 8.1, já que cada painel lida com isso de um jeito.
5. Repetições de deploy (depois da primeira vez): repetir só os passos 1-2, mais `composer install --no-dev` e `php artisan migrate --force` + re-cachear (passo 3, pulando os itens "só na primeira vez").

Não há necessidade de zero-downtime deploy sofisticado (Envoyer, etc.) dado o uso pessoal — uma janela curta de indisponibilidade durante o `migrate` é aceitável.

### 8.1 Document root — apontar para `public/`

Laravel espera que o **document root do domínio seja a pasta `public/`** do projeto, não a raiz (`app/`, `vendor/` etc. não podem ficar acessíveis publicamente). Em painéis compartilhados isso costuma aparecer de duas formas:

- **O painel permite escolher a pasta pública do domínio/subdomínio**: aponte diretamente para `.../ancora/public`. Caminho mais limpo — usar sempre que disponível.
- **O painel força o document root para `public_html/` (ou equivalente) e não permite trocar**: clonar o projeto FORA de `public_html` (ex.: `~/ancora`), copiar o **conteúdo** de `~/ancora/public/` para dentro de `public_html/`, e editar o `public_html/index.php` copiado para apontar os `require` para `~/ancora/vendor/autoload.php` e `~/ancora/bootstrap/app.php` (em vez dos caminhos relativos `__DIR__.'/../...'` originais, que assumiam estar *dentro* da estrutura do projeto). Esse `index.php` copiado precisa ser reaplicado a cada deploy (ou trocado por um symlink de `public_html` para `~/ancora/public`, se o Hostoo permitir symlinks).

### 8.2 Lições do deploy real na Hostoo (cPanel + LiteSpeed)

O painel da Hostoo não expõe nenhuma opção de document root (nem por domínio, nem por subdomínio depois de criado). O caminho que funcionou:

1. **Criar um subdomínio dedicado** (ex.: `ancora.jgmoreira.com.br`) e, na tela de criação, apontar o campo "Diretório" direto para `ancoraweb/public` (dentro de `public_html`). Isso evita ter que reestruturar/mover a instalação.
2. **O app precisa estar na raiz do (sub)domínio, não numa subpasta da URL.** Testamos rodar em `dominio.com/pasta/public` e o login quebrava: o próprio pacote **Livewire monta a URL do seu script e do endpoint `/livewire/update` como caminho absoluto começando com `/`**, ignorando `APP_URL` — isso não é configurável (não é um bug do nosso código, é assim que o pacote funciona) e só existe uma saída real: o app precisa responder na raiz de um domínio ou subdomínio (sem `/algumacoisa` na URL).
3. **A versão do PHP selecionada no painel pode não valer pro (sub)domínio de verdade** — descobrimos isso vendo o erro `Composer detected issues in your platform: ... PHP version ">= 8.4.1"` mesmo com "PHP 8.4" marcado como selecionado na tela do painel. Confirmar sempre com um teste direto (`curl -s https://seu-dominio/algum-arquivo.php` retornando `<?php echo PHP_VERSION;`), não confiar só na tela do painel. Se a versão real divergir, forçar via `.htaccess` (ver `public/.htaccess` no repositório — bloco `AddHandler application/x-httpd-ea-php84 .php` no topo do arquivo).

## 9. Backup do banco

Cron adicional (diário) rodando `mysqldump` para um arquivo com data, mantendo os últimos N dias e, idealmente, copiando para um local fora do próprio servidor (ex.: download manual periódico, ou upload para armazenamento externo do próprio usuário). Dado que são dados emocionais sensíveis e pessoais, perder o histórico seria o pior cenário de falha do projeto.

## 10. Checklist pré/pós-deploy

- [ ] PHP 8.4 confirmado no domínio (`php -v` via SSH)
- [ ] Banco MySQL criado no painel (nome/usuário/senha anotados)
- [ ] Repositório GitHub configurado como fonte de deploy no painel Hostoo
- [ ] Document root do domínio apontando para `public/` (seção 8.1)
- [ ] `.env` de produção revisado (`APP_DEBUG=false`, `APP_ENV=production`, chaves VAPID preenchidas)
- [ ] `storage/` e `bootstrap/cache/` graváveis (seção 7)
- [ ] HTTPS ativo e válido
- [ ] `MoodCatalogSeeder` rodado (categorias/sentimentos populados)
- [ ] Usuário único criado (`php artisan ancora:install`, doc 04)
- [ ] Cron do Scheduler configurado e testado (`schedule:run` manual uma vez)
- [ ] Backup automático do MySQL configurado
- [ ] PWA instalável testada no domínio real (ícones, manifest, service worker)
- [ ] Um lembrete de teste disparado com sucesso em produção
- [ ] Link compartilhado (docs/13) testado ponta a ponta em produção (gerar, copiar, abrir em aba anônima, digitar PIN)
