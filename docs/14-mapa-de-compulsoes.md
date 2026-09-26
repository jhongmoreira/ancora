# 14 — Mapa de Compulsões

Módulo pós-MVP para o paciente registrar os impulsos ligados a comportamentos compulsivos (ex.: pornografia, compras, comer por impulso), tanto quando **cedeu** quanto quando **resistiu**, e para a psicóloga analisar os padrões em sessão.

## 1. Fundamentação clínica (TCC)

- **Automonitoramento e análise funcional (modelo ABC).** O registro segue o ciclo antecedente → comportamento → consequência: situação/gatilho, pensamento automático, emoção, intensidade do impulso, o que foi feito e como a pessoa ficou depois. É o que permite identificar as situações de alto risco e o que mantém o ciclo. Fontes: [Psychology Tools — Self-monitoring](https://www.psychologytools.com/professional/techniques/self-monitoring), [Psychology Tools — A-B-C model](https://www.psychologytools.com/resource/exploring-problems-using-an-a-b-c-model), [Terappia — Compulsão sob a lente da TCC](https://www.terappia.com.br/posts/compuls%C3%A3o-sob-a-lente-da-terapia-cognitivo-comportamental-(tcc)).
- **Sentimento antes *e* depois.** Os dois momentos respondem a perguntas diferentes:
  - *Antes* (antecedente): qual estado emocional dispara o impulso (ansiedade, solidão, tédio...).
  - *Depois* (consequência): o reforço de curto prazo (alívio) e, muitas vezes, culpa/vergonha, o "efeito de violação da abstinência" do modelo de prevenção de recaída de Marlatt, que alimenta o próximo episódio. Ver [Relapse prevention](https://en.wikipedia.org/wiki/Relapse_prevention).
- **Impulsos resistidos também contam.** Registrar só as recaídas dá uma visão distorcida. Os resistidos mostram progresso e revelam quais estratégias funcionam. A intensidade vai de 0 a 10 e o lapso é tratado como informação, não como fracasso ([cogbtherapy](https://cogbtherapy.com/cbt-for-addiction-treatment-los-angeles), [addictions-healing](https://www.addictions-healing.com/blog/relapse-prevention-strategies-for-problematic-sexual-behavior)). Por isso as mensagens da tela evitam julgamento.

## 2. Modelo de dados

Ver doc 02 (`compulsions`, `compulsion_logs`, `compulsion_log_feeling`). Pontos principais:

- O pivot de sentimentos tem a coluna `moment` (`before`/`after`), então o mesmo sentimento pode aparecer nos dois momentos. Por isso o `CompulsionLogService` sincroniza o pivot por momento, e não com `sync()`.
- Reaproveita o catálogo `feelings` do registro emocional, para que o vocabulário emocional seja o mesmo nos dois módulos.
- Compulsões com registros só podem ser **arquivadas**, não excluídas. Isso evita apagar dados clínicos por engano.

## 3. Fluxo do paciente

- `/compulsoes` (`compulsions.index`): cadastro das compulsões (`Compulsion\Manager`) + o mapa (`Compulsion\History`).
- `/compulsoes/registrar` (`compulsions.log`): wizard `Compulsion\LogWizard` em 3 passos, cada um validado ao avançar:
  1. Compulsão (pré-selecionada se houver só uma), data/hora (não pode ser no futuro), **Resisti/Cedi**, força da vontade 0-10.
  2. **Antes:** o que estava acontecendo (obrigatório), sentimentos (mín. 1), pensamento automático (opcional).
  3. **Depois:** sentimentos (mín. 1), duração em minutos (só se cedeu, opcional), "o que ajudou a resistir" ou "o que poderia ter feito diferente" (opcional), observações.

## 4. O mapa

`Compulsion\History`, com filtro de período (7 dias, quinzena, 30/90 dias, mês, personalizado; abre na quinzena por padrão, pensando em quem tem sessões quinzenais) e por compulsão:

- **Resumo por compulsão:** quantas vezes resistiu × cedeu, vontade média e dias desde o último "cedeu" (considera todo o histórico, não só o período).
- **Padrões do período:**
  - sentimentos mais frequentes antes (possíveis gatilhos), depois de ceder e depois de resistir;
  - distribuição por faixa horária (madrugada/manhã/tarde/noite);
  - distribuição por dia da semana, separando resistiu e cedeu.
- **Lista de registros:** mais recentes primeiro, com sentimentos antes → depois e o texto completo ao expandir. O paciente pode excluir registros.

## 5. Visão compartilhada

`/compartilhado/{token}/compulsoes` (`share.compulsions`) usa o mesmo componente com `readOnly=true` e `GuardsSharedAccess`, protegido pelo middleware `share.access` (doc 13). Não permite excluir registros e para de retornar dados assim que o link é revogado ou expira.

## 6. Próximos passos (backlog)

- Incluir o mapa no PDF/Excel de relatórios (doc 08) e um card no dashboard (doc 09).
- Lembretes específicos por compulsão (ex.: em horários de risco identificados no mapa).
- Edição de registros de compulsão (o `CompulsionLogService::update` já existe).
