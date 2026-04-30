# Convenção: Tratamento de Erros e Logging

## Princípios

1. **Falhe explícito.** Erro silencioso é o pior tipo de bug.
2. **Mensagens diferentes para usuário e logs.** Usuário vê mensagem clara e segura. Log tem detalhes técnicos completos.
3. **Logs estruturados.** Sempre com contexto (`tenant_id`, `user_id`, `action`).
4. **Nunca vaze info sensível em mensagens** (existência de e-mail, detalhes de erro interno).
5. **Captura tudo, decida depois.** Sentry captura, você decide o que importa.

---

## Hierarquia de exceptions customizadas

```
App\Exceptions\Handler
  └── Renderiza JSON para API, página para web

Exceptions de domínio (estendem Exception)
  ├── TenantException
  │   ├── TenantNotFoundException
  │   └── TenantSuspendedException
  │
  ├── PlanLimitException
  │   ├── ImovelLimitExceededException
  │   └── CorretorLimitExceededException
  │
  ├── PaymentException
  │   ├── PaymentFailedException
  │   └── WebhookValidationException
  │
  └── AuthException
      ├── InvalidCredentialsException
      ├── AccountLockedException
      └── TokenExpiredException
```

### Estrutura de exception customizada

```php
namespace App\Exceptions\Tenant;

use Exception;
use Symfony\Component\HttpFoundation\Response;

class TenantNotFoundException extends Exception
{
    public function __construct(string $slug)
    {
        parent::__construct("Tenant com slug '{$slug}' não encontrado.");
    }

    public function render(): Response
    {
        if (request()->expectsJson()) {
            return response()->json([
                'message' => 'Imobiliária não encontrada.',
                'code' => 'TENANT_NOT_FOUND',
            ], 404);
        }

        return response()->view('errors.tenant-not-found', [], 404);
    }
}
```

### Exception com contexto

```php
namespace App\Exceptions\Plan;

use Exception;

class ImovelLimitExceededException extends Exception
{
    public function __construct(
        public readonly int $limite,
        public readonly int $atual,
    ) {
        parent::__construct(
            "Limite de imóveis atingido ({$atual} de {$limite})."
        );
    }

    public function render(): Response
    {
        return response()->json([
            'message' => "Você atingiu o limite de {$this->limite} imóveis do seu plano.",
            'code' => 'PLAN_LIMIT_EXCEEDED',
            'meta' => [
                'limite' => $this->limite,
                'atual' => $this->atual,
            ],
        ], 422);
    }
}
```

### Quando criar exception customizada

✅ **Crie quando:**

- Erro de domínio que tem tratamento específico (ex: limite de plano)
- Resposta HTTP específica para esse erro
- Cliente precisa diferenciar do genérico
- Serve de "tag" para filtrar nos logs

❌ **Não crie quando:**

- Erro genuinamente inesperado (use Exception padrão)
- Erro de validação de input (use FormRequest)
- Erro de framework (HTTP exceptions já existem)

---

## Handler centralizado

```php
namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontReport = [
        // Exceptions que não vão para Sentry (esperadas)
        TenantNotFoundException::class,
        InvalidCredentialsException::class,
        ImovelLimitExceededException::class,
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // Adiciona contexto ao Sentry
            if (app()->bound('sentry') && app()->bound('currentTenant')) {
                app('sentry')->setTag('tenant_id', app('currentTenant')->id);
                app('sentry')->setTag('tenant_slug', app('currentTenant')->slug);
            }

            if (auth()->check()) {
                app('sentry')->setUser([
                    'id' => auth()->id(),
                ]);
            }
        });

        $this->renderable(function (Throwable $e, $request) {
            // Renderização customizada para JSON
            if ($request->expectsJson()) {
                return $this->renderJsonError($e);
            }
        });
    }

    private function renderJsonError(Throwable $e): JsonResponse
    {
        if (config('app.debug')) {
            return response()->json([
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => collect($e->getTrace())->take(5),
            ], 500);
        }

        return response()->json([
            'message' => 'Erro interno do servidor.',
            'code' => 'INTERNAL_ERROR',
        ], 500);
    }
}
```

---

## Mensagens de erro

### Para o usuário final

**Princípios:**

- Linguagem clara, sem jargão técnico
- Sem detalhes que vazem info sensível
- Sugerir ação quando possível
- Tom amigável, não acusatório

```
✅ Bom
"Não conseguimos processar seu pagamento. Verifique os dados do cartão."
"E-mail ou senha inválidos."
"Você atingiu o limite de imóveis do seu plano."

❌ Ruim
"Erro 500: SQLSTATE[23000]: Integrity constraint violation"
"Usuário existe mas senha está incorreta"  // vaza info
"Falha desconhecida"                        // sem contexto
```

### Em logs

**Princípios:**

- Contexto completo (IDs, parâmetros)
- Stack trace quando inesperado
- Fácil de buscar (use chaves consistentes)

```php
Log::error('Falha ao processar pagamento', [
    'tenant_id' => $tenant->id,
    'subscription_id' => $subscription->id,
    'pagarme_transaction_id' => $transaction->id,
    'amount_cents' => $transaction->amount,
    'error_code' => $error->getCode(),
    'error_message' => $error->getMessage(),
]);
```

---

## Logging

### Levels e quando usar

| Level     | Quando usar                                              |
|-----------|----------------------------------------------------------|
| `debug`   | Detalhes para investigação (desabilitado em produção)    |
| `info`    | Eventos normais que valem registrar (login, criação)     |
| `notice`  | Notável mas não anormal (rate limit acionado)            |
| `warning` | Comportamento inesperado mas recuperável (retry funcionou)|
| `error`   | Erro real, fluxo afetado, mas sistema continua           |
| `critical`| Sistema parcialmente quebrado (ex: banco fora)           |
| `alert`   | Ação imediata necessária                                 |
| `emergency`| Sistema inutilizável                                    |

### Em produção

- `info` e acima vão para arquivos de log
- `warning` e acima vão para Sentry
- `critical` e acima geram alerta imediato

### Logs estruturados sempre

```php
// ❌ Ruim
Log::info('Usuário X criou imóvel Y');

// ✅ Bom
Log::info('Imóvel criado', [
    'user_id' => $user->id,
    'tenant_id' => $tenant->id,
    'imovel_id' => $imovel->id,
    'titulo' => $imovel->titulo,
]);
```

**Por quê:** logs estruturados permitem busca, agregação, alertas. Texto livre não.

### Contexto global automático

Configure middleware para adicionar contexto a todos os logs da request:

```php
// app/Http/Middleware/AddRequestContextToLogs.php
class AddRequestContextToLogs
{
    public function handle(Request $request, Closure $next)
    {
        $context = [
            'request_id' => Str::uuid()->toString(),
            'ip' => $request->ip(),
        ];

        if (app()->bound('currentTenant')) {
            $context['tenant_id'] = app('currentTenant')->id;
        }

        if (auth()->check()) {
            $context['user_id'] = auth()->id();
        }

        Log::shareContext($context);

        return $next($request);
    }
}
```

---

## Sentry

### Setup

```env
SENTRY_LARAVEL_DSN=https://...@sentry.io/...
SENTRY_TRACES_SAMPLE_RATE=0.1
```

### O que captura

- Errors e exceptions não capturadas
- Logs `warning` e acima
- Slow queries (configurável)
- Performance traces (10% das requests por padrão)

### Filtros importantes

```php
// config/sentry.php
'before_send' => function (\Sentry\Event $event): ?\Sentry\Event {
    // Não enviar em ambiente local
    if (app()->environment('local')) {
        return null;
    }

    // Filtrar exceptions esperadas
    $exception = $event->getExceptions()[0] ?? null;
    if ($exception && in_array($exception->getType(), [
        TenantNotFoundException::class,
        InvalidCredentialsException::class,
    ])) {
        return null;
    }

    return $event;
},
```

### Não logue dados sensíveis

```php
// ❌ NUNCA
Log::info('Login attempt', [
    'password' => $request->password,  // ❌
    'cpf' => $user->cpf,               // ❌
    'card_number' => $card->number,    // ❌
]);

// ✅ Mascarar ou omitir
Log::info('Login attempt', [
    'email' => $request->email,
    'password_length' => strlen($request->password),  // se útil
]);
```

---

## Frontend (React)

### Erros de fetch

```tsx
import { router } from '@inertiajs/react';
import { toast } from '@/Lib/toast';

router.post('/api/imoveis', data, {
  onError: (errors) => {
    if (errors.message) {
      toast.error(errors.message);
    }
  },
  onSuccess: () => {
    toast.success('Imóvel criado!');
  },
});
```

### Error Boundaries

Para falhas de renderização de componente:

```tsx
// resources/js/Components/shared/ErrorBoundary.tsx
import { Component, ReactNode } from 'react';
import * as Sentry from '@sentry/react';

interface Props {
  children: ReactNode;
  fallback?: ReactNode;
}

export class ErrorBoundary extends Component<Props, { hasError: boolean }> {
  state = { hasError: false };

  static getDerivedStateFromError() {
    return { hasError: true };
  }

  componentDidCatch(error: Error, info: React.ErrorInfo) {
    Sentry.captureException(error, { extra: info });
  }

  render() {
    if (this.state.hasError) {
      return this.props.fallback ?? <div>Algo deu errado.</div>;
    }
    return this.props.children;
  }
}
```

Uso em pontos críticos:

```tsx
<ErrorBoundary fallback={<MapaPlaceholder />}>
  <Mapa />
</ErrorBoundary>
```

---

## Padrões a evitar

### Catch silencioso

```php
// ❌ NUNCA
try {
    $service->execute();
} catch (Throwable $e) {
    // engole o erro
}

// ✅ FAZER
try {
    $service->execute();
} catch (PaymentFailedException $e) {
    // tratamento específico
    Log::warning('Pagamento falhou', ['error' => $e->getMessage()]);
    return response()->json(['message' => 'Pagamento falhou.'], 402);
}
// outras exceptions sobem para o Handler central
```

### Mensagem genérica para usuário

```php
// ❌ Ruim
return response()->json(['message' => 'Erro.']);

// ✅ Bom
return response()->json([
    'message' => 'Não foi possível salvar o imóvel. Tente novamente.',
    'code' => 'IMOVEL_SAVE_FAILED',
]);
```

### Vazar exception interna

```php
// ❌ Ruim (em produção)
return response()->json([
    'message' => $exception->getMessage(),  // pode vazar SQL, paths, etc.
]);

// ✅ Bom
report($exception);  // log
return response()->json([
    'message' => 'Erro interno. Tente novamente.',
]);
```

---

## Checklist em código novo

Ao escrever lógica nova, pergunte:

- [ ] Cobri os caminhos de erro previsíveis?
- [ ] Tenho exception customizada se faz sentido?
- [ ] Mensagem ao usuário é clara e segura?
- [ ] Logs têm contexto suficiente para debug?
- [ ] Não estou vazando info sensível?
- [ ] Sentry vai capturar erros inesperados?
- [ ] Frontend trata o erro adequadamente?

---

## Referências

- ADRs relacionadas: `ADR-022` (Security), `ADR-021` (Infrastructure), `ADR-025` (Project Patterns)
- Outras conventions: `05-api-design.md`, `08-testing.md`
