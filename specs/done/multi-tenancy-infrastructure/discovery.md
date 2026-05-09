# Discovery: Infraestrutura de Multi-Tenancy
**Data:** 2026-04-30

## Problema

O projeto Acho (SaaS multi-tenant white-label para imobiliárias) precisa da fundação de multi-tenancy antes de qualquer feature de domínio. Sem essa infraestrutura — middleware de resolução por subdomínio, `BaseTenantModel` com scope automático, RLS no PostgreSQL e mecanismo de injeção de `tenant_id` no contexto da requisição — nenhum model, migration ou rota de negócio pode ser construída corretamente.

A ADR-001 já decidiu a estratégia (banco único compartilhado com Row-Level Security do PostgreSQL combinado com isolamento via aplicação). A ADR-002 definiu o modelo white-label SaaS. A ADR-016 definiu o roteamento por subdomínio com wildcard DNS/SSL. Esta etapa materializa essas decisões em código.

## Usuários e Benefício

**Usuário direto:** o solo founder (responsável pelo desenvolvimento) e qualquer dev futuro que entrar no projeto.

**Benefício imediato:** desbloqueia o desenvolvimento de todas as features do MVP (imóveis, agendamentos, auth, onboarding, vitrine, billing). Sem essa fundação, qualquer model novo nasceria errado por design — sem `tenant_id`, sem RLS, sem scope automático.

**Benefício indireto (cliente final, imobiliária):** garante isolamento de dados entre tenants desde o dia 1, em duas camadas independentes (Eloquent scope + RLS no banco), atendendo aos requisitos de segurança/LGPD da ADR-022.

## Critério de Sucesso

A etapa estará concluída quando todos os itens abaixo forem verdadeiros:

1. **Subdomínio resolve corretamente:**
   - `tenant1.acho.test` → middleware identifica o tenant, injeta `app.tenant_id` no Postgres, app responde 200
   - Subdomínio inexistente → página 404 com texto "Imobiliária não encontrada" + botão "Voltar para seuapp.com.br" (ADR-016)
   - Tenant suspenso → página com texto "Esta imobiliária está temporariamente indisponível" (ADR-016)
   - Slug reservado (`admin`, `www`, `api`, etc.) bloqueado conforme `config/reserved_slugs.php`

2. **Isolamento provado por teste automatizado:**
   - Tabela `tenant_isolation_probes` (vivendo apenas em `tests/`) usada como sonda
   - Tenant A insere registro; mesma query no contexto do tenant B retorna vazio (scope)
   - Bypass do scope no contexto do tenant B → RLS bloqueia no banco (defesa em profundidade)

3. **`BaseTenantModel` aplica scope automático:**
   - Model que estende `BaseTenantModel` em contexto de tenant A só vê dados do tenant A
   - `tenant_id` preenchido automaticamente em `creating`
   - Bypass explícito disponível via `withoutTenantScope()` (preparação para ADR-010 — Super Admin)

4. **RLS ativo nas tabelas de negócio:**
   - Migration de `tenants` rodando
   - Política `tenant_isolation` aplicada na tabela de probe e em qualquer tabela de negócio futura
   - Comando/query documentada para auditar quais tabelas têm RLS habilitado

5. **Cache de tenant funciona (ADR-016):**
   - Chave `tenant:{slug}` no Redis com TTL 60s
   - 1ª request consulta banco; demais (dentro do TTL) leem do cache
   - Atualização/suspensão de tenant invalida o cache imediatamente

6. **Tenant interno de teste seedado:**
   - `php artisan migrate:fresh --seed` cria o tenant `teste-interno` acessível em `teste-interno.acho.test`

7. **Cookies isolados por subdomínio:**
   - Configuração de session com `Domain` explícito do subdomínio (não wildcard) — ADR-016

8. **Qualidade:**
   - `make lint` passa (Pint + Larastan nível 8 + ESLint)
   - `make test` passa
   - Cobertura ≥70% no código novo

## Fora do Escopo

Itens cobertos por outras ADRs e que **não** entram nesta etapa:

- Auth completo (JWT, Refresh Token, login, register, password reset) — **ADR-014**
- Onboarding de imobiliárias (cadastro público, validação CNPJ, e-mail boas-vindas) — **ADR-011**
- Vitrine pública (páginas Inertia/React de Home/Listing/ImovelDetail) — **ADR-006**
- Painel Admin Tenant (Filament) — **ADR-007**
- Super Admin (Filament) — **ADR-010**
- Planos, trial e billing — **ADR-012**, **ADR-013**
- Notificações por e-mail (Resend) — **ADR-005**
- Storage de imagens (Cloudflare R2) — **ADR-015**
- Tabela `users` e modelo de autenticação — **ADR-014**
- Models de domínio (Imovel, Agendamento, Bairro, ImovelFoto, etc.)
- **Resolução por custom domain** — ADR-016 explicita "estrutura preparada... implementação real (v2)". Apenas a coluna `custom_domain` (nullable) e `domain_verified_at` (nullable) são criadas; o fluxo de lookup por custom domain não é implementado.
- CI/CD em produção (Forge/Hetzner) — apenas funcionar local nesta etapa

## Riscos e Dependências

### Dependências

- **`stancl/tenancy`** — composer require pendente; modo single-database integrado com RLS conforme ADR-001
- **PostgreSQL 16** com `gen_random_uuid()` — disponível no Docker Compose (ADR-019)
- **Redis** — disponível no Docker Compose
- **DNS wildcard local** — `*.acho.test` resolvido via `dnsmasq` no macOS (TLD `.test` adotado em substituição a `.local` por conflito com Bonjour/mDNS do macOS)

### Restrições técnicas

- A role da aplicação no Postgres **não pode** ter `BYPASSRLS` nem ser superuser (mitigação descrita na ADR-001). Configuração feita no Docker Compose local.
- `current_setting('app.tenant_id', true)::uuid` — o `missing_ok = true` é obrigatório para não quebrar contextos sem tenant (super admin, migrations).
- Migrations rodam **fora** de contexto de tenant — exigem role privilegiada com `BYPASSRLS` ou desabilitação temporária do RLS no contexto da migration.
- Cookies de sessão precisam de `Domain` explícito por subdomínio (não wildcard) — config diferente entre dev e prod.

### Riscos conhecidos

- **`stancl/tenancy` é opinionado** — uso parcial pode gerar atrito com a abordagem RLS+Eloquent custom. Aceito como conhecido; ADR-001 mantém o pacote como referência oficial.
- **Larastan nível 8 + `stancl/tenancy`** — pacote tem tipagem fraca em algumas APIs; baseline pode crescer no dia 1. Aceito; registrar e seguir.
- **Migrations em ambiente RLS** — risco de seeds bloqueados se executados pela role da aplicação. Mitigação: role separada para migrations (`acho_migrator` com `BYPASSRLS`) configurada no Docker Compose.
- **DNS wildcard em macOS** — `dnsmasq` exige configuração; `/etc/hosts` não suporta wildcard. Mitigação: documentação clara no runbook `docs/runbooks/local-setup.md`.
