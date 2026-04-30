# Convenção: Banco de Dados

## Stack

- **PostgreSQL 16** com Row-Level Security
- **Migrations** via Laravel
- **UUIDs** como Primary Keys
- **Soft Delete** universal em tabelas de negócio

Decisões em `ADR-001` (Database Strategy).

---

## Estrutura padrão de tabela de negócio

Toda tabela de negócio (que pertence a um tenant) segue este padrão:

```sql
CREATE TABLE imoveis (
    id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id     UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,

    -- colunas específicas do domínio
    titulo        VARCHAR(200) NOT NULL,
    preco_centavos INTEGER NOT NULL,
    -- ...

    -- timestamps obrigatórios
    created_at    TIMESTAMP NOT NULL,
    updated_at    TIMESTAMP NOT NULL,
    deleted_at    TIMESTAMP NULL
);

-- Index obrigatório no tenant_id
CREATE INDEX idx_imoveis_tenant ON imoveis(tenant_id);

-- RLS habilitado
ALTER TABLE imoveis ENABLE ROW LEVEL SECURITY;

-- Política de isolamento
CREATE POLICY tenant_isolation ON imoveis
    USING (tenant_id = current_setting('app.tenant_id', true)::uuid);
```

### Migration equivalente (Laravel)

```php
public function up(): void
{
    Schema::create('imoveis', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('tenant_id');
        $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

        $table->string('titulo', 200);
        $table->integer('preco_centavos');
        // ...

        $table->timestamps();
        $table->softDeletes();

        $table->index('tenant_id');
    });

    DB::statement('ALTER TABLE imoveis ENABLE ROW LEVEL SECURITY');

    DB::statement("
        CREATE POLICY tenant_isolation ON imoveis
            USING (tenant_id = current_setting('app.tenant_id', true)::uuid)
    ");
}

public function down(): void
{
    Schema::dropIfExists('imoveis');
}
```

---

## Tabelas SEM tenant_id

Apenas estas tabelas **não** têm `tenant_id`:

| Tabela              | Por quê                                       |
|---------------------|-----------------------------------------------|
| `tenants`           | A própria entidade tenant                     |
| `plans`             | Compartilhado entre todos                     |
| `super_admin_users` | Operadores da plataforma, fora de tenant      |
| `webhook_events`    | Logs globais do sistema                       |
| `migrations`        | Sistema do Laravel                            |
| `password_reset_tokens` | Sistema do Laravel                        |
| `personal_access_tokens` | Sistema do Sanctum                       |

Toda **outra** tabela tem `tenant_id` obrigatório.

---

## Primary Keys: UUID

**Por que UUID:**

- Não vazam quantidade (ex: `/imoveis/1234` revela escala)
- Permitem geração no frontend antes de POST
- Evitam conflitos em multi-tenant
- Trade-off: 16 bytes vs 8 bytes (aceitável)

**Como gerar:**

```php
// Em Models que estendem BaseTenantModel:
use HasUuids;

// PostgreSQL gera automaticamente:
$table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
```

---

## Soft Delete

**Toda tabela de negócio** usa `deleted_at`:

```php
$table->softDeletes();
```

```php
class Imovel extends BaseTenantModel
{
    use SoftDeletes;
    // ...
}
```

**Por quê:**

- Permite restore em caso de erro humano
- Mantém integridade referencial (FK não quebra)
- Facilita auditoria (vê o que foi deletado)
- Custo: queries excluem soft-deleted automaticamente

**Quando usar `forceDelete()`:**

- Limpeza de dados em compliance LGPD (exclusão definitiva solicitada)
- Job de limpeza periódica (após N dias soft-deleted)

---

## Indexes

### Quando criar

**Sempre crie index em:**

1. **Foreign keys** (`tenant_id`, `user_id`, etc.)
2. **Colunas de busca frequente** (`slug`, `email`, `cnpj`)
3. **Colunas de ordenação** (`created_at` se queries ordenam por isso)
4. **Colunas em WHERE de queries comuns**

**Crie index composto quando:**

```sql
-- Query frequente: imóveis ativos do tenant ordenados por data
SELECT * FROM imoveis
WHERE tenant_id = ? AND status = 'disponivel'
ORDER BY created_at DESC;

-- Index composto otimiza esse caso:
CREATE INDEX idx_imoveis_tenant_status_created
    ON imoveis (tenant_id, status, created_at DESC);
```

### Padrão de nomenclatura de indexes

```
idx_{tabela}_{colunas}
```

```sql
idx_imoveis_tenant
idx_imoveis_tenant_status
idx_users_email
idx_agendamentos_corretor_data
```

### Indexes únicos

```sql
CREATE UNIQUE INDEX idx_tenants_slug ON tenants(slug);
CREATE UNIQUE INDEX idx_tenants_cnpj ON tenants(cnpj);
CREATE UNIQUE INDEX idx_users_tenant_email
    ON users (tenant_id, email);  -- e-mail único POR tenant
```

---

## Migrations

### Princípio: imutáveis após deploy

Migration que rodou em produção **nunca** é editada. Mudanças geram **nova migration**.

```bash
# ✅ FAZER: nova migration
php artisan make:migration add_descricao_to_imoveis_table

# ❌ NÃO FAZER: editar migration antiga já deployada
```

### Sempre reversíveis

Toda migration tem `down()` funcional:

```php
public function up(): void
{
    Schema::table('imoveis', function (Blueprint $table) {
        $table->text('descricao')->nullable();
    });
}

public function down(): void
{
    Schema::table('imoveis', function (Blueprint $table) {
        $table->dropColumn('descricao');
    });
}
```

### Operações destrutivas

Operações como `DROP COLUMN`, `RENAME` exigem cuidado em produção. Padrão:

1. Migration 1: adiciona nova coluna (compatível)
2. Deploy + período de transição
3. Migration 2: migra dados
4. Migration 3: remove coluna antiga

Nunca quebre compatibilidade num deploy só.

### Backups antes de migrate

CI deve fazer snapshot do banco antes de rodar migrations em produção (ver `ADR-018`).

---

## Convenções de tipo

### Inteiros

```php
$table->integer('preco_centavos');           // valores monetários
$table->unsignedInteger('quantidade');       // contadores
$table->bigInteger('contador_total');        // se for crescer muito
```

### Strings

```php
$table->string('titulo', 200);               // VARCHAR com limite explícito
$table->text('descricao');                   // TEXT (sem limite definido)
$table->string('email', 255);                // padrão e-mail
$table->string('slug', 30);                  // subdomínio limitado
$table->string('cnpj', 18);                  // 14 chars + máscara opcional
```

### Booleans

```php
$table->boolean('is_active')->default(true);
$table->boolean('has_completed_onboarding')->default(false);
```

### Timestamps

```php
$table->timestamps();                        // created_at + updated_at
$table->softDeletes();                       // deleted_at
$table->timestamp('trial_ends_at')->nullable();
$table->timestamp('paid_at')->nullable();
```

### JSON

Usar quando dados são genuinamente flexíveis:

```php
$table->jsonb('metadata');                   // PostgreSQL JSONB (indexável)
$table->jsonb('settings')->default('{}');
```

**Não use JSON para:**

- Dados estruturados que poderiam ser tabela própria
- Dados que serão consultados por valores internos frequentemente

### Enums

Em PostgreSQL, prefira **constraints check** sobre enums nativos (mais flexível para alterar):

```php
$table->string('status', 20);

// Ou usando constraint
DB::statement("
    ALTER TABLE imoveis
    ADD CONSTRAINT check_status
    CHECK (status IN ('disponivel', 'reservado', 'vendido', 'pausado'))
");
```

No PHP, use enums nativos (PHP 8.1+):

```php
enum ImovelStatus: string
{
    case Disponivel = 'disponivel';
    case Reservado = 'reservado';
    case Vendido = 'vendido';
    case Pausado = 'pausado';
}
```

E faça cast automático no Model:

```php
protected $casts = [
    'status' => ImovelStatus::class,
];
```

---

## Convenções específicas

### Valores monetários

Sempre em **centavos**, como `integer`. Coluna sufixada com `_centavos`:

```php
$table->integer('preco_centavos');
$table->integer('valor_total_centavos');
```

**Por quê:** floating point causa bugs sutis em cálculos financeiros. R$ 0.10 + R$ 0.20 ≠ R$ 0.30 em float.

### CNPJ

```php
$table->string('cnpj', 18);
```

**Por quê 18:** suporta a máscara `12.345.678/0001-90` (18 chars com máscara) E o formato alfanumérico futuro `AB12C3D4/0E9F-45` (18 chars).

Armazenar **sem máscara** (apenas dígitos/letras), normalizando no input.

### CEP, Telefone

```php
$table->string('cep', 9);                    // 00000-000
$table->string('telefone', 20);              // suporta DDI + máscara
```

Armazenar normalizado (sem máscara), formatar no display.

### URLs

```php
$table->string('url', 2048);                 // limite de URL HTTP
```

### E-mail

```php
$table->string('email', 255);                // padrão
```

E-mail é único **por tenant**, não global:

```php
$table->unique(['tenant_id', 'email']);
```

---

## Foreign Keys

**Sempre** declare:

```php
$table->foreign('tenant_id')
    ->references('id')
    ->on('tenants')
    ->onDelete('cascade');                   // ou 'set null', conforme caso
```

### Comportamentos de delete

| Comportamento | Quando usar                              |
|---------------|------------------------------------------|
| `cascade`     | Delete pai → filhos vão junto (imovel_fotos quando imovel é deletado) |
| `set null`    | Delete pai → filho fica órfão mas válido |
| `restrict`    | Bloqueia delete se houver filhos         |

**Padrão para tenant_id:** `cascade` (deletar tenant → deleta dados).

---

## Seeds

Use seeds para:

- Dados de plataforma (planos, slugs reservados)
- Dados de teste em desenvolvimento

**Não use seeds para:**

- Dados de tenant específico em produção
- Dados que mudam frequentemente

```php
// database/seeders/PlanSeeder.php
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::firstOrCreate(['slug' => 'gratuito'], [
            'name' => 'Gratuito',
            'price_centavos' => 0,
            'max_imoveis' => 3,
            'max_corretores' => 1,
        ]);

        Plan::firstOrCreate(['slug' => 'basico'], [
            'name' => 'Básico',
            'price_centavos' => 9900,        // R$ 99,00
            'max_imoveis' => 30,
            'max_corretores' => 3,
        ]);

        // ...
    }
}
```

---

## Tabelas críticas (referência)

Tabelas centrais do sistema, que aparecem em quase tudo:

| Tabela              | Descrição                                    | tenant_id? |
|---------------------|----------------------------------------------|-----------|
| `tenants`           | Imobiliárias                                 | ❌         |
| `users`             | Admins, corretores, clientes finais          | ✅ (nullable para super admin)|
| `plans`             | Planos disponíveis                           | ❌         |
| `subscriptions`     | Assinatura Pagar.me de cada tenant           | ✅         |
| `imoveis`           | Imóveis cadastrados                          | ✅         |
| `imovel_fotos`      | Fotos de imóveis                             | ✅         |
| `agendamentos`      | Visitas agendadas                            | ✅         |
| `disponibilidades`  | Horários de cada corretor                    | ✅         |
| `bairros`           | Bairros cadastrados pelo tenant              | ✅         |
| `audit_logs`        | Auditoria de ações sensíveis                 | ✅ (nullable para super admin)|
| `auth_anomalies`    | Detecção de anomalias em auth                | ✅ (nullable)|
| `refresh_tokens`    | Refresh tokens de auth                       | ✅         |
| `webhook_events`    | Eventos recebidos do Pagar.me                | ❌         |

---

## Referências

- ADR principal: `ADR-001` (Database Strategy)
- ADRs relacionadas: `ADR-022` (Security), `ADR-025` (Project Patterns)
- Outras conventions: `02-folder-structure.md`, `03-naming.md`
