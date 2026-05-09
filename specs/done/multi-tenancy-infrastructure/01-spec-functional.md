# Spec Funcional: Infraestrutura de Multi-Tenancy

**Versão:** 1.0
**Status:** done
**Discovery:** specs/wip/multi-tenancy-infrastructure/discovery.md

## Contexto

O projeto Acho é um SaaS multi-tenant white-label para imobiliárias brasileiras. Cada imobiliária recebe um subdomínio dedicado (ex: `primoimoveis.acho.com.br`) e seus dados são isolados dos demais tenants.

Antes de qualquer feature de domínio (imóveis, agendamentos, auth, onboarding), é preciso construir a fundação de multi-tenancy: middleware de resolução de tenant por subdomínio, scope automático no Eloquent, RLS no PostgreSQL e mecanismo de injeção de `app.tenant_id` no contexto da requisição.

ADRs decisórias: ADR-001 (RLS + isolamento em camadas), ADR-002 (modelo white-label), ADR-016 (roteamento por subdomínio), ADR-025 (padrões de projeto).

## Usuários e Benefício

- **Usuário direto:** solo founder e devs futuros do projeto.
- **Benefício imediato:** desbloqueia o desenvolvimento de todas as features do MVP.
- **Benefício indireto (imobiliária cliente):** isolamento de dados entre tenants em duas camadas (scope + RLS) desde o dia 1, atendendo aos requisitos de segurança/LGPD da ADR-022.

## Comportamento Esperado

**Fluxo F1: Acesso a subdomínio de tenant ativo**
- DADO que existe um tenant com `slug = "primoimoveis"` e `status = "active"`
- QUANDO o usuário acessa `https://primoimoveis.acho.test/qualquer-rota`
- ENTÃO o middleware identifica o tenant
- E injeta `app('currentTenant')` no container da aplicação
- E executa `SET app.tenant_id = '{uuid-do-tenant}'` na conexão Postgres
- E a aplicação processa a request normalmente

**Fluxo F2: Acesso a subdomínio inexistente**
- DADO que não existe tenant com `slug = "naocadastrada"`
- QUANDO o usuário acessa `https://naocadastrada.acho.test/`
- ENTÃO a aplicação retorna HTTP 404
- E renderiza página com texto "Imobiliária não encontrada"
- E exibe botão "Voltar para acho.test"
- E não vaza informação técnica (sem stack trace, sem detalhes de banco)

**Fluxo F3: Acesso a tenant suspenso**
- DADO que existe um tenant com `slug = "primoimoveis"` e `status = "suspended"`
- QUANDO o usuário acessa `https://primoimoveis.acho.test/`
- ENTÃO a aplicação retorna HTTP 403
- E renderiza página com texto "Esta imobiliária está temporariamente indisponível"
- E não expõe motivo de suspensão nem detalhes técnicos

**Fluxo F4: Cache de resolução de tenant**
- DADO que `tenant1` foi resolvido há menos de 60s
- QUANDO uma nova request chega para o mesmo subdomínio
- ENTÃO o tenant é lido de `Redis: tenant:tenant1` sem consultar o banco

**Fluxo F5: Invalidação de cache**
- DADO que `tenant1` está em cache
- QUANDO o tenant é atualizado, suspenso, reativado ou deletado
- ENTÃO o evento correspondente dispara o listener `InvalidateTenantCache`
- E a chave `tenant:tenant1` é deletada do Redis imediatamente
- E a próxima request reflete o novo estado

**Fluxo F6: Scope automático em Model de tenant**
- DADO que estou em contexto do `tenant_a`
- E que existe um `Probe` com `tenant_id = tenant_b.id`
- QUANDO executo `Probe::all()`
- ENTÃO o resultado não inclui o registro do `tenant_b`
- E ao criar `Probe::create(['nome' => 'x'])`, `tenant_id` é preenchido com `tenant_a.id` automaticamente

**Fluxo F7: RLS como defesa em profundidade**
- DADO que estou em contexto do `tenant_a`
- E que existe um registro `Probe` do `tenant_b`
- QUANDO executo `Probe::withoutTenantScope()->find($idDoBProbe)`
- ENTÃO o registro não é retornado (RLS bloqueia no banco mesmo sem scope)

**Fluxo F8: Bypass autorizado de scope**
- DADO que estou em contexto privilegiado (role com `BYPASSRLS`, ex: super admin futuro / migration)
- QUANDO executo `Probe::withoutTenantScope()->get()` nessa role
- ENTÃO todos os registros de todos os tenants são retornados

**Fluxo F9: Cookies isolados por subdomínio**
- DADO que um cookie de sessão foi criado em `primoimoveis.acho.test`
- QUANDO o usuário acessa `casanova.acho.test`
- ENTÃO o cookie de `primoimoveis` não é enviado para `casanova`
- E as sessões permanecem totalmente isoladas

**Fluxo F10: Tenant interno seedado**
- DADO um banco recém-criado
- QUANDO executo `php artisan migrate:fresh --seed`
- ENTÃO existe um tenant com `slug = "teste-interno"`, `status = "active"`
- E ele é acessível em `teste-interno.acho.test`

**Fluxo F11: Acesso ao domínio raiz**
- DADO que o usuário acessa `https://acho.test/`
- QUANDO a request chega
- ENTÃO uma rota web simples responde com a mensagem "Acho — plataforma em construção"
- E não passa pelo middleware `TenantResolver`

**Fluxo F12: Acesso a `admin.acho.test`**
- DADO que `admin` é um slug reservado
- QUANDO o usuário acessa `https://admin.acho.test/`
- ENTÃO a aplicação retorna HTTP 404 com a mesma página de "Imobiliária não encontrada"
- E o subdomínio não é tratado como tenant (Super Admin é ADR-010 — futuro)

**Fluxo F13: Acesso a `www.acho.test`**
- DADO que o usuário acessa `https://www.acho.test/`
- QUANDO a request chega
- ENTÃO a aplicação retorna HTTP 301 redirecionando para `https://acho.test/`

## Contrato de Interface

Esta é uma feature de infraestrutura — não há API REST pública nesta etapa. Os contratos são internos:

**Middleware `TenantResolver`**
- Input: HTTP Request (`Host` header)
- Output: request enriquecida; `app('currentTenant')` populado
- Erros: tenant não encontrado → resposta 404; tenant suspenso → resposta 403

**Middleware `SetTenantContext`**
- Input: tenant resolvido em `app('currentTenant')`
- Output: `SET app.tenant_id = '{uuid}'` aplicado na conexão Postgres do request
- Erros: nenhum (idempotente)

**`TenantService`**
- `resolveBySlug(string $slug): ?Tenant` — lookup com cache (TTL 60s, key `tenant:{slug}`)
- `resolveByCustomDomain(string $domain): ?Tenant` — preparado mas retorna `null` (ativação em v2)
- `invalidateCache(Tenant $tenant): void`

**`BaseTenantModel`**
- Scope global automático filtrando por `tenant_id = app('currentTenant')->id`
- Boot `creating`: preenche `tenant_id` automaticamente
- Macro/método `withoutTenantScope()`: remove o scope de uma query
- RLS continua aplicado ao bypass a menos que a role do banco tenha `BYPASSRLS`

**Eventos disparados**
- `TenantCreated` — quando um tenant é criado (seed inicial dispara)
- `TenantUpdated` — quando atributos do tenant mudam
- `TenantSuspended` — transição active → suspended
- `TenantReactivated` — transição suspended → active

**Listener (único nesta etapa)**
- `InvalidateTenantCache` — escuta os 4 eventos acima e remove `tenant:{slug}` do Redis

**Erros tratados**
- Subdomínio inexistente → 404 página estilizada (texto da ADR-016)
- Tenant suspenso → 403 página estilizada (texto da ADR-016)
- Slug reservado em validação → mensagem de erro via `Rules\ReservedSlug` (cadastro completo está fora do escopo, mas a regra existe e é testada)
- Migrations rodando sem `app.tenant_id` → role `acho_migrator` com `BYPASSRLS` evita bloqueio

## Casos de Borda

- [ ] Request a `acho.test` (raiz) — rota web responde "Acho — plataforma em construção"; não passa por `TenantResolver`
- [ ] Request a `admin.acho.test` — slug reservado; retorna 404 (Super Admin é ADR-010)
- [ ] Request a `www.acho.test` — redirect 301 para `acho.test`
- [ ] Subdomínio com caracteres inválidos (`tenant_x`, `Tenant1`, `xn--`) — rejeitado; trata como inexistente (404)
- [ ] Subdomínio acima de 30 caracteres (limite da convenção 04-database) — não pode existir no banco; tratado como inexistente
- [ ] Tenant atualizado durante request em andamento — cache stale por até 60s aceito; mudanças de status invalidam cache imediatamente
- [ ] Migrations e seeds — rodam com role `acho_migrator` (`BYPASSRLS`)
- [ ] Concorrência: dois processos invalidando o mesmo cache simultaneamente — operação idempotente, sem problema
- [ ] CLI artisan/jobs sem subdomínio — sem tenant ativo; queries em tabelas com RLS retornam vazio (esperado); operações cross-tenant exigem role privilegiada ou bypass explícito
- [ ] Cookie de sessão pré-existente — bloqueado por `Domain` explícito do subdomínio
- [ ] Slug em maiúsculas no header `Host` — normalizado para minúsculas antes do lookup

## Critérios de Aceite

- [ ] **AC-01:** Subdomínio de tenant ativo é resolvido e a app responde 200 (Fluxo F1)
- [ ] **AC-02:** Subdomínio inexistente retorna 404 com texto "Imobiliária não encontrada" + botão "Voltar para acho.test" (Fluxo F2)
- [ ] **AC-03:** Tenant suspenso retorna 403 com texto "Esta imobiliária está temporariamente indisponível" (Fluxo F3)
- [ ] **AC-04:** Cache de tenant funciona (TTL 60s, key `tenant:{slug}`) e é invalidado pelos eventos `TenantUpdated/Suspended/Reactivated` (Fluxos F4, F5)
- [ ] **AC-05:** `BaseTenantModel` aplica scope automático e preenche `tenant_id` em `creating` (Fluxo F6)
- [ ] **AC-06:** RLS bloqueia leitura cross-tenant mesmo com scope desabilitado em role sem `BYPASSRLS` (Fluxo F7)
- [ ] **AC-07:** Bypass autorizado funciona em role com `BYPASSRLS` (Fluxo F8)
- [ ] **AC-08:** Cookies de sessão isolados por subdomínio (Fluxo F9)
- [ ] **AC-09:** Tenant `teste-interno` é seedado em `migrate:fresh --seed` (Fluxo F10)
- [ ] **AC-10:** `acho.test` (raiz) responde "Acho — plataforma em construção"; `admin.acho.test` responde 404; `www.acho.test` faz redirect 301 (Fluxos F11, F12, F13)
- [ ] **AC-11:** Slugs reservados (lista da ADR-016 em `config/reserved_slugs.php`) são bloqueados pela `Rule` `ReservedSlug` — testável isoladamente
- [ ] **AC-12:** Tabela `tenants` existe com colunas `id (uuid)`, `slug (unique, varchar 30)`, `name`, `custom_domain (nullable)`, `domain_verified_at (nullable)`, `status` (`active`/`suspended`), timestamps + soft delete
- [ ] **AC-13:** Eventos `TenantCreated`, `TenantUpdated`, `TenantSuspended`, `TenantReactivated` são disparados nas mutações correspondentes; listener `InvalidateTenantCache` reage a `Updated/Suspended/Reactivated`
- [ ] **AC-14:** `make lint` (Pint + Larastan 8) e `make test` passam; cobertura ≥70% no código novo

## Fora do Escopo

- Auth completo (ADR-014): JWT, Refresh Token, login, register, password reset
- Onboarding de imobiliárias (ADR-011): cadastro público, validação CNPJ, e-mail boas-vindas
- Vitrine pública (ADR-006): páginas Inertia/React de Home/Listing/ImovelDetail
- Painel Admin Tenant Filament (ADR-007)
- Super Admin Filament (ADR-010)
- Planos, trial e billing (ADR-012, ADR-013) — status do tenant fica restrito a `active`/`suspended` nesta etapa
- Notificações por e-mail (ADR-005)
- Storage de imagens (ADR-015)
- Tabela `users` (ADR-014)
- Models de domínio (Imovel, Agendamento, Bairro, ImovelFoto, Subscription, etc.)
- Lookup ativo por custom domain — apenas as colunas (`custom_domain`, `domain_verified_at`) são preparadas; resolução fica para v2
- Estilização avançada das páginas de erro com identidade visual — textos da ADR-016 são preservados; visual fica simples (HTML básico) e melhora com a vitrine
- CI/CD e deploy em produção
- Headers de segurança avançados (CSP, HSTS dinâmico) — ADR-022

## Dúvidas em Aberto

Nenhuma. Todas as 6 dúvidas levantadas foram travadas:

1. Status enum: apenas `active` e `suspended` nesta etapa
2. `acho.test` raiz: rota web simples respondendo "Acho — plataforma em construção"
3. `admin.acho.test`: retorna 404 (Super Admin é ADR-010)
4. `www.acho.test`: redirect 301 para `acho.test`
5. Eventos: dispatchers nos pontos de mutação + único listener `InvalidateTenantCache`; demais listeners ficam para suas ADRs
6. Nome da tabela: `tenants` (inglês)

## Como foi implementado

### Desvios e decisões técnicas relevantes

**`SET app.tenant_id` → `SELECT set_config()`**: PostgreSQL não suporta bind parameters no comando `SET`. Substituído por `SELECT set_config('app.tenant_id', ?, false)` em `SetTenantContext`, `TestCase::actingAsTenant()` e `BaseTenantModelTest`.

**`ProbeMigration` usa conexão `pgsql` (não `pgsql_migrator` como na spec)**: DDL em uma transaction (T2) não é visível para outra connection (T1). Mover o `CREATE TABLE` para `pgsql` (T1) permite que o mesmo transaction contenha tabela, dados e queries do teste. `FORCE ROW LEVEL SECURITY` foi adicionado ao probe table porque o owner da tabela (`acho_app`) seria isento do RLS por padrão no PostgreSQL.

**`ProbeMigration` sem FK para `tenants`**: FK cross-connection falha quando o tenant referenciado está em transaction diferente (uncommitted). Tabela é test-only; FK dispensável.

**Grants bidirecionais entre roles Postgres**: A spec previa apenas `acho_migrator → acho_app` via `ALTER DEFAULT PRIVILEGES`. Na prática, o `RefreshDatabase` do Pest/PHPUnit cria as tabelas como `acho_app` (trait sobrescreve o override do parent class, impedindo que `migrate:fresh` rode como `acho_migrator`). O init script `20-create-roles.sh` foi atualizado com grants em ambos os sentidos.

**`connectionsToTransact = ['pgsql', 'pgsql_migrator']` no TestCase**: Garante rollback nas duas connections ao final de cada teste. Testes que precisam de dados visíveis via `TenantService` (que usa `pgsql_migrator`) criam tenants com `Tenant::on('pgsql_migrator')` — a connection enxerga seus próprios dados não-commitados dentro da mesma transaction.

**Nome do arquivo do init script**: `docker/postgres/20-create-roles.sh` (não `init.sql` como na spec técnica). Docker executa `/docker-entrypoint-initdb.d/` em ordem lexicográfica; o prefix `20-` garante que o `10-create-testing-database.sql` do Sail rode antes das roles serem criadas.

### ACs com cobertura parcial
- **AC-07**: Role `acho_migrator` criada com `BYPASSRLS` e usada em `TenantService` para todos os lookups. Sem teste explícito que consulta dados cross-tenant via `pgsql_migrator` e verifica retorno irrestrito.
- **AC-08**: `TenantServiceProvider::boot()` seta `session.domain` dinamicamente. Sem teste automatizado de isolamento de cookie (requer inspeção HTTP de headers `Set-Cookie`).
