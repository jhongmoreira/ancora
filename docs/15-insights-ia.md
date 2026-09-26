# 15 — Insights com IA (Gemini)

> **Status: implementado** (branch `gemini-ai-report`). A seção 11 lista onde a implementação detalhou este plano.

Relatório gerado por IA a partir dos registros de um **período escolhido pelo paciente** (padrão: última quinzena), do registro emocional e do mapa de compulsões, salvo no banco e exibido numa tela "Insights". O **paciente** gera; a **psicóloga** só visualiza, pelo link compartilhado (doc 13).

## 1. Restrições e decisões já tomadas

### 1.1 Termos da API do Gemini (camada gratuita)

Segundo os [termos da Gemini API](https://ai.google.dev/gemini-api/terms):

- Na camada gratuita, *"Google uses the content you submit to the Services and any generated responses to provide, improve, and develop Google products"*, e *"human reviewers may read, annotate, and process your API input and output"*.
- *"Do not submit sensitive, confidential, or personal information to the Unpaid Services."*
- Os serviços não podem ser usados *"in clinical practice, to provide medical advice"*.

**Decisão (usuário, 26/09/2026):** seguir na camada gratuita com **anonimização** antes do envio e **consentimento explícito** do paciente antes do primeiro relatório (seção 4). O relatório é enquadrado como **resumo descritivo dos próprios registros do paciente**, sem diagnóstico nem recomendação de tratamento (seção 5.3).

- **Risco residual assumido:** o texto livre (situações, pensamentos) continua sendo dado de saúde, mesmo sem nomes.
- **Saída mais segura:** ativar faturamento no Google Cloud. Com billing, o Google não usa os dados para treino. O custo de um relatório quinzenal é de frações de centavo, e a troca é só de configuração.

### 1.2 Outras decisões

- **Histórico completo:** cada relatório fica salvo com o seu período. A psicóloga vê o mais recente e pode abrir os anteriores para comparar quinzenas.
- **Período escolhível** (mudança pedida pelo usuário em 26/09/2026, substituindo o período fixo de 15 dias):
  - as opções são as mesmas do mapa de compulsões: últimos 7 dias, **última quinzena (padrão)**, últimos 30 dias, últimos 90 dias, este mês e personalizado (De/Até);
  - editar uma data muda a opção para "Personalizado";
  - no personalizado, o fim não pode estar no futuro, o início vem antes do fim, e o período tem no máximo **90 dias**, para manter o relatório focado e o envio pequeno;
  - a opção escolhida (`period_preset`) e as datas resolvidas (`period_start`, `period_end`) ficam salvas em cada relatório.
- **Sem fila:** o projeto roda com `QUEUE_CONNECTION=sync` (doc 01). A geração é síncrona, com uma tela de carregamento caprichada (seção 6.2).

## 2. Modelo e API

- **Chamada:** HTTP direto (`Illuminate\Support\Facades\Http`), sem SDK, contra `generativelanguage.googleapis.com/v1beta/models/{model}:generateContent`, com a chave no header `x-goog-api-key`.
- **Configuração** em `config/services.php`: `gemini.key` (`GEMINI_API_KEY`, já configurada), `gemini.model` (`GEMINI_MODEL`, padrão `gemini-flash-latest`) e `gemini.fallback_model` (`gemini-flash-lite-latest`, usado em caso de 429/503).
  - Modelos Flash e Flash-Lite estão disponíveis para a chave (listados via API em 26/09/2026). O Pro saiu da camada gratuita em 2026.
- **Saída estruturada:** `responseMimeType: application/json` + `responseSchema` (seção 5.2). Usar `temperature` 0.3, para respostas mais estáveis e menos invenção.
- **Resiliência:** timeout de 60s, 2 retentativas com backoff em 429/5xx e fallback para o modelo Lite. Guardar `usageMetadata` (tokens) de cada chamada.
- **Limites da camada gratuita:** da ordem de 10–15 req/min e centenas de req/dia nos modelos Flash. Alguns relatórios por semana ficam muito abaixo disso.

## 3. Dados enviados (só o período escolhido)

`InsightDataBuilder` monta duas partes.

### 3.1 Estatísticas determinísticas (calculadas em PHP)

Os números mostrados na tela vêm **daqui, não da IA**, o que evita números "alucinados". A IA recebe essas estatísticas só como contexto. Sempre que possível, reaproveitar o que já existe:

- **Registro emocional** (`App\Livewire\Dashboard`: `chartData`, `consistencyData`, `streakData`, `heatmapData`, `triggersData`, `recoveryData`):
  - total, dias com registro e sequência;
  - distribuição de humor e sentimentos mais frequentes;
  - intensidade média;
  - mapa de calor (dia da semana × período);
  - palavras-gatilho;
  - tempo médio de recuperação.
- **Compulsões** (`App\Livewire\Compulsion\History`: `summary`, `patterns`):
  - resistiu × cedeu por compulsão e vontade média;
  - sentimentos antes e depois;
  - horário e dia da semana.
- **Refatoração:** extrair esses cálculos para classes de serviço (ex.: `EmotionStats`, `CompulsionStats`) usadas pelos componentes Livewire **e** pelo relatório, sem duplicar lógica.

### 3.2 Registros individuais (texto livre)

Cada registro vai com um **id curto** (`E12`, `C7`), dia relativo ("dia 3 de 15, 23h") e os campos de texto:
- **Registro emocional:** situação, ação e pensamento automático.
- **Compulsão:** gatilho, pensamento, estratégia e observações.

### 3.3 Anonimização (`InsightAnonymizer`)

Aplicada antes do envio:

- **Nunca enviar:** nome, e-mail, contato ou data de nascimento do paciente, nome da psicóloga, datas absolutas, IP ou token.
- **Nos textos:**
  - trocar por `[nome]` as partes do nome do paciente e da psicóloga;
  - trocar por `[email]`, `[telefone]`, `[documento]` e `[link]` os e-mails, telefones, CPF/RG, URLs e números longos;
  - trocar por `[nome]` palavras capitalizadas no meio da frase que não estejam numa lista de exceções (ex.: dias da semana e palavras comuns).
- **Limitação:** a heurística pode deixar passar nomes escritos em minúsculas. Isso é explicado no termo de consentimento.
- **Transparência:** o pacote anonimizado exato fica salvo no relatório (`payload`), para o paciente poder conferir "o que foi enviado".

### 3.4 Mínimo de dados

Com menos de **3 registros** no período, não gera: mostra "Registre um pouco mais para gerar insights".

## 4. Consentimento

- Antes do primeiro relatório, um modal explica:
  - o que é enviado (textos anonimizados + estatísticas) e o que não é;
  - que o serviço é gratuito, que o Google pode usar o conteúdo para melhorar os produtos dele e que pessoas podem revisá-lo;
  - que o relatório é gerado por IA, pode conter erros e não substitui a psicóloga.
- A aceitação grava `patients.ai_consent_at`. O consentimento pode ser revogado em "Meus dados"; sem ele, o botão de gerar fica bloqueado. Os relatórios já gerados continuam salvos.

## 5. O relatório

### 5.1 Conteúdo (critérios da TCC)

Baseado no automonitoramento e na análise funcional da TCC, que já fundamentam os docs 06 e 14 ([Psychology Tools — Self-monitoring](https://www.psychologytools.com/professional/techniques/self-monitoring), [Blueprint — Thought records](https://www.blueprint.ai/blog/cbt-thought-record-using-cbt-thought-records-in-clinical-practice---a-therapists-guide-to-cognitive-restructuring)):

| Seção | O que traz | Utilidade para a psicóloga |
|---|---|---|
| **Visão geral** | 2–3 frases sobre o período | Leitura rápida antes da sessão |
| **Padrões identificados** | Relações recorrentes (ex.: "ansiedade nas manhãs de segunda ligada ao trabalho"), cada uma com os ids dos registros que a sustentam | Hipóteses para a formulação do caso, verificáveis nos registros |
| **Ciclo mais frequente** | Situação → pensamento → emoção → comportamento típico do período | O modelo cognitivo aplicado aos dados do paciente |
| **Pensamentos automáticos** | Citações literais + *possível* distorção cognitiva (catastrofização, leitura mental, tudo-ou-nada, rotulação, "deveria", personalização, filtro mental, desqualificar o positivo, raciocínio emocional, generalização) | Material para reestruturação cognitiva ([Psychology Tools — Cognitive distortions](https://www.psychologytools.com/resource/cognitive-distortions-self-monitoring-record)) |
| **Gatilhos e situações de alto risco** | Contextos que antecedem emoções desagradáveis e impulsos | Prevenção de recaída |
| **Compulsões** | Leitura do ciclo (antes → ato → depois), o que ajudou a resistir, evolução resistiu × cedeu | Reforçar as estratégias que funcionam |
| **Estratégias de enfrentamento** | O que funcionou × o que pareceu pouco efetivo (a partir das ações e estratégias) | Treino de habilidades |
| **Reconhecimentos** | Progressos e pontos fortes (constância, impulsos resistidos, recuperação mais rápida) | Reforço positivo, tom acolhedor |
| **Para levar à sessão** | 3–5 perguntas ou temas abertos para explorar | Pauta colaborativa da sessão |
| **Limitações** | Poucos registros, dias sem registro, dados ambíguos | Calibrar a confiança na leitura |

### 5.2 Formato da resposta (`responseSchema`)

JSON com as chaves:
- `visao_geral`;
- `padroes[] {titulo, descricao, evidencias[]}`;
- `ciclo {situacao, pensamento, emocao, comportamento}`;
- `pensamentos[] {citacao, possivel_distorcao, explicacao, registro}`;
- `gatilhos[] {descricao, emocoes[]}`;
- `compulsoes {ciclo, alto_risco, o_que_ajudou}`;
- `estrategias {funcionaram[], pouco_efetivas[]}`;
- `reconhecimentos[]`;
- `perguntas_sessao[]`;
- `limitacoes[]`.

**Validação no servidor:**
- descartar evidências e registros com ids que não existem no pacote enviado (contra alucinação);
- só aceitar distorções da lista fechada;
- se o JSON vier inválido, o relatório fica como `failed`, com a mensagem de erro, e o paciente pode tentar de novo.

### 5.3 Prompt (`resources/prompts/insights.md`, versionado)

- Papel: assistente de apoio ao **automonitoramento em TCC**. Resume e organiza os registros e **não faz diagnóstico**, não sugere medicação nem tratamento.
- Linguagem de hipótese ("pode indicar", "possível"), português do Brasil, tom acolhedor e sem julgamento (mesmo princípio do doc 14).
- Usar **somente** os dados fornecidos e citar os ids como evidência. Os registros vão entre delimitadores e são tratados como dados, nunca como instruções.
- O `prompt_version` é salvo em cada relatório.

### 5.4 Alerta de risco (não depende da IA)

LLMs erram na detecção de risco, sobretudo na ideação passiva ([revisão](https://arxiv.org/html/2510.27521v1)). Por isso:

- `RiskDetector` em PHP procura expressões de risco (ex.: "me matar", "suicídio", "não quero mais viver", "sumir de vez", "me machucar") nos textos **originais** do período.
- Se encontrar:
  - o relatório é marcado com `risk_flag`;
  - o paciente vê no topo um banner acolhedor com o **CVV (188, 24h)** e o SAMU (192);
  - a psicóloga vê um destaque "Atenção: registros com conteúdo de risco", com os ids dos registros.
- A IA **não** é usada para decidir isso.

## 6. Telas

### 6.1 Paciente — `/insights` (`insights.index`, item "Insights" no menu)

- **Barra de ações:** seletor de período + campos De/Até + botão **"Gerar relatório"**.
- **Relatório mais recente**, em cartões:
  1. **Faixa de aviso:** "Gerado por IA em 26/09 às 14:32 — pode conter erros; converse sobre ele com sua psicóloga".
  2. **Banner de risco**, se houver.
  3. **Números do período:** blocos de indicadores com os dados determinísticos (registros, dias registrados, humor predominante, intensidade média, resistiu × cedeu).
  4. **Visão geral:** texto em destaque.
  5. **Ciclo mais frequente:** diagrama em 4 etapas (situação → pensamento → emoção → comportamento), com setas, empilhado no celular.
  6. **Padrões:** cartões com etiquetas de evidência ("E12", "C7") que abrem o registro citado.
  7. **Pensamentos automáticos:** citações com a etiqueta da possível distorção e uma explicação curta.
  8. **Compulsões e estratégias:** duas colunas, "o que ajudou" (verde) e "pouco efetivo" (neutro).
  9. **Reconhecimentos:** cartão verde.
  10. **Para levar à sessão:** lista numerada.
  11. **Limitações:** texto discreto no fim.
- **Histórico:** lista dos relatórios anteriores (período + data de geração) para abrir e comparar.
- **"Ver o que foi enviado":** mostra o pacote anonimizado (transparência).

### 6.2 Geração (UX)

- Clicar em "Gerar" desabilita o botão e mostra uma tela de carregamento com etapas ("Reunindo seus registros…", "Anonimizando…", "Analisando padrões…"), porque a chamada leva de 10 a 30s.
- **Controle de uso:** no máximo 1 geração a cada 5 minutos e 10 por dia por paciente, via `RateLimiter`, com uma mensagem amigável.
- **Em caso de erro** (cota esgotada, timeout, JSON inválido): mensagem clara e "Tentar novamente". O relatório fica salvo como `failed`.

### 6.3 Psicóloga — `/compartilhado/{token}/insights` (`share.insights`)

- Nova aba "Insights" no menu compartilhado, com título "Insights" e a mesma faixa de informações (doc 13).
- Mesmo componente em modo **somente leitura** (`readOnly`, `GuardsSharedAccess`): **sem botão de gerar** e sem "ver o que foi enviado".
- A ação de gerar também é bloqueada no servidor quando `readOnly` (e `#[Locked]`), não só escondida na tela.
- Sem relatório ainda: "O paciente ainda não gerou insights."

## 7. Modelo de dados

- **`insight_reports`:**
  - identificação e período: `patient_id` (cascade), `period_preset` (opção do seletor), `period_start`, `period_end`;
  - estado: `status` (`completed`/`failed`), `model`, `prompt_version`;
  - conteúdo: `stats` (json, os números determinísticos), `payload` (json, o pacote anonimizado enviado), `content` (json, a resposta validada);
  - risco e uso: `risk_flag` (bool), `risk_record_ids` (json), `usage` (json, tokens);
  - erro: `error` (nullable);
  - `timestamps`.
- **`patients.ai_consent_at`** (nullable).

## 8. Fases de implementação

1. **Infraestrutura:** config, `GeminiClient` (HTTP, retentativa, fallback), migration e model `InsightReport`.
2. **Dados:** extrair `EmotionStats`/`CompulsionStats` dos componentes (sem mudar o comportamento, garantido pelos testes atuais), `InsightDataBuilder`, `InsightAnonymizer` e `RiskDetector`.
3. **Geração:**
   - `InsightReportService::generate()`, que orquestra consentimento, limites, mínimo de dados, montagem, chamada, validação e gravação;
   - prompt versionado e `responseSchema`.
4. **Tela do paciente:** componente Livewire `Insights\Report` + views, item no menu, modal de consentimento, carregamento e histórico.
5. **Tela da psicóloga:** rota, controller, aba no menu compartilhado e modo somente leitura.
6. **Fechamento:** testes, este doc atualizado de "plano" para implementado, rebuild do `public/build`.

## 9. Testes (Pest, sem chamar a API real)

- Todas as chamadas usam `Http::fake()` com respostas do Gemini (sucesso, 429 com fallback, 500, JSON inválido, timeout).
- **Montagem dos dados:**
  - só entram registros do período (bordas incluídas);
  - as opções de período resolvem para as mesmas datas das outras telas;
  - o personalizado é validado (datas ausentes, invertidas, no futuro ou acima de 90 dias);
  - só do próprio paciente.
- **Anonimização:** o nome do paciente e da psicóloga, e-mail e telefone nunca aparecem no corpo enviado (checado no request capturado pelo `Http::fake`).
- **Validação:** ids de evidência inexistentes são descartados, distorções fora da lista são rejeitadas, e um JSON inválido gera um relatório `failed`.
- **Risco:** o `RiskDetector` marca os textos de risco e ignora falsos positivos óbvios ("morrendo de rir").
- **Permissões:**
  - o paciente sem consentimento não gera;
  - com menos de 3 registros não gera;
  - o limite de 5 minutos e o de 10 por dia são respeitados;
  - a psicóloga vê o relatório, mas a ação de gerar não faz nada em `readOnly`;
  - um link revogado ou expirado bloqueia a aba.
- **Telas:** as rotas novas entram no teste de páginas (`AppPagesSmokeTest`).

## 10. Riscos em aberto

- **Termos do Gemini:** a cláusula de "clinical practice" e o uso dos dados na camada gratuita (seção 1.1). O enquadramento descritivo e o consentimento reduzem o risco, mas não o eliminam. Com billing, a questão dos dados se resolve.
- **Alucinação:** mitigada pelos números determinísticos, pela exigência de evidências verificadas, pela linguagem de hipótese e pelo aviso visível.
- **Tempo de execução em produção** (doc 11): a chamada síncrona pode esbarrar no `max_execution_time` da hospedagem. Usar `set_time_limit` na ação e timeout de 60s. Se for insuficiente, migrar para job + polling do Livewire.
- **Modelos mudam:** o nome do modelo é configurável por `.env`, para trocar sem deploy de código.

## 11. Notas da implementação

- **Onde está o código:**
  - serviços em `app/Services/Insights` (`GeminiClient`, `InsightDataBuilder`, `InsightAnonymizer`, `RiskDetector`, `InsightSchema`, `InsightReportService`);
  - estatísticas em `app/Services/Stats` (`EmotionStats`, `CompulsionStats`, extraídas do dashboard e do mapa, que agora delegam para elas);
  - tela em `App\Livewire\Insights\Report`;
  - prompt em `resources/prompts/insights.md` (`PROMPT_VERSION` em `InsightReportService`).
- **Ids das evidências:** são os ids do banco com prefixo (`E` para registro emocional, `C` para compulsão). Ao tocar num código, a tela mostra o registro **original** (não o anonimizado), já que tanto o paciente quanto a psicóloga têm acesso a ele.
- **Distorções:** além da lista da seção 5.1, entrou o **pensamento permissivo** ("só dessa vez", "eu mereço"), do modelo cognitivo das dependências, útil para os registros de compulsão.
- **Consentimento:** é dado no modal da tela de Insights. "Meus dados" mostra quando foi dado e permite retirá-lo (`App\Livewire\Insights\Consent`).
- **Visão da psicóloga:** vê só relatórios concluídos; tentativas com falha aparecem apenas para o paciente.
- **Menu compartilhado:** com a aba Insights são 4 links. Em telas de até ~340px, a linha rola na horizontal em vez de cortar o primeiro item.
- **Filtros de segurança do Gemini:** configurados em `BLOCK_ONLY_HIGH`, porque registros de compulsão sexual e de sofrimento emocional esbarravam nos filtros padrão.
- **Teste real (26/09/2026, dados de demonstração):** ~20s e ~8 mil tokens por relatório. O modelo principal recusou a primeira chamada e o reserva (Flash-Lite) respondeu, que é o caso coberto pelo fallback.

