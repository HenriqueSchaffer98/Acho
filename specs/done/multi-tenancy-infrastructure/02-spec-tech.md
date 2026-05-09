# Spec Técnica: Infraestrutura de Multi-Tenancy

**Versão:** 1.0
**Status:** done
**Spec funcional:** specs/wip/multi-tenancy-infrastructure/01-spec-functional.md

## Decisões de Arquitetura

**1. Camadas (ADR-025)**
- Request → `TenantResolver` (middleware) → `SetTenantContext` (middleware) → Controller thin → Service → Model → DB.
- Middlewares aplicados apenas em rotas dentro de `routes/tenant.php` (subdomínio dinâmico). Rotas em `routes/web.php` (landing) não passam por eles.

**2. `stancl/tenancy ^3.10` em modo single-database**
- Adotado conforme ADR-001. Versão verificada como compatível com Laravel 13 (constraint do pacote: `illuminate/support ^12.0|^13.0`).
- Configurado em **single-database mode** — sem switching de connection por tenant, sem schemas separados. Apenas `tenant_id` + RLS.
- Usaremos `InitializeTenancyByDomain` adaptado, mas o middleware concreto fica como classe própria do projeto (`TenantResolver`) chamando o pacote internamente. Isolamos a dependência atrás dos nossos middlewares para reduzir acoplamento à API do pacote.
- Bootstrappers do pacote desligados (`tenancy.bootstrappers = []`); usamos os nossos (`SetTenantContext` injeta `app.tenant_id` no Postgres).

**3. RLS em duas roles Postgres**
- `acho_app` — role da aplicação. SEM `BYPASSRLS`. Usada por `DB::connection('pgsql')` em todas as requests HTTP.
- `acho_migrator` — role privilegiada COM `BYPASSRLS`. Usada por `DB::connection('pgsql_migrator')` em migrations, seeds, lookup inicial de tenant e jobs administrativos.
- Configuradas no init script do Postgres (`docker/postgres/init.sql`).

**4. Scope automático via `BaseTenantModel`**
- Implementação custom (não usa Models do `stancl/tenancy`).
- `protected static function booted()` registra `addGlobalScope(new TenantScope)` e listener `creating` que preenche `tenant_id`.
- `withoutTenantScope()` é método estático que retorna builder sem o global scope.

**5. Resolução de tenant**
- `TenantService::resolveBySlug()` consulta cache (`Cache::remember("tenant:{slug}", 60, ...)`).
- Cache miss → query no banco usando conexão `pgsql_migrator` (sem RLS — caso contrário, o próprio lookup seria bloqueado por não haver `app.tenant_id` setado ainda).
- Importante: a query de lookup usa role privilegiada **apenas para o lookup**; o request real usa `acho_app` e o `app.tenant_id` setado pelo `SetTenantContext`.

**6. Cookies de sessão por subdomínio**
- `config/session.php`: `domain` setado dinamicamente via `TenantServiceProvider` que lê `request()->getHost()` quando estamos em contexto de tenant.
- Em rotas de raiz (`acho.test`), `domain` fica como o domínio raiz.
- Driver de sessão: **Redis** (`SESSION_DRIVER=redis`).

**7. DNS local com `dnsmasq`**
- Não é código do projeto; decisão técnica documentada.
- Runbook `docs/runbooks/local-setup.md` instrui o setup no macOS.
- Padrão: `*.acho.test` → `127.0.0.1`.

**8. Status do tenant via PHP Enum**
- `App\Enums\TenantStatus` com cases `Active` (`'active'`) e `Suspended` (`'suspended'`).
- Cast no Model: `protected $casts = ['status' => TenantStatus::class]`.
- Constraint check no banco garante apenas valores válidos.

**9. Erros de tenant via Exception Handler**
- `TenantNotFoundException` e `TenantSuspendedException` capturadas em `app/Exceptions/Handler.php`.
- Renderizam views específicas (`tenant.not-found`, `tenant.suspended`) com status code 404 e 403 respectivamente.
- Não usam a página 404 padrão do Laravel.

## Stack e Dependências

**Composer require (novas):**
- `stancl/tenancy: ^3.10` — multi-tenancy single-database (ADR-001), confirmado compatível com Laravel 13
- `spatie/laravel-data: ^4` — DTOs (ADR-025), necessário para `TenantData`

**Composer require-dev (novas):**
- `larastan/larastan: ^3` — análise estática nível 8 (ADR-025)
- `laravel/pint: ^1` — formatação Laravel

**Já presentes (skeleton Laravel 13):**
- `laravel/framework: ^13`
- `pestphp/pest: ^3` — testes
- `predis/predis` ou ext-redis — cache de tenant + driver de sessão

**Sem novas dependências de NPM** — esta etapa não toca front.

## Estrutura de Arquivos

**Criar:**

```
app/
├── Data/
│   └── Tenant/
│       └── TenantData.php
├── Enums/
│   └── TenantStatus.php
├── Events/
│   └── Tenant/
│       ├── TenantCreated.php
│       ├── TenantUpdated.php
│       ├── TenantSuspended.php
│       └── TenantReactivated.php
├── Exceptions/
│   ├── Handler.php                          (modificado — render de exceções de tenant)
│   ├── TenantNotFoundException.php
│   └── TenantSuspendedException.php
├── Http/
│   ├── Controllers/
│   │   └── Public/
│   │       └── HomeController.php           (responde acho.test raiz)
│   └── Middleware/
│       ├── TenantResolver.php
│       └── SetTenantContext.php
├── Listeners/
│   └── Tenant/
│       └── InvalidateTenantCache.php
├── Models/
│   ├── BaseTenantModel.php
│   ├── Scopes/
│   │   └── TenantScope.php
│   └── Tenant.php
├── Providers/
│   ├── EventServiceProvider.php             (mapeia eventos → listeners)
│   └── TenantServiceProvider.php            (configura session domain dinâmico)
├── Rules/
│   └── ReservedSlug.php
├── Services/
│   └── Tenant/
│       └── TenantService.php
└── Support/
    └── SubdomainHelper.php

config/
├── reserved_slugs.php                       (lista da ADR-016)
├── session.php                              (modificado — domain dinâmico via provider)
├── database.php                             (modificado — adiciona pgsql_migrator)
└── tenancy.php                              (publicado pelo stancl/tenancy)

database/
├── migrations/
│   └── 2026_04_30_000000_create_tenants_table.php
└── seeders/
    ├── DatabaseSeeder.php                   (modificado — chama TenantSeeder)
    └── TenantSeeder.php                     (cria teste-interno)

docker/
└── postgres/
    └── init.sql                             (cria roles acho_app e acho_migrator)

resources/
└── views/
    ├── public/
    │   └── home.blade.php                   ("Acho — plataforma em construção")
    └── tenant/
        ├── not-found.blade.php              (404)
        └── suspended.blade.php              (403)

routes/
├── web.php                                  (modificado — rota raiz e redirect www)
└── tenant.php                               (rotas dentro de subdomínio com middleware group)

tests/
├── Feature/
│   └── Tenant/
│       ├── ResolveTenantBySubdomainTest.php
│       ├── TenantIsolationTest.php
│       ├── TenantCacheTest.php
│       ├── TenantStatusTest.php
│       ├── ReservedSubdomainTest.php
│       └── RootDomainTest.php
├── Unit/
│   ├── Models/
│   │   └── BaseTenantModelTest.php
│   ├── Rules/
│   │   └── ReservedSlugTest.php
│   └── Services/
│       └── TenantServiceTest.php
├── Support/
│   └── ProbeMigration.php                   (cria/derruba tabela probe em setUp/tearDown)
└── TestCase.php                             (modificado — helpers para tenant context)

docs/
└── runbooks/
    └── local-setup.md                       (DNS dnsmasq + roles Postgres)
```

**Modificar:**

- `composer.json` — adicionar deps
- `bootstrap/app.php` — registrar middleware aliases e route groups (Laravel 13 usa bootstrap/app.php centralizado)
- `bootstrap/providers.php` — registrar `TenantServiceProvider`, `EventServiceProvider`
- `config/database.php` — adicionar conexão `pgsql_migrator`
- `config/session.php` — driver `redis` e `domain` ajustável dinamicamente
- `database/seeders/DatabaseSeeder.php` — chamar `TenantSeeder`
- `phpunit.xml` — env de teste com Postgres
- `.env.example` — variáveis de roles separadas
- `phpstan.neon` — Larastan 8 (criado)
- `pint.json` — config Pint (criado)
- `Makefile` — `make lint/test/fresh/analyze/up/down` (criado)

## Modelos e Schemas

**Tabela `tenants`** (sem `tenant_id` — é a própria entidade tenant)

```php
Schema::create('tenants', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('slug', 30)->unique();
    $table->string('name', 200);
    $table->string('custom_domain', 255)->nullable()->unique();
    $table->timestamp('domain_verified_at')->nullable();
    $table->string('status', 20)->default('active');
    $table->timestamps();
    $table->softDeletes();
});

DB::statement("
    ALTER TABLE tenants
    ADD CONSTRAINT check_tenant_status
    CHECK (status IN ('active', 'suspended'))
");
```

`slug` já é único via `unique()` (cria index automaticamente). **Sem RLS na tabela `tenants`** — é tabela "pai", lookup acontece antes de qualquer contexto de tenant.

**Tabela `tenant_isolation_probes`** (apenas em ambiente de teste — criada via helper em `tests/Support/ProbeMigration.php`):

```php
Schema::create('tenant_isolation_probes', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('tenant_id');
    $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
    $table->string('label', 100);
    $table->timestamps();
    $table->softDeletes();
    $table->index('tenant_id');
});

DB::statement('ALTER TABLE tenant_isolation_probes ENABLE ROW LEVEL SECURITY');
DB::statement("
    CREATE POLICY tenant_isolation ON tenant_isolation_probes
    USING (tenant_id = current_setting('app.tenant_id', true)::uuid)
    WITH CHECK (tenant_id = current_setting('app.tenant_id', true)::uuid)
");
```

**Importante:** uso de `WITH CHECK` além de `USING` — protege também INSERT/UPDATE de cross-tenant. A ADR-001 não detalha; decisão técnica desta spec.

**`TenantStatus` (Enum PHP)**

```php
enum TenantStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
```

**`TenantData` (DTO)**

```php
class TenantData extends Data
{
    public function __construct(
        public string $id,
        public string $slug,
        public string $name,
        public ?string $customDomain,
        public ?string $domainVerifiedAt,
        public TenantStatus $status,
    ) {}
}
```

**`Tenant` (Model)**

```php
class Tenant extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['slug', 'name', 'custom_domain', 'status'];

    protected $casts = [
        'status' => TenantStatus::class,
        'domain_verified_at' => 'datetime',
    ];

    protected $dispatchesEvents = [
        'created' => TenantCreated::class,
        'updated' => TenantUpdated::class,
    ];

    public function suspend(): void
    {
        $this->update(['status' => TenantStatus::Suspended]);
        TenantSuspended::dispatch($this);
    }

    public function reactivate(): void
    {
        $this->update(['status' => TenantStatus::Active]);
        TenantReactivated::dispatch($this);
    }

    public function isActive(): bool { return $this->status === TenantStatus::Active; }
    public function isSuspended(): bool { return $this->status === TenantStatus::Suspended; }
}
```

**`BaseTenantModel`**

```php
abstract class BaseTenantModel extends Model
{
    use HasUuids, SoftDeletes;

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
        static::creating(function (BaseTenantModel $model) {
            if (! $model->tenant_id && app()->bound('currentTenant')) {
                $model->tenant_id = app('currentTenant')->id;
            }
        });
    }

    public static function withoutTenantScope(): Builder
    {
        return static::query()->withoutGlobalScope(TenantScope::class);
    }
}
```

**`TenantScope`**

```php
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! app()->bound('currentTenant')) {
            return; // sem tenant ativo, RLS protege
        }
        $builder->where(
            $model->getTable() . '.tenant_id',
            app('currentTenant')->id
        );
    }
}
```

## Contrato de API

Sem API REST nesta etapa. Pontos de superfície:

**Rotas web (`routes/web.php` — domínio raiz):**
- `GET /` (host = `acho.test`) → `HomeController@show` — view `public.home`
- `GET /` (host = `www.acho.test`) → redirect 301 → `https://acho.test/`

**Rotas tenant (`routes/tenant.php` — subdomínio dinâmico):**
- Group com middleware `[TenantResolver, SetTenantContext]`
- Subdomínio dinâmico: `Route::domain('{slug}.acho.test')`
- Rota mínima nesta etapa: `GET /` → resposta 200 com mensagem "Tenant {slug} resolvido" (placeholder até a vitrine entrar)

**Renderizadas pelo Exception Handler:**
- `TenantNotFoundException` → view `tenant.not-found`, status 404
- `TenantSuspendedException` → view `tenant.suspended`, status 403

**Comandos artisan novos:**
- Nenhum nesta etapa (futuro: `tenant:list`, `tenant:audit-rls`).

## Riscos Técnicos

- **R1: `stancl/tenancy` em modo single-database** — pacote tem fluxos próprios (Tenancy facade, eventos próprios) que podem conflitar com nossa abordagem. **Mitigação:** isolar uso atrás dos nossos middlewares; usar apenas `Tenancy::initialize()` mínimo. Documentar em comentário no `TenantServiceProvider`.

- **R2: Lookup de tenant em request HTTP usa role privilegiada** — `TenantService::resolveBySlug` precisa rodar fora de RLS para encontrar o tenant antes de setar `app.tenant_id`. **Mitigação:** conexão dedicada `pgsql_migrator` usada **apenas** para o método de lookup; chamada explícita via `DB::connection('pgsql_migrator')->...`. Auditável.

- **R3: Cache stale de tenant após suspensão** — TTL 60s aceito pela ADR-016. Listener `InvalidateTenantCache` precisa rodar síncrono para que a próxima request reflita a mudança imediatamente. **Mitigação:** listener síncrono (não implementa `ShouldQueue`).

- **R4: `request()->getHost()` em CLI** — quando `php artisan` roda, não há request. **Mitigação:** middlewares só aplicados em route groups HTTP; código CLI usa `Tenant::find()` direto na conexão `pgsql_migrator`.

- **R5: Larastan 8 + `stancl/tenancy`** — pacote tem tipagem fraca em algumas APIs. **Mitigação:** baseline inicial tolerada para o pacote (`larastan-baseline.neon`); código nosso obrigado a passar sem baseline.

- **R6: Race entre criação de tenant e primeira request** — janela onde tenant existe no banco mas cache pode ter `null` cacheado. **Mitigação:** `Cache::remember` só cacheia hit; misses não são cacheados (chave simplesmente não existe).

- **R7: Cookies de sessão e CSRF token entre subdomínios** — config dinâmica de `domain` precisa ser feita antes do session middleware. **Mitigação:** `TenantServiceProvider::boot()` ajusta `config('session.domain')` lendo o `Host` da request — registrado com prioridade alta no boot.

- **R8: SoftDelete em `tenants` + Foreign Keys com `ON DELETE CASCADE`** — soft delete não dispara cascade real. **Mitigação:** convivem; cascade só ocorre em hard delete (`forceDelete`). Documentado.

- **R9: Laravel 13 — bootstrap centralizado** — `bootstrap/app.php` substitui Kernel HTTP e Console na versão atual. **Mitigação:** registrar middleware aliases e route groups via API nova (`->withRouting()` e `->withMiddleware()`); `EventServiceProvider` ainda existe mas é discricionário.

- **R10: Pacote `stancl/tenancy` v3 e Laravel 13** — pacote suporta a versão (constraint `^12.0|^13.0` confirmada), mas pode ter peculiaridades em features que não usamos. **Mitigação:** desabilitar features não usadas (queue isolation, asset routes, mail isolation) na config; usar apenas Tenancy initializer.

## Dúvidas Técnicas em Aberto

Nenhuma. Todas as 5 dúvidas foram resolvidas:

1. `stancl/tenancy ^3.10` — confirmado compatível com Laravel 13 via Packagist
2. `HomeController` com view Blade simples ("Acho — plataforma em construção")
3. `SESSION_DRIVER=redis` desde o início
4. `App\Enums\TenantStatus` (Active/Suspended) com cast automático no Model
5. Exception Handler captura `TenantNotFoundException`/`TenantSuspendedException` e renderiza views próprias
