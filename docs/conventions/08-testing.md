# Convenção: Testes

## Stack

- **Pest** — testing framework (sucessor do PHPUnit)
- **Laravel Test Helpers** — factories, http test, etc.
- **PHPUnit** por baixo (Pest é uma camada sobre)
- **Vitest** (futuro) para testes de frontend

---

## Pirâmide de testes

```
       ╱─────────╲
      ╱   E2E     ╲       10%   (apenas critical paths)
     ╱─────────────╲
    ╱   Feature     ╲     40%   (fluxos completos via HTTP)
   ╱─────────────────╲
  ╱     Unit          ╲   50%   (Services, Data, Rules)
 ╱─────────────────────╲
```

**No MVP:** focar em **Unit + Feature**. E2E (Dusk) fica para depois.

---

## Cobertura mínima

| Tipo de código              | Cobertura mínima  |
|-----------------------------|-------------------|
| Services (lógica de negócio)| **90%**           |
| Form Requests               | **80%**           |
| Custom validation rules     | **100%**          |
| Policies                    | **100%**          |
| Models (lógica não-trivial) | **70%**           |
| Controllers                 | Coberto via Feature tests |
| Helpers / Support           | **80%**           |
| **Geral do projeto**        | **70%+**          |

Crítico para multi-tenancy: **isolamento entre tenants tem cobertura de 100%**.

---

## Estrutura de pastas

```
tests/
├── Feature/                         # Testes de fluxo HTTP completo
│   ├── Auth/
│   │   ├── LoginTest.php
│   │   ├── RegisterTest.php
│   │   └── PasswordResetTest.php
│   ├── Tenant/
│   │   ├── CreateTenantTest.php
│   │   └── TenantIsolationTest.php  # CRÍTICO
│   ├── Imovel/
│   │   └── CreateImovelTest.php
│   └── Public/
│       └── ListagemPublicaTest.php
│
├── Unit/                            # Testes unitários
│   ├── Services/
│   │   ├── CreateTenantServiceTest.php
│   │   └── AvailabilityServiceTest.php
│   ├── Data/
│   │   └── CreateImovelDataTest.php
│   ├── Rules/
│   │   ├── ValidCnpjTest.php
│   │   └── StrongPasswordTest.php
│   └── Policies/
│       └── ImovelPolicyTest.php
│
├── Pest.php                         # Configuração global do Pest
└── TestCase.php                     # Base TestCase do Laravel
```

---

## Convenções de nomenclatura

### Arquivo de teste

`{ClasseTestada}Test.php`:

```
App\Services\CreateTenantService → tests/Unit/Services/CreateTenantServiceTest.php
App\Rules\ValidCnpj             → tests/Unit/Rules/ValidCnpjTest.php
```

### Nome do teste (Pest)

Use `it()` ou `test()` com descrição em inglês, no formato "it does X":

```php
// ✅ Bom
it('creates tenant with valid data', function () { ... });
it('rejects invalid cnpj', function () { ... });
it('isolates data between tenants', function () { ... });

// ❌ Ruim
it('test1', function () { ... });
it('createTenant', function () { ... });
it('Should create tenant when data is valid', function () { ... });  // verboso
```

---

## Exemplos: testes unitários

### Service simples

```php
// tests/Unit/Services/CreateTenantServiceTest.php

use App\Data\Tenant\CreateTenantData;
use App\Models\Tenant;
use App\Services\Tenant\CreateTenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates tenant with admin user atomically', function () {
    $service = app(CreateTenantService::class);

    $data = new CreateTenantData(
        razaoSocial: 'Primo Imóveis Ltda',
        cnpj: '11222333000181',
        email: 'admin@primoimoveis.com',
        senha: 'SenhaForte123!',
        slug: 'primoimoveis',
        telefone: '21987654321',
    );

    $result = $service->execute($data);

    expect($result->tenant)->toBeInstanceOf(Tenant::class)
        ->and($result->tenant->slug)->toBe('primoimoveis')
        ->and($result->tenant->cnpj)->toBe('11222333000181')
        ->and($result->user->email)->toBe('admin@primoimoveis.com')
        ->and($result->user->hasRole('admin'))->toBeTrue();
});

it('rolls back if user creation fails', function () {
    // ... força erro na criação do usuário
    expect(Tenant::count())->toBe(0);
});

it('dispatches TenantCreated event', function () {
    Event::fake();

    app(CreateTenantService::class)->execute(/* ... */);

    Event::assertDispatched(TenantCreated::class);
});
```

### Validation rule

```php
// tests/Unit/Rules/ValidCnpjTest.php

use App\Rules\ValidCnpj;

it('accepts valid numeric cnpj', function (string $cnpj) {
    $rule = new ValidCnpj();
    expect($rule->passes('cnpj', $cnpj))->toBeTrue();
})->with([
    '11.222.333/0001-81',
    '11222333000181',
    '12345678000195',
]);

it('accepts valid alphanumeric cnpj (post-2026 format)', function (string $cnpj) {
    $rule = new ValidCnpj();
    expect($rule->passes('cnpj', $cnpj))->toBeTrue();
})->with([
    'AB12C3D4/0E9F-45',
    // mais exemplos do simulador da Receita
]);

it('rejects invalid cnpj', function (string $cnpj) {
    $rule = new ValidCnpj();
    expect($rule->passes('cnpj', $cnpj))->toBeFalse();
})->with([
    '11111111111111',           // dígitos repetidos
    '12345678000100',           // DV incorreto
    '123',                      // muito curto
    'invalido',                 // formato errado
]);
```

---

## Exemplos: testes de feature

### Endpoint público

```php
// tests/Feature/Auth/RegisterTest.php

use App\Models\Tenant;

uses(RefreshDatabase::class);

it('registers new tenant with valid data', function () {
    $response = $this->postJson('/api/cadastro', [
        'razao_social' => 'Casa Nova Imóveis',
        'cnpj' => '11222333000181',
        'email' => 'admin@casanova.com',
        'senha' => 'SenhaForte123!',
        'slug' => 'casanova',
        'telefone' => '21987654321',
        'aceite_termos' => true,
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('tenants', [
        'slug' => 'casanova',
        'cnpj' => '11222333000181',
    ]);
});

it('rejects duplicate slug', function () {
    Tenant::factory()->create(['slug' => 'casanova']);

    $response = $this->postJson('/api/cadastro', [
        'slug' => 'casanova',
        // ... outros campos válidos
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['slug']);
});

it('rejects reserved slugs', function (string $slug) {
    $response = $this->postJson('/api/cadastro', [
        'slug' => $slug,
        // ... outros campos válidos
    ]);

    $response->assertStatus(422);
})->with([
    'admin', 'www', 'api', 'app', 'mail',
]);
```

### Teste de isolamento entre tenants (CRÍTICO)

```php
// tests/Feature/Tenant/TenantIsolationTest.php

uses(RefreshDatabase::class);

it('does not leak imoveis between tenants', function () {
    $tenantA = Tenant::factory()->create(['slug' => 'tenant-a']);
    $tenantB = Tenant::factory()->create(['slug' => 'tenant-b']);

    $imovelA = Imovel::factory()->for($tenantA)->create(['titulo' => 'Casa do Tenant A']);
    $imovelB = Imovel::factory()->for($tenantB)->create(['titulo' => 'Casa do Tenant B']);

    $userA = User::factory()->for($tenantA)->admin()->create();

    // Logado como admin do Tenant A, acessando subdomínio do Tenant A
    $this->actingAs($userA);
    setTenantContext($tenantA);

    $response = $this->getJson('/api/imoveis');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment(['titulo' => 'Casa do Tenant A']);
    $response->assertJsonMissing(['titulo' => 'Casa do Tenant B']);
});

it('blocks cross-tenant access via direct id', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $imovelB = Imovel::factory()->for($tenantB)->create();
    $userA = User::factory()->for($tenantA)->admin()->create();

    $this->actingAs($userA);
    setTenantContext($tenantA);

    // Tenta acessar imóvel do Tenant B usando ID direto
    $response = $this->getJson("/api/imoveis/{$imovelB->id}");

    $response->assertStatus(404);  // RLS impede de ver
});
```

### Endpoint autenticado com Policy

```php
it('allows admin to update any imovel of tenant', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->for($tenant)->admin()->create();
    $corretor = User::factory()->for($tenant)->corretor()->create();
    $imovel = Imovel::factory()->for($tenant)->create(['corretor_id' => $corretor->id]);

    $this->actingAs($admin);
    setTenantContext($tenant);

    $response = $this->putJson("/api/imoveis/{$imovel->id}", [
        'titulo' => 'Novo título',
    ]);

    $response->assertOk();
    expect($imovel->fresh()->titulo)->toBe('Novo título');
});

it('forbids corretor to update imovel of another corretor', function () {
    $tenant = Tenant::factory()->create();
    $corretorA = User::factory()->for($tenant)->corretor()->create();
    $corretorB = User::factory()->for($tenant)->corretor()->create();
    $imovel = Imovel::factory()->for($tenant)->create(['corretor_id' => $corretorB->id]);

    $this->actingAs($corretorA);
    setTenantContext($tenant);

    $response = $this->putJson("/api/imoveis/{$imovel->id}", [
        'titulo' => 'Tentando alterar',
    ]);

    $response->assertStatus(403);
});
```

---

## Factories

Factories vivem em `database/factories/`. Use **states** para variações:

```php
// database/factories/UserFactory.php

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => $this->faker->uuid,
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'password' => Hash::make('SenhaForte123!'),
        ];
    }

    public function admin(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->assignRole('admin');
        });
    }

    public function corretor(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->assignRole('corretor');
        });
    }

    public function cliente(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->assignRole('cliente');
        });
    }
}
```

Uso:

```php
$admin = User::factory()->admin()->create();
$corretor = User::factory()->corretor()->for($tenant)->create();
$cliente = User::factory()->cliente()->create();
```

---

## Arrange-Act-Assert

Estrutura recomendada para todo teste:

```php
it('does X when Y', function () {
    // Arrange — setup do contexto
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->admin()->create();

    // Act — ação testada
    $response = $this->actingAs($user)->postJson('/api/imoveis', [
        'titulo' => 'Casa',
    ]);

    // Assert — verificações
    $response->assertStatus(201);
    expect($tenant->imoveis()->count())->toBe(1);
});
```

Linhas em branco separando seções deixam claro onde cada parte começa.

---

## Mocks e Fakes

### Event::fake()

```php
it('dispatches event', function () {
    Event::fake([TenantCreated::class]);

    // executa código

    Event::assertDispatched(TenantCreated::class);
});
```

### Mail::fake()

```php
it('sends welcome email', function () {
    Mail::fake();

    $service->execute(/* ... */);

    Mail::assertSent(BoasVindasMail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email);
    });
});
```

### Queue::fake()

```php
it('queues image processing job', function () {
    Queue::fake();

    // upload

    Queue::assertPushed(ProcessImovelImage::class);
});
```

### HTTP fake

```php
it('handles pagarme api error', function () {
    Http::fake([
        'pagarme.com/*' => Http::response([], 500),
    ]);

    expect(fn() => $service->cobrar(/* ... */))
        ->toThrow(PaymentFailedException::class);
});
```

---

## Helpers customizados

Funções globais em `tests/Pest.php`:

```php
// tests/Pest.php

use App\Models\Tenant;

uses(
    Tests\TestCase::class,
    Illuminate\Foundation\Testing\RefreshDatabase::class,
)->in('Feature', 'Unit');

function setTenantContext(Tenant $tenant): void
{
    app()->instance('currentTenant', $tenant);
    DB::statement("SET app.tenant_id = ?", [$tenant->id]);
}

function actingAsAdmin(Tenant $tenant): User
{
    $admin = User::factory()->for($tenant)->admin()->create();
    test()->actingAs($admin);
    setTenantContext($tenant);
    return $admin;
}
```

---

## Performance

### Use RefreshDatabase com cuidado

`RefreshDatabase` reseta o banco entre testes. Para suítes grandes, considere `DatabaseTransactions` (mais rápido).

### Paralelização

Pest suporta execução paralela:

```bash
./vendor/bin/pest --parallel
```

Configure threads conforme cores da CPU.

### Database em memória (SQLite)

Para testes ultra-rápidos, configure SQLite in-memory em `phpunit.xml`:

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

**Cuidado:** algumas features de PostgreSQL (RLS, JSONB) não funcionam em SQLite. Use Postgres para testes que precisam dessas features.

---

## CI

Em CI (GitHub Actions, ver `ADR-018`):

```yaml
- name: Run tests
  run: ./vendor/bin/pest --parallel --coverage --min=70
  env:
    DB_CONNECTION: pgsql
    DB_HOST: localhost
    # ...
```

PR não merja se cobertura cair abaixo do mínimo ou testes falharem.

---

## O que NÃO testar

Para evitar testes inúteis:

- ❌ Getters e setters triviais
- ❌ Configurações do framework (já testado pelo Laravel)
- ❌ Bibliotecas de terceiros (não é nosso código)
- ❌ Exatamente o output de Mock (você está testando o mock, não o código)

---

## Checklist de teste novo

Ao adicionar feature, pergunte:

- [ ] Cobri o caminho feliz?
- [ ] Cobri os principais erros possíveis?
- [ ] Cobri permissões (Policy)?
- [ ] Cobri isolamento entre tenants (se aplicável)?
- [ ] Mock de serviços externos?
- [ ] Eventos disparados foram verificados?

---

## Referências

- ADRs relacionadas: `ADR-018` (Git Strategy), `ADR-019` (Tech Stack), `ADR-025` (Project Patterns)
- Outras conventions: `01-architecture.md`, `04-database.md`
- Pest: https://pestphp.com
