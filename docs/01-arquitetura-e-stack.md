# 01 — Arquitetura e Stack

## 1. Visão geral da stack

| Camada | Escolha | Por quê |
|---|---|---|
| Backend | Laravel 13 (PHP 8.4+) | Framework maduro, produtivo, com tudo que o app precisa (auth, ORM, scheduler, notifications) sem infraestrutura extra. |
| Frontend | Blade + Livewire 3 + Alpine.js | Interatividade (wizard de registro, filtros reativos) sem precisar de uma API JSON separada nem de um build SPA (React/Vue) — mantém tudo renderizado no servidor. |
| Gráficos | Chart.js | Leve, sem dependência de framework JS, atende aos 3 gráficos pedidos. |
| Banco | MySQL 8 | Definido pelo usuário. |
| PDF | barryvdh/laravel-dompdf | PHP puro — sem Chrome headless/Node, funciona em hospedagem compartilhada. |
| Excel | maatwebsite/excel | Padrão de facto no ecossistema Laravel (wrapper do PhpSpreadsheet). |
| Push | laravel-notification-channels/webpush | VAPID/Web Push nativo, sem infraestrutura de terceiros (ex.: Firebase). |
| Sessão/Cache | Driver `database` | Evita depender de Redis (não garantido em hospedagem tradicional) e evita o driver `file` (sensível a permissões variáveis entre hosts). |
| Fila | `sync` (sem worker) | Volume baixíssimo (1 usuário); evita depender de `queue:work` persistente, que hospedagem compartilhada normalmente não garante. |

### Por que Blade+Livewire em vez de uma SPA (Vue/React) com API

- O app não precisa de app mobile nativo consumindo a mesma API — é só um PWA do próprio Laravel.
- Evita duplicar validação (Form Request no backend + validação no frontend).
- Produção não terá Node rodando; com Livewire, o único artefato de build é o CSS/JS compilado uma vez (Vite), sem exigir SSR ou servidor Node.
- Reduz a superfície do projeto — alinhado ao princípio "prático, não grandioso" (doc 00).

## 2. Estrutura de pastas do Laravel (padrão, com adições)

```
app/
  Livewire/
    EmotionLog/
      CreateWizard.php
      History.php
    Dashboard.php
    ReminderManager.php
  Models/
    Patient.php
    Professional.php
    MoodCategory.php
    Feeling.php
    EmotionLog.php
    Reminder.php
    ReminderDispatchLog.php
  Services/
    EmotionLogService.php      # cria/edita registros, valida regras de negócio
    ReportExportService.php    # monta dados para PDF/Excel
    ReminderDispatchService.php
  Http/
    Requests/
      StorePatientRequest.php
      StoreEmotionLogRequest.php
      StoreReminderRequest.php
    Controllers/
      ExportController.php     # download de PDF/Excel (não precisa de Livewire)
  Console/
    Commands/
      DispatchReminders.php
  Exports/
    EmotionLogsExport.php      # classe do maatwebsite/excel
resources/
  views/
    livewire/...
    pdf/
      emotion-logs-report.blade.php
  js/app.js
  css/app.css
public/
  manifest.json
  sw.js
  icons/
routes/
  web.php
  console.php                  # Scheduler (Laravel 11+ não usa mais app/Console/Kernel.php)
database/
  migrations/
  seeders/
    MoodCatalogSeeder.php
    DemoUserSeeder.php (dev only)
```

## 3. Padrões de código

- **Form Requests** para toda validação de entrada (`StoreEmotionLogRequest`, `StoreReminderRequest`, etc.) — nunca validar direto no controller/componente Livewire além do essencial de UI.
- **Services** para regras de negócio que envolvem mais de uma escrita ou lógica não trivial (ex.: `EmotionLogService::create()` grava o log e sincroniza a pivot de sentimentos numa transação; `ReminderDispatchService` decide quais lembretes disparar).
- **Policies** não são necessárias no sentido de multi-usuário (só existe 1 usuário), mas usar um middleware simples que garante que todo dado acessado pertence ao paciente do usuário autenticado (defesa em profundidade, útil se um dia virar multi-paciente).
- **Eloquent** com relacionamentos explícitos e `casts` (`occurred_at` como `datetime`, `intensity` como `integer`).
- Nomeação em inglês no código (tabelas, classes, variáveis), rótulos e textos de UI em português.

## 4. Pacotes — escolhidos x descartados

| Necessidade | Escolhido | Descartado | Motivo do descarte |
|---|---|---|---|
| Auth | laravel/breeze | laravel/jetstream, laravel/fortify isolado | Breeze é mais simples e já entrega Blade+Livewire pronto; Jetstream traz times/API tokens desnecessários para 1 usuário. |
| PDF | barryvdh/laravel-dompdf | spatie/laravel-pdf | Spatie usa Browsershot (Chrome headless via Node) — inviável em hospedagem compartilhada sem controle de binários. |
| Excel | maatwebsite/excel | exportar CSV manualmente | Usuário pediu XLS explicitamente; a lib dá suporte a formatação de planilha real. |
| Push | laravel-notification-channels/webpush | Firebase Cloud Messaging | Web Push nativo (VAPID) não exige conta/serviço de terceiro nem SDK externo. |
| Gráficos | Chart.js (via CDN ou npm) | ApexCharts, Livewire Charts | Chart.js é o mais leve e suficiente para os gráficos pedidos. |
| Fila | sync | database queue + worker | Sem garantia de processo persistente em produção; volume não justifica a complexidade. |

## 5. Convenções de nomenclatura

- Tabelas: snake_case plural (`emotion_logs`, `mood_categories`).
- Rotas nomeadas: `patient.profile`, `emotion-logs.index`, `emotion-logs.create`, `reports.export`, `reminders.index`.
- Componentes Livewire: PascalCase dentro de namespaces por domínio (`Livewire\EmotionLog\CreateWizard`).

## 6. Estratégia de testes

- **Pest** como runner (padrão moderno do Laravel).
- Feature tests dos fluxos críticos:
  - Criar registro emocional completo e parcial (sem pensamento automático).
  - Validação de máximo de 3 lembretes ativos.
  - Comando `reminders:dispatch` — idempotência (não duplica envio no mesmo dia).
  - Exportação PDF/Excel gera arquivo com o período correto.
  - Middleware de autenticação bloqueia acesso não logado a todas as rotas do app.
- Não é necessário cobertura extensa de UI/Livewire browser testing (Dusk) dado o escopo enxuto — testes de feature/HTTP bastam.

## 7. Considerações de segurança

- Login obrigatório em todas as rotas (exceto `/login`), via middleware `auth` no grupo de rotas do app.
- Registro público de novos usuários **desabilitado** (ver doc 04) — usuário único criado via seeder/comando artisan.
- CSRF padrão do Laravel/Livewire.
- Rate limiting no login (padrão do Breeze).
- `.env` nunca commitado; `APP_DEBUG=false` em produção.
- HTTPS obrigatório em produção (também requisito técnico do Web Push).
