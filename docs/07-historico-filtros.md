# 07 — Histórico e Filtros

## 1. Tela de listagem/timeline

Componente Livewire (`Livewire\EmotionLog\History`) que lista os registros do paciente em ordem cronológica decrescente (mais recente primeiro), agrupados visualmente por dia. Cada item mostra: horário, ícone/cor do humor, sentimentos (chips), trecho da situação (truncado), e ações rápidas (editar/excluir/ver completo).

## 2. Filtros reativos (Livewire)

- **Período**: seletor de datas (predefinidos: últimos 7 dias, últimos 30 dias, este mês, personalizado com data inicial/final).
- **Humor**: filtro multi-select por categoria (Positivo/Neutro/Negativo).
- **Sentimento**: filtro multi-select pela lista de sentimentos (dependente do catálogo, não do humor selecionado — permite cruzar, ex.: "mostre registros negativos com o sentimento Ansioso").

Todos os filtros atualizam a lista via `wire:model.live` sem reload de página, com debounce nos campos de texto (se houver busca livre — opcional, fora do escopo mínimo).

## 3. Paginação

Paginação padrão do Livewire (`WithPagination`), 20 registros por página, preservando os filtros na query string para permitir compartilhar/voltar a um filtro específico.

## 4. Agrupamento por dia

Os registros são agrupados por data (`groupBy(fn ($log) => $log->occurred_at->toDateString())`) para facilitar a leitura em forma de "diário", com um cabeçalho de data separando cada grupo (ex.: "Terça-feira, 23 de setembro").

## 5. Ações rápidas

- **Editar**: abre formulário de edição (doc 06, seção 9).
- **Excluir**: modal de confirmação simples.
- **Ver completo**: expande o item para mostrar situação/ação/pensamento automático na íntegra (já que a lista trunca texto longo).

## 6. Índices de performance

Reaproveita os índices definidos no doc 02 (`(patient_id, occurred_at)` e `(patient_id, mood_category_id)`), suficientes dado o volume esperado (uso pessoal, no máximo alguns milhares de registros ao longo de anos).
