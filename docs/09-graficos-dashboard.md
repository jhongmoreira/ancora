# 09 — Gráficos e Dashboard

## 1. Dashboard geral (7/30 dias)

Tela inicial após login (`dashboard`), com seletor de janela temporal (7 dias / 30 dias / mês atual) que atualiza os 3 gráficos abaixo via Livewire (os dados são recalculados no servidor a cada mudança de filtro; Chart.js apenas redesenha com os novos dados via evento JS disparado pelo Livewire).

## 2. Gráfico 1 — Evolução de humor/intensidade

Gráfico de linha no eixo X = dias do período, eixo Y = intensidade média do dia (1-5), com a cor do ponto/linha variando conforme o humor predominante do dia (ou 3 linhas separadas, uma por categoria de humor, mostrando contagem de registros por dia — decisão de implementação a refinar na fase de execução, mantendo o mais simples visualmente).

## 3. Gráfico 2 — Frequência de sentimentos

Gráfico de barras horizontais (ou pizza) mostrando a contagem de cada sentimento registrado no período, ordenado do mais frequente ao menos frequente — ajuda a identificar rapidamente "qual emoção mais apareceu esse mês".

## 4. Gráfico 3 — Distribuição por período (humor)

Gráfico de pizza/rosca simples: proporção de registros Agradável / Neutro / Desagradável no período selecionado.

## 5. Integração Chart.js + dados agregados

Os componentes Livewire calculam os dados agregados (arrays de labels/valores) em PHP e os expõem via `wire:key`/evento (`$this->dispatch('chart-updated', data: ...)`), com um pequeno script Alpine/JS no lado do Blade escutando o evento e chamando `chart.update()` — evita reconstruir o gráfico do zero a cada filtro.

## 6. Queries agregadas

Usar `EmotionLog::query()->selectRaw(...)->groupBy(...)` diretamente no banco (em vez de trazer todos os registros para agregar em PHP), aproveitando os índices `(patient_id, occurred_at)` e `(patient_id, mood_category_id)` do doc 02.

## 7. Filtros de período

Reaproveita os mesmos atalhos de período definidos no doc 07 (histórico) e doc 08 (exportação), para consistência de UX em todo o app.
