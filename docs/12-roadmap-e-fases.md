# 12 — Roadmap e Fases

## 1. Ordem de execução

| # | Fase | Doc relacionado | Pré-requisitos | Entregável |
|---|---|---|---|---|
| 1 | Fundação | [01](./01-arquitetura-e-stack.md), [03](./03-ambiente-dev-docker.md) | Nenhum | Projeto Laravel instalado, rodando via Docker Compose local, repositório com commit inicial |
| 2 | Autenticação | [04](./04-autenticacao.md) | Fase 1 | Login funcional, usuário único criado, registro público removido |
| 3 | Modelo de dados | [02](./02-modelo-de-dados.md) | Fase 1 | Todas as migrations e seeders (catálogo humor/sentimentos) aplicados |
| 4 | Cadastro paciente | [05](./05-cadastro-paciente.md) | Fases 2, 3 | Tela "Meus dados" funcional, com vínculo de psicóloga |
| 5 | Registro emocional | [06](./06-registro-emocional.md) | Fases 3, 4 | Wizard completo (5 passos) salvando registros |
| 6 | Histórico e filtros | [07](./07-historico-filtros.md) | Fase 5 | Listagem com filtros por período/humor/sentimento |
| 7 | Gráficos e dashboard | [09](./09-graficos-dashboard.md) | Fase 5 | Dashboard com os 3 gráficos comparativos |
| 8 | Relatórios e exportação | [08](./08-relatorios-exportacao.md) | Fase 5 | Exportação PDF e Excel funcionando por período |
| 9 | PWA e notificações | [10](./10-pwa-notificacoes.md) | Fases 1, 4 | App instalável, lembretes push funcionando ponta a ponta |
| 10 | Deploy em produção | [11](./11-deploy-producao.md) | Todas anteriores | App publicado, acessível via HTTPS, com backup configurado |

## 2. Definition of Done por fase

- **Fundação**: `docker compose up` sobe o app sem erros; `php artisan migrate` roda contra o MySQL do compose; commit inicial no git.
- **Autenticação**: não é possível acessar nenhuma rota do app sem login; não existe rota de registro público acessível.
- **Modelo de dados**: `php artisan migrate:fresh --seed` recria o schema do zero com o catálogo de humor/sentimentos populado.
- **Cadastro paciente**: dados salvos persistem e aparecem corretamente no cabeçalho de um PDF de teste (mesmo antes da fase 8 estar completa, pode ser validado manualmente via tinker).
- **Registro emocional**: um registro completo (todos os 5 passos) e um registro mínimo (sem intensidade nem pensamento automático) são salvos corretamente e aparecem no banco com os relacionamentos certos.
- **Histórico e filtros**: filtrar por um período específico e por uma categoria de humor retorna exatamente os registros esperados (testado com massa de dados de exemplo).
- **Gráficos**: os 3 gráficos refletem corretamente uma massa de dados de teste conhecida (números batem manualmente).
- **Relatórios**: PDF e Excel gerados abrem corretamente e contêm todos os registros do período, com cabeçalho de paciente/psicóloga correto.
- **PWA/notificações**: checklist completo do doc 10, seção 10, executado com sucesso.
- **Deploy**: checklist completo do doc 11, seção 10, executado com sucesso em produção real.

## 3. Marcos

- **MVP mínimo (uso pessoal já viável)**: Fases 1 a 6 (fundação até histórico). Já permite registrar e consultar emoções, mesmo sem gráficos/exportação/push.
- **Versão completa (todos os requisitos do usuário)**: Fases 1 a 10.

## 4. Riscos e mitigação

| Risco | Impacto | Mitigação |
|---|---|---|
| Web Push não funcionar em algum navegador/dispositivo específico (ex.: iOS mais antigo) | Lembretes não chegam | Documentar limitações conhecidas (doc 10, seção 9); considerar fallback futuro de lembrete por e-mail se isso for crítico |
| Layout do PDF ficar ruim para impressão | Prejudica o objetivo principal (levar à psicóloga) | Testar impressão real (não só visualização em tela) antes de considerar a fase 8 concluída |
| Cron do servidor de produção não estar habilitado por padrão na hospedagem escolhida | Lembretes nunca disparam | Validar no doc 11 se o provedor de hospedagem final oferece cron de usuário antes de contratar/migrar |
| Volume de dados ao longo dos anos degradar performance de histórico/gráficos | Lentidão | Índices já definidos desde o doc 02; reavaliar apenas se performance realmente incomodar (não otimizar prematuramente) |

## 5. Backlog futuro (fora da v1)

- Suporte a múltiplos pacientes (multi-tenancy real).
- Histórico de troca de profissional por paciente (hoje só há "profissional atual").
- Insights automáticos sobre padrões (ex.: "você costuma ficar ansioso às segundas-feiras").
- Anexos/fotos no registro emocional.
- Modo escuro.
- Compartilhamento direto do PDF por e-mail a partir do próprio app.
- Fallback de lembrete por e-mail para dispositivos sem suporte a Web Push.
