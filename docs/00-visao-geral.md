# 00 — Visão Geral

## 1. Propósito e contexto terapêutico

O Âncora é um diário emocional digital de uso pessoal, criado para apoiar o acompanhamento em Terapia Cognitivo-Comportamental (TCC). A técnica central da TCC que o app replica é o **registro de pensamentos/emoções**: sempre que o paciente percebe uma emoção relevante, ele registra o humor, o(s) sentimento(s) específico(s), a situação que disparou a emoção e a ação tomada em resposta. Ao longo de semanas, esse histórico revela padrões comportamentais que paciente e psicóloga discutem em sessão.

O app não substitui a terapia nem tenta interpretar os dados clinicamente — ele apenas **facilita a coleta consistente e a exportação organizada** desses registros.

## 2. Persona e caso de uso único

Existe **um único usuário/paciente** (o dono do sistema). Não há necessidade de multi-tenancy, cadastro público, papéis de permissão ou área para a psicóloga logar no sistema — a psicóloga recebe os dados via PDF/Excel exportado, fora do app.

Fluxo de uso típico:
1. Durante o dia, o paciente recebe uma notificação push (até 3x/dia, horários configuráveis) lembrando de fazer um registro.
2. Abre o app (idealmente instalado como PWA na tela inicial do celular) e registra a emoção em poucos toques.
3. Periodicamente (ex.: antes de cada sessão), consulta o histórico com filtros e exporta o período em PDF ou Excel.
4. Leva o PDF impresso ou por e-mail/WhatsApp para a psicóloga, ou consulta os gráficos junto com ela na sessão.

## 3. Referências — Cogni e Psiquê

**Cogni**: aplicativo de registro de pensamentos disfuncionais usado em TCC. Fluxo: selecionar humor → escolher sentimentos de uma lista → registrar o pensamento → histórico → gráficos de emoções → envio de relatório ao terapeuta.

**Psiquê**: registra emoção, situação, pensamento e ação, formando uma série histórica usada para identificar padrões junto com o terapeuta. Foco em privacidade dos dados (criptografia, LGPD).

**O que o Âncora replica:**
- Registro estruturado de humor → sentimento(s) → situação → ação (fluxo do Psiquê).
- Campo opcional de pensamento automático (elemento clássico de TCC presente em ambos os apps).
- Histórico navegável e exportável para a psicóloga.
- Gráficos de evolução emocional.

**O que fica fora do escopo (v1):**
- Múltiplos pacientes/multi-tenancy.
- Login/acesso da psicóloga dentro do app.
- Conteúdo educativo, exercícios guiados de TCC, meditação, etc.
- Análise automática/IA sobre os registros.
- App nativo (iOS/Android) — o Âncora é um PWA acessado pelo navegador.

## 4. Princípios de design

- **Prático antes de completo**: cada tela deve resolver o essencial com o menor número de cliques/toques possível. Funcionalidades "bonitas de ter" ficam no backlog (seção 6).
- **Mobile-first**: o registro emocional acontece no dia a dia, no celular. O layout desktop é uma adaptação responsiva, não o design primário.
- **Sem fricção no momento de registrar**: o fluxo de registro (doc 06) é o coração do app — deve poder ser concluído em menos de 30 segundos para o caso simples (só humor + sentimento + situação curta).
- **Dados sensíveis, mas sem complexidade excessiva de segurança**: como há um único usuário, não é necessário RBAC, mas autenticação, HTTPS e backup são obrigatórios (é um diário emocional).

## 5. Escopo da v1

- Login de usuário único.
- Cadastro do paciente (dados pessoais) e da psicóloga vinculada.
- Registro emocional: humor + intensidade (opcional) → sentimento(s) → situação → ação → pensamento automático (opcional, pulável).
- Edição e exclusão de registros.
- Histórico com filtros por período, humor e sentimento.
- Exportação em PDF e Excel por período selecionado.
- Dashboard com 2-3 gráficos comparativos.
- PWA instalável com notificações push em até 3 horários configuráveis por dia.

## 6. Fora de escopo / backlog futuro

Ver detalhamento em [`12-roadmap-e-fases.md`](./12-roadmap-e-fases.md), seção "Backlog futuro". Resumo: múltiplos pacientes, insights automáticos sobre padrões, campo de anexos/fotos no registro, modo escuro, compartilhamento direto por e-mail do PDF.

## 7. Glossário TCC (usado no app)

- **Humor**: categoria ampla do estado emocional no momento (Agradável, Neutro, Desagradável).
- **Sentimento**: emoção específica dentro de uma categoria de humor (ex.: ansioso, alegre, triste).
- **Situação (gatilho)**: evento ou contexto que precedeu a emoção.
- **Ação**: comportamento ou resposta do paciente diante da situação/emoção.
- **Pensamento automático**: pensamento espontâneo, muitas vezes não questionado, que surge diante da situação e influencia a emoção — conceito central da TCC (Beck).
- **Intensidade**: força percebida da emoção, numa escala de 1 (fraca) a 5 (muito forte).

## 8. Nomenclatura adotada

**Categorias de humor** (simples, sem jargão clínico, para não intimidar o paciente leigo):

| Chave | Rótulo | Cor sugerida |
|---|---|---|
| `positivo` | Agradável | Verde suave |
| `neutro` | Neutro | Cinza/azulado |
| `negativo` | Desagradável | Vermelho suave (evitar tom alarmante) |

> As chaves internas continuam `positivo`/`negativo`; só os rótulos exibidos mudaram de "Positivo"/"Negativo" para "Agradável"/"Desagradável" (migration `rename_mood_category_labels_to_agradavel_desagradavel`), por soarem menos como um julgamento sobre a emoção.

**Catálogo inicial de sentimentos por categoria** (seed, editável futuramente):

- **Agradável**: Alegre, Grato, Amoroso, Animado, Orgulhoso, Aliviado, Confiante, Surpreso (positivamente)
- **Neutro**: Calmo, Indiferente, Pensativo, Cansado, Entediado
- **Desagradável**: Ansioso, Triste, Com raiva, Envergonhado, Culpado, Frustrado, Assustado, Sozinho, Surpreso (negativamente)

> Nota: "Surpreso" existe nas duas pontas porque surpresa é uma emoção neutra em valência até que o contexto a qualifique — replicado como dois itens distintos no catálogo para simplicidade (evita um mecanismo de "polaridade dependente do contexto").

## 9. Requisitos não funcionais

- **Privacidade**: dados ficam apenas no servidor do próprio usuário; nenhuma integração com terceiros. HTTPS obrigatório em produção (também exigido tecnicamente pelo Web Push).
- **Backup**: dump periódico do MySQL (ver doc 11).
- **Performance**: irrelevante em escala (um usuário), mas queries de histórico/gráficos devem ter índices adequados para não degradar com anos de dados.
- **Disponibilidade**: sem SLA formal; é aceitável indisponibilidade eventual, mas o Scheduler de lembretes deve ser resiliente a atrasos do cron (idempotência, ver doc 10).
- **Acessibilidade**: contraste adequado, alvos de toque grandes o suficiente para uso mobile, sem depender apenas de cor para diferenciar humor (usar também ícone/texto).
