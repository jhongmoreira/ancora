# Âncora — Documentação de Planejamento

Diário emocional pessoal para apoiar acompanhamento em Terapia Cognitivo-Comportamental (TCC), inspirado nos apps Cogni e Psiquê. Uso: um único paciente/usuário, com exportação de registros (PDF/Excel) para levar às sessões com a psicóloga.

Esta pasta contém o planejamento completo do projeto, dividido em documentos por fase. Cada documento é autocontido e serve como especificação da fase correspondente na implementação.

## Como usar esta documentação

Implemente uma fase por vez, na ordem descrita em [`12-roadmap-e-fases.md`](./12-roadmap-e-fases.md) (que **não** é a mesma ordem numérica dos arquivos — a numeração abaixo é por tópico). Marque o checkbox quando a fase estiver concluída e validada.

## Índice

- [x] [00 — Visão Geral](./00-visao-geral.md) — propósito, escopo, princípios de design, nomenclatura.
- [x] [01 — Arquitetura e Stack](./01-arquitetura-e-stack.md) — decisões técnicas, pacotes, padrões de código.
- [x] [02 — Modelo de Dados](./02-modelo-de-dados.md) — entidades, migrations, seeders.
- [x] [03 — Ambiente de Dev (Docker)](./03-ambiente-dev-docker.md) — Docker Compose **somente para desenvolvimento local**.
- [x] [04 — Autenticação](./04-autenticacao.md) — login de usuário único, sem cadastro público.
- [x] [05 — Cadastro de Paciente](./05-cadastro-paciente.md) — dados do paciente e da psicóloga vinculada.
- [x] [06 — Registro Emocional](./06-registro-emocional.md) — o fluxo central do app (humor, sentimentos, situação, ação).
- [x] [07 — Histórico e Filtros](./07-historico-filtros.md) — consulta dos registros passados.
- [x] [08 — Relatórios e Exportação](./08-relatorios-exportacao.md) — geração de PDF e Excel por período.
- [x] [09 — Gráficos e Dashboard](./09-graficos-dashboard.md) — visualizações comparativas.
- [ ] [10 — PWA e Notificações](./10-pwa-notificacoes.md) — instalabilidade e lembretes via Web Push.
- [ ] [11 — Deploy em Produção](./11-deploy-producao.md) — publicação em servidor **sem Docker**.
- [x] [12 — Roadmap e Fases](./12-roadmap-e-fases.md) — ordem de execução, Definition of Done, riscos, backlog.

**Status atual: fases 1-8 do roadmap implementadas e testadas** (fundação, auth, modelo de dados, cadastro de paciente, registro emocional, histórico, gráficos/dashboard, relatórios PDF/Excel) — falta PWA/push e deploy.

## Decisões-chave já fechadas

- Stack: Laravel 13 + Blade + Livewire 3 + Alpine.js + MySQL.
- Docker é **exclusivo do ambiente de desenvolvimento**; produção roda em hospedagem tradicional (PHP-FPM/Apache ou Nginx + MySQL, sem containers).
- Sem filas assíncronas obrigatórias (`QUEUE_CONNECTION=sync`); sessão/cache em MySQL.
- Registro emocional tem os campos: humor (+ intensidade opcional), sentimento(s), situação, ação, pensamento automático (opcional).
- Psicóloga vinculada é uma entidade própria (`professionals`), não texto livre.
