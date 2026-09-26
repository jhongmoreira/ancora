# 06 — Registro Emocional (core feature)

Esta é a funcionalidade central do Âncora — deve ser rápida, agradável e mobile-first.

## 1. Wireframe dos passos

Fluxo em wizard de até 5 passos (os 2 últimos combináveis com pular etapa):

```
Passo 1 — Humor                Passo 2 — Sentimentos          Passo 3 — Situação
┌───────────────────┐          ┌───────────────────┐          ┌───────────────────┐
│ Como você está?    │          │ O que você sentiu? │          │ O que aconteceu?   │
│                    │          │ (multi-seleção)    │          │                    │
│ [🙂 Agradável]     │          │ [Ansioso] [Alegre] │          │ [ textarea________]│
│ [😐 Neutro]        │   -->    │ [Triste] [Surpreso]│   -->    │                    │
│ [🙁 Desagradável]  │          │ [Amoroso] ...      │          │       [Próximo]    │
│                    │          │                    │          │                    │
│ Intensidade (opc.) │          │       [Próximo]    │          │                    │
│ [1 2 3 4 5]        │          │                    │          │                    │
└───────────────────┘          └───────────────────┘          └───────────────────┘

Passo 4 — Ação                 Passo 5 — Pensamento (opcional)
┌───────────────────┐          ┌───────────────────────────┐
│ O que você fez?    │          │ Qual pensamento passou    │
│                    │          │ pela sua cabeça? (opcional)│
│ [ textarea________]│   -->    │ [ textarea________]       │
│                    │          │                            │
│  [Voltar] [Salvar] │          │ [Pular] [Voltar] [Salvar] │
└───────────────────┘          └───────────────────────────┘
```

O passo 5 tem dois botões de saída equivalentes a "concluir": **Pular** (salva sem `automatic_thought`) e **Salvar** (salva com o texto preenchido). O passo 4 (Ação) também permite salvar diretamente sem passar pelo passo 5, para quem quer o fluxo mínimo de 4 passos.

## 2. Componente Livewire multi-step

`app/Livewire/EmotionLog/CreateWizard.php`, com propriedade pública `$step` (1 a 5) e propriedades para cada campo. Cada "Próximo" valida apenas os campos do passo atual (validação incremental via `$this->validateOnly()`), evitando frustrar o usuário com erros de passos futuros.

Estado mantido em memória do componente durante o wizard; só persiste no banco (via `EmotionLogService::create()`) ao clicar em "Salvar" no último passo alcançado.

## 3. Catálogo humor/sentimentos

Carregado uma vez do banco (`MoodCategory::with('feelings')->orderBy('order')->get()`, cacheado em cache de aplicação já que o catálogo muda raramente) e usado tanto no Passo 1 (categorias) quanto no Passo 2 (lista de sentimentos filtrada pela categoria escolhida no passo 1 — ver doc 00 seção 8 para os valores seed).

## 4. Campo intensidade (opcional)

Exibido no Passo 1, abaixo da seleção de humor: escala de 1 a 5 (botões numerados ou slider), com um botão/estado "não informar" — campo `nullable`, não bloqueia avanço do wizard.

## 5. Campo pensamento automático (opcional)

Passo 5, totalmente pulável (ver seção 1). Texto de apoio na tela explicando brevemente o conceito para quem não conhece: *"Pensamento automático é aquele pensamento rápido que passou pela sua cabeça diante da situação — é opcional, mas costuma ajudar bastante na terapia."*

## 6. Data/hora do evento

Campo `occurred_at`, com valor padrão `now()` preenchido automaticamente e exibido de forma discreta no topo do wizard (ex.: "Registrando para: hoje, 14:32 — [alterar]"), com um link/botão que abre um seletor de data/hora caso o paciente queira registrar algo que aconteceu antes (ex.: relembrando à noite algo que sentiu de manhã).

## 7. Seleção múltipla de sentimentos

Passo 2 usa botões tipo "chip" com estado toggle (selecionado/não selecionado), sem limite de quantidade, mas com validação de **mínimo 1 sentimento selecionado** para avançar.

## 8. Persistência (Service + transação)

`EmotionLogService::create(Patient $patient, array $data): EmotionLog`:
1. Abre transação DB.
2. Cria o `EmotionLog` com os campos escalares (`mood_category_id`, `occurred_at`, `intensity`, `situation`, `action`, `automatic_thought`).
3. Sincroniza a pivot de sentimentos via `$log->feelings()->sync($data['feeling_ids'])`.
4. Commit.

`EmotionLogService::update()` segue o mesmo padrão para edição.

## 9. Edição/exclusão

A partir do histórico (doc 07), cada registro tem ações "Editar" (reabre o mesmo componente Livewire, pré-preenchido, sem o conceito de "passos" — formulário único de edição, já que não é mais o momento de registro espontâneo) e "Excluir" (com confirmação simples, hard delete — não há requisito de soft delete/auditoria neste app pessoal).

## 10. Feedback pós-registro

Ao salvar, mostrar uma confirmação simples (toast/mensagem) e oferecer atalho "Fazer outro registro" ou "Ver histórico" — sem redirecionar automaticamente para longe, já que o paciente pode querer registrar mais de uma emoção em sequência.

## 11. Acessibilidade / mobile-first

- Alvos de toque grandes (mínimo 44x44px) nos botões de humor e sentimentos.
- Não depender só de cor para indicar seleção (usar borda + ícone de check).
- Textarea de situação/ação com `autofocus` no passo correspondente.
- Testado em viewport de celular como layout primário; desktop centraliza o wizard num card de largura máxima (~480px) para não esticar demais o fluxo.
