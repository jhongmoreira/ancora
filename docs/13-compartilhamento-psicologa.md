# 13 — Compartilhamento com a Psicóloga

Módulo adicional (solicitado após o MVP inicial, antes do deploy): permite ao
paciente gerar um link temporário, protegido por PIN, para a psicóloga
acompanhar o dashboard e o histórico **somente leitura** — sem precisar de
login/conta própria no Âncora.

## 1. Modelo de dados

| Tabela | Campos principais | Notas |
|---|---|---|
| `share_links` | `patient_id` (único), `token`, `pin_hash`, `expires_at` | 1 link por paciente. Gerar de novo **substitui** o registro existente (`updateOrCreate` por `patient_id`), invalidando o token antigo imediatamente. |
| `share_link_accesses` | `patient_id`, `ip_address`, `user_agent`, `accessed_at` | Ligado ao **paciente**, não ao `share_link` — o histórico de acessos sobrevive a uma revogação/regeneração do link. |

- `token`: string aleatória de 48 caracteres (`Str::random(48)`), usada na URL — inadivinhável por força bruta.
- `pin_hash`: PIN de 6 dígitos numéricos, armazenado com `Hash::make()` (bcrypt), nunca em texto puro. O PIN em texto só existe na memória logo após a geração, para exibição única ao paciente.
- Sem tabela de "sessão de acesso" separada: a verificação do PIN grava uma flag na sessão HTTP padrão do Laravel (`share_access.{token}`), válida enquanto a sessão do navegador durar ou até o link expirar — o que vier primeiro.

## 2. Fluxo do paciente (`/compartilhar`, autenticado)

`ShareLinkManager` (Livewire): gera/regenera o link definindo uma data/hora de expiração (padrão: hoje às 23:59, via `ShareLinkService::defaultExpiration()`), exibe o PIN em texto **uma única vez** logo após gerar, mostra o status do link atual (ativo/expirado) e permite revogar antecipadamente. Também lista o **histórico de acessos** (data/hora, dispositivo/navegador detectado a partir do user agent, IP).

## 3. Fluxo da psicóloga (público, sem login)

1. `GET /compartilhado/{token}` — tela de PIN (`ShareAccessController::showPin`). Token inexistente ou expirado → tela "link inválido".
2. `POST /compartilhado/{token}` — verifica o PIN (`verifyPin`):
   - Rate limit: **3 tentativas / 30 minutos** por `token+IP` (`RateLimiter`), evitando força bruta sobre o espaço de 1 milhão de combinações.
   - PIN correto → grava a flag de sessão, registra um `ShareLinkAccess` (IP + user agent) e redireciona para o dashboard.
3. `GET /compartilhado/{token}/dashboard` e `/historico` — protegidas pelo middleware `share.access` (alias de `EnsureShareLinkAccess`), que reconfere a cada request se o link ainda existe e não expirou (não basta ter validado o PIN uma vez — um link revogado/expirado *depois* da verificação também bloqueia o acesso na próxima request).

## 4. Reuso dos componentes Dashboard/Histórico

Em vez de duplicar a lógica de agregação/filtros, os componentes `Dashboard` e `EmotionLog\History` (docs/07, docs/09) foram generalizados:

- Aceitam um `?Patient $patient` opcional no `mount()` — quando omitido, resolvem `Auth::user()->patient` (comportamento original, inalterado para o paciente logado); quando informado, operam sobre esse paciente (usado pela visão compartilhada).
- Aceitam `bool $readOnly = false`. Quando `true`: os botões "Novo registro" ficam ocultos, e **os métodos de mutação (`confirmDelete`, `delete`) retornam sem efeito no servidor** — a UI escondida não é a única defesa, já que uma chamada direta ao componente Livewire (fora da UI) também é bloqueada.
- A view compartilhada usa um layout próprio (`layouts/share.blade.php` / `<x-share-layout>`), sem a navegação da área logada (registrar, lembretes, etc.), com um aviso fixo "Acesso compartilhado — somente leitura".

## 5. Decisões de segurança

- PIN sempre hasheado; nunca gravado nem logado em texto puro após a geração.
- Token longo e aleatório na URL — o PIN é uma segunda camada, não a única proteção.
- Rate limiting por token+IP nas tentativas de PIN.
- Verificação de expiração a cada request às rotas protegidas (não só no momento do PIN), então revogar/expirar tem efeito imediato mesmo com a sessão já autenticada.
- Somente leitura garantido no servidor, não só escondendo botões na UI.
- Histórico de acesso (IP, dispositivo, data/hora) dá visibilidade ao paciente sobre quem usou o link — dado sensível (diário emocional), então essa transparência foi priorizada mesmo no escopo enxuto do app.

## 6. Fora de escopo (por ora)

- Múltiplas psicólogas/múltiplos links simultâneos por paciente (hoje é 1:1).
- Notificar o paciente automaticamente quando o link é acessado (o histórico é consultado sob demanda, não há push/e-mail).
- Log de tentativas de PIN incorretas (só o acesso bem-sucedido é registrado).
