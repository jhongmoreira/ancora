# 05 — Cadastro de Paciente

## 1. Form Patient

Tela "Meus dados" (rota `patient.edit` / `patient.update`) com os campos definidos no modelo de dados (doc 02):
- Nome completo (obrigatório)
- Data de nascimento (obrigatório) — idade é exibida calculada, nunca editada diretamente
- Gênero (opcional, select curto: Feminino/Masculino/Prefiro não informar/Outro)
- Contato (opcional)
- Observações (opcional, textarea)
- Psicóloga vinculada (select da tabela `professionals`, com opção "+ Adicionar nova")

## 2. Form Professional vinculado

Pequeno sub-formulário (pode ser um modal Livewire ou uma seção na mesma tela) para cadastrar uma nova psicóloga sem sair da tela de paciente:
- Nome (obrigatório)
- Contato (opcional)
- Observações (opcional — ex.: CRP, especialidade)

Se o paciente ainda não tiver profissional vinculado, o campo fica em branco e o app funciona normalmente (profissional é opcional em `patients.professional_id`), mas a tela de exportação (doc 08) avisa que o cabeçalho do PDF ficará sem nome de psicóloga até que seja preenchido.

## 3. Form Requests

- `UpdatePatientRequest`: valida nome (string, max 150), `birth_date` (date, before today), `gender` (in: lista curta, nullable), `contact` (nullable, max 100), `notes` (nullable), `professional_id` (nullable, exists:professionals,id).
- `StoreProfessionalRequest`: valida `name` (obrigatório), `contact`/`notes` opcionais.

## 4. Tela "Meus dados"

Layout simples de formulário único (Blade + Livewire), sem necessidade de wizard — ao contrário do registro emocional (doc 06), aqui não há problema em mostrar todos os campos de uma vez, pois é uma tela de configuração acessada raramente.

## 5. Onboarding — criação automática do Patient no 1º acesso

Como o `Patient` tem relação 1:1 obrigatória com `User`, no primeiro login (ou na criação do usuário via comando artisan do doc 04) já se cria um registro `Patient` vazio associado. Isso evita uma tela de "setup wizard" separada — o usuário simplesmente é redirecionado para "Meus dados" a preencher, com uma mensagem amigável tipo "Complete seu cadastro antes de começar a registrar".

Regra simples: se `patient.full_name` estiver vazio, o middleware do grupo autenticado redireciona qualquer rota (exceto a própria tela de perfil/dados) para `patient.edit`.

## 6. Wireframe textual

```
┌─────────────────────────────┐
│ Meus dados                  │
├─────────────────────────────┤
│ Nome completo: [__________] │
│ Data de nascimento: [__/__/____] (Idade: 32 anos) │
│ Gênero: [ Select ▾ ]        │
│ Contato: [__________]       │
│ Observações: [textarea]     │
│                              │
│ Psicóloga: [ Select ▾ ] [+ Nova] │
│                              │
│           [ Salvar ]         │
└─────────────────────────────┘
```
