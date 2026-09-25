# 04 — Autenticação

## 1. Instalação do Breeze

`composer require laravel/breeze --dev` seguido de `php artisan breeze:install blade` com a stack `Livewire` (Breeze oferece um preset Livewire pronto, coerente com a decisão do doc 01). Isso gera: telas de login, reset de senha, perfil, e middleware `auth`/`guest` já configurados.

## 2. Bloqueio do registro público

Como há um único usuário e o app é de uso pessoal, a rota de **registro público deve ser removida/desabilitada**:
- Remover a rota `register` gerada pelo Breeze em `routes/auth.php` (ou envolvê-la num middleware que sempre retorna 404).
- Remover o link "Registre-se" da tela de login.
- Manter apenas: login, logout, "esqueci minha senha" (útil mesmo com 1 usuário, evita ficar trancado fora).

## 3. Criação do usuário único

Em vez de cadastro público, o usuário é criado por um comando artisan dedicado (ou via `DemoUserSeeder` em dev, e um comando `php artisan ancora:install` em produção que pede nome/e-mail/senha interativamente e cria `User` + `Patient` vazio associado). Isso evita expor qualquer rota de criação de conta.

## 4. Login / logout / recuperação de senha

Fluxo padrão do Breeze, sem alterações significativas:
- Login com e-mail + senha.
- "Lembrar-me" habilitado (conveniência para uso pessoal recorrente).
- Recuperação de senha via e-mail (usa `MAIL_MAILER` configurado — Mailpit em dev, SMTP real em produção).

## 5. Middleware de proteção global

Todas as rotas do domínio da aplicação (registro emocional, histórico, relatórios, lembretes, cadastro de paciente) ficam dentro de um grupo de rotas com middleware `auth`, em `routes/web.php`:

```php
Route::middleware(['auth'])->group(function () {
    // todas as rotas do app
});
```

Não há necessidade de Policies complexas (só 1 usuário), mas os componentes/controllers devem sempre resolver o `Patient` a partir do `auth()->user()` (nunca aceitar um `patient_id` vindo de input do usuário sem checar posse) — defesa em profundidade simples.

## 6. Rate limiting

Mantido o padrão do Breeze (`throttle:login`) para o formulário de login, mitigando força bruta.

## 7. Sessão sem Redis

`SESSION_DRIVER=database` (ver doc 01/03). Requer `php artisan session:table && php artisan migrate` uma vez. Mesmo racional para `CACHE_STORE=database`.

## 8. Tela de perfil

Mantida a tela de perfil padrão do Breeze (trocar nome/e-mail/senha do usuário de login), separada da tela de "Meus dados" do paciente (doc 05) — são conceitos diferentes: `User` é a credencial de acesso, `Patient` são os dados clínicos/pessoais exibidos no relatório.
