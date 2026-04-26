# ADR-018: Estratégia de Git e CI/CD

## Status

`Aceita`

**Data:** 2026-04-25
**Autor:** Equipe Fundadora

---

## Contexto

A estratégia de versionamento e integração contínua afeta:

- Velocidade de entrega
- Qualidade do código
- Rastreabilidade de mudanças
- Capacidade de rollback
- Facilidade para outros devs entrarem no projeto

Existem duas estratégias principais de branching:

1. **GitFlow** — branches longas (develop, release, hotfix, feature)
2. **Trunk-Based Development** — branches curtas, merge frequente em main

Para um projeto solo founder em estágio inicial, sem ambiente de staging permanente (ADR-017), a escolha precisa equilibrar:

- **Velocidade** (não pode ter overhead burocrático)
- **Segurança** (não pode quebrar produção facilmente)
- **Simplicidade** (mantenível por uma pessoa)
- **Auditoria** (cada mudança rastreável)

A escolha também precisa cobrir CI/CD: como código vai do commit ao servidor.

---

## Decisão

Adotar **Trunk-Based Development** com branches de feature curtas (lifetime < 2 dias), Pull Requests obrigatórios mesmo para solo founder, squash merge em `main`, e CI/CD via **GitHub Actions** com deploy automático para produção em merge para main.

### Detalhamento

```
Estrutura de Branches
─────────────────────────────────────────

main (única branch persistente)
  ├── Sempre deployable
  ├── Proteção: PR obrigatório
  ├── Required checks: testes verdes
  └── Push direto bloqueado (mesmo para owner)

feature/{descrição-curta}
  ├── Criada a partir de main
  ├── Lifetime ideal: < 2 dias
  ├── Mais que isso → quebrar em PRs menores
  └── Deletada após merge

bugfix/{descrição-curta}
  ├── Mesmo fluxo de feature
  ├── Para correções não urgentes
  └── Mesma estrutura

hotfix/{descrição-curta}
  ├── Para correções urgentes em produção
  ├── Pode pular review se crítico
  └── Sempre seguido de post-mortem
```

```
Convenção de Mensagens de Commit
─────────────────────────────────────────

Conventional Commits:
  ├── feat: nova funcionalidade
  ├── fix: correção de bug
  ├── refactor: refatoração sem mudança comportamental
  ├── docs: documentação
  ├── test: testes
  ├── chore: tarefas de manutenção
  ├── style: formatação (sem mudança de lógica)
  └── perf: melhoria de performance

Exemplos:
  feat(imoveis): adiciona galeria de fotos
  fix(auth): corrige expiração de refresh token
  refactor(scheduling): extrai lógica para Service
  docs(adr): adiciona ADR-019 sobre stack
```

```
Fluxo de Pull Request
─────────────────────────────────────────

1. Criar branch
   └── git checkout -b feature/upload-de-fotos

2. Desenvolver com commits frequentes
   └── Commits podem ser "WIP" durante o trabalho

3. Push e abrir PR
   └── Template de PR preenchido:
       ├── O que muda
       ├── Por quê
       ├── Como testar
       └── Checklist de qualidade

4. CI roda automaticamente:
   ├── Testes (Pest)
   ├── Linting (Pint, Larastan)
   ├── Type checking (TypeScript)
   ├── Frontend lint (ESLint)
   └── Verificações de segurança

5. Self-review (mesmo solo)
   └── Olhar o próprio diff com olhos críticos

6. Merge com squash
   ├── Histórico fica limpo
   ├── Mensagem final é descrição clara
   └── Branch é deletada automaticamente

7. Deploy automático
   └── Push em main → GitHub Actions → produção
```

```
Pipeline de CI (GitHub Actions)
─────────────────────────────────────────

Em todo Pull Request:

job: lint
  ├── Pint (PHP CS Fixer)
  ├── Larastan (análise estática nível 8)
  ├── ESLint (TypeScript/React)
  └── Prettier (formatação JS/CSS)

job: test
  ├── Setup PostgreSQL container
  ├── Setup Redis container
  ├── php artisan migrate
  ├── ./vendor/bin/pest --parallel
  └── Coverage report uploaded

job: security
  ├── composer audit (vulnerabilidades PHP)
  ├── npm audit (vulnerabilidades JS)
  └── secrets scan (TruffleHog)

job: build
  ├── npm run build (vite)
  └── Verificar bundle size

Status check obrigatório para merge:
  ✅ lint
  ✅ test
  ✅ security
  ✅ build
```

```
Pipeline de CD (Deploy)
─────────────────────────────────────────

Em push para main:

1. CI completo roda novamente
       │
       ▼
2. Backup automático do banco
   └── Snapshot Neon antes de migrate
       │
       ▼
3. Build de produção
   ├── composer install --no-dev --optimize
   ├── npm ci && npm run build
   └── php artisan optimize
       │
       ▼
4. Deploy via Forge webhook
   ├── Pull do código novo
   ├── Composer install no servidor
   ├── npm install (se necessário)
   ├── php artisan migrate --force
   ├── php artisan config:cache
   ├── php artisan route:cache
   ├── php artisan view:cache
   └── Restart queue workers
       │
       ▼
5. Smoke tests pós-deploy
   ├── Health check endpoint
   ├── Verifica resposta de homepage
   └── Verifica que migrate rodou
       │
       ▼
6. Notificação
   └── Discord/Slack: "Deploy successful"

Em caso de falha:
  ├── Rollback automático (Forge mantém histórico)
  ├── Restore do backup do banco
  └── Notificação urgente
```

```
Branches Protegidas
─────────────────────────────────────────

main:
  ├── Push direto: BLOQUEADO
  ├── Merge sem PR: BLOQUEADO
  ├── Merge sem CI verde: BLOQUEADO
  ├── Auto-merge habilitado (após checks)
  ├── Linear history: forçado (squash apenas)
  └── Aplica ao owner também (sem bypass)
```

```
Versionamento de Releases
─────────────────────────────────────────

Tags semânticas: v{MAJOR}.{MINOR}.{PATCH}

Geração:
  ├── Manual via GitHub release no MVP
  └── Automático com Release Please na v2

Quando bumpar:
  ├── PATCH: bug fix sem breaking change
  ├── MINOR: nova feature compatível
  └── MAJOR: breaking change

Mudanças em ADRs ou specs:
  └── Não geram release (apenas docs)
```

---

## Justificativa

A escolha por Trunk-Based Development se justifica por:

1. **Velocidade** — Branches curtas mantêm momentum
2. **Menos conflitos** — Merges frequentes evitam grandes rebases
3. **Padrão moderno** — Empresas como Google, Spotify, Shopify usam
4. **Compatível com solo founder** — Sem overhead de múltiplas branches longas
5. **Funciona bem com Feature Flags** — Código pode ir para main mesmo incompleto

A escolha de PR obrigatório mesmo solo:
- Force self-review antes de merge
- CI roda em PR (catch bugs antes de main)
- Histórico de Pull Requests é documentação
- Hábito profissional desde o dia 1
- Quando 2ª pessoa entrar, fluxo já está estabelecido

A escolha de squash merge:
- Histórico de main fica limpo
- Reverter feature é trivial (1 commit)
- Commits "WIP" não poluem histórico

---

## Alternativas Consideradas

### Alternativa A — GitFlow Completo

- **Descrição:** Branches develop, release, hotfix, feature.
- **Pontos fortes:** Estrutura clara para ambientes múltiplos.
- **Pontos fracos:** Overhead alto, lento, complexo para solo founder.
- **Por que não foi escolhida:** Sem ambiente staging, GitFlow não traz benefício.

### Alternativa B — Sem Branches, Push Direto em Main

- **Descrição:** Trabalhar direto na main sem PR.
- **Pontos fortes:** Velocidade máxima.
- **Pontos fracos:** Sem CI, sem self-review, hábitos ruins.
- **Por que não foi escolhida:** Disciplina é importante mesmo solo. PR não é overhead real.

### Alternativa C — GitLab CI em vez de GitHub Actions

- **Descrição:** Usar GitLab para CI/CD.
- **Pontos fortes:** GitLab tem CI excelente.
- **Pontos fracos:** GitHub é mais comum, ecossistema mais maduro.
- **Por que não foi escolhida:** GitHub é padrão. Migração futura é trivial.

### Alternativa D — Deploy Manual via FTP/SSH

- **Descrição:** Deploy manual quando "estiver pronto".
- **Pontos fortes:** Zero CI/CD para configurar.
- **Pontos fracos:** Lento, propenso a erro, não escala.
- **Por que não foi escolhida:** GitHub Actions free tier resolve gratuitamente.

---

## Consequências

### Positivas

- Histórico de mudanças limpo e auditável
- CI captura erros antes de produção
- Deploy automatizado e confiável
- Self-review via PR melhora qualidade
- Padrão profissional desde o dia 1
- Onboarding de futuro dev é trivial

### Negativas

- Overhead pequeno de PR mesmo para mudanças simples
- Squash perde granularidade de commits (mas main fica limpa)
- Dependência do GitHub (vendor lock-in mínimo)
- Tempo de CI adiciona pequena latência

### Riscos

- **Risco:** GitHub Actions ter outage e bloquear deploys
  - **Mitigação:** Deploy manual documentado como fallback. Forge permite deploy direto via webhook.

- **Risco:** Free tier do GitHub Actions acabar em pico
  - **Mitigação:** 2.000 minutos/mês cobre projeto solo. Monitorar uso.

- **Risco:** Migration quebrar deploy automatizado
  - **Mitigação:** Backup antes de migrate. Migrations sempre reversíveis. Smoke tests pós-deploy.

---

## Critérios de Revisão

Esta decisão deve ser revisitada quando:

- Equipe crescer para 3+ pessoas (talvez precise mais review)
- Surgir necessidade de ambiente staging (fluxo muda)
- Free tier do GitHub Actions ficar limitante
- Compliance exigir aprovação por 2ª pessoa em produção

---

## Referências

- ADRs relacionadas: `ADR-017` (Environments), `ADR-021` (Infrastructure)
- Trunk-Based Development: https://trunkbaseddevelopment.com
- Conventional Commits: https://www.conventionalcommits.org

---

## Notas de Implementação

- `.github/workflows/`:
  - `ci.yml` — roda em todo PR
  - `deploy.yml` — roda em push para main
  - `preview.yml` — provisiona preview por PR (futuro)
- Templates:
  - `.github/PULL_REQUEST_TEMPLATE.md`
  - `.github/ISSUE_TEMPLATE/bug.md`
  - `.github/ISSUE_TEMPLATE/feature.md`
- Husky pre-commit hooks:
  - Pint em arquivos PHP modificados
  - ESLint em arquivos TS modificados
  - Bloqueia commit se falhar
- Configuração de proteção da main:
  - GitHub Settings → Branches → Add rule
  - Require pull request before merging
  - Require status checks to pass
  - Require linear history
  - Include administrators
- Forge webhook em `main`:
  - URL secreta no GitHub Secrets
  - Trigger: push em main
  - Forge faz pull + composer + migrate + cache
- Backup pré-deploy:
  - Job no GitHub Actions chama Neon API
  - Cria snapshot antes do deploy
  - Mantém últimos 7 dias
- Notificações:
  - Discord webhook em `.github/workflows/deploy.yml`
  - Mensagens: deploy iniciado, sucesso, falha
