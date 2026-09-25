# 08 — Relatórios e Exportação

## 1. Tela de seleção de período

Página simples (`reports.index`) com: seletor de período (mesmos atalhos do histórico — 7/30 dias, mês atual, personalizado), preview do total de registros que serão exportados, e dois botões: "Exportar PDF" e "Exportar Excel".

## 2. Geração PDF (layout, cabeçalho paciente+psicóloga)

Usa `barryvdh/laravel-dompdf`. Template Blade dedicado (`resources/views/pdf/emotion-logs-report.blade.php`) com:
- **Cabeçalho**: nome do paciente, idade (calculada), nome da psicóloga vinculada (se houver), período do relatório, data de geração.
- **Corpo**: tabela ou blocos por registro — data/hora, humor (com cor), sentimentos, intensidade, situação, ação, pensamento automático (se preenchido).
- **Rodapé**: "Gerado pelo Âncora" + número de página.

Layout pensado para impressão em A4, fonte legível (mínimo 11pt no corpo), evitando quebra de registro no meio entre páginas quando possível (`page-break-inside: avoid` no CSS do dompdf).

## 3. Rota/controller

`ExportController@pdf(Request $request)`: recebe `from`/`to` (datas), busca os `EmotionLog` do paciente autenticado no período (com eager load de `feelings`, `moodCategory`), renderiza a view e retorna via `Pdf::loadView(...)->download($filename)`.

## 4. Geração Excel (colunas)

Usa `maatwebsite/excel` com uma classe `EmotionLogsExport implements FromCollection, WithHeadings, WithMapping`. Colunas: Data, Hora, Humor, Intensidade, Sentimentos (concatenados), Situação, Ação, Pensamento automático.

`ExportController@excel(Request $request)` segue o mesmo padrão de filtro por período e delega para `Excel::download(new EmotionLogsExport($logs), $filename)`.

## 5. Performance para períodos longos

Períodos muito longos (ex.: "todo o histórico" em anos de uso) devem usar `chunk()`/`cursor()` na query de exportação para não estourar memória — improvável dado o volume de 1 usuário, mas trivial de implementar corretamente desde o início.

## 6. Nomenclatura de arquivos

Padrão: `ancora-relatorio_{paciente-slug}_{from}_a_{to}.pdf` e `.xlsx`, ex.: `ancora-relatorio_joao-silva_2026-08-01_a_2026-08-31.pdf`.
