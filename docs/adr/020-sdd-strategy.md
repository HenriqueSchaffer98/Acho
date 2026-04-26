# ADR-020: Estratégia de Documentação SDD

## Status

`Aceita`

**Data:** 2026-04-25
**Autor:** Equipe Fundadora

---

## Contexto

A documentação é frequentemente o ponto de falha em projetos solo founder. Sem time, é fácil deslizar para "o código é a documentação" — o que funciona até o dia em que se precisa lembrar do "porquê" de uma decisão tomada 6 meses atrás.

Adicionalmente, o projeto usará Claude Code (assistente de IA) como copiloto de desenvolvimento. A qualidade do output da IA depende diretamente da qualidade do contexto fornecido. Documentação estruturada vira gasolina para a IA.

A escolha precisa equilibrar:

- **Disciplina** (ser realmente seguida)
- **Leveza** (não burocrática)
- **IA-friendly** (legível por LLMs)
- **Hierarquia clara** (resolver conflitos)
- **Versionada** (parte do repositório)

Existem duas abordagens populares:

1. **Spec-Driven Development (SDD) clássico** — Markdown estruturado em pastas
2. **GitHub Spec Kit** — Tooling completo com geração automática

Para um projeto solo founder, a escolha mais simples geralmente vence pela disciplina.

---

## Decisão

Adotar **SDD clássico** baseado em markdown estruturado, com hierarquia de verdade clara e formato otimizado para uso com Claude Code. Spec Kit fica como evolução possível na v2.

### Detalhamento

```
Estrutura de Pastas
─────────────────────────────────────────

docs/
├── vision/
│   ├── 01-product-vision.md
│   ├── 02-business-model.md
│   ├── 03-target-audience.md
│   └── 04-roadmap.md
│
├── adr/
│   ├── _template.md
│   ├── README.md (índice)
│   ├── 001-database-strategy.md
│   ├── 002-tenancy-model.md
│   └── ...
│
├── conventions/
│   ├── 01-architecture.md
│   ├── 02-folder-structure.md
│   ├── 03-naming.md
│   ├── 04-database.md
│   ├── 05-api-design.md
│   ├── 06-frontend-patterns.md
│   ├── 07-error-handling.md
│   └── 08-testing.md
│
├── specs/
│   ├── _template.md
│   ├── 001-cadastro-imobiliaria.md
│   ├── 002-login.md
│   ├── 003-cadastro-imovel.md
│   └── ...
│
├── runbooks/
│   ├── deploy.md
│   ├── rollback.md
│   ├── incident-response.md
│   └── backup-restore.md
│
└── claude/
    ├── CLAUDE.md (entry point para a IA)
    ├── prompts/
    │   ├── new-feature.md
    │   ├── refactor.md
    │   └── debug.md
    └── examples/
        └── (snippets de código exemplares)
```

```
Hierarquia de Verdade
─────────────────────────────────────────

Quando há conflito, a ordem é:

1. ADR (Architecture Decision Records)
   └── Decisões arquiteturais
   └── IMUTÁVEIS após aceitas
   └── Conflito: ADR vence

2. CONVENÇÕES (Conventions)
   └── Padrões de implementação
   └── Mutáveis (com PR)
   └── Conflito com Spec: convenção vence

3. SPECS (Feature Specifications)
   └── Detalhes de cada feature
   └── Geradas via SDD para cada feature
   └── Conflito com código: spec vence

4. CÓDIGO
   └── Implementação concreta
   └── Reflete tudo acima

Mantra:
  "Se contradiz uma ADR, a ADR está errada
   ou o código está errado.
   Resolver explicitamente, não ignorar."
```

```
Visão (Vision Documents)
─────────────────────────────────────────

Documentos curtos (1-2 páginas) que respondem:

01-product-vision.md
  ├── O que estamos construindo
  ├── Para quem
  ├── Por que existe
  └── Norte estratégico

02-business-model.md
  ├── Como geramos receita
  ├── Estrutura de planos
  ├── Estratégia de pricing
  └── Métricas chave

03-target-audience.md
  ├── Personas detalhadas
  ├── Imobiliária-tipo
  ├── Casos de uso
  └── Não-clientes (quem NÃO é foco)

04-roadmap.md
  ├── MVP escopo
  ├── v2 (próximos passos)
  └── Backlog de longo prazo
```

```
ADRs (Architecture Decision Records)
─────────────────────────────────────────

Já estabelecido nesta pasta.
Template em _template.md.
README.md como índice.

Características:
  ├── Imutáveis após aceitas
  ├── Numeradas sequencialmente
  ├── Substituições geram nova ADR
  └── Conflito explícito (Substitui/Substituída por)
```

```
Convenções (Conventions)
─────────────────────────────────────────

Padrões de COMO implementar (vs ADR que define O QUE):

01-architecture.md
  └── Camadas: Controller → Request → Service → Model
  └── Ver ADR-025 (Project Patterns)

02-folder-structure.md
  └── Onde cada tipo de arquivo vai

03-naming.md
  └── Convenções de nomes (classes, métodos, variáveis)
  └── snake_case vs camelCase
  └── Português vs inglês

04-database.md
  └── Tabelas em snake_case
  └── tenant_id obrigatório em tabelas de negócio
  └── UUIDs como PKs
  └── deleted_at para soft delete

05-api-design.md
  └── REST conventions
  └── Status codes
  └── Estrutura de responses
  └── Error handling

06-frontend-patterns.md
  └── Estrutura de componentes
  └── State management
  └── Forms

07-error-handling.md
  └── Como tratar erros (backend e frontend)
  └── Logs estruturados

08-testing.md
  └── Pyramid de testes
  └── Convenções de naming
  └── Test data builders
```

```
Specs (Feature Specifications)
─────────────────────────────────────────

Para cada feature, gerar uma spec antes de codar.

Template:

# Feature: {Nome}

## Contexto
Por que essa feature existe?

## Critérios de Aceitação
- [ ] User story 1 (Given/When/Then)
- [ ] User story 2
- [ ] User story 3

## Requisitos Funcionais
Descrição detalhada do comportamento

## Requisitos Não-Funcionais
Performance, segurança, UX

## Modelo de Dados
Tabelas, campos, relacionamentos

## API Design
Endpoints, requests, responses

## UI/UX
Telas, fluxos, estados

## Edge Cases
Casos de borda mapeados

## Considerações de Segurança
Riscos e mitigações

## Métricas
Como medir sucesso da feature

## Dependências
ADRs, outras specs, libs externas

## Plano de Testes
Cenários a cobrir
```

```
CLAUDE.md (Entry Point para IA)
─────────────────────────────────────────

Documento mestre que orienta Claude Code:

# CLAUDE.md

## Como Usar Este Repositório

Ordem de leitura para entender o projeto:

1. docs/vision/01-product-vision.md
2. docs/vision/02-business-model.md
3. docs/adr/README.md (índice de decisões)
4. docs/conventions/ (todos os arquivos)

## Antes de Implementar Algo

1. Existe spec em docs/specs/?
   ├── Sim: seguir spec
   └── Não: criar spec antes de codar

2. Sua mudança contradiz alguma ADR?
   ├── Sim: discutir, não simplesmente ignorar
   └── Não: prosseguir

3. Sua mudança contradiz alguma convenção?
   ├── Sim: ou justificar exceção, ou seguir
   └── Não: prosseguir

## Patterns e Anti-patterns

[Exemplos de código exemplar e o que evitar]

## Stack

[Ver ADR-019]

## Comandos Úteis

make up, make test, make lint, etc.
```

```
Workflow Diário com SDD
─────────────────────────────────────────

Iniciando nova feature:

1. Abrir issue/task
2. Ler ADRs relacionadas
3. Criar spec em docs/specs/{numero}-{slug}.md
4. Spec é revisada (mesmo solo, fazer self-review)
5. Implementar seguindo spec + convenções
6. Atualizar spec se algo mudar durante implementação
7. PR com link para a spec
8. Merge após CI verde

Refatoração:

1. Identificar mudança necessária
2. Atualizar convenção relevante (PR separado se for grande)
3. Implementar mudança seguindo nova convenção
4. Documentar em commit message
```

---

## Justificativa

A escolha por SDD clássico se justifica por:

1. **Simplicidade impõe disciplina** — Markdown puro, sem ferramentas
2. **Versionado no repositório** — Histórico de mudanças natural via Git
3. **Markdown é universal** — Qualquer editor, qualquer plataforma
4. **IA-friendly** — Claude Code lê markdown nativamente
5. **Não exige aprendizado** — Solo founder não tem tempo para curva de tooling

Por que não Spec Kit:
- Adiciona dependência de tooling
- Pode virar overhead se a disciplina não estiver firme
- Markdown puro funciona tão bem quanto se bem organizado
- Spec Kit pode ser adotado depois sem refatoração

---

## Alternativas Consideradas

### Alternativa A — GitHub Spec Kit Completo

- **Descrição:** Tooling com generation, validation, automação.
- **Pontos fortes:** Mais estruturado, validação automática.
- **Pontos fracos:** Curva de aprendizado, dependência de tool.
- **Por que não foi escolhida:** Markdown puro é suficiente. Pode evoluir depois.

### Alternativa B — Notion / Confluence

- **Descrição:** Documentação em ferramenta SaaS dedicada.
- **Pontos fortes:** UX rica, colaboração.
- **Pontos fracos:** Fora do repositório, custo, lock-in.
- **Por que não foi escolhida:** Documentação deve estar com o código. Versionada.

### Alternativa C — Sem Documentação Estruturada (Apenas READMEs)

- **Descrição:** README.md em cada pasta, sem ADRs/specs/conventions.
- **Pontos fortes:** Zero overhead.
- **Pontos fracos:** Esquecimento, conflitos não resolvidos, IA pobre de contexto.
- **Por que não foi escolhida:** Disciplina mínima vale o investimento.

### Alternativa D — Documentação Apenas no Código (Comentários)

- **Descrição:** PHPDoc + comentários ricos no código.
- **Pontos fortes:** Próximo ao código.
- **Pontos fracos:** Não cobre decisões arquiteturais. Contexto perdido.
- **Por que não foi escolhida:** Comentários no código são complementares, não substitutos.

---

## Consequências

### Positivas

- Disciplina simples e auto-aplicável
- Documentação versionada com o código
- IA Code (Claude Code) tem contexto rico
- Onboarding de futuro dev é direto
- Decisões arquiteturais ficam registradas
- Conflitos resolvidos pela hierarquia

### Negativas

- Manter docs sincronizadas com código exige disciplina
- Documentos podem ficar desatualizados se negligenciados
- Sem ferramentas de busca semântica no MVP
- Markdown não tem renderização rica por padrão

### Riscos

- **Risco:** Documentação ficar desatualizada (drift)
  - **Mitigação:** PR template inclui checklist "Atualizei docs?". Self-review verifica. Convenção: doc-first ou doc-as-you-go.

- **Risco:** Spec virar burocracia inútil
  - **Mitigação:** Specs proporcionais ao escopo. Feature trivial não precisa spec elaborada.

- **Risco:** Tempo gasto em docs em vez de código
  - **Mitigação:** Specs leves no MVP. ADRs apenas para decisões reais. Não escrever doc por doc.

---

## Critérios de Revisão

Esta decisão deve ser revisitada quando:

- Equipe crescer e precisar ferramentas colaborativas
- Documentação ficar grande demais para markdown puro (>200 docs)
- Spec Kit oferecer features que justifiquem migração
- Surgir necessidade de validação automática de specs

---

## Referências

- ADRs relacionadas: `ADR-018` (Git Strategy), `ADR-025` (Project Patterns)
- ADR Pattern: https://adr.github.io
- Spec-Driven Development: https://github.com/github/spec-kit

---

## Notas de Implementação

- Pasta `docs/` na raiz do repositório
- README.md em cada subpasta explica seu propósito
- Template para ADR já criado: `docs/adr/_template.md`
- Template para spec será criado: `docs/specs/_template.md`
- CLAUDE.md atualizado conforme projeto evolui
- Convenções iniciais escritas durante setup (semanas 1-2)
- ADRs já estabelecidas servem de exemplo
- Specs criadas conforme features são implementadas
- Pull Request template inclui:
  - "ADR relevante: ___"
  - "Spec relevante: ___"
  - "Atualizou docs? Sim/Não/N/A"
- VS Code workspace settings para preview de markdown
