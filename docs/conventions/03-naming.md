# Convenção: Nomenclatura

## Idioma

**Domínio em português, técnico em inglês.**

Termos do **negócio** ficam em português:

```
Imovel, Corretor, Agendamento, Bairro, Visita,
Imobiliaria, Inquilino, Comprador
```

Termos **técnicos** ficam em inglês:

```
User, Tenant, Subscription, Plan, Token, Webhook,
Notification, Event, Listener, Service
```

**Por quê:**

- Domínio em português evita tradução errada e ambiguidade ("realtor" vs "corretor")
- Técnico em inglês mantém alinhamento com Laravel, libs e ecossistema
- Mistura é proposital — cada palavra na língua que faz mais sentido

**Exemplos válidos:**

```php
class CreateImovelService            // CreateImovel (domínio) + Service (técnico)
class TenantSuspended                // Tenant (técnico) + Suspended (técnico)
class ImovelStatus                   // enum
class Bairro extends Model
```

**Não misture na mesma palavra:**

```php
class CriarImovelService             // ❌ "Criar" + "Service"
class CreatePropertyService          // ❌ "Property" sendo o domínio
```

---

## Classes (PHP)

**PascalCase**, descritivo, sem abreviações.

```php
// ✅ Bom
class CreateTenantService
class ImovelPolicy
class ValidCnpj
class StoreImovelRequest

// ❌ Ruim
class CTService                      // abreviação obscura
class imovel_policy                  // case errado
class Validator                      // genérico demais
```

### Sufixos por tipo

Use sufixos consistentes para indicar o tipo:

| Sufixo            | Tipo                          | Exemplo                            |
|-------------------|-------------------------------|------------------------------------|
| `Service`         | Lógica de negócio             | `CreateImovelService`              |
| `Request`         | Form Request                  | `StoreImovelRequest`               |
| `Resource`        | API Resource (transformer)    | `ImovelResource`                   |
| `Policy`          | Authorization policy          | `ImovelPolicy`                     |
| `Notification`    | Laravel Notification          | `BoasVindasNotification`           |
| `Job`             | Queue job                     | `EndExpiredTrialsJob`              |
| `Listener`        | Event listener                | `NotificarCorretorListener`        |
| `Data`            | DTO (Spatie Laravel Data)     | `CreateImovelData`                 |
| `Exception`       | Exception customizada         | `TenantNotFoundException`          |
| `Rule`            | Validation rule               | `ValidCnpjRule` (ou só `ValidCnpj`)|
| `Provider`        | Service provider              | `TenantServiceProvider`            |

### Sem sufixo

| Tipo                       | Padrão                                  |
|----------------------------|-----------------------------------------|
| Models                     | Singular: `Imovel`, `Tenant`, `User`    |
| Eventos                    | Verbo no passado: `ImovelCreated`       |
| Controllers                | `XxxController`                         |
| Middleware                 | `XxxMiddleware` opcional                |

---

## Métodos (PHP)

**camelCase**, verbo no início.

```php
// ✅ Bom
public function execute(CreateImovelData $data): Imovel
public function findBySlug(string $slug): ?Tenant
public function isActive(): bool
public function hasPermission(string $permission): bool

// ❌ Ruim
public function imovel($data)                    // sem verbo
public function execute_imovel()                 // snake_case
public function ImovelCreate()                   // PascalCase
```

### Padrões comuns

| Padrão            | Uso                                     |
|-------------------|-----------------------------------------|
| `execute`         | Método principal de Service             |
| `handle`          | Método principal de Job/Listener        |
| `is{X}`           | Retorna boolean (`isActive`)            |
| `has{X}`          | Retorna boolean (`hasRole`)             |
| `can{X}`          | Retorna boolean (`canEdit`)             |
| `get{X}`          | Retorna valor (raro, prefira propriedades) |
| `find{X}`         | Busca, pode retornar null               |
| `findOrFail{X}`   | Busca, throw se não achar               |
| `create{X}`       | Cria nova entidade                      |
| `update{X}`       | Atualiza entidade existente             |
| `delete{X}`       | Remove entidade                         |

---

## Variáveis (PHP)

**camelCase**, descritivo.

```php
// ✅ Bom
$imovelId = $request->imovel_id;
$tenantsAtivos = Tenant::active()->get();
$dataHoraVisita = $agendamento->data_visita;

// ❌ Ruim
$id = ...;                          // genérico demais
$tnts = ...;                        // abreviação
$tenants_ativos = ...;              // snake_case
```

### Booleans

Sempre prefixe com `is`, `has`, `can`, `should`:

```php
$isActive = true;
$hasPermission = false;
$canEdit = $user->can('edit', $imovel);
$shouldNotify = $config['notifications']['enabled'];
```

---

## Banco de Dados

### Tabelas

**Plural, snake_case, em português para domínio:**

```sql
-- ✅ Bom
imoveis, agendamentos, corretores, bairros,
tenants, users, plans, subscriptions, audit_logs

-- ❌ Ruim
Imoveis                              -- PascalCase
imovel                               -- singular
properties                           -- inglês para domínio
```

### Colunas

**snake_case, descritivo:**

```sql
-- ✅ Bom
id, tenant_id, user_id, created_at, updated_at,
data_visita, preco_centavos, is_active

-- ❌ Ruim
ID                                   -- maiúsculo
tenantId                             -- camelCase
data                                 -- ambíguo
preco                                -- sem unidade clara
```

### Foreign Keys

Padrão: `{tabela_singular}_id`

```sql
tenant_id → tenants.id
user_id → users.id
imovel_id → imoveis.id
corretor_id → users.id (corretor é um user)
```

### Pivot Tables

Ordem alfabética, ambos singulares:

```sql
-- ✅ Bom
imovel_user                          -- ordem alfabética
permission_role                      -- ordem alfabética

-- ❌ Ruim
user_imovel                          -- ordem invertida
imoveis_users                        -- plurais
```

### Booleans

Prefixe com `is_`, `has_`, `can_`:

```sql
is_active, is_published, is_verified,
has_paid, has_completed_onboarding,
can_invite_users
```

### Timestamps

Padrão Laravel + extensões claras:

```sql
created_at, updated_at, deleted_at,    -- padrão Laravel
trial_ends_at, suspended_at, paid_at,  -- timestamps de domínio
last_login_at, password_changed_at     -- timestamps de auditoria
```

### Valores Monetários

Sempre em **centavos**, como `integer`:

```sql
preco_centavos INTEGER NOT NULL       -- ✅ R$ 1.500,00 = 150000
preco DECIMAL(10,2)                   -- ❌ floating point causa bugs
```

Convenção: campo termina com `_centavos` para deixar explícito.

---

## Frontend (TypeScript / React)

### Componentes

**PascalCase**, descritivo:

```tsx
// ✅ Bom
ImovelCard.tsx
ScheduleVisitModal.tsx
FormFieldInput.tsx

// ❌ Ruim
imovelCard.tsx                       // camelCase
imovel-card.tsx                      // kebab-case
Card.tsx                             // genérico demais
```

### Hooks

**camelCase**, prefixo `use`:

```tsx
// ✅ Bom
useAuth, useDebounce, useImovelFilters

// ❌ Ruim
authHook                             // sem prefixo
UseAuth                              // PascalCase
```

### Variáveis e funções

**camelCase**, mesma lógica do PHP:

```tsx
const isActive = true;
const handleSubmit = () => { ... };
const filteredImoveis = imoveis.filter(...);
```

### Constantes

**SCREAMING_SNAKE_CASE** para valores realmente constantes:

```tsx
const MAX_FOTOS_POR_IMOVEL = 10;
const TRIAL_DURATION_DAYS = 14;
const STATUS_OPTIONS = ['ativo', 'pausado', 'vendido'] as const;
```

### Types e Interfaces

**PascalCase**:

```tsx
type Imovel = { ... };
interface UserSession { ... };
type ImovelStatus = 'disponivel' | 'reservado' | 'vendido';
```

---

## Branches Git

**`{tipo}/{descrição-curta}` em inglês ou português, kebab-case:**

```
feature/cadastro-imobiliaria
feature/upload-de-fotos
bugfix/email-recovery-not-sending
hotfix/payment-webhook-validation
refactor/extract-tenant-service
chore/update-laravel-13-1
docs/add-runbook-deploy
```

**Tipos válidos:** `feature`, `bugfix`, `hotfix`, `refactor`, `chore`, `docs`, `test`.

---

## Commits (Conventional Commits)

```
feat(imoveis): adiciona galeria de fotos
fix(auth): corrige expiração de refresh token
refactor(scheduling): extrai lógica para AvailabilityService
docs(adr): adiciona ADR-019 sobre stack tecnológica
test(tenant): testa isolamento entre tenants
chore: atualiza dependências do composer
```

**Scopes comuns:** `auth`, `tenant`, `imoveis`, `agendamentos`, `payment`, `auth`, `infra`, `deps`, `adr`.

---

## Resumo rápido

| Contexto              | Convenção                                |
|-----------------------|------------------------------------------|
| Classes PHP           | `PascalCase`                             |
| Métodos PHP           | `camelCase`                              |
| Variáveis PHP         | `camelCase`                              |
| Constantes PHP        | `SCREAMING_SNAKE_CASE`                   |
| Tabelas               | `plural_snake_case` (português domínio)  |
| Colunas               | `snake_case`                             |
| Componentes React     | `PascalCase`                             |
| Hooks React           | `camelCase` com prefixo `use`            |
| Branches              | `tipo/kebab-case`                        |
| Commits               | Conventional Commits                     |

---

## Referências

- ADR relacionada: `ADR-025` (Project Patterns)
- Outras conventions: `02-folder-structure.md`, `04-database.md`
