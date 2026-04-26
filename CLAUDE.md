# CLAUDE.md

Este arquivo é o ponto de entrada para o Claude Code neste repositório. Leia ele primeiro em toda sessão.

---

## Sobre o Projeto

SaaS multi-tenant white-label para imobiliárias brasileiras. Cada cliente recebe subdomínio próprio (ex: `primoimoveis.seuapp.com.br`) com vitrine pública e painel admin.

**Cliente pagante:** imobiliária (B2B subscription mensal)
**Usuário final:** comprador/inquilino (gratuito, cadastra para agendar visitas)

---

## Hierarquia de Verdade

Quando houver conflito, esta é a ordem:

1. **ADRs** (`docs/adr/`) — decisões arquiteturais imutáveis
2. **Convenções** (`docs/conventions/`) — padrões de implementação
3. **Specs** (`docs/specs/`) — especificações de feature
4. **Código** — implementação concreta

**Regra:** se uma ADR diz X e o código faz Y, o código está errado (ou a ADR precisa ser substituída por nova ADR — não editada).

---

## Como Navegar a Documentação

Antes de implementar qualquer coisa significativa, leia na seguinte ordem:

1. `docs/adr/README.md` — índice de todas as decisões arquiteturais
2. ADRs específicas relacionadas à tarefa (referenciadas no índice)
3. Convenções relevantes em `docs/conventions/`
4. Spec da feature em `docs/specs/` (se existir; criar antes de codar se não existir)

**Não pule essa leitura em mudanças arquiteturais.** As ADRs respondem "por quê" — sem elas, decisões viram opinião pessoal.

---

## Stack (resumo)

Detalhes completos em `docs/adr/019-tech-stack.md`.

- **Backend:** Laravel 13 + PHP 8.3
- **Vitrine pública:** Inertia.js + React + TypeScript
- **Admin tenant + Super Admin:** Filament 3 (panels separados)
- **Banco:** PostgreSQL 16 com Row-Level Security
- **Cache/Queue:** Redis
- **Storage:** Cloudflare R2 (prod) / filesystem (local)
- **Email:** Resend
- **Pagamento:** Pagar.me
- **Auth:** JWT + Refresh Token (ver ADR-014)
- **Senhas:** Argon2id + Pepper (ver ADR-023)

---

## Comandos Úteis

```bash
# Setup local
make up              # sobe ambiente (Docker Compose)
make down            # para ambiente
make fresh           # reset completo do banco + seeds

# Qualidade
make lint            # Pint + Larastan + ESLint
make test            # Pest (testes)
make analyze         # análise estática (Larastan nível 8)

# Backend
php artisan migrate
php artisan tinker
./vendor/bin/pest --parallel

# Frontend
npm run dev
npm run build
```

---

## Regras Críticas (NÃO violar)

### Multi-tenancy

- **TODA tabela de negócio tem `tenant_id` (uuid, NOT NULL, indexed).**
- **TODO Model de negócio estende `BaseTenantModel`** (aplica scope automático).
- **TODA tabela de negócio tem RLS habilitado** (defesa em profundidade).
- **NUNCA fazer query bypassando tenant_id** sem ser via conexão Super Admin específica.
- Em dúvida sobre isolamento: ler `docs/adr/001-database-strategy.md`.

### Segurança

- **NUNCA logar senhas, tokens ou dados de cartão.**
- **NUNCA expor `tenant_id` ou `user_id` em URLs públicas** sem validação de permissão.
- **TODA validação de input passa por Form Request** (não confiar em frontend).
- **Senhas:** apenas Argon2id + Pepper (ver ADR-023). Nunca bcrypt.
- **APP_PEPPER nunca vai pro banco nem pro repo.** Apenas em `.env` (vault em prod).

### Arquitetura

- **NÃO usar Repository Pattern.** Eloquent já é repository. Ver ADR-025.
- **Controllers thin.** Lógica em Services (`app/Services/`).
- **Form Requests para validação + autorização.**
- **DTOs (Spatie Laravel Data) em inputs/outputs de Services críticos.**
- **Eventos para desacoplar** side-effects (notifications, audit, cache invalidation).
- **Policies para autorização**, não condicionais espalhadas.

### Banco de Dados

- **PKs em UUID**, não auto-increment.
- **Sempre `created_at`, `updated_at`, `deleted_at`** (soft delete).
- **Migrations imutáveis após deploy.** Mudança = nova migration.
- **Migrations sempre reversíveis** (`down()` funcional).
- **Tabelas em `snake_case` plural**, colunas em `snake_case`.

### Frontend

- **TypeScript strict mode.** Sem `any` exceto justificadamente.
- **Componentes em `resources/js/Components/`**, páginas em `resources/js/Pages/`.
- **React Hook Form + Zod** para formulários.
- **Tailwind utility-first**, sem CSS custom exceto raros casos.
- **Tanstack Query** para estado de servidor.

---

## Padrões de Trabalho

### Ao criar uma nova feature

1. Verificar se existe spec em `docs/specs/`. Se não existir, criar antes de codar.
2. Verificar ADRs relevantes (não contradizer).
3. Branch: `feature/{descrição-curta}` a partir de `main`.
4. Commits seguem Conventional Commits (`feat:`, `fix:`, `refactor:`, `docs:`, `test:`, `chore:`).
5. Lifetime da branch: < 2 dias. Se passar, quebrar em PRs menores.
6. PR para `main` com squash merge. Sem push direto.

### Ao corrigir bug

1. Reproduzir com teste que falha.
2. Branch: `bugfix/{descrição-curta}`.
3. Commit: `fix(scope): descrição`.
4. PR com link para a issue/contexto.

### Ao mexer em algo arquitetural

1. **Pare e leia a ADR relacionada.**
2. Se a mudança contradiz uma ADR, criar nova ADR substituindo a anterior antes do código.
3. Não silenciosamente desviar de uma decisão registrada.

---

## O que NÃO Está no MVP

Para evitar scope creep — estas features ficam para v2 (ver ADRs específicas):

- ❌ Contrato digital (DocuSign/ClickSign) — ADR-004
- ❌ Login social (Google/Facebook) — ADR-009
- ❌ 2FA / Magic links — ADR-009
- ❌ WhatsApp Business API — ADR-005 (no MVP usar link `wa.me/`)
- ❌ Domínio customizado (custom domain) — ADR-016 (estrutura preparada, não ativa)
- ❌ Tour virtual 360°, comparador, favoritos — ADR-006
- ❌ Integração Google Calendar — ADR-008
- ❌ Importação XML de portais (ZAP/OLX) — ADR-007
- ❌ Relatórios e dashboards avançados — ADR-007

Se o usuário pedir uma dessas, **pergunte se quer entrar no escopo** (e criar nova ADR) ou manter para v2.

---

## Ambientes

Apenas dois ambientes ativos no MVP (ver ADR-017):

- **Local:** Docker Compose, custo $0
- **Produção:** Hetzner CX21 + Forge + Neon Pro, custo ~$38/mês

**Não existe staging permanente.** Substitutos:
- Preview deployments por PR (Neon branching)
- Feature flags (`spatie/laravel-feature-flags`)
- Tenant interno de teste em produção (`teste-interno`)
- Suíte robusta de testes (>70% nos paths críticos)

---

## Quando Pedir Confirmação ao Usuário

Claude Code: **pergunte antes de**:

- Rodar migrations em produção (mesmo via deploy automatizado, confirme se não souber)
- Apagar arquivos ou tabelas
- Fazer mudanças que afetam mais de uma ADR
- Adicionar dependências externas novas (composer/npm)
- Mudanças em `.env`, configurações de Forge ou Cloudflare
- Operações que afetem dados de tenants reais

**Não pergunte para:** formatação, linting, refactor pequeno em arquivo único, criar/editar testes, criar branches, gerar specs ou ADRs novas.

---

## Referência Rápida de ADRs

| # | ADR | Quando consultar |
|---|---|---|
| 001 | Database Strategy | Mexer em schema, queries cross-tenant |
| 002 | Tenancy Model | Dúvidas sobre subdomínio vs marketplace |
| 003 | User Profiles | Adicionar role ou permissão |
| 005 | Notifications | Enviar email ou notificação |
| 006 | Listing Module | Mexer na vitrine pública |
| 007 | Admin Module | Mexer no painel Filament tenant |
| 008 | Scheduling | Mexer em agendamentos |
| 009 | Auth Module | Cadastro, login, recuperação |
| 010 | Super Admin | Funcionalidades de operador |
| 011 | Onboarding | Cadastro de imobiliária, validação CNPJ |
| 012 | Trial and Plans | Limites, downgrade, planos |
| 013 | Payment Gateway | Webhooks Pagar.me, cobranças |
| 014 | Authentication | JWT, Refresh Token, sessões |
| 015 | Image Storage | Upload, R2, abstração de storage |
| 016 | Subdomain Routing | Middleware de tenant, DNS, SSL |
| 022 | Security | OWASP, LGPD, headers, auditoria |
| 023 | Password Crypto | Hashing, política de senha, pepper |
| 025 | Project Patterns | Estrutura de pastas, DTOs, Services |

Índice completo em `docs/adr/README.md`.

---

## Última Coisa

Se algo neste arquivo conflitar com uma ADR, **a ADR vence**. Este arquivo é resumo e ponto de entrada — não fonte de verdade.

Se uma regra acima estiver desatualizada, abra PR atualizando este arquivo junto com a ADR relacionada.