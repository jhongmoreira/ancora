# 10 — PWA e Notificações Push

Este é o ponto tecnicamente mais sensível do projeto: notificações precisam chegar **mesmo com o navegador/app fechado**, em até 3 horários configuráveis por dia, rodando em um servidor de produção **sem fila assíncrona garantida**.

## 1. Manifest, ícones e instalabilidade

`public/manifest.json` com `name`, `short_name` ("Âncora"), `start_url`, `display: "standalone"`, `theme_color`, `background_color`, e um conjunto de ícones (192x192, 512x512, incluindo versão "maskable" para Android). Referenciado no `<head>` de `resources/views/layouts/app.blade.php` via `<link rel="manifest" href="/manifest.json">` + meta tags de tema.

## 2. Service Worker

`public/sw.js`, registrado via JS no layout principal (`navigator.serviceWorker.register('/sw.js')`). Responsabilidades mínimas:
- Cache básico de shell estático (ícones, CSS/JS compilados) para permitir abrir o app mesmo offline (tela de "sem conexão", já que o registro emocional em si exige backend).
- Listener de evento `push` — recebe o payload da notificação e exibe via `self.registration.showNotification(...)`.
- Listener de `notificationclick` — abre/foca a aba do app na URL do registro emocional.

## 3. VAPID + pacote webpush

`composer require laravel-notification-channels/webpush`, gerar chaves com `php artisan webpush:vapid` (salvas em `.env` como `VAPID_PUBLIC_KEY`/`VAPID_PRIVATE_KEY`). O model `User` implementa `HasPushSubscriptions`.

## 4. Fluxo de subscription

1. Usuário acessa a tela de configuração de lembretes e clica em "Ativar notificações".
2. JS pede permissão (`Notification.requestPermission()`).
3. Se concedida, `navigator.serviceWorker.ready` + `pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: VAPID_PUBLIC_KEY })`.
4. A subscription resultante é enviada ao backend (rota `POST /push-subscriptions`) e persistida via `$user->updatePushSubscription(...)` do pacote.

## 5. Tela de configuração de lembretes (máx. 3)

CRUD simples sobre `Reminder`: adicionar horário (time picker), rótulo opcional, ativar/desativar. Validação (`StoreReminderRequest`) bloqueia criar um 4º lembrete ativo por paciente — mensagem clara: "Você já tem 3 lembretes ativos. Desative um para adicionar outro."

## 6. Comando `reminders:dispatch` + idempotência

`app/Console/Commands/DispatchReminders.php`, executado a cada minuto:

```php
$now = now();
$reminders = Reminder::where('is_active', true)
    ->whereTime('time', '>=', $now->copy()->subMinutes(15)->format('H:i:s'))
    ->whereTime('time', '<=', $now->format('H:i:s'))
    ->get();

foreach ($reminders as $reminder) {
    $created = ReminderDispatchLog::firstOrCreate([
        'reminder_id' => $reminder->id,
        'sent_date' => $now->toDateString(),
    ], ['sent_at' => $now]);

    if ($created->wasRecentlyCreated) {
        $reminder->patient->user->notify(new EmotionLogReminder($reminder));
    }
}
```

A janela de tolerância de 15 minutos cobre atrasos do cron; a constraint única em `reminder_dispatch_logs` (doc 02) garante que, mesmo rodando o comando mais de uma vez no mesmo minuto/janela, o envio não duplica.

## 7. Scheduler + cron do sistema

Em `routes/console.php` (Laravel 11+ não usa mais `app/Console/Kernel.php`):

```php
Schedule::command('reminders:dispatch')->everyMinute();
```

E no servidor (dev e produção), uma única entrada de cron do sistema operacional:

```
* * * * * php /caminho/para/ancora/artisan schedule:run >> /dev/null 2>&1
```

## 8. Por que sem filas assíncronas

O envio da notificação acontece **dentro do próprio comando agendado** (síncrono), não despachado para uma fila. Como o volume é de no máximo 3 notificações/dia para 1 usuário, não há ganho em processar de forma assíncrona, e evitar filas remove a dependência de um worker persistente (`queue:work`), que hospedagem compartilhada tipicamente não garante manter rodando.

## 9. Limitações conhecidas

- **HTTPS obrigatório**: Push API e Service Workers só funcionam em contexto seguro (exceção: `localhost` em dev).
- **iOS Safari**: Web Push só funciona em PWAs **instalados na tela de início** (não em aba comum do Safari), e requer iOS 16.4+. Isso deve ser comunicado ao usuário na tela de configuração de notificações (ex.: aviso "No iPhone, adicione o Âncora à tela de início para receber lembretes").
- **Permissão negada/revogada**: se o usuário negar ou revogar a permissão do navegador, os lembretes continuam sendo "disparados" no backend (log gravado), mas a entrega falha silenciosamente do lado do navegador — o app deve refletir na UI se a subscription está ativa ou não.

## 10. Testes manuais

Checklist para validar esta fase manualmente (não há como testar Web Push real via Pest de forma prática):
- [ ] Instalar o PWA na tela de início (Android e, se possível, iOS).
- [ ] Ativar notificações e confirmar subscription salva no banco.
- [ ] Criar um lembrete para 1-2 minutos no futuro e confirmar que a notificação chega com o navegador fechado.
- [ ] Rodar `php artisan reminders:dispatch` manualmente duas vezes seguidas e confirmar que não duplica o envio (checar `reminder_dispatch_logs`).
- [ ] Revogar a permissão de notificação no navegador e confirmar que a UI reflete o estado "desativado".
