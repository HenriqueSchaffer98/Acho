# Plano: Infraestrutura de Multi-Tenancy

**Spec funcional:** specs/wip/multi-tenancy-infrastructure/01-spec-functional.md
**Spec técnica:** specs/wip/multi-tenancy-infrastructure/02-spec-tech.md
**Status:** ready

## Tarefas

### Infraestrutura Local (Docker / DNS / Qualidade)

- [ ] TASK-01 — Criar `docker/postgres/init.sql` com roles `acho_app` (sem `BYPASSRLS`) e `acho_migrator` (com `BYPASSRLS`)
  - Spec ref: ADR-001 (R2), AC-06, AC-07
  - Done quando: `docker compose up` cria as duas roles automaticamente; `psql` confirma `pg_roles.rolbypassrls = true` apenas em `acho_migrator`

- [ ] TASK-02 — Atualizar `docker-compose.yml` para montar o init.sql e ajustar usuário padrão da app para `acho_app`
  - Spec ref: ADR-001
  - Done quando: container Postgres inicializa com as roles e a app conecta como `acho_app` por padrão

- [ ] TASK-03 — Criar `pint.json` com preset `laravel`
  - Spec ref: AC-14
  - Done quando: `vendor/bin/pint --test` roda sem erro de config

- [ ] TASK-04 — Criar `phpstan.neon` com Larastan nível 8 e baseline para `stancl/tenancy`
  - Spec ref: AC-14, R5
  - Done quando: `vendor/bin/phpstan analyse` roda no nível 8 sem falsos positivos do pacote

- [ ] TASK-05 — Criar `Makefile` com targets `up/down/fresh/lint/test/analyze`
  - Spec ref: AC-14
  - Done quando: `make lint` chama Pint + Larastan; `make test` chama Pest

- [ ] TASK-06 — Criar `docs/runbooks/local-setup.md` documentando dnsmasq (`*.acho.test` → 127.0.0.1) e roles Postgres
  - Spec ref: ADR-016 (DNS), R7
  - Done quando: dev novo consegue subir o ambiente seguindo apenas o runbook

### Dependências e Configuração Base

- [ ] TASK-07 — Adicionar `stancl/tenancy: ^3.10`, `spatie/laravel-data: ^4`, `larastan/larastan: ^3` e `laravel/pint: ^1` ao `composer.json`
  - Spec ref: Stack e Dependências
  - Done quando: `composer install` resolve sem conflitos no Laravel 13

- [ ] TASK-08 — Publicar e configurar `config/tenancy.php` em modo single-database, bootstrappers desabilitados
  - Spec ref: Decisão #2
  - Done quando: arquivo publicado; `tenancy.bootstrappers = []`; modelos do pacote não usados

- [ ] TASK-09 — Adicionar conexão `pgsql_migrator` em `config/database.php` apontando para a role privilegiada
  - Spec ref: Decisão #3, R2
  - Done quando: `DB::connection('pgsql_migrator')` conecta como `acho_migrator`

- [ ] TASK-10 — Atualizar `.env.example` com `DB_USERNAME=acho_app`, `DB_USERNAME_MIGRATOR=acho_migrator`, `SESSION_DRIVER=redis`, `APP_URL=http://acho.test`
  - Spec ref: Decisão #6
  - Done quando: `.env.example` reflete a stack final

- [ ] TASK-11 — Atualizar `config/session.php` com driver `redis` e `domain = null` por padrão (será sobrescrito no provider)
  - Spec ref: Decisão #6, R7
  - Done quando: sessão usa Redis; `domain` ajustável dinamicamente

- [ ] TASK-12 — Configurar `bootstrap/app.php` para registrar middleware aliases (`tenant.resolve`, `tenant.context`) e route groups (web, tenant)
  - Spec ref: R9
  - Done quando: aliases disponíveis para rotas tenant; `routes/tenant.php` carregado

### Banco de Dados

- [ ] TASK-13 — Migration `create_tenants_table` com colunas, constraint check de status e sem RLS (tenants é tabela pai)
  - Spec ref: AC-12
  - Done quando: `php artisan migrate` cria a tabela; `\d tenants` mostra todas as colunas; constraint check ativo

### Domain Layer (Models / Enums / DTOs / Eventos)

- [ ] TASK-14 — Criar `App\Enums\TenantStatus` (Active, Suspended)
  - Spec ref: Decisão #8, AC-12
  - Done quando: enum retorna 2 cases corretos

- [ ] TASK-15 — Criar `App\Models\Scopes\TenantScope` (apply respeita `app('currentTenant')`)
  - Spec ref: AC-05, F6
  - Done quando: scope filtra automaticamente quando há tenant; não filtra quando não há

- [ ] TASK-16 — Criar `App\Models\BaseTenantModel` abstrato com scope global + listener `creating` + método `withoutTenantScope()`
  - Spec ref: AC-05, F6
  - Done quando: subclasse hipotética em teste isolado herda comportamento

- [ ] TASK-17 — Criar `App\Models\Tenant` com casts, métodos `suspend()`/`reactivate()`/`isActive()`/`isSuspended()` e `$dispatchesEvents`
  - Spec ref: AC-12, AC-13
  - Done quando: criação dispara `TenantCreated`; mutação dispara `TenantUpdated`; suspensão chama `dispatch(TenantSuspended)`

- [ ] TASK-18 — Criar `App\Data\Tenant\TenantData` (Spatie Data DTO)
  - Spec ref: ADR-025 (DTOs)
  - Done quando: hidrata a partir do Model; serializa para array/JSON correto

- [ ] TASK-19 — Criar 4 eventos: `TenantCreated`, `TenantUpdated`, `TenantSuspended`, `TenantReactivated`
  - Spec ref: AC-13, F5
  - Done quando: classes existentes recebem `Tenant` no construtor

### Validação

- [ ] TASK-20 — Criar `config/reserved_slugs.php` com a lista da ADR-016
  - Spec ref: AC-11, ADR-016
  - Done quando: `config('reserved_slugs')` retorna array com todos os slugs

- [ ] TASK-21 — Criar `App\Rules\ReservedSlug` invocável via Form Request
  - Spec ref: AC-11
  - Done quando: rule rejeita slug em `config('reserved_slugs')`; aceita slug livre

### Service Layer e Listeners

- [ ] TASK-22 — Criar `App\Services\Tenant\TenantService` com `resolveBySlug`, `resolveByCustomDomain` (null) e `invalidateCache`
  - Spec ref: AC-04, F4, F5, R2
  - Done quando: lookup usa `pgsql_migrator`; cache hit/miss verificável; invalidate remove a key

- [ ] TASK-23 — Criar `App\Listeners\Tenant\InvalidateTenantCache` (síncrono, escuta Updated/Suspended/Reactivated)
  - Spec ref: AC-13, R3
  - Done quando: ouvir os 3 eventos remove `tenant:{slug}` do Redis

- [ ] TASK-24 — Criar `App\Providers\EventServiceProvider` mapeando eventos → `InvalidateTenantCache`
  - Spec ref: AC-13
  - Done quando: `Event::fake()` em teste confirma listener registrado

### Middlewares e Provider

- [ ] TASK-25 — Criar `App\Http\Middleware\TenantResolver`
  - Spec ref: AC-01, AC-02, AC-03, F1, F2, F3
  - Done quando: middleware aplicado a rota teste roteia certo conforme estado do tenant

- [ ] TASK-26 — Criar `App\Http\Middleware\SetTenantContext`
  - Spec ref: AC-01, F1
  - Done quando: após o middleware, `current_setting('app.tenant_id')` retorna uuid correto

- [ ] TASK-27 — Criar `App\Support\SubdomainHelper` para normalizar e extrair slug do `Host` header
  - Spec ref: F1, casos de borda
  - Done quando: helper retorna slug válido ou `null`

- [ ] TASK-28 — Criar `App\Providers\TenantServiceProvider`
  - Spec ref: Decisão #6, R7
  - Done quando: domain de sessão reflete subdomínio atual em request HTTP

### Exceções e Páginas de Erro

- [ ] TASK-29 — Criar `TenantNotFoundException` e `TenantSuspendedException`
  - Spec ref: AC-02, AC-03
  - Done quando: classes existem com mensagens apropriadas

- [ ] TASK-30 — Modificar `app/Exceptions/Handler.php` para renderizar views específicas com 404/403
  - Spec ref: AC-02, AC-03, Decisão #9
  - Done quando: exceções renderizam views certas com status code correto

- [ ] TASK-31 — Criar views `tenant.not-found` e `tenant.suspended` com textos exatos da ADR-016
  - Spec ref: AC-02, AC-03
  - Done quando: textos corretos presentes; sem stack trace

### Rotas e Controllers Públicos

- [ ] TASK-32 — Criar view `public.home`
  - Spec ref: AC-10, F11
  - Done quando: view renderiza "Acho — plataforma em construção"

- [ ] TASK-33 — Criar `HomeController` retornando view `public.home`
  - Spec ref: AC-10, F11
  - Done quando: `acho.test/` responde 200

- [ ] TASK-34 — Configurar `routes/web.php` com rota raiz e redirect 301 de `www.acho.test`
  - Spec ref: AC-10, F11, F13
  - Done quando: `acho.test/` → 200; `www.acho.test/` → 301

- [ ] TASK-35 — Criar `routes/tenant.php` com Route::domain e middleware group
  - Spec ref: AC-01, F1
  - Done quando: subdomínio válido renderiza placeholder 200

### Seeder

- [ ] TASK-36 — Criar `TenantSeeder` que cria `teste-interno` (active)
  - Spec ref: AC-09, F10
  - Done quando: seeder idempotente; cria tenant no banco

- [ ] TASK-37 — Atualizar `DatabaseSeeder` para chamar `TenantSeeder`
  - Spec ref: AC-09
  - Done quando: `migrate:fresh --seed` deixa `teste-interno` no banco

### Testes

- [ ] TASK-38 — Criar `tests/Support/ProbeMigration.php` e Model `Probe` dummy
  - Spec ref: AC-05, AC-06, AC-07
  - Done quando: helper cria tabela com RLS + `WITH CHECK` em setUp

- [ ] TASK-39 — Modificar `tests/TestCase.php` com helpers `actingAsTenant` e `withoutTenantContext`
  - Spec ref: AC-05, AC-07
  - Done quando: helpers configuram contexto de tenant corretamente

- [ ] TASK-40 — Criar `tests/Unit/Rules/ReservedSlugTest.php`
  - Spec ref: AC-11
  - Done quando: 100% das branches da rule testadas

- [ ] TASK-41 — Criar `tests/Unit/Services/TenantServiceTest.php`
  - Spec ref: AC-04, R2
  - Done quando: cobertura ≥90% do service

- [ ] TASK-42 — Criar `tests/Unit/Models/BaseTenantModelTest.php`
  - Spec ref: AC-05
  - Done quando: scope e auto-fill de tenant_id validados

- [ ] TASK-43 — Criar `tests/Feature/Tenant/ResolveTenantBySubdomainTest.php`
  - Spec ref: AC-01
  - Done quando: request para subdomínio ativo responde 200

- [ ] TASK-44 — Criar `tests/Feature/Tenant/TenantStatusTest.php`
  - Spec ref: AC-02, AC-03
  - Done quando: inexistente → 404; suspenso → 403 com textos corretos

- [ ] TASK-45 — Criar `tests/Feature/Tenant/TenantIsolationTest.php`
  - Spec ref: AC-05, AC-06
  - Done quando: tenant B não enxerga registros de A nem com `withoutTenantScope`

- [ ] TASK-46 — Criar `tests/Feature/Tenant/TenantCacheTest.php`
  - Spec ref: AC-04
  - Done quando: 2ª request não bate no banco; suspensão invalida cache

- [ ] TASK-47 — Criar `tests/Feature/Tenant/ReservedSubdomainTest.php`
  - Spec ref: AC-10, F12
  - Done quando: `admin.acho.test` → 404

- [ ] TASK-48 — Criar `tests/Feature/Tenant/RootDomainTest.php`
  - Spec ref: AC-10
  - Done quando: raiz → 200; www → 301

- [ ] TASK-49 — Verificar cobertura ≥70% e ajustar se necessário
  - Spec ref: AC-14
  - Done quando: relatório atinge o limite

### Fechamento

- [ ] TASK-50 — Executar `make lint` e `make test`; corrigir; rodar `make fresh && make test`
  - Spec ref: AC-14
  - Done quando: ambos saem sem erro em build limpo

## Ordem sugerida de implementação

1. TASK-01 → TASK-02 → TASK-06 (infra base)
2. TASK-03 → TASK-04 → TASK-05 (qualidade)
3. TASK-07 → TASK-08 → TASK-09 → TASK-10 → TASK-11 → TASK-12 (dependências + config)
4. TASK-13 (banco)
5. TASK-14 → TASK-15 → TASK-16 → TASK-17 → TASK-18 → TASK-19 (domain)
6. TASK-20 → TASK-21 (validação)
7. TASK-22 → TASK-23 → TASK-24 (service + eventos)
8. TASK-27 → TASK-25 → TASK-26 → TASK-28 (middlewares + provider)
9. TASK-29 → TASK-30 → TASK-31 (erros)
10. TASK-32 → TASK-33 → TASK-34 → TASK-35 (rotas + views)
11. TASK-36 → TASK-37 (seeder)
12. TASK-38 → TASK-39 → TASK-40 → TASK-41 → TASK-42 → TASK-43 → TASK-44 → TASK-45 → TASK-46 → TASK-47 → TASK-48 (testes)
13. TASK-49 (cobertura)
14. TASK-50 (fechamento)

## Pontos de incerteza técnica

- TASK-08 — Configuração mínima do `tenancy.php`; pode exigir iteração até desligar tudo sem quebrar o init
- TASK-12 — Laravel 13 usa `bootstrap/app.php` novo; validar API de registro de middleware/routes
- TASK-22 — Serialização do Model no cache (`Cache::remember`) precisa preservar cast do enum
- TASK-25/TASK-26 — Ordem dos middlewares importa; `TenantResolver` antes de qualquer middleware que toque `pgsql`
- TASK-28 — `config('session.domain')` em runtime; Plano B: middleware dedicado para Cookie
- TASK-30 — `Handler.php` mudou em Laravel 13 (configurado via `->withExceptions()` no bootstrap)
- TASK-45 — Provar RLS exige rodar query em `pgsql` (não `pgsql_migrator`); cuidado para não testar com role errada
