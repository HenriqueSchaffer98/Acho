# Convenção: Arquitetura e Camadas

Esta convenção detalha o que `ADR-025` decidiu. Em conflito, a ADR vence.

---

## Camadas da aplicação

```
HTTP Request
     ↓
Controller (thin)
     ↓
Form Request (validação + autorização)
     ↓
Service (lógica de negócio)
     ↓
Model (Eloquent)
     ↓
Database (PostgreSQL com RLS)
```

### Controller

**Responsabilidade:** receber request, delegar para Service, retornar response.

**O que NÃO deve ter no Controller:**

- Lógica de negócio
- Queries diretas (use Service)
- Validação manual (use Form Request)
- Manipulação direta de Models (use Service)

**Exemplo correto:**

```php
class ImovelController extends Controller
{
    public function __construct(
        private readonly CreateImovelService $createImovel,
    ) {}

    public function store(StoreImovelRequest $request): JsonResponse
    {
        $imovel = $this->createImovel->execute(
            $request->toData()
        );

        return response()->json(
            ImovelResource::make($imovel),
            Response::HTTP_CREATED
        );
    }
}
```

**Exemplo incorreto (não fazer):**

```php
class ImovelController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // ❌ Validação no Controller
        $validated = $request->validate([
            'titulo' => 'required',
        ]);

        // ❌ Lógica de negócio no Controller
        if ($request->user()->imoveis()->count() >= 30) {
            throw new \Exception('Limite atingido');
        }

        // ❌ Manipulação direta de Model
        $imovel = Imovel::create([
            'tenant_id' => app('currentTenant')->id,
            ...$validated,
        ]);

        return response()->json($imovel);
    }
}
```

### Form Request

**Responsabilidade:** validar input, autorizar, transformar em DTO se aplicável.

**Padrão:**

```php
class StoreImovelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Imovel::class);
    }

    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:200'],
            'preco' => ['required', 'integer', 'min:0'],
            'tipo' => ['required', 'in:venda,aluguel'],
            'corretor_id' => ['required', 'uuid', 'exists:users,id'],
        ];
    }

    public function toData(): CreateImovelData
    {
        return CreateImovelData::from($this->validated());
    }
}
```

### Service

**Responsabilidade:** lógica de negócio. Pode chamar outros Services, dispatch de eventos, transações de banco.

**Padrão:**

```php
class CreateImovelService
{
    public function __construct(
        private readonly ImageProcessingService $imageProcessor,
    ) {}

    public function execute(CreateImovelData $data): Imovel
    {
        return DB::transaction(function () use ($data) {
            $imovel = Imovel::create([
                'tenant_id' => app('currentTenant')->id,
                'titulo' => $data->titulo,
                'preco_centavos' => $data->precoCentavos,
                'tipo' => $data->tipo,
                'corretor_id' => $data->corretorId,
            ]);

            ImovelCreated::dispatch($imovel);

            return $imovel;
        });
    }
}
```

**Características:**

- Stateless (não mantém estado entre chamadas)
- Single responsibility por método público (`execute`, `update`, `delete`)
- Recebe DTOs, retorna Models ou DTOs
- Constructor injection para dependências

### Model

**Responsabilidade:** representar entidade, definir relacionamentos, scopes e casts.

**O que pode ter no Model:**

- Relacionamentos (`hasMany`, `belongsTo`, etc.)
- Casts (`integer`, `boolean`, `array`, custom casts)
- Scopes simples e reutilizáveis
- Accessors e mutators simples
- Constantes de status (enum-like)

**O que NÃO deve ter no Model:**

- Lógica de negócio complexa (vai pro Service)
- Validação (vai pro Form Request)
- Side effects (eventos vão pra Listener)
- Queries customizadas reutilizáveis (vai pro Scope ou Service)

**Exemplo:**

```php
class Imovel extends BaseTenantModel
{
    protected $fillable = [
        'titulo',
        'preco_centavos',
        'tipo',
        'status',
        'corretor_id',
    ];

    protected $casts = [
        'preco_centavos' => 'integer',
        'tipo' => ImovelTipo::class,
        'status' => ImovelStatus::class,
    ];

    public function corretor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corretor_id');
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(ImovelFoto::class)->orderBy('ordem');
    }

    public function agendamentos(): HasMany
    {
        return $this->hasMany(Agendamento::class);
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ImovelStatus::Disponivel,
            ImovelStatus::Reservado,
        ]);
    }
}
```

---

## Padrões obrigatórios

### Multi-tenancy (defesa em profundidade)

Toda Model de negócio:

1. Estende `BaseTenantModel`
2. Tem coluna `tenant_id` na migration
3. Tabela tem RLS habilitado

```php
abstract class BaseTenantModel extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function (Model $model) {
            if (empty($model->tenant_id) && app()->bound('currentTenant')) {
                $model->tenant_id = app('currentTenant')->id;
            }
        });
    }
}
```

### DTOs com Spatie Laravel Data

**Quando usar:**

- Input de Service complexo
- Output estruturado de Service
- Payload de evento
- Response de API onde consistência importa

**Quando NÃO usar:**

- Operação trivial (DTO seria boilerplate sem ganho)
- Models simples para uso interno

```php
class CreateImovelData extends Data
{
    public function __construct(
        public string $titulo,
        public int $precoCentavos,
        public ImovelTipo $tipo,
        public string $corretorId,
        public ?string $descricao = null,
    ) {}
}
```

### Eventos

Para desacoplar side-effects:

```php
// Evento
class ImovelCreated
{
    use Dispatchable;

    public function __construct(public readonly Imovel $imovel) {}
}

// Listener
class NotificarCorretorImovelCriado implements ShouldQueue
{
    public function handle(ImovelCreated $event): void
    {
        $event->imovel->corretor->notify(
            new ImovelAtribuidoNotification($event->imovel)
        );
    }
}

// Registro em EventServiceProvider
protected $listen = [
    ImovelCreated::class => [
        NotificarCorretorImovelCriado::class,
        AuditLogImovelCreated::class,
        InvalidarCacheImoveis::class,
    ],
];
```

### Policies

Para autorização granular:

```php
class ImovelPolicy
{
    public function view(User $user, Imovel $imovel): bool
    {
        return $user->tenant_id === $imovel->tenant_id;
    }

    public function update(User $user, Imovel $imovel): bool
    {
        if ($user->tenant_id !== $imovel->tenant_id) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('corretor')) {
            return $user->id === $imovel->corretor_id;
        }

        return false;
    }
}
```

---

## O que NÃO usamos

### Repository Pattern

**Não use.** Eloquent já é repository pattern. Adicionar abstração extra é redundância.

```php
// ❌ NÃO FAZER
class ImovelRepository
{
    public function find(string $id): ?Imovel
    {
        return Imovel::find($id);  // redundante
    }
}

// ✅ FAZER
$imovel = Imovel::find($id);
```

### Active Record Pesado

**Não coloque lógica de negócio no Model.**

```php
// ❌ NÃO FAZER (Model com regra de negócio)
class Imovel extends Model
{
    public function publicar(): void
    {
        $this->status = 'disponivel';
        $this->save();

        // 50 linhas de side effects aqui...
        Mail::send(...);
        Cache::forget(...);
        Log::info(...);
    }
}

// ✅ FAZER (Service)
class PublicarImovelService
{
    public function execute(Imovel $imovel): void
    {
        $imovel->update(['status' => ImovelStatus::Disponivel]);

        ImovelPublicado::dispatch($imovel);
    }
}
```

### Singletons globais customizados

**Não crie classes globais para "compartilhar estado".** Use container do Laravel ou request lifecycle.

---

## Referências

- ADR principal: `ADR-025` (Project Patterns)
- ADRs relacionadas: `ADR-001` (Database), `ADR-019` (Tech Stack)
- Outras conventions: `02-folder-structure.md`, `03-naming.md`
